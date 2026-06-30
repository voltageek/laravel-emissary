# Phase 1 — Foundation Tasks

> Sprint: Phase 1 · Date: 2026-06-30 · Plan: [plan.md](plan.md) · Spec: `specs/` (12 files)

## Summary

Implement the 46 file foundation for Emissary: contracts, DTOs, enums, error taxonomy, config,
service provider, 10 Eloquent models, 11 migrations, 4 test doubles, and 2 default implementations.
No pipeline behavior yet — this phase establishes the vocabulary and data layer everything else builds on.

## Task Organization

Phases reflect dependency order. Files within a phase marked `[P]` are parallel-safe (independent
files, no shared state). Migrations are sequential by FK constraint order; models can be written
in parallel.

---

## Phase 1.1 — Project Scaffold

**Goal**: Directory structure and namespace wiring exist before any source file is created.

- [x] T001 Create package directory structure: `src/`, `src/Contracts/`, `src/Attributes/`, `src/Models/`, `src/Testing/`, `config/`, `database/migrations/`, `tests/`, `resources/views/`
- [x] T002 [P] Create `composer.json` with package name `voltageek/laravel-emissary`, PHP 8.3+ requirement, PSR-4 autoload mapping `Emissary\\` → `src/`, `Emissary\\Testing\\` → `src/Testing/`, and dependencies (guzzlehttp/guzzle, nesbot/carbon, illuminate/support ^11)
- [x] T003 [P] Create `phpunit.xml` or `pest.xml` configuring Pest test runner with `Emissary\Testing\` namespace

---

## Phase 1.2 — Contracts & DTOs (Blocking Foundation)

**Goal**: All interfaces, DTOs, enums, attribute, and error codes compile. These are consumed by every downstream component.

**Independent test**: `composer dump-autoload && php -r "require 'vendor/autoload.php';"` succeeds with zero errors.

### Interfaces (7 files — all `[P]` parallel)

- [x] T004 [P] Implement `AgentGuard` interface in `src/Contracts/AgentGuard.php` with `getName(): string`, `beforeIntent(InboundMessage, ?Authenticatable, mixed): GuardResult`, `beforeExecution(string, ?Authenticatable, mixed): GuardResult`, `beforeTool(string, array, ?Authenticatable, mixed): GuardResult`
- [x] T005 [P] Implement `AgentToolProvider` interface in `src/Contracts/AgentToolProvider.php` with `pluginName()`, `getIntents()`, `getIntentConfig()`, `getIntentClassificationHints()`, `getToolDefinitions()`, `getGuards()`, `getSystemPromptExtension()`, `getDocumentMappings()`, `isIntentSupported()`
- [x] T006 [P] Implement `TenancyResolver` interface in `src/Contracts/TenancyResolver.php` with `resolve(InboundMessage): mixed` and `activate(mixed): void`
- [x] T007 [P] Implement `ChannelIdentityResolver` interface in `src/Contracts/ChannelIdentityResolver.php` with `resolveUser(InboundMessage): ?Authenticatable`
- [x] T008 [P] Implement `ChannelCredentialStore` interface in `src/Contracts/ChannelCredentialStore.php` with `resolve(Channel, mixed): ?ChannelCredentials`
- [x] T009 [P] Implement `ChannelAdapter` interface in `src/Contracts/ChannelAdapter.php` with `parse(Request): InboundMessage`, `verify(Request): bool`, `formatResponse(AgentResponse): OutboundMessage`, `send(string, OutboundMessage): void`
- [x] T010 [P] Implement `ConfirmationGate` interface in `src/Contracts/ConfirmationGate.php` with `propose(Conversation, array): string`, `execute(Conversation): array`, `cancel(Conversation): void`, `isExpired(Conversation): bool`

### DTOs & Value Objects (10 files — all `[P]` parallel)

- [x] T011 [P] Implement `AgentError` final class in `src/AgentError.php` with all 15 constants: GUARD_DENIED, AUTH_UNAUTHENTICATED, AUTH_UNAUTHORIZED, INTENT_LOW_CONFIDENCE, INTENT_UNKNOWN, TOOL_EXECUTION_FAILED, TOOL_INVALID_ARGUMENTS, TOOL_MAX_ROUNDS, LLM_TIMEOUT, LLM_RATE_LIMITED, LLM_ERROR, SECURITY_JAILBREAK, COST_LIMIT_EXCEEDED, ONBOARDING_REQUIRED, CONVERSATION_MAX_TURNS
- [x] T012 [P] Implement `Channel` backed enum in `src/Channel.php` with cases: WhatsApp, Telegram, Web
- [x] T013 [P] Implement `GuardResult` readonly class in `src/GuardResult.php` with `bool $allowed`, `?string $userMessage`, `?string $errorCode`; static factories `allow(): self`, `deny(string, ?string): self`
- [x] T014 [P] Implement `InboundMessage` readonly class in `src/InboundMessage.php` with `string $conversationRef`, `Channel $channel`, `string $text`, `?string $mediaUrl`, `Carbon $receivedAt`
- [x] T015 [P] Implement `OutboundMessage` readonly class in `src/OutboundMessage.php` with `string $text`, `?string $mediaUrl`, `?array $quickReplies`, `?array $channelExtras`
- [x] T016 [P] Implement `IntentResult` readonly class in `src/IntentResult.php` with `string $slug`, `float $confidence`
- [x] T017 [P] Implement `AgentResponse` mutable class in `src/AgentResponse.php` with `string $content`, `?string $intent`, `?array $toolCalls`, `bool $confirmationRequired`, `?string $errorCode`; static `fromContent(string): self`, `fromError(string, string): self`, `toOutbound(): OutboundMessage`
- [x] T018 [P] Implement `TransactionResult` mutable class in `src/TransactionResult.php` with `bool $success`, `?string $referenceId`, `?string $message`; static factories `ok(string, ?string): self`, `fail(string): self`
- [x] T019 [P] Implement `ChannelCredentials` readonly class in `src/ChannelCredentials.php` with `string $verifySecret`, `?string $accessToken`, `?string $senderId`, `?string $handshakeToken`, `?array $extra`

### Attribute (1 file)

- [x] T020 [P] Implement `#[Tool]` attribute class in `src/Attributes/Tool.php` targeting `\Attribute::TARGET_METHOD` with constructor properties: `string $description`, `bool $requiresConfirmation = false`, `?string $confirmationTemplate = null`, `array $intents = []`, `array $params = []`

---

## Phase 1.3 — Configuration & Service Provider

**Goal**: `config/emissary.php` provides all 23 top-level keys with spec defaults. `EmissaryServiceProvider` merges config and registers 8 container bindings.

**Independent test**: `config('emissary.default_model')` returns `'google/gemma-4-31b-it'`. Service provider is bootable in a test Laravel app.

- [x] T021 Implement `config/emissary.php` with all 23 top-level keys from `specs/09-configuration.md`: default_model, complex_model, vision_model, model_rates, openrouter, complex_intents, confidence_escalation_threshold, intent_confidence_threshold, intents, rate_limit, require_auth_intents, confirmation_timeout_seconds, max_conversation_turns, max_tool_call_rounds, memory, webhook_path, channels, channel_credential_store, error_messages (14 entries), cost_alerts, security (jailbreak/tool_result_wrap/require_webhook_verify), retention (4 TTLs), observability (trace_guard_allows/capture_llm_payloads/capture_trace_spans/otel), onboarding (enabled/mode/welcome_message/fields/field_map/require_consent/consent_text/consent_version/gated_intents/guest_role)
- [x] T022 Implement `EmissaryServiceProvider` in `src/EmissaryServiceProvider.php` with: `register()` → merge config, singletons (IntentRouter, ToolRegistry, GuardRegistry), bindings (TenancyResolver → NullTenancyResolver, ChannelIdentityResolver → AuthChannelIdentityResolver, ChannelCredentialStore → config key, ConfirmationGate → DatabaseConfirmationGate); `boot()` → publishes config and migrations; stubbed `$app->booted()` callback for plugin tag scanning

---

## Phase 1.4 — Migrations & Eloquent Models

**Goal**: All 11 migrations run and roll back cleanly. All 10 Eloquent models expose correct columns, casts, and relationships.

**Independent test**: `php artisan migrate && php artisan migrate:rollback && php artisan migrate` succeeds. Each model can `::create()` a valid row.

### Migrations (11 files — sequential by FK dependency)

- [x] T023 Create `database/migrations/2026_06_30_000001_create_conversations_table.php` with columns: id (UUID PK), tenant_id (nullable UUID), channel (varchar 20), channel_ref (varchar 100), status (varchar 20 default 'active'), onboarding_state (varchar 20 default 'new'), pending_action (nullable JSON), summary (nullable TEXT), created_at, updated_at; UNIQUE(channel, channel_ref)
- [x] T024 Create `database/migrations/2026_06_30_000002_create_conversation_messages_table.php` with columns: id (UUID PK), conversation_id (UUID FK → conversations), turn_id (nullable UUID), role (varchar 20), content (TEXT), media_url (nullable varchar), intent (nullable varchar), error_code (nullable varchar), created_at; INDEX(conversation_id, created_at), INDEX(turn_id)
- [x] T025 Create `database/migrations/2026_06_30_000003_create_agent_events_table.php` with columns: id (UUID PK), turn_id (nullable UUID), conversation_id (UUID FK → conversations), tenant_id (nullable UUID), kind (varchar 20), model (nullable varchar), input_tokens (nullable integer), output_tokens (nullable integer), latency_ms (nullable integer), intent (nullable varchar), checkpoint (nullable varchar), guard (nullable varchar), tool_name (nullable varchar), result (nullable varchar), error_code (nullable varchar), error (nullable TEXT), payload (nullable JSON), conversation_message_id (nullable UUID), created_at; INDEX(turn_id, created_at), INDEX(conversation_id, created_at)
- [x] T026 Create `database/migrations/2026_06_30_000004_create_tool_invocations_table.php` with columns: id (UUID PK), turn_id (nullable UUID), conversation_id (UUID FK → conversations), tenant_id (nullable UUID), tool_name (varchar), arguments (JSON), result_summary (nullable TEXT), duration_ms (nullable integer), success (boolean), validation_error (nullable varchar), triggered_via (varchar 24), agent_event_id (nullable UUID FK → agent_events), created_at; INDEX(conversation_id, tool_name)
- [x] T027 Create `database/migrations/2026_06_30_000005_create_channel_identity_links_table.php` with columns: id (UUID PK), user_id (UUID FK → users), channel (varchar 20), channel_ref (varchar 100), verified_at (nullable timestamp), created_at; UNIQUE(channel, channel_ref), INDEX(user_id)
- [x] T028 Create `database/migrations/2026_06_30_000006_create_llm_payloads_table.php` with columns: id (UUID PK), agent_event_id (UUID FK → agent_events), turn_id (nullable UUID), request_messages (JSON), tools_sent (nullable JSON), response (JSON), created_at
- [x] T029 Create `database/migrations/2026_06_30_000007_create_agent_spans_table.php` with columns: id (UUID PK), turn_id (nullable UUID), conversation_id (UUID FK → conversations), stage (varchar 48), duration_ms (integer), created_at
- [x] T030 Create `database/migrations/2026_06_30_000008_create_cost_ledgers_table.php` with columns: id (UUID PK), conversation_id (UUID FK → conversations), tenant_id (nullable UUID), month (varchar 7), input_tokens (integer), output_tokens (integer), cost_usd (decimal 12,6); UNIQUE(conversation_id, month)
- [x] T031 Create `database/migrations/2026_06_30_000009_create_channel_configs_table.php` with columns: id (UUID PK), tenant_id (nullable UUID), channel (varchar 20), label (varchar), credentials (TEXT — encrypted JSON), status (varchar 20 default 'active'), created_at, updated_at; UNIQUE(tenant_id, channel)
- [x] T032 Create `database/migrations/2026_06_30_000010_create_user_onboardings_table.php` with columns: id (UUID PK), user_id (UUID FK → users), conversation_id (nullable UUID FK → conversations), status (varchar 20), profile (nullable JSON), consent_at (nullable timestamp), consent_version (nullable varchar 32), created_at, completed_at (nullable timestamp); UNIQUE(user_id)
- [x] T033 Create `database/migrations/2026_06_30_000011_add_onboarded_at_to_users_table.php` — publishable migration adding nullable `onboarded_at` timestamp column to host `users` table

### Eloquent Models (10 files — all `[P]` parallel, each paired with its migration)

- [x] T034 [P] Implement `Conversation` model in `src/Models/Conversation.php` with UUID PK (`$incrementing = false`, `$keyType = 'string'`, `HasUuids`), `$fillable`, `$casts` for pending_action (array), relationships: `messages()`, `events()`, `toolInvocations()`, `identityLink()`
- [x] T035 [P] Implement `ConversationMessage` model in `src/Models/ConversationMessage.php` with UUID PK, `$timestamps = false` (no updated_at), `$fillable`, `$casts`, relationship: `conversation()`
- [x] T036 [P] Implement `AgentEvent` model in `src/Models/AgentEvent.php` with UUID PK, `$timestamps = false`, `$fillable`, `$casts` for payload (array), relationships: `conversation()`, `toolInvocations()`, `llmPayload()`
- [x] T037 [P] Implement `ToolInvocation` model in `src/Models/ToolInvocation.php` with UUID PK, `$timestamps = false`, `$fillable`, `$casts` for arguments (array), relationships: `conversation()`, `agentEvent()`
- [x] T038 [P] Implement `ChannelIdentityLink` model in `src/Models/ChannelIdentityLink.php` with UUID PK, `$timestamps = false`, `$fillable`, `$casts` for verified_at (datetime), relationship: `user()`
- [x] T039 [P] Implement `LlmPayload` model in `src/Models/LlmPayload.php` with UUID PK, `$timestamps = false`, `$fillable`, `$casts` for request_messages (array), tools_sent (array), response (array), relationship: `agentEvent()`
- [x] T040 [P] Implement `AgentSpan` model in `src/Models/AgentSpan.php` with UUID PK, `$timestamps = false`, `$fillable`, relationship: `conversation()`
- [x] T041 [P] Implement `CostLedger` model in `src/Models/CostLedger.php` with UUID PK, `$timestamps = false`, `$fillable`, relationship: `conversation()`
- [x] T042 [P] Implement `ChannelConfig` model in `src/Models/ChannelConfig.php` with UUID PK, `$fillable`, `$casts` for credentials (encrypted), relationship: none (standalone)
- [x] T043 [P] Implement `UserOnboarding` model in `src/Models/UserOnboarding.php` with UUID PK, `$fillable`, `$casts` for profile (array), consent_at (datetime), completed_at (datetime), relationships: `user()`, `conversation()`

---

## Phase 1.5 — Default Implementations

**Goal**: Two simple implementations satisfy their interfaces with minimal behavior so Phase 2 has resolvable bindings.

**Independent test**: `app(TenancyResolver::class)->resolve(...)` returns null. `app(ChannelIdentityResolver::class)->resolveUser(...)` returns session user or null per channel.

- [x] T044 [P] Implement `NullTenancyResolver` in `src/NullTenancyResolver.php` implementing `TenancyResolver` — `resolve()` always returns `null`, `activate()` is a no-op
- [x] T045 [P] Implement `AuthChannelIdentityResolver` in `src/AuthChannelIdentityResolver.php` implementing `ChannelIdentityResolver` — `resolveUser(InboundMessage)` returns `auth()->user()` when `$message->channel === Channel::Web`, `null` otherwise

---

## Phase 1.6 — Test Doubles

**Goal**: `FakeLlmClient`, `FakeChannelAdapter`, `Clock`, and `ToolCall` provide deterministic test infrastructure for Phase 2.

**Independent test**: Pest tests pass: scripting API chains, recording, and clock advancement.

- [x] T046 [P] Implement `ToolCall` value object in `src/Testing/ToolCall.php` with `string $name`, `array $arguments = []` and static factory `make(string, array): self`
- [x] T047 Implement `FakeLlmClient` in `src/Testing/FakeLlmClient.php` with: static `make(): self`, `onIntent(IntentResult): self` (queues intent response), `onAgent(ToolCall|string): self` (queues agent step), `thenText(string): self` (alias), `thenToolCall(ToolCall): self` (alias), `calls(): array` (recorded calls), `assertCalled(int): void`. Internal state machine dequeues responses per call.
- [x] T048 [P] Implement `FakeChannelAdapter` in `src/Testing/FakeChannelAdapter.php` implementing `ChannelAdapter` with: static `whatsapp(): self`, `telegram(): self`, `web(): self` factories; `parse()` creates InboundMessage from internal buffer; `verify()` returns true; `formatResponse()` wraps in OutboundMessage; `send()` records to internal array; `assertSent(string): void`, `sendCount(): int`, `lastOutbound(): ?OutboundMessage`
- [x] T049 [P] Implement `Clock` fake in `src/Testing/Clock.php` extending Carbon with: static `fake(string $now): self` (freeze time), `advance(int $seconds): void` (move frozen time forward). Injectable via constructor — pipeline code never calls `now()` directly.

---

## Phase 1.7 — Pest Tests

**Goal**: Verify Phase 1 artifacts compile, migrations work, and test doubles behave correctly.

**Independent test**: `composer test -- --filter=Phase1` is green.

- [x] T050 [P] Create `tests/Unit/Contracts/AgentErrorTest.php` — assert all 15 constants are defined with correct string values per `specs/02-contracts.md`
- [x] T051 [P] Create `tests/Unit/Contracts/GuardResultTest.php` — assert `allow()` returns `$allowed = true`, `deny('msg', 'code')` returns `$allowed = false` with correct message and code; `deny('msg')` defaults code to `null`
- [x] T052 [P] Create `tests/Unit/Contracts/DtoTest.php` — assert all readonly DTOs (InboundMessage, OutboundMessage, IntentResult, ChannelCredentials) accept constructor args and expose properties correctly
- [x] T053 [P] Create `tests/Unit/Contracts/AgentResponseTest.php` — assert `fromContent()` creates with content, `fromError()` sets errorCode; `toOutbound()` returns OutboundMessage with matching text
- [x] T054 [P] Create `tests/Unit/Contracts/TransactionResultTest.php` — assert `ok('ref', 'msg')` returns success=true, `fail('msg')` returns success=false
- [x] T055 [P] Create `tests/Unit/Testing/FakeLlmClientTest.php` — assert `make()->onIntent(...)->onAgent(...)->thenText(...)` chains return correct responses in order; `calls()` records all calls; empty queue throws
- [x] T056 [P] Create `tests/Unit/Testing/FakeChannelAdapterTest.php` — assert `whatsapp()` factory sets channel; `send()` records OutboundMessage; `assertSent()` passes/fails correctly; `lastOutbound()` returns latest
- [x] T057 [P] Create `tests/Unit/Testing/ClockTest.php` — assert `fake('2026-06-30 10:00:00')` freezes time; `advance(900)` moves 15 min forward; `now()` reflects advanced time
- [x] T058 [P] Create `tests/Unit/Identity/NullTenancyResolverTest.php` — assert `resolve()` always returns null regardless of InboundMessage
- [x] T059 [P] Create `tests/Unit/Identity/AuthChannelIdentityResolverTest.php` — assert returns authenticated user for Channel::Web, null for WhatsApp and Telegram
- [x] T060 [P] Create `tests/Unit/Migrations/MigrationTest.php` — assert `php artisan migrate` runs all 11 migrations; `php artisan migrate:rollback` drops all tables; re-run succeeds; assert table columns match spec

---

## Phase 1.8 — Verification

**Goal**: Full Phase 1 validation pass — no failures, no regressions.

- [x] T061 Run `composer dump-autoload` and verify zero class-not-found errors
- [x] T062 Run `php artisan migrate && php artisan migrate:rollback && php artisan migrate` and verify zero errors
- [x] T063 Run `composer test` and verify all Phase 1 tests pass with green output
- [x] T064 Verify no live HTTP calls, no live LLM calls, no direct `now()` or `Carbon::now()` calls in any source file (only in `Clock` test double)

---

## Dependency Graph

```
Phase 1.1 (Scaffold: T001-T003)
  │
  ▼
Phase 1.2 (Contracts + DTOs: T004-T020)
  │  └─ All [P] — parallelize across agents
  │
  ▼
Phase 1.3 (Config + Provider: T021-T022)
  │
  ▼
Phase 1.4 (Migrations: T023-T033 sequentially, Models: T034-T043 parallel)
  │  └─ Models depend on migrations being written, not run
  │
  ├──► Phase 1.5 (Defaults: T044-T045) [P]
  │
  ├──► Phase 1.6 (Test Doubles: T046-T049) [T047 blocks T046; T048-T049 parallel]
  │
  └──► Phase 1.7 (Tests: T050-T060) [all P after T047-T049 exist]
          │
          ▼
       Phase 1.8 (Verification: T061-T064)
```

## Parallel Execution Opportunities

**Phase 1.2**: All 17 files (T004-T020) are independent. Can be split across 3-4 agents:
- Agent A: Interfaces T004-T007
- Agent B: Interfaces T008-T010 + Attribute T020
- Agent C: DTOs T011-T015
- Agent D: DTOs T016-T019

**Phase 1.4 (Models)**: T034-T043 all independent. Can be split across 2 agents:
- Agent A: Core models T034-T039
- Agent B: Optional models T040-T043

**Phase 1.7 (Tests)**: T050-T060 all independent after test doubles exist:
- Agent A: Contract tests T050-T054
- Agent B: Test double tests T055-T057
- Agent C: Identity + Migration tests T058-T060

## File Count

| Sub-Phase | Files | Tasks |
|---|---|---|
| 1.1 Scaffold | 3 dirs + 2 files | 3 |
| 1.2 Contracts & DTOs | 18 files | 17 |
| 1.3 Config & Provider | 2 files | 2 |
| 1.4 Migrations & Models | 21 files | 21 |
| 1.5 Defaults | 2 files | 2 |
| 1.6 Test Doubles | 4 files | 4 |
| 1.7 Tests | 11 test files | 11 |
| 1.8 Verification | — | 4 |
| **Total** | **~46 source + 11 test** | **64** |

## Verification Commands

```bash
# Contracts compile
composer dump-autoload

# Migrations
php artisan migrate && php artisan migrate:rollback && php artisan migrate

# Full Phase 1 test suite
composer test -- --filter="(AgentError|GuardResult|AgentResponse|TransactionResult|FakeLlmClient|FakeChannelAdapter|Clock|NullTenancyResolver|AuthChannelIdentityResolver|Migration)"

# Single test file
vendor/bin/pest tests/Unit/Testing/FakeLlmClientTest.php
```
