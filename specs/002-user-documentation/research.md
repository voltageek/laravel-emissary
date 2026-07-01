# Research — User Documentation

> Resolved decisions for Phase 0 unknowns. Date: 2026-07-01

## R1: Static-Site Generator

**Decision**: Jigsaw (`tightenco/jigsaw`)

**Rationale**:
- PHP/Blade-based — zero JavaScript framework knowledge required for content authors
- Laravel-native tooling (uses `illuminate/view` under the hood); matches the host project's ecosystem
- Blade layouts and partials enable DRY page templates (layout, sidebar, breadcrumbs, Next links)
- Build output is plain static HTML — compatible with any static host (GitHub Pages confirmed)
- Actively maintained by Tighten (Laravel community fixture)

**Alternatives considered**:
- Docusaurus: Best versioning support, but React-based — adds JS framework dependency to a PHP project
- VitePress: Minimal config, but Vue-based and manual versioning
- Starlight: Modern Astro-based, but less PHP-ecosystem alignment
- MkDocs + Material: Python toolchain requirement; excellent but wrong ecosystem for a Laravel package

## R2: Client-Side Search

**Decision**: PageFind (`@pagefind/linux-x64`) — SSG-agnostic binary

**Rationale**:
- Zero framework integration — runs as post-build step on any static HTML output
- Indexes full page content including Blade-rendered output (Jigsaw → HTML → PageFind)
- No-build-step search index; binary runs in <1s for ~50 pages
- Supports CJK, multi-language; handles partial matches well
- Works on GitHub Pages (no server-side component)

**Alternatives considered**:
- lunr.js: Requires building index JSON at build time via custom Jigsaw event listener — more complex integration
- Fuse.js: Client-side fuzzy search only; index must be shipped as JSON bundled in JS
- MiniSearch: Similar to lunr.js; needs custom Jigsaw integration
- Algolia DocSearch: External dependency, API key management, free tier has limits

## R3: API Reference Generation

**Decision**: Manual reference pages written from `specs/02-contracts.md`

**Rationale**:
- Emissary's public surface is ~15 interfaces/DTOs/attributes — auto-generation overhead exceeds manual writing benefit at this scale
- Manual pages enable the progressive disclosure pattern: Quick Start summary → Deep Dive with full signatures
- Spec contracts are the canonical source of truth; writing from them ensures accuracy
- Avoids phpDocumentor config, output theme customization, and CI integration complexity

**Alternatives considered**:
- phpDocumentor: Heavy; output styling requires custom templates; CI overhead for a small API surface
- Sami (abandoned): No longer maintained
- Laravel-auto-doc: Generates OpenAPI, not PHP class reference

## R4: Multi-Version Hosting on GitHub Pages

**Decision**: Directory-based versioning (`/{version}/` path prefix)

**Rationale**:
- GitHub Pages serves from a single branch (`gh-pages`); directories map naturally to URL paths
- Jigsaw builds each version to a subdirectory (`build/`, `build/2.x/`)
- Version switcher is a Blade partial that rewrites URL path prefixes
- No DNS changes, no subdomain setup, no branch-per-version complexity
- Initial deployment: `latest` at `/`; future: build matrix supports `2.x/`, `3.x/`

**Implementation**:
- Jigsaw `config.php` sets `baseUrl` per build target
- Version manifest (`versions.json`) lists available versions, labels, and paths
- Version switcher reads manifest and rewrites `window.location.pathname`

## R5: Architecture & Concept Diagrams

**Decision**: Mermaid — inline in markdown (included via Blade partial)

**Rationale**:
- Jigsaw supports markdown files (`.blade.md`) which can include Mermaid blocks
- Mermaid renders to SVG client-side via `mermaid.js` CDN include
- Source-controllable (diagrams are code, reviewed in PRs)
- Sufficient for pipeline flow diagrams and checkpoint sequence diagrams
- No external drawing tool or image asset management needed

**Alternatives considered**:
- Excalidraw/Manual SVGs: Binary assets in repo; not difffable; require external tool to edit
- PlantUML: Requires server-side rendering or local Java installation

## R6: Build & Deploy Pipeline

**Decision**: GitHub Actions workflow → `gh-pages` branch

**Rationale**:
- Single workflow: checkout → composer install → jigsaw build → pagefind index → deploy
- `peaceiris/actions-gh-pages@v3` action handles push to `gh-pages`
- Runs on push to `main` (and `002-user-documentation` during development)
- Multi-version builds iterate over version manifest and run Jigsaw with per-version config

**Workflow steps**:
1. Checkout repo
2. Setup PHP 8.3 + Composer
3. `composer install` (for Jigsaw + Emissary autoload)
4. For each version in manifest: `php docs/bin/console build production --base-url=/{version}`
5. `npx @pagefind/linux-x64 --site docs/build`
6. Deploy `docs/build/` to `gh-pages` branch
