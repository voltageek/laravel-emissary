# Emissary

> A standalone PHP library that adds agentic, multi-channel chat (WhatsApp/Telegram/Web) to existing Laravel apps. Developers define three things: **intents, tools, guards**.

**Version**: 2.6.0 · **Status**: Draft · **Package**: `voltageek/laravel-emissary`

The spec is split by concern so an implementing agent loads only the relevant slice. Start with `../AGENTS.md` for conventions and the definition-of-done, then the file you need from the map below.

## File map

| File | What it covers | Read when… |
|---|---|---|
| [`01-architecture.md`](01-architecture.md) | Purpose, pipeline diagram, the 7 pipeline stages | you need the big picture / data flow |
| [`02-contracts.md`](02-contracts.md) | `AgentToolProvider`, `AgentGuard`, `ChannelAdapter`, `ChannelCredentialStore`, `ChannelIdentityResolver`, `ConfirmationGate`, `TenancyResolver`; `AgentError`; all DTOs | implementing any interface or DTO |
| [`03-pipeline.md`](03-pipeline.md) | `IntentRouter`, `ModelSelector`, `GuardRegistry`, `TaskAgent`, `ToolScanner`, `ToolRegistry`, `ConversationMemory`; end-to-end data flow | implementing pipeline behaviour |
| [`04-data-models.md`](04-data-models.md) | Every table: `Conversation`, `ConversationMessage`, `AgentEvent`, `ToolInvocation`, `LlmPayload`, `AgentSpan`, `CostLedger`, `ChannelConfig`, `ChannelIdentityLink`, `UserOnboarding` | writing migrations |
| [`05-observability.md`](05-observability.md) | Typed events, listeners, export hook, replay→fixtures | wiring events / metrics |
| [`06-security.md`](06-security.md) | The 6 attack surfaces, consent, retention/PII | hardening / compliance |
| [`07-channels.md`](07-channels.md) | Channel onboarding: credential matrix, webhook routes, per-channel setup | getting a channel live |
| [`08-user-onboarding.md`](08-user-onboarding.md) | First-contact flow, modes, consent gate, guest creation | onboarding behaviour |
| [`09-configuration.md`](09-configuration.md) | `config/agent.php`, service-provider bindings, plugin registration | wiring/configuring |
| [`10-commands-testing.md`](10-commands-testing.md) | Artisan commands + the Pest `Emissary\Testing\` toolkit | testing / debugging |
| [`11-decisions.md`](11-decisions.md) | 17 key design decisions, dependencies, migration path | you're unsure *why* something is the way it is |

## Concept → file quick lookup

- Intents → `03` (router), `09` (config), `08` (onboarding intents)
- Tools (`#[Tool]`, `ToolScanner`, schema, validation) → `02` (attribute + contract), `03` (scanner + registry)
- Guards (3 checkpoints, built-ins, `GuardResult`) → `02`, `03`
- Channels (parse/verify/send, credentials, webhook) → `02`, `07`
- Identity (`ChannelIdentityResolver`, linking, guests) → `02`, `08`
- Onboarding (welcome, consent, guest upgrade) → `08`
- Observability (`turn_id`, events, replay) → `05`
- Security (injection, consent, encryption) → `06`
- Errors (`AgentError` codes + messages) → `02`, `09`
- Testing (fakes, `AgentTestCase`, fixtures) → `10`

## Changelog (latest first)
- **2.6** — Testing strategy (Pest toolkit, replay-as-fixture).
- **2.5** — User onboarding (hybrid, configurable guest creation, consent).
- **2.4** — Channel onboarding (`ChannelCredentialStore`, setup commands, identity linking).
- **2.3** — Turn-traceable observability (`turn_id`, typed events, replay capture).
- **2.2** — Security model (webhook verify, schema validation, jailbreak guard, cost cap).
- **2.1** — Attribute-driven tools (`#[Tool]`, `ToolScanner`); `getToolHandlers()` removed.
- **2.0** — Merged handler types; first-class guards; optional tenancy; error taxonomy.
