<?php

declare(strict_types=1);

namespace DeployTeam\Intercall\Transports;

use DeployTeam\Intercall\Contracts\Bridge\HttpClient;
use DeployTeam\Intercall\Contracts\Bridge\Logger;
use DeployTeam\Intercall\Transports\Configuration\HttpOutboundConfig;
use DeployTeam\Intercall\Transports\Contracts\OutboundTransport;
use DeployTeam\Intercall\Transports\Results\AsyncTransportResult;
use DeployTeam\Intercall\Transports\Results\FailedTransportResult;
use DeployTeam\Intercall\Transports\Results\SyncTransportResult;
use DeployTeam\Intercall\Transports\Results\TransportResult;
use Throwable;

class HttpOutboundTransport implements OutboundTransport
{
    public function __construct(
        protected HttpClient $httpClient,
        protected Logger $logger,
        protected HttpOutboundConfig $config,
    ) {}

    public function send(string $destination, array $data, array $options = []): TransportResult
    {
        try {
            $eventName = $data['event_name'] ?? 'unknown';
            $sourceSystem = $data['source_system'] ?? 'unknown';
            $requestId = $data['request_id'] ?? ('http-' . bin2hex(random_bytes(16)));
            $isAsync = $data['is_async'] ?? false;
            $token = $data['auth_token'] ?? null;
            assert(is_string($token) || $token === null);
            $payload = $data['payload'] ?? [];

            $headers = [
                'X-Intercall-Event' => $eventName,
                'X-Intercall-Source' => $sourceSystem,
                'X-Intercall-Request-Id' => $requestId,
                'X-Intercall-Async' => $isAsync ? 'true' : 'false',
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ];

            if ($token !== null) {
                $headers['Authorization'] = 'Bearer ' . $token;
            }

            $response = $this->httpClient->request('POST', $this->config->baseUrl, [
                'headers' => $headers,
                'json' => $payload,
                'timeout' => $this->config->timeout,
                'insecure' => $this->config->insecure,
            ]);

            $statusCode = $response->getStatusCode();
            $success = $statusCode >= 200 && $statusCode < 300;

            if (!$success) {
                $errorMessage = "HTTP {$statusCode}: " . $response->getBody();
                $this->logger->error('[Intercall HTTP] Failed to send message', [
                    'destination' => $destination,
                    'url' => $this->config->baseUrl,
                    'status' => $statusCode,
                    'body' => $response->getBody(),
                ]);
                return new FailedTransportResult($errorMessage);
            }

            $this->logger->debug('[Intercall HTTP] Message sent', [
                'destination' => $destination,
                'url' => $this->config->baseUrl,
                'event' => $eventName,
                'status' => $statusCode,
            ]);

            if (!$isAsync) {
                $responseBody = $response->getBody();
                $responseData = json_decode($responseBody, true, 512, JSON_THROW_ON_ERROR);

                if (!is_array($responseData)) {
                    $this->logger->error('[Intercall HTTP] Invalid response format', [
                        'destination' => $destination,
                        'body' => $responseBody,
                    ]);
                    return new FailedTransportResult('Invalid JSON response format');
                }

                return new SyncTransportResult($responseData);
            }

            return new AsyncTransportResult();
        } catch (Throwable $e) {
            $this->logger->error('[Intercall HTTP] Send error', [
                'destination' => $destination,
                'error' => $e->getMessage(),
            ]);
            return new FailedTransportResult($e->getMessage());
        }
    }

    public function isAvailable(): bool
    {
        return true;
    }

    public function getBaseUrl(): string
    {
        return $this->config->baseUrl;
    }

    public function getTimeout(): int
    {
        return $this->config->timeout;
    }
}
