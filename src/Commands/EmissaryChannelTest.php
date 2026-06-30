<?php

declare(strict_types=1);

namespace Emissary\Commands;

use Emissary\Contracts\ChannelCredentialStore;
use Emissary\Channel;
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

        return self::SUCCESS;
    }
}
