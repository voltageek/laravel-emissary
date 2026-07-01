# User Documentation

> Spec version: 1.0 · Status: Draft · Audience: Laravel developers (junior → senior)

## Purpose

Create a user-facing documentation suite for the **Emissary** Composer package (`voltageek/laravel-emissary`) that balances two outcomes: a fast, frustration-free on-ramp for junior developers, and progressively deeper technical detail for seniors. The docs live alongside the codebase (in a `docs/` directory) and are published to a documentation site. Every page answers "what problem does this solve?" before diving into "how do I use it?".

## Scope

### In Scope

- **Getting Started Guide** — install, publish config, register the plugin, verify with a test command → first "hello world" chat in under 10 minutes
- **Core Concepts Overview** — intents, tools, guards explained with diagrams and worked examples
- **Tool Authoring Guide** — `#[Tool]` attribute, parameter schemas, validation, return types
- **Guard Authoring Guide** — the 3 checkpoints, `GuardResult`, built-in guards (rate-limit, cost-cap, jailbreak)
- **Channel Setup Guides** — WhatsApp Business API, Telegram Bot, Web Widget — with credential matrix per channel
- **Onboarding & Consent** — first-contact flow, guest accounts, consent gate configuration
- **Observability & Debugging** — events, turn tracing, replay captures, Artisan commands
- **Testing Guide** — using the Pest test toolkit (`Emissary\Testing\`), `FakeLlmClient`, `FakeChannelAdapter`
- **Configuration Reference** — every config key in `config/emissary.php` with defaults, env vars, and security implications
- **Migration Guide** — upgrading between Emissary versions; framework-agnostic migration path
- **API Reference** — generated from docblocks on public interfaces and DTOs

### Out of Scope

- Internal implementation details (pipeline internals, speculative code paths)
- Spec/index docs (`specs/*.md`) — those are for implementers, not end users
- Third-party LLM provider docs (OpenRouter, OpenAI) beyond what Emissary needs
- Host-app business logic (a developer defines their own intents/tools; we don't document example app domains)

## User Scenarios & Testing

### Scenario 1: Junior Developer's First 10 Minutes (Priority: P0)

A Laravel developer new to Emissary wants to add a chatbot to their app. They should:
1. Read a single "Getting Started" page
2. Run `composer require` and an Artisan install command
3. Drop in a web widget
4. See a working "hello" response from the agent
5. Leave with enough context to know what to read next

**Acceptance**: A developer unfamiliar with Emissary completes the Getting Started steps and sees a chatbot respond in under 10 minutes, verified by a timed walk-through.

### Scenario 2: Adding a Custom Tool (Priority: P1)

A mid-level developer needs to add a business tool the agent can call. They should:
1. Navigate to the "Tool Authoring" page
2. Copy a minimal annotated example
3. Understand the `#[Tool]` attribute contract, parameter types, and validation
4. Register the tool provider and verify it appears in `agent:tool:list`
5. Test the tool end-to-end with `FakeLlmClient`

**Acceptance**: A developer writes a working `#[Tool]` method (including parameter schema and error handling) using only the docs, confirmed by a running test.

### Scenario 3: Going Live on Telegram (Priority: P1)

A developer wants to connect a Telegram bot. They should:
1. Navigate to the "Channels → Telegram" page
2. Follow numbered steps (create bot via BotFather, set env vars, set webhook)
3. Run `agent:channel:setup telegram` to validate configuration
4. Send a message from Telegram and see the agent respond

**Acceptance**: A developer gets Telegram working end-to-end in under 30 minutes using only the channel guide.

### Scenario 4: Auditing & Debugging a Production Issue (Priority: P2)

A senior developer investigates an unexpected agent response. They should:
1. Navigate to "Observability & Debugging"
2. Understand the turn-tracing model and event catalog
3. Use Artisan commands to replay a specific turn from fixtures
4. Inspect `AgentEvent` and `ToolInvocation` records to trace the decision path

**Acceptance**: A developer replays a turn and identifies which tool call or guard decision caused the unexpected outcome, confirmed by event log inspection.

### Edge Cases

- **Offline / no API key**: Getting Started detects missing `OPENROUTER_API_KEY` and guides the user to set it, with a link to the configuration page
- **Existing app with conflicting routes**: Docs warn about route prefix collisions and show how to customize via config
- **Channel credentials not yet set up**: Channel guides detect unconfigured credentials and point to the "Channel Setup" prerequisites section
- **Upgrade from an older Emissary version**: Migration guide handles breaking changes between versions with step-by-step instructions

## Functional Requirements

### Documentation Structure

- **FR-01**: The docs SHALL be organized as a hierarchical navigation tree with no more than 3 levels of depth
- **FR-02**: The top-level SHALL be: Getting Started, Core Concepts, Guides (Tools, Guards, Channels), Reference (Config, API), Operations (Observability, Testing, Migration)
- **FR-03**: Every page SHALL open with a 1–2 sentence "what this solves" summary followed by a 30-second TL;DR code snippet

### Getting Started

- **FR-04**: SHALL include a step-by-step installation flow: `composer require` → Artisan command → env vars → verify
- **FR-05**: SHALL include a web widget drop-in snippet that shows without additional configuration
- **FR-06**: SHALL end with a "Where to go next" section linking to the 3 most-used follow-up topics

### Core Concepts

- **FR-07**: SHALL define intents, tools, and guards with both plain-English and code examples
- **FR-08**: SHALL include an architectural diagram showing message flow through channels → guards → routing → agent loop → response
- **FR-09**: SHALL map each concept to the configuration keys that control it

### Tool Authoring

- **FR-10**: SHALL document the `#[Tool]` attribute with all accepted parameters (`name`, `description`, `parameters`)
- **FR-11**: SHALL show annotated examples: a simple tool (no parameters), a tool with scalar parameters, and a tool with array/object parameters
- **FR-12**: SHALL document the contract for `Emissary\Contracts\AgentToolProvider` and the `getToolHandlers()` method
- **FR-13**: SHALL explain parameter validation, error handling, and return shapes

### Guard Authoring

- **FR-14**: SHALL document the 3 guard checkpoints (`beforeIntent`, `beforeExecution`, `afterExecution`) with sequence diagrams
- **FR-15**: SHALL document the `GuardResult` DTO (allow/deny/pending with reason)
- **FR-16**: SHALL document built-in guards: rate-limit, cost-cap, jailbreak, with configuration keys for each

### Channel Setup

- **FR-17**: SHALL provide a dedicated page per channel (WhatsApp, Telegram, Web) with numbered setup steps
- **FR-18**: SHALL document the credential matrix: which env var / config key maps to which channel field
- **FR-19**: SHALL document webhook setup, signature verification, and the `agent:channel:setup` command

### Configuration Reference

- **FR-20**: SHALL document every key in `config/emissary.php` with type, default, env-var override, and security classification
- **FR-21**: SHALL group keys by concern: Models, LLM Gateway, Routing, Intents, Security, Channels, Observability

### Testing Guide

- **FR-22**: SHALL document the `Emissary\Testing\` namespace: `FakeLlmClient`, `FakeChannelAdapter`, `AgentTestCase`
- **FR-23**: SHALL show how to write a Pest test that simulates a complete turn without live LLM or channel calls
- **FR-24**: SHALL show how to replay captured fixtures for regression testing

### Operations

- **FR-25**: SHALL document all Artisan commands with their arguments, options, and example output
- **FR-26**: SHALL document the migration path for Emissary version upgrades, highlighting breaking changes; each supported version SHALL have its own Migration Guide
- **FR-26a**: The docs SHALL support multiple versions (e.g., `latest`, `2.x`, `3.x`) selectable via a version switcher, each with its own complete doc set

### API Reference

- **FR-27**: SHALL generate reference pages from public interface docblocks (contracts, DTOs, attributes)
- **FR-28**: SHALL include inheritance diagrams for the contract hierarchy

### Progressive Disclosure

- **FR-29**: Each topic SHALL start with a "Quick Start" section that shows the minimal code needed, followed by "Deep Dive" sections with full detail
- **FR-30**: Code examples SHALL be copy-pasteable — all imports shown, no placeholders that would cause a syntax error
- **FR-31**: Advanced sections SHALL be clearly marked and collapsible so junior devs aren't overwhelmed

### Accessibility & Navigation

- **FR-32**: Sidebar navigation SHALL show the user's current position with breadcrumbs
- **FR-33**: All pages SHALL include a "Next" link at the bottom guiding linear reading order
- **FR-34**: Search SHALL be available across all documentation pages powered by a client-side search index generated at build time

## Success Criteria

- **SC-01**: A developer who has never seen Emissary completes the Getting Started flow and has a working chatbot in under 10 minutes
- **SC-02**: A developer authors a custom `#[Tool]` method using only the docs in under 15 minutes
- **SC-03**: 100% of `config/emissary.php` keys are documented with type, default, and env-var override
- **SC-04**: 100% of public interfaces (contracts, DTOs, attributes) have API reference pages
- **SC-05**: All code examples in the docs compile (valid PHP 8.3+ syntax) and use real Emissary class/constant names
- **SC-06**: Every "Quick Start" section contains a complete, copy-pasteable code block with no hidden dependencies
- **SC-07**: Navigation depth never exceeds 3 levels from the top
- **SC-08**: A junior developer self-reports confidence to build a simple Emissary integration after reading Getting Started + Core Concepts (validated via user testing with 3+ developers)
- **SC-09**: Search returns relevant results for all concept keywords (intent, tool, guard, channel, pipeline, turn, replay, consent, onboarding)

## Key Entities

| Entity | Description |
|---|---|
| Documentation Site | The generated, hosted output: navigation, search, pages |
| Page | A single markdown file rendered as a documentation page with a unique URL |
| Code Example | A self-contained, copy-pasteable PHP snippet within a page |
| API Reference Page | Auto-generated page from a PHP interface or DTO docblock |
| Quick Start Section | The first major section of each page; minimal code, immediate value |
| Deep Dive Section | Optional expanded section with edge cases, performance notes, security implications |
| Navigation Tree | The hierarchical sidebar structure (max 3 levels) |
| Guides | Task-oriented documentation (Tool Authoring, Guard Authoring, Channel Setup) |
| Reference | Exhaustive, searchable documentation (Config keys, API surface, Artisan commands) |

## Assumptions

- Documentation will be written as a set of markdown files in a `docs/` directory, processed by a static-site generator — the choice of generator is deferred to implementation
- The doc site will be a separate deployment artifact, not bundled inside the Composer package; hosted on GitHub Pages and deployed via GitHub Actions
- Code examples target PHP 8.3+ and use Emissary's actual namespace, classes, and method signatures
- Existing `specs/*.md` files serve as the source of truth for technical accuracy — the docs translate spec into user-facing prose
- The README may be updated as a byproduct (it serves as the package-level entry point on Packagist/GitHub)
- No translation/localization is in scope for this version
- The doc site supports multiple versions matching Emissary release lines (e.g., `latest`, `2.x`, `3.x`), selectable via a version switcher

## Clarifications

### Session 2026-07-01

- Q: When Emissary releases new versions, how should the docs handle versioning? → A: Multi-version (Option B): docs host `latest`, `2.x`, `3.x` each with complete doc set and version switcher.
- Q: What search mechanism should the doc site use? → A: Client-side search index generated at build time (no backend dependency).
- Q: Where should the doc site be hosted? → A: GitHub Pages (deployed via GitHub Actions).

## Cross-References

- Architecture: `specs/01-architecture.md`
- Contracts: `specs/02-contracts.md`
- Pipeline: `specs/03-pipeline.md`
- Configuration: `specs/09-configuration.md`
- Testing: `specs/10-commands-testing.md`
