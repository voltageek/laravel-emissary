<?php

declare(strict_types=1);

namespace Emissary;

use Carbon\Carbon;

readonly class InboundMessage
{
    public function __construct(
        public string $conversationRef,
        public Channel $channel,
        public string $text,
        public ?string $mediaUrl = null,
        public Carbon $receivedAt = new Carbon(),
    ) {}
}
