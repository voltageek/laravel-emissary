<?php

declare(strict_types=1);

namespace Emissary;

use Emissary\Contracts\ChannelIdentityResolver;
use Illuminate\Contracts\Auth\Authenticatable;

class AuthChannelIdentityResolver implements ChannelIdentityResolver
{
    public function resolveUser(InboundMessage $message): ?Authenticatable
    {
        return $message->channel === Channel::Web ? auth()->user() : null;
    }
}
