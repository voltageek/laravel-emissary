<?php

declare(strict_types=1);

namespace Emissary;

readonly class GuardResult
{
    public function __construct(
        public bool $allowed,
        public ?string $userMessage = null,
        public ?string $errorCode = null,
    ) {}

    public static function allow(): self
    {
        return new self(allowed: true);
    }

    public static function deny(
        string $userMessage,
        ?string $errorCode = null,
    ): self {
        return new self(
            allowed: false,
            userMessage: $userMessage,
            errorCode: $errorCode,
        );
    }
}
