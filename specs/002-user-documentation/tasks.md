# User Documentation — Tasks

> Sprint/Iteration: 1 · Date: 2026-07-01 · Branch: `002-user-documentation`

## Task Organization

Tasks are organized by user story from `spec.md` with infrastructure-first build order from `plan.md`.

| User Story | Priority | Phase |
|---|---|---|
| US1: Junior Developer's First 10 Minutes | P0 | Phase 3 |
| US2: Adding a Custom Tool | P1 | Phase 4 |
| US3: Going Live on Telegram | P1 | Phase 5 |
| US4: Auditing & Debugging a Production Issue | P2 | Phase 6 |

Supporting content (Core Concepts, Guard Authoring, Onboarding, API Ref, Config Ref, Testing, Migration, Commands) is in Phase 7 since it enables all stories. Progressive disclosure audit is Phase 8.

---

## Phase 1: Setup (Jigsaw Project Initialization)

> **Goal**: Scaffold the Jigsaw documentation site project. `bin/console build` succeeds with an empty site.

- [x] T001 Create `docs/composer.json` with Jigsaw dependency (`tightenco/jigsaw: ^1.7`) and autoload config for `Emissary\\` namespace via `../../vendor/autoload.php`
- [x] T002 Run `composer install` in `docs/` to install Jigsaw
- [x] T003 Create `docs/config.php` with site metadata (`baseUrl: ""`, `production: false`, `collections: []`)
- [x] T004 Create `docs/bootstrap.php` with event listener stubs (empty for now, expanded in Phase 2)
- [x] T005 [P] Add `docs/build/` to `.gitignore`
- [x] T006 Verify `php docs/vendor/bin/jigsaw build` produces static output in `docs/build/`

---

## Phase 2: Foundational (Layouts, Navigation, Search, Deploy)

> **Goal**: Complete site infrastructure. All Blade layouts, partials, navigation tree, PageFind search, version switcher, and GitHub Actions deploy pipeline are functional. A stub home page builds and deploys. **Blocks all subsequent phases.**

- [x] T007 Create `docs/source/_layouts/master.blade.php` with HTML shell, meta tags, CSS/JS includes, sidebar + content + breadcrumbs layout grid per data-model.md
- [x] T008 [P] Create `docs/source/_assets/css/main.css` with sidebar styling, collapsible sections, code block highlighting, version switcher styling
- [x] T009 [P] Create `docs/source/_assets/js/search.js` with PageFind UI integration (search input, results dropdown, keyboard nav)
- [x] T010 Create `docs/source/_partials/sidebar.blade.php` rendering navigation tree from `config.php → navigation` with active page highlighting, collapsible Level 2 sections, 3-level max depth per FR-01
- [x] T011 [P] Create `docs/source/_partials/breadcrumbs.blade.php` showing path from root to current page with `>` separator per breadcrumbs contract
- [x] T012 [P] Create `docs/source/_partials/next-link.blade.php` with next page title + URL; on last page of section, link to next section; on last page of site, show "View source on GitHub →" per next-link contract
- [x] T013 [P] Create `docs/source/_partials/version-switcher.blade.php` reading `versions.json`, rendering dropdown, JS to rewrite URL path prefix per research.md R4
- [x] T014 Define full navigation tree in `docs/config.php` matching data-model.md: Getting Started, Core Concepts (5 pages), Guides (6 pages), Reference (5 pages), Operations (3 pages) per FR-01/FR-02
- [x] T015 Add mermaid.js CDN include to `docs/source/_layouts/master.blade.php` for diagram rendering per research.md R5
- [x] T016 Create `docs/versions.json` with `latest` (path: `/`, default: true) and `2.x` (path: `/2.x/`) per research.md R4
- [x] T017 Configure PageFind post-build index generation: add `npx @pagefind/linux-x64 --site docs/build` step and UI include in `docs/source/_layouts/master.blade.php` per research.md R2
- [x] T018 Create `.github/workflows/deploy-docs.yml` with steps: checkout → setup PHP 8.3 + Composer → `composer install` (root + docs) → for each version in versions.json: `php docs/vendor/bin/jigsaw build production --base-url=/{version}` → PageFind index → deploy `docs/build/` to `gh-pages` branch via `peaceiris/actions-gh-pages@v3` per research.md R6
- [x] T019 Create stub `docs/source/index.blade.php` with title + description frontmatter and placeholder content; verify `php docs/vendor/bin/jigsaw build` succeeds with full navigation, sidebar, breadcrumbs, version switcher visible
- [x] T020 Verify PageFind search works locally: `npx @pagefind/linux-x64 --site docs/build` and `php -S localhost:8080 -t docs/build/` shows searchable stub page

---

## Phase 3: US1 — Junior Developer's First 10 Minutes (P0)

> **Goal**: A developer new to Emissary reads Getting Started and has a working chatbot in under 10 minutes. FR-04, FR-05, FR-06 satisfied. SC-01 verifiable.

**Independent Test**: Timed walk-through — compose require → install command → web widget → hello response from agent.

- [x] T021 [US1] Write `docs/source/index.blade.php` (Getting Started) with TL;DR snippet, step-by-step install flow: `composer require voltageek/laravel-emissary` → `php artisan emissary:install` → `OPENROUTER_API_KEY` env var → `php artisan serve` verify per FR-04
- [x] T022 [US1] Add web widget drop-in snippet to Getting Started page — Blade directive or `<script>` embed that shows without additional config per FR-05
- [x] T023 [US1] Add "Where to go next" section at bottom of Getting Started linking to Core Concepts, Tool Authoring, Channel Setup per FR-06
- [x] T024 [US1] Add edge case callouts to Getting Started: missing `OPENROUTER_API_KEY` → link to Config Reference; conflicting routes → link to route customization in Config Reference per edge cases

---

## Phase 4: US2 — Adding a Custom Tool (P1)

> **Goal**: A developer writes a working `#[Tool]` method using only the docs in under 15 minutes. FR-10, FR-11, FR-12, FR-13 satisfied. SC-02 verifiable.

**Independent Test**: Developer copies examples and produces a working tool with parameter schema and error handling, verified by a running Pest test with `FakeLlmClient`.

- [x] T025 [US2] Write `docs/source/guides/tool-authoring.blade.php` with TL;DR: minimal `#[Tool]` attribute example; Quick Start: simple no-parameter tool; Deep Dive: scalar parameter tool + array/object parameter tool per FR-10, FR-11
- [x] T026 [US2] Document `Emissary\Contracts\AgentToolProvider` interface and `getToolHandlers()` method in Tool Authoring Deep Dive per FR-12
- [x] T027 [US2] Document parameter validation rules, error handling patterns, and return shapes (string, DTO, array) in Tool Authoring Deep Dive per FR-13

---

## Phase 5: US3 — Going Live on Telegram (P1)

> **Goal**: A developer gets Telegram working end-to-end in under 30 minutes. FR-17, FR-18, FR-19 satisfied. SC verifiable (30-min walk-through).

**Independent Test**: Developer follows Telegram guide, sets up bot via BotFather, sets env vars, runs `agent:channel:setup telegram`, sends a message, sees agent respond.

- [x] T028 [US3] Write `docs/source/guides/channels/telegram.blade.php` with numbered steps: create bot via BotFather → set `TELEGRAM_BOT_TOKEN` env → set webhook URL → run `agent:channel:setup telegram` → send test message per FR-17
- [x] T029 [US3] Document credential matrix per channel in Telegram guide: `TELEGRAM_BOT_TOKEN` → bot token, `TELEGRAM_WEBHOOK_SECRET` → webhook validation, with env var + config key mapping per FR-18
- [x] T030 [US3] Document webhook setup, signature verification, and `agent:channel:setup` command in Telegram guide per FR-19
- [x] T031 [P] [US3] Write `docs/source/guides/channels/whatsapp.blade.php` with WhatsApp Business API steps: business account setup → webhook verify token → phone number ID → run `agent:channel:setup whatsapp` per FR-17
- [x] T032 [P] [US3] Write `docs/source/guides/channels/web.blade.php` with web widget setup: Blade directive → CSRF config → route registration → widget customization per FR-17
- [x] T033 [US3] Add edge case callouts to all channel guides: unconfigured credentials → link to Channel Setup prerequisites; webhook URL conflicts → link to Config Reference per edge cases

---

## Phase 6: US4 — Auditing & Debugging a Production Issue (P2)

> **Goal**: A senior developer replays a turn and identifies which tool call or guard decision caused an unexpected outcome. FR-25 satisfied.

**Independent Test**: Developer replays a turn from fixtures using Artisan commands and inspects `AgentEvent` + `ToolInvocation` records to trace the decision path.

- [x] T034 [US4] Write `docs/source/operations/observability.blade.php` with TL;DR, Quick Start: turn-tracing model explanation using Mermaid diagram, Deep Dive: event catalog (from `specs/05-observability.md`), listener configuration, replay capture setup per FR-25
- [x] T035 [US4] Document Artisan commands in Observability page: `emissary:replay` (replay a turn from fixtures), `emissary:report` (cost/usage report), `emissary:prune` (cleanup old data) with arguments, options, and example output per FR-25

---

## Phase 7: Supporting Content

> **Goal**: All remaining documentation pages written. Covers FR-07–FR-09, FR-14–FR-16, FR-20–FR-24, FR-26–FR-28. Pages can be written in parallel (different files, no inter-dependencies).

- [x] T036 [P] Write `docs/source/concepts/index.blade.php` (Core Concepts Overview) with plain-English + code definition of intents, tools, guards; Mermaid architecture diagram showing message flow through channels → guards → routing → agent loop → response per FR-07, FR-08
- [x] T037 [P] Write `docs/source/concepts/intents.blade.php` with TL;DR, Quick Start, and Deep Dive per FR-07, FR-09
- [x] T038 [P] Write `docs/source/concepts/tools.blade.php` with TL;DR, Quick Start, and Deep Dive per FR-07, FR-09
- [x] T039 [P] Write `docs/source/concepts/guards.blade.php` with TL;DR, Quick Start, and Deep Dive per FR-07, FR-09
- [x] T040 [P] Write `docs/source/concepts/pipeline.blade.php` with Mermaid pipeline flow diagram and config key mapping per FR-08, FR-09
- [x] T041 [P] Write `docs/source/guides/guard-authoring.blade.php` with 3 guard checkpoints (`beforeIntent`, `beforeExecution`, `afterExecution`) Mermaid sequence diagram, `GuardResult` DTO docs, built-in guards (rate-limit, cost-cap, jailbreak) with config keys per FR-14, FR-15, FR-16
- [x] T042 [P] Write `docs/source/guides/onboarding.blade.php` with first-contact flow, guest account creation, consent gate configuration per `specs/08-user-onboarding.md`
- [x] T043 [P] Write `docs/source/reference/config.blade.php` documenting every key in `config/emissary.php` with type, default value, env-var override, security classification, grouped by concern (Models, LLM Gateway, Routing, Intents, Security, Channels, Observability) per FR-20, FR-21
- [x] T044 [P] Write `docs/source/reference/api/contracts.blade.php` from `specs/02-contracts.md` — all public interfaces with method signatures per FR-27
- [x] T045 [P] Write `docs/source/reference/api/dtos.blade.php` from `specs/02-contracts.md` — all DTOs with properties and types per FR-27
- [x] T046 [P] Write `docs/source/reference/api/attributes.blade.php` from `specs/02-contracts.md` — `#[Tool]` attribute with accepted parameters per FR-27
- [x] T047 [P] Write `docs/source/reference/commands.blade.php` documenting all Artisan commands (`emissary:report`, `emissary:replay`, `emissary:prune`, `emissary:channels:list`, `emissary:channel:setup`, `emissary:channel:test`, `emissary:webhook:url`, `emissary:set-telegram-webhook`, `emissary:onboarding:status`, `emissary:onboarding:reset`, `emissary:fixture:capture`) with arguments, options, example output per FR-25
- [x] T048 [P] Write `docs/source/operations/testing.blade.php` documenting `Emissary\Testing\` namespace (`FakeLlmClient`, `FakeChannelAdapter`, `AgentTestCase`), Pest test that simulates a complete turn without live LLM/channel, replay captured fixtures for regression testing per FR-22, FR-23, FR-24
- [x] T049 [P] Write `docs/source/operations/migration.blade.php` documenting upgrade path between Emissary versions with breaking changes per FR-26
- [x] T050 [P] Write `docs/source/reference/api/inheritance.blade.php` with contract hierarchy inheritance diagram (Mermaid class diagram) per FR-28

---

## Phase 8: Polish & Cross-Cutting

> **Goal**: Every page follows progressive disclosure pattern, all code examples are valid PHP 8.3+, site builds cleanly, search returns results for all concept keywords.

- [x] T051 Audit all content pages for Quick Start → Deep Dive pattern (FR-29): verify TL;DR present, Quick Start section, Deep Dive section with collapsible toggle
- [x] T052 Audit all code examples for copy-pasteability (FR-30): full imports shown, no `// ...` placeholders, no hidden dependencies
- [x] T053 Verify advanced sections are clearly marked and collapsible (FR-31) across all pages
- [x] T054 Extract all PHP code blocks from `docs/source/` pages and run `php -l` lint sweep; fix any syntax errors
- [x] T055 Build site (`php docs/vendor/bin/jigsaw build production`) and verify zero build errors
- [x] T056 Run PageFind index (`npx @pagefind/linux-x64 --site docs/build`) and verify search returns results for all 9 concept keywords in SC-09: intent, tool, guard, channel, pipeline, turn, replay, consent, onboarding
- [x] T057 Verify all internal links resolve (no 404s) by crawling `docs/build/`
- [x] T058 Verify navigation tree depth never exceeds 3 levels per SC-07
- [x] T059 Verify 100% of `config/emissary.php` keys are documented in `docs/source/reference/config.blade.php` per SC-03
- [x] T060 Verify 100% of public interfaces/contracts/DTOs/attributes from `specs/02-contracts.md` are covered in API reference pages per SC-04

---

## Dependencies

```
Phase 1 (Setup)
    ↓
Phase 2 (Foundational) ← BLOCKS all content phases
    ↓
    ├── Phase 3 (US1: Getting Started) ← P0, no deps on other content
    ├── Phase 4 (US2: Tool Authoring) ← P1, independent
    ├── Phase 5 (US3: Channel Setup) ← P1, independent
    ├── Phase 6 (US4: Observability) ← P2, independent
    └── Phase 7 (Supporting Content) ← all tasks [P], can parallelize
               ↓
          Phase 8 (Polish) ← depends on all content phases complete
```

## Parallel Execution Examples

**Phase 7 (Supporting Content)** — all 15 tasks are parallelizable:

```bash
# Batch 1: Concepts
T036 & T037 & T038 & T039 & T040

# Batch 2: Guides + Reference (run alongside Batch 1)
T041 & T042 & T043 & T044 & T045

# Batch 3: Operations (run alongside Batch 1+2)
T046 & T047 & T048 & T049 & T050
```

**Phase 5 (Channels)** — channel pages are parallel:

```bash
T028 (Telegram) & T031 (WhatsApp) & T032 (Web)  # can run simultaneously
T029 & T033  # depends on T028 complete
```

## Implementation Strategy

### MVP Scope (Phase 1–3)

Deliver `composer require` → install → working chatbot in Getting Started:

1. Phase 1: Scaffold Jigsaw project
2. Phase 2: Layout, navigation, search, deploy pipeline
3. Phase 3: Getting Started page → **deploy to GitHub Pages**

**MVP acceptance**: External developer reads Getting Started and gets a chatbot running.

### Incremental Delivery

- **Sprint 1**: Phase 1 + 2 + 3 (MVP) → ship
- **Sprint 2**: Phase 4 + 5 + 6 (US2, US3, US4) → ship
- **Sprint 3**: Phase 7 (Supporting Content) → ship
- **Sprint 4**: Phase 8 (Polish + audit) → ship

## Verification

```bash
# Jigsaw build
cd docs && php vendor/bin/jigsaw build production

# PageFind search index
npx @pagefind/linux-x64 --site docs/build

# Local preview
php -S localhost:8080 -t docs/build/

# PHP syntax check on all code blocks from docs
find docs/source -name "*.blade.php" -exec grep -l '<?php' {} \; | while read f; do
  php -l "$f" || echo "FAIL: $f"
done

# Link check (after build)
find docs/build -name "*.html" -exec grep -oP 'href="[^"]*"' {} \; | sort -u
```
