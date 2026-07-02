# WAHA WhatsApp Adapter

> Spec version: 1.0 · Status: Draft · Source: user request

## Purpose

Migrate the WhatsApp channel from Meta's WhatsApp Business API to WAHA (WhatsApp HTTP API) as the default backend. WAHA is a self-hostable REST API that connects to WhatsApp Web — no Meta Business account, System User tokens, or webhook subscription required. The existing Meta adapter is preserved as an opt-in alternative (`MetaWhatsAppAdapter`) for users already on the Meta ecosystem, selectable via a single config key. Full WAHA session lifecycle (create, start, stop, status, QR code) is included via Artisan commands. Both WAHA free (single session) and WAHA Plus (multi-session) deployments are supported.

## User Scenarios & Testing

### Scenario 1: New user sets up WhatsApp via WAHA

1. Developer pulls and starts a WAHA Docker container alongside their application
2. Developer sets `.env`: `WAHA_API_URL=http://localhost:3000`, `WAHA_API_KEY=my-secret-key`
3. Developer runs `php artisan emissary:waha:session:start` — the command creates the session, configures the webhook URL (derived from `APP_URL`) and HMAC key on the session, starts it, polls its status, and displays a QR code when the session enters `SCAN_QR_CODE` state
4. Developer scans the QR code with their WhatsApp mobile app — the session transitions to `WORKING`
5. Developer runs `php artisan emissary:channel:test whatsapp` — a test message is sent and received on the connected phone
6. End user sends a WhatsApp message to the connected number → WAHA webhook fires → Emissary pipeline processes the message → the agent replies back through WAHA
7. If WAHA is unreachable during a send, the adapter returns an error response; the user sees a delivery-failure message rather than silent data loss

### Scenario 2: Existing Meta user migrates to WAHA

1. User changes `channels.whatsapp.backend` from `'meta'` to `'waha'` and updates `adapter` to `WahaWhatsAppAdapter::class`
2. User configures WAHA env vars and starts a WAHA container
3. User runs QR scan flow as in Scenario 1
4. All existing intents, tools, guards, and conversation history continue working unchanged — the pipeline is completely adapter-agnostic below the `ChannelAdapter` seam

### Scenario 3: Existing Meta user stays on Meta

1. User sets `channels.whatsapp.backend` to `'meta'` and `adapter` back to `MetaWhatsAppAdapter::class`
2. All existing Meta credentials (access token, phone number ID, app secret, verify token) continue to work exactly as before
3. Webhook GET handshake, HMAC-SHA256 verification, and Facebook Graph API send endpoint all preserved

### Scenario 4: WAHA Plus multi-tenant deployment

1. Admin provisions per-tenant WAHA sessions via `emissary:channel:add whatsapp --tenant=acme --waha-session=acme-session --waha-api-key=tenant-key`
2. Each tenant's session name and API key are stored in `EncryptedChannelCredentialStore`
3. The pipeline resolves the correct session per tenant via `ChannelCredentialStore::resolve(Channel::WhatsApp, $tenant)`
4. Multiple tenants share one WAHA Plus instance, each with an isolated session

### Scenario 5: WAHA webhook security with HMAC

1. Developer sets `WAHA_HMAC_KEY=shared-secret` in `.env`
2. When `emissary:waha:session:start` runs, it passes the HMAC key to WAHA's session webhook config under `hmac.key`
3. Inbound webhooks carrying a valid `X-Webhook-Hmac` header (HMAC-SHA512) pass verification and enter the pipeline
4. Inbound webhooks with an invalid or missing HMAC header are rejected with HTTP 401
5. When `WAHA_HMAC_KEY` is not set, HMAC verification is skipped entirely (dev/local/trusted-network mode)

### Scenario 6: WAHA free version guards

1. Developer sets `WAHA_VERSION=free` in `.env`
2. Running `emissary:waha:session:start custom-session` displays a warning and forces the session name to `"default"`
3. Running `emissary:waha:session:list` shows only the `default` session
4. All send/parse operations use the `default` session

### Scenario 7: Session lifecycle operations

1. `emissary:waha:session:status` shows `STOPPED` → developer runs `emissary:waha:session:start`
2. `emissary:waha:session:status` shows `SCAN_QR_CODE` → QR code is displayed for scanning
3. `emissary:waha:session:status` shows `WORKING` → adapter is ready to send and receive
4. `emissary:waha:session:status` shows `FAILED` → status output includes error details
5. `emissary:waha:session:stop` gracefully disconnects the session
6. `emissary:waha:session:restart` cycles the session (stop then start)

### Scenario 8: GET webhook returns 405 for WAHA backend

1. A `GET` request arrives at `POST /webhooks/whatsapp` while `backend = 'waha'`
2. The webhook controller returns HTTP 405 Method Not Allowed
3. This signals to former Meta users who forgot to switch `backend` that the endpoint has changed

## Functional Requirements

| # | Requirement | Acceptance Criteria |
|---|---|---|
| FR1 | `WahaWhatsAppAdapter` implements `ChannelAdapter` with `parse()`, `verify()`, `formatResponse()`, `send()` | All four methods exist with correct signatures matching the interface; Pest tests pass for each method |
| FR2 | Existing `WhatsAppAdapter` class renamed to `MetaWhatsAppAdapter`; all internal behavior preserved | All existing WhatsApp tests pass after updating class references; webhook, parse, verify, send unchanged |
| FR3 | Config key `channels.whatsapp.backend` selects between `'waha'` (default) and `'meta'` | Service provider binds the correct adapter class based on `backend` value at boot time |
| FR4 | WAHA credentials in config: `waha_api_url`, `waha_api_key`, `waha_session`, `waha_hmac_key`, `waha_version` | All five keys present in `config/emissary.php` under `channels.whatsapp` with `env()` fallbacks and sensible defaults |
| FR5 | WAHA `parse()` extracts `payload.from` as `conversationRef`, `payload.body` as `text`, and `payload.media` when `hasMedia` is true | Inbound WAHA webhook JSON produces a valid `InboundMessage` DTO with correct `Channel::WhatsApp` |
| FR6 | WAHA `verify()` validates HMAC-SHA512 signature when `waha_hmac_key` is configured; passes through when key is not set | HMAC validation test: valid signature passes, invalid signature returns false, missing key and no header passes |
| FR7 | WAHA `send()` POSTs to `{waha_url}/api/sendText` with `X-Api-Key` header, `session`, `chatId`, and `text` body; when `OutboundMessage.mediaUrl` is set, dispatches to the appropriate WAHA media endpoint (`sendImage`, `sendFile`, `sendVoice` depending on MIME type) | Outbound text and media messages reach WAHA instance; `FakeChannelAdapter` records the call for assertions |
| FR8 | Webhook `GET /webhooks/whatsapp` returns HTTP 405 when `backend = 'waha'` | Integration test confirms 405 status; pipeline is never entered on GET |
| FR9 | Webhook `POST /webhooks/whatsapp` resolves the correct adapter (WAHA or Meta) based on config and calls `verify()` then `parse()` | Controller test with both backends confirms correct adapter is used per config setting |
| FR10 | `emissary:waha:session:start [session]` creates a WAHA session (if needed), configures the webhook URL (from `APP_URL` + `webhook_path`) and HMAC key (from `waha_hmac_key`) on the session, starts it, polls status, and displays QR code when state is `SCAN_QR_CODE` | Command exits 0 on `WORKING` status; QR code rendered to console when in `SCAN_QR_CODE` state; webhook URL and HMAC are present in the WAHA session config after start |
| FR11 | `emissary:waha:session:status [session]` prints the current session state and QR code when applicable | Output shows one of: `STOPPED`, `STARTING`, `SCAN_QR_CODE` (with QR), `WORKING`, `FAILED` (with error) |
| FR12 | `emissary:waha:session:stop [session]` stops a running WAHA session | Command exits 0 on success; reports API error details on failure |
| FR13 | `emissary:waha:session:restart [session]` stops then starts the session | Session cycles through `STOPPED` → `STARTING` → `SCAN_QR_CODE`/`WORKING` |
| FR14 | `emissary:waha:session:qr [session]` fetches and displays the raw QR code for manual scanning | QR string printed to console; error if session is not in `SCAN_QR_CODE` state |
| FR15 | `emissary:waha:session:list` lists all sessions and their statuses via WAHA API | JSON or table output showing session name and status for each |
| FR16 | `emissary:waha:session:delete [session]` deletes a session from WAHA | Command exits 0 on success; warns if session is currently `WORKING` |
| FR17 | WAHA free version constraint: when `waha_version = 'free'`, session name is forced to `'default'` | Commands display a warning if a custom session name is provided in free mode; actual API call uses `'default'` |
| FR18 | WAHA Plus multi-session: per-tenant session names and API keys via `EncryptedChannelCredentialStore` | `ChannelCredentialStore::resolve(Channel::WhatsApp, $tenant)` returns credentials scoped to the tenant with the correct session |
| FR19 | `ChannelCredentials.extra` carries WAHA-specific fields when `backend = 'waha'` | `extra` array contains `waha_api_url`, `waha_session`, `waha_version` keys accessible by the adapter |
| FR20 | `emissary:channel:test whatsapp` with `backend = 'waha'` verifies WAHA API connectivity, checks session status, and sends a test message | Command confirms session is `WORKING` or reports the current state; test message is delivered |
| FR21 | `emissary:channel:add whatsapp` supports `--waha-session` and `--waha-api-key` options for WAHA Plus provisioning | Interactive prompt collects WAHA credentials; data stored encrypted in `EncryptedChannelCredentialStore` |
| FR22 | WAHA `send()` constructs `chatId` as `{phone_number}@c.us` from the `$channelRef` (the `from` field from inbound `parse()`) | Outbound message uses the correct WAHA chat ID format; user receives the reply |
| FR23 | WAHA inbound messages with `fromMe: true` (outbound echo) are silently dropped and do not enter the pipeline | `verify()` or `parse()` returns early when `payload.fromMe === true`; no pipeline entry, no duplicate replies |
| FR24 | WAHA adapter logs all API errors as structured log entries with error context | Failed `send()` or session API calls produce log entries with status code, response body, and channel context |
| FR25 | When WAHA `send()` fails (unreachable, session stopped, API error), the adapter returns an `AgentResponse::fromError(AgentError::CHANNEL_DELIVERY_FAILED)` so the user receives a delivery-failure message | Pipeline emits a `TurnCompleted` event with the error code; user sees the configured error message on their next interaction rather than silent failure |
| FR26 | WAHA `send()` supports media types that the Meta adapter supports: images (`image/jpeg`, `image/png`), documents (`application/pdf`), and audio (`audio/ogg`); dispatches to `sendImage`, `sendFile`, or `sendVoice` based on MIME type | Sending an `OutboundMessage` with a `mediaUrl` reaches the correct WAHA media endpoint; fake records the correct endpoint used |
| FR27 | WAHA `formatResponse()` maps `OutboundMessage.quickReplies` to WAHA's interactive button/reply format via `channelExtras`, achieving interactive parity with the Meta adapter | An `AgentResponse` with `quickReplies` produces a WAHA `OutboundMessage` whose `channelExtras` contains valid WAHA button payload; fake captures the interactive formatting |
| FR28 | `AgentError::CHANNEL_DELIVERY_FAILED` is added as a new error constant with a default configurable message for channel send failures | The constant exists in `AgentError`; `config/emissary.php` includes a default message under `error_messages`; FR25 uses this code |

## Success Criteria

1. **Zero Meta dependency for new installs** — A fresh Emissary install can send and receive WhatsApp messages using only Docker, WAHA, and five env vars. No Meta Business account, System User token, or Facebook app required.
2. **Existing Meta users unaffected** — Current WhatsApp users who set `backend = 'meta'` and reference `MetaWhatsAppAdapter` experience zero behavior change. All existing env vars and webhook configurations continue to work.
3. **Session setup under 2 minutes** — From WAHA container start to first successful test message via `emissary:channel:test whatsapp`, including QR code scan and session transition to `WORKING`.
4. **Full test coverage via fakes** — All WAHA adapter methods and session management commands are tested using `FakeChannelAdapter::waha()` and an HTTP test double for the WAHA API. No live WAHA instance is required in the test suite.
5. **Observability parity** — WAHA adapter emits the same `turn_id`-tagged events (`AgentCallCompleted`, `ToolInvocationCompleted`, `TurnCompleted`) as the Meta adapter. The replay and fixture tools work identically regardless of backend.
6. **Session lifecycle complete** — All six session states (`STOPPED`, `STARTING`, `SCAN_QR_CODE`, `WORKING`, `FAILED`) are observable, commandable, and testable. No manual WAHA dashboard interaction required after initial Docker setup.

## Key Entities

- **`WahaWhatsAppAdapter`** — New `ChannelAdapter` implementation that parses WAHA webhook payloads, verifies HMAC-SHA512 signatures, formats agent responses, and sends via WAHA's REST API
- **`MetaWhatsAppAdapter`** — Renamed existing adapter (formerly `WhatsAppAdapter`); unchanged behavior for Meta WhatsApp Business API integration
- **`WahaClient`** — Thin HTTP client wrapping WAHA's session management API (create, start, stop, status, QR code, screenshot, me, delete, list)
- **`ChannelCredentials.extra`** — Extension point on the existing `ChannelCredentials` DTO carrying backend-specific fields (`waha_api_url`, `waha_session`, `waha_version`)
- **`WahaSessionState`** — Enum representing the five WAHA session states: `STOPPED`, `STARTING`, `SCAN_QR_CODE`, `WORKING`, `FAILED`
- **`AgentError::CHANNEL_DELIVERY_FAILED`** — New error constant for channel adapter send failures, distinct from LLM and tool errors

## Assumptions

1. WAHA instance is network-reachable from the Laravel application (same host, same Docker network, or routable URL).
2. WAHA's API is stable; the adapter targets the current WAHA API surface as documented at `devlikeapro/waha`. No API version negotiation is needed.
3. WAHA free version's single-session constraint is enforced at the command and config validation layer, not inside the adapter itself. The adapter issues whatever session name it resolves from the credential store.
4. QR code scanning is a one-time setup step per session. Session state persists across WAHA container restarts provided WAHA's data volume is mounted.
5. HMAC webhook verification uses a shared secret present in both WAHA's session webhook config (`hmac.key`) and Emissary's config (`waha_hmac_key`).
6. The `Channel` enum value `WhatsApp` does not change — both WAHA and Meta serve the same logical channel. Backend selection is a config concern, not a channel identity concern.
7. WAHA Plus supports arbitrary session names; the adapter does not enforce naming conventions beyond what the credential store provides.
8. Inbound WAHA webhook messages with `fromMe: true` are outbound echoes and should not re-enter the pipeline.

## Clarifications

### Session 2026-07-01

- Q: What happens when WAHA `send()` fails (e.g., WAHA unreachable, session stopped, API error)? → A: Return an `AgentResponse::fromError()` so the user sees a "couldn't deliver" message on their next interaction. The pipeline handles the error reply via the standard error-message path.
- Q: Should `emissary:waha:session:start` automatically configure the webhook URL + HMAC on the WAHA session? → A: Yes. The command derives the webhook URL from `APP_URL` + `webhook_path` and passes it (plus `waha_hmac_key` if set) to WAHA's session start API. No manual WAHA config needed.
- Q: Should the WAHA adapter support sending media (images, documents, audio) or be text-only initially? → A: Full media send parity with Meta adapter. WAHA `send()` dispatches to the appropriate WAHA endpoint (`sendImage`, `sendFile`, `sendVoice`, etc.) when `OutboundMessage.mediaUrl` is set.
- Q: Should WAHA's `formatResponse()` map `quickReplies` to WAHA's interactive message format (buttons/lists) or be text-only? → A: Full interactive parity. `formatResponse()` maps `quickReplies` to WAHA's button/reply format via `channelExtras`. WAHA users get the same interactive experience as Meta users.
- Q: Should a new error code be added for channel send failures, vs reusing an existing code like `LLM_ERROR`? → A: Add `AgentError::CHANNEL_DELIVERY_FAILED` with a default message "I couldn't deliver that message. Please try again." Used exclusively for channel send failures.

## Dependencies

- **WAHA Docker container** (`devlikeapro/waha`) — self-hosted by the user; Emissary does not manage WAHA's container lifecycle
- **GuzzleHttp** — already a project dependency for HTTP calls to LLM APIs; reused for WAHA API calls
- **Laravel's Encrypter** — already used for `EncryptedChannelCredentialStore`; reused for WAHA API keys at rest

## Out of Scope

- WAHA container provisioning (Dockerfile, docker-compose, health checks) — infrastructure concern, not library code
- WhatsApp Business API (On-Premises / BSP model) — only WAHA and Meta Cloud API are supported
- Auto-reconnect or session health monitoring in the pipeline — session lifecycle is a CLI concern; the adapter assumes a working session
- Per-message session selection — one session per channel configuration; routing between multiple sessions for the same tenant is not supported
- Migration of existing Meta webhook subscriptions to WAHA — users migrate manually by changing config and restarting
