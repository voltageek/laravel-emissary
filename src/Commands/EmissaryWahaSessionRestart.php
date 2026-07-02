<?php

declare(strict_types=1);

namespace Emissary\Commands;

use Emissary\Waha\WahaClient;
use Emissary\WahaSessionState;
use Illuminate\Console\Command;

class EmissaryWahaSessionRestart extends Command
{
    protected $signature = 'emissary:waha:session:restart {session? : Session name (defaults to config)}';
    protected $description = 'Restart a WAHA session (stop then start)';

    public function handle(): int
    {
        $config = config('emissary.channels.whatsapp');
        $apiUrl = $config['waha_api_url'] ?? 'http://localhost:3000';
        $apiKey = $config['waha_api_key'] ?? '';
        $defaultSession = $config['waha_session'] ?? 'default';
        $hmacKey = $config['waha_hmac_key'] ?? '';

        if ($apiKey === '') {
            $this->error('WAHA_API_KEY is not configured.');

            return self::FAILURE;
        }

        $session = $this->argument('session') ?: $defaultSession;

        $client = new WahaClient($apiUrl, $apiKey);

        $this->info("Restarting session: {$session}");

        $webhookUrl = rtrim(config('app.url', 'http://localhost'), '/')
            . '/' . config('emissary.webhook_path', 'webhooks')
            . '/whatsapp';

        $result = $client->restartSession($session, $webhookUrl, $hmacKey !== '' ? $hmacKey : null);

        if ($result !== []) {
            $this->info('Session restarted. Checking status...');
            $state = $client->getStatus($session);
            $this->info("Status: {$state->value}");
        } else {
            $this->error('Failed to restart session.');
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
