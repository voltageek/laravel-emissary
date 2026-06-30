<!--
Sync Impact Report
==================
Version change: 1.0.0 → 1.0.1
Rationale: PATCH — clarification on P4 (replay capture opt-in default) per MECE review.
Modified principles:
  - Principle 4: Observability by Design — added explicit statement that heavy capture
    (llm_payloads, agent_spans) is opt-in and defaults off (aligns with Decision #14).
Added sections: N/A
Removed sections: N/A
Templates requiring updates: None (P4 clarification is self-contained)
Follow-up TODOs: None
-->

# Emissary — Project Constitution

> Version: 1.0.1 · Ratified: 2026-06-30 · Last Amended: 2026-06-30

## Preamble

**Emissary** is a standalone, reusable PHP library (namespace `Emissary\`, Composer package
`voltageek/laravel-emissary`) that adds agentic, multi-channel conversational capability to
existing Laravel applications. Host-app developers integrate by defining three things — **intents,
tools, guards** — and the library handles routing, LLM calls, tool execution, memory, confirmation
gates, and channel delivery across WhatsApp, Telegram, and Web.

The spec lives in `specs/` and is the canonical source of truth. The intended implementer is an AI
coding agent guided by `AGENTS.md`.

## Core Principles

### Principle 1 — Spec-Driven Implementation

The specification in `specs/` is the single source of truth for all class names, method signatures,
return types, constant values, and behavioural rules. Every interface, DTO, and event defined in
`specs/02-contracts.md` and `specs/03-pipeline.md` MUST be implemented verbatim. Names MUST NOT be
"improved." When a behaviour is unspecified, the implementer MUST flag the ambiguity and ask rather
than invent.

**Rationale**: The spec is reviewed and signed off separately from the code. Divergence between
spec and implementation creates untraceable bugs and erodes trust in the spec as a contract.

### Principle 2 — Security by Default

Six attack surfaces are defended with concrete controls wired into the pipeline:
1. Webhook spoofing → `ChannelAdapter::verify()` hard-fails with HTTP 401
2. Direct prompt injection → `JailbreakDetectionGuard` + system-role prompt
3. Indirect prompt injection → tool-result data envelope wrapping
4. Tool-argument injection → schema validation before handler execution
5. Channel identity & authorization → `ChannelIdentityResolver` + guards
6. Resource & cost exhaustion → `RateLimitGuard`, `CostCapGuard`, `MaxTurnsGuard`

All controls MUST be on by default. Disabling any control MUST be explicit and per-control in
`config/emissary.php`. Channel credentials are secrets and MUST be encrypted at rest; conversation
content is not. These defaults MUST NOT be inverted.

**Rationale**: A library that ships with defences off transfers the security burden to every
integrator, most of whom will not discover the controls. Default-on with explicit opt-out ensures
the safe path is the easy path.

### Principle 3 — Testability First

The non-deterministic LLM and external channel APIs are isolated behind test doubles in the
`Emissary\Testing\` namespace: `FakeLlmClient` (scripted agent loops), `FakeChannelAdapter`
(in-process webhook→reply), and a `Clock` fake for deterministic time. No test MUST ever hit a
live LLM or channel API. The test toolkit MUST be importable by host apps (mirroring
`Illuminate\Testing`). `AgentTestCase` provides Pest assertions over tools, guards, events, and
turn outcomes.

**Rationale**: An agent pipeline whose core loop is non-deterministic is untestable without
deterministic fakes. Shipping the fakes as part of the public API allows host-app developers to
test their own plugins (intents, tools, guards) with the same infrastructure.

### Principle 4 — Observability by Design

Every signal — LLM calls, tool executions, guard decisions, confirmation-gate transitions, and turn
completion — MUST be emitted as a typed PSR-14 event carrying a `turn_id` that propagates from the
inbound message through the entire turn. Events serve dual purpose: listeners persist to SQL for
internal reporting/replay, and the same events are the stable export hook for external metrics
backends. The full observable surface includes `AgentCallCompleted`, `ToolInvocationCompleted`,
`GuardDecision`, `ConfirmationGateTransitioned`, `TurnCompleted`, and `UserOnboardingTransitioned`.

Heavy capture (full `llm_payloads` and per-stage `agent_spans`) enables exact replay debugging
but is storage-heavy and may contain user PII. It MUST default off and be TTL-bounded — opt-in
only via `observability.capture_llm_payloads` and `observability.capture_agent_spans` config toggles.

**Rationale**: Without turn-scoped observability, debugging multi-step agent behaviour requires
reconstructing state from disparate logs. Typed events with `turn_id` propagation make the agent's
behaviour queryable as a single unit (`WHERE turn_id = ? ORDER BY created_at`).

### Principle 5 — Single Tool Surface

All domain operations (reads and writes) MUST be declared as tools via the `#[Tool]` attribute on
provider methods. The `ToolScanner` derives each tool's OpenAI JSON schema from PHP parameter types
plus the attribute's `params` array and binds the method as the handler — the definition and its
handler are the same object and CANNOT drift apart. `getToolDefinitions()` exists only as an
escape hatch for schemas too complex for attribute syntax (nested objects, `anyOf`, `$ref`) and
takes precedence on a name clash. There MUST NOT be a parallel handler map.

**Rationale**: Prior art split tools into separate handler buckets (queries, transactions,
commands), forcing developers to choose the right bucket and maintain parallel registrations. A
single surface with the attribute as the declarative source eliminates ambiguity and keeps the
handler next to its definition.

### Principle 6 — Guards as First-Class Primitives

`AgentGuard` is a peer to intents and tools in the plugin SPI. Three fixed checkpoints —
`beforeIntent`, `beforeExecution`, `beforeTool` — give guard authors a clear place to attach rules
without racing against each other. Guards are evaluated in registration order; the first
`GuardResult::deny()` result MUST short-circuit evaluation, and the denied message MUST be returned
to the user immediately. Every guard evaluation MUST emit a `GuardDecision` event (deny always;
allow only when `observability.trace_guard_allows` is enabled).

**Rationale**: In prior art, authorization was ad-hoc (scattered across handlers and pipeline
stages) and unobservable. Fixed checkpoints with ordered evaluation and typed events make the
authorization surface predictable, testable, and auditable.

### Principle 7 — Config-Driven Behavior

Model escalation, rate limits, onboarding mode, consent requirements, tool-argument validation,
and error messages MUST be driven by `config/emissary.php`. The LLM MUST NOT determine its own
model tier — escalation is determined solely by the `complex_intents` list and
`confidence_escalation_threshold` in config. Behavior that varies by deployment (single-tenant vs.
multi-tenant, web-centric vs. channel-first, cost tracking on/off) MUST be a config toggle, not a
code branch conditioned on the presence or absence of a binding.

**Rationale**: Config-driven behavior lets host-app operators tune the agent's cost, safety, and
UX without touching library code. Removing the LLM as a dual source for model selection eliminates
non-deterministic cost behaviour.

### Principle 8 — Channel Agnosticism

Tool handlers MUST return plain data (strings, DTOs, arrays) and MUST NOT know or care which
channel the user is on. `ChannelAdapter::formatResponse()` translates an `AgentResponse` into a
channel-native `OutboundMessage`. Channel credentials MUST be resolved through the
`ChannelCredentialStore` seam rather than read directly from config. Each `ChannelAdapter`
implementation MUST perform per-request webhook verification (`verify()`) and hard-fail on invalid
requests before entering the pipeline.

**Rationale**: Tool handlers that emit channel-specific markup (WhatsApp buttons, Telegram inline
keyboards) couple domain logic to a delivery channel, making new channels an exercise in rewriting
every handler. The adapter seam keeps channel concerns in one place and makes adding channels a
new adapter, not a rewrite.

### Principle 9 — Explicit Error Taxonomy

Every failure mode MUST have a unique error code (an `AgentError` constant) and a configurable
user-facing message in `config/emissary.php`. Guards, tool handlers, and pipeline stages MUST use
these codes consistently rather than inventing ad-hoc messages. The error taxonomy covers
guard/auth failures, intent classification issues, tool execution errors, LLM errors, security
blocks, cost limits, onboarding gates, and conversation limits.

**Rationale**: Ad-hoc error strings make it impossible for host-app developers to programmatically
handle failures (e.g., show a specific UI, trigger a notification, log to a particular channel). A
taxonomy of codes with configurable messages separates the machine-readable signal from the
human-readable text.

### Principle 10 — PHP 8.3+ Conventions

All library code MUST target PHP 8.3+ and use readonly classes, named arguments, match
expressions, and constructor property promotion. The namespace MUST be `Emissary\`
(`Emissary\Contracts\`, `Emissary\Testing\`, etc.). Tests MUST be Pest-first in the
`Emissary\Testing\` namespace. Docblocks on public interfaces are allowed and expected — they
appear in the spec — but inline comments in implementation code MUST NOT be added unless
explicitly requested.

**Rationale**: Consistent language-level conventions reduce cognitive overhead for contributors and
ensure the codebase reads uniformly. The namespace convention (`Emissary\Testing\` mirroring
`Illuminate\Testing`) signals to Laravel developers that the test toolkit is a first-class feature.

## Governance

### Amendment Procedure

1. Any principle change, addition, or removal MUST be proposed as a diff against this document.
2. The proposal MUST include a rationale and an assessment of impact on dependent templates
   (`plan-template.md`, `spec-template.md`, `tasks-template.md`).
3. The change is ratified when the constitution is updated with an incremented version and a
   Sync Impact Report documenting the delta.
4. Breaking changes to governance or principle removals/redefinitions require a MAJOR version bump.

### Versioning Policy

- **MAJOR**: Backward-incompatible governance/principle removals or redefinitions.
- **MINOR**: New principle added or materially expanded guidance.
- **PATCH**: Clarifications, wording fixes, typo corrections, non-semantic refinements.

### Compliance Review

Every component implementation MUST be verified against the principles before being marked done:

1. **Principle 1**: Interface/behaviour matches `specs/02-contracts.md` and `specs/03-pipeline.md`
   verbatim.
2. **Principle 2**: Security controls are wired and default-on; `ChannelAdapter::verify()` hard-fails
   on invalid requests.
3. **Principle 3**: Tests use `FakeLlmClient`, `FakeChannelAdapter`, and `Clock` fakes; never a
   live LLM or channel.
4. **Principle 4**: Typed events are emitted with `turn_id` propagation at every observable point.
5. **Principle 5**: Tools are declared with `#[Tool]` attribute; no parallel handler map exists.
6. **Principle 6**: Guards fire at three checkpoints; first deny short-circuits.
7. **Principle 7**: Model escalation, rate limits, onboarding mode, and error messages are
   config-driven.
8. **Principle 8**: Tool handlers return plain data; `ChannelAdapter::formatResponse()` handles
   channel formatting.
9. **Principle 9**: Failures use `AgentError` codes with configurable messages.
10. **Principle 10**: PHP 8.3+ conventions; Pest-first tests in `Emissary\Testing\` namespace.

### Ratification

This constitution was ratified on 2026-06-30 as version 1.0.0, establishing the foundational
governance for the Emissary project. It is binding on all contributions, implementations, and
spec amendments from this date forward.
