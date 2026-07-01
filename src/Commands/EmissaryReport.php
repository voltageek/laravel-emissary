<?php

declare(strict_types=1);

namespace Emissary\Commands;

use Emissary\Models\AgentEvent;
use Emissary\Models\ToolInvocation;
use Emissary\Models\CostLedger;
use Illuminate\Console\Command;

class EmissaryReport extends Command
{
    protected $signature = 'emissary:report {--conversation=} {--since=} {--model=}';
    protected $description = 'Print aggregate summary of agent activity';

    public function handle(): int
    {
        $conversationFilter = $this->option('conversation');
        $since = $this->option('since');

        $turnQuery = AgentEvent::where('kind', 'turn');

        if ($conversationFilter) {
            $turnQuery->where('conversation_id', $conversationFilter);
        }

        if ($since) {
            $turnQuery->where('created_at', '>=', $since);
        }

        $turnCount = $turnQuery->count();
        $successCount = (clone $turnQuery)->where('result', 'success')->count();
        $guardDeniedCount = (clone $turnQuery)->where('result', 'guard_denied')->count();
        $errorCount = (clone $turnQuery)->where('result', 'error')->count();

        $avgLatency = (clone $turnQuery)->avg('latency_ms') ?? 0;

        $toolQuery = ToolInvocation::query();

        if ($since) {
            $toolQuery->where('created_at', '>=', $since);
        }

        $toolCount = $toolQuery->count();
        $toolSuccessCount = (clone $toolQuery)->where('success', true)->count();
        $toolFailureCount = (clone $toolQuery)->where('success', false)->count();

        $topTools = ToolInvocation::selectRaw('tool_name, count(*) as cnt')
            ->groupBy('tool_name')
            ->orderByDesc('cnt')
            ->limit(5)
            ->get();

        $totalCost = CostLedger::sum('cost_usd');

        $this->info('=== Emissary Report ===');
        $this->line("");
        $this->line("Turns: {$turnCount} (success: {$successCount}, denied: {$guardDeniedCount}, error: {$errorCount})");
        $this->line("Avg latency: " . round($avgLatency) . " ms");
        $this->line("Tool invocations: {$toolCount} (success: {$toolSuccessCount}, failed: {$toolFailureCount})");
        $this->line("Total cost: \$" . number_format((float) $totalCost, 6));
        $this->line("");

        if ($topTools->isNotEmpty()) {
            $this->info('Top tools by call count:');
            foreach ($topTools as $tool) {
                $this->line("  {$tool->tool_name}: {$tool->cnt}");
            }
        }

        $this->line("");

        return self::SUCCESS;
    }
}
