<?php

declare(strict_types=1);

namespace Emissary\Listeners;

use Emissary\Models\AgentSpan;

class LogTraceSpan
{
    public function record(string $turnId, string $conversationId, string $stage, int $durationMs): void
    {
        if (! config('emissary.observability.capture_trace_spans', false)) {
            return;
        }

        AgentSpan::create([
            'turn_id' => $turnId,
            'conversation_id' => $conversationId,
            'stage' => $stage,
            'duration_ms' => $durationMs,
            'created_at' => now(),
        ]);
    }
}
