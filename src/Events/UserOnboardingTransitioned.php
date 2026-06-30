<?php

declare(strict_types=1);

namespace Emissary\Events;

readonly class UserOnboardingTransitioned
{
    public function __construct(
        public string $turnId,
        public string $conversationId,
        public ?string $userId,
        public string $transition,
        public ?array $profile = null,
        public ?string $consentVersion = null,
    ) {}
}
