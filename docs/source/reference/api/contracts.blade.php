---
extends: _layouts.master
title: API — Contracts
description: Emissary public interfaces — AgentToolProvider, AgentGuard, ChannelAdapter, and more.
---

@section('body')
<div class="tldr-box">
    <h4>TL;DR</h4>
    <p>Emissary's public API surface consists of ~15 interfaces and DTOs in the <code>Emissary\Contracts\</code> namespace. Implement these to customize behavior.</p>
</div>

## Quick Start

The three interfaces you'll implement most often:

| Interface | Purpose | When to Implement |
|---|---|---|
| `AgentToolProvider` | Declare intents, tools, guards | Every Emissary plugin |
| `AgentGuard` | Safety rules at checkpoints | Custom authorization rules |
| `ChannelAdapter` | Parse + send channel messages | New channel support |

## Core Interfaces

### AgentToolProvider

The central plugin SPI. Implement this to add domain capabilities.

```php
namespace Emissary\Contracts;

interface AgentToolProvider
{
    public function pluginName(): string;
    public function getIntents(): array;
    public function getIntentConfig(): array;
    public function getIntentClassificationHints(): array;
    public function getToolDefinitions(): array;
    public function getGuards(): array;
    public function getSystemPromptExtension(): string;
    public function getDocumentMappings(): array;
    public function isIntentSupported(string $intent, mixed $tenant): bool;
}
```

### AgentGuard

Safety rules evaluated at pipeline checkpoints.

```php
interface AgentGuard
{
    const BEFORE_INTENT = 'beforeIntent';
    const BEFORE_EXECUTION = 'beforeExecution';
    const AFTER_EXECUTION = 'afterExecution';

    public function checkpoint(): string;
    public function evaluate(InboundMessage $message, ?IntentResult $intent): GuardResult;
}
```

### ChannelAdapter

Parse inbound messages and format outbound responses per channel.

```php
interface ChannelAdapter
{
    public function parse(Request $request): InboundMessage;
    public function verify(Request $request): bool;
    public function formatResponse(AgentResponse $response): OutboundMessage;
    public function send(OutboundMessage $message): void;
}
```

### ChannelCredentialStore

Resolve channel credentials. Config-backed or DB-backed.

```php
interface ChannelCredentialStore
{
    public function get(Channel $channel): ChannelCredentials;
}
```

### ChannelIdentityResolver

Resolve who's messaging across channels.

```php
interface ChannelIdentityResolver
{
    public function resolve(InboundMessage $message): ?ResolvedIdentity;
}
```

### ConfirmationGate

Confirmation flow for sensitive tool executions.

```php
interface ConfirmationGate
{
    public function isPending(string $conversationId): bool;
    public function confirm(string $conversationId): void;
    public function deny(string $conversationId): void;
    public function request(string $conversationId, string $prompt): void;
}
```

### TenancyResolver

Optional multi-tenant context resolution.

```php
interface TenancyResolver
{
    public function resolve(InboundMessage $message): mixed;
}
```

## See Also

- [DTOs →](/reference/api/dtos) — Data transfer objects
- [Attributes →](/reference/api/attributes) — `#[Tool]` attribute reference
- [Inheritance Diagram →](/reference/api/inheritance)
@endsection
