<?php

declare(strict_types=1);

namespace Emissary;

readonly class OutboundMessage
{
    public function __construct(
        public string $text,
        public ?string $mediaUrl = null,
        public ?array $quickReplies = null,
        public ?array $channelExtras = null,
    ) {}
}
