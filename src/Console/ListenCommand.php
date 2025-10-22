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

    /**
     * @param array<int, string> $watchPaths
     * @param array<int, string> $watchIgnorePatterns
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

        if ($workers === 1) {
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

        $this->registerSignalHandlers();

        for ($i = 1; $i <= $workers; $i++) {
            $pid = pcntl_fork();

            if ($pid === -1) {
                $this->output->error($this->getWorkerForkErrorMessage($i));
                $this->cleanup();
                return 1;
            }

            if ($pid === 0) {
                $this->workerPids = [];
                $this->registerChildSignalHandlers();

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
            exit(0);
        });

        pcntl_signal(SIGINT, function (): void {
            $this->output->info('Received SIGINT signal, shutting down...');
            $this->cleanup();
            exit(0);
        });
    }

    protected function registerChildSignalHandlers(): void
    {
        if (!extension_loaded('pcntl')) {
            return;
        }

        pcntl_async_signals(true);

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

        $timeout = time() + 5;
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
