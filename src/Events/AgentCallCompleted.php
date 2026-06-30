<?php

declare(strict_types=1);

namespace Emissary\Events;

readonly class AgentCallCompleted
{
    public function __construct(
        public string $turnId,
        public string $conversationId,
        public string $model,
        public string $callType,
        public int $inputTokens,
        public int $outputTokens,
        public int $latencyMs,
        public ?string $intent = null,
        public ?array $toolCalls = null,
        public ?string $errorCode = null,
        public ?string $error = null,
        public ?string $conversationMessageId = null,
    ) {}
}
