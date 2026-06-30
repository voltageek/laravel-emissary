<?php

declare(strict_types=1);

namespace Emissary\Channels;

use Emissary\Channel;
use Emissary\ChannelCredentials;
use Emissary\Contracts\ChannelCredentialStore;
use Emissary\Models\ChannelConfig;
use Illuminate\Support\Facades\Crypt;

class EncryptedChannelCredentialStore implements ChannelCredentialStore
{
    public function resolve(Channel $channel, mixed $tenant = null): ?ChannelCredentials
    {
        $query = ChannelConfig::where('channel', $channel->value)
            ->where('status', 'active');

        if ($tenant !== null) {
            $tenantId = $tenant instanceof \Illuminate\Database\Eloquent\Model
                ? $tenant->getKey()
                : $tenant;
            $query->where('tenant_id', $tenantId);
        }

        $config = $query->first();

        if ($config === null) {
            return null;
        }

        $data = json_decode(Crypt::decryptString($config->credentials), true);

        return new ChannelCredentials(
            verifySecret: $data['verify_secret'] ?? '',
            accessToken: $data['access_token'] ?? null,
            senderId: $data['sender_id'] ?? null,
            handshakeToken: $data['handshake_token'] ?? null,
            extra: $data['extra'] ?? null,
        );
    }
}
