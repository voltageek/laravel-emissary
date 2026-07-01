---
extends: _layouts.master
title: API — DTOs
description: Emissary data transfer objects — InboundMessage, AgentResponse, GuardResult, and more.
---

@section('body')
<div class="tldr-box">
    <h4>TL;DR</h4>
    <p>Emissary uses readonly DTOs for all data transfer between pipeline stages. DTOs carry <code>turn_id</code> for traceability.</p>
</div>

## Quick Start

Every DTO in Emissary is a `readonly class` with constructor property promotion:

```php
readonly class GuardResult
{
    public function __construct(
        public bool $allowed,
        public ?string $reason = null,
        public bool $pending = false,
    ) {}
}
```

## DTOs

### InboundMessage

Carries a message from a channel into the pipeline.

| Property | Type | Description |
|---|---|---|
| `conversationId` | `string` | Unique conversation identifier |
| `channel` | `Channel` | Source channel (whatsapp, telegram, web) |
| `content` | `string` | Message body text |
| `senderId` | `string` | Channel-specific sender identifier |
| `metadata` | `array` | Channel-specific metadata |
| `turnId` | `string` | Unique identifier for this turn |

### AgentResponse

Carries the agent's response back through the pipeline.

| Property | Type | Description |
|---|---|---|
| `content` | `string` | Response text |
| `toolCalls` | `array` | Tool invocations made |
| `turnId` | `string` | Turn identifier |
| `cost` | `float` | Total cost in USD |

### IntentResult

Output of intent classification.

| Property | Type | Description |
|---|---|---|
| `intent` | `string` | Classified intent slug |
| `confidence` | `float` | Classification confidence (0–1) |
| `turnId` | `string` | Turn identifier |

### GuardResult

Guard evaluation outcome. Built with named constructors:

```php
GuardResult::allow();                      // Proceed
GuardResult::deny('Rate limit exceeded');  // Block
GuardResult::pending('Confirm refund?');   // Need confirmation
```

### ChannelCredentials

Channel authentication data.

| Property | Type | Description |
|---|---|---|
| `channel` | `Channel` | Channel identifier |
| `credentials` | `array` | Key-value credential map |

### OutboundMessage

Carries a formatted response to a channel adapter.

| Property | Type | Description |
|---|---|---|
| `recipientId` | `string` | Channel recipient identifier |
| `content` | `string` | Formatted message content |
| `channel` | `Channel` | Target channel |
| `turnId` | `string` | Turn identifier |

## See Also

- [Contracts →](/reference/api/contracts)
- [Attributes →](/reference/api/attributes)
- [Inheritance Diagram →](/reference/api/inheritance)
@endsection
