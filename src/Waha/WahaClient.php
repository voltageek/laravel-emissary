<?php

declare(strict_types=1);

namespace Emissary\Waha;

use Emissary\WahaSessionState;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;

class WahaClient
{
    private Client $http;

    public function __construct(
        private string $apiUrl,
        private string $apiKey,
    ) {
        $this->http = new Client([
            'base_uri' => rtrim($this->apiUrl, '/') . '/',
            'headers' => [
                'X-Api-Key' => $this->apiKey,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ],
        ]);
    }

    public function createSession(string $name, ?string $webhookUrl = null, ?string $hmacKey = null): array
    {
        $body = ['name' => $name];

        if ($webhookUrl !== null) {
            $body['config'] = $this->buildWebhookConfig($webhookUrl, $hmacKey);
        }

        return $this->request('POST', 'api/sessions', $body);
    }

    public function startSession(string $name, ?string $webhookUrl = null, ?string $hmacKey = null): array
    {
        $body = ['name' => $name];

        if ($webhookUrl !== null) {
            $body['config'] = $this->buildWebhookConfig($webhookUrl, $hmacKey);
        }

        return $this->request('POST', 'api/sessions/start', $body);
    }

    public function stopSession(string $name): array
    {
        return $this->request('POST', "api/sessions/{$name}/stop");
    }

    public function restartSession(string $name, ?string $webhookUrl = null, ?string $hmacKey = null): array
    {
        $this->stopSession($name);

        return $this->startSession($name, $webhookUrl, $hmacKey);
    }

    public function getStatus(string $name): WahaSessionState
    {
        $response = $this->request('GET', "api/sessions/{$name}");

        $status = $response['status'] ?? 'FAILED';

        return WahaSessionState::fromApiResponse($status);
    }

    public function getQrCode(string $name, string $format = 'raw'): ?string
    {
        $response = $this->request('GET', "api/{$name}/auth/qr", [
            'query' => ['format' => $format],
        ]);

        return $response['qrCode'] ?? null;
    }

    public function deleteSession(string $name): array
    {
        return $this->request('DELETE', "api/sessions/{$name}");
    }

    public function listSessions(): array
    {
        $response = $this->request('GET', 'api/sessions');

        return $response['sessions'] ?? [];
    }

    public function getScreenshot(string $session): string
    {
        $response = $this->request('GET', 'api/screenshot', [
            'query' => ['session' => $session],
        ]);

        return $response['screenshot'] ?? '';
    }

    public function getMe(string $session): array
    {
        $response = $this->request('GET', "api/sessions/{$session}/me");

        return $response['user'] ?? $response;
    }

    private function buildWebhookConfig(string $webhookUrl, ?string $hmacKey): array
    {
        $webhook = [
            'url' => $webhookUrl,
            'events' => ['message'],
        ];

        if ($hmacKey !== null && $hmacKey !== '') {
            $webhook['hmac'] = ['key' => $hmacKey];
        }

        return ['webhooks' => [$webhook]];
    }

    private function request(string $method, string $uri, ?array $options = null): array
    {
        try {
            $response = $this->http->request($method, $uri, $options ?? []);

            $body = json_decode((string) $response->getBody(), true);

            return is_array($body) ? $body : [];
        } catch (GuzzleException $e) {
            Log::error('WAHA API request failed', [
                'method' => $method,
                'uri' => $uri,
                'status_code' => $e->getCode(),
                'message' => $e->getMessage(),
            ]);

            return [];
        }
    }
}
