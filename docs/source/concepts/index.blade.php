---
extends: _layouts.master
title: Core Concepts
description: Understand intents, tools, and guards — the three building blocks of Emissary.
---

@section('body')
<div class="tldr-box">
    <h4>TL;DR</h4>
    <p>Define <strong>intents</strong> (what users ask), <strong>tools</strong> (what the agent can do), and <strong>guards</strong> (safety rules). Emissary handles everything else.</p>
</div>

## Quick Start

Emissary is a **pipeline-based conversational agent**. It:

1. Receives messages from channels (WhatsApp, Telegram, Web)
2. Resolves the sender's identity
3. Evaluates guards at defined checkpoints
4. Classifies intent using an LLM
5. Runs a tool-calling agent loop
6. Persists all activity (messages, events, costs)
7. Delivers responses back through the originating channel

### The Architecture

<div class="mermaid">
graph TD
    A[Channel Adapter] -->|InboundMessage| B[Message Bridge]
    B -->|tenancy resolution| C[Guard Registry]
    C -->|beforeIntent checkpoint| D[Intent Router]
    D -->|classified intent| E[Guard Registry]
    E -->|beforeExecution checkpoint| F[Model Selector]
    F -->|selected model| G[Tool Registry]
    G -->|resolved tools| H[Task Agent]
    H -->|tool calls| I[Tool Handlers]
    I -->|results| H
    H -->|AgentResponse| J[Channel Adapter]
    J -->|OutboundMessage| A
</div>

## Intents

An **intent** is what the user wants to do — `record_sale`, `check_inventory`, `process_refund`. You declare intents in your plugin:

```php
public function getIntents(): array
{
    return ['record_sale', 'check_inventory', 'process_refund'];
}
```

The intent router classifies messages using the LLM, matching natural language to your declared intents.

**Config keys**: `intents`, `complex_intents`, `intent_confidence_threshold`, `confidence_escalation_threshold`

**Learn more**: [Intents →](/concepts/intents)

## Tools

A **tool** is a PHP method the agent can call. Define tools with the `#[Tool]` attribute:

```php
#[Tool(name: 'record_sale', description: 'Record a sale transaction.')]
public function recordSale(float $amount, string $item): string
{
    // ...
}
```

The `ToolScanner` derives JSON schemas from PHP types and binds the method as the handler.

**Config keys**: `intents.&lt;slug&gt;.tools` (maps tools to intents)

**Learn more**: [Tools →](/concepts/tools) | [Tool Authoring →](/guides/tool-authoring)

## Guards

A **guard** is a safety rule evaluated at checkpoints in the pipeline. Three checkpoints:

| Checkpoint | Fires | Purpose |
|---|---|---|
| `beforeIntent` | After message reception | Authentication, rate limiting |
| `beforeExecution` | After intent classification | Authorization, cost caps |
| `afterExecution` | After agent response | Post-hoc auditing |

Guards return `GuardResult` — `allow()`, `deny(reason)`, or `pending(reason)`. First deny short-circuits.

**Config keys**: `guards`, `rate_limit`, `cost_cap`, `max_turns`

**Learn more**: [Guards →](/concepts/guards) | [Guard Authoring →](/guides/guard-authoring)
@endsection
