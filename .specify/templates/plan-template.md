# [PLAN_TITLE] — Implementation Plan

> Plan version: [PLAN_VERSION] · Target spec version: [SPEC_VERSION] · Date: [DATE]

## Summary

[ONE_PARAGRAPH_SUMMARY]

## Constitution Check

Before proceeding, verify alignment with the [Project Constitution](../memory/constitution.md):

- [ ] **Principle 1 — Spec-Driven**: All component names, method signatures, and return types
  match `specs/02-contracts.md` and `specs/03-pipeline.md` verbatim.
- [ ] **Principle 2 — Security by Default**: Security controls are wired and default-on; webhook
  verification fails closed; channel credentials are encrypted at rest.
- [ ] **Principle 3 — Testability First**: Tests use `FakeLlmClient`, `FakeChannelAdapter`, and
  `Clock` fakes; no live LLM or channel calls.
- [ ] **Principle 4 — Observability by Design**: Typed events are emitted with `turn_id`
  propagation at every observable point.
- [ ] **Principle 5 — Single Tool Surface**: Tools use `#[Tool]` attribute; no parallel handler map.
- [ ] **Principle 6 — Guards as First-Class Primitives**: Guards fire at three checkpoints; first
  deny short-circuits.
- [ ] **Principle 7 — Config-Driven Behavior**: Model escalation, rate limits, onboarding mode,
  and error messages are config-driven.
- [ ] **Principle 8 — Channel Agnosticism**: Tool handlers return plain data; channel formatting
  is in `ChannelAdapter::formatResponse()`.
- [ ] **Principle 9 — Explicit Error Taxonomy**: Failures use `AgentError` codes with
  configurable messages.
- [ ] **Principle 10 — PHP 8.3+ Conventions**: Readonly classes, named arguments, match
  expressions; Pest-first in `Emissary\Testing\` namespace.

## Scope

### In Scope

- [SCOPE_ITEM_1]
- [SCOPE_ITEM_2]

### Out of Scope

- [OUT_OF_SCOPE_ITEM_1]
- [OUT_OF_SCOPE_ITEM_2]

## Components

| Component | Spec File | Status | Owner |
|---|---|---|---|
| [COMPONENT_1] | `specs/[FILE].md` | [STATUS] | [OWNER] |
| [COMPONENT_2] | `specs/[FILE].md` | [STATUS] | [OWNER] |

## Dependencies

- [DEPENDENCY_1]
- [DEPENDENCY_2]

## Risks & Mitigations

| Risk | Likelihood | Impact | Mitigation |
|---|---|---|---|
| [RISK_1] | [L/M/H] | [L/M/H] | [MITIGATION] |

## Definition of Done

Per `AGENTS.md`:
1. Interface/behaviour matches `specs/02-contracts.md` / `specs/03-pipeline.md` verbatim.
2. Emits events specified in `specs/05-observability.md` with `turn_id` propagation.
3. Security controls from `specs/06-security.md` are wired and on by default.
4. Covered by tests described in `specs/10-commands-testing.md` using fakes.
5. `composer test` (Pest) is green for new cases.
