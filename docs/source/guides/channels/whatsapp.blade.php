---
extends: _layouts.master
title: WhatsApp
description: Connect Emissary to WhatsApp via WAHA (default) or Meta Business API.
---

@section('body')
<div class="tldr-box">
    <h4>TL;DR — WAHA (Default)</h4>
    <pre><code class="language-bash"># 1. Start WAHA Docker container
docker run -d --name waha -p 3000:3000 devlikeapro/waha

# 2. Set env vars:
WAHA_API_URL=http://localhost:3000
WAHA_API_KEY=your-secret-key

# 3. Start session (creates + starts + configures webhook)
php artisan emissary:waha:session:start
# → Scan QR code with WhatsApp mobile app

# 4. Verify
php artisan emissary:channel:test whatsapp</code></pre>
</div>

## Quick Start — WAHA (Default)

Emissary uses [WAHA](https://github.com/devlikeapro/waha) (WhatsApp HTTP API) as the default WhatsApp backend. WAHA is self-hostable and connects via WhatsApp Web — no Meta Business account required.

### 1. Start WAHA

```bash
docker run -d --name waha -p 3000:3000 devlikeapro/waha
```

### 2. Configure Environment

```bash
WAHA_API_URL=http://localhost:3000
WAHA_API_KEY=your-secret-key
```

### 3. Start Session & Scan QR Code

```bash
php artisan emissary:waha:session:start
```

The command creates the session, configures the webhook URL automatically, and displays a QR code. Scan it with your WhatsApp mobile app to connect.

### 4. Verify

```bash
php artisan emissary:channel:test whatsapp
```

<details class="deep-dive">
    <summary>Deep Dive — WAHA</summary>

### WAHA Credential Matrix

| Env Variable | Config Key | Default | Purpose |
|---|---|---|---|
| `WAHA_API_URL` | `channels.whatsapp.waha_api_url` | `http://localhost:3000` | WAHA instance URL |
| `WAHA_API_KEY` | `channels.whatsapp.waha_api_key` | — | API key for WAHA authentication |
| `WAHA_SESSION` | `channels.whatsapp.waha_session` | `default` | Session name (must be `default` for WAHA free) |
| `WAHA_HMAC_KEY` | `channels.whatsapp.waha_hmac_key` | — | Optional: HMAC-SHA512 key for webhook verification |
| `WAHA_VERSION` | `channels.whatsapp.waha_version` | `free` | `free` (single session) or `plus` (multi-session) |

### Session Management Commands

| Command | Purpose |
|---|---|
| `emissary:waha:session:start` | Create, start, and watch a session |
| `emissary:waha:session:status` | Print session state |
| `emissary:waha:session:stop` | Stop a running session |
| `emissary:waha:session:restart` | Restart a session |
| `emissary:waha:session:qr` | Show QR code for scanning |
| `emissary:waha:session:list` | List all sessions |
| `emissary:waha:session:delete` | Delete a session |

### Webhook Verification

WAHA sends POST webhooks with optional HMAC-SHA512 authentication via the `X-Webhook-Hmac` and `X-Webhook-Hmac-Algorithm` headers. When `WAHA_HMAC_KEY` is configured, Emissary validates the signature. When unset, verification is skipped (dev/trusted-network mode).

### Inbound Messages

Emissary handles WAHA text and media messages. The adapter parses the webhook payload (`payload.from`, `payload.body`, `payload.media`), filters out `fromMe` echoes, resolves the sender identity, and enters the pipeline.

### WAHA Free vs Plus

- **Free**: Single session (`default` only). The `session:start` command warns and forces `default` if you try another name.
- **Plus**: Multi-session support. Use `emissary:channel:add whatsapp --waha-session=my-session` for per-tenant sessions.

    </div>
</details>

<hr>

## Meta Business API (Legacy)

Existing Meta WhatsApp Business API users can stay on the Meta backend by setting `backend = 'meta'` in config.

<div class="tldr-box">
    <h4>TL;DR — Meta (Legacy)</h4>
    <pre><code class="language-bash"># Set env vars:
WHATSAPP_PHONE_NUMBER_ID=123456789
WHATSAPP_ACCESS_TOKEN=EAAx...
WHATSAPP_WEBHOOK_VERIFY_TOKEN=your-verify-token

# Configure webhook in Meta dashboard

# Verify
php artisan emissary:channel:test whatsapp</code></pre>
</div>

### Switching to Meta Backend

Set `backend` to `meta` and update the adapter in `config/emissary.php`:

```php
'channels' => [
    'whatsapp' => [
        'backend'  => 'meta',
        'adapter'  => \Emissary\Channels\MetaWhatsAppAdapter::class,
        // ... Meta credentials
    ],
],
```

### Meta Credential Matrix

| Env Variable | Config Key | Purpose |
|---|---|---|
| `WHATSAPP_PHONE_NUMBER_ID` | `channels.whatsapp.phone_number_id` | Meta phone number identifier |
| `WHATSAPP_ACCESS_TOKEN` | `channels.whatsapp.access_token` | Meta API access token |
| `WHATSAPP_WEBHOOK_VERIFY_TOKEN` | `channels.whatsapp.verify_token` | Webhook verification challenge |
| `WHATSAPP_APP_SECRET` | `channels.whatsapp.app_secret` | HMAC-SHA256 signing secret |

### Webhook Verification (Meta)

Meta sends a `GET` challenge with `hub.mode`, `hub.verify_token`, and `hub.challenge` parameters. Emissary responds with the challenge value only if the verify token matches — otherwise 403. Per-request verification uses `X-Hub-Signature-256` (HMAC-SHA256).
@endsection
