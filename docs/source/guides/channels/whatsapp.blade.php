---
extends: _layouts.master
title: WhatsApp
description: Connect Emissary to the WhatsApp Business API.
---

@section('body')
<div class="tldr-box">
    <h4>TL;DR</h4>
    <pre><code class="language-bash"># 1. Set up Meta Business app → get phone number ID + access token
# 2. Set env vars:
WHATSAPP_PHONE_NUMBER_ID=123456789
WHATSAPP_ACCESS_TOKEN=EAAx...
WHATSAPP_WEBHOOK_VERIFY_TOKEN=your-verify-token

# 3. Configure webhook in Meta dashboard → your-app.com/emissary/webhook/whatsapp
# 4. Verify
php artisan emissary:channel:test whatsapp</code></pre>
</div>

## Quick Start

### 1. Meta Business Setup

1. Go to [Meta for Developers](https://developers.facebook.com/)
2. Create a Business app with the WhatsApp product
3. Get your **Phone Number ID** and a **Permanent Access Token**
4. Set up a **Webhook** pointing to `https://your-app.com/emissary/webhook/whatsapp`

### 2. Configure Environment

```bash
WHATSAPP_PHONE_NUMBER_ID=123456789
WHATSAPP_ACCESS_TOKEN=EAAx...
WHATSAPP_WEBHOOK_VERIFY_TOKEN=your-verify-token
```

### 3. Verify

```bash
php artisan emissary:channel:setup whatsapp
php artisan emissary:channel:test whatsapp
```

<details class="deep-dive">
    <summary>Deep Dive</summary>

### Credential Matrix

| Env Variable | Config Key | Required | Purpose |
|---|---|---|---|
| `WHATSAPP_PHONE_NUMBER_ID` | `channels.whatsapp.phone_number_id` | Yes | Meta phone number identifier |
| `WHATSAPP_ACCESS_TOKEN` | `channels.whatsapp.access_token` | Yes | Meta API access token |
| `WHATSAPP_WEBHOOK_VERIFY_TOKEN` | `channels.whatsapp.webhook_verify_token` | Yes | Webhook verification challenge |

### Webhook Verification

Meta sends a `GET` challenge with `hub.mode`, `hub.verify_token`, and `hub.challenge` parameters. Emissary responds with the challenge value only if the verify token matches — otherwise 403.

### Inbound Messages

Emissary handles WhatsApp text and media messages. The adapter parses the webhook payload, resolves the sender identity, and enters the pipeline.

    </div>
</details>
@endsection
