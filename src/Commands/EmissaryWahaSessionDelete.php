<?php

declare(strict_types=1);

namespace Emissary\Commands;

use Emissary\Waha\WahaClient;
use Emissary\WahaSessionState;
use Illuminate\Console\Command;

class EmissaryWahaSessionDelete extends Command
{
    protected $signature = 'emissary:waha:session:delete {session? : Session name (defaults to config)}';
    protected $description = 'Delete a WAHA session';

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

        if ($state === WahaSessionState::Working) {
            $this->warn("Session '{$session}' is currently WORKING.");
            if (! $this->confirm('Are you sure you want to delete a working session?')) {
                return self::SUCCESS;
            }
        }

        $result = $client->deleteSession($session);

        if ($result !== []) {
            $this->info("Session '{$session}' deleted.");
        } else {
            $this->error('Failed to delete session.');
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
