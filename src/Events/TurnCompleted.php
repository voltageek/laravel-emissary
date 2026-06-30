<?php

declare(strict_types=1);

namespace Emissary\Events;

readonly class TurnCompleted
{
    public function __construct(
        public string $turnId,
        public string $conversationId,
        public string $outcome,
        public ?string $intent = null,
        public ?array $models = null,
        public int $totalLatencyMs = 0,
        public int $totalInputTokens = 0,
        public int $totalOutputTokens = 0,
        public int $toolCount = 0,
        public ?string $errorCode = null,
    ) {}
}
