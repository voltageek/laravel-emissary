# Observability

> Typed events, listeners, export hook, replay to fixtures.

---

## Events & Observability

Every signal is a **typed PSR-14 event** carrying the `turn_id` of the inbound message that caused it. Listeners persist to SQL; the same events are the **export hook** — any app listener can forward them to Datadog, StatsD, or (future) OpenTelemetry without the library depending on a metrics backend. A full turn is reconstructed with `WHERE turn_id = ? ORDER BY created_at` across `agent_events` (+ `tool_invocations`).

### Typed events

```php
// Per LLM API call — intent classification, agent turns, errors.
class AgentCallCompleted {
    public function __construct(
        public string  $turnId,
        public string  $conversationId,
        public string  $model,
        public string  $callType,        // intent|agent|agent_error|direct_handler
        public int     $inputTokens,
        public int     $outputTokens,
        public int     $latencyMs,
        public ?string $intent = null,
        public ?array  $toolCalls = null,
        public ?string $errorCode = null,
        public ?string $error = null,
        public ?string $conversationMessageId = null,
    ) {}
}

// Per tool execution — every path (agent loop, confirmation fast-path, direct handler)
// and validation failures (handler skipped).
class ToolInvocationCompleted {
    public function __construct(
        public string  $turnId,
        public string  $conversationId,
        public string  $toolName,
        public array   $arguments,
        public ?string $resultSummary = null,
        public ?int    $durationMs = null,
        public bool    $success = true,
        public ?string $validationError = null,   // set when args failed schema validation
        public string  $triggeredVia = 'agent_loop', // agent_loop|confirmation_fastpath|direct_handler
        public ?string $agentEventId = null,      // the llm_call that requested it
    ) {}
}

// Per guard checkpoint evaluation.
class GuardDecision {
    public function __construct(
        public string  $turnId,
        public string  $conversationId,
        public string  $checkpoint,    // beforeIntent|beforeExecution|beforeTool
        public string  $guard,
        public bool    $allowed,
        public ?string $toolName = null,
        public ?string $errorCode = null,
        public ?string $userMessage = null,
    ) {}
}

// Confirmation gate lifecycle.
class ConfirmationGateTransitioned {
    public function __construct(
        public string  $turnId,
        public string  $conversationId,
        public string  $transition,    // propose|execute|cancel|expire
        public ?string $toolName = null,
        public ?array  $fields = null,
    ) {}
}

// Per turn — the rollup, and the natural unit for reporting/export.
class TurnCompleted {
    public function __construct(
        public string  $turnId,
        public string  $conversationId,
        public string  $outcome,       // success|guard_denied|error|confirmation_proposed|low_confidence
        public ?string $intent = null,
        public ?array  $models = null,
        public int     $totalLatencyMs = 0,
        public int     $totalInputTokens = 0,
        public int     $totalOutputTokens = 0,
        public int     $toolCount = 0,
        public ?string $errorCode = null,
    ) {}
}

// Onboarding lifecycle (only when onboarding.enabled).
class UserOnboardingTransitioned {
    public function __construct(
        public string  $turnId,
        public string  $conversationId,
        public ?string $userId,
        public string  $transition,    // started|profile_updated|consented|completed|skipped|guest_created
        public ?array  $profile = null,
        public ?string $consentVersion = null,
    ) {}
}
```

### Listeners

| Listener | Default | Handles | Writes |
|---|---|---|---|
| `LogAgentEvent` | Yes | `AgentCallCompleted`, `GuardDecision`, `ConfirmationGateTransitioned`, `TurnCompleted`, `UserOnboardingTransitioned` | `agent_events` (kind-discriminated) |
| `LogToolInvocation` | Yes | `ToolInvocationCompleted` | `tool_invocations` |
| `UpdateCostLedger` | No | `AgentCallCompleted` | `cost_ledgers` |
| `CaptureLlmPayload` | No | `AgentCallCompleted` (full request/response) | `llm_payloads` (replay) |
| `LogTraceSpan` | No | per-stage span recording | `agent_spans` |

### Export hook
Because every signal is a PSR-14 event, an external-metrics integration is just a listener — no library coupling. Register a listener that forwards `TurnCompleted` / `ToolInvocationCompleted` / `GuardDecision` to your backend (StatsD, Datadog, Logflare…). An OpenTelemetry exporter (spans with `turn_id` as trace parent) is the intended **future** first-party listener; the typed events are the stable seam, so it requires no pipeline changes.

### Replay → regression fixtures
The same capture that powers `agent:replay` (`llm_payloads`) doubles as a test-fixture source. `agent:fixture:capture <turn_id>` freezes a captured turn into a JSON fixture that `AgentTestCase::replay()` re-runs deterministically as a Pest dataset — see Testing. Production incidents thus become permanent regression tests with no extra instrumentation.
