<?php

declare(strict_types=1);

namespace Emissary\Commands;

use Emissary\Contracts\ChannelCredentialStore;
use Emissary\Channel;
use Emissary\Waha\WahaClient;
use Illuminate\Console\Command;

class EmissaryChannelTest extends Command
{
    protected $signature = 'emissary:channel:test {channel : whatsapp|telegram|web} {--tenant=}';
    protected $description = 'Round-trip health check for a channel';

    public function handle(ChannelCredentialStore $store): int
    {
        $channelName = $this->argument('channel');
        $channel = Channel::tryFrom($channelName);

        if ($channel === null) {
            $this->error("Unknown channel: {$channelName}");

            return self::FAILURE;
        }

        $creds = $store->resolve($channel, $this->option('tenant'));

        if ($creds === null) {
            $this->warn("Channel '{$channelName}' is not provisioned.");

            return self::FAILURE;
        }

        $this->info("Channel '{$channelName}': credentials resolved.");
        $this->line("  verifySecret: " . (empty($creds->verifySecret) ? 'MISSING' : 'present'));
        $this->line("  accessToken:  " . ($creds->accessToken ? 'present' : 'MISSING'));
        $this->line("  senderId:     " . ($creds->senderId ?? 'N/A'));

        if ($channel === Channel::WhatsApp && config('emissary.channels.whatsapp.backend', 'waha') === 'waha') {
            $this->checkWahaConnectivity($creds);
        }

        return self::SUCCESS;
    }

    private function checkWahaConnectivity($creds): void
    {
        $apiUrl = $creds->extra['waha_api_url'] ?? 'http://localhost:3000';
        $apiKey = $creds->accessToken ?? '';
        $session = $creds->extra['waha_session'] ?? 'default';

        if ($apiKey === '') {
            $this->warn('WAHA API key not set. Skipping connectivity check.');
            return;
        }

        $client = new WahaClient($apiUrl, $apiKey);

        $state = $client->getStatus($session);

        $this->line("  WAHA session '{$session}': {$state->value}");

        if ($state !== \Emissary\WahaSessionState::Working) {
            $this->warn("  Session is not WORKING. Run 'emissary:waha:session:start' to start it.");
        } else {
            $this->info('  WAHA session is ready.');
        }
    }
}
