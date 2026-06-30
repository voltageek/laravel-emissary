<?php

declare(strict_types=1);

namespace Emissary;

class TransactionResult
{
    public function __construct(
        public bool $success,
        public ?string $referenceId = null,
        public ?string $message = null,
    ) {}

    public static function ok(string $referenceId, ?string $message = null): self
    {
        return new self(
            success: true,
            referenceId: $referenceId,
            message: $message,
        );
    }

    public static function fail(string $message): self
    {
        return new self(
            success: false,
            message: $message,
        );
    }
}
