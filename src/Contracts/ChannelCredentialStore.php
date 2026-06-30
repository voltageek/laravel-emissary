<?php

declare(strict_types=1);

namespace Emissary\Contracts;

use Emissary\Channel;
use Emissary\ChannelCredentials;

interface ChannelCredentialStore
{
    public function resolve(Channel $channel, mixed $tenant = null): ?ChannelCredentials;
}
