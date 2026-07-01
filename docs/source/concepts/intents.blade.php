---
extends: _layouts.master
title: Intents
description: Declare what your users can ask the agent to do.
---

@section('body')
<div class="tldr-box">
    <h4>TL;DR</h4>
    <p>Intents map user messages to agent capabilities. Declare them in your plugin, configure classification hints, and map tools to each intent.</p>
</div>

## Quick Start

Declare intents in your `AgentToolProvider`:

```php
public function getIntents(): array
{
    return ['record_sale', 'check_inventory', 'process_refund'];
}
```

### Intent Configuration

For each intent, define the model tier and associated tools:

```php
public function getIntentConfig(): array
{
    return [
        'record_sale' => ['model' => 'default', 'tools' => ['record_sale']],
        'check_inventory' => ['model' => 'default', 'tools' => ['check_inventory']],
        'process_refund' => ['model' => 'complex', 'tools' => ['process_refund', 'check_inventory']],
    ];
}
```

### Classification Hints

Help the LLM classify messages accurately:

```php
public function getIntentClassificationHints(): array
{
    return [
        'record_sale' => 'User wants to record a sale or transaction',
        'check_inventory' => 'User asks about stock levels or product availability',
        'process_refund' => 'User wants a refund or return',
    ];
}
```

<details class="deep-dive">
    <summary>Deep Dive</summary>
    <div class="deep-dive-content">

### Model Routing

Config-driven model selection:

| Config Key | Purpose |
|---|---|
| `complex_intents` | Intents routed to the complex model |
| `confidence_escalation_threshold` | Below this confidence → escalate to complex model |

### Intent Fallback

When confidence is below `intent_confidence_threshold`, the agent sends a clarification response instead of guessing.

### Config Reference

See [Configuration Reference →](/reference/config) for all intent-related config keys.

    </div>
</details>
@endsection
