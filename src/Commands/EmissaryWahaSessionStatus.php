<?php

declare(strict_types=1);

namespace Emissary\Commands;

use Emissary\Waha\WahaClient;
use Emissary\WahaSessionState;
use Illuminate\Console\Command;

class EmissaryWahaSessionStatus extends Command
{
    protected $signature = 'emissary:waha:session:status {session? : Session name (defaults to config)}';
    protected $description = 'Print the current status of a WAHA session';

    public function handle(): int
    {
        $config = config('emissary.channels.whatsapp');
        $apiUrl = $config['waha_api_url'] ?? 'http://localhost:3000';
        $apiKey = $config['waha_api_key'] ?? '';
        $defaultSession = $config['waha_session'] ?? 'default';

        if ($apiKey === '') {
            $this->error('WAHA_API_KEY is not configured.');

            return self::FAILURE;
        }

        $session = $this->argument('session') ?: $defaultSession;

        $client = new WahaClient($apiUrl, $apiKey);
        $state = $client->getStatus($session);

        $this->info("Session: {$session}");
        $this->line("Status: {$state->value}");

        if ($state === WahaSessionState::ScanQrCode) {
            $qr = $client->getQrCode($session);

            if ($qr !== null && $qr !== '') {
                $this->line("QR Code: {$qr}");
            }
        } elseif ($state === WahaSessionState::Failed) {
            $this->warn('Session is in FAILED state. Check WAHA logs for details.');
        }

        return self::SUCCESS;
    }
}
