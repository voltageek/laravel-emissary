# Emissary Full Implementation — Phased Plan

> Plan version: 1.0.0 · Target spec version: 2.6.0 · Date: 2026-06-30

## Summary

Implement the complete Emissary library (~95 distinct units) across 4 sequential phases. Each phase
produces a testable, runnable increment. Phase 1 establishes the contract and data layer. Phase 2
builds the pipeline core so full agent turns execute in-memory via fakes. Phase 3 wires channels so
WhatsApp/Telegram/Web messages flow end-to-end. Phase 4 adds onboarding flows, Artisan commands,
and replay-as-fixture. Phases 3 and 4 may partially overlap after Phase 2 completes.

Total estimated files: ~82 source files + ~10 migrations + 1 config + 1 service provider + 1 view
+ 4 test doubles.

## Constitution Check

- [x] **Principle 1 — Spec-Driven**: All names from `specs/02-contracts.md` and `specs/03-pipeline.md` used verbatim.
- [x] **Principle 2 — Security by Default**: Six attack-surface controls wired; webhook verifies fail closed; credentials encrypted at rest.
- [x] **Principle 3 — Testability First**: All tests use `FakeLlmClient`, `FakeChannelAdapter`, `Clock`; no live APIs.
- [x] **Principle 4 — Observability by Design**: All 6 typed events emitted with `turn_id`; heavy capture defaults off.
- [x] **Principle 5 — Single Tool Surface**: `#[Tool]` attribute on provider methods; no parallel handler map.
- [x] **Principle 6 — Guards as First-Class Primitives**: Three checkpoints; first-deny short-circuits.
- [x] **Principle 7 — Config-Driven Behavior**: Model escalation, rate limits, onboarding mode error messages all config-driven.
- [x] **Principle 8 — Channel Agnosticism**: Tool handlers return plain data; `ChannelAdapter::formatResponse()` handles channel formatting.
- [x] **Principle 9 — Explicit Error Taxonomy**: All failures use `AgentError` codes with configurable messages.
- [x] **Principle 10 — PHP 8.3+ Conventions**: Readonly classes, named arguments, match expressions; Pest-first in `Emissary\Testing\`.

---

## Phase 1 — Foundation

**Goal**: Every downstream component can reference contracts, DTOs, and data models without
circular dependencies. Test doubles exist so Phase 2 can be fully tested from day one.

**Milestone**: `composer test` passes on contract compilation, migration tests, and fake behavior tests.

### 1.1 — Contracts & DTOs

Create all interfaces, value objects, enums, and the error taxonomy. These are the shared
vocabulary every other component consumes.

| # | File | Spec Ref | Category |
|---|---|---|---|
| 1 | `src/Contracts/AgentToolProvider.php` | `02-contracts.md` | Interface |
| 2 | `src/Contracts/AgentGuard.php` | `02-contracts.md` | Interface |
| 3 | `src/Contracts/TenancyResolver.php` | `02-contracts.md` | Interface |
| 4 | `src/Contracts/ChannelIdentityResolver.php` | `02-contracts.md` | Interface |
| 5 | `src/Contracts/ChannelCredentialStore.php` | `02-contracts.md` | Interface |
| 6 | `src/Contracts/ChannelAdapter.php` | `02-contracts.md` | Interface |
| 7 | `src/Contracts/ConfirmationGate.php` | `02-contracts.md` | Interface |
| 8 | `src/AgentError.php` | `02-contracts.md` | Final class (15 constants) |
| 9 | `src/Channel.php` | `02-contracts.md` | Enum (WhatsApp, Telegram, Web) |
| 10 | `src/GuardResult.php` | `02-contracts.md` | Readonly DTO |
| 11 | `src/InboundMessage.php` | `02-contracts.md` | Readonly DTO |
| 12 | `src/OutboundMessage.php` | `02-contracts.md` | Readonly DTO |
| 13 | `src/IntentResult.php` | `02-contracts.md` | Readonly DTO |
| 14 | `src/AgentResponse.php` | `02-contracts.md` | Mutable DTO (fromContent, fromError, toOutbound) |
| 15 | `src/TransactionResult.php` | `02-contracts.md` | Mutable DTO (ok, fail) |
| 16 | `src/ChannelCredentials.php` | `02-contracts.md` | Readonly DTO |
| 17 | `src/Attributes/Tool.php` | `02-contracts.md` | Attribute class (#[\Attribute]) |

### 1.2 — Configuration

The config file defines every toggle, key, and default. The service provider skeleton binds
interfaces so Phase 2 components can be resolved from the container.

| # | File | Spec Ref | Category |
|---|---|---|---|
| 18 | `config/emissary.php` | `09-configuration.md` | Config (23 top-level keys) |
| 19 | `src/EmissaryServiceProvider.php` | `09-configuration.md` | Service provider (8 bindings + tag boot) |

### 1.3 — Data Models & Migrations

All 9 Eloquent models plus 10 migrations. The data layer must exist before pipeline components
can persist conversations, events, tool invocations, etc.

| # | File | Spec Ref | Category |
|---|---|---|---|
| 20 | `database/migrations/*_create_conversations_table.php` | `04-data-models.md` | Migration |
| 21 | `database/migrations/*_create_conversation_messages_table.php` | `04-data-models.md` | Migration |
| 22 | `database/migrations/*_create_agent_events_table.php` | `04-data-models.md` | Migration |
| 23 | `database/migrations/*_create_tool_invocations_table.php` | `04-data-models.md` | Migration |
| 24 | `database/migrations/*_create_channel_identity_links_table.php` | `04-data-models.md` | Migration |
| 25 | `database/migrations/*_create_llm_payloads_table.php` | `04-data-models.md` | Migration (optional) |
| 26 | `database/migrations/*_create_agent_spans_table.php` | `04-data-models.md` | Migration (optional) |
| 27 | `database/migrations/*_create_cost_ledgers_table.php` | `04-data-models.md` | Migration (optional) |
| 28 | `database/migrations/*_create_channel_configs_table.php` | `04-data-models.md` | Migration (optional) |
| 29 | `database/migrations/*_create_user_onboardings_table.php` | `04-data-models.md` | Migration (optional) |
| 30 | `database/migrations/*_add_onboarded_at_to_users_table.php` | `04-data-models.md` | Host users column |
| 31 | `src/Models/Conversation.php` | `04-data-models.md` | Eloquent model |
| 32 | `src/Models/ConversationMessage.php` | `04-data-models.md` | Eloquent model |
| 33 | `src/Models/AgentEvent.php` | `04-data-models.md` | Eloquent model |
| 34 | `src/Models/ToolInvocation.php` | `04-data-models.md` | Eloquent model |
| 35 | `src/Models/ChannelIdentityLink.php` | `04-data-models.md` | Eloquent model |
| 36 | `src/Models/LlmPayload.php` | `04-data-models.md` | Eloquent model (optional) |
| 37 | `src/Models/AgentSpan.php` | `04-data-models.md` | Eloquent model (optional) |
| 38 | `src/Models/CostLedger.php` | `04-data-models.md` | Eloquent model (optional) |
| 39 | `src/Models/ChannelConfig.php` | `04-data-models.md` | Eloquent model (optional) |
| 40 | `src/Models/UserOnboarding.php` | `04-data-models.md` | Eloquent model (optional) |

### 1.4 — Test Doubles

Test doubles are part of the public API (`Emissary\Testing\` namespace) and must exist before
Phase 2 so the pipeline can be tested without live dependencies.

| # | File | Spec Ref | Category |
|---|---|---|---|
| 41 | `src/Testing/FakeLlmClient.php` | `10-commands-testing.md` | Test double (scripted responses) |
| 42 | `src/Testing/FakeChannelAdapter.php` | `10-commands-testing.md` | Test double (in-process webhook) |
| 43 | `src/Testing/Clock.php` | `10-commands-testing.md` | Test double (deterministic time) |
| 44 | `src/Testing/ToolCall.php` | `10-commands-testing.md` | Test helper (tool call builder) |

### 1.5 — Null/Default Implementations

Simple default implementations that satisfy interfaces with minimal behavior. These let Phase 2
run with zero configuration.

| # | File | Spec Ref | Category |
|---|---|---|---|
| 45 | `src/NullTenancyResolver.php` | `02-contracts.md` | Default impl (always null) |
| 46 | `src/AuthChannelIdentityResolver.php` | `02-contracts.md` | Default impl (session for Web, null for chat) |

**Phase 1 tests**: Contract compilation, migration structure (rollback/rerun), `FakeLlmClient`
scripting (`onIntent`, `onAgent`, `thenText`), `FakeChannelAdapter` record/assert, `Clock` fake/advance.

**Phase 1 verification**:
```bash
composer test -- --filter="(FakeLlmClient|FakeChannelAdapter|Clock|AgentError|GuardResult|InboundMessage)"
```

---

## Phase 2 — Pipeline Core

**Goal**: The full agent loop — intent classification → guard checkpoints → tool-calling →
confirmation gates → memory — executes deterministically via `FakeLlmClient` and `AgentTestCase`.

**Milestone**: `AgentTestCase` scripts a complete turn: `send('order 3 widgets')` →
`assertIntentClassified('place_order')` → `assertToolCalled('placeOrder')` → `assertReply('Order placed.')`.

### 2.1 — Pipeline Components

Every component from `specs/03-pipeline.md`.

| # | File | Spec Ref | Category |
|---|---|---|---|
| 47 | `src/Pipeline/IntentRouter.php` | `03-pipeline.md` | Classification (registerIntents, registerHints, classify) |
| 48 | `src/Pipeline/ModelSelector.php` | `03-pipeline.md` | Model routing (vision > complex > default) |
| 49 | `src/Pipeline/GuardRegistry.php` | `03-pipeline.md` | Guard orchestration (3 checkpoints, short-circuit) |
| 50 | `src/Pipeline/ToolScanner.php` | `03-pipeline.md` | #[Tool] attribute reader + schema inference |
| 51 | `src/Pipeline/ToolRegistry.php` | `03-pipeline.md` | Tool registration, schema validation, execution |
| 52 | `src/Pipeline/TaskAgent.php` | `03-pipeline.md` | LLM + tool loop (max 5 rounds) |
| 53 | `src/Pipeline/ConversationMemory.php` | `03-pipeline.md` | Session-based memory, token budget, result wrapping |
| 54 | `src/Pipeline/MessageBridge.php` | `03-pipeline.md` | Tenancy → conversation mgmt → dispatch → reply |
| 55 | `src/Pipeline/ProcessMessage.php` | `03-pipeline.md` | Job that orchestrates the full pipeline |

### 2.2 — Confirmation Gate

| # | File | Spec Ref | Category |
|---|---|---|---|
| 56 | `src/Pipeline/DatabaseConfirmationGate.php` | `03-pipeline.md` | Gate impl (propose/execute/cancel/isExpired) |

### 2.3 — Observability Events (6)

Typed PSR-14 events with `turn_id` propagation.

| # | File | Spec Ref | Category |
|---|---|---|---|
| 57 | `src/Events/AgentCallCompleted.php` | `05-observability.md` | Event (LLM call) |
| 58 | `src/Events/ToolInvocationCompleted.php` | `05-observability.md` | Event (tool execution) |
| 59 | `src/Events/GuardDecision.php` | `05-observability.md` | Event (guard checkpoint) |
| 60 | `src/Events/ConfirmationGateTransitioned.php` | `05-observability.md` | Event (gate lifecycle) |
| 61 | `src/Events/TurnCompleted.php` | `05-observability.md` | Event (turn rollup) |
| 62 | `src/Events/UserOnboardingTransitioned.php` | `05-observability.md` | Event (onboarding lifecycle) |

### 2.4 — Observability Listeners (5)

| # | File | Spec Ref | Category |
|---|---|---|---|
| 63 | `src/Listeners/LogAgentEvent.php` | `05-observability.md` | Listener (writes agent_events) |
| 64 | `src/Listeners/LogToolInvocation.php` | `05-observability.md` | Listener (writes tool_invocations) |
| 65 | `src/Listeners/UpdateCostLedger.php` | `05-observability.md` | Listener (opt-in, writes cost_ledgers) |
| 66 | `src/Listeners/CaptureLlmPayload.php` | `05-observability.md` | Listener (opt-in, writes llm_payloads) |
| 67 | `src/Listeners/LogTraceSpan.php` | `05-observability.md` | Listener (opt-in, writes agent_spans) |

### 2.5 — Built-in Guards (6)

| # | File | Spec Ref | Category |
|---|---|---|---|
| 68 | `src/Guards/RateLimitGuard.php` | `06-security.md` | Guard (messages per minute) |
| 69 | `src/Guards/JailbreakDetectionGuard.php` | `06-security.md` | Guard (prompt injection) |
| 70 | `src/Guards/CostCapGuard.php` | `06-security.md` | Guard (cost limit) |
| 71 | `src/Guards/MaxTurnsGuard.php` | `06-security.md` | Guard (turn cap) |
| 72 | `src/Guards/AuthenticatedUserGuard.php` | `06-security.md` | Guard (auth check) |
| 73 | `src/Guards/OnboardingGuard.php` | `08-user-onboarding.md` | Guard (onboarding gate) |

### 2.6 — AgentTestCase

| # | File | Spec Ref | Category |
|---|---|---|---|
| 74 | `src/Testing/AgentTestCase.php` | `10-commands-testing.md` | Pest base class (9 assertions) |

**Phase 2 tests**: `IntentRouter` classification (low confidence fallback, unknown slug coercion, LLM error),
`GuardRegistry` short-circuit ordering, `ToolScanner` type inference (every PHP → JSON mapping),
`ToolRegistry` schema validation (rejects unknown props, validates enums/types), `TaskAgent` tool loop
(multi-round, max-round cap), `ConversationMemory` token budget + result wrapping, `DatabaseConfirmationGate`
(propose/confirm/cancel/expire flows), guard EARS acceptance criteria (all checkpoints, deny short-circuits),
event emission with `turn_id` propagation.

**Phase 2 verification**:
```bash
composer test -- --filter="(IntentRouter|GuardRegistry|ToolScanner|ToolRegistry|TaskAgent|ConversationMemory|ConfirmationGate|AgentTestCase)"
```

---

## Phase 3 — Channels

**Goal**: WhatsApp, Telegram, and Web messages flow through the pipeline end-to-end using
`FakeChannelAdapter`. The `ChannelCredentialStore` seam supports both config-backed and DB-backed
credential sourcing.

**Milestone**: `FakeChannelAdapter::whatsapp()` → `send('order 3 widgets')` → `assertReply('Order placed.')`.

### 3.1 — Channel Credential Infrastructure

| # | File | Spec Ref | Category |
|---|---|---|---|
| 75 | `src/Channels/ConfigChannelCredentialStore.php` | `07-channels.md` | Credential store (reads config) |
| 76 | `src/Channels/EncryptedChannelCredentialStore.php` | `07-channels.md` | Credential store (DB-backed, encrypted) |

### 3.2 — Channel Adapters (3)

| # | File | Spec Ref | Category |
|---|---|---|---|
| 77 | `src/Channels/WhatsAppAdapter.php` | `07-channels.md` | Adapter (HMAC verify, GET handshake, parse/send) |
| 78 | `src/Channels/TelegramAdapter.php` | `07-channels.md` | Adapter (secret header verify, parse/send) |
| 79 | `src/Channels/WebChatAdapter.php` | `07-channels.md` | Adapter (CSRF/session verify, parse/send) |

### 3.3 — Identity Resolvers

| # | File | Spec Ref | Category |
|---|---|---|---|
| 80 | `src/Identity/LinkedChannelIdentityResolver.php` | `08-user-onboarding.md` | Identity resolver (DB-linked) |

### 3.4 — Webhook Controller & Routes

| # | File | Spec Ref | Category |
|---|---|---|---|
| 81 | `src/Http/WebhookController.php` | `07-channels.md` | Controller (5 routes, GET handshake + POST verify/parse) |

### 3.5 — Web Widget

| # | File | Spec Ref | Category |
|---|---|---|---|
| 82 | `resources/views/widget.blade.php` | `07-channels.md` | Blade include |
| 83 | `public/vendor/emissary/*` (assets) | `07-channels.md` | Publishable JS/CSS |

### 3.6 — Channel Artisan Commands (partial)

| # | File | Spec Ref | Category |
|---|---|---|---|
| 84 | `src/Commands/EmissaryChannelsList.php` | `10-commands-testing.md` | Command |
| 85 | `src/Commands/EmissaryWebhookUrl.php` | `10-commands-testing.md` | Command |
| 86 | `src/Commands/EmissarySetTelegramWebhook.php` | `10-commands-testing.md` | Command |
| 87 | `src/Commands/EmissaryChannelTest.php` | `10-commands-testing.md` | Command |
| 88 | `src/Commands/EmissaryChannelAdd.php` | `10-commands-testing.md` | Command |

**Phase 3 tests**: `WhatsAppAdapter` verify (valid HMAC passes, invalid 401s), GET handshake (hub.challenge echo), parse (WhatsApp JSON → InboundMessage). `TelegramAdapter` verify (secret header), parse. `WebChatAdapter` verify (CSRF). `ConfigChannelCredentialStore` resolves from config. `EncryptedChannelCredentialStore` resolves from DB. `WebhookController` routing (GET handshake vs POST verify/parse). `FakeChannelAdapter` end-to-end `send()` → `assertReply()` through the pipeline.

**Phase 3 verification**:
```bash
composer test -- --filter="(WhatsAppAdapter|TelegramAdapter|WebChatAdapter|ChannelCredentialStore|WebhookController)"
```

---

## Phase 4 — Onboarding + Operations

**Goal**: User onboarding flows, all remaining Artisan commands, replay-as-fixture, full system
integration.

**Milestone**: `emissary:replay <turn_id> --re-run` works. `emissary:fixture:capture` produces
valid JSON fixtures. `composer test` is green for the full suite.

### 4.1 — Onboarding Identity Resolver

| # | File | Spec Ref | Category |
|---|---|---|---|
| 89 | `src/Identity/GuestCreatingChannelIdentityResolver.php` | `08-user-onboarding.md` | Identity resolver (guest creation) |

### 4.2 — Built-in Onboarding Intents & Tools

These are `AgentToolProvider` implementations — the same plugin SPI host apps use, but bundled.

| # | File | Spec Ref | Category |
|---|---|---|---|
| 90 | `src/Onboarding/OnboardingToolProvider.php` | `08-user-onboarding.md` | Plugin (start_onboarding intent, update_profile tool, accept_consent tool, verify_identity intent) |

### 4.3 — Remaining Artisan Commands

| # | File | Spec Ref | Category |
|---|---|---|---|
| 91 | `src/Commands/EmissaryReport.php` | `10-commands-testing.md` | Command (summary report) |
| 92 | `src/Commands/EmissaryReplay.php` | `10-commands-testing.md` | Command (trace replay) |
| 93 | `src/Commands/EmissaryPrune.php` | `10-commands-testing.md` | Command (TTL pruning) |
| 94 | `src/Commands/EmissaryOnboardingStatus.php` | `10-commands-testing.md` | Command |
| 95 | `src/Commands/EmissaryOnboardingReset.php` | `10-commands-testing.md` | Command |
| 96 | `src/Commands/EmissaryFixtureCapture.php` | `10-commands-testing.md` | Command (replay-as-fixture) |

### 4.4 — Replay-as-Fixture

| # | File | Spec Ref | Category |
|---|---|---|---|
| 97 | `src/Testing/FixtureReplayer.php` | `10-commands-testing.md` | Replay utility |

**Phase 4 tests**: Onboarding flow EARS criteria (start_onboarding routing, guest creation, profile
collection, consent gate, completion). `OnboardingGuard` blocks gated intents, allows when complete.
`OnboardingGuard` re-consent on version bump. Artisan command output assertions (`emissary:report`
format, `emissary:replay` trace, `emissary:prune` pruning). `emissary:fixture:capture` → valid JSON.
`AgentTestCase::replay()` replays fixtures deterministically. Full `composer test` green.

**Phase 4 verification**:
```bash
composer test  # full suite
```

---

## Dependency Graph

```
Phase 1 (Foundation: 46 files)
  │
  │  contracts, DTOs, models, migrations, fakes, config
  │
  ▼
Phase 2 (Pipeline Core: 28 files)
  │
  │  router, guards, tools, agent, memory, events, listeners, AgentTestCase
  │
  ├──────────────────────────┐
  ▼                          ▼
Phase 3 (Channels: 14 files)   Phase 4 (Onboarding + Ops: 9 files)
  │                            │
  │  adapters, credential       │  onboarding, commands, replay-fixture
  │  stores, webhooks, widget   │
  │  channel commands           │
  ▼                            ▼
         Full system integration
```

Phase 3 and Phase 4 are independent after Phase 2. They can be developed in parallel by separate agents.

## Risks & Mitigations

| Risk | Likelihood | Impact | Mitigation |
|---|---|---|---|
| Migrations mismatch models | M | H | Models + migrations built as pairs per table; test up/down per migration. |
| ToolScanner type inference bugs | H | M | Test every PHP type → JSON mapping combinatorially (string, int, float, bool, nullable, array, enum override). |
| Guard short-circuit order wrong | L | H | Test guard ordering and short-circuit in Phase 2 before any guard implementation. |
| WhatsApp HMAC verification failures | M | M | Use known-good test vectors for HMAC-SHA256; test with invalid/expired/malformed signatures. |
| Onboarding state machine race conditions | M | H | Test every state transition path; assert idempotency for re-entrant calls. |
| LLM API compatibility drift | L | M | `FakeLlmClient` captures exact JSON shapes; Phase 2 tests catch schema mismatches early. |
| Config key naming drifts from spec | L | L | Link every config key in `config/emissary.php` to a spec reference; test coverage for each key. |

## Definition of Done (per phase)

1. All components match `specs/02-contracts.md` / `specs/03-pipeline.md` verbatim (Principle 1).
2. Typed events emitted with `turn_id` propagation at every observable point (Principle 4).
3. Security controls wired and default-on per `specs/06-security.md` (Principle 2).
4. Covered by Pest tests using fakes — never a live LLM or channel (Principle 3).
5. `composer test` is green for all new cases in the phase.
6. No inline comments in implementation code unless explicitly requested (Principle 10).

## File Count Summary

| Phase | Source Files | Migrations | Config/Views | Test Doubles | Total |
|---|---|---|---|---|---|
| Phase 1 | 27 | 10 | 2 | 4 | 43 |
| Phase 2 | 28 | 0 | 0 | 0 | 28 |
| Phase 3 | 14 | 0 | 1 (widget + assets) | 0 | 15 |
| Phase 4 | 9 | 0 | 0 | 1 | 10 |
| **Grand Total** | **78** | **10** | **3** | **5** | **96** |
