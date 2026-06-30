<?php

declare(strict_types=1);

namespace Emissary\Events;

readonly class GuardDecision
{
    public function __construct(
        public string $turnId,
        public string $conversationId,
        public string $checkpoint,
        public string $guard,
        public bool $allowed,
        public ?string $toolName = null,
        public ?string $errorCode = null,
        public ?string $userMessage = null,
    ) {}
}
