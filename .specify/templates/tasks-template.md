# [TASK_LIST_TITLE] — Tasks

> Sprint/Iteration: [ITERATION] · Date: [DATE]

## Task Categories

Tasks are organized by principle-driven type:

### 1. Contract Implementation (Principle 1 — Spec-Driven)
- Implement interfaces verbatim from `specs/02-contracts.md`
- Implement DTOs verbatim from `specs/02-contracts.md`
- Implement pipeline components from `specs/03-pipeline.md`

### 2. Security Wiring (Principle 2 — Security by Default)
- Wire per-component security controls from `specs/06-security.md`
- Ensure webhook verification fails closed on every channel adapter
- Encrypt channel credentials at rest

### 3. Test Coverage (Principle 3 — Testability First)
- Write Pest tests using `FakeLlmClient`, `FakeChannelAdapter`, `Clock`
- Assert events emitted per `specs/05-observability.md`
- Assert guard checkpoints per `specs/02-contracts.md` EARS rules
- Verify `composer test` is green

### 4. Observability Wiring (Principle 4 — Observability by Design)
- Emit typed events with `turn_id` propagation
- Implement listeners (default on: `LogAgentEvent`, `LogToolInvocation`)
- Wire replay capture hooks

### 5. Tool Registration (Principle 5 — Single Tool Surface)
- Implement `#[Tool]` attribute
- Implement `ToolScanner` with PHP-type → JSON-schema inference
- Implement `ToolRegistry` with schema validation before execution

### 6. Guard Implementation (Principle 6 — Guards as First-Class Primitives)
- Implement `GuardRegistry` with three checkpoints and ordered short-circuit evaluation
- Implement built-in guards: `RateLimitGuard`, `CostCapGuard`, `MaxTurnsGuard`,
  `JailbreakDetectionGuard`, `AuthenticatedUserGuard`, `OnboardingGuard`
- Emit `GuardDecision` events

### 7. Configuration (Principle 7 — Config-Driven Behavior)
- Publish `config/emissary.php` with all keys
- Bind interfaces in service provider from config
- Implement `ChannelCredentialStore` (config-backed and DB-backed)

### 8. Channel Adapters (Principle 8 — Channel Agnosticism)
- Implement `WhatsAppAdapter`, `TelegramAdapter`, `WebChatAdapter`
- Implement `ChannelCredentialStore` seam
- Implement `ChannelIdentityResolver` variants

### 9. Error Handling (Principle 9 — Explicit Error Taxonomy)
- Define `AgentError` constants
- Configure `error_messages` in `config/emissary.php`
- Return error codes consistently from guards, tools, and pipeline stages

### 10. Artisan Commands (Principle 3 — Testability First)
- Implement `emissary:report`, `emissary:replay`, `emissary:prune`
- Implement `emissary:channels:list`, `emissary:webhook:url`
- Implement `emissary:channel:test`, `emissary:channel:add`
- Implement `emissary:set-telegram-webhook`
- Implement `emissary:onboarding:status`, `emissary:onboarding:reset`
- Implement `emissary:fixture:capture`

## Tasks

### [PHASE/SECTION]

| # | Task | Category | Spec Ref | Status |
|---|---|---|---|---|
| 1 | [TASK_DESCRIPTION] | [CATEGORY] | `specs/[FILE].md` | [ ] |
| 2 | [TASK_DESCRIPTION] | [CATEGORY] | `specs/[FILE].md` | [ ] |

## Verification

```bash
composer test                                    # full suite
composer test -- --filter=[FILTER]               # targeted
vendor/bin/pest tests/Unit/[TestFile].php        # single file
```
