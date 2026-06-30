<?php

declare(strict_types=1);

namespace Emissary\Identity;

use Emissary\Contracts\ChannelIdentityResolver;
use Emissary\InboundMessage;
use Emissary\Models\ChannelIdentityLink;
use Illuminate\Contracts\Auth\Authenticatable;

class LinkedChannelIdentityResolver implements ChannelIdentityResolver
{
    public function resolveUser(InboundMessage $message): ?Authenticatable
    {
        $link = ChannelIdentityLink::where('channel', $message->channel->value)
            ->where('channel_ref', $message->conversationRef)
            ->whereNotNull('verified_at')
            ->first();

        if ($link === null) {
            return null;
        }

        $model = config('auth.providers.users.model', \App\Models\User::class);

        return $model::find($link->user_id);
    }
}
