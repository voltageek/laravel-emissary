---
extends: _layouts.master
title: Web Widget
description: Drop a chat widget into any Blade view — no additional configuration needed.
---

@section('body')
<div class="tldr-box">
    <h4>TL;DR</h4>
    <pre><code class="language-php">// resources/views/welcome.blade.php
&lt;x-emissary-web /&gt;</code></pre>
</div>

## Quick Start

The web widget is the simplest channel. No external API setup required.

### 1. Drop In the Component

Add to any Blade view:

```blade
&lt;x-emissary-web /&gt;
```

That's it. The widget renders a floating chat button that opens a conversation interface.

### 2. Customize (Optional)

Configure in `config/emissary.php`:

```php
'channels' => [
    'web' => [
        'route_prefix' => 'emissary',
        'widget' => [
            'position' => 'bottom-right',  // or 'bottom-left'
            'title' => 'Chat with us',
            'placeholder' => 'Type a message...',
            'color' => '#6366f1',
        ],
    ],
],
```

<details class="deep-dive">
    <summary>Deep Dive</summary>
    <div class="deep-dive-content">

### Architecture

The web widget uses a persistent conversation ID stored in localStorage. Messages are sent via fetch to the Emissary webhook endpoint and streamed back through Server-Sent Events (SSE).

### Route Registration

Emissary registers routes under the configured prefix. Default endpoints:

| Route | Method | Purpose |
|---|---|---|
| `/emissary/chat` | POST | Send a message |
| `/emissary/chat/{conversationId}` | GET | Load conversation history |
| `/emissary/chat/{conversationId}/stream` | GET | SSE message stream |

### CSRF Protection

The web widget automatically includes the Laravel CSRF token in requests. Ensure your app's CSRF middleware covers the Emissary routes (default).

### Widget Customization

```php
'widget' => [
    'position' => 'bottom-right',
    'title' => 'Chat with us',
    'placeholder' => 'Type a message...',
    'color' => '#6366f1',
    'show_branding' => true,
    'welcome_message' => 'Hello! How can I help?',
],
```

    </div>
</details>
@endsection
