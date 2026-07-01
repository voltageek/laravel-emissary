<?php

declare(strict_types=1);

namespace Emissary\Commands;

use Emissary\Models\AgentEvent;
use Emissary\Models\ToolInvocation;
use Emissary\Models\ConversationMessage;
use Illuminate\Console\Command;

class EmissaryReplay extends Command
{
    protected $signature = 'emissary:replay {turn_id : The turn UUID to replay} {--re-run : Re-send captured payloads to LLM and diff response}';
    protected $description = 'Reconstruct and optionally re-run a captured turn';

    public function handle(): int
    {
        $turnId = $this->argument('turn_id');

        $events = AgentEvent::where('turn_id', $turnId)
            ->orderBy('created_at')
            ->get();

        $tools = ToolInvocation::where('turn_id', $turnId)
            ->orderBy('created_at')
            ->get();

        $messages = ConversationMessage::where('turn_id', $turnId)
            ->orderBy('created_at')
            ->get();

        $this->info("=== Turn Trace: {$turnId} ===");
        $this->line("Events: " . $events->count());
        $this->line("Tool invocations: " . $tools->count());
        $this->line("Messages: " . $messages->count());

        foreach ($events as $event) {
            $this->line("  [{$event->kind}] {$event->created_at} — {$event->result}");
        }

        if ($this->option('re-run')) {
            $this->info('--re-run is enabled but requires llm_payloads capture to be active.');
            $this->info('Set observability.capture_llm_payloads to true and re-capture the turn.');
        }

        return self::SUCCESS;
    }
}
