---
extends: _layouts.master
title: Pipeline
description: How messages flow through Emissary — from channel reception to agent response.
---

@section('body')
<div class="tldr-box">
    <h4>TL;DR</h4>
    <p>The pipeline processes every message through 7 stages: Channel → Bridge → Guards → Intent Router → Model Selector → Tool Registry → Task Agent → Response.</p>
</div>

## Quick Start

<div class="mermaid">
graph TD
    A[Channel Adapter] -->|parse + verify| B[InboundMessage DTO]
    B -->|dispatch| C[Message Bridge]
    C -->|resolve tenancy| D[Conversation Memory]
    C -->|dispatch| E[Guard Registry]
    E -->|beforeIntent| F[/Guards evaluated/]
    F -->|allow| G[Intent Router]
    G -->|LLM classification| H[IntentResult DTO]
    H -->|dispatch| I[Guard Registry]
    I -->|beforeExecution| J[/Guards evaluated/]
    J -->|allow| K[Model Selector]
    K -->|config-driven| L[Model tier]
    L -->|dispatch| M[Tool Registry]
    M -->|resolve tools| N[Tool Set]
    N -->|dispatch| O[Task Agent]
    O -->|LLM tool-calling loop| P[/Tool invocations/]
    P -->|aggregate| Q[AgentResponse DTO]
    Q -->|format| R[Channel Adapter]
    R -->|send| S[OutboundMessage]
</div>

## Pipeline Stages

| Stage | Component | Purpose | Config Keys |
|---|---|---|---|
| 1. Reception | `ChannelAdapter` | Parse, verify, create `InboundMessage` | `channels` |
| 2. Bridge | `Message Bridge` | Tenancy, conversation management | `tenancy` |
| 3. Guard (1) | `GuardRegistry` | `beforeIntent` checkpoint | `guards`, `rate_limit` |
| 4. Intent | `IntentRouter` | LLM classification | `intents`, `complex_intents` |
| 5. Guard (2) | `GuardRegistry` | `beforeExecution` checkpoint | `cost_cap`, `max_turns` |
| 6. Model | `ModelSelector` | Config-driven model routing | `default_model`, `complex_model` |
| 7. Tools | `ToolRegistry` | Resolve tools for intent | `intents.&lt;slug&gt;.tools` |
| 8. Agent | `TaskAgent` | LLM tool-calling loop | `openrouter`, `model_rates` |
| 9. Response | `ChannelAdapter` | Format + deliver | Channel-specific |

### Observability

Every stage emits typed events with `turn_id` propagation. See [Observability & Debugging →](/operations/observability).
@endsection
