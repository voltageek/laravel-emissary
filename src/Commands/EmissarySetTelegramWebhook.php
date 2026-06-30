<?php

declare(strict_types=1);

namespace Emissary\Commands;

use GuzzleHttp\Client;
use Illuminate\Console\Command;

class EmissarySetTelegramWebhook extends Command
{
    protected $signature = 'emissary:set-telegram-webhook';
    protected $description = 'Set the Telegram webhook URL';

    public function handle(): int
    {
        $botToken = config('emissary.channels.telegram.bot_token');
        $secretToken = config('emissary.channels.telegram.secret_token');
        $appUrl = config('app.url', 'http://localhost');
        $prefix = config('emissary.webhook_path', 'webhooks');

        if (empty($botToken)) {
            $this->error('TELEGRAM_BOT_TOKEN is not configured.');

            return self::FAILURE;
        }

        $url = rtrim($appUrl, '/') . '/' . $prefix . '/telegram';

        $this->info("Setting Telegram webhook to: {$url}");

        $client = new Client();

        try {
            $response = $client->post("https://api.telegram.org/bot{$botToken}/setWebhook", [
                'json' => [
                    'url' => $url,
                    'secret_token' => $secretToken,
                ],
            ]);

            $body = json_decode($response->getBody()->getContents(), true);

            if ($body['ok'] ?? false) {
                $this->info('Webhook set successfully.');

                return self::SUCCESS;
            }

            $this->warn('Webhook may not have been set: ' . ($body['description'] ?? 'Unknown error'));

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Failed: ' . $e->getMessage());

            return self::FAILURE;
        }
    }
}
