---
extends: _layouts.master
title: Getting Started
description: Add agentic, multi-channel conversational AI to your Laravel application in under 10 minutes.
---

@section('body')
<div class="tldr-box">
    <h4>TL;DR</h4>
    <pre><code class="language-bash">composer require voltageek/laravel-emissary
php artisan vendor:publish --tag=emissary-config --tag=emissary-migrations
php artisan migrate
# Set OPENROUTER_API_KEY in .env
# Add &lt;x-emissary-web /&gt; to any Blade view
php artisan serve</code></pre>
</div>

## Quick Start

Emissary adds conversational AI to any Laravel app. You define **intents** (what users ask), **tools** (what the agent can do), and **guards** (safety rules). The library handles routing, LLM calls, memory, and multi-channel delivery.

### 1. Install

```bash
composer require voltageek/laravel-emissary
```

The service provider (`Emissary\EmissaryServiceProvider`) is auto-discovered by Laravel.

### 2. Publish Configuration & Migrations

```bash
php artisan vendor:publish --tag=emissary-config --tag=emissary-migrations
php artisan migrate
```

This publishes `config/emissary.php` (254 lines of config-driven behaviour) and the conversation, event, and tool-invocation tables.

### 3. Set Your OpenRouter API Key

Add to `.env`:

```bash
OPENROUTER_API_KEY=sk-or-v1-your-key-here
```

<div class="callout callout-info">
    <strong>No API key?</strong> Sign up at <a href="https://openrouter.ai">openrouter.ai</a> for a free tier. Emissary supports any OpenAI-compatible endpoint — configure <code>openrouter.base_url</code> and <code>openrouter.api_key</code> in <code>config/emissary.php</code> if using a different provider.
</div>

### 4. Add the Web Widget

Drop the Blade component into any view:

    ```blade
    &lt;x-emissary-web /&gt;
    ```

That's it. No additional configuration — the widget connects to the default chat endpoint.

### 5. Test It

```bash
php artisan serve
```

Visit your app, open the chat widget, and say "hello". The agent responds.

## What Just Happened?

1. The web widget sent your message through Emissary's pipeline
2. A guard checkpoint verified the request (security controls default **on**)
3. The intent router classified your message via `OPENROUTER_API_KEY`
4. The agent responded through the web channel

## Where to Go Next

- **[Core Concepts →](/concepts)** — Understand intents, tools, and guards
- **[Tool Authoring →](/guides/tool-authoring)** — Add your first `#[Tool]`
- **[Channel Setup →](/guides/channels/telegram)** — Connect Telegram or WhatsApp

## Troubleshooting

<div class="callout callout-warning">
    <strong>"No OPENROUTER_API_KEY set"</strong><br>
    Verify your <code>.env</code> has <code>OPENROUTER_API_KEY</code>. See the <a href="/reference/config">Configuration Reference</a> for all env vars.
</div>

<div class="callout callout-warning">
    <strong>"Route not found" or widget doesn't appear</strong><br>
    Emissary registers routes under <code>/emissary/</code>. If you have conflicting routes, customize the prefix in <code>config/emissary.php</code> → <code>routing.prefix</code>. See the <a href="/reference/config">Configuration Reference</a>.
</div>
@endsection
