<?php

declare(strict_types=1);

namespace Emissary\Commands;

use Emissary\Waha\WahaClient;
use Emissary\WahaSessionState;
use Illuminate\Console\Command;

class EmissaryWahaSessionQr extends Command
{
    protected $signature = 'emissary:waha:session:qr {session? : Session name (defaults to config)}';
    protected $description = 'Fetch and display the QR code for a WAHA session';

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

        if ($state !== WahaSessionState::ScanQrCode) {
            $this->error("Session '{$session}' is not in SCAN_QR_CODE state. Current state: {$state->value}");
            return self::FAILURE;
        }

        $qr = $client->getQrCode($session);

        if ($qr === null || $qr === '') {
            $this->error('No QR code available.');
            return self::FAILURE;
        }

        $this->info('Scan this QR code with WhatsApp:');
        $this->line($qr);

        return self::SUCCESS;
    }
}
