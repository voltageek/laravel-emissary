<?php

declare(strict_types=1);

namespace Emissary\Commands;

use Emissary\Waha\WahaClient;
use Illuminate\Console\Command;

class EmissaryWahaSessionList extends Command
{
    protected $signature = 'emissary:waha:session:list';
    protected $description = 'List all WAHA sessions and their statuses';

    public function handle(): int
    {
        $config = config('emissary.channels.whatsapp');
        $apiUrl = $config['waha_api_url'] ?? 'http://localhost:3000';
        $apiKey = $config['waha_api_key'] ?? '';

        if ($apiKey === '') {
            $this->error('WAHA_API_KEY is not configured.');

            return self::FAILURE;
        }

        $client = new WahaClient($apiUrl, $apiKey);
        $sessions = $client->listSessions();

        if ($sessions === []) {
            $this->info('No sessions found.');

            return self::SUCCESS;
        }

        $this->table(
            ['Name', 'Status'],
            array_map(fn (array $session): array => [
                $session['name'] ?? 'unknown',
                $session['status'] ?? 'unknown',
            ], $sessions),
        );

        return self::SUCCESS;
    }
}
