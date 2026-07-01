<?php

declare(strict_types=1);

namespace Emissary\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class EmissaryPrune extends Command
{
    protected $signature = 'emissary:prune';
    protected $description = 'Delete rows older than their TTL across all emissary tables';

    public function handle(): int
    {
        $this->info('Pruning old emissary data...');

        $this->pruneTable('conversation_messages', 'emissary.retention.message_ttl_days', 90);
        $this->pruneTable('agent_events', 'emissary.retention.event_ttl_days', 90);
        $this->pruneTable('tool_invocations', 'emissary.retention.event_ttl_days', 90);
        $this->pruneTable('llm_payloads', 'emissary.retention.payload_ttl_days', 30);
        $this->pruneTable('agent_spans', 'emissary.retention.span_ttl_days', 14);

        $this->info('Prune complete.');

        return self::SUCCESS;
    }

    private function pruneTable(string $table, string $configKey, int $defaultDays): void
    {
        $days = config($configKey, $defaultDays);
        $cutoff = now()->subDays($days);

        $deleted = DB::table($table)->where('created_at', '<', $cutoff)->delete();

        if ($deleted > 0) {
            $this->line("  {$table}: deleted {$deleted} rows (TTL: {$days} days)");
        }
    }
}
