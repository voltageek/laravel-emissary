# Security

> The 6 attack surfaces, consent, retention/PII.

---

## Security

The library addresses six attack surfaces. Each defence is wired into a specific pipeline component; the per-component sections state the precise contract, this section states the threat and rationale.

### 1. Webhook spoofing → `ChannelAdapter::verify()`
Every webhook is a public HTTP endpoint. Each adapter validates the channel's signed payload (WhatsApp HMAC-SHA256, Telegram secret header, Web CSRF/session). The webhook controller **hard-fails with HTTP 401** when `verify()` returns false — the request is never parsed and never enters the pipeline. Verification fails closed on a missing or misconfigured secret. *(See `ChannelAdapter`; `security.require_webhook_verify` config.)*

### 2. Prompt injection (direct) → `JailbreakDetectionGuard` + system-role prompt
User text reaches the LLM verbatim, so a crafted message may attempt to override the system prompt. Defences, layered:
- The system prompt is always sent in the `system` role, which most models treat as higher-trust than user turns — it is never concatenated into user content.
- `JailbreakDetectionGuard` fires at `beforeIntent` and blocks flagged messages (heuristic pattern match, optionally a model-based classifier). It is **on by default**; disable via `security.jailbreak.enabled`.
- These raise the bar; no defence is complete. Sensitive tools must still be gated by `beforeTool` guards.

### 3. Indirect prompt injection → `ConversationMemory::appendToolResult()`
Tool results may contain attacker-controlled text from your own data (product descriptions, customer notes) that the LLM could treat as instructions. `appendToolResult()` wraps every tool result in a delimited data envelope and instructs the model to treat the content as untrusted data. *(See `ConversationMemory`; `security.tool_result_wrap` config.)*

### 4. Tool argument injection → `ToolRegistry::execute()`
The LLM chooses tool arguments; via injection it could call a legitimate tool with malicious arguments. `execute()` validates `$arguments` against the tool's registered JSON schema before the handler runs (unknown properties rejected, required/enum/type enforced). Failures are returned to the LLM as a `tool_result` error for self-correction, tagged `AgentError::TOOL_INVALID_ARGUMENTS`. Handlers should still validate business invariants — the schema guards the shape, not the domain meaning.

### 5. Channel identity & authorization → `ChannelIdentityResolver` + guards
For WhatsApp/Telegram there is no Laravel session; `$user` comes from `ChannelIdentityResolver`. The default returns the session user for Web and `null` for chat channels. Apps that want chat-channel users to authorise against Laravel roles implement a resolver that links a channel identity (verified phone/chat-id) to a `User` record, after which guard behaviour (`AuthenticatedUserGuard`, custom `beforeExecution`/`beforeTool` checks) applies uniformly. The `pending_action` on the confirmation gate is scoped per-conversation, so guards should verify the acting user matches the user who proposed the action when identity is ambiguous.

### 6. Resource & cost exhaustion → `RateLimitGuard`, `CostCapGuard`, `MaxTurnsGuard`
- `RateLimitGuard` caps messages per conversation per minute. It is per-conversation, not per-IP/global — an attacker with many numbers can bypass it; put a global edge/WAF rate limit in front for hostile environments.
- `CostCapGuard` blocks a conversation once its accumulated LLM cost reaches `cost_alerts.per_conversation_max_usd` (no-op when cost tracking is off). This caps cost-of-service abuse per conversation.
- `MaxTurnsGuard` caps turns per conversation; `max_tool_call_rounds` caps tool iterations per turn.

### Data retention & PII
Conversation transcripts are sent to a third-party LLM and stored verbatim in `conversation_messages`. Configure a retention TTL and schedule `php artisan agent:prune` to bound storage. The `content` column is not encrypted at rest by default — apps handling PII should encrypt it or avoid storing raw transcripts. *(See Data Models → Retention.)* Note the asymmetry: **channel credentials are secrets and ARE encrypted at rest** (see Channel Onboarding → Credential security), the opposite default to conversation content.

### Consent & lawful processing
Because transcripts are shipped to a third-party LLM, processing a sender's messages often needs their agreement. The onboarding flow makes this an **enforceable precondition**: with `onboarding.require_consent = true`, `OnboardingGuard` blocks tool-bearing intents until `consent_at` + `consent_version` are recorded, and consent is re-capturable on version bump. Disabling it is the host's legal call and should be a deliberate decision per jurisdiction — the library treats consent as a security/compliance control, not a cosmetic prompt. *(See User Onboarding.)*
