# Pipeline

> Component behaviour + the end-to-end data flow.

---

## Pipeline Components

### IntentRouter

Classifies user messages into intents using an LLM.

```php
class IntentRouter
{
    private array $intents = [
        'smalltalk_or_other', 'confirm_action', 'cancel_action',
        'start_onboarding',   // built-in: first-contact onboarding flow (see User Onboarding)
        'verify_identity',    // built-in: chat→user linking (see ChannelIdentityResolver)
        // ... domain-specific base intents
    ];

    private array $classificationHints = [];

    public function registerIntents(array $intents): void;
    public function registerClassificationHints(array $hints): void;
    public function classify(string $userMessage): IntentResult;
}
```

**Classification flow:**
1. Build prompt: intent list + classification hints (from providers)
2. POST to LLM: `{model, messages, max_tokens: 100, temperature: 0.1}`
3. Parse JSON: `{slug, confidence}`
4. Validate slug against registered list (fallback: `unknown`, confidence: 0.0)
5. If `confidence < intent_confidence_threshold` → return `IntentResult('unknown', confidence)`
6. Emit `AgentCallCompleted` event (callType: `intent`)

**Low-confidence fallback:** When the returned slug is `unknown` or confidence is below `intent_confidence_threshold`, the pipeline skips agent execution and returns the configured `error_messages.intent.low_confidence` response directly to the user.

**Acceptance criteria (EARS):**
- **WHEN** the classifier returns `confidence < intent_confidence_threshold` **OR** `slug == 'unknown'` **THE SYSTEM SHALL** skip agent execution, set `TurnCompleted.outcome = low_confidence`, and reply with `error_messages.intent.low_confidence`.
- **WHEN** the classifier returns a slug not in the registered intent list **THE SYSTEM SHALL** coerce the result to `IntentResult('unknown', 0.0)`.
- **WHEN** the classifier LLM call fails **THE SYSTEM SHALL** emit an `AgentCallCompleted` with `errorCode = AgentError::LLM_ERROR` and terminate the turn with that error.

---

### ModelSelector

Routes to the cheapest capable model. Single source of truth for escalation — escalation is determined by config only, not by LLM output.

```php
class ModelSelector
{
    public function select(IntentResult $intent, bool $hasMedia = false): string;
}
```

**Priority (evaluated in order):**
1. Media present → `vision_model`
2. Intent slug in `complex_intents` config → `complex_model`
3. `intent.confidence < confidence_escalation_threshold` config → `complex_model`
4. Otherwise → `default_model`

---

### GuardRegistry

Aggregates guards from all registered providers and evaluates them at each checkpoint.

```php
class GuardRegistry
{
    public function register(AgentGuard $guard): void;

    // Returns first denial or GuardResult::allow() if all pass
    public function checkBeforeIntent(InboundMessage $message, ?Authenticatable $user, mixed $tenant): GuardResult;
    public function checkBeforeExecution(string $intent, ?Authenticatable $user, mixed $tenant): GuardResult;
    public function checkBeforeTool(string $toolName, array $arguments, ?Authenticatable $user, mixed $tenant): GuardResult;
}
```

**Built-in guards (registered by default, configurable):**

| Guard | Checkpoint | Behaviour |
|---|---|---|
| `RateLimitGuard` | beforeIntent | Blocks if message count > `rate_limit.per_minute` in sliding window |
| `JailbreakDetectionGuard` | beforeIntent | Blocks messages flagged as prompt-injection attempts (heuristic + optional model classifier). On by default; disable via `security.jailbreak.enabled`. Emits `AgentError::SECURITY_JAILBREAK` |
| `CostCapGuard` | beforeIntent | Blocks if the conversation's accumulated cost ≥ `cost_alerts.per_conversation_max_usd`. No-op when cost tracking is disabled. Emits `AgentError::COST_LIMIT_EXCEEDED` |
| `AuthenticatedUserGuard` | beforeExecution | Denies if `$user` is null and intent is in `require_auth_intents` config |
| `MaxTurnsGuard` | beforeIntent | Blocks if conversation turn count > `max_conversation_turns` |
| `OnboardingGuard` | beforeExecution | Denies if `Conversation.onboarding_state != complete` (or `skipped`) and the intent is in `onboarding.gated_intents` (default `['*']`), steering the user to finish onboarding. No-op when `onboarding.enabled` is false |

**Observability:** every checkpoint evaluation emits a `GuardDecision` event (checkpoint, guard name, `allow`/`deny`, `errorCode`, `turn_id`). Denials are always emitted; allows are emitted only when `observability.trace_guard_allows` is on. This closes the "guard acted but nothing recorded" gap — you can answer "how many requests did each guard block?" from the event log.

---

### TaskAgent

Runs the AI agent loop with tool calling.

```php
class TaskAgent
{
    public function run(
        Conversation $conversation,
        string $model,
        string $userMessage,
        ?string $mediaUrl = null,
        array $toolNames = [],
        ?Authenticatable $user = null,
    ): AgentResponse;
}
```

**Loop (max `max_tool_call_rounds` iterations):**
1. Load conversation memory (session-grouped, token-budgeted)
2. Build system prompt (tenant context + plugin extensions) — always in the `system` role, never concatenated into user turns
3. POST to LLM with tools
4. If response has `tool_calls`:
   - For each tool: evaluate `GuardRegistry::checkBeforeTool()` → if denied, return `AgentResponse::fromError()`
   - Execute via `ToolRegistry::execute()` (validates args against schema first) → on validation failure, append a `tool_result` error and continue (model self-corrects)
   - Append the result via `ConversationMemory::appendToolResult()` (wrapped in a data envelope) → continue loop
5. If response is text → emit event → return `AgentResponse`
6. On HTTP error → emit `agent_error` event → return `AgentResponse::fromError(AgentError::LLM_ERROR)`
7. On loop exhaustion → return `AgentResponse::fromError(AgentError::TOOL_MAX_ROUNDS)`

---

### ToolScanner

Derives tool definitions and handler bindings from `#[Tool]`-annotated methods on a provider. Invoked once per provider at boot via `ToolRegistry::registerProvider()`.

```php
class ToolScanner
{
    /**
     * @return array<string, array{definition: array, handler: callable}>
     */
    public function scan(AgentToolProvider $provider): array;
}
```

**Scan flow, per provider:**

1. `new ReflectionClass($provider)` — iterate all public, non-static methods.
2. For each method, read `$refl->getAttributes(Tool::class)`. Skip methods without one.
3. Hydrate the attribute via `->newInstance()` → `Tool` instance.
4. For each `ReflectionParameter`:
   - Read `getType()` (`ReflectionNamedType`) → map PHP type → JSON schema type (see table above).
   - `isOptional()` / nullable → exclude from `required[]`.
   - Merge meta from `params[$paramName]` (`description`, `enum`, type override).
   - **Validate** every `params` key has a matching parameter — throw on mismatch.
5. Build the OpenAI function schema: `name` (method name), `description`, `parameters` (properties + `required`), `requires_confirmation`, `confirmation_template`.
6. Bind handler: the `[$provider, $methodName]` callable.
7. Merge with the provider's `getToolDefinitions()` escape-hatch array — **escape-hatch wins** on a name clash (a warning is logged); reflected entries are additive.

The provider itself is resolved from the service container, so constructor dependency injection works normally. The scanner never calls the methods — it only reads their shape. Results are cached by the `ToolRegistry` singleton, so the scan runs at most once per process (continuously warm under Octane).

---

### ToolRegistry

Manages tool definitions and execution. All domain operations — reads and writes — are registered here. Definitions and handlers are populated by the `ToolScanner` from `#[Tool]` methods (plus any `getToolDefinitions()` escape-hatch entries); `registerProvider()` triggers a single scan per provider. Write tools that require confirmation set `requires_confirmation: true` in their definition.

```php
class ToolRegistry
{
    public function register(string $name, callable $handler): void;
    public function registerProvider(AgentToolProvider $provider): void;
    public function resolveToolsForIntent(string $intent, mixed $tenant): array;
    public function execute(string $name, array $arguments): mixed;
    public function getToolDefinitions(array $toolNames): array;
    public function getAllDefinitions(): array;
    public function getMergedIntentConfig(): array;
    public function requiresConfirmation(string $toolName): bool;
    public function getConfirmationTemplate(string $toolName): ?string;
}
```

**Definition merging:** Base definitions are deep-merged with provider definitions. Parameter `properties` and `required` arrays are unioned on tool name collision. On a clash between a reflected `#[Tool]` method and a `getToolDefinitions()` entry, the escape-hatch entry wins (a warning is logged).

**Argument validation:** `execute()` validates `$arguments` against the tool's registered JSON schema **before** invoking the handler — unknown properties are rejected, required fields are checked, and `enum`/type constraints are enforced. The schema therefore does double duty: it tells the LLM what to send and verifies what the LLM actually sent. On failure the handler is **not** called; a structured error tagged `AgentError::TOOL_INVALID_ARGUMENTS` is returned to `TaskAgent`, which feeds it back to the LLM as a `tool_result` so the model can self-correct within the remaining rounds. This is the primary defence against tool-argument injection.

**Observability:** `execute()` emits a `ToolInvocationCompleted` event on **every** invocation — the agent loop, the confirmation fast-path, and direct handlers — recording `tool_name`, `arguments`, `result_summary`, `duration_ms`, `success`, `validation_error` (set when args failed validation and the handler was skipped), `triggered_via`, and `turn_id`. This is the single source for tool-level metrics (call count, p95 latency, failure rate, how often the LLM sends bad args) and ensures the actual service call — including the Turn-2 confirmation execution — is always metered.

**Acceptance criteria (EARS) — argument validation:**
- **WHEN** `execute()` receives `$arguments` that fail the tool's registered JSON schema (unknown property, missing required, bad type, or out-of-`enum` value) **THE SYSTEM SHALL NOT** invoke the handler; it SHALL return a structured error tagged `AgentError::TOOL_INVALID_ARGUMENTS`, emit a `ToolInvocationCompleted(success: false, validation_error: <detail>)`, and feed the error back to the LLM as a `tool_result` for self-correction.
- **WHEN** the handler throws **THE SYSTEM SHALL** emit `ToolInvocationCompleted(success: false)` and surface the failure as `AgentError::TOOL_EXECUTION_FAILED`.

**Confirmation flow:** When `TaskAgent` receives a tool call for a tool where `requiresConfirmation()` returns true, it does not execute the tool. Instead it calls `ConfirmationGate::propose()` with the action and returns an `AgentResponse` with `confirmationRequired: true`. Execution resumes on the next message if the user confirms.

---

### ConversationMemory

Manages conversation context with session-based grouping and token budgeting.

```php
class ConversationMemory
{
    public function load(Conversation $conversation): array;
    public function appendToolResult(array &$messages, string $content): void;
}
```

**Tool-result wrapping (indirect prompt injection defence):** `appendToolResult()` never feeds raw tool output straight into the message history. Results are wrapped in a delimited data envelope and prefixed with an instruction that the model must treat the content as untrusted data, never as instructions:

```
[TOOL_RESULT_BEGIN]
<tool output>
[TOOL_RESULT_END]
```

This mitigates indirect prompt injection, where attacker-controlled text inside your own data (a product description, a customer note) would otherwise be acted on by the model. Wrapping is not foolproof — sensitive deployments should additionally filter tool output through a sanitiser before wrapping.

**Load flow:**
1. Fetch recent messages (2 × `max_conversation_turns`)
2. Group into sessions by `activity_gap_minutes` threshold
3. Current session → full messages
4. Completed sessions → dispatch summarization job
5. Conversation summary → system message (capped at 30% of token budget)
6. Enforce `token_budget` (chars/4 as token estimate)

---

## Complete Data Flow

```
INBOUND: WhatsApp / Telegram / Web webhook
  │
  ├─► ChannelAdapter::verify()   ── false? → HTTP 401, END (never parsed)
  ├─► ChannelAdapter::parse()  → InboundMessage
  │
  ▼
MessageBridge::receive(InboundMessage)
  │
  ├─► ChannelIdentityResolver::resolveUser() → ?Authenticatable (session user for Web)
  ├─► TenancyResolver::resolve()   → tenant (or null)
  ├─► TenancyResolver::activate()
  │
  ├─► Conversation::firstOrCreate(channel, channel_ref)
  ├─► Generate turn_id (UUID)   ── stamps the user message; carried by the job
  ├─► Store ConversationMessage(role=user, turn_id)
  │
  ├─► GuardRegistry::checkBeforeIntent(user)   ── emits GuardDecision (deny always)
  │     ├─ RateLimitGuard / JailbreakDetectionGuard / CostCapGuard / MaxTurnsGuard
  │     └─ denied? → reply(guardResult.userMessage) → END
  │
  └─► ProcessMessage::dispatch(conversationId)
        │
        ▼
      ProcessMessage::handle()
        │
        ├─► Confirmation fast-path?
        │     ├─ "yes/confirm" → gate.execute() (emits gate transition)
        │     │        → ToolRegistry::execute() (emits ToolInvocationCompleted, triggeredVia=confirmation_fastpath)
        │     │        → reply
        │     └─ "no/cancel"   → gate.cancel() (emits gate transition) → reply
        │
        ├─► IntentRouter::classify(text) → IntentResult
        │     └─ LLM call #1 (emits 'intent' event)
        │
        ├─► confidence < threshold OR slug == 'unknown'?
        │     └─ reply(error_messages.intent.low_confidence) → END
        │
        ├─► GuardRegistry::checkBeforeExecution(intent, user, tenant)
        │     └─ denied? → reply(guardResult.userMessage) → END
        │
        ├─► Direct handler? (export/download intents)
        │     └─ execute → format → reply (emits 'direct_handler' event)
        │
        ├─► ModelSelector::select(intent) → model
        ├─► ToolRegistry::resolveToolsForIntent(slug, tenant) → tools
        │
        ├─► TaskAgent::run(conversation, model, text, tools, user)
        │     ├─ ConversationMemory::load()
        │     ├─ System prompt: tenant context + plugin extensions
        │     ├─ LLM call #2+ (emits 'agent' event)
         │     └─ Tool loop:
         │           ├─ GuardRegistry::checkBeforeTool(name, args, user, tenant) ── emits GuardDecision
         │           │     └─ denied? → AgentResponse::fromError()
         │           ├─ ToolRegistry::requiresConfirmation(name)?
         │           │     └─ yes → ConfirmationGate::propose() (emits gate transition) → AgentResponse(confirmationRequired: true)
         │           └─ ToolRegistry::execute(name, args) ── emits ToolInvocationCompleted (triggeredVia=agent_loop)
         │                 ├─ validate args against JSON schema → fail → tool_result error (self-correct)
         │                 └─ append via ConversationMemory (data-envelope wrap) → re-query LLM
        │
        └─► MessageBridge::reply(conversation, response)
              ├─► ChannelAdapter::formatResponse(response) → OutboundMessage
              ├─► ChannelAdapter::send(channel_ref, outboundMessage)
              └─► Store ConversationMessage(role=assistant)

END OF TURN:
  ProcessMessage emits TurnCompleted (outcome, totals, turn_id) → LogAgentEvent → agent_events (kind=turn)

SIDE EFFECTS (event-driven, all carry turn_id):
  AgentCallCompleted           → LogAgentEvent     → agent_events (kind=llm_call)
  GuardDecision                → LogAgentEvent     → agent_events (kind=guard)
  ConfirmationGateTransitioned → LogAgentEvent     → agent_events (kind=gate)
  ToolInvocationCompleted      → LogToolInvocation → tool_invocations
  AgentCallCompleted           → UpdateCostLedger  → cost_ledgers  [optional listener]
  AgentCallCompleted           → CaptureLlmPayload → llm_payloads [optional, replay]
  *(span recording)            → LogTraceSpan      → agent_spans  [optional, per-stage]
  Session overflow             → SummarizeConversation job → updates conversation.summary
```
