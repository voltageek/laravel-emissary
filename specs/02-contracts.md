# Contracts

> All interfaces, the error taxonomy, and DTOs. Copy names/signatures verbatim.

---

## Core Interfaces

### AgentToolProvider

The central plugin SPI. Plugins implement this to inject domain capabilities. The interface is organised into five exclusive concerns: identity, intents, tools, guards, and prompt extension.

```php
interface AgentToolProvider
{
    // Identity
    public function pluginName(): string;

    // ── Intents ──────────────────────────────────────────────────────────────
    // Declare which intents this plugin handles.
    public function getIntents(): array;
    public function getIntentConfig(): array;               // slug → {model, tools[]}
    public function getIntentClassificationHints(): array;  // slug → "description with examples"

    // ── Tools ─────────────────────────────────────────────────────────────────
    // Tools are declared by annotating public, non-static methods with the
    // #[Tool] attribute (see "Attribute-Driven Tools" below). The ToolScanner
    // derives each tool's OpenAI JSON schema from the method's PHP parameter
    // types and the attribute's params array, and binds the method itself as
    // the handler — definition and handler can never drift apart.
    //
    // Escape hatch for schemas too complex for attribute syntax (nested
    // objects, anyOf, $ref): override getToolDefinitions(). It defaults to []
    // and takes precedence on a name clash with a reflected #[Tool] method.
    public function getToolDefinitions(): array;   // OpenAI function schema[] (default: [])

    // ── Guards ────────────────────────────────────────────────────────────────
    // Rules and restrictions applied at pipeline checkpoints.
    public function getGuards(): array;            // AgentGuard[]

    // ── System Prompt ─────────────────────────────────────────────────────────
    public function getSystemPromptExtension(): string;

    // ── Document Processing ───────────────────────────────────────────────────
    public function getDocumentMappings(): array;

    // ── Tenant Gating (optional) ──────────────────────────────────────────────
    // Return true to allow, false to suppress. Defaults to true if not
    // overridden. Only relevant when a TenancyResolver is configured.
    public function isIntentSupported(string $intent, mixed $tenant): bool;
}
```

**Tool definition schema** (extended OpenAI function schema). Each field maps directly to a `#[Tool]` attribute constructor argument; the array form below applies only when using the `getToolDefinitions()` escape hatch:

```php
[
    'name'                    => 'record_sale',
    'description'             => 'Records a sale transaction.',
    'parameters'              => [/* OpenAI JSON Schema */],
    'requires_confirmation'   => true,   // triggers ConfirmationGate before execution
    'confirmation_template'   => 'Record a sale of {amount} for {product}?',
]
```

---

### Attribute-Driven Tools

The `#[Tool]` attribute declares a provider method as a tool. The `ToolScanner` reads it at boot, derives the JSON schema from PHP types, and binds the method as the handler — the definition and its handler are the same object and cannot drift apart.

**Attribute definition:**

```php
#[\Attribute(\Attribute::TARGET_METHOD)]
class Tool
{
    public function __construct(
        public string  $description,
        public bool    $requiresConfirmation = false,
        public ?string $confirmationTemplate = null,
        public array   $intents = [],   // intents this tool may serve
        public array   $params = [],    // param name => meta (see below)
    ) {}
}
```

**`params` array shape** — keyed by parameter name; each value may carry:

```php
'product_id' => [
    'description' => 'The product ID to order',
    'required'    => false,                  // optional; defaults to PHP signature
    'enum'        => ['pending', 'paid'],    // optional
    'type'        => 'array',                // optional override (only meaningful for arrays)
],
```

**Type inference rules** — the scanner reads PHP types from the method signature and maps them to JSON schema types automatically:

| PHP type | JSON schema `type` |
|---|---|
| `string` | `"string"` |
| `int` | `"integer"` |
| `float` | `"number"` |
| `bool` | `"boolean"` |
| `?T` | type `T`, removed from `required[]` |
| `array` | `"object"` unless `params` overrides with `"type" => "array"` |

The `params` array supplies `description`, `enum`, and type overrides — everything the PHP type system cannot express. Neither alone is sufficient; together they produce a complete schema with no duplication.

**Boot-time validation:** Keys in `params` must match parameter names in the method signature. A mismatch throws a loud error at registration, not at runtime:

```
[AgentToolProvider] Tool 'recordSale': params key 'custommer' does not match
any parameter. Did you mean 'customer'?
```

**Worked example:**

```php
class OrderPlugin implements AgentToolProvider
{
    public function __construct(private OrderService $orders) {}

    public function pluginName(): string { return 'orders'; }

    #[Tool(
        description: 'Places a new order for a product.',
        requiresConfirmation: true,
        confirmationTemplate: 'Place an order for {quantity}x {product_id}?',
        intents: ['place_order'],
        params: [
            'product_id' => ['description' => 'The product ID to order'],
            'quantity'   => ['description' => 'How many to order'],
        ],
    )]
    public function placeOrder(int $product_id, int $quantity): TransactionResult
    {
        // identical to what you'd write in a controller
        $order = $this->orders->create(auth()->user(), $product_id, $quantity);
        return TransactionResult::ok($order->id, "Order #{$order->id} placed.");
    }

    #[Tool(
        description: 'Gets the status of an existing order.',
        intents: ['check_order_status'],
        params: [
            'order_id' => ['description' => 'The order ID to look up'],
        ],
    )]
    public function getOrderStatus(int $order_id): array
    {
        $order = Order::findOrFail($order_id);
        return [
            'status'       => $order->status,
            'estimated_at' => $order->estimated_delivery?->toDateString(),
        ];
    }

    // getIntents(), getIntentConfig(), getGuards(), etc. as usual.
}
```

The method bodies are untouched — no new patterns, no wrapper layer around your models. The scanner never invokes them; it only reads their shape.

---

### AgentGuard

Guards express rules and restrictions. They are called at three defined checkpoints in the pipeline. The `$user` parameter is the currently authenticated Laravel `Authenticatable` (or null for unauthenticated channels like WhatsApp).

```php
interface AgentGuard
{
    public function getName(): string;

    /**
     * Checkpoint 1 — fires before intent classification.
     * Use for: rate limiting, channel restrictions, authentication requirements.
     */
    public function beforeIntent(
        InboundMessage $message,
        ?Authenticatable $user,
        mixed $tenant,
    ): GuardResult;

    /**
     * Checkpoint 2 — fires after intent classification, before agent/tool execution.
     * Use for: intent-level authorization, user role checks, business state conditions.
     */
    public function beforeExecution(
        string $intent,
        ?Authenticatable $user,
        mixed $tenant,
    ): GuardResult;

    /**
     * Checkpoint 3 — fires before each individual tool call inside the agent loop.
     * Use for: per-tool authorization, argument validation, audit hooks.
     */
    public function beforeTool(
        string $toolName,
        array $arguments,
        ?Authenticatable $user,
        mixed $tenant,
    ): GuardResult;
}
```

**GuardResult:**

```php
readonly class GuardResult
{
    public static function allow(): self;
    public static function deny(
        string $userMessage,
        string $errorCode = AgentError::GUARD_DENIED,
    ): self;

    public bool $allowed;
    public ?string $userMessage;  // shown to user when denied
    public ?string $errorCode;    // see Error Taxonomy below
}
```

**Guard evaluation order:**  
Guards registered by multiple plugins are evaluated in registration order. The first `deny` result short-circuits evaluation — remaining guards are not called. The denied `userMessage` is returned to the user immediately.

**Worked example:**

```php
class OrderGuard implements AgentGuard
{
    public function getName(): string { return 'order-guard'; }

    public function beforeIntent(InboundMessage $m, ?Authenticatable $u, mixed $t): GuardResult
    {
        return $u ? GuardResult::allow()
                  : GuardResult::deny('You need to be logged in.', AgentError::AUTH_UNAUTHENTICATED);
    }

    public function beforeExecution(string $intent, ?Authenticatable $u, mixed $t): GuardResult
    {
        if ($intent === 'cancel_order' && ! $u->hasRole('manager')) {
            return GuardResult::deny('Only managers can cancel orders.', AgentError::AUTH_UNAUTHORIZED);
        }
        return GuardResult::allow();
    }

    public function beforeTool(string $tool, array $args, ?Authenticatable $u, mixed $t): GuardResult
    {
        return GuardResult::allow(); // per-tool checks optional
    }
}
```

**Acceptance criteria (EARS):**
- **WHEN** a guard returns `GuardResult::deny(M, C)` at any checkpoint **THE SYSTEM SHALL** stop evaluating further guards, skip the rest of that checkpoint's pipeline stage, and reply `M` to the user with `errorCode = C`.
- **WHEN** all guards at a checkpoint return `allow` **THE SYSTEM SHALL** proceed to the next pipeline stage.
- **WHEN** a guard's `deny()` omits the error code **THE SYSTEM SHALL** default the code to `AgentError::GUARD_DENIED`.
- **WHEN** a `GuardDecision` listener is registered **THE SYSTEM SHALL** emit one event per evaluated guard, carrying `turn_id` (deny always; allow only if `observability.trace_guard_allows`).

---

### TenancyResolver

Injectable interface for resolving a tenant from an inbound message. Defaults to `NullTenancyResolver` (single-tenant / no tenancy). Override for multi-tenant applications.

```php
interface TenancyResolver
{
    public function resolve(InboundMessage $message): mixed; // tenant or null
    public function activate(mixed $tenant): void;
}

// Default — always returns null, no tenancy activation
class NullTenancyResolver implements TenancyResolver { ... }
```

---

### ChannelIdentityResolver

Resolves the Laravel `Authenticatable` user behind an inbound message — the single source of the `$user` value passed to every guard. For the Web channel this is the session user; for WhatsApp/Telegram it requires linking a channel identity (phone number, Telegram chat ID) to a user record, typically via a verification flow you ship. Defaults to `AuthChannelIdentityResolver`, which returns the session user for Web and `null` for chat channels.

```php
interface ChannelIdentityResolver
{
    public function resolveUser(InboundMessage $message): ?Authenticatable;
}

// Default — session auth for Web, null for chat channels
class AuthChannelIdentityResolver implements ChannelIdentityResolver
{
    public function resolveUser(InboundMessage $message): ?Authenticatable
    {
        return $message->channel === Channel::Web ? auth()->user() : null;
    }
}
```

For chat channels, identity is established through an explicit **linking flow** rather than assumed. The library ships the pieces:

- `ChannelIdentityLink` table — binds a `(channel, channel_ref)` to a `user_id` with a `verified_at` timestamp (see Data Models).
- `LinkedChannelIdentityResolver` — reads that table; returns the linked `Authenticatable` for a `channel_ref` once verified, `null` otherwise. Apps wanting chat-channel auth bind this (or their own) over `AuthChannelIdentityResolver`.
- `GuestCreatingChannelIdentityResolver` — **channel-first path.** On first chat contact with no existing link, creates a **guest** `User` (with `onboarded_at = null`) plus a `ChannelIdentityLink`, and returns it; subsequent messages resolve to that same guest until onboarding upgrades it. This resolver is bound only when `onboarding.mode` enables channel-first guest creation (see User Onboarding).
- Built-in `verify_identity` intent — drives the link: the app issues a short-lived code to a logged-in user (e.g. "Connect WhatsApp" in the web UI), the user sends `VERIFY <code>` from their channel, the intent binds the `channel_ref` to their account and stamps `verified_at`. Until a link exists, chat-channel `$user` is `null` (or a guest, if that path is enabled) and guards decide what (if anything) such senders may do.

Channel-first guest creation is **configurable** — see `onboarding.mode`. `web_centric` binds `AuthChannelIdentityResolver` (no guest creation, link-only); `channel_first` and `hybrid` bind `GuestCreatingChannelIdentityResolver`.

This replaces the earlier "verification flow you ship" hand-wave with a concrete, optional flow — single-app deployments that don't need chat-channel auth leave the default resolver and ignore it.

---

### ChannelCredentialStore

Resolves the credentials (tokens, sender identities, verify secrets) for a channel, optionally scoped to a tenant. `ChannelAdapter::verify()` and `send()` resolve credentials **through this seam** rather than reading config directly — that decoupling is what makes onboarding hybrid. The default `ConfigChannelCredentialStore` reads `config/emissary.php` + env, so single-app installs work unchanged; multi-tenant apps bind a DB-backed implementation (the library ships `EncryptedChannelCredentialStore` over the `ChannelConfig` table) and provision channels per tenant at runtime.

```php
interface ChannelCredentialStore
{
    // null return = channel/tenant not provisioned → adapter fails closed
    public function resolve(Channel $channel, mixed $tenant = null): ?ChannelCredentials;
}

readonly class ChannelCredentials
{
    public function __construct(
        public string  $verifySecret,   // WhatsApp app secret / Telegram secret-token / Web CSRF key
        public ?string $accessToken,    // WhatsApp access token / Telegram bot token
        public ?string $senderId,       // WhatsApp phone_number_id, etc. — used by send()
        public ?string $handshakeToken, // WhatsApp hub.verify_token (registration handshake only)
        public ?array  $extra = null,
    ) {}
}
```

A `null` result means "this channel/tenant is not provisioned"; the adapter treats it as a verification failure (fail closed) rather than erroring. This is what lets `emissary:channel:test` and per-tenant provisioning distinguish *misconfigured* from *absent*.

---

### ChannelAdapter

Abstraction for message channels. The `formatResponse()` method allows each adapter to express channel-native message types (WhatsApp buttons/lists, Telegram inline keyboards, Web structured cards) without leaking channel logic into tool handlers.

```php
interface ChannelAdapter
{
    public function parse(Request $request): InboundMessage;
    public function verify(Request $request): bool;
    public function formatResponse(AgentResponse $response): OutboundMessage;
    public function send(string $channelRef, OutboundMessage $message): void;
}
```

**Webhook verification is mandatory.** The webhook controller calls `verify()` on every inbound request and **must hard-fail (HTTP 401)** when it returns `false` — the request is never parsed, never enters the pipeline, and produces no side effects. Each adapter validates the channel's signed payload: WhatsApp verifies the `X-Hub-Signature-256` HMAC-SHA256 over the raw body using the app secret; Telegram checks the `X-Telegram-Bot-Api-Secret-Token` header; Web validates the CSRF token / session. A missing or misconfigured secret causes verification to **fail closed**.

**Registration handshake vs. per-request verification (do not conflate).** WhatsApp onboarding has *two* distinct verification steps at the same URL:
- **GET handshake** (one-time, at webhook registration) — Meta sends `hub.mode=subscribe` + `hub.verify_token`; the adapter echoes back `hub.challenge` iff the token equals `ChannelCredentials::handshakeToken`. This confirms URL ownership to Meta.
- **POST HMAC** (every message thereafter) — the per-request signature check above.

The webhook controller routes by HTTP method: `GET` → handshake echo, `POST` → `verify()` then `parse()`. Telegram and Web have no handshake step. Blending the two is the most common WhatsApp onboarding failure.

**Credential resolution.** Adapters never read tokens from config directly. `verify()` and `send()` obtain a `ChannelCredentials` via `ChannelCredentialStore::resolve()` (scoped to the active tenant where relevant), and `send()` uses `senderId` as the outbound sender identity. Unprovisioned → fail closed.

---

### ConfirmationGate

Controls write operations that set `requires_confirmation: true` in their tool definition.

```php
interface ConfirmationGate
{
    public function propose(Conversation $conversation, array $action): string;
    public function execute(Conversation $conversation): array;
    public function cancel(Conversation $conversation): void;
    public function isExpired(Conversation $conversation): bool;
}
```

**Observability:** each state change emits a `ConfirmationGateTransitioned` event (transitions: `propose` / `execute` / `cancel` / `expire`), carrying the `turn_id`, `tool_name`, and the staged fields. This makes the confirmation lifecycle measurable — e.g. the share of proposed actions that were abandoned (proposed but never confirmed before expiry).

**Acceptance criteria (EARS):**
- **IF** `ConfirmationGate::isExpired($conversation)` is true **WHEN** the user later confirms **THE SYSTEM SHALL** discard the staged action, emit a `ConfirmationGateTransitioned(transition: expire)` event, and reply with the configured expiry message (not execute the tool).
- **WHEN** `ConfirmationGate::propose()` is called **THE SYSTEM SHALL** store the action on the conversation as `pending_action` and emit `ConfirmationGateTransitioned(transition: propose)`.
- **WHEN** the user reply matches the confirm fast-path **AND** the action is not expired **THE SYSTEM SHALL** re-evaluate `GuardRegistry::checkBeforeTool()` before executing the staged tool.
- **WHEN** the user reply matches the cancel fast-path **THE SYSTEM SHALL** clear `pending_action` and emit `ConfirmationGateTransitioned(transition: cancel)`.

---

## Error Taxonomy

All user-facing errors have a code (for programmatic handling) and a default message (overridable in config). Plugin guards may use any of these codes in `GuardResult::deny()`.

```php
final class AgentError
{
    // Guard / auth
    const GUARD_DENIED        = 'guard.denied';         // a guard blocked the request
    const AUTH_UNAUTHENTICATED = 'auth.unauthenticated'; // no authenticated user
    const AUTH_UNAUTHORIZED    = 'auth.unauthorized';    // user lacks permission for tool/intent

    // Intent classification
    const INTENT_LOW_CONFIDENCE = 'intent.low_confidence'; // below threshold → clarification
    const INTENT_UNKNOWN        = 'intent.unknown';         // no matching intent

    // Tool execution
    const TOOL_EXECUTION_FAILED  = 'tool.execution_failed';   // tool callable threw exception
    const TOOL_INVALID_ARGUMENTS = 'tool.invalid_arguments';  // arguments failed schema validation
    const TOOL_MAX_ROUNDS        = 'agent.max_rounds';         // tool loop limit reached

    // LLM
    const LLM_TIMEOUT      = 'llm.timeout';
    const LLM_RATE_LIMITED = 'llm.rate_limited';
    const LLM_ERROR        = 'llm.error';

    // Security
    const SECURITY_JAILBREAK = 'security.jailbreak';  // prompt-injection attempt blocked

    // Cost
    const COST_LIMIT_EXCEEDED = 'cost.limit_exceeded';  // per-conversation cost cap reached

    // Onboarding
    const ONBOARDING_REQUIRED = 'onboarding.required'; // blocked until onboarding complete

    // Conversation
    const CONVERSATION_MAX_TURNS = 'conversation.max_turns';
}
```

Default user messages for each code are defined in `config/emissary.php` under `error_messages` and are used by `MessageBridge::reply()` when an error terminates the pipeline.

---

## Data Transfer Objects

```php
// Inbound — produced by ChannelAdapter::parse()
readonly class InboundMessage {
    public function __construct(
        public string $conversationRef,   // channel-side conversation ID
        public Channel $channel,          // enum: WhatsApp, Telegram, Web
        public string $text,
        public ?string $mediaUrl,
        public Carbon $receivedAt,
    ) {}
}

// Outbound — sent via ChannelAdapter::send()
// Base fields are channel-agnostic. Channel-specific formatting is applied
// by ChannelAdapter::formatResponse() before send().
readonly class OutboundMessage {
    public function __construct(
        public string $text,
        public ?string $mediaUrl = null,
        public ?array $quickReplies = null,   // generic buttons
        public ?array $channelExtras = null,  // adapter-specific payload (raw)
    ) {}
}

// Intent classification result
readonly class IntentResult {
    public function __construct(
        public string $slug,         // matched intent slug
        public float $confidence,    // 0.0–1.0
    ) {}
}

// Agent response after processing
class AgentResponse {
    public function __construct(
        public string $content,
        public ?string $intent = null,
        public ?array $toolCalls = null,       // [{name, args_summary, duration_ms, success}]
        public bool $confirmationRequired = false,
        public ?string $errorCode = null,      // null = success
    ) {}

    public static function fromContent(string $content): self;
    public static function fromError(string $errorCode, string $message): self;
    public function toOutbound(): OutboundMessage;
}

// Tool execution result
class TransactionResult {
    public static function ok(string $referenceId, ?string $message = null): self;
    public static function fail(string $message): self;

    public bool $success;
    public ?string $referenceId;
    public ?string $message;
}
```
