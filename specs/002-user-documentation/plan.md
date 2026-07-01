# User Documentation — Implementation Plan

> Plan version: 1.0 · Target spec version: 1.0 · Date: 2026-07-01

## Summary

Build a multi-version documentation site for the Emissary Composer package using **Jigsaw** (PHP/Blade, Laravel-native static-site generator), **PageFind** (client-side search index), and **GitHub Pages** (hosting + GitHub Actions deploy). Deliver infrastructure-first: scaffold the Jigsaw project, configure deploy pipeline, search, and version switcher — then write all documentation pages following the progressive disclosure pattern (Quick Start → Deep Dive) targeting Laravel developers from junior to senior.

## Constitution Check

This feature is a documentation deliverable, not library code. Only applicable principles are evaluated:

- [ ] **Principle 1 — Spec-Driven**: Docs structure, page outlines, and code examples must match spec FRs verbatim. Page content accuracy is verified against `specs/02-contracts.md`, `specs/03-pipeline.md`, and `specs/09-configuration.md`.
- [ ] **Principle 10 — PHP 8.3+ Conventions**: All code examples in docs must use valid PHP 8.3+ syntax with readonly classes, named arguments, match expressions, and `Emissary\` namespace.

Principles 2–9 (security controls, test fakes, observability events, tool surface, guards, config-driven behavior, channel agnosticism, error taxonomy) apply to the library, not to documentation authoring. No gate violations.

## Scope

### In Scope

- Jigsaw project scaffolding with Blade layouts, navigation, search, version switcher
- GitHub Actions workflow for build + deploy to GitHub Pages
- PageFind search index generation as post-build step
- Multi-version directory structure (`/`, `/2.x/`)
- 11 doc areas covering 34 functional requirements (see Components below)
- API reference pages generated from `specs/02-contracts.md` contracts
- Config reference page generated from `config/emissary.php`

### Out of Scope

- Writing Emissary library code (only docs)
- Auto-generated API reference from live docblocks (manual via spec)
- i18n / localization
- Blog, changelog, or non-docs pages
- Algolia or other external search services
- Animated diagrams (Mermaid static is sufficient)

## Components

| Component | FRs Covered | Deliverables |
|---|---|---|
| **A. Jigsaw scaffolding** | FR-01, FR-02, FR-32, FR-33, FR-34, FR-26a | `docs/` directory: `config.php`, `bootstrap.php`, Blade layouts, `navigation.php`, PageFind integration, version switcher UI, GitHub Actions `.github/workflows/deploy-docs.yml` |
| **B. Getting Started** | FR-04, FR-05, FR-06 | `source/getting-started.blade.php` |
| **C. Core Concepts** | FR-07, FR-08, FR-09 | `source/concepts/intents.blade.php`, `tools.blade.php`, `guards.blade.php`, `pipeline.blade.php` (architecture diagram) |
| **D. Tool Authoring Guide** | FR-10, FR-11, FR-12, FR-13 | `source/guides/tool-authoring.blade.php` |
| **E. Guard Authoring Guide** | FR-14, FR-15, FR-16 | `source/guides/guard-authoring.blade.php` |
| **F. Channel Setup Guides** | FR-17, FR-18, FR-19 | `source/guides/channels/whatsapp.blade.php`, `telegram.blade.php`, `web.blade.php` |
| **G. Onboarding & Consent** | (scope) | `source/guides/onboarding.blade.php` |
| **H. Configuration Reference** | FR-20, FR-21 | `source/reference/config.blade.php` (generated from `config/emissary.php`) |
| **I. API Reference** | FR-27, FR-28 | `source/reference/api/contracts.blade.php`, `dtos.blade.php`, `attributes.blade.php` |
| **J. Testing Guide** | FR-22, FR-23, FR-24 | `source/operations/testing.blade.php` |
| **K. Observability & Debugging** | FR-25 | `source/operations/observability.blade.php` |
| **L. Migration Guide** | FR-26 | `source/operations/migration.blade.php` |
| **M. Progressive Disclosure** | FR-29, FR-30, FR-31 | Applied across all pages: Quick Start sections first, Deep Dive sections collapsible |

## Build Order

### Phase 0: Infrastructure (Components A)

1. Initialize Jigsaw project in `docs/`
2. Configure Blade layout with sidebar, breadcrumbs, Next links
3. Define navigation tree matching FR-01/FR-02
4. Integrate PageFind as post-build step
5. Add version switcher UI (directory-based: `latest`, `2.x`)
6. Create GitHub Actions workflow for build + deploy to `gh-pages` branch
7. Verify: `bin/console build` succeeds, serve locally, search works, version switcher navigates

### Phase 1: Reference (Components H, I)

8. Generate config reference from `config/emissary.php`
9. Write API reference pages from `specs/02-contracts.md`

### Phase 2: Getting Started + Core Concepts (Components B, C)

10. Write Getting Started page (FR-04, FR-05, FR-06)
11. Write Core Concepts pages with architecture diagram (FR-07, FR-08, FR-09)

### Phase 3: Guides (Components D, E, F, G)

12. Write Tool Authoring Guide (FR-10–FR-13)
13. Write Guard Authoring Guide (FR-14–FR-16)
14. Write Channel Setup Guides (FR-17–FR-19)
15. Write Onboarding & Consent Guide

### Phase 4: Operations (Components J, K, L)

16. Write Testing Guide (FR-22–FR-24)
17. Write Observability & Debugging Guide (FR-25)
18. Write Migration Guide (FR-26)

### Phase 5: Progressive Disclosure Audit (Component M)

19. Verify every page has Quick Start + Deep Dive structure (FR-29)
20. Verify all code examples are copy-pasteable with full imports (FR-30)
21. Verify advanced sections are clearly marked (FR-31)

## Dependencies

| Dependency | Used For | Source |
|---|---|---|
| `specs/02-contracts.md` | API reference: all interfaces, DTOs, `AgentError` codes | Repo |
| `specs/03-pipeline.md` | Core concepts: pipeline stages, component behaviour | Repo |
| `specs/05-observability.md` | Observability: events, listeners, export hook | Repo |
| `specs/06-security.md` | Config reference: security implications per key; Guard guide: attack surfaces | Repo |
| `specs/07-channels.md` | Channel guides: credential matrix, webhook routes, setup commands | Repo |
| `specs/08-user-onboarding.md` | Onboarding guide: first-contact flow, consent gate, guest creation | Repo |
| `specs/09-configuration.md` | Config reference: every key with type, default, env var | Repo |
| `specs/10-commands-testing.md` | Testing guide: `Emissary\Testing\` namespace, Artisan commands | Repo |
| `config/emissary.php` | Config reference source | Repo |
| `composer.json` | Package name, namespace, requirements | Repo |
| `tightenco/jigsaw` (`^1.7`) | Static-site generator | Composer |
| `@pagefind/linux-x64` | Client-side search index generation | npm (binary) |

## Risks & Mitigations

| Risk | Likelihood | Impact | Mitigation |
|---|---|---|---|
| PageFind doesn't index Blade-generated content correctly | Low | Medium | Verify with `bin/console build` → `npx pagefind --site build` in local test before finalizing |
| Version switcher redirects broken on GitHub Pages | Low | Medium | GitHub Pages serves from subdirectories cleanly; test with `--base-url` Jigsaw config |
| Jigsaw Blade syntax differs from Laravel Blade | Low | Low | Jigsaw uses standard Blade; only difference is `@yield`/`@section` in layout contexts |
| Code examples become stale as library evolves | Medium | Medium | Add CI lint step that extracts PHP blocks from docs and runs `php -l` |
| Spec contracts change after docs pages written | Low | Medium | Each doc page references spec version it was written from; migration guide covers sync |

## Definition of Done

Adapted from AGENTS.md for a documentation deliverable:

1. All 34 functional requirements (FR-01 through FR-34) are satisfied with at least one docs page per requirement
2. Jigsaw builds without errors; all internal and cross-version links resolve
3. GitHub Pages deploys successfully on push to main
4. PageFind search returns results for all 9 concept keywords in SC-09
5. Version switcher navigates between `latest` and `2.x` directories
6. Every page follows Quick Start → Deep Dive pattern (FR-29)
7. All code examples parse as valid PHP 8.3+ (verified via `php -l` lint sweep of extracted code blocks)
8. All 9 success criteria from spec are verifiable
9. Config reference covers 100% of `config/emissary.php` keys (SC-03)
10. API reference covers 100% of public interfaces/contracts/DTOs/attributes (SC-04)
