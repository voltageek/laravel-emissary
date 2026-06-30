# AGENTS.md — implementing Emissary

> Read this first. It frames every other file in `specs/` for an AI coding agent doing the implementation.

## Larger task (the "why")
**Emissary** is a **standalone, reusable PHP library** (PHP namespace `Emissary\`, Composer package `voltageek/laravel-emissary`) that adds agentic, multi-channel conversational capability to **existing Laravel applications**. The intended user of these specs is **you, the implementing agent**. Host-app developers integrate by defining three things — **intents, tools, guards** — and the library handles routing, LLM calls, tool execution, memory, confirmation gates, and channel delivery.

The output that matters: a Composer package a Laravel app can `composer require`, publish a config + migrations, register a plugin, and have WhatsApp/Telegram/Web chat working — without touching their existing service layer.

## Non-goals (do not build these)
- Not a new framework. Compose on Laravel, target framework-agnostic later (see `specs/11-decisions.md` → Migration Path).
- Not a model-training or fine-tuning tool. Inference is delegated to OpenRouter / any OpenAI-compatible API.
- Not a UI framework. Web chat is a drop-in widget; channels are adapters.
- Do not invent features beyond the spec. If a behaviour is unspecified, flag it and ask rather than guess — ambiguity is resolved upstream (see "Handling ambiguity").

## How to navigate the spec
Start at **`specs/README.md`** (the index). It maps every concept to a file. Files are small and self-contained by design — load the one relevant to your task rather than the whole set.
- `01-architecture.md` — pipeline shape + data flow.
- `02-contracts.md` — all interfaces, DTOs, error taxonomy.
- `03-pipeline.md` — component behaviour + the end-to-end data flow.
- `04-data-models.md` — every table.
- `05-observability.md` — events, listeners, export hook.
- `06-security.md` — threat model.
- `07-channels.md` — getting a channel live.
- `08-user-onboarding.md` — first-contact journey.
- `09-configuration.md` — `config/agent.php`, bindings, registration.
- `10-commands-testing.md` — Artisan commands + the test toolkit.
- `11-decisions.md` — the 17 design decisions + dependencies + migration path.

## Conventions (follow exactly)
- **PHP 8.3+**: readonly classes, named arguments, match expressions, constructor property promotion.
- **Namespace**: all code lives under `Emissary\` (e.g., `Emissary\Contracts\AgentToolProvider`, `Emissary\Testing\FakeLlmClient`). The internal `Agent*` class prefixes are kept (they name the concept, not the product).
- **Tool definitions use the `#[Tool]` attribute** on provider methods — never a parallel handler map (see `02-contracts.md` → Attribute-Driven Tools).
- **Tests are Pest-first**, in the `Emissary\Testing\` namespace, importable by host apps (mirrors `Illuminate\Testing`). See `10-commands-testing.md`.
- **No comments in code unless explicitly requested.** Docblocks on public interfaces are allowed and expected (they appear in the spec).
- **Naming**: the spec is the source of truth for class/method/constant names — copy them verbatim; do not "improve" them.
- **Security defaults are on**; opting out is explicit per-control in config (`06-security.md`).
- **PII / secrets asymmetry**: channel credentials are encrypted at rest; conversation content is not. Do not invert these defaults.

## Handling ambiguity (EARS)
Behavioural requirements are written as **EARS** ("WHEN/IF ⟨trigger⟩ THE SYSTEM SHALL ⟨response⟩") with acceptance criteria in the high-risk files (`02`, `03`, `08`). If you encounter a behavioural question not covered by an EARS rule:
1. Do not invent behaviour.
2. State the ambiguity and the options, then stop and ask.

## Definition of done (per component)
A component is complete when **all** hold:
1. Its interface/behaviour matches `02-contracts.md` / `03-pipeline.md` verbatim (names, signatures, return types).
2. It emits the events specified in `05-observability.md` with `turn_id` propagation.
3. The security controls for it (`06-security.md`) are wired and on by default.
4. It is covered by the tests described in `10-commands-testing.md` using the fakes (never a live LLM or live channel).
5. `php artisan test` (Pest) is green for the new cases.

## Verify commands
```bash
composer test           # Pest, the whole suite
composer test -- --filter=ToolScanner   # one area
vendor/bin/pest tests/Unit/ToolScannerTest.php
```
Where a spec file lists an acceptance criterion, the matching Pest test name should exist and pass before that item is "done".

## What is never done in code
- Live LLM calls in tests (use `FakeLlmClient`).
- Live webhook/Send API calls in tests (use `FakeChannelAdapter`).
- Reading the wall clock directly (inject the `Clock`, fake it in tests).
