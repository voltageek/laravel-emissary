<?php

declare(strict_types=1);

namespace Emissary\Commands;

use Emissary\Waha\WahaClient;
use Illuminate\Console\Command;

class EmissaryWahaSessionStop extends Command
{
    protected $signature = 'emissary:waha:session:stop {session? : Session name (defaults to config)}';
    protected $description = 'Stop a running WAHA session';

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

        $this->info("Stopping session: {$session}");

        $result = $client->stopSession($session);

        if ($result !== []) {
            $this->info('Session stopped successfully.');
        } else {
            $this->error('Failed to stop session. Check WAHA API connectivity.');
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
