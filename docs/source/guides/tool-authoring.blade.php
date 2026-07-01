---
extends: _layouts.master
title: Tool Authoring
description: Define tools the agent can call — database queries, actions, and integrations — using the #[Tool] attribute.
---

@section('body')
<div class="tldr-box">
    <h4>TL;DR</h4>
<pre><code class="language-php">&lt;?php

namespace App\Emissary;

use Emissary\Attributes\Tool;
use Emissary\Contracts\AgentToolProvider;

class SalesTools implements AgentToolProvider
{
    #[Tool(name: 'record_sale', description: 'Record a sale transaction.')]
    public function recordSale(float $amount, string $item): string
    {
        Sale::create(['amount' => $amount, 'item' => $item]);

        return "Recorded sale of \${$amount} for {$item}.";
    }
}</code></pre>
</div>

## Quick Start

A **tool** is any PHP method the agent can call. You define tools with the `#[Tool]` attribute on a provider class. Emissaryautomatically discovers methods, derives JSON schemas from PHP types, and binds the method as the handler.

### Minimal Tool (No Parameters)

```php
&lt;?php

namespace App\Emissary;

use Emissary\Attributes\Tool;

class InventoryTools
{
    #[Tool(name: 'check_inventory', description: 'Check current inventory status.')]
    public function checkInventory(): array
    {
        return Product::select('name', 'stock')->get()->toArray();
    }
}
```

The agent can now call `check_inventory` when a user asks about stock levels.

### Tool with Scalar Parameters

```php
#[Tool(
    name: 'record_sale',
    description: 'Record a sale transaction.',
    parameters: '{&quot;amount&quot;: &quot;Sale amount in dollars&quot;, &quot;item&quot;: &quot;Item name sold&quot;}'
)]
public function recordSale(float $amount, string $item): string
{
    // ...
}
```

</xv-deep-dive>

<details class="deep-dive">
    <summary>Deep Dive</summary>
    <div class="deep-dive-content">

### The #[Tool] Attribute

The attribute accepts three arguments:

| Parameter | Type | Required | Description |
|---|---|---|---|
| `name` | `string` | Yes | Unique identifier the agent uses to invoke the tool |
| `description` | `string` | Yes | What the tool does — shown to the agent as context |
| `parameters` | `string` | No | JSON describing each parameter's meaning (used when PHP type hints aren't self-documenting) |

### PHP Type → JSON Schema Mapping

The `ToolScanner` derives OpenAI-compatible JSON schemas from your PHP parameter types:

| PHP Type | JSON Schema Type |
|---|---|
| `string` | `{"type": "string"}` |
| `int` | `{"type": "integer"}` |
| `float` | `{"type": "number"}` |
| `bool` | `{"type": "boolean"}` |
| `array` | `{"type": "array"}` |

When the `parameters` attribute argument is provided, its descriptions are merged into the schema for clarity.

### Tool with Array/Object Parameters

```php
#[Tool(
    name: 'search_products',
    description: 'Search products by category and price range.',
    parameters: '{&quot;filters&quot;: &quot;Search criteria: category, min_price, max_price&quot;}'
)]
public function searchProducts(array $filters): array
{
    return Product::query()
        ->when($filters['category'] ?? null, fn($q, $v) => $q->where('category', $v))
        ->when($filters['min_price'] ?? null, fn($q, $v) => $q->where('price', '>=', $v))
        ->when($filters['max_price'] ?? null, fn($q, $v) => $q->where('price', '<=', $v))
        ->get()
        ->toArray();
}
```

### The AgentToolProvider Contract

All tool providers must implement `Emissary\Contracts\AgentToolProvider`:

| Method | Returns | Purpose |
|---|---|---|
| `pluginName(): string` | Plugin identifier | Unique name for your plugin |
| `getIntents(): array` | Intent slugs | Intents this plugin handles |
| `getIntentConfig(): array` | slug → {model, tools[]} | Which tools map to which intents |
| `getIntentClassificationHints(): array` | slug → description | Helps the LLM classify user messages |
| `getGuards(): array` | `AgentGuard[]` | Guard rules for this plugin |
| `getSystemPromptExtension(): string` | Additional prompt text | Extends the system prompt |
| `getToolDefinitions(): array` | OpenAI function schema[] (default: []) | Escape hatch for complex schemas |
| `getDocumentMappings(): array` | Document type mappings | For document-processing features |
| `isIntentSupported(string $intent, mixed $tenant): bool` | true/false | Tenant-gate specific intents |

### Parameter Validation

Emissary validates tool arguments against the derived JSON schema before execution. If a required parameter is missing or the type doesn't match, the call fails with an `AgentError` code.

### Error Handling

Return plain data from tools. For errors, return a string describing the failure:

```php
#[Tool(name: 'process_refund', description: 'Process a refund.')]
public function processRefund(int $orderId, float $amount): string
{
    $order = Order::find($orderId);

    if (! ($order)) {
        return "Order #{$orderId} not found.";
    }

    $order->refund($amount);

    return "Refunded \${$amount} for order #{$orderId}.";
}
```

### Return Shapes

Tools return plain data — strings, arrays, or DTOs. The channel adapter formats the response for each channel automatically.

| Return Type | Use Case |
|---|---|
| `string` | Confirmation messages, status updates |
| `array` | Structured data the LLM interprets for the user |
| `object` | Any serializable PHP object |

### Registering Your Tool Provider

Add your provider to a service provider:

```php
$this->app->tag([SalesTools::class], 'emissary.plugins');
```

Or register in `config/emissary.php`:

```php
'plugins' => [
    App\Emissary\SalesTools::class,
],
```

Verify with:

```bash
php artisan emissary:tool:list
```

    </div>
</details>
@endsection
