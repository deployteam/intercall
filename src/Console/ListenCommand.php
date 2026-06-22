<?php

declare(strict_types=1);

namespace DeployTeam\Intercall\Console;

use DeployTeam\Intercall\Configuration\SystemRegistry;
use DeployTeam\Intercall\Contracts\Bridge\ConsoleOutput;
use DeployTeam\Intercall\Exceptions\Transport\NoTransportAvailableException;
use DeployTeam\Intercall\Services\RequestListener;
use DeployTeam\Intercall\Transports\Contracts\InboundTransport;
use DeployTeam\Intercall\Transports\Contracts\Transport;

class ListenCommand
{
    /** @var array<int, int> */
    protected array $workerPids = [];

    protected ?int $listenerPid = null;

    protected bool $skipWatch = false;

    protected bool $shouldReload = false;

    /**
     * @param array<int, string> $watchPaths
     * @param array<int, string> $watchIgnorePatterns
     * @param array<int, string>|null $restartCommand
     */
    public function __construct(
        protected RequestListener $listener,
        protected SystemRegistry $systemRegistry,
        protected ConsoleOutput $output,
        protected ?string $basePath = null,
        protected array $watchPaths = [],
        protected array $watchIgnorePatterns = [],
        protected int $watchPollInterval = 1,
        protected int $watchRestartDelay = 1,
        protected ?array $restartCommand = null,
        protected ?string $pidFilePath = null,
        protected int $shutdownTimeout = 5,
    ) {}

    public function execute(): int
    {
        if (!$this->skipWatch && $this->output->option('watch') !== null && $this->output->option('watch') !== false) {
            return $this->handleWithWatch();
        }

        $transportIds = $this->getAvailableTransportIds();

        if ($transportIds === []) {
            $this->output->error('You must register at least an inbound transport');
            return 1;
        }

        $transportId = $this->output->option('transport') ?? '';

        if ($transportId === '') {
            $this->output->error('You must provide a transport ID');
            return 1;
        }

        try {
            $transport = $this->systemRegistry->getLocalSystemConfig()->transports->getById($transportId);
        } catch (NoTransportAvailableException) {
            $this->output->error("The transport id {$transportId} is not registerd");
            return 1;
        }

        if (!$transport instanceof InboundTransport) {
            $this->output->error("The transport id {$transportId} is not an inbound transport");
            return 1;
        }

        if (!$transport->isAvailable()) {
            $this->output->error("Transport '{$transportId}' is not available. Please check the Redis connection configuration.");
            $this->output->error("Tip: Verify host, port, password, and database settings in config/intercall.php");
            return 1;
        }

        $workersOption = $this->output->option('workers', 1);
        assert(is_numeric($workersOption));
        $workers = max(1, (int) $workersOption);

        $this->output->info($this->getStartMessage($workers, $transportId));

        $this->terminateExistingMaster();

        if ($workers === 1) {
            $this->writePidFile();
            $this->registerSignalHandlers();
            $workerId = $this->getWorkerId(1, $transportId);
            $myPid = getmypid();
            $this->output->info($this->getWorkerStartedMessage(1, $myPid));
            $this->listener->listenOnTransport($transport, $workerId);
        }

        if (!extension_loaded('pcntl')) {
            $this->output->error('Multi-worker mode requires the pcntl extension.');
            return 1;
        }

        $this->writePidFile();
        $this->registerSignalHandlers();

        do {
            $this->shouldReload = false;

            for ($i = 1; $i <= $workers; $i++) {
                $pid = pcntl_fork();

                if ($pid === -1) {
                    $this->output->error($this->getWorkerForkErrorMessage($i));
                    $this->cleanup();
                    $this->removePidFile();
                    return 1;
                }

                if ($pid === 0) {
                    $this->workerPids = [];
                    $this->registerChildSignalHandlers();

                    if (method_exists($transport, 'resetConnection')) {
                        $transport->resetConnection();
                    }

                    $workerId = $this->getWorkerId($i, $transportId);
                    $myPid = getmypid();
                    $this->output->info($this->getWorkerStartedMessage($i, $myPid));
                    $this->listener->listenOnTransport($transport, $workerId);
                }

                $this->workerPids[$i] = $pid;
            }

            while (count($this->workerPids) > 0) {
                $status = 0;
                $pid = pcntl_wait($status);

                if ($pid > 0) {
                    $workerId = array_search($pid, $this->workerPids, true);
                    if ($workerId !== false) {
                        unset($this->workerPids[$workerId]);
                        $this->output->warning($this->getWorkerExitedMessage($workerId, $pid));
                    }
                }

                usleep(100000);
            }
        } while ($this->shouldReload);

        $this->removePidFile();

        return 0;
    }

    protected function registerSignalHandlers(): void
    {
        if (!extension_loaded('pcntl')) {
            return;
        }

        pcntl_async_signals(true);

        pcntl_signal(SIGTERM, function (): void {
            $this->output->info('Received SIGTERM signal, shutting down...');
            $this->cleanup();
            $this->removePidFile();
            exit(0);
        }, false);

        pcntl_signal(SIGINT, function (): void {
            $this->output->info('Received SIGINT signal, shutting down...');
            $this->cleanup();
            $this->removePidFile();
            exit(0);
        }, false);

        pcntl_signal(SIGUSR1, function (): void {
            $this->output->info('Received SIGUSR1 signal, reloading...');
            $this->cleanup();
            $this->reExecSelf();
        }, false);
    }

    protected function registerChildSignalHandlers(): void
    {
        if (!extension_loaded('pcntl')) {
            return;
        }

        pcntl_async_signals(false);

        pcntl_signal(SIGTERM, function (): void {
            exit(0);
        });

        pcntl_signal(SIGINT, function (): void {
            exit(0);
        });
    }

    protected function cleanup(): void
    {
        if (!extension_loaded('pcntl')) {
            return;
        }

        foreach ($this->workerPids as $workerId => $pid) {
            $this->output->info($this->getStoppingWorkerMessage($workerId, $pid));
            posix_kill($pid, SIGTERM);
        }

        $timeout = time() + $this->shutdownTimeout;
        while (count($this->workerPids) > 0 && time() < $timeout) {
            $status = 0;
            $pid = pcntl_waitpid(-1, $status, WNOHANG);

            if ($pid > 0) {
                $workerId = array_search($pid, $this->workerPids, true);
                if ($workerId !== false) {
                    unset($this->workerPids[$workerId]);
                }
            }

            usleep(100000);
        }

        foreach ($this->workerPids as $workerId => $pid) {
            $this->output->warning($this->getForceKillingWorkerMessage($workerId, $pid));
            posix_kill($pid, SIGKILL);
        }

        $this->workerPids = [];
    }

    protected function writePidFile(): void
    {
        if ($this->pidFilePath === null) {
            return;
        }

        $directory = dirname($this->pidFilePath);

        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        file_put_contents($this->pidFilePath, (string) getmypid());
    }

    protected function terminateExistingMaster(): void
    {
        if ($this->pidFilePath === null || !file_exists($this->pidFilePath) || !extension_loaded('posix')) {
            return;
        }

        $existingPid = (int) trim((string) @file_get_contents($this->pidFilePath));

        if ($existingPid <= 0 || $existingPid === getmypid() || !posix_kill($existingPid, 0)) {
            return;
        }

        $this->safeWarning("Stale master found at PID {$existingPid}, terminating before start");
        posix_kill($existingPid, SIGTERM);

        for ($i = 0; $i < 50; $i++) {
            usleep(100000);
            if (!posix_kill($existingPid, 0)) {
                return;
            }
        }

        $this->safeWarning("Stale master at PID {$existingPid} did not exit, sending SIGKILL");
        posix_kill($existingPid, SIGKILL);

        for ($i = 0; $i < 20; $i++) {
            usleep(100000);
            if (!posix_kill($existingPid, 0)) {
                return;
            }
        }
    }

    protected function safeWarning(string $message): void
    {
        try {
            $this->output->warning($message);
        } catch (\Throwable) {
        }
    }

    protected function removePidFile(): void
    {
        if ($this->pidFilePath !== null && file_exists($this->pidFilePath)) {
            @unlink($this->pidFilePath);
        }
    }

    protected function reExecSelf(): void
    {
        if ($this->restartCommand === null || $this->restartCommand === []) {
            $this->output->warning('No restart command configured, exiting instead of reloading.');
            $this->removePidFile();
            exit(0);
        }

        $this->output->info('Re-executing process...');

        $command = $this->restartCommand;
        $binary = array_shift($command);

        pcntl_exec($binary, $command);

        $this->output->error('Failed to re-exec, exiting.');
        $this->removePidFile();
        exit(1);
    }

    protected function getWorkerPrefix(): string
    {
        return 'worker';
    }

    protected function getWorkerId(int $workerNumber, ?string $transportId = null): string
    {
        $id = $this->getWorkerPrefix() . "-{$workerNumber}";
        if ($transportId !== null) {
            $id .= "-{$transportId}";
        }
        return $id;
    }

    protected function getStartMessage(int $workers, ?string $transportId = null): string
    {
        $prefix = $this->getWorkerPrefix();
        $message = "Starting {$workers} {$prefix}(s)";
        if ($transportId !== null) {
            $message .= " for transport '{$transportId}'";
        }
        $message .= '...';
        return $message;
    }

    protected function getWorkerStartedMessage(int $workerNumber, int $pid): string
    {
        $prefix = $this->getWorkerPrefix();
        return ucfirst($prefix) . " {$workerNumber} started (PID: {$pid})";
    }

    protected function getWorkerForkErrorMessage(int $workerNumber): string
    {
        $prefix = $this->getWorkerPrefix();
        return "Failed to fork {$prefix} {$workerNumber}";
    }

    protected function getWorkerExitedMessage(int|string $workerId, int $pid): string
    {
        $prefix = $this->getWorkerPrefix();
        return ucfirst($prefix) . " {$workerId} exited (PID: {$pid})";
    }

    protected function getStoppingWorkerMessage(int|string $workerId, int $pid): string
    {
        $prefix = $this->getWorkerPrefix();
        return "Stopping {$prefix} {$workerId} (PID: {$pid})...";
    }

    protected function getForceKillingWorkerMessage(int|string $workerId, int $pid): string
    {
        $prefix = $this->getWorkerPrefix();
        return "Force killing {$prefix} {$workerId} (PID: {$pid})...";
    }

    protected function getAvailableTransportIds(): array
    {
        $currentSystem = $this->systemRegistry->getLocalSystemConfig();
        $transports = array_filter(
            $currentSystem->transports->getTransports(),
            fn(Transport $transport) => $transport instanceof InboundTransport,
        );

        return array_map(
            fn(InboundTransport $transport) => $transport->getId(),
            $transports,
        );
    }

    protected function handleWithWatch(): int
    {
        if (!extension_loaded('pcntl')) {
            $this->output->error('Watch mode requires the pcntl extension.');
            return 1;
        }

        if ($this->restartCommand !== null) {
            return $this->handleWithWatchProcess();
        }

        return $this->handleWithWatchFork();
    }

    /** @var resource|null */
    protected mixed $watchProcess = null;

    protected function handleWithWatchProcess(): int
    {
        assert($this->restartCommand !== null);

        $watchPaths = $this->getWatchPaths();
        $lastHashes = $this->getDirectoryHashes($watchPaths);

        $this->output->info('🔄 File watching enabled (process mode). Monitoring: ' . implode(', ', $watchPaths));
        $this->output->info('💡 Press Ctrl+C to stop');
        $this->output->newLine();

        $this->registerProcessWatcherSignalHandlers();

        while (true) {
            $this->output->info('🚀 Starting listener process...');

            $descriptors = [
                0 => ['pipe', 'r'],
                1 => ['file', 'php://stdout', 'w'],
                2 => ['file', 'php://stderr', 'w'],
            ];

            $this->watchProcess = proc_open($this->restartCommand, $descriptors, $pipes);

            if (!is_resource($this->watchProcess)) {
                $this->output->error('Failed to start listener process');
                return 1;
            }

            fclose($pipes[0]);

            while (true) {
                sleep($this->watchPollInterval);

                $status = proc_get_status($this->watchProcess);

                if (!$status['running']) {
                    $this->output->warning('⚠️  Listener process exited unexpectedly. Restarting...');
                    proc_close($this->watchProcess);
                    $this->watchProcess = null;
                    break;
                }

                $currentHashes = $this->getDirectoryHashes($watchPaths);

                if ($currentHashes !== $lastHashes) {
                    $this->output->info('📝 File changes detected. Restarting listener...');
                    $this->stopWatchProcess();
                    $lastHashes = $currentHashes;
                    sleep($this->watchRestartDelay);
                    break;
                }
            }
        }

        return 0;
    }

    protected function registerProcessWatcherSignalHandlers(): void
    {
        pcntl_async_signals(true);

        pcntl_signal(SIGTERM, function (): void {
            $this->output->info('Received SIGTERM signal, shutting down watcher...');
            $this->stopWatchProcess();
            exit(0);
        });

        pcntl_signal(SIGINT, function (): void {
            $this->output->info('Received SIGINT signal, shutting down watcher...');
            $this->stopWatchProcess();
            exit(0);
        });
    }

    protected function stopWatchProcess(): void
    {
        if ($this->watchProcess === null || !is_resource($this->watchProcess)) {
            return;
        }

        $status = proc_get_status($this->watchProcess);

        if (!$status['running']) {
            proc_close($this->watchProcess);
            $this->watchProcess = null;
            return;
        }

        $pid = $status['pid'];
        posix_kill($pid, SIGTERM);

        $timeout = time() + 5;
        while (time() < $timeout) {
            $status = proc_get_status($this->watchProcess);
            if (!$status['running']) {
                proc_close($this->watchProcess);
                $this->watchProcess = null;
                return;
            }
            usleep(100000);
        }

        posix_kill($pid, SIGKILL);
        proc_close($this->watchProcess);
        $this->watchProcess = null;
    }

    protected function handleWithWatchFork(): int
    {
        $this->registerWatcherSignalHandlers();

        $watchPaths = $this->getWatchPaths();
        $lastHashes = $this->getDirectoryHashes($watchPaths);

        $this->output->info('🔄 File watching enabled. Monitoring: ' . implode(', ', $watchPaths));
        $this->output->info('💡 Press Ctrl+C to stop');
        $this->output->newLine();

        while (true) {
            $this->listenerPid = pcntl_fork();

            if ($this->listenerPid === -1) {
                $this->output->error('Failed to fork listener process');
                return 1;
            }

            if ($this->listenerPid === 0) {
                $this->skipWatch = true;
                exit($this->execute());
            }

            while (true) {
                sleep($this->watchPollInterval);

                $status = 0;
                $result = pcntl_waitpid($this->listenerPid, $status, WNOHANG);

                if ($result === $this->listenerPid) {
                    $this->output->warning('⚠️  Listener process exited unexpectedly. Restarting...');
                    break;
                }

                $currentHashes = $this->getDirectoryHashes($watchPaths);

                if ($currentHashes !== $lastHashes) {
                    $this->output->info('📝 File changes detected. Restarting workers...');
                    $this->stopListener();
                    $lastHashes = $currentHashes;
                    sleep($this->watchRestartDelay);
                    break;
                }
            }
        }

        return 0;
    }

    protected function registerWatcherSignalHandlers(): void
    {
        pcntl_async_signals(true);

        pcntl_signal(SIGTERM, function (): void {
            $this->output->info('Received SIGTERM signal, shutting down watcher...');
            $this->stopListener();
            exit(0);
        });

        pcntl_signal(SIGINT, function (): void {
            $this->output->info('Received SIGINT signal, shutting down watcher...');
            $this->stopListener();
            exit(0);
        });
    }

    protected function stopListener(): void
    {
        if ($this->listenerPid === null) {
            return;
        }

        posix_kill($this->listenerPid, SIGTERM);

        $timeout = time() + 5;
        while (time() < $timeout) {
            $status = 0;
            $result = pcntl_waitpid($this->listenerPid, $status, WNOHANG);

            if ($result === $this->listenerPid) {
                $this->listenerPid = null;
                return;
            }

            usleep(100000);
        }

        posix_kill($this->listenerPid, SIGKILL);
        pcntl_waitpid($this->listenerPid, $status);
        $this->listenerPid = null;
    }

    /** @return array<int, string> */
    protected function getWatchPaths(): array
    {
        if ($this->basePath === null || $this->watchPaths === []) {
            return [];
        }

        $paths = array_map(
            fn(string $path) => $this->basePath . '/' . ltrim($path, '/'),
            $this->watchPaths
        );

        return array_filter($paths, fn(string $path) => file_exists($path));
    }

    /**
     * @param array<int, string> $paths
     * @return array<string, string>
     */
    protected function getDirectoryHashes(array $paths): array
    {
        $hashes = [];

        foreach ($paths as $path) {
            if (is_file($path)) {
                if (!$this->shouldIgnoreFile($path)) {
                    $hashes[$path] = md5_file($path) ?: '';
                }
            } elseif (is_dir($path)) {
                $files = new \RecursiveIteratorIterator(
                    new \RecursiveDirectoryIterator($path, \RecursiveDirectoryIterator::SKIP_DOTS),
                    \RecursiveIteratorIterator::LEAVES_ONLY
                );

                foreach ($files as $file) {
                    if ($file->isFile() && $file->getExtension() === 'php') {
                        $filePath = $file->getPathname();
                        if (!$this->shouldIgnoreFile($filePath)) {
                            $hashes[$filePath] = md5_file($filePath) ?: '';
                        }
                    }
                }
            }
        }

        return $hashes;
    }

    protected function shouldIgnoreFile(string $filePath): bool
    {
        foreach ($this->watchIgnorePatterns as $pattern) {
            if (fnmatch($pattern, $filePath)) {
                return true;
            }
        }

        return false;
    }
}
