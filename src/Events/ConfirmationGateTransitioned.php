<?php

declare(strict_types=1);

namespace Emissary\Events;

readonly class ConfirmationGateTransitioned
{
    public function __construct(
        public string $turnId,
        public string $conversationId,
        public string $transition,
        public ?string $toolName = null,
        public ?array $fields = null,
    ) {}
}
