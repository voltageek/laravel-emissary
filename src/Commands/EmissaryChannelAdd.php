<?php

declare(strict_types=1);

namespace Emissary\Commands;

use Emissary\Channel;
use Emissary\Models\ChannelConfig;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Crypt;

class EmissaryChannelAdd extends Command
{
    protected $signature = 'emissary:channel:add {channel : whatsapp|telegram|web} {--tenant=} {--waha-session=} {--waha-api-key=}';
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

        $fields = $this->getChannelFields($channel);

        $credentials = [];

        foreach ($fields as $field) {
            $value = $this->secret("Enter {$field}");
            $key = match ($field) {
                'access_token', 'bot_token' => 'access_token',
                'phone_number_id' => 'sender_id',
                'app_secret', 'secret_token' => 'verify_secret',
                'verify_token' => 'handshake_token',
                'waha_api_key' => 'access_token',
                'waha_hmac_key' => 'verify_secret',
                'waha_session' => 'waha_session',
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

    private function getChannelFields(Channel $channel): array
    {
        return match ($channel) {
            Channel::WhatsApp => $this->getWhatsAppFields(),
            Channel::Telegram => ['bot_token', 'secret_token'],
            Channel::Web => [],
        };
    }

    private function getWhatsAppFields(): array
    {
        $backend = config('emissary.channels.whatsapp.backend', 'waha');

        if ($backend === 'meta') {
            return ['access_token', 'phone_number_id', 'app_secret', 'verify_token'];
        }

        return ['waha_api_key', 'waha_session', 'waha_hmac_key'];
    }
}
