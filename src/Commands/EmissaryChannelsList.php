<?php

declare(strict_types=1);

namespace Emissary\Commands;

use Emissary\Models\ChannelConfig;
use Illuminate\Console\Command;

class EmissaryChannelsList extends Command
{
    protected $signature = 'emissary:channels:list';
    protected $description = 'Lists configured and provisioned channels with status';

    public function handle(): int
    {
        $channels = config('emissary.channels', []);
        $dbConfigs = ChannelConfig::all();

        $this->info('Configured channels (from config):');
        foreach ($channels as $name => $config) {
            $adapter = $config['adapter'] ?? 'unknown';
            $hasToken = ! empty($config['access_token'] ?? $config['bot_token'] ?? null);
            $status = $hasToken ? 'configured' : 'missing credentials';
            $this->line("  {$name}: {$adapter} — {$status}");
        }

        if ($dbConfigs->isNotEmpty()) {
            $this->info('Provisioned channels (from DB):');
            foreach ($dbConfigs as $c) {
                $this->line("  {$c->channel} — {$c->label} — {$c->status}");
            }
        }

        return self::SUCCESS;
    }
}
