# Phase 1 — Data Model

> All entities, relationships, and migration ordering.

## Entity Relationship Diagram

```
users (host table)
  │
  ├── 1:N ── channel_identity_links (channel, channel_ref UNIQUE)
  │
  └── 1:1 ── user_onboardings (optional, onboarding.enabled)

conversations
  │  UNIQUE(channel, channel_ref)
  │
  ├── 1:N ── conversation_messages
  │            indexed by turn_id
  │
  ├── 1:N ── agent_events
  │            kind-discriminated (llm_call|guard|gate|turn|onboarding)
  │            │
  │            └── 1:N ── tool_invocations (agent_event_id FK)
  │            │
  │            └── 1:1 ── llm_payloads (opt-in, agent_event_id FK)
  │
  ├── 1:N ── agent_spans (opt-in, per-stage latency)
  │
  └── 1:1 ── cost_ledgers (opt-in, UNIQUE(conversation_id, month))

channel_configs (standalone, optional)
  UNIQUE(tenant_id, channel)
```

## Migration Order & Foreign Keys

| Order | Table | FK Dependencies | Timestamped |
|---|---|---|---|
| 1 | `conversations` | none | `2026_01_01_000001_create_conversations_table` |
| 2 | `conversation_messages` | `conversation_id` → `conversations.id` | `..._000002` |
| 3 | `agent_events` | `conversation_id` → `conversations.id` | `..._000003` |
| 4 | `tool_invocations` | `conversation_id` → `conversations.id`, `agent_event_id` → `agent_events.id` | `..._000004` |
| 5 | `channel_identity_links` | `user_id` → `users.id` (host) | `..._000005` |
| 6 | `llm_payloads` | `agent_event_id` → `agent_events.id` | `..._000006` |
| 7 | `agent_spans` | `conversation_id` → `conversations.id` | `..._000007` |
| 8 | `cost_ledgers` | `conversation_id` → `conversations.id` | `..._000008` |
| 9 | `channel_configs` | none (self-contained) | `..._000009` |
| 10 | `user_onboardings` | `user_id` → `users.id`, `conversation_id` → `conversations.id` | `..._000010` |
| 11 | `add_onboarded_at_to_users` | alters `users` (publishable) | `..._000011` |

## Index Strategy

| Table | Index | Type | Reason |
|---|---|---|---|
| `conversations` | `(channel, channel_ref)` | UNIQUE | Lookup by sender identity |
| `conversation_messages` | `(conversation_id, created_at)` | INDEX | Load recent messages for memory |
| `conversation_messages` | `(turn_id)` | INDEX | Reconstruct turn traces |
| `agent_events` | `(turn_id, created_at)` | INDEX | Turn reconstruction: `WHERE turn_id = ? ORDER BY created_at` |
| `agent_events` | `(conversation_id, created_at)` | INDEX | Per-conversation event listing |
| `tool_invocations` | `(conversation_id, tool_name)` | INDEX | Tool invocation reporting |
| `cost_ledgers` | `(conversation_id, month)` | UNIQUE | Upsert accumulation |
| `channel_configs` | `(tenant_id, channel)` | UNIQUE | One config per channel per tenant |
| `channel_identity_links` | `(channel, channel_ref)` | UNIQUE | Identity resolution lookup |
| `channel_identity_links` | `(user_id)` | INDEX | Find all channels for a user |
| `user_onboardings` | `(user_id)` | UNIQUE (implicit) | One onboarding record per user |

## State Machines

### Conversation
```
[created] → active → closed
```
- `status`: `active` | `closed`
- `onboarding_state`: `new` → `onboarding` → `complete` | `skipped`

### ConversationMessage
- No state transitions. Append-only log.

### AgentEvent
- No state transitions. Append-only log. `kind` discriminates: `llm_call` | `guard` | `gate` | `turn` | `onboarding`.

### ToolInvocation
- No state transitions. `triggered_via`: `agent_loop` | `confirmation_fastpath` | `direct_handler`.

### ChannelIdentityLink
```
[created, verified_at = null] → [verified_at = now()]
```

### UserOnboarding
```
guest → onboarding → complete
```
- `status`: `guest` | `onboarding` | `complete`
- Guest users: `User.onboarded_at = null`, `UserOnboarding.status = guest`
- Completed: `User.onboarded_at = now()`, `UserOnboarding.status = complete`, `UserOnboarding.completed_at = now()`
