<?php

declare(strict_types=1);

namespace Emissary\Commands;

use Emissary\Waha\WahaClient;
use Emissary\WahaSessionState;
use Illuminate\Console\Command;

class EmissaryWahaSessionStart extends Command
{
    protected $signature = 'emissary:waha:session:start {session? : Session name (defaults to config)}';
    protected $description = 'Create and start a WAHA session, configure webhook, and display QR code';

    public function handle(): int
    {
        $config = config('emissary.channels.whatsapp');
        $apiUrl = $config['waha_api_url'] ?? 'http://localhost:3000';
        $apiKey = $config['waha_api_key'] ?? '';
        $defaultSession = $config['waha_session'] ?? 'default';
        $hmacKey = $config['waha_hmac_key'] ?? '';
        $version = $config['waha_version'] ?? 'free';

        if ($apiKey === '') {
            $this->error('WAHA_API_KEY is not configured.');

            return self::FAILURE;
        }

        $session = $this->argument('session') ?: $defaultSession;

        if ($version === 'free' && $session !== 'default') {
            $this->warn("WAHA free version only supports the 'default' session. Forcing session name to 'default'.");
            $session = 'default';
        }

        $client = new WahaClient($apiUrl, $apiKey);

        $webhookUrl = rtrim(config('app.url', 'http://localhost'), '/')
            . '/' . config('emissary.webhook_path', 'webhooks')
            . '/whatsapp';

        $this->info("Starting WAHA session: {$session}");

        $client->startSession($session, $webhookUrl, $hmacKey !== '' ? $hmacKey : null);

        $this->pollStatus($client, $session);

        return self::SUCCESS;
    }

    private function pollStatus(WahaClient $client, string $session): void
    {
        $maxAttempts = 60;
        $attempt = 0;

        while ($attempt < $maxAttempts) {
            $state = $client->getStatus($session);

            match ($state) {
                WahaSessionState::Working => $this->handleWorking(),
                WahaSessionState::ScanQrCode => $this->handleScanQrCode($client, $session),
                WahaSessionState::Failed => $this->handleFailed(),
                default => $this->info("Status: {$state->value}..."),
            };

            if ($state === WahaSessionState::Working || $state === WahaSessionState::Failed) {
                return;
            }

            sleep(2);
            $attempt++;
        }

        $this->error('Timed out waiting for session to be ready.');
    }

    private function handleWorking(): void
    {
        $this->info('Session is WORKING. Ready to send and receive messages.');
    }

    private function handleScanQrCode(WahaClient $client, string $session): void
    {
        $qr = $client->getQrCode($session);

        if ($qr !== null && $qr !== '') {
            $this->info('Scan this QR code with WhatsApp:');
            $this->line($qr);
        }

        $this->info('Waiting for QR scan...');
    }

    private function handleFailed(): void
    {
        $this->error('Session failed. Check WAHA logs for details.');
    }
}
