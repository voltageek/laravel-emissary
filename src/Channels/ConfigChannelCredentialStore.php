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
            Channel::WhatsApp => new ChannelCredentials(
                verifySecret: $config['app_secret'] ?? '',
                accessToken: $config['access_token'] ?? null,
                senderId: $config['phone_number_id'] ?? null,
                handshakeToken: $config['verify_token'] ?? null,
            ),
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
}
