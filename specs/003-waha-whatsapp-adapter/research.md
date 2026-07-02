# WAHA WhatsApp Adapter — Research & Decisions

> Resolved: 2026-07-01 · All unknowns resolved · 0 NEEDS CLARIFICATION remaining

## 1. WAHA API Surface

**Decision**: Target the stable WAHA REST API endpoints as documented at `devlikeapro/waha`. No API version negotiation; the adapter targets the current API shape.

**Rationale**: WAHA is a self-hosted API that evolves with WhatsApp Web. The endpoints for send (`/api/sendText`, `/api/sendImage`, `/api/sendFile`, `/api/sendVoice`), session management (`/api/sessions/`), and auth (`/api/{session}/auth/qr`) have been stable across recent releases. Version negotiation would add complexity with no practical benefit.

**Alternatives considered**: Abstracting behind a WAHA version adapter — rejected because WAHA doesn't expose API version headers. The user controls the WAHA version by choosing a Docker tag; breaking changes are handled by updating the adapter.

**Source**: Context7 query of `/devlikeapro/waha-docs` (848+ snippets).

## 2. Backend Selection Mechanism

**Decision**: Use a config key `channels.whatsapp.backend` (`'waha'` | `'meta'`) rather than a separate `Channel` enum value. The service provider binds the correct adapter class at boot.

**Rationale**: The `Channel::WhatsApp` enum value represents the logical channel. Both WAHA and Meta serve WhatsApp — they're backends, not distinct channels. A `backend` key keeps the Channel enum clean and lets the service provider swap implementations without any pipeline changes. The `adapter` config key becomes the resolved class name; the `backend` key is the human-readable toggle.

**Alternatives considered**:
- Separate `Channel::WahaWhatsApp` and `Channel::MetaWhatsApp` enum values — rejected because it would require every pipeline component, guard, and tool to be aware of two WhatsApp channels, doubling the cognitive overhead.
- Removing the Meta adapter entirely — rejected per user requirement to preserve backward compatibility.

## 3. WAHA Credential Mapping to ChannelCredentials

**Decision**: Map WAHA credentials into the existing `ChannelCredentials` DTO: `verifySecret` = `waha_hmac_key`, `accessToken` = `waha_api_key`, `senderId` = `null` (WAHA doesn't use it), `handshakeToken` = `null` (WAHA has no GET handshake), `extra` = `['waha_api_url' => ..., 'waha_session' => ..., 'waha_version' => ...]`.

**Rationale**: The `ChannelCredentials` DTO is a channel-agnostic envelope. The four main fields map to concepts every channel has (or can leave null). WAHA-specific fields go into the `extra` array which was designed for this purpose. This zero-change approach to the DTO maintains backward compatibility for all existing consumers.

**Alternatives considered**:
- Creating a `WahaChannelCredentials` subclass — rejected because it would require the credential store to return different types per backend, complicating the `ChannelCredentialStore::resolve()` return type.
- Adding WAHA-specific fields to `ChannelCredentials` — rejected because it would pollute the DTO with backend-specific fields that other channels (Telegram, Web) must carry as null.

## 4. HMAC Verification Strategy

**Decision**: WAHA webhook verification uses HMAC-SHA512 (`X-Webhook-Hmac` header) instead of Meta's HMAC-SHA256 (`X-Hub-Signature-256`). Verification is enforced when `waha_hmac_key` is set and skipped when not set (dev/trusted-network mode).

**Rationale**: WAHA's webhook HMAC uses SHA-512 and sends two headers: `X-Webhook-Hmac-Algorithm: sha512` and `X-Webhook-Hmac: <signature>`. The adapter checks the algorithm header and validates against the shared secret. Skipping verification when the key is unset makes local development frictionless while encouraging production deployments to enable it.

**Test vector** from WAHA docs:
```
Body: {"event":"message","session":"default","engine":"WEBJS"}
Secret: my-secret-key
Algorithm: sha512
Signature: 208f8a55dde9e05519e898b10b89bf0d0b3b0fdf11fdbf09b6b90476301b98d8097c462b2b17a6ce93b6b47a136cf2e78a33a63f6752c2c1631777076153fa89
```

**Alternatives considered**: Always require HMAC (fail-closed) — rejected because WAHA free users often run on localhost or private Docker networks where HMAC adds ceremony without security benefit. The decision to skip when unset matches the "secure by default, permissive when explicit" pattern.

## 5. WAHA Free vs Plus Session Handling

**Decision**: Enforce single-session constraint at the command and config layer. The `WahaWhatsAppAdapter` and `WahaClient` accept whatever session name they're given — they do not enforce the free/plus distinction.

**Rationale**: Separation of concerns. The adapter should not need to know about WAHA licensing. The constraint logic lives in the `EmissaryWahaSessionStart` command (warns and forces `'default'` when `waha_version = 'free'`) and in `ChannelCredentialStore` resolution (single session for free, per-tenant for plus). This keeps the adapter simple and makes the constraint testable at the command level.

**Alternatives considered**: Enforcing in the adapter — rejected because the adapter should work with any session name the credential store provides. If a user upgrades from free to plus, the adapter should not need code changes.

## 6. fromMe Echo Filtering

**Decision**: WAHA webhook payloads include `fromMe: true` for outbound messages (echoes of what the adapter sent). The adapter must skip these in `parse()` to prevent infinite loops.

**Rationale**: When the adapter sends a message via WAHA, WAHA's webhook fires back with the same message marked `fromMe: true`. Without filtering, this re-enters the pipeline, the agent responds again, creating an infinite loop. The adapter returns `null` or a sentinel value from `parse()` when `fromMe === true`, and the pipeline skips processing.

**Alternatives considered**: Filtering at the webhook controller level — rejected because `fromMe` is WAHA-specific logic. The adapter is the right place since it knows the WAHA payload format. Other channels may have different echo patterns.

## 7. Media Send Routing

**Decision**: WAHA `send()` inspects `OutboundMessage.mediaUrl` and dispatches to the appropriate WAHA endpoint:
- No media → `POST /api/sendText`
- Image MIME types → `POST /api/sendImage`
- Document/PDF → `POST /api/sendFile`
- Audio → `POST /api/sendVoice`

**Rationale**: WAHA has separate endpoints per media type (no unified send endpoint). The adapter determines the endpoint from the MIME type of the media URL or from explicit `channelExtras` hints. This matches the Meta adapter's behavior which also dispatches to different endpoints per media type.

**Alternatives considered**: Single `sendMessage` endpoint with type detection — rejected because WAHA's API doesn't offer one. Text-only initially — rejected per clarification (full media parity required).

## 8. Interactive Message (Quick Replies) Formatting

**Decision**: WAHA `formatResponse()` maps `OutboundMessage.quickReplies` to WAHA's interactive button format stored in `channelExtras`. The WAHA button payload uses the `buttons` array format.

**Rationale**: WAHA supports interactive messages with buttons (up to 3 buttons). The `formatResponse()` method is the designated channel-formatting seam (`specs/02-contracts.md` Principle 8). The adapter translates the channel-agnostic `quickReplies` into WAHA-specific JSON in `channelExtras`, which `send()` passes through to the WAHA API.

**WAHA button format**:
```json
{
  "chatId": "123@c.us",
  "text": "Choose an option:",
  "buttons": [
    {"buttonId": "opt1", "text": "Option 1"},
    {"buttonId": "opt2", "text": "Option 2"}
  ]
}
```

**Alternatives considered**: Text-only numbered list — rejected per clarification. WAHA native lists — considered but buttons are simpler and more universal.

## 9. Webhook URL Auto-Registration

**Decision**: `emissary:waha:session:start` configures the webhook URL + HMAC key on the WAHA session automatically using `APP_URL` + `webhook_path` + `waha_hmac_key`.

**Rationale**: Previously out of scope; moved into scope per clarification. WAHA's session start API accepts `webhooks[].url` and `webhooks[].hmac.key` in the config. The command derives the full webhook URL from existing config values (`APP_URL` and `webhook_path`), eliminating a manual setup step.

**Alternatives considered**: Separate `emissary:waha:webhook:register` command — rejected in favor of bundling into `session:start` for a single-command setup flow.

## 10. Chat ID Format

**Decision**: WAHA chat IDs use the format `{phone_number}@c.us` (e.g., `12345678901@c.us`). The adapter constructs this from the `conversationRef` returned by `parse()` (which is the `payload.from` field from WAHA — already in `@c.us` format).

**Rationale**: WAHA's `payload.from` already includes the `@c.us` suffix in incoming webhooks. The adapter uses this value directly as the chat ID for outbound sends. No suffix manipulation is needed; the inbound `from` and outbound `chatId` are the same string.

**Alternatives considered**: Stripping and re-adding `@c.us` — unnecessary since WAHA returns the full qualified chat ID.

## 11. Error Code Naming

**Decision**: New error constant `AgentError::CHANNEL_DELIVERY_FAILED = 'channel.delivery_failed'` with default message "I couldn't deliver that message. Please try again."

**Rationale**: Existing codes (`LLM_ERROR`, `TOOL_EXECUTION_FAILED`) are semantically wrong for a channel adapter failing to deliver a message. A dedicated code lets host apps customize messaging and observability dashboards distinguish "AI failed" from "reply never arrived."

**Alternatives considered**: Reusing `LLM_ERROR` — rejected because it conflates LLM and channel failures. Reusing `TOOL_EXECUTION_FAILED` — rejected because send is not a tool invocation.

## 12. Config Key Naming Convention

**Decision**: WAHA config keys use the `waha_` prefix under `channels.whatsapp` to namespace them: `waha_api_url`, `waha_api_key`, `waha_session`, `waha_hmac_key`, `waha_version`.

**Rationale**: Flat key names like `api_url` would be ambiguous (which API?). The `waha_` prefix clearly scopes them to the WAHA backend and avoids collisions with Meta keys (`access_token`, `phone_number_id`) which live under the same `channels.whatsapp` array.

**Alternatives considered**: Nested `waha` key — would create asymmetry with Meta keys at the top level. Flat with prefix is simpler and works well with `env()` fallbacks.
