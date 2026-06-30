<?php

declare(strict_types=1);

namespace Emissary;

readonly class ChannelCredentials
{
    public function __construct(
        public string $verifySecret,
        public ?string $accessToken = null,
        public ?string $senderId = null,
        public ?string $handshakeToken = null,
        public ?array $extra = null,
    ) {}
}
