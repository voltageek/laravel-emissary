<?php

declare(strict_types=1);

namespace Emissary;

readonly class IntentResult
{
    public function __construct(
        public string $slug,
        public float $confidence,
    ) {}
}
