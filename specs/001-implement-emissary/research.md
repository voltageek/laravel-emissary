# Phase 1 — Research & Decisions

> Resolved: 2026-06-30 · All unknowns resolved · 0 NEEDS CLARIFICATION remaining

## 1. Directory Structure

**Decision**: All library source code under `src/`, following PSR-4 convention mapped to
`Emissary\` namespace. Migrations published to host app via service provider.

**Rationale**: The spec mandates `Emissary\` namespace (Principle 10). Standard Laravel package
layout puts source in `src/`, config in `config/`, migrations in `database/migrations/`, tests in
`tests/`. This matches `composer.json` PSR-4 autoloading out of the box.

**Alternatives considered**: Flat `Emissary/` directory — rejected because it breaks Composer
PSR-4 conventions and would require custom autoloading.

## 2. Namespace Mapping

**Decision**:
```
src/Contracts/      → Emissary\Contracts\
src/Attributes/     → Emissary\Attributes\
src/Models/         → Emissary\Models\
src/Testing/        → Emissary\Testing\
src/                → Emissary\
```

**Rationale**: `Emissary\Contracts\` for interfaces matches the spec. `Emissary\Testing\` mirrors
`Illuminate\Testing` for host-app importability. DTOs and enums go in `Emissary\` root namespace
since they're referenced directly in interfaces.

**Alternatives considered**: Putting DTOs under `Emissary\DTOs\` — simpler but spec consistently
references DTOs as `Emissary\GuardResult`, `Emissary\InboundMessage`, etc. without a DTO sub-namespace.

## 3. UUID Primary Keys

**Decision**: All Eloquent models use UUIDs as primary keys, not auto-incrementing integers.
Use Laravel's built-in `HasUuids` trait.

**Rationale**: The spec explicitly states all PKs are `UUID`. UUIDs prevent enumeration attacks
on conversation IDs and avoid collision risks in multi-tenant or distributed deployments.

**Alternatives considered**: Ulid — Laravel supports it via `HasUlids` since 9.x, but the spec
says UUID specifically.

## 4. Migration Ordering

**Decision**: Migration files are timestamped in dependency order:
1. `conversations` (FK target for most other tables)
2. `conversation_messages` (FK → conversations)
3. `agent_events` (FK → conversations, FK target for tool_invocations + llm_payloads)
4. `tool_invocations` (FK → conversations, agent_events)
5. `channel_identity_links` (FK → users, standalone)
6. `llm_payloads` (FK → agent_events)
7. `agent_spans` (FK → conversations)
8. `cost_ledgers` (FK → conversations)
9. `channel_configs` (standalone, FK → tenant optionally)
10. `user_onboardings` (FK → users, conversations)
11. `add_onboarded_at_to_users` (alters host table — publishable migration)

**Rationale**: Foreign key constraints require referenced tables to exist first. Optional tables
(6-9) still need migrations so models match the schema whether opt-in listeners are registered or not.

## 5. Eloquent Model Conventions

**Decision**:
- `public $incrementing = false` + `protected $keyType = 'string'` on all models (UUID PKs)
- `$timestamps = false` on models without `updated_at` (`ConversationMessage`, `AgentEvent`,
  `ToolInvocation`, `LlmPayload`, `AgentSpan`, `CostLedger`)
- `$timestamps = true` on models with both `created_at` and `updated_at` (`Conversation`,
  `ChannelConfig`, `UserOnboarding`)
- `$fillable` arrays explicitly defined; no `$guarded` for security
- Central DB connection: `protected $connection = 'central'` or default application connection
  (spec says "central database connection, not tenant-scoped")

**Rationale**: Explicit is safer for a library. `$fillable` prevents mass-assignment surprises
in host apps. Leaving `$connection` at default lets the host app configure it globally.

## 6. Test Double Architecture

**Decision**: `FakeLlmClient` uses an internal state machine with scripted response queues:
- `onIntent(IntentResult)` → queues intent classification response
- `onAgent(ToolCall|string)` → queues agent loop responses (tool call or text)
- `thenText(string)` / `thenToolCall(ToolCall)` → synonym aliases for readability
- `assertCalled(n)` / `calls()` → inspection API

`FakeChannelAdapter` wraps a factory method and records outbound messages:
- `FakeChannelAdapter::whatsapp()` creates a WhatsApp-flavored fake
- `->assertSent(string)` / `->sendCount()` for assertions
- `->lastOutbound()` returns the most recent `OutboundMessage`

`Clock` extends Carbon and provides:
- `Clock::fake(string $now)` → freezes time
- `->advance(int $seconds)` → moves time forward
- Injectable via constructor — never read `now()` directly in pipeline code

**Rationale**: Fluent API matches Pest testing conventions. Recording all calls enables
assertion-based testing without mocking. Clock injection (vs. `Carbon::setTestNow()`) prevents
global state leakage between tests.

## 7. Configuration Publishing

**Decision**: Use `vendor:publish` with tag `emissary-config` for `config/emissary.php` and
`emissary-migrations` for migrations. The service provider's `boot()` method calls
`$this->publishes()`, not `$this->loadMigrationsFrom()`. Host apps run migrations themselves.

**Rationale**: Host apps control when migrations run. Auto-loading migrations from a package
is surprising and can cause CI failures if the host hasn't reviewed the schema. Config
publishing is standard Laravel package convention.

## 8. Service Provider Registration Order

**Decision**: The `EmissaryServiceProvider` registers:
1. Merge config (so `config('emissary.*')` works immediately)
2. Singletons (IntentRouter, ToolRegistry, GuardRegistry)
3. Bindings (TenancyResolver → NullTenancyResolver, ChannelIdentityResolver → AuthChannelIdentityResolver, etc.)

Plugin tagging happens in the host app's own `AppServiceProvider::boot()` via `$app->tag()`.
The boot callback (`$app->booted()`) scans tagged providers and feeds them into singletons.

**Rationale**: Separation of concerns — the library provides the plumbing, the host app
declares the plugins. Singletons before bindings ensures resolver chains are constructed
correctly.

## 9. NullTenancyResolver Design

**Decision**: `NullTenancyResolver::resolve()` always returns `null`. `activate()` is a no-op.
This is the default binding so single-tenant apps need zero configuration.

**Rationale**: Decision #5 explicitly requires tenancy to be optional. A null resolver with
no-op activate means the pipeline never blocks on tenant context. Multi-tenant apps swap it.

## 10. AuthChannelIdentityResolver Design

**Decision**: Returns `auth()->user()` when `$message->channel === Channel::Web`, otherwise
`null`. This is the default binding when onboarding mode is `web_centric` (or onboarding is
disabled entirely).

**Rationale**: Decision #15 requires chat-channel identity to be established through an
explicit linking flow, not assumed. The default resolver makes the conservative choice —
null for unauthenticated channels, session user for web.
