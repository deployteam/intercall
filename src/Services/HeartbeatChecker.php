<?php

declare(strict_types=1);

namespace DeployTeam\Intercall\Services;

use DeployTeam\Intercall\Configuration\RemoteSystemConfig;
use DeployTeam\Intercall\Configuration\SystemRegistry;
use DeployTeam\Intercall\Contracts\Bridge\Logger;
use DeployTeam\Intercall\Transports\HttpOutboundTransport;
use Throwable;

class HeartbeatChecker
{
    /** @param array<string, mixed> $config */
    public function __construct(
        protected SystemRegistry $systemRegistry,
        protected Logger $logger,
        protected IntercallAuth $auth,
        protected array $config,
    ) {}

    public function check(string $systemName): bool
    {
        $heartbeatConfig = $this->config['heartbeat'] ?? [];
        $enabled = $heartbeatConfig['enabled'] ?? true;

        if (!$enabled) {
            return true;
        }

        try {
            $systemConfig = $this->systemRegistry->getRemoteSystemConfig($systemName);
            $heartbeatUrl = $this->getHeartbeatUrl($systemConfig);

            if ($heartbeatUrl === null) {
                return true;
            }

            return $this->checkUrl($heartbeatUrl, $systemName, $systemConfig);
        } catch (Throwable $e) {
            $this->logger->error('[Intercall Heartbeat] Failed to check heartbeat', [
                'system' => $systemName,
                'error' => $e->getMessage(),
            ]);

            return true;
        }
    }

    protected function getHeartbeatUrl(RemoteSystemConfig $systemConfig): ?string
    {
        foreach ($systemConfig->transports->getTransports() as $transport) {
            if ($transport instanceof HttpOutboundTransport) {
                $baseUrl = rtrim($transport->getBaseUrl(), '/');
                return "{$baseUrl}/heartbeat";
            }
        }

        return null;
    }

    protected function checkUrl(string $url, string $systemName, RemoteSystemConfig $systemConfig): bool
    {
        try {
            $timeout = $this->config['heartbeat']['timeout'] ?? 2;

            $ch = curl_init($url);
            if ($ch === false) {
                return false;
            }

            $localSystemName = $this->systemRegistry->getLocalSystemConfig()->name;
            $token = $this->auth->generateToken($systemConfig->token);

            $headers = [
                "Authorization: Bearer {$token}",
                "X-Intercall-Source: {$localSystemName}",
            ];

            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => $timeout,
                CURLOPT_CONNECTTIMEOUT => $timeout,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false,
                CURLOPT_HTTPHEADER => $headers,
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode !== 200) {
                $this->logger->warning('[Intercall Heartbeat] Remote system heartbeat failed', [
                    'system' => $systemName,
                    'url' => $url,
                    'http_code' => $httpCode,
                ]);
                return false;
            }

            if (!is_string($response)) {
                $this->logger->warning('[Intercall Heartbeat] Invalid response from heartbeat endpoint', [
                    'system' => $systemName,
                    'url' => $url,
                ]);
                return false;
            }

            $data = json_decode($response, true);
            if (!is_array($data)) {
                $this->logger->warning('[Intercall Heartbeat] Failed to parse heartbeat response', [
                    'system' => $systemName,
                    'url' => $url,
                    'response' => $response,
                ]);
                return false;
            }

            $listenerCount = $data['listener_count'] ?? 0;

            if ($listenerCount > 0) {
                $this->logger->debug('[Intercall Heartbeat] Remote system has active listeners', [
                    'system' => $systemName,
                    'url' => $url,
                    'listener_count' => $listenerCount,
                ]);
                return true;
            }

            $this->logger->warning('[Intercall Heartbeat] Remote system has no active listeners', [
                'system' => $systemName,
                'url' => $url,
                'listener_count' => $listenerCount,
            ]);

            return false;
        } catch (Throwable $e) {
            $this->logger->warning('[Intercall Heartbeat] Failed to check heartbeat URL', [
                'system' => $systemName,
                'url' => $url,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
