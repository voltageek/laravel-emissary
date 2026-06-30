# Data Models

> Every table. Central DB connection (not tenant-scoped).

---

## Data Models

All models use the **central** database connection (not tenant-scoped).

### Conversation
```
id              UUID (PK)
tenant_id       UUID nullable  — null for single-tenant apps or unlinked channels
channel         varchar(20)    — whatsapp|telegram|web
channel_ref     varchar(100)   — sender identifier
status          varchar(20)    — active|closed
onboarding_state varchar(20)   — new|onboarding|complete|skipped (see User Onboarding)
pending_action  JSON nullable  — {type, tool_name, fields, proposed_at}
summary         TEXT nullable  — accumulated session summaries
created_at      timestamp
updated_at      timestamp

UNIQUE(channel, channel_ref)
```

### ConversationMessage
```
id              UUID (PK)
conversation_id UUID (FK → conversations)
turn_id         UUID nullable  — groups all messages/rows belonging to one user turn
role            varchar(20)   — user|assistant|tool_result
content         TEXT
media_url       varchar nullable
intent          varchar nullable
error_code      varchar nullable  — populated on error responses
created_at      timestamp
```

**Retention:** Message and event rows are prunable by age via the `agent:prune` Artisan command, governed by `retention.message_ttl_days` / `retention.event_ttl_days` (plus `payload_ttl_days` and `span_ttl_days` for the opt-in capture tables). Conversation transcripts may contain PII or accidentally-typed secrets; set a TTL appropriate to your compliance posture. The `content` column is **not** encrypted at rest by default — apps handling sensitive data should encrypt it or avoid storing raw transcripts.

### AgentEvent
The unified trace log. One row per observable signal; `kind` discriminates the payload shape. Every row carries the `turn_id` of the inbound message that caused it, so a full turn is reconstructed with `WHERE turn_id = ? ORDER BY created_at`.
```
id                      UUID (PK)
turn_id                 UUID nullable   — groups rows for one user turn
conversation_id         UUID (FK → conversations)
tenant_id               UUID nullable
kind                    varchar(20)     — llm_call|guard|gate|turn|onboarding
model                   varchar nullable      — llm_call
input_tokens            INTEGER nullable      — llm_call
output_tokens           INTEGER nullable      — llm_call
latency_ms              INTEGER nullable      — llm_call|turn (turn = end-to-end)
intent                  varchar nullable      — llm_call|turn
checkpoint              varchar nullable      — guard: beforeIntent|beforeExecution|beforeTool
guard                   varchar nullable      — guard: guard name
tool_name               varchar nullable      — guard(beforeTool)|gate|turn
result                  varchar nullable      — guard: allow|deny; gate/turn: transition|outcome
error_code              varchar nullable
error                   TEXT nullable
payload                 JSON nullable         — kind-specific extras (tool_calls summary, gate fields, etc.)
conversation_message_id UUID nullable
created_at              timestamp
```
`call_type` from earlier versions maps onto `kind = llm_call` with `intent`/`payload` distinguishing intent vs. agent vs. direct_handler vs. agent_error.

### ToolInvocation
First-class row per tool execution — emitted for **every** path (agent loop, confirmation fast-path, direct handler) and for validation failures (where `success = false` and `validation_error` is set). The queryable source for "how often / how fast / how often failing is tool X?".
```
id              UUID (PK)
turn_id         UUID nullable
conversation_id UUID (FK → conversations)
tenant_id       UUID nullable
tool_name       varchar
arguments       JSON                 — args as passed to the handler (or as the LLM supplied, on validation failure)
result_summary  TEXT nullable        — short result / TransactionResult message
duration_ms     INTEGER nullable
success         BOOLEAN
validation_error varchar nullable    — set when args failed schema validation (handler not called)
triggered_via   varchar(24)          — agent_loop|confirmation_fastpath|direct_handler
agent_event_id  UUID nullable        — FK → agent_events (the llm_call that requested it, when applicable)
created_at      timestamp
```

### LlmPayload *(optional — only if CaptureLlmPayload listener is registered)*
Replay-grade capture: the exact messages, tools schema, and response for one LLM call. Storage-heavy and may contain user PII; gated behind `observability.capture_llm_payloads` and bound by `retention.payload_ttl_days`. Powers `agent:replay --re-run`.
```
id              UUID (PK)
agent_event_id  UUID (FK → agent_events)
turn_id         UUID nullable
request_messages  JSON              — full message array sent to the model (system + history + user)
tools_sent        JSON nullable     — tool definitions supplied on this call
response          JSON              — raw model response
created_at      timestamp
```

### AgentSpan *(optional — only if LogTraceSpan listener is registered)*
Per-stage latency within a turn (`webhook_parse`, `identity_resolve`, `guard_before_intent`, `intent_classify`, `guard_before_execution`, `memory_load`, `llm_call`, `guard_before_tool`, `tool_execute`, `format`, `send`). High-volume; gated behind `observability.capture_trace_spans` and bound by `retention.span_ttl_days`.
```
id              UUID (PK)
turn_id         UUID nullable
conversation_id UUID (FK → conversations)
stage           varchar(48)
duration_ms     INTEGER
created_at      timestamp
```

### CostLedger *(optional — only if UpdateCostLedger listener is registered)*
```
id              UUID (PK)
conversation_id UUID (FK → conversations)
tenant_id       UUID nullable
month           varchar(7)   — YYYY-MM
input_tokens    INTEGER
output_tokens   INTEGER
cost_usd        NUMERIC(12,6)

UNIQUE(conversation_id, month)
```

### ChannelConfig *(optional — only EncryptedChannelCredentialStore / dynamic provisioning)*
Per-channel (and optionally per-tenant) credentials for DB-backed onboarding. The `credentials` blob is **encrypted at rest** (these are secrets, unlike conversation content). Not used by the default `ConfigChannelCredentialStore`.
```
id           UUID (PK)
tenant_id    UUID nullable
channel      varchar(20)   — whatsapp|telegram|web
label        varchar       — friendly name
credentials  TEXT          — encrypted JSON: {access_token, sender_id, verify_secret, handshake_token, extra}
status       varchar(20)   — active|disabled|verify_pending
created_at   timestamp
updated_at   timestamp

UNIQUE(tenant_id, channel)
```

### ChannelIdentityLink
Binds a chat-channel identity to a Laravel user (populated by the built-in `verify_identity` intent and by guest creation; read by `LinkedChannelIdentityResolver`).
```
id            UUID (PK)
user_id       UUID (FK → users)
channel       varchar(20)   — whatsapp|telegram
channel_ref   varchar(100)  — phone number / Telegram chat id
verified_at   timestamp nullable
created_at    timestamp

UNIQUE(channel, channel_ref)
```

### UserOnboarding *(only if onboarding.enabled)*
Per-user onboarding record — profile fields collected by the `start_onboarding` flow plus consent state. Kept library-side so the host `users` table gains only a discriminator column (see note below).
```
id              UUID (PK)
user_id         UUID (FK → users)
conversation_id UUID nullable  — where onboarding is taking place
status          varchar(20)    — guest|onboarding|complete
profile         JSON nullable  — collected fields keyed by name
consent_at      timestamp nullable
consent_version varchar(32) nullable
created_at      timestamp
completed_at    timestamp nullable
```

**Guest marker on `users`:** channel-first guests are real `User` rows with `onboarded_at = null` (null = guest / not yet onboarded). The library adds this one nullable column to the host `users` table (publishable migration); upgrade to full is `onboarded_at = now()`. Apps that don't enable channel-first guest creation never write guest rows.
