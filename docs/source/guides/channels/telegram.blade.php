---
extends: _layouts.master
title: Telegram
description: Connect Emissary to a Telegram bot in under 30 minutes.
---

@section('body')
<div class="tldr-box">
    <h4>TL;DR</h4>
    <pre><code class="language-bash"># 1. Create bot with @BotFather → get token
# 2. Set env vars:
TELEGRAM_BOT_TOKEN=123456:ABC-DEF1234ghIkl-zyx57W2v1u123ew11
TELEGRAM_WEBHOOK_SECRET=your-secret-here

# 3. Set webhook
php artisan emissary:set-telegram-webhook

# 4. Verify
php artisan emissary:channel:test telegram</code></pre>
</div>

## Quick Start

Emissary supports Telegram out of the box. The adapter handles webhook verification, message parsing, and response delivery.

### 1. Create a Bot

1. Open Telegram and message [@BotFather](https://t.me/BotFather)
2. Send `/newbot` and follow the prompts
3. Save the bot token you receive (format: `123456:ABC-DEF...`)

### 2. Configure Environment

Add to your `.env`:

```bash
TELEGRAM_BOT_TOKEN=123456:ABC-DEF1234ghIkl-zyx57W2v1u123ew11
TELEGRAM_WEBHOOK_SECRET=your-random-secret-here
```

The webhook secret protects against spoofed requests. Generate a random string (e.g., `php artisan emissary:secret`).

### 3. Set the Webhook

```bash
php artisan emissary:set-telegram-webhook
```

This registers your app's webhook URL with Telegram's API. Emissary serves at `/emissary/webhook/telegram`.

### 4. Test

```bash
php artisan emissary:channel:test telegram
```

Send a message to your bot on Telegram. You should see the agent respond.

<details class="deep-dive">
    <summary>Deep Dive</summary>
    <div class="deep-dive-content">

### Credential Matrix

| Env Variable | Config Key | Required | Purpose |
|---|---|---|---|
| `TELEGRAM_BOT_TOKEN` | `channels.telegram.bot_token` | Yes | Authenticates requests to Telegram API |
| `TELEGRAM_WEBHOOK_SECRET` | `channels.telegram.webhook_secret` | Yes | Verifies incoming webhook signatures |

### Webhook Verification

Every inbound Telegram webhook is verified via `ChannelAdapter::verify()`. The adapter computes a HMAC signature and compares it against the `X-Telegram-Bot-Api-Secret-Token` header. Invalid signatures receive HTTP 401 — the request never enters the pipeline.

### Webhook URL

By default: `https://your-app.com/emissary/webhook/telegram`

Customize via `config/emissary.php`:

```php
'channels' => [
    'telegram' => [
        'webhook_url' => env('TELEGRAM_WEBHOOK_URL', '/emissary/webhook/telegram'),
    ],
],
```

### Channel Setup Command

```bash
php artisan emissary:channel:setup telegram
```

Validates:
- Bot token is configured
- Webhook secret is set
- Webhook URL is publicly reachable
- Bot responds to API calls

### Edge Cases

<div class="callout callout-warning">
    <strong>Webhook URL behind firewall?</strong> Use ngrok for local development: <code>ngrok http 8000</code> → update <code>TELEGRAM_WEBHOOK_URL</code> to your ngrok URL.
</div>

<div class="callout callout-info">
    <strong>Multiple bots?</strong> Configure each in <code>channels.telegram.instances</code> array. Each instance gets its own bot token and webhook route.
</div>

    </div>
</details>
@endsection
