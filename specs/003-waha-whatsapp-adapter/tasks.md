# WAHA WhatsApp Adapter — Tasks

> Plan: [plan.md](plan.md) · Spec: [spec.md](spec.md) · Date: 2026-07-01

## Summary

Replace the default WhatsApp adapter from Meta's Business API to WAHA (WhatsApp HTTP API). Preserve the existing Meta adapter as `MetaWhatsAppAdapter`. Add a `WahaWhatsAppAdapter`, `WahaClient`, `WahaSessionState` enum, 7 Artisan commands, and a new `CHANNEL_DELIVERY_FAILED` error constant. ~27 file changes across 3 user stories.

## Task Organization

Phases reflect dependency order. Tasks marked `[P]` are parallel-safe (independent files, no shared state). User story tasks carry the `[US1]`, `[US2]`, `[US3]` labels for traceability.

---

## Phase 1 — Setup (Shared Foundation)

**Goal**: Configuration, error taxonomy, and foundational types exist before any adapter or command is built.

**Independent test**: `config('emissary.channels.whatsapp.backend')` returns `'waha'`. `AgentError::CHANNEL_DELIVERY_FAILED` exists.

- [x] T001 [P] Add `CHANNEL_DELIVERY_FAILED` constant to `src/AgentError.php` with value `'channel.delivery_failed'`
- [x] T002 [P] Create `WahaSessionState` enum in `src/WahaSessionState.php` with cases: `Stopped` (`'STOPPED'`), `Starting` (`'STARTING'`), `ScanQrCode` (`'SCAN_QR_CODE'`), `Working` (`'WORKING'`), `Failed` (`'FAILED'`); add static `fromApiResponse(string $status): self` factory method using match expression
- [x] T003 Update `config/emissary.php` channels.whatsapp section: add `backend` key (default `'waha'`); add 5 WAHA keys (`waha_api_url`, `waha_api_key`, `waha_session`, `waha_hmac_key`, `waha_version`) with env fallbacks; change default `adapter` to `\Emissary\Channels\WahaWhatsAppAdapter::class`; preserve all Meta keys; add `channel.delivery_failed` entry under `error_messages` with default text `'I couldn\'t deliver that message. Please try again.'`
- [x] T004 Update `src/EmissaryServiceProvider.php` to bind channel adapter based on `channels.whatsapp.backend` config: resolve to `WahaWhatsAppAdapter::class` when `'waha'`, `MetaWhatsAppAdapter::class` when `'meta'`; keep existing singleton bindings unchanged

---

## Phase 2 — US2: Meta Adapter Preservation (P1)

**Story**: As an existing Emissary user of the Meta WhatsApp Business API, I want my WhatsApp integration to continue working exactly as before after the rename, so that I can choose when (or if) to migrate to WAHA.

**Independent test**: All original WhatsApp adapter tests pass with updated class name. `MetaWhatsAppAdapter` parses Meta webhook payloads, verifies HMAC-SHA256, performs GET handshake, and sends via Facebook Graph API identically to the original.

### Rename

- [x] T005 Rename `src/Channels/WhatsAppAdapter.php` to `src/Channels/MetaWhatsAppAdapter.php`; update class name to `MetaWhatsAppAdapter`; update namespace references; no behavioral changes
- [x] T006 Rename `tests/Unit/WhatsAppAdapterTest.php` to `tests/Unit/MetaWhatsAppAdapterTest.php`; update all `WhatsAppAdapter` references to `MetaWhatsAppAdapter` in test file; update use imports; all test logic unchanged

### Reference Updates

- [x] T007 [P] [US2] Update all source files referencing `WhatsAppAdapter` class to use `MetaWhatsAppAdapter`: `src/Http/WebhookController.php`, `src/Commands/EmissaryChannelTest.php`, `src/Commands/EmissaryWebhookUrl.php`, `src/Commands/EmissaryChannelAdd.php`
- [x] T008 [P] [US2] Update all test files referencing `WhatsAppAdapter` class to use `MetaWhatsAppAdapter`: `tests/Unit/FakeChannelAdapterTest.php`, `tests/Unit/ChannelCredentialStoreTest.php`, `tests/Unit/DtoTest.php`, `tests/Unit/NullTenancyResolverTest.php`, `tests/Unit/AuthChannelIdentityResolverTest.php`

### Verification

- [x] T009 [US2] Run `composer test -- --filter="MetaWhatsAppAdapter"` and confirm all 4 original WhatsApp adapter tests pass unchanged

---

## Phase 3 — US1: WAHA Adapter Core (P1)

**Story**: As a new Emissary user, I want to send and receive WhatsApp messages using WAHA instead of Meta's API, so that I can connect to WhatsApp without a Meta Business account or token provisioning.

**Independent test**: `FakeChannelAdapter::waha()` → `parse()` produces correct `InboundMessage` from WAHA webhook JSON (text, media, fromMe skip) → `verify()` validates HMAC-SHA512 or passes when key unset → `send()` reaches correct WAHA endpoint → `formatResponse()` maps quickReplies to WAHA buttons → send failure returns `AgentResponse::fromError(CHANNEL_DELIVERY_FAILED)`.

### WAHA HTTP Client

- [x] T010 [P] [US1] Create `src/Waha/WahaClient.php` with constructor `(private string $apiUrl, private string $apiKey)`. Implement `createSession(string $name, ?string $webhookUrl, ?string $hmacKey): array` — POST to `/api/sessions/` with optional webhook config. Implement `startSession(string $name, ?string $webhookUrl, ?string $hmacKey): array` — POST to `/api/sessions/start` with session name + optional webhook/hmac config. Implement `stopSession(string $name): array` — POST to `/api/sessions/{name}/stop`. Implement `restartSession(string $name, ?string $webhookUrl, ?string $hmacKey): array` — stop then start. Implement `getStatus(string $name): WahaSessionState` — GET `/api/sessions/{name}`, map status string to enum via `WahaSessionState::fromApiResponse()`. Implement `getQrCode(string $name, string $format = 'raw'): ?string` — GET `/api/{name}/auth/qr?format={format}`, return raw QR string or null if not in SCAN_QR_CODE state. Implement `deleteSession(string $name): array` — DELETE to `/api/sessions/{name}`. Implement `listSessions(): array` — GET `/api/sessions/`. Implement `getScreenshot(string $session): string` — GET `/api/screenshot`. Implement `getMe(string $session): array` — GET `/api/sessions/{session}/me`. Use GuzzleHttp; set `X-Api-Key` header on all requests; handle non-200 responses with structured log entries.

### WAHA Adapter

- [x] T011 [US1] Create `src/Channels/WahaWhatsAppAdapter.php` implementing `ChannelAdapter`. Constructor: `(private ChannelCredentialStore $credentialStore)`. Implement `parse(Request $request): InboundMessage` — read JSON body, extract `payload.from` as `$conversationRef`, `payload.body` as `$text`, `payload.media` when `hasMedia` is true; set `Channel::WhatsApp`; skip and return early when `payload.fromMe === true` (echo filtering, FR23).
- [x] T012 [US1] Implement `verify(Request $request): bool` in `WahaWhatsAppAdapter`. Resolve `ChannelCredentials` for `Channel::WhatsApp`; if `waha_hmac_key` (from `credentials->verifySecret`) is empty/null, return true (passes unverified, FR6). Otherwise read `X-Webhook-Hmac` and `X-Webhook-Hmac-Algorithm` headers; validate HMAC-SHA512 against raw request body using `hash_equals()`. Return false on missing header or mismatch.
- [x] T013 [US1] Implement `send(string $channelRef, OutboundMessage $message): void` in `WahaWhatsAppAdapter`. Resolve credentials; build WAHA base URL from `extra['waha_api_url']`. If `OutboundMessage.mediaUrl` is set, determine MIME type and dispatch: `image/jpeg` or `image/png` → POST to `/api/sendImage`, `application/pdf` → POST to `/api/sendFile`, `audio/ogg` → POST to `/api/sendVoice`. Otherwise POST to `/api/sendText`. All requests include: `X-Api-Key` header (from `credentials->accessToken`), JSON body with `session` (from `extra['waha_session']`), `chatId` (from `$channelRef`), `text`/`media` content. On Guzzle `\Throwable` or non-2xx response, log the error and return `AgentResponse::fromError(AgentError::CHANNEL_DELIVERY_FAILED, ...)` (FR25).
- [x] T014 [US1] Implement `formatResponse(AgentResponse $response): OutboundMessage` in `WahaWhatsAppAdapter`. If `$response->toolCalls` includes quick replies (derived from agent output), map them to WAHA button format: create `channelExtras` with `buttons` array where each entry has `buttonId` and `text` from `quickReplies`. Return `new OutboundMessage(text: $response->content, channelExtras: $buttons ?? null)` (FR27).

### Webhook Controller Update

- [x] T015 [US1] Update `src/Http/WebhookController.php` `whatsapp()` method: resolve the active adapter dynamically (not hardcoded `WhatsAppAdapter`). For GET requests: return HTTP 405 when backend is `'waha'` (FR8); dispatch to `MetaWhatsAppAdapter::handshake()` when backend is `'meta'`. For POST requests: call `verify()` then `parse()` on the resolved adapter.

### Fake Channel Adapter

- [x] T016 [P] [US1] Add static factory `FakeChannelAdapter::waha(?string $conversationRef = null): self` in `src/Testing/FakeChannelAdapter.php`. Creates fake with `Channel::WhatsApp`, default `conversationRef` `'12345678901@c.us'`. Configure `parse()` to return WAHA-shaped `InboundMessage` with `from` in `@c.us` format. Record sent messages with WAHA-specific payload shape.

### Tests

- [x] T017 [P] [US1] Create `tests/Unit/WahaSessionStateTest.php`. Test: `fromApiResponse('STOPPED')` returns `Stopped`, `fromApiResponse('STARTING')` returns `Starting`, `fromApiResponse('SCAN_QR_CODE')` returns `ScanQrCode`, `fromApiResponse('WORKING')` returns `Working`, `fromApiResponse('FAILED')` returns `Failed`. Test unknown status throws `\ValueError`.
- [x] T018 [US1] Create `tests/Unit/WahaWhatsAppAdapterTest.php`. Test parse: extracts `payload.from` as conversationRef and `payload.body` as text from WAHA webhook JSON; extracts `payload.media.url` when `hasMedia` is true; skips `fromMe: true` messages (returns no InboundMessage). Test verify: valid HMAC-SHA512 passes using WAHA test vector; invalid signature returns false; missing `waha_hmac_key` returns true. Test send: POST reaches correct `sendText` endpoint with correct headers; POST reaches `sendImage` for image media; POST reaches `sendFile` for document media. Test formatResponse: quickReplies produce WAHA button `channelExtras`. Test send failure: Guzzle exception returns `AgentResponse::fromError(CHANNEL_DELIVERY_FAILED)`.
- [x] T019 [P] [US1] Create `tests/Unit/WahaClientTest.php`. Test: `createSession` sends correct POST body to `/api/sessions/`; `startSession` sends correct POST body with webhook URL + HMAC to `/api/sessions/start`; `stopSession` sends POST to correct endpoint; `getStatus` maps WAHA response to correct `WahaSessionState`; `getQrCode` returns raw QR string; `deleteSession` sends DELETE; `listSessions` returns array from GET response. Use HTTP fake/mock to intercept Guzzle requests.

### Verification

- [x] T020 [US1] Run `composer test -- --filter="(WahaWhatsAppAdapter|WahaClient|WahaSessionState)"` and confirm all new tests pass
- [x] T021 [US1] Run `composer test -- --filter="FakeChannelAdapter.*waha"` and confirm WAHA factory works end-to-end

---

## Phase 4 — US3: WAHA Session Management (P2)

**Story**: As a developer deploying Emissary, I want Artisan commands to manage WAHA sessions (start, stop, status, QR code, list, restart, delete), so that I can set up and maintain WhatsApp connectivity entirely from the CLI without touching the WAHA dashboard.

**Independent test**: Each WAHA session command produces expected output and exits with correct status code. Free mode forces `'default'` session name. `emissary:channel:test` and `emissary:channel:add` work with WAHA backend.

### Session Commands

- [x] T022 [P] [US3] Create `src/Commands/EmissaryWahaSessionStart.php`. Command `emissary:waha:session:start {session?}`. Read `waha_api_url`, `waha_api_key`, `waha_session`, `waha_hmac_key`, `waha_version` from config. Force session name to `'default'` and display warning when `waha_version = 'free'` and a custom name is provided (FR17). Instantiate `WahaClient`, call `startSession()` with webhook URL (derived from `config('app.url')` + `config('emissary.webhook_path')` + `/whatsapp`) and HMAC key. Poll `getStatus()` in a loop until `WORKING` or `FAILED`. Display QR code when `SCAN_QR_CODE` state. Exit 0 on `WORKING`, exit 1 on `FAILED`.
- [x] T023 [P] [US3] Create `src/Commands/EmissaryWahaSessionStatus.php`. Command `emissary:waha:session:status {session?}`. Resolve session name from argument or config default. Call `WahaClient::getStatus()` and print state. Display QR code via `getQrCode()` when state is `SCAN_QR_CODE` (FR11).
- [x] T024 [P] [US3] Create `src/Commands/EmissaryWahaSessionStop.php`. Command `emissary:waha:session:stop {session?}`. Call `WahaClient::stopSession()` and report success or API error details (FR12).
- [x] T025 [P] [US3] Create `src/Commands/EmissaryWahaSessionRestart.php`. Command `emissary:waha:session:restart {session?}`. Call `WahaClient::stopSession()` then `startSession()`. Report state transitions (FR13).
- [x] T026 [P] [US3] Create `src/Commands/EmissaryWahaSessionQr.php`. Command `emissary:waha:session:qr {session?}`. Call `WahaClient::getQrCode()` and print raw QR string. Display error if session is not in `SCAN_QR_CODE` state (FR14).
- [x] T027 [P] [US3] Create `src/Commands/EmissaryWahaSessionList.php`. Command `emissary:waha:session:list`. Call `WahaClient::listSessions()` and output table with session name and status (FR15).
- [x] T028 [P] [US3] Create `src/Commands/EmissaryWahaSessionDelete.php`. Command `emissary:waha:session:delete {session}`. Check session status via `getStatus()` first; warn if `WORKING`. Call `deleteSession()` and report success or failure (FR16).

### Update Existing Commands

- [x] T029 [P] [US3] Update `src/Commands/EmissaryChannelTest.php`: when `config('emissary.channels.whatsapp.backend')` is `'waha'`, first check WAHA API connectivity via `WahaClient::getStatus()`, then send test message through the WAHA adapter. When `'meta'`, use existing Meta logic unchanged (FR20).
- [x] T030 [P] [US3] Update `src/Commands/EmissaryChannelAdd.php`: add `--waha-session` and `--waha-api-key` CLI options; in interactive mode, prompt for WAHA credentials when channel is `whatsapp` and backend is `'waha'`; store in `EncryptedChannelCredentialStore` with WAHA-specific `extra` fields (FR21).

### Tests

- [x] T031 [US3] Create `tests/Unit/WahaCommandTest.php`. Test `waha:session:start`: resolves session name, enforces `'default'` in free mode with warning, polls status, displays QR on `SCAN_QR_CODE`, exits 0 on `WORKING`. Test `waha:session:status`: prints state string. Test `waha:session:stop`: calls stop and reports. Test `waha:session:restart`: calls stop then start. Test `waha:session:qr`: prints raw QR string. Test `waha:session:list`: outputs table. Test `waha:session:delete`: warns when `WORKING`. All tests use HTTP fake via `Http::fake()` to mock WAHA API responses.
- [x] T032 [P] [US3] Update `tests/Unit/FakeChannelAdapterTest.php`: add test for `FakeChannelAdapter::waha()` factory — verifies `Channel::WhatsApp`, WAHA-specific `conversationRef` format, send records WAHA payload shape.

### Verification

- [x] T033 [US3] Run `composer test -- --filter="(WahaCommand|EmissaryChannelTest)"` and confirm all command tests pass

---

## Phase 5 — Polish & Cross-Cutting

**Goal**: Documentation, doc lint, full test suite green, no regressions.

### Documentation

- [x] T034 [P] Rewrite `docs/source/guides/channels/whatsapp.blade.php`: WAHA as primary/default setup (Docker + env vars + `session:start` + QR scan + `channel:test`). Meta setup preserved as secondary option with `backend = 'meta'` note. Tabbed or toggled UI for WAHA vs Meta.
- [x] T035 [P] Update `docs/source/reference/config.blade.php`: add WAHA config keys (`backend`, `waha_api_url`, `waha_api_key`, `waha_session`, `waha_hmac_key`, `waha_version`) with types, defaults, and sensitivity labels; mark Meta keys as legacy.
- [x] T036 [P] Update `docs/source/reference/commands.blade.php`: add 7 WAHA session commands (`waha:session:start`, `waha:session:status`, `waha:session:stop`, `waha:session:restart`, `waha:session:qr`, `waha:session:list`, `waha:session:delete`) with signatures and descriptions.
- [x] T037 [P] Update `docs/source/reference/api/dtos.blade.php`: add `AgentError::CHANNEL_DELIVERY_FAILED` constant with value and description.
- [x] T038 [P] Update `docs/source/reference/api/inheritance.blade.php`: add `WahaWhatsAppAdapter` and `MetaWhatsAppAdapter` to the inheritance diagram showing both implementing `ChannelAdapter`.

### Final Verification

- [x] T039 Run `composer test` — full suite must be green (no regressions from rename or new code).
- [x] T040 Run `php scripts/docslint.php` — all doc code examples must be valid PHP 8.3+ syntax.

---

## Dependencies

```
Phase 1 (Setup: T001–T004)
  │
  ├──────────────────────────────┐
  ▼                              ▼
Phase 2 (US2: Meta preserve)    Phase 3 (US1: WAHA core)
  T005–T009                      T010–T021
  │                              │
  │  ┌───────────────────────────┘
  │  │  (T010 WahaClient needed)
  │  ▼
  ├──► Phase 4 (US3: Sessions)
  │     T022–T033
  │     │
  └─────┴────► Phase 5 (Polish)
               T034–T040
```

US2 (Meta rename) and US3 (Session commands) can run in parallel after Phase 1 completes. US1 (WAHA adapter) depends on T010 (WahaClient) and T002 (WahaSessionState) from Phase 1.

## Parallel Execution Examples

**Phase 1** — all tasks parallel:
```
T001 | T002 | T003 | T004
```

**Phase 3** — after T002 + T003 complete:
```
T010 (WahaClient) ────────────────┐
                                  ▼
                          T011 → T012 → T013 → T014 (sequential within adapter)
                          T016 (Fake factory, parallel with adapter)
                          T017 (State tests, parallel)
                          T019 (Client tests, after T010)
                                  │
                                  ▼
                          T018 (Adapter tests, after T011–T014)
                          T015 (Webhook controller, after T011)
```

**Phase 4** — after T010 (WahaClient) + T011 (Adapter) complete:
```
All 7 session commands (T022–T028) are parallel [P]
T029 | T030 are parallel [P]
T031 (command tests, after T022–T028)
T032 (fake tests, parallel with T031)
```

**Phase 5** — after all stories complete:
```
T034 | T035 | T036 | T037 | T038 (all parallel)
T039 → T040 (sequential)
```

## Implementation Strategy

### MVP (US1 + US2)

Deliver the core WAHA adapter with Meta backward compatibility (Phase 1–3). At this point:

- WAHA is the default WhatsApp backend
- Meta users continue working unchanged
- Webhook parsing, HMAC verification, send/receive all functional
- 4 new test files + 2 updated test files
- Can verify with: `composer test -- --filter="(Waha|MetaWhatsApp)"`

### Full Delivery (US3 + Polish)

Add session management CLI and documentation (Phase 4–5):

- 7 session management commands
- Updated channel-test and channel-add for WAHA
- Full documentation for both WAHA and Meta paths
- Full test suite green
- Can verify with: `composer test && php scripts/docslint.php`

## Task Count Summary

| Phase | Tasks | New Files | Modified Files | Deleted Files |
|-------|-------|-----------|----------------|---------------|
| Phase 1 (Setup) | 4 | 1 | 2 | 0 |
| Phase 2 (US2: Meta) | 5 | 0 | 7 | 2 |
| Phase 3 (US1: WAHA) | 12 | 4 | 2 | 0 |
| Phase 4 (US3: Sessions) | 12 | 7 | 3 | 0 |
| Phase 5 (Polish) | 7 | 0 | 5 | 0 |
| **Total** | **40** | **12** | **19** | **2** |
