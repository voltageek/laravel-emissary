<?php

declare(strict_types=1);

namespace Emissary\Events;

readonly class ToolInvocationCompleted
{
    public function __construct(
        public string $turnId,
        public string $conversationId,
        public string $toolName,
        public array $arguments,
        public ?string $resultSummary = null,
        public ?int $durationMs = null,
        public bool $success = true,
        public ?string $validationError = null,
        public string $triggeredVia = 'agent_loop',
        public ?string $agentEventId = null,
    ) {}
}
