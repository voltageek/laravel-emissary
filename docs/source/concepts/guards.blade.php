---
extends: _layouts.master
title: Guards
description: Security rules evaluated at pipeline checkpoints — authentication, rate limiting, cost control.
---

@section('body')
<div class="tldr-box">
    <h4>TL;DR</h4>
    <p>Guards are safety rules that run at three checkpoints. First deny short-circuits. Built-in guards cover rate limiting, cost caps, jailbreak detection, and authentication.</p>
</div>

## Quick Start

Guards fire at three checkpoints in order:

<div class="mermaid">
sequenceDiagram
    participant C as Channel
    participant G as Guard Registry
    participant P as Pipeline

    C->>G: Inbound message
    G->>G: beforeIntent (auth, rate limit)
    G->>P: Allowed
    
    P->>G: Intent classified
    G->>G: beforeExecution (cost cap, authorization)
    G->>P: Allowed
    
    P->>G: Agent responded
    G->>G: afterExecution (post-hoc audit)
    G->>C: Response delivered
</div>

### Guard Result

Every guard returns a `GuardResult`:

```php
GuardResult::allow();              // Proceed
GuardResult::deny('Rate limit exceeded');  // Block with message
GuardResult::pending('Confirm refund?');   // Require confirmation
```

First `deny` stops evaluation — the denied message goes directly to the user.

<details class="deep-dive">
    <summary>Deep Dive</summary>
    <div class="deep-dive-content">

### Built-in Guards

| Guard | Checkpoint | Config Keys | Purpose |
|---|---|---|---|
| `AuthenticatedUserGuard` | `beforeIntent` | — | Requires authenticated user |
| `RateLimitGuard` | `beforeIntent` | `rate_limit.max_requests`, `rate_limit.window_seconds` | Limits requests per window |
| `JailbreakDetectionGuard` | `beforeIntent` | `security.jailbreak_detection` | Detects prompt injection |
| `CostCapGuard` | `beforeExecution` | `cost_cap.max_cost_per_turn` | Limits spending per turn |
| `MaxTurnsGuard` | `beforeExecution` | `max_turns` | Limits conversation turns |
| `OnboardingGuard` | `beforeIntent` | `onboarding` | Controls onboarding flow |

### Evaluation Order

Guards run in registration order. A deny at any checkpoint stops the pipeline.

### Config-Driven

All guard behavior is configurable:

```php
// config/emissary.php
'rate_limit' => [
    'max_requests' => 60,
    'window_seconds' => 60,
],
'cost_cap' => [
    'max_cost_per_turn' => 0.10,
],
```

### See Also

- [Guard Authoring Guide →](/guides/guard-authoring) — Build custom guards
- [Configuration Reference →](/reference/config) — All guard config keys

    </div>
</details>
@endsection
