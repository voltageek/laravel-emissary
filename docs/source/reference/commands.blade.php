---
extends: _layouts.master
title: Artisan Commands
description: Every Emissary Artisan command with arguments, options, and example output.
---

@section('body')
<div class="tldr-box">
    <h4>TL;DR</h4>
    <pre><code class="language-bash">php artisan emissary:replay {turnId}          # Replay a turn
php artisan emissary:report                    # Cost/usage report
php artisan emissary:prune --days=30           # Clean old data</code></pre>
</div>

## Command Reference

### emissary:replay

Replay a turn from fixtures, showing the event tree.

```bash
php artisan emissary:replay abc-123
```

| Argument | Description |
|---|---|
| `turnId` | Turn identifier to replay |

### emissary:report

Cost and usage report.

```bash
php artisan emissary:report --from=2026-06-01 --to=2026-07-01
```

| Option | Description |
|---|---|
| `--from=` | Start date (default: 30 days ago) |
| `--to=` | End date (default: now) |
| `--model=` | Filter by model |

### emissary:prune

Delete old events and conversations.

```bash
php artisan emissary:prune --days=30
```

| Option | Description |
|---|---|
| `--days=` | Retention in days (default: config `retention.*_ttl_days`) |

### emissary:channels:list

List configured channels and their status.

```bash
php artisan emissary:channels:list
```

### emissary:channel:add

Add a new channel configuration (for multi-tenant DB-backed credential stores).

```bash
php artisan emissary:channel:add whatsapp
```

| Argument | Description |
|---|---|
| `channel` | Channel name (telegram, whatsapp, web) |

### emissary:channel:setup

Verify and configure a channel.

```bash
php artisan emissary:channel:setup telegram
```

| Argument | Description |
|---|---|
| `channel` | Channel name (telegram, whatsapp, web) |

### emissary:channel:test

Send a test message through a channel.

```bash
php artisan emissary:channel:test telegram
```

### emissary:webhook:url

Display the webhook URL for a channel.

```bash
php artisan emissary:webhook:url telegram
```

### emissary:set-telegram-webhook

Register the webhook URL with Telegram's API.

```bash
php artisan emissary:set-telegram-webhook
```

### emissary:onboarding:status

Show onboarding state for a user.

```bash
php artisan emissary:onboarding:status {userId}
```

### emissary:onboarding:reset

Reset onboarding for a user.

```bash
php artisan emissary:onboarding:reset {userId}
```

### emissary:fixture:capture

Capture a turn as a replayable fixture.

```bash
php artisan emissary:fixture:capture {turnId}
```

| Argument | Description |
|---|---|
| `turnId` | Turn to capture as fixture |

### WAHA Session Commands

Manage WAHA WhatsApp sessions from the CLI.

```bash
php artisan emissary:waha:session:start {session?}
php artisan emissary:waha:session:status {session?}
php artisan emissary:waha:session:stop {session?}
php artisan emissary:waha:session:restart {session?}
php artisan emissary:waha:session:qr {session?}
php artisan emissary:waha:session:list
php artisan emissary:waha:session:delete {session?}
```

| Command | Description |
|---|---|
| `waha:session:start` | Create, start, and watch a session; configure webhook; display QR code |
| `waha:session:status` | Print current session state and QR code if applicable |
| `waha:session:stop` | Stop a running session |
| `waha:session:restart` | Restart a session (stop then start) |
| `waha:session:qr` | Fetch and display the raw QR code |
| `waha:session:list` | List all sessions and their statuses |
| `waha:session:delete` | Delete a session; warns if currently WORKING |

The optional `{session?}` argument defaults to the config value (`WAHA_SESSION` / `channels.whatsapp.waha_session`).
@endsection
