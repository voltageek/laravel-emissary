<?php

declare(strict_types=1);

namespace Emissary\Commands;

use Emissary\Channel;
use Emissary\Models\ChannelConfig;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Crypt;

class EmissaryChannelAdd extends Command
{
    protected $signature = 'emissary:channel:add {channel : whatsapp|telegram|web} {--tenant=}';
    protected $description = 'Interactively provision channel credentials into the DB-backed store';

    public function handle(): int
    {
        $channelName = $this->argument('channel');
        $channel = Channel::tryFrom($channelName);

        if ($channel === null) {
            $this->error("Unknown channel: {$channelName}");

            return self::FAILURE;
        }

        $label = $this->ask('Label for this channel configuration', $channelName);

        $fields = match ($channel) {
            Channel::WhatsApp => ['access_token', 'phone_number_id', 'app_secret', 'verify_token'],
            Channel::Telegram => ['bot_token', 'secret_token'],
            Channel::Web => [],
        };

        $credentials = [];

        foreach ($fields as $field) {
            $value = $this->secret("Enter {$field}");
            $key = match ($field) {
                'access_token', 'bot_token' => 'access_token',
                'phone_number_id' => 'sender_id',
                'app_secret', 'secret_token' => 'verify_secret',
                'verify_token' => 'handshake_token',
                default => $field,
            };
            $credentials[$key] = $value;
        }

        $encrypted = Crypt::encryptString(json_encode($credentials));

        ChannelConfig::updateOrCreate(
            [
                'tenant_id' => $this->option('tenant'),
                'channel' => $channel->value,
            ],
            [
                'label' => $label,
                'credentials' => $encrypted,
                'status' => 'active',
            ],
        );

        $this->info("Channel '{$channelName}' provisioned successfully.");

        return self::SUCCESS;
    }
}
