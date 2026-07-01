---
extends: _layouts.master
title: Guard Authoring
description: Build custom guards — authentication rules, authorization logic, rate limiting extensions.
---

@section('body')
<div class="tldr-box">
    <h4>TL;DR</h4>
<pre><code class="language-php">use Emissary\Contracts\AgentGuard;
use Emissary\GuardResult;

class BusinessHoursGuard implements AgentGuard
{
    public function checkpoint(): string
    {
        return 'beforeExecution';
    }

    public function evaluate(InboundMessage $message, ?IntentResult $intent): GuardResult
    {
        $hour = now()->hour;

        return ($hour >= 9 && $hour &lt; 17)
            ? GuardResult::allow()
            : GuardResult::deny('We are currently closed. Business hours: 9 AM–5 PM.');
    }
}</code></pre>
</div>

## Quick Start

A guard implements `Emissary\Contracts\AgentGuard` with two methods: `checkpoint()` and `evaluate()`.

### Checkpoints

| Constant | Value | Fires |
|---|---|---|
| `AgentGuard::BEFORE_INTENT` | `beforeIntent` | After message reception, before intent classification |
| `AgentGuard::BEFORE_EXECUTION` | `beforeExecution` | After intent classified, before tool execution |
| `AgentGuard::AFTER_EXECUTION` | `afterExecution` | After agent responds, before delivery |

### GuardResult

```php
GuardResult::allow();                    // Proceed
GuardResult::deny('Reason message');     // Block (shown to user)
GuardResult::pending('Confirmation?');   // Require user confirmation
```

<details class="deep-dive">
    <summary>Deep Dive</summary>
    <div class="deep-dive-content">

### Built-in Guards

Emissary ships with these guards. All default **on**.

| Guard | Checkpoint | Config |
|---|---|---|
| `RateLimitGuard` | `beforeIntent` | `rate_limit.max_requests`, `rate_limit.window_seconds` |
| `CostCapGuard` | `beforeExecution` | `cost_cap.max_cost_per_turn` |
| `JailbreakDetectionGuard` | `beforeIntent` | `security.jailbreak_detection` |
| `MaxTurnsGuard` | `beforeExecution` | `max_turns` |
| `AuthenticatedUserGuard` | `beforeIntent` | — |
| `OnboardingGuard` | `beforeIntent` | `onboarding` |

### Registration

Register in your plugin:

```php
public function getGuards(): array
{
    return [new BusinessHoursGuard];
}
```

Or register globally in a service provider:

```php
$this->app->tag([BusinessHoursGuard::class], 'emissary.guards');
```

### Evaluation Order

Guards evaluate in registration order. First `GuardResult::deny()` short-circuits — no further guards run, and the deny message is returned to the user.

### Observability

Every guard evaluation emits a `GuardDecision` event:
- **Deny**: Always emitted
- **Allow**: Only when `observability.trace_guard_allows` is enabled (off by default)

    </div>
</details>
@endsection
