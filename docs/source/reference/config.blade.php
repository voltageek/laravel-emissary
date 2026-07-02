---
extends: _layouts.master
title: Configuration Reference
description: Every config key in config/emissary.php with type, default, env-var override, and security classification.
---

@section('body')
<div class="tldr-box">
    <h4>TL;DR</h4>
    <p><code>config/emissary.php</code> drives all Emissary behavior. Published via <code>php artisan vendor:publish --tag=emissary-config</code>.</p>
</div>

## Quick Start

```bash
php artisan vendor:publish --tag=emissary-config
```

This creates `config/emissary.php` in your app. All behavior is config-driven.

## Model Configuration

| Key | Type | Default | Env Override | Security |
|---|---|---|---|---|
| `default_model` | string | `google/gemma-4-31b-it` | `AGENT_DEFAULT_MODEL` | Public |
| `complex_model` | string | `google/gemma-4-31b-it` | `AGENT_COMPLEX_MODEL` | Public |
| `vision_model` | string | `google/gemma-4-31b-it` | `AGENT_VISION_MODEL` | Public |

## Cost Tracking

| Key | Type | Default | Notes |
|---|---|---|---|
| `model_rates` | array | `{google/gemma-4-31b-it: {input_per_m: 0.12, output_per_m: 0.35}}` | Per-million token rates; only needed if `UpdateCostLedger` listener is registered (opt-in) |

## LLM Gateway

| Key | Type | Default | Env Override | Security |
|---|---|---|---|---|
| `openrouter.api_key` | string | — | `OPENROUTER_API_KEY` | **Secret** — never commit |
| `openrouter.base_url` | string | `https://openrouter.ai/api/v1` | `OPENROUTER_BASE_URL` | Public |
| `openrouter.timeout` | int | `60` | — | Public |
| `openrouter.retries` | int | `2` | — | Public |

## Model Routing

| Key | Type | Default | Purpose |
|---|---|---|---|
| `complex_intents` | array | `['query_financials', 'unknown']` | Intents routed to complex model |
| `confidence_escalation_threshold` | float | `0.5` | Below this → escalate to complex model |

## Intent Fallback

| Key | Type | Default | Purpose |
|---|---|---|---|
| `intent_confidence_threshold` | float | `0.4` | Below this → clarification response |

## Intents → Tools

| Key | Type | Default | Purpose |
|---|---|---|---|
| `intents` | array | `[]` | Base intent → tools mapping; extended by plugins |

## Rate Limiting

| Key | Type | Default | Purpose |
|---|---|---|---|
| `rate_limit.per_minute` | int | `10` | Max requests per minute per user; enforced by `RateLimitGuard` |

## Authentication

| Key | Type | Default | Purpose |
|---|---|---|---|
| `require_auth_intents` | array | `[]` | Intents requiring authenticated user; enforced by `AuthenticatedUserGuard` |

## Conversation Limits

| Key | Type | Default | Purpose |
|---|---|---|---|
| `confirmation_timeout_seconds` | int | `900` | Confirmation gate timeout (15 min) |
| `max_conversation_turns` | int | `24` | Max turns per conversation |
| `max_tool_call_rounds` | int | `5` | Max tool-calling loop iterations |

## Memory

| Key | Type | Default | Purpose |
|---|---|---|---|
| `memory.activity_gap_minutes` | int | `30` | Gap before new conversation starts |
| `memory.token_budget` | int | `4096` | Max tokens in conversation context |

## Channels

<table>
<tr><th>Key</th><th>Type</th><th>Default</th><th>Security</th></tr>
<tr><td><code>webhook_path</code></td><td>string</td><td><code>webhooks</code></td><td>Public</td></tr>
 <tr><td><code>channels.whatsapp.backend</code></td><td>string</td><td><code>waha</code></td><td>Public</td></tr>
 <tr><td><code>channels.whatsapp.adapter</code></td><td>class</td><td><code>WahaWhatsAppAdapter::class</code></td><td>Internal</td></tr>
 <tr><td><code>channels.whatsapp.waha_api_url</code></td><td>string</td><td><code>http://localhost:3000</code></td><td>Public</td></tr>
 <tr><td><code>channels.whatsapp.waha_api_key</code></td><td>string</td><td>—</td><td><strong>Secret</strong></td></tr>
 <tr><td><code>channels.whatsapp.waha_session</code></td><td>string</td><td><code>default</code></td><td>Public</td></tr>
 <tr><td><code>channels.whatsapp.waha_hmac_key</code></td><td>string</td><td>—</td><td><strong>Secret</strong></td></tr>
 <tr><td><code>channels.whatsapp.waha_version</code></td><td>string</td><td><code>free</code></td><td>Public</td></tr>
 <tr><td><em>channels.whatsapp.access_token</em></td><td>string</td><td>—</td><td><strong>Secret</strong> (legacy Meta)</td></tr>
 <tr><td><em>channels.whatsapp.phone_number_id</em></td><td>string</td><td>—</td><td>Public (legacy Meta)</td></tr>
 <tr><td><em>channels.whatsapp.app_secret</em></td><td>string</td><td>—</td><td><strong>Secret</strong> (legacy Meta)</td></tr>
 <tr><td><em>channels.whatsapp.verify_token</em></td><td>string</td><td>—</td><td>Public (legacy Meta)</td></tr>
 <tr><td><code>channels.telegram.adapter</code></td><td>class</td><td><code>TelegramAdapter::class</code></td><td>Internal</td></tr>
 <tr><td><code>channels.telegram.bot_token</code></td><td>string</td><td>—</td><td><strong>Secret</strong></td></tr>
 <tr><td><code>channels.telegram.secret_token</code></td><td>string</td><td>—</td><td><strong>Secret</strong></td></tr>
 <tr><td><code>channels.web.adapter</code></td><td>class</td><td><code>WebChatAdapter::class</code></td><td>Internal</td></tr>
</table>

Env overrides: `WHATSAPP_ACCESS_TOKEN`, `WHATSAPP_PHONE_NUMBER_ID`, `WHATSAPP_APP_SECRET`, `WHATSAPP_VERIFY_TOKEN`, `TELEGRAM_BOT_TOKEN`, `TELEGRAM_SECRET_TOKEN`

### Channel Credential Store

| Key | Type | Default | Purpose |
|---|---|---|---|
| `channel_credential_store` | class | `ConfigChannelCredentialStore::class` | Swap for `EncryptedChannelCredentialStore` for DB-backed provisioning |

## Error Messages

| Key | AgentError Code | Default Message |
|---|---|---|
| `error_messages.guard.denied` | — | "Sorry, you're not able to do that." |
| `error_messages.auth.unauthenticated` | — | "You need to be logged in to do that." |
| `error_messages.auth.unauthorized` | — | "You don't have permission to do that." |
| `error_messages.intent.low_confidence` | — | "I'm not sure I understood that — could you rephrase?" |
| `error_messages.intent.unknown` | — | "I can help with several things. What would you like to do?" |
| `error_messages.tool.execution_failed` | — | "Something went wrong processing your request." |
| `error_messages.tool.invalid_arguments` | — | "I couldn't complete that with the details given." |
| `error_messages.security.jailbreak` | — | "I can't help with that." |
| `error_messages.cost.limit_exceeded` | — | "You've hit the usage limit for this conversation." |
| `error_messages.llm.timeout` | — | "I'm taking too long to respond. Please try again." |
| `error_messages.llm.rate_limited` | — | "I'm temporarily unavailable. Please try again shortly." |
| `error_messages.llm.error` | — | "I encountered an error. Please try again." |
| `error_messages.onboarding.required` | — | "Let's get you set up first." |
| `error_messages.agent.max_rounds` | — | "I've reached my step limit." |
| `error_messages.conversation.max_turns` | — | "We've reached the limit. Start a new one?" |
| `error_messages.channel.delivery_failed` | — | "I couldn't deliver that message. Please try again." |

## Cost Alerts

| Key | Type | Default | Purpose |
|---|---|---|---|
| `cost_alerts.per_conversation_max_usd` | float | `0.10` | Enforced by `CostCapGuard` |

## Security

| Key | Type | Default | Purpose |
|---|---|---|---|
| `security.jailbreak.enabled` | bool | `true` | Enable jailbreak detection |
| `security.jailbreak.model` | ?string | `null` | Model for jailbreak detection (null = default) |
| `security.tool_result_wrap` | bool | `true` | Wrap tool results to prevent injection |
| `security.require_webhook_verify` | bool | `true` | Require webhook signature verification |

## Data Retention

| Key | Type | Default | Purpose |
|---|---|---|---|
| `retention.message_ttl_days` | int | `90` | Message retention (days) |
| `retention.event_ttl_days` | int | `90` | Event retention (days) |
| `retention.payload_ttl_days` | int | `30` | LLM payload retention (days) |
| `retention.span_ttl_days` | int | `14` | Agent span retention (days) |

## Observability

| Key | Type | Default | Purpose |
|---|---|---|---|
| `observability.trace_guard_allows` | bool | `false` | Log Allow decisions too |
| `observability.capture_llm_payloads` | bool | `false` | Capture full prompt/response (opt-in, PII risk) |
| `observability.capture_trace_spans` | bool | `false` | Capture per-stage timing (opt-in, storage heavy) |
| `observability.otel.enabled` | bool | `false` | OpenTelemetry export |

## User Onboarding

| Key | Type | Default | Purpose |
|---|---|---|---|
| `onboarding.enabled` | bool | `false` | Enable onboarding flow (opt-in) |
| `onboarding.mode` | string | `hybrid` | `channel_first`, `auth_first`, or `hybrid` |
| `onboarding.welcome_message` | string | — | First-contact welcome message |
| `onboarding.fields` | array | `['name', 'email']` | Profile fields to collect |
| `onboarding.require_consent` | bool | `true` | Require user consent |
| `onboarding.consent_text` | string | — | Consent message shown to user |
| `onboarding.consent_version` | string | `1.0` | Consent version for re-consent flows |
| `onboarding.gated_intents` | array | `['*']` | Intents requiring completed onboarding |
| `onboarding.guest_role` | string | `guest` | Role assigned to guest users |
@endsection
