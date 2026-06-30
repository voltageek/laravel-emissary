# Phase 1 — Quickstart

## Prerequisites

- PHP 8.3+
- Laravel 11+ application (for running migrations and container binding)
- Composer with `voltageek/laravel-emissary` package initialized
- Pest for testing (`composer require --dev pestphp/pest`)

## Phase 1 Goal

Establish the contract surface, data layer, config, service provider skeleton, and test doubles.
At the end of Phase 1, no agent behavior exists — but every downstream component has a stable
vocabulary and data persistence to build on.

## File Creation Order

### Step 1: Contracts (7 interfaces + 1 attribute)

```bash
# Create source directories
mkdir -p src/Contracts src/Attributes src/Models src/Testing

# Interfaces
touch src/Contracts/AgentToolProvider.php
touch src/Contracts/AgentGuard.php
touch src/Contracts/TenancyResolver.php
touch src/Contracts/ChannelIdentityResolver.php
touch src/Contracts/ChannelCredentialStore.php
touch src/Contracts/ChannelAdapter.php
touch src/Contracts/ConfirmationGate.php

# Attribute
touch src/Attributes/Tool.php
```

Start with `AgentGuard` and `GuardResult` — they're the simplest and establish the pattern.
Then `AgentToolProvider` (largest interface, depends on `AgentGuard`). DTOs next. Enums and
error codes last (pure constants).

### Step 2: DTOs + Enums + Error Codes (10 files)

```bash
touch src/AgentError.php          # Final class, 15 constants
touch src/Channel.php             # Backed enum
touch src/GuardResult.php         # Readonly DTO with static factories
touch src/InboundMessage.php      # Readonly DTO
touch src/OutboundMessage.php     # Readonly DTO
touch src/IntentResult.php        # Readonly DTO
touch src/AgentResponse.php       # Mutable DTO (static constructors + toOutbound)
touch src/TransactionResult.php   # Mutable DTO (static ok/fail factories)
touch src/ChannelCredentials.php  # Readonly DTO

# Default implementations (no dependencies beyond interfaces)
touch src/NullTenancyResolver.php
touch src/AuthChannelIdentityResolver.php
```

### Step 3: Config + Service Provider (2 files)

```bash
touch config/emissary.php
touch src/EmissaryServiceProvider.php
```

The service provider merges config, registers singletons, and binds defaults. Plugin boot logic
(`$app->booted()` callback scanning tagged providers) is stubbed — it's wired in Phase 2.

### Step 4: Migrations (11 files)

```bash
mkdir -p database/migrations
# Create in dependency order (see data-model.md for FK constraints)
# Use timestamped filenames: 2026_06_30_000001_create_conversations_table.php
touch database/migrations/2026_06_30_000001_create_conversations_table.php
touch database/migrations/2026_06_30_000002_create_conversation_messages_table.php
touch database/migrations/2026_06_30_000003_create_agent_events_table.php
touch database/migrations/2026_06_30_000004_create_tool_invocations_table.php
touch database/migrations/2026_06_30_000005_create_channel_identity_links_table.php
touch database/migrations/2026_06_30_000006_create_llm_payloads_table.php
touch database/migrations/2026_06_30_000007_create_agent_spans_table.php
touch database/migrations/2026_06_30_000008_create_cost_ledgers_table.php
touch database/migrations/2026_06_30_000009_create_channel_configs_table.php
touch database/migrations/2026_06_30_000010_create_user_onboardings_table.php
touch database/migrations/2026_06_30_000011_add_onboarded_at_to_users_table.php
```

Build each migration alongside its Eloquent model as a pair — model matches migration column for
column. Optional tables get migrations regardless of whether opt-in listeners are registered.

### Step 5: Eloquent Models (10 files)

```bash
touch src/Models/Conversation.php
touch src/Models/ConversationMessage.php
touch src/Models/AgentEvent.php
touch src/Models/ToolInvocation.php
touch src/Models/ChannelIdentityLink.php
touch src/Models/LlmPayload.php
touch src/Models/AgentSpan.php
touch src/Models/CostLedger.php
touch src/Models/ChannelConfig.php
touch src/Models/UserOnboarding.php
```

### Step 6: Test Doubles (4 files)

```bash
touch src/Testing/FakeLlmClient.php
touch src/Testing/FakeChannelAdapter.php
touch src/Testing/Clock.php
touch src/Testing/ToolCall.php
```

`ToolCall` is a simple value object: `new ToolCall(string $name, array $arguments = [])`.

## Verification

### Run migrations

```bash
php artisan migrate
php artisan migrate:rollback   # verify down() works
php artisan migrate            # verify up() works twice
```

### Run tests

```bash
composer test -- --filter="(FakeLlmClient|FakeChannelAdapter|Clock|AgentError|GuardResult|InboundMessage|IntentResult|AgentResponse|TransactionResult|ChannelCredentials|Channel)"
```

### Check Phase 1 completion

- [ ] All 7 interfaces compile with correct signatures per `specs/02-contracts.md`
- [ ] All 10 DTOs/enums/error codes match spec verbatim (names, types, defaults)
- [ ] `#[Tool]` attribute class targets `TARGET_METHOD` with correct constructor params
- [ ] All 11 migrations run and rollback cleanly
- [ ] All 10 Eloquent models match their migration columns
- [ ] `config/emissary.php` has all 23 top-level keys with correct defaults
- [ ] `EmissaryServiceProvider` registers 8 bindings correctly
- [ ] `FakeLlmClient::make()->onIntent(...)->onAgent(...)->thenText(...)` chains work
- [ ] `FakeChannelAdapter::whatsapp()` records outbound messages without HTTP
- [ ] `Clock::fake('...')->advance(N)` advances deterministically
- [ ] `NullTenancyResolver::resolve()` returns null
- [ ] `AuthChannelIdentityResolver::resolveUser()` returns session user for Web, null for chat channels
- [ ] No live LLM calls, no live HTTP, no reading the wall clock directly in any file

## Next: Phase 2

When Phase 1 is verified green, Phase 2 builds the pipeline core on top of this foundation:
`IntentRouter`, `ModelSelector`, `GuardRegistry`, `ToolScanner`, `ToolRegistry`, `TaskAgent`,
`ConversationMemory`, `MessageBridge`, `ProcessMessage`, `DatabaseConfirmationGate`, all 6 event
classes, all 5 listeners, all 6 built-in guards, and `AgentTestCase`.
