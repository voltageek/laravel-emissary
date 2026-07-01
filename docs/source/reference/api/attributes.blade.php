---
extends: _layouts.master
title: API — Attributes
description: The #[Tool] attribute — declare agent-callable methods with automatic JSON schema derivation.
---

@section('body')
<div class="tldr-box">
    <h4>TL;DR</h4>
    <pre><code class="language-php">#[Tool(
    name: 'record_sale',
    description: 'Record a sale transaction.',
    parameters: '{&quot;amount&quot;: &quot;Sale amount in dollars&quot;}'
)]
public function recordSale(float $amount, string $item): string
{
    // ...
}</code></pre>
</div>

## Quick Start

The `#[Tool]` attribute declares a method as agent-callable. The `ToolScanner` reflects on it at registration time, derives a JSON schema from PHP types, and binds the method as the handler.

### Attribute Parameters

| Parameter | Type | Required | Default | Purpose |
|---|---|---|---|---|
| `name` | `string` | Yes | — | Unique tool identifier |
| `description` | `string` | Yes | — | What the tool does (shown to LLM) |
| `parameters` | `string` | No | `''` | JSON parameter descriptions for complex types |

### Schema Derivation

The `ToolScanner` maps PHP type hints to JSON Schema types:

```php
// Reflection           → JSON Schema
string $name            → {"type": "string"}
int $count              → {"type": "integer"}
float $amount           → {"type": "number"}
bool $confirmed         → {"type": "boolean"}
array $items            → {"type": "array"}
```

When the `parameters` argument is provided, descriptions are merged into the schema.

### Escape Hatch

For schemas too complex for attribute syntax (nested objects, `anyOf`, `$ref`), override `getToolDefinitions()`:

```php
public function getToolDefinitions(): array
{
    return [[
        'name' => 'complex_tool',
        'description' => '...',
        'parameters' => [
            'type' => 'object',
            'properties' => [
                'nested' => [
                    'type' => 'object',
                    'properties' => [
                        'field' => ['type' => 'string'],
                    ],
                ],
            ],
        ],
    ]];
}
```

`getToolDefinitions()` takes precedence on name clashes with reflected `#[Tool]` methods.

### See Also

- [Tool Authoring Guide →](/guides/tool-authoring)
- [Core Concepts: Tools →](/concepts/tools)
- [Contracts →](/reference/api/contracts)
@endsection
