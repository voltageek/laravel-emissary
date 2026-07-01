# Contracts — Page Structure & Navigation

> The contract every documentation page must fulfill. Derived from FR-03, FR-29, FR-30, FR-31, FR-32, FR-33.

## Page Structure Contract

Every content page in `docs/source/` MUST conform to:

```yaml
page:
  frontmatter:
    title: string          # H1 heading, shown in sidebar and <title>
    description: string    # 1-2 sentence summary (FR-03)
    weight: integer        # Sort order within parent section

  sections:
    tldr:                  # FR-03: 30-second code snippet
      type: code_block
      language: php
      copyable: true       # FR-30: no hidden imports, no placeholders
      max_lines: 15

    quick_start:           # FR-29: minimal working example
      type: prose + code
      imports: all_shown   # FR-30
      dependencies: []     # FR-30: no hidden deps

    deep_dive:             # FR-29, FR-31: full detail
      type: prose + code
      collapsible: true    # FR-31
      sections:
        - edge_cases
        - performance
        - security
        - configuration_impact

  navigation:
    breadcrumbs: true      # FR-32
    sidebar_position: highlighted
    next_page:             # FR-33
      title: string
      url: string

  code_examples:
    php_version: "8.3+"    # Constitution P10
    namespace: "Emissary\\"
    valid_syntax: true     # SC-05
```

## Navigation Sidebar Contract

The sidebar partial (`_partials/sidebar.blade.php`) MUST:

1. Render the full navigation tree defined in `config.php → navigation`
2. Highlight the current page's position (FR-32)
3. Show collapsible sections for Level 2 headings
4. Never exceed 3 levels of depth (FR-01)
5. Include the version switcher component above the tree (FR-26a)

## Breadcrumbs Contract

The breadcrumbs partial (`_partials/breadcrumbs.blade.php`) MUST:

1. Show the full path from root to current page (FR-32)
2. Link each ancestor segment
3. Use `>` as separator (e.g., `Guides > Channels > WhatsApp`)

## Next Link Contract

The next-link partial (`_partials/next-link.blade.php`) MUST:

1. Appear at the bottom of every page (FR-33)
2. Show the title of the next page in linear reading order
3. Link to the next page URL
4. On the last page of a section, link to the first page of the next section
5. On the last page of the site, display "View the source on GitHub →"

## Code Example Contract

Every code block in the documentation MUST:

1. Include all `use` / `import` statements (FR-30)
2. Use real `Emissary\` class and method names matching `specs/02-contracts.md`
3. Be syntactically valid PHP 8.3+ (Constitution P10)
4. Use `<?php` opening tag
5. Never contain `// ...` or `// your code here` placeholders (FR-30)
6. Never reference classes or methods not defined in the Emissary library

## Progressive Disclosure Contract

Per FR-29, FR-31:

| Section | Audience | Content Depth | Visibility |
|---|---|---|---|
| TL;DR | All | 30-second copy-paste | Always visible |
| Quick Start | Junior+ | Minimal working example | Always visible |
| Deep Dive | Mid+ | Edge cases, security, perf | Collapsible toggle (FR-31) |
| Configuration Reference | All | Key table with defaults | Always visible |
