---
extends: _layouts.master
title: Observability & Debugging
description: Trace agent decisions through the pipeline, replay turns from fixtures, and inspect every event.
---

@section('body')
<div class="tldr-box">
    <h4>TL;DR</h4>
    <pre><code class="language-bash"># Replay a turn from fixtures
php artisan emissary:replay {turnId}

# Cost and usage report
php artisan emissary:report

# Clean up old data
php artisan emissary:prune --days=30</code></pre>
</div>

## Quick Start

Every action in Emissary — LLM calls, tool executions, guard decisions, turn completion — emits a typed PSR-14 event carrying a `turn_id`. Events persist to SQL for reporting, auditing, and exact replay.

### Trace a Turn

```bash
php artisan emissary:replay abc-123
```

Output:

```
Turn abc-123 (2026-07-01 10:30:00)
├── IntentClassified: record_sale (confidence: 0.92)
├── GuardDecision: Allow — AuthenticatedUserGuard
├── GuardDecision: Allow — RateLimitGuard (5/60 remaining)
├── ToolInvocation: recordSale(amount: 29.99, item: "T-shirt")
│   └── Completed in 12ms → "Recorded sale of $29.99 for T-shirt."
└── TurnCompleted: Success — 1 tool call, $0.0004 cost
```

</xv-deep-dive>

<details class="deep-dive">
    <summary>Deep Dive</summary>
    <div class="deep-dive-content">

### Event Catalog

| Event | Emitted When | Key Fields |
|---|---|---|
| `AgentCallCompleted` | LLM call finishes | `turn_id`, `model`, `tokens_in`, `tokens_out`, `duration_ms` |
| `ToolInvocationCompleted` | Tool handler returns | `turn_id`, `tool_name`, `arguments`, `result`, `duration_ms` |
| `GuardDecision` | Guard evaluates (deny always; allow when `trace_guard_allows` enabled) | `turn_id`, `checkpoint`, `guard_class`, `result`, `reason` |
| `ConfirmationGateTransitioned` | Confirmation gate changes state | `turn_id`, `from_state`, `to_state` |
| `TurnCompleted` | Turn finishes | `turn_id`, `status`, `tool_count`, `cost` |

### Listener Configuration

Selective capture via `config/emissary.php`:

```php
'observability' => [
    'log_events' => true,                  // Default: on — basic event logging
    'trace_guard_allows' => false,          // Default: off — log Allow decisions too
    'capture_llm_payloads' => false,        // Default: off — full prompt/response (PPI risk)
    'capture_agent_spans' => false,         // Default: off — per-stage timing (storage heavy)
],
```

### Heavy Capture (Opt-In)

Full LLM payloads and per-stage agent spans enable exact replay debugging but are storage-heavy and may contain user PII. Enable with caution and set TTL:

```php
'observability' => [
    'capture_llm_payloads' => true,
    'capture_agent_spans' => true,
    'retention_days' => 30,               // Auto-prune after N days
],
```

### Artisan Commands

| Command | Arguments | Purpose |
|---|---|---|
| `emissary:replay {turnId}` | Turn ID | Replay a turn from fixtures, showing event tree |
| `emissary:report` | `--from=`, `--to=`, `--model=` | Cost and usage report |
| `emissary:prune` | `--days=30` | Delete old events and conversations |
| `emissary:fixture:capture {turnId}` | Turn ID | Capture a turn as a replayable fixture |

### Event Export Hook

Emissary events can be forwarded to external monitoring:

```php
// AppServiceProvider::boot()
Event::listen(Emissary\Events\TurnCompleted::class, function ($event) {
    // Forward to Datadog, Sentry, etc.
    Metrics::increment('emissary.turns', 1, ['status' => $event->status]);
});
```

    </div>
</details>
@endsection
