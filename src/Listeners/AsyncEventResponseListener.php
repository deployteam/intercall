<?php

declare(strict_types=1);

namespace DeployTeam\Intercall\Listeners;

use DeployTeam\Intercall\Contracts\CallbackHandler;
use DeployTeam\Intercall\Events\AsyncResponseReceived;
use DeployTeam\Intercall\Events\BaseIntercallEvent;
use DeployTeam\Intercall\Services\EventRegistry;
use Throwable;

class AsyncEventResponseListener
{
    public function __construct(
        protected EventRegistry $registry,
    ) {}

    public function handle(AsyncResponseReceived $event): void
    {
        $eventClass = $this->registry->getEventClass($event->originalEventName);

        if ($eventClass === null || !class_exists($eventClass)) {
            return;
        }

        $callbackHandlerClass = $this->registry->getCallbackHandler($event->originalEventName);

        if ($callbackHandlerClass === null) {
            return;
        }

        try {
            /** @var class-string<BaseIntercallEvent<array<string, mixed>>> $eventClass */
            $payload = is_array($event->response) ? $event->response : ['data' => $event->response];
            $originalEvent = $eventClass::fromArray(['payload' => $payload]);

            if (!class_exists($callbackHandlerClass)) {
                logger()->warning('[Intercall] Callback handler class not found', [
                    'event' => $event->originalEventName,
                    'handler' => $callbackHandlerClass,
                ]);
                return;
            }

            $handler = app()->make($callbackHandlerClass, [
                'originalEvent' => $originalEvent,
                'requestId' => $event->requestId,
                'result' => $event->response,
                'success' => $event->success,
            ]);

            if (!$handler instanceof CallbackHandler) {
                logger()->warning('[Intercall] Handler does not implement CallbackHandler interface', [
                    'event' => $event->originalEventName,
                    'handler' => $callbackHandlerClass,
                ]);
                return;
            }

            if ($event->success) {
                app()->call([$handler, 'onSuccess']);
            } else {
                app()->call([$handler, 'onFailure']);
            }
        } catch (Throwable $e) {
            logger()->error('[Intercall] Failed to handle async event response', [
                'event' => $event->originalEventName,
                'request_id' => $event->requestId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }
}
