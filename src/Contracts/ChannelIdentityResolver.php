<?php

declare(strict_types=1);

namespace Emissary\Contracts;

use Emissary\InboundMessage;
use Illuminate\Contracts\Auth\Authenticatable;

interface ChannelIdentityResolver
{
    public function resolveUser(InboundMessage $message): ?Authenticatable;
}
