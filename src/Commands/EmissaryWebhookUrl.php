<?php

declare(strict_types=1);

namespace Emissary\Commands;

use Illuminate\Console\Command;

class EmissaryWebhookUrl extends Command
{
    protected $signature = 'emissary:webhook:url {channel : whatsapp|telegram|web}';
    protected $description = 'Print the absolute webhook URL for a channel';

    public function handle(): int
    {
        $channel = $this->argument('channel');
        $prefix = config('emissary.webhook_path', 'webhooks');
        $appUrl = config('app.url', 'http://localhost');

        $url = rtrim($appUrl, '/') . '/' . $prefix . '/' . $channel;

        $this->info("Webhook URL for {$channel}:");
        $this->line("  {$url}");

        return self::SUCCESS;
    }
}
