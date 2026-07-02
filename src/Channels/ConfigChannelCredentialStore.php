<?php

declare(strict_types=1);

namespace Emissary\Channels;

use Emissary\Channel;
use Emissary\ChannelCredentials;
use Emissary\Contracts\ChannelCredentialStore;

class ConfigChannelCredentialStore implements ChannelCredentialStore
{
    public function resolve(Channel $channel, mixed $tenant = null): ?ChannelCredentials
    {
        $config = config("emissary.channels.{$channel->value}");

        if ($config === null) {
            return null;
        }

        return match ($channel) {
            Channel::WhatsApp => $this->resolveWhatsApp($config),
            Channel::Telegram => new ChannelCredentials(
                verifySecret: $config['secret_token'] ?? '',
                accessToken: $config['bot_token'] ?? null,
                senderId: null,
                handshakeToken: null,
            ),
            Channel::Web => new ChannelCredentials(
                verifySecret: csrf_token(),
                accessToken: null,
                senderId: null,
                handshakeToken: null,
            ),
        };
    }

    private function resolveWhatsApp(array $config): ChannelCredentials
    {
        $backend = $config['backend'] ?? 'waha';

        if ($backend === 'meta') {
            return new ChannelCredentials(
                verifySecret: $config['app_secret'] ?? '',
                accessToken: $config['access_token'] ?? null,
                senderId: $config['phone_number_id'] ?? null,
                handshakeToken: $config['verify_token'] ?? null,
            );
        }

        return new ChannelCredentials(
            verifySecret: $config['waha_hmac_key'] ?? '',
            accessToken: $config['waha_api_key'] ?? null,
            senderId: null,
            handshakeToken: null,
            extra: [
                'waha_api_url' => $config['waha_api_url'] ?? 'http://localhost:3000',
                'waha_session' => $config['waha_session'] ?? 'default',
                'waha_version' => $config['waha_version'] ?? 'free',
            ],
        );
    }
}
