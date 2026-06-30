# Decisions, Dependencies, Migration

> The 17 design decisions, dependencies, and the standalone-package path.

---

## Key Design Decisions

1. **Single tool surface** — All domain operations (reads and writes) are registered as tools, declared via the `#[Tool]` attribute on provider methods. Write operations that need confirmation set `requires_confirmation: true` on the attribute rather than being registered in a separate handler bucket. This eliminates the ambiguity of choosing between `getToolHandlers`, `getTransactionHandlers`, and `getQueryHandlers`.

2. **Guards as a first-class primitive** — `AgentGuard` is a peer to intents and tools in the plugin SPI. Three fixed checkpoints (before-intent, before-execution, before-tool) give guard authors a clear place to attach rules without racing against each other.

3. **Single escalation source** — Model escalation is driven by config (`complex_intents` list, `confidence_escalation_threshold`) only. The LLM no longer returns an `escalate` flag, removing the dual-source conflict.

4. **Per-channel response formatting** — `ChannelAdapter::formatResponse()` translates an `AgentResponse` into a channel-native `OutboundMessage`. Tool handlers return plain data; they do not know or care which channel the user is on.

5. **Tenancy is optional** — `TenancyResolver` defaults to `NullTenancyResolver`. Single-tenant Laravel apps wire zero tenancy config and everything works. Multi-tenant apps supply their own resolver.

6. **Explicit error taxonomy** — Every failure mode has a code and a configurable user-facing message. Guards, tool handlers, and the pipeline use these codes consistently rather than inventing ad-hoc messages.

7. **Direct handlers bypass AI** — For operations where AI tool-calling compliance is poor (exports, downloads), direct handlers in the pipeline process the request without an LLM call.

8. **Cost tracking is opt-in** — `UpdateCostLedger` is not registered by default. Apps that want cost tracking register the listener and supply `model_rates` config.

9. **Session-based memory** — Messages are grouped into sessions by inactivity gaps. Old sessions are summarized via LLM and loaded as compact system messages. Token budget prevents context bloat.

10. **Event-driven observability** — Every API call emits `AgentCallCompleted`; the v2.3 surface adds `ToolInvocationCompleted`, `GuardDecision`, `ConfirmationGateTransitioned`, and `TurnCompleted`. All carry a `turn_id`, so a multi-step turn is fully reconstructable from SQL. Listeners handle persistence and cost accumulation; the same events are the export hook for external metrics backends.

11. **Attribute-driven tool definitions** — Tools are declared by annotating provider methods with `#[Tool]`. The `ToolScanner` derives the OpenAI JSON schema from PHP parameter types plus the attribute's `params` array and binds the method as the handler, so definition and handler cannot drift apart. Developers write only what the type system cannot infer (descriptions, enums, type overrides). `getToolDefinitions()` remains as an escape hatch for schemas too complex for attribute syntax (nested objects, `anyOf`, `$ref`) and takes precedence on a name clash.

12. **Secure by default** — Six attack surfaces (webhook spoofing, direct/indirect prompt injection, tool-argument injection, channel identity, resource/cost exhaustion) each map to one concrete defence wired into the pipeline, and the default config turns them on: enforced webhook verification, a default-on `JailbreakDetectionGuard`, schema-validated tool arguments, data-wrapped tool results, an enforced per-conversation cost cap, and a retention TTL with a prune command. Opting out is explicit, per-control, in config.

13. **Turn-traceable observability** — A `turn_id` propagates from each inbound message to every event, so the agent's multi-step behaviour is queryable end-to-end rather than call-by-call. Guard denials, confirmation-gate transitions, and tool executions (including the confirmation fast-path) are first-class events, closing the structural blind spots where the pipeline previously acted unobserved. Heavy capture (full LLM payloads, per-stage spans) is opt-in and TTL-bounded.

14. **Replay-grade capture is opt-in** — Full request/response payloads (`llm_payloads`) and per-stage spans (`agent_spans`) enable exact debugging via `emissary:replay [--re-run]`, but are storage-heavy and may contain user PII, so they default off. The TTL stance from v2.2 applies: bounded retention, not encrypted at rest by default, document the risk before enabling.

15. **Hybrid channel onboarding** — A single `ChannelCredentialStore` seam feeds `ChannelAdapter::verify()`/`send()`. The default reads `config/emissary.php` + env (single-app, zero ceremony); a DB-backed `EncryptedChannelCredentialStore` over the `ChannelConfig` table enables per-tenant provisioning at runtime. The pipeline is unaware of the source. Chat-channel user identity is established by an explicit `verify_identity` linking flow (`ChannelIdentityLink` + `LinkedChannelIdentityResolver`), not assumed. Channel credentials are secrets and are encrypted at rest — the opposite default to conversation content.

16. **Hybrid user onboarding, opt-in** — A built-in, config-driven flow handles first-contact (welcome → profile capture → consent) composed from existing primitives (`start_onboarding` intent, `OnboardingGuard`, `update_profile`/`accept_consent` tools). The identity model is hybrid: web users link existing accounts via `verify_identity`, channel-first users get a guest `User` created on first contact and upgraded after onboarding. **Channel-first guest creation is configurable via `onboarding.mode`** (`web_centric` = no guests, link-only; `channel_first`/`hybrid` = guest creation). The whole feature defaults off, so existing apps see no behaviour change. Consent is an enforceable guard precondition, not a cosmetic prompt.

17. **Testable by default** — The non-deterministic LLM and external channels are isolated behind a `Emissary\Testing\` toolkit: `FakeLlmClient` (scripted agent loops), `FakeChannelAdapter` (in-process webhook→reply), and a `Clock` fake make the pipeline deterministic. `AgentTestCase` gives plugin authors Pest assertions over tools, guards, events, and turn outcomes. Replay-as-fixture closes the loop with v2.3: production turns captured for `emissary:replay` are frozen into regression datasets by `emissary:fixture:capture` — incidents become tests, no extra instrumentation.

---

## Dependencies

- **PHP 8.3+** (readonly classes, named arguments, match expressions)
- **GuzzleHttp** — HTTP client for LLM API calls
- **OpenRouter API** (or any OpenAI-compatible API) — LLM inference
- **Carbon** — date/time handling
- **Laravel 11+** (for initial extraction; library targets framework-agnostic in v2)
- **Pest** *(dev only)* — the test toolkit ships Pest-first; PHPUnit shops can still use it since Pest runs on PHPUnit. The `Emissary\Testing\` namespace (`FakeLlmClient`, `FakeChannelAdapter`, `Clock`, `AgentTestCase`) is importable by host apps.

---

## Migration Path to Standalone Library

### Phase 1: Extract interfaces and DTOs
- Move all interfaces, DTOs, enums, and `AgentError` constants into `src/Contracts/` (namespace `Emissary\Contracts`)
- Ship the `Emissary\Testing\` test doubles (`FakeLlmClient`, `FakeChannelAdapter`, `Clock`) alongside the contracts so components are testable from the first extraction
- No behavioral changes

### Phase 2: Extract pipeline components
- Move `IntentRouter`, `ModelSelector`, `TaskAgent`, `ConversationMemory`, `ToolRegistry`, `ToolScanner`, `GuardRegistry`, `ConfirmationGate`
- Emit the typed observability events (`AgentCallCompleted`, `ToolInvocationCompleted`, `GuardDecision`, `ConfirmationGateTransitioned`, `TurnCompleted`, `UserOnboardingTransitioned`) with `turn_id` propagation
- Add `AgentTestCase` (Pest) + replay-as-fixture once `llm_payloads` capture exists
- Replace Laravel-specific dependencies (config, events) with injected configuration

### Phase 3: Framework-agnostic adapters
- Replace Laravel `Request`/`Response` with PSR-7
- Replace Laravel `Authenticatable` with a library-defined `UserContext` interface
- Replace Laravel Event dispatcher with PSR-14
- Replace Laravel Queue with a job interface
- Keep `ChannelCredentialStore` as the credential seam so adapters stay framework-agnostic; ship a config-backed default and leave DB-backed provisioning to the host app

### Phase 4: Package
- Composer package with minimal dependencies
- Laravel service provider for drop-in integration
- Standalone initialization for non-Laravel usage
- Future: first-party OpenTelemetry exporter listener (spans keyed by `turn_id`); no pipeline change required — the typed PSR-14 events are the stable seam
