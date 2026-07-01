---
extends: _layouts.master
title: Tools
description: Give the agent capabilities — database queries, API calls, and actions.
---

@section('body')
<div class="tldr-box">
    <h4>TL;DR</h4>
    <p>Tools are PHP methods the agent can call. The <code>#[Tool]</code> attribute declares them. The <code>ToolScanner</code> derives JSON schemas from PHP types.</p>
</div>

## Quick Start

```php
use Emissary\Attributes\Tool;

class MyTools
{
    #[Tool(name: 'greet', description: 'Greet the user by name.')]
    public function greet(string $name): string
    {
        return "Hello, {$name}!";
    }
}
```

The agent can now call `greet` with a `name` parameter.

<details class="deep-dive">
    <summary>Deep Dive</summary>
    <div class="deep-dive-content">

### How It Works

1. `ToolScanner` reflects on your class at registration time
2. Finds all `#[Tool]` methods
3. Derives OpenAI JSON Schema from PHP parameter types + attribute `parameters`
4. Binds the method as the handler — definition and handler can never drift apart

### Configuration

Map tools to intents in your plugin:

```php
public function getIntentConfig(): array
{
    return [
        'record_sale' => ['tools' => ['record_sale']],
    ];
}
```

Or in `config/emissary.php`:

```php
'intents' => [
    'record_sale' => ['tools' => ['record_sale']],
],
```

### See Also

- [Tool Authoring Guide →](/guides/tool-authoring) — Full guide with examples
- [API Reference →](/reference/api/attributes) — `#[Tool]` attribute reference

    </div>
</details>
@endsection
