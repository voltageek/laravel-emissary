<?php

declare(strict_types=1);

namespace Emissary\Listeners;

use Emissary\Events\ToolInvocationCompleted;
use Emissary\Models\ToolInvocation;

class LogToolInvocation
{
    public function handle(ToolInvocationCompleted $event): void
    {
        ToolInvocation::create([
            'turn_id' => $event->turnId,
            'conversation_id' => $event->conversationId,
            'tool_name' => $event->toolName,
            'arguments' => $event->arguments,
            'result_summary' => $event->resultSummary,
            'duration_ms' => $event->durationMs,
            'success' => $event->success,
            'validation_error' => $event->validationError,
            'triggered_via' => $event->triggeredVia,
            'agent_event_id' => $event->agentEventId,
            'created_at' => now(),
        ]);
    }
}
