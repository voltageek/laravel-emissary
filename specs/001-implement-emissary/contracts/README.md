# Phase 1 — Contracts

This directory documents the public API surface established in Phase 1. All names, signatures, and
return types are copied verbatim from `specs/02-contracts.md`.

## Interfaces

### AgentToolProvider (`Emissary\Contracts\AgentToolProvider`)

The central plugin SPI. Host-app plugins implement this to inject intents, tools, guards, and
system prompt extensions. Implemented by Phase 4's `OnboardingToolProvider` and any host-app plugin.

**Methods**:
- `pluginName(): string`
- `getIntents(): array`
- `getIntentConfig(): array` — `slug => {model, tools[]}`
- `getIntentClassificationHints(): array` — `slug => "description with examples"`
- `getToolDefinitions(): array` — OpenAI function schema[] (escape hatch, defaults to `[]`)
- `getGuards(): array` — `AgentGuard[]`
- `getSystemPromptExtension(): string`
- `getDocumentMappings(): array`
- `isIntentSupported(string $intent, mixed $tenant): bool`

**Phase 1 status**: Interface only. No implementations yet beyond built-in defaults in Phase 4.

### AgentGuard (`Emissary\Contracts\AgentGuard`)

Guards express rules and restrictions at three pipeline checkpoints.

**Methods**:
- `getName(): string`
- `beforeIntent(InboundMessage $message, ?Authenticatable $user, mixed $tenant): GuardResult`
- `beforeExecution(string $intent, ?Authenticatable $user, mixed $tenant): GuardResult`
- `beforeTool(string $toolName, array $arguments, ?Authenticatable $user, mixed $tenant): GuardResult`

**EARS acceptance criteria** (from spec):
- WHEN a guard returns `deny(M, C)` at any checkpoint THE SYSTEM SHALL stop evaluating further
  guards, skip the rest of that checkpoint's pipeline stage, and reply `M` to the user with
  `errorCode = C`.
- WHEN all guards at a checkpoint return `allow` THE SYSTEM SHALL proceed to the next stage.
- WHEN a guard's `deny()` omits the error code THE SYSTEM SHALL default to `AgentError::GUARD_DENIED`.
- WHEN a `GuardDecision` listener is registered THE SYSTEM SHALL emit one event per evaluated
  guard, carrying `turn_id` (deny always; allow only if `observability.trace_guard_allows`).

### TenancyResolver (`Emissary\Contracts\TenancyResolver`)

Injectable interface for resolving a tenant from an inbound message.

**Methods**: `resolve(InboundMessage $message): mixed`, `activate(mixed $tenant): void`

### ChannelIdentityResolver (`Emissary\Contracts\ChannelIdentityResolver`)

Resolves the Laravel `Authenticatable` user behind an inbound message — the single source of
the `$user` value passed to every guard.

**Methods**: `resolveUser(InboundMessage $message): ?Authenticatable`

### ChannelCredentialStore (`Emissary\Contracts\ChannelCredentialStore`)

Resolves channel credentials, optionally scoped to a tenant. Used by `ChannelAdapter::verify()`
and `send()`.

**Methods**: `resolve(Channel $channel, mixed $tenant = null): ?ChannelCredentials`

### ChannelAdapter (`Emissary\Contracts\ChannelAdapter`)

Abstraction for message channels. `formatResponse()` allows each adapter to express
channel-native message types.

**Methods**: `parse(Request $request): InboundMessage`, `verify(Request $request): bool`,
`formatResponse(AgentResponse $response): OutboundMessage`, `send(string $channelRef, OutboundMessage $message): void`

### ConfirmationGate (`Emissary\Contracts\ConfirmationGate`)

Controls write operations that set `requires_confirmation: true` in their tool definition.

**Methods**: `propose(Conversation $conversation, array $action): string`,
`execute(Conversation $conversation): array`, `cancel(Conversation $conversation): void`,
`isExpired(Conversation $conversation): bool`

## DTOs

| DTO | Modifier | Key Properties |
|---|---|---|
| `GuardResult` | `readonly` | `$allowed` (bool), `$userMessage` (?string), `$errorCode` (?string); static `allow()`, `deny()` |
| `InboundMessage` | `readonly` | `$conversationRef`, `$channel` (Channel), `$text`, `$mediaUrl`, `$receivedAt` (Carbon) |
| `OutboundMessage` | `readonly` | `$text`, `$mediaUrl`, `$quickReplies`, `$channelExtras` |
| `IntentResult` | `readonly` | `$slug` (string), `$confidence` (float 0.0–1.0) |
| `AgentResponse` | mutable | `$content`, `$intent`, `$toolCalls`, `$confirmationRequired`, `$errorCode`; static `fromContent()`, `fromError()`, `toOutbound()` |
| `TransactionResult` | mutable | `$success`, `$referenceId`, `$message`; static `ok()`, `fail()` |
| `ChannelCredentials` | `readonly` | `$verifySecret`, `$accessToken`, `$senderId`, `$handshakeToken`, `$extra` |

## Enums

| Enum | Values |
|---|---|
| `Channel` | `WhatsApp`, `Telegram`, `Web` |

## Error Taxonomy

`AgentError` (final class, 15 constants):

| Constant | Value |
|---|---|
| `GUARD_DENIED` | `guard.denied` |
| `AUTH_UNAUTHENTICATED` | `auth.unauthenticated` |
| `AUTH_UNAUTHORIZED` | `auth.unauthorized` |
| `INTENT_LOW_CONFIDENCE` | `intent.low_confidence` |
| `INTENT_UNKNOWN` | `intent.unknown` |
| `TOOL_EXECUTION_FAILED` | `tool.execution_failed` |
| `TOOL_INVALID_ARGUMENTS` | `tool.invalid_arguments` |
| `TOOL_MAX_ROUNDS` | `agent.max_rounds` |
| `LLM_TIMEOUT` | `llm.timeout` |
| `LLM_RATE_LIMITED` | `llm.rate_limited` |
| `LLM_ERROR` | `llm.error` |
| `SECURITY_JAILBREAK` | `security.jailbreak` |
| `COST_LIMIT_EXCEEDED` | `cost.limit_exceeded` |
| `ONBOARDING_REQUIRED` | `onboarding.required` |
| `CONVERSATION_MAX_TURNS` | `conversation.max_turns` |

Each error code has a default user-facing message in
`config('emissary.error_messages')` — overridable per locale by host apps.

## Attribute

### `#[Tool]` (`Emissary\Attributes\Tool`)

Target: `\Attribute::TARGET_METHOD`. Constructor parameters:

| Property | Type | Default | Description |
|---|---|---|---|
| `$description` | string | (required) | Description for the tool |
| `$requiresConfirmation` | bool | `false` | Triggers ConfirmationGate before execution |
| `$confirmationTemplate` | ?string | `null` | Message shown to user (e.g. "Place order for {quantity}x {product_id}?") |
| `$intents` | array | `[]` | Intents this tool may serve |
| `$params` | array | `[]` | Param name → meta (description, required, enum, type override) |
