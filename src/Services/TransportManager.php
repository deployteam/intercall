<?php

declare(strict_types=1);

namespace DeployTeam\Intercall\Services;

use DeployTeam\Intercall\Configuration\RemoteSystemConfig;
use DeployTeam\Intercall\Configuration\SystemRegistry;
use DeployTeam\Intercall\Contracts\Bridge\Logger;
use DeployTeam\Intercall\Exceptions\Transport\NoTransportAvailableException;
use DeployTeam\Intercall\Transports\Contracts\InboundTransport;
use DeployTeam\Intercall\Transports\Contracts\OutboundTransport;
use DeployTeam\Intercall\Transports\Factories\TransportFactory;
use DeployTeam\Intercall\Transports\Results\FailedTransportResult;
use DeployTeam\Intercall\Transports\Results\TransportResult;
use Throwable;

class TransportManager
{
    /** @var array<string, OutboundTransport|InboundTransport> */
    protected array $transports = [];

    /** @param array<string, mixed> $config */
    public function __construct(
        protected Logger $logger,
        protected array $config,
        protected TransportFactory $factory,
        protected SystemRegistry $systemRegistry,
    ) {}

    public function register(OutboundTransport|InboundTransport $transport): void
    {
        $this->transports[$transport->getName()] = $transport;

        $this->logger->debug('[Intercall TransportManager] Transport registered', [
            'transport' => $transport->getName(),
        ]);
    }

    public function get(string $name): OutboundTransport|InboundTransport|null
    {
        return $this->transports[$name] ?? null;
    }

    public function getDefault(): OutboundTransport|InboundTransport
    {
        $transportConfig = $this->config['transport'] ?? [];
        assert(is_array($transportConfig));
        $defaultName = $transportConfig['default'] ?? 'redis';
        assert(is_string($defaultName));
        $transport = $this->get($defaultName);

        if ($transport === null) {
            throw NoTransportAvailableException::notRegistered($defaultName);
        }

        return $transport;
    }

    /** @return array<string, OutboundTransport|InboundTransport> */
    public function all(): array
    {
        return $this->transports;
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $options
     */
    public function send(string $destination, array $payload, array $options = []): TransportResult
    {
        $systemConfig = $this->systemRegistry->getRemoteSystemConfig($destination);

        return $this->sendWithSystemConfig($systemConfig, $payload, $options);
    }

    public function getTransportFor(string $destination): ?OutboundTransport
    {
        $systemConfig = $this->systemRegistry->getRemoteSystemConfig($destination);
        $transportConfigurations = $systemConfig->transports->getTransports();

        foreach ($transportConfigurations as $transport) {
            if ($transport instanceof OutboundTransport && $transport->isAvailable()) {
                return $transport;
            }
        }

        return null;
    }

    public function getListeningTransport(): ?InboundTransport
    {
        foreach ($this->transports as $transport) {
            if ($transport instanceof InboundTransport && $transport->isAvailable()) {
                return $transport;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $options
     */
    private function sendWithSystemConfig(
        RemoteSystemConfig $systemConfig,
        array $payload,
        array $options,
    ): TransportResult {
        $transportConfigurations = $systemConfig->transports->getTransports();

        if ($transportConfigurations === []) {
            throw NoTransportAvailableException::forDispatching($systemConfig->name);
        }

        foreach ($transportConfigurations as $transport) {
            $transportName = $transport::class;

            try {
                if (!$transport->isAvailable()) {
                    $this->logger->info('[Intercall TransportManager] Transport not available, trying next', [
                        'transport' => $transportName,
                        'system' => $systemConfig->name,
                    ]);
                    continue;
                }

                $result = $transport->send($systemConfig->name, $payload, $options);

                if ($result->isSuccess()) {
                    $this->logger->info('[Intercall TransportManager] Message sent successfully', [
                        'transport' => $transportName,
                        'destination' => $systemConfig->name,
                    ]);
                    return $result;
                }

                $this->logger->error('[Intercall TransportManager] Failed to send via available transport', [
                    'transport' => $transportName,
                    'error' => $result->getError(),
                    'system' => $systemConfig->name,
                ]);

                return $result;
            } catch (Throwable $e) {
                $this->logger->error('[Intercall TransportManager] Exception using transport', [
                    'transport_type' => $transport::class,
                    'error' => $e->getMessage(),
                ]);

                return new FailedTransportResult($e->getMessage());
            }
        }

        $this->logger->error('[Intercall TransportManager] Failed to send message via all configured transports', [
            'destination' => $systemConfig->name,
        ]);

        return new FailedTransportResult('All configured transports failed');
    }
}
