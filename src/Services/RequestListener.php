<?php

declare(strict_types=1);

namespace DeployTeam\Intercall\Services;

use DeployTeam\Intercall\Configuration\SystemRegistry;
use DeployTeam\Intercall\Contracts\Bridge\EventDispatcher;
use DeployTeam\Intercall\Contracts\Bridge\Logger;
use DeployTeam\Intercall\Contracts\EventHandler;
use DeployTeam\Intercall\Contracts\IntercallEvent;
use DeployTeam\Intercall\Enums\AsyncStatus;
use DeployTeam\Intercall\Enums\RequestType;
use DeployTeam\Intercall\Events\AsyncResponseReceived;
use DeployTeam\Intercall\Events\BaseIntercallEvent;
use DeployTeam\Intercall\Events\RequestReceived;
use DeployTeam\Intercall\Exceptions\Authentication\InvalidTokenException;
use DeployTeam\Intercall\Exceptions\Authentication\ReplayAttackException;
use DeployTeam\Intercall\Exceptions\Authentication\TokenExpiredException;
use DeployTeam\Intercall\Exceptions\Configuration\MissingTokenException;
use DeployTeam\Intercall\Exceptions\Configuration\SystemNotConfiguredException;
use DeployTeam\Intercall\Transports\Contracts\InboundTransport;
use DeployTeam\Intercall\Transports\Contracts\SupportsDirectResponse;
use DeployTeam\Intercall\Transports\Contracts\TransportHasPrefix;
use LogicException;
use Throwable;

class RequestListener
{
    /** @param array<string, mixed> $config */
    public function __construct(
        protected TransportManager $transportManager,
        protected Logger $logger,
        protected EventDispatcher $eventDispatcher,
        protected IntercallAuth $auth,
        protected RateLimiter $rateLimiter,
        protected AsyncRequestManager $asyncManager,
        protected MessageSerializer $serializer,
        protected EventRegistry $registry,
        protected SystemRegistry $systemRegistry,
        protected IdempotencyManager $idempotency,
        protected ListenerRegistry $listenerRegistry,
        protected HeartbeatChecker $heartbeatChecker,
        protected array $config,
    ) {}

    public function listenOnTransport(InboundTransport $transport, string $workerId): void
    {
        $transportId = $transport->getId();

        if (!$transport->isAvailable()) {
            throw new LogicException(
                "Transport '{$transportId}' is not available. Please check the connection configuration.",
            );
        }

        $this->listenerRegistry->register($transportId);

        $channel = $this->getRequestChannel($transport);
        $this->log("Worker {$workerId} started listening on transport {$transportId} (channel: {$channel})");

        try {
            $transport->listen($channel, function (array $envelope) use ($workerId, $transport, $transportId): void {
                try {
                    $this->processEnvelope($envelope, $workerId, $transport);

                    $this->listenerRegistry->register($transportId);
                } catch (Throwable $e) {
                    $this->logError('Error processing message', [
                        'worker_id' => $workerId,
                        'error' => $e->getMessage(),
                    ]);
                }
            });
        } finally {
            $this->listenerRegistry->unregister($transportId);
        }
    }

    /** @param array<string, mixed> $envelope */
    protected function processEnvelope(
        array $envelope,
        string $workerId,
        InboundTransport $transport,
    ): void
    {
        if (($envelope['message_type'] ?? null) === 'callback') {
            $this->processCallbackMessage($envelope, $workerId);
            return;
        }

        $this->processRequestMessage($envelope, $workerId, $transport);
    }

    /** @param array<string, mixed> $envelope */
    protected function processRequestMessage(
        array $envelope,
        string $workerId,
        InboundTransport $transport,
    ): void
    {
        $requestId = 'unknown';
        $requestType = null;

        try {
            $requestId = $envelope['id'] ?? 'unknown';
            assert(is_string($requestId));
            $requestType = RequestType::from($envelope['type'] ?? 'sync');
            $sourceSystem = $envelope['source_system'] ?? 'unknown';
            assert(is_string($sourceSystem));
            $eventName = $envelope['event_name'] ?? 'unknown';

            if ($requestType === RequestType::SYNC && $transport instanceof SupportsDirectResponse) {
                try {
                    $this->sendAck($requestId, $transport);
                } catch (Throwable $ackError) {
                    $this->logError('Failed to send ACK, continuing processing', [
                        'worker_id' => $workerId,
                        'request_id' => $requestId,
                        'error' => $ackError->getMessage(),
                    ]);
                }
            }

            if ($requestType !== RequestType::FIRE_AND_FORGET) {
                $cached = $this->idempotency->getCachedResponse($requestId);

                if ($cached !== null) {
                    $this->log("Returning cached response for duplicate request {$requestId}", [
                        'worker_id' => $workerId,
                        'event' => $eventName,
                        'type' => $requestType->value,
                    ]);

                    if ($requestType === RequestType::SYNC) {
                        $this->sendResponse($requestId, $transport, $cached['result'], $cached['error']);
                    } elseif ($requestType === RequestType::ASYNC) {
                        $this->sendCallback($requestId, $sourceSystem, $cached['result'], $cached['error'] === null);
                    }

                    return;
                }
            }

            $this->log("Processing request {$requestId} from {$sourceSystem}", [
                'worker_id' => $workerId,
                'event' => $eventName,
                'type' => $requestType->value,
            ]);

            if (!isset($envelope['auth_token'])) {
                throw InvalidTokenException::missingInRequest();
            }

            try {
                $this->verifyTokenWithMultipleSecrets($envelope['auth_token'], $sourceSystem);
            } catch (ReplayAttackException | TokenExpiredException $e) {
                if ($requestType !== RequestType::FIRE_AND_FORGET) {
                    $cached = $this->idempotency->getCachedResponse($requestId);

                    if ($cached !== null) {
                        $this->log("Token already used but found cached result - returning cached response", [
                            'worker_id' => $workerId,
                            'request_id' => $requestId,
                            'event' => $eventName,
                        ]);

                        if ($requestType === RequestType::SYNC) {
                            $this->sendResponse($requestId, $transport, $cached['result'], $cached['error']);
                        } elseif ($requestType === RequestType::ASYNC) {
                            $this->sendCallback($requestId, $sourceSystem, $cached['result'], $cached['error'] === null);
                        }

                        return;
                    }
                }

                throw $e;
            }

            $this->rateLimiter->attempt($sourceSystem);

            $event = $this->reconstructEvent($envelope);

            $this->eventDispatcher->dispatch(new RequestReceived($requestId, $sourceSystem, $eventName, $event));

            $handler = $this->registry->getHandler($eventName);

            match ($requestType) {
                RequestType::SYNC => $this->handleSync($requestId, $event, $handler, $transport),
                RequestType::ASYNC => $this->handleAsync($requestId, $sourceSystem, $event, $handler),
                RequestType::FIRE_AND_FORGET => $this->handleForget($requestId, $event, $handler),
            };
        } catch (Throwable $e) {
            $this->logError('Error processing request', [
                'worker_id' => $workerId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            if ($requestType !== null && $requestType === RequestType::SYNC) {
                $this->sendResponse($requestId, $transport, null, $e->getMessage());
                $this->idempotency->cacheResponse($requestId, null, $e->getMessage());
            }

            if ($requestType !== null && $requestType === RequestType::ASYNC) {
                $this->asyncManager->setStatus($requestId, AsyncStatus::FAILED, [
                    'error' => $e->getMessage(),
                ]);
                $this->idempotency->cacheResponse($requestId, null, $e->getMessage());
            }
        }
    }

    /** @param array<string, mixed> $callbackData */
    protected function processCallbackMessage(array $callbackData, string $workerId): void
    {
        try {
            $requestId = $callbackData['request_id'] ?? 'unknown';
            assert(is_string($requestId));
            $result = $callbackData['result'] ?? null;
            $success = $callbackData['success'] ?? false;

            $this->log("Processing callback for request {$requestId}", [
                'worker_id' => $workerId,
                'success' => $success,
            ]);

            $eventName = $callbackData['original_event_name'] ?? null;
            $responseEvent = null;

            if ($eventName !== null) {
                $responseEventClass = $this->registry->getAsyncMapping($eventName);

                if (
                    $responseEventClass !== null
                    && class_exists($responseEventClass)
                    && is_subclass_of($responseEventClass, BaseIntercallEvent::class)
                ) {
                    /** @var class-string<BaseIntercallEvent<array<string, mixed>>> $responseEventClass */
                    $responseEvent = $responseEventClass::fromArray([
                        'payload' => is_array($result) ? $result : ['data' => $result],
                    ]);
                }
            }

            $this->eventDispatcher->dispatch(new AsyncResponseReceived(
                $requestId,
                $eventName ?? 'unknown',
                $responseEvent ?? $result,
                $success,
            ));
        } catch (Throwable $e) {
            $this->logError('Error processing callback', [
                'worker_id' => $workerId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    protected function extractEventName(mixed $result): ?string
    {
        if (!is_array($result)) {
            return null;
        }

        return $result['event_name'] ?? $result['event'] ?? null;
    }

    /** @param IntercallEvent<array<string, mixed>> $event */
    protected function handleSync(
        string $requestId,
        IntercallEvent $event,
        mixed $handler,
        InboundTransport $transport,
    ): void
    {
        try {
            assert($handler instanceof EventHandler);
            $result = $handler->handle($event, ['request_id' => $requestId]);
            $this->sendResponse($requestId, $transport, $result);
            $this->idempotency->cacheResponse($requestId, $result, null);
        } catch (Throwable $e) {
            $this->sendResponse($requestId, $transport, null, $e->getMessage());
            $this->idempotency->cacheResponse($requestId, null, $e->getMessage());
            throw $e;
        }
    }

    /** @param IntercallEvent<array<string, mixed>> $event */
    protected function handleAsync(
        string $requestId,
        string $sourceSystem,
        IntercallEvent $event,
        mixed $handler,
    ): void {
        try {
            $this->asyncManager->setStatus($requestId, AsyncStatus::PROCESSING);

            assert($handler instanceof EventHandler);
            $result = $handler->handle($event, ['request_id' => $requestId]);

            $this->asyncManager->setStatus($requestId, AsyncStatus::COMPLETED, $result);

            $this->sendCallback($requestId, $sourceSystem, $event->getEventName(), $result, true);
            $this->idempotency->cacheResponse($requestId, $result, null);
        } catch (Throwable $e) {
            $this->asyncManager->setStatus($requestId, AsyncStatus::FAILED, [
                'error' => $e->getMessage(),
            ]);

            $this->sendCallback($requestId, $sourceSystem, $event->getEventName(), [
                'error' => $e->getMessage(),
            ], false);

            $this->idempotency->cacheResponse($requestId, null, $e->getMessage());

            throw $e;
        }
    }

    /** @param IntercallEvent<array<string, mixed>> $event */
    protected function handleForget(string $requestId, IntercallEvent $event, mixed $handler): void
    {
        try {
            assert($handler instanceof EventHandler);
            $handler->handle($event, ['request_id' => $requestId]);
        } catch (Throwable $e) {
            $this->logError('Error in fire-and-forget handler', [
                'request_id' => $requestId,
                'event' => $event->getEventName(),
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected function sendAck(
        string $requestId,
        InboundTransport $transport,
    ): void {
        if (!$transport instanceof SupportsDirectResponse) {
            throw new LogicException(
                'Transport does not support direct response channels',
            );
        }

        $ackData = ['ack' => true, 'request_id' => $requestId];
        $serialized = $this->serializer->serialize($ackData);
        $channel = $this->getAckChannel($requestId, $transport);

        $transport->sendToChannel($channel, $serialized, 5);

        $this->log("Sent ACK for request {$requestId}");
    }

    protected function sendResponse(
        string $requestId,
        InboundTransport $transport,
        mixed $result = null,
        ?string $error = null,
    ): void {
        if (!$transport instanceof SupportsDirectResponse) {
            throw new LogicException(
                'Transport does not support direct response channels',
            );
        }

        $responseData = $error !== null
            ? ['error' => $error]
            : ['result' => $result];

        $serialized = $this->serializer->serialize($responseData);
        $channel = $this->getResponseChannel($requestId, $transport);

        $transport->sendToChannel($channel, $serialized, 60);
    }

    protected function sendCallback(
        string $requestId,
        string $sourceSystem,
        string $originalEventName,
        mixed $result,
        bool $success,
    ): void {
        $currentSystem = $this->systemRegistry->getLocalSystemConfig();
        $targetSystemConfig = $this->systemRegistry->getRemoteSystemConfig($sourceSystem);

        $sharedSecret = $targetSystemConfig->token;
        $currentSystemName = $currentSystem->name;

        $heartbeatEnabled = $this->config['heartbeat']['enabled'] ?? true;
        if ($heartbeatEnabled) {
            $isAlive = $this->heartbeatChecker->check($sourceSystem);

            if (!$isAlive) {
                $this->logger->warning('[Intercall] Remote system may not be listening - callback will be queued', [
                    'system' => $sourceSystem,
                    'request_id' => $requestId,
                    'event' => $originalEventName,
                ]);
            }
        }

        $callbackData = [
            'message_type' => 'callback',
            'request_id' => $requestId,
            'original_event_name' => $originalEventName,
            'result' => $result,
            'success' => $success,
            'timestamp' => time(),
            'source_system' => $currentSystemName,
            'event_name' => 'callback.response',
            'auth_token' => $this->auth->generateToken($sharedSecret, [
                'request_id' => $requestId,
                'source_system' => $currentSystemName,
                'target_system' => $sourceSystem,
                'callback' => true,
            ]),
            'payload' => [
                'message_type' => 'callback',
                'request_id' => $requestId,
                'original_event_name' => $originalEventName,
                'result' => $result,
                'success' => $success,
                'timestamp' => time(),
            ],
        ];

        $this->transportManager->send($sourceSystem, $callbackData);
    }

    /**
     * @param array<string, mixed> $envelope
     * @return IntercallEvent<array<string, mixed>>
     */
    protected function reconstructEvent(array $envelope): IntercallEvent
    {
        $eventName = $envelope['event_name'];
        $eventClass = $this->registry->getEventClass($eventName);

        if (
            $eventClass !== null
            && class_exists($eventClass)
            && is_subclass_of($eventClass, BaseIntercallEvent::class)
        ) {
            /** @var class-string<BaseIntercallEvent<array<string, mixed>>> $eventClass */
            return $eventClass::fromArray($envelope);
        }

        return new class ($envelope['payload'] ?? [], $eventName) extends BaseIntercallEvent {
            /** @param array<string, mixed> $payload */
            public function __construct(array $payload, private readonly string $name)
            {
                parent::__construct($payload);
            }

            public function getEventName(): string
            {
                return $this->name;
            }
        };
    }

    /**
     * @throws MissingTokenException
     * @throws InvalidTokenException
     * @throws TokenExpiredException
     * @throws ReplayAttackException
     */
    protected function verifyTokenWithMultipleSecrets(string $token, string $sourceSystem): void
    {
        $localConfig = $this->systemRegistry->getLocalSystemConfig();
        $tokens = $localConfig->getTokensForSystem($sourceSystem);

        if (empty($tokens)) {
            throw MissingTokenException::forInboundSystem($sourceSystem);
        }

        $lastException = null;

        foreach ($tokens as $tokenObj) {
            try {
                $this->auth->verifyToken($token, $tokenObj->value);
                return;
            } catch (InvalidTokenException $e) {
                $lastException = $e;
                continue;
            }
        }

        throw $lastException ?? new InvalidTokenException('Authentication failed');
    }

    protected function getRequestChannel(InboundTransport $transport): string
    {
        $currentSystem = $this->systemRegistry->getLocalSystemConfig();
        if ($currentSystem === null) {
            throw SystemNotConfiguredException::currentSystem();
        }

        $systemId = $currentSystem->name;
        $prefix = '';

        if ($transport instanceof TransportHasPrefix) {
            $prefix = $transport->getPrefix();
        }

        if ($prefix === '') {
            $prefix = 'intercall';
        }

        return "{$prefix}:{$systemId}:requests";
    }

    protected function getAckChannel(string $requestId, InboundTransport $transport): string
    {
        $prefix = '';

        if ($transport instanceof TransportHasPrefix) {
            $prefix = $transport->getPrefix();
        }

        if ($prefix === '') {
            $prefix = 'intercall';
        }

        return "{$prefix}:ack:{$requestId}";
    }

    protected function getResponseChannel(string $requestId, InboundTransport $transport): string
    {
        $prefix = '';

        if ($transport instanceof TransportHasPrefix) {
            $prefix = $transport->getPrefix();
        }

        if ($prefix === '') {
            $prefix = 'intercall';
        }

        return "{$prefix}:response:{$requestId}";
    }

    /** @param array<string, mixed> $context */
    protected function log(string $message, array $context = []): void
    {
        $this->logger->info("[Intercall] {$message}", $context);
    }

    /** @param array<string, mixed> $context */
    protected function logError(string $message, array $context = []): void
    {
        $this->logger->error("[Intercall] {$message}", $context);
    }

}
