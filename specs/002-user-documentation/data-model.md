# Data Model — Docs Directory Structure

> Maps FR-01 / FR-02 navigation tree to the Jigsaw `source/` directory layout.

## Navigation Tree (3 levels max)

Per FR-01 and FR-02:

| Level 1 | Level 2 | Level 3 | File |
|---|---|---|---|
| Getting Started | — | — | `source/index.blade.php` (also serves as home) |
| Core Concepts | Overview | — | `source/concepts/index.blade.php` |
| | Intents | — | `source/concepts/intents.blade.php` |
| | Tools | — | `source/concepts/tools.blade.php` |
| | Guards | — | `source/concepts/guards.blade.php` |
| | Pipeline | — | `source/concepts/pipeline.blade.php` (architecture diagram) |
| Guides | Tool Authoring | — | `source/guides/tool-authoring.blade.php` |
| | Guard Authoring | — | `source/guides/guard-authoring.blade.php` |
| | Channels | WhatsApp | `source/guides/channels/whatsapp.blade.php` |
| | | Telegram | `source/guides/channels/telegram.blade.php` |
| | | Web Widget | `source/guides/channels/web.blade.php` |
| | Onboarding & Consent | — | `source/guides/onboarding.blade.php` |
| Reference | Configuration | — | `source/reference/config.blade.php` |
| | API | Contracts | `source/reference/api/contracts.blade.php` |
| | | DTOs | `source/reference/api/dtos.blade.php` |
| | | Attributes | `source/reference/api/attributes.blade.php` |
| | Artisan Commands | — | `source/reference/commands.blade.php` |
| Operations | Observability & Debugging | — | `source/operations/observability.blade.php` |
| | Testing | — | `source/operations/testing.blade.php` |
| | Migration | — | `source/operations/migration.blade.php` |

## Directory Layout

```
docs/
├── config.php              # Jigsaw site config (baseUrl, collections, navigation)
├── bootstrap.php           # Event listeners, view composers
├── composer.json           # Jigsaw + pagefind dependencies
├── webpack.mix.js          # (optional) asset compilation
├── source/                 # Content source
│   ├── _assets/            # CSS, JS, images
│   │   ├── css/
│   │   │   └── main.css
│   │   └── js/
│   │       └── search.js   # PageFind UI integration
│   ├── _layouts/           # Blade layouts
│   │   └── master.blade.php
│   ├── _partials/          # Blade partials
│   │   ├── sidebar.blade.php
│   │   ├── breadcrumbs.blade.php
│   │   ├── next-link.blade.php
│   │   └── version-switcher.blade.php
│   ├── index.blade.php     # Getting Started (home page)
│   ├── concepts/
│   │   ├── index.blade.php
│   │   ├── intents.blade.php
│   │   ├── tools.blade.php
│   │   ├── guards.blade.php
│   │   └── pipeline.blade.php
│   ├── guides/
│   │   ├── tool-authoring.blade.php
│   │   ├── guard-authoring.blade.php
│   │   ├── channels/
│   │   │   ├── whatsapp.blade.php
│   │   │   ├── telegram.blade.php
│   │   │   └── web.blade.php
│   │   └── onboarding.blade.php
│   ├── reference/
│   │   ├── config.blade.php
│   │   ├── commands.blade.php
│   │   └── api/
│   │       ├── contracts.blade.php
│   │       ├── dtos.blade.php
│   │       └── attributes.blade.php
│   └── operations/
│       ├── observability.blade.php
│       ├── testing.blade.php
│       └── migration.blade.php
├── build/                  # Static output (gitignored, rebuilt by CI)
│   ├── index.html
│   ├── pagefind/           # PageFind search index
│   ├── concepts/
│   └── ...
└── versions.json           # Version manifest for switcher
```

## Page Template (per FR-03, FR-29)

Every content page follows this structure:

```blade
---
title: "Page Title"
description: "1–2 sentence what-this-solves summary"
---

{{-- TL;DR (FR-03) --}}
<x-tldr>
<!-- 30-second copy-pasteable code snippet -->
</x-tldr>

{{-- Quick Start (FR-29) --}}
## Quick Start

Minimal working example with full imports.

{{-- Deep Dive (FR-29, FR-31) --}}
<x-deep-dive>
## Deep Dive

Full detail, edge cases, security notes, performance considerations.
</x-deep-dive>

{{-- Next Link (FR-33) --}}
@include('_partials.next-link', ['next' => 'page-name'])
```

## Versioning

Multi-version (FR-26a) implemented as separate Jigsaw builds with `--base-url`:

```bash
# Build latest (deploys to /)
php docs/bin/console build production

# Build 2.x (deploys to /2.x/)
php docs/bin/console build production --base-url=/2.x

# Version manifest used by CI and version switcher
# docs/versions.json
{
  "versions": [
    {"label": "latest", "path": "/", "default": true},
    {"label": "2.x", "path": "/2.x/"}
  ]
}
```
