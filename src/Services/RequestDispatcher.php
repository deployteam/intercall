<?php

declare(strict_types=1);

namespace DeployTeam\Intercall\Services;

use DeployTeam\Intercall\Configuration\RemoteSystemConfig;
use DeployTeam\Intercall\Configuration\SystemRegistry;
use DeployTeam\Intercall\Contracts\Bridge\Logger;
use DeployTeam\Intercall\Contracts\IntercallEvent;
use DeployTeam\Intercall\Enums\AsyncStatus;
use DeployTeam\Intercall\Enums\RequestType;
use DeployTeam\Intercall\Exceptions\Request\RequestFailedException;
use DeployTeam\Intercall\Exceptions\Request\RequestTimeoutException;
use DeployTeam\Intercall\Transports\Contracts\OutboundTransport;
use DeployTeam\Intercall\Transports\Contracts\SupportsDirectResponse;
use DeployTeam\Intercall\Transports\Contracts\TransportHasPrefix;
use DeployTeam\Intercall\Transports\HttpOutboundTransport;
use DeployTeam\Intercall\Transports\Results\SyncTransportResult;
use Throwable;

class RequestDispatcher
{
    /** @param array<string, mixed> $config */
    public function __construct(
        protected TransportManager $transportManager,
        protected Logger $logger,
        protected IntercallAuth $auth,
        protected RateLimiter $rateLimiter,
        protected AsyncRequestManager $asyncManager,
        protected MessageSerializer $serializer,
        protected SystemRegistry $systemRegistry,
        protected HeartbeatChecker $heartbeatChecker,
        protected array $config,
    ) {}

    /** @param IntercallEvent<array<string, mixed>> $event */
    public function dispatch(string $targetSystem, IntercallEvent $event, RequestType $type): mixed
    {
        $currentSystem = $this->systemRegistry->getLocalSystemConfig();
        $requestId = $this->generateUuid();
        $envelope = $this->createEnvelope($requestId, $targetSystem, $event, $type);

        try {
            $this->rateLimiter->attempt($currentSystem->name);
        } catch (Throwable $e) {
            $this->logError('Rate limit exceeded', [
                'request_id' => $requestId,
                'event' => $event->getEventName(),
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }

        return match ($type) {
            RequestType::SYNC => $this->dispatchSync($requestId, $targetSystem, $envelope, $event),
            RequestType::ASYNC => $this->dispatchAsync($requestId, $targetSystem, $envelope),
            RequestType::FIRE_AND_FORGET => $this->dispatchForget($requestId, $targetSystem, $envelope),
        };
    }

    /**
     * @param IntercallEvent<array<string, mixed>> $event
     * @return array<string, mixed>
     */
    protected function createEnvelope(
        string $requestId,
        string $targetSystem,
        IntercallEvent $event,
        RequestType $type,
    ): array {
        $currentSystem = $this->systemRegistry->getLocalSystemConfig();
        $targetSystemConfig = $this->systemRegistry->getRemoteSystemConfig($targetSystem);

        $sharedSecret = $targetSystemConfig->token;
        $sourceSystemName = $currentSystem->name;

        $compressionConfig = $this->config['compression'] ?? [];
        assert(is_array($compressionConfig));
        $serializationFormat = $compressionConfig['format'] ?? 'msgpack';
        assert(is_string($serializationFormat));

        return [
            'id' => $requestId,
            'type' => $type->value,
            'source_system' => $sourceSystemName,
            'target_system' => $targetSystem,
            'event_name' => $event->getEventName(),
            'event_class' => $event::class,
            'payload' => $event->getPayload(),
            'timestamp' => time(),
            'serialization' => $serializationFormat,
            'auth_token' => $this->auth->generateToken($sharedSecret, [
                'request_id' => $requestId,
                'source_system' => $sourceSystemName,
                'target_system' => $targetSystem,
            ]),
        ];
    }

    /**
     * @param array<string, mixed> $envelope
     * @param IntercallEvent<array<string, mixed>> $event
     */
    protected function dispatchSync(
        string $requestId,
        string $targetSystem,
        array $envelope,
        IntercallEvent $event,
    ): mixed {
        $message = array_merge($envelope, [
            'request_id' => $requestId,
            'event_name' => $event->getEventName(),
            'is_async' => false,
        ]);

        $systemConfig = $this->systemRegistry->getRemoteSystemConfig($targetSystem);
        $transports = $systemConfig->transports->getTransports();

        if ($transports === []) {
            throw RequestFailedException::forSystem($targetSystem, 'No transports configured');
        }

        $lastError = null;

        foreach ($transports as $transport) {
            $timeout = (int) ($transport->getTimeout() ?? 30);

            $message['auth_token'] = $this->auth->generateToken(
                $systemConfig->token,
                [
                    'request_id' => $requestId,
                    'source_system' => $message['source_system'],
                    'target_system' => $targetSystem,
                ]
            );

            try {
                if (!$transport->isAvailable()) {
                    $this->logger->info('[Intercall RequestDispatcher] Transport not available, trying next', [
                        'transport' => $transport::class,
                        'request_id' => $requestId,
                    ]);
                    continue;
                }

                $result = $transport->send($systemConfig->name, $message);

                if (!$result->isSuccess()) {
                    $this->logger->warning('[Intercall RequestDispatcher] Send failed, trying next transport', [
                        'transport' => $transport::class,
                        'request_id' => $requestId,
                        'error' => $result->getError(),
                    ]);
                    $lastError = $result->getError() ?? 'Send failed';
                    continue;
                }

                $this->logger->debug('[Intercall RequestDispatcher] Message sent successfully', [
                    'transport' => $transport::class,
                    'request_id' => $requestId,
                ]);

                if ($result instanceof SyncTransportResult) {
                    $responseData = $result->getData();

                    if (isset($responseData['error'])) {
                        throw new RequestFailedException($responseData['error']);
                    }

                    $this->logger->info('[Intercall RequestDispatcher] Sync request completed via direct response', [
                        'transport' => $transport::class,
                        'request_id' => $requestId,
                    ]);

                    return $responseData['result'] ?? null;
                }

                if (!$transport instanceof SupportsDirectResponse) {
                    throw RequestFailedException::forSystem(
                        $targetSystem,
                        'Transport does not support synchronous requests'
                    );
                }

                $ackChannel = $this->getAckChannel($requestId, $transport);
                $ackTimeout = (int) ($this->config['ack_timeout'] ?? 1);
                $ack = $transport->receiveFromChannel($ackChannel, $ackTimeout);

                if ($ack === null) {
                    $idempotencyConfig = $this->config['idempotency'] ?? [];
                    assert(is_array($idempotencyConfig));
                    $idempotencyEnabled = $idempotencyConfig['enabled'] ?? true;

                    if ($idempotencyEnabled) {
                        $this->logger->warning('[Intercall RequestDispatcher] No ACK received, trying next transport', [
                            'transport' => $transport::class,
                            'request_id' => $requestId,
                            'ack_timeout' => $ackTimeout,
                        ]);
                        $lastError = "No ACK received after {$ackTimeout} seconds";
                        continue;
                    }

                    $this->logger->error('[Intercall RequestDispatcher] No ACK received and idempotency disabled', [
                        'transport' => $transport::class,
                        'request_id' => $requestId,
                        'ack_timeout' => $ackTimeout,
                    ]);
                    throw RequestTimeoutException::afterSeconds($ackTimeout);
                }

                $this->logger->debug('[Intercall RequestDispatcher] ACK received, waiting for response', [
                    'transport' => $transport::class,
                    'request_id' => $requestId,
                ]);

                // ACK received - remote system confirmed it received the request and is processing
                // Now wait for the actual response with the configured timeout
                $responseChannel = $this->getResponseChannel($requestId, $transport);
                $response = $transport->receiveFromChannel($responseChannel, $timeout);

                if ($response === null) {
                    // ACK was received, so remote system HAS the request and is processing it
                    // We must NOT retry - that would cause duplicate processing
                    $this->logger->error('[Intercall RequestDispatcher] Response timeout after ACK received', [
                        'transport' => $transport::class,
                        'request_id' => $requestId,
                        'timeout' => $timeout,
                        'note' => 'Remote system acknowledged receiving the request. Not retrying to avoid duplicates.',
                    ]);
                    throw RequestTimeoutException::afterSeconds($timeout);
                }

                if (!isset($response[1])) {
                    throw new RequestFailedException(
                        'Invalid response from transport: missing message data. Received: ' . json_encode($response)
                    );
                }

                $responseData = $this->serializer->deserialize($response[1]);

                if (isset($responseData['error'])) {
                    throw new RequestFailedException($responseData['error']);
                }

                $this->logger->info('[Intercall RequestDispatcher] Sync request completed via response channel', [
                    'transport' => $transport::class,
                    'request_id' => $requestId,
                ]);

                return $responseData['result'] ?? null;
            } catch (RequestFailedException $e) {
                throw $e;
            } catch (RequestTimeoutException $e) {
                throw $e;
            } catch (Throwable $e) {
                $this->logger->error('[Intercall RequestDispatcher] Exception before send', [
                    'transport' => $transport::class,
                    'request_id' => $requestId,
                    'error' => $e->getMessage(),
                ]);
                $lastError = $e->getMessage();
                continue;
            }
        }

        throw RequestFailedException::forSystem(
            $targetSystem,
            'All transports failed to send. Last error: ' . ($lastError ?? 'Unknown error')
        );
    }

    /** @param array<string, mixed> $envelope */
    protected function dispatchAsync(
        string $requestId,
        string $targetSystem,
        array $envelope,
    ): string {
        try {
            $this->asyncManager->setStatus($requestId, AsyncStatus::PENDING);

            $message = array_merge($envelope, [
                'request_id' => $requestId,
                'is_async' => true,
            ]);

            $heartbeatEnabled = $this->config['heartbeat']['enabled'] ?? true;
            if ($heartbeatEnabled) {
                $isAlive = $this->heartbeatChecker->check($targetSystem);

                if (!$isAlive) {
                    $this->logger->warning('[Intercall] Remote system has no active listeners - switching to HTTP transport', [
                        'system' => $targetSystem,
                        'request_id' => $requestId,
                    ]);

                    $systemConfig = $this->systemRegistry->getRemoteSystemConfig($targetSystem);
                    $httpTransport = $this->findHttpTransport($systemConfig);

                    if ($httpTransport === null) {
                        throw RequestFailedException::forSystem(
                            $targetSystem,
                            'No active listeners and no HTTP fallback available'
                        );
                    }

                    $result = $httpTransport->send($targetSystem, $message);

                    if (!$result->isSuccess()) {
                        throw RequestFailedException::forSystem($targetSystem, $result->getError() ?? 'HTTP transport failed');
                    }

                    return $requestId;
                }
            }

            $result = $this->transportManager->send($targetSystem, $message);

            if (!$result->isSuccess()) {
                throw RequestFailedException::forSystem($targetSystem, $result->getError() ?? 'Unknown error');
            }

            return $requestId;
        } catch (Throwable $e) {
            $this->asyncManager->setStatus($requestId, AsyncStatus::FAILED, [
                'error' => $e->getMessage(),
            ]);

            $this->logError('Async dispatch failed', [
                'request_id' => $requestId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    protected function findHttpTransport(RemoteSystemConfig $systemConfig): ?OutboundTransport
    {
        foreach ($systemConfig->transports->getTransports() as $transport) {
            if ($transport instanceof HttpOutboundTransport && $transport->isAvailable()) {
                return $transport;
            }
        }

        return null;
    }

    /** @param array<string, mixed> $envelope */
    protected function dispatchForget(
        string $requestId,
        string $targetSystem,
        array $envelope,
    ): string {
        try {
            $message = array_merge($envelope, [
                'request_id' => $requestId,
                'is_async' => false,
            ]);

            $result = $this->transportManager->send($targetSystem, $message);

            if (!$result->isSuccess()) {
                throw RequestFailedException::forSystem($targetSystem, $result->getError() ?? 'Unknown error');
            }

            return $requestId;
        } catch (Throwable $e) {
            $this->logError('Fire-and-forget dispatch failed', [
                'request_id' => $requestId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    protected function getAckChannel(
        string $requestId,
        SupportsDirectResponse $transport,
    ): string {
        $prefix = 'intercall';

        if ($transport instanceof TransportHasPrefix) {
            $prefix = $transport->getPrefix();
        }

        return "{$prefix}:ack:{$requestId}";
    }

    protected function getResponseChannel(
        string $requestId,
        SupportsDirectResponse $transport,
    ): string {
        $prefix = 'intercall';

        if ($transport instanceof TransportHasPrefix) {
            $prefix = $transport->getPrefix();
        }

        return "{$prefix}:response:{$requestId}";
    }

    /** @param array<string, mixed> $context */
    protected function logError(string $message, array $context = []): void
    {
        $loggingConfig = $this->config['logging'] ?? [];
        assert(is_array($loggingConfig));
        if ($loggingConfig['enabled'] ?? true) {
            $this->logger->error("[Intercall] {$message}", $context);
        }
    }

    protected function generateUuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
