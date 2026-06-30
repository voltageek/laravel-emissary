# Emissary Full Implementation

> Spec version: 2.6.0 · Status: Approved · Source: `specs/` (12 files)

## Purpose

Implement the complete Emissary library (`voltageek/laravel-emissary`) as specified in the
12-spec-file suite under `specs/`. This is a reference to the canonical spec, not a duplicate.

## Source Spec Files

| File | Content |
|---|---|
| `specs/01-architecture.md` | Pipeline architecture, 7 stages, data flow |
| `specs/02-contracts.md` | 7 interfaces, 8 DTOs, #[Tool] attribute, 15 error codes |
| `specs/03-pipeline.md` | 9 pipeline components with EARS acceptance criteria |
| `specs/04-data-models.md` | 9 tables, 10 migrations, all columns and relationships |
| `specs/05-observability.md` | 6 events, 5 listeners, export hook, replay |
| `specs/06-security.md` | 6 attack surfaces, consent, retention/PII |
| `specs/07-channels.md` | 3 channel adapters, credential matrix, webhooks |
| `specs/08-user-onboarding.md` | 3 onboarding modes, first-contact flow, consent |
| `specs/09-configuration.md` | 23 config keys, 8 service provider bindings |
| `specs/10-commands-testing.md` | 11 artisan commands, 4 test doubles, AgentTestCase |
| `specs/11-decisions.md` | 17 design decisions, dependencies, migration path |

## Non-Goals

- Not a new framework — composes on Laravel, targets framework-agnostic later
- Not a model-training or fine-tuning tool
- Not a UI framework — web chat is a drop-in widget
- Phase 3 of the migration path (framework-agnostic adapters) is deferred

## Implementation Phases

See `plan.md` for the detailed 4-phase implementation plan with file-by-file breakdown.
