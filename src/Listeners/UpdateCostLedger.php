<?php

declare(strict_types=1);

namespace Emissary\Listeners;

use Emissary\Events\AgentCallCompleted;
use Emissary\Models\CostLedger;

class UpdateCostLedger
{
    public function handle(AgentCallCompleted $event): void
    {
        $rates = config('emissary.model_rates', []);
        $modelRate = $rates[$event->model] ?? null;

        if ($modelRate === null) {
            return;
        }

        $inputCost = ($event->inputTokens / 1_000_000) * ($modelRate['input_per_m'] ?? 0);
        $outputCost = ($event->outputTokens / 1_000_000) * ($modelRate['output_per_m'] ?? 0);
        $totalCost = $inputCost + $outputCost;

        $month = now()->format('Y-m');

        $ledger = CostLedger::where('conversation_id', $event->conversationId)
            ->where('month', $month)
            ->first();

        if ($ledger) {
            $ledger->increment('input_tokens', $event->inputTokens);
            $ledger->increment('output_tokens', $event->outputTokens);
            $ledger->increment('cost_usd', $totalCost);
        } else {
            CostLedger::create([
                'conversation_id' => $event->conversationId,
                'month' => $month,
                'input_tokens' => $event->inputTokens,
                'output_tokens' => $event->outputTokens,
                'cost_usd' => $totalCost,
                'created_at' => now(),
            ]);
        }
    }
}
