---
extends: _layouts.master
title: Testing
description: Test your Emissary plugins with Pest — use FakeLlmClient, FakeChannelAdapter, and AgentTestCase.
---

@section('body')
<div class="tldr-box">
    <h4>TL;DR</h4>
    <pre><code class="language-php">use Emissary\Testing\FakeLlmClient;
use Emissary\Testing\FakeChannelAdapter;
use Emissary\Testing\AgentTestCase;

$fakeLlm = FakeLlmClient::script([
    'intent' => 'record_sale',
    'tool_calls' => ['recordSale(amount: 29.99, item: "T-shirt")'],
]);

$fakeChannel = FakeChannelAdapter::forChat('telegram', $messages);

$result = $this->process($fakeChannel, $fakeLlm);

expect($result->lastOutput())->toContain('Recorded sale');</code></pre>
</div>

## Quick Start

Emissary ships a Pest test toolkit in `Emissary\Testing\` — mirroring `Illuminate\Testing`.

### FakeLlmClient

Script the LLM's behavior deterministically:

```php
use Emissary\Testing\FakeLlmClient;

$fakeLlm = FakeLlmClient::script([
    'intent' => 'record_sale',
    'tool_calls' => [
        'recordSale(amount: 29.99, item: "T-shirt")',
    ],
]);
```

### FakeChannelAdapter

Simulate messages from any channel without a live API:

```php
use Emissary\Testing\FakeChannelAdapter;

$fakeChannel = FakeChannelAdapter::forChat('telegram', [
    'I want to record a sale of $29.99 for a T-shirt',
]);
```

</xv-deep-dive>

<details class="deep-dive">
    <summary>Deep Dive</summary>
    <div class="deep-dive-content">

### AgentTestCase

Extend `AgentTestCase` for Pest assertions over tools, guards, events, and turn outcomes:

```php
use Emissary\Testing\AgentTestCase;

uses(AgentTestCase::class);

it('records a sale when user provides amount and item', function () {
    $fakeLlm = FakeLlmClient::script([
        'intent' => 'record_sale',
        'tool_calls' => ['recordSale(amount: 29.99, item: "T-shirt")'],
    ]);

    $this->withTools(SalesTools::class)
         ->process($fakeLlm, 'Record sale of $29.99 for T-shirt')
         ->assertToolCalled('record_sale')
         ->assertEventEmitted(TurnCompleted::class)
         ->assertOutputContains('Recorded sale');
});
```

### Replaying Captured Fixtures

```php
it('reproduces a captured turn', function () {
    $turn = AgentTestCase::fixture('turn_abc123.json');

    $this->replay($turn)
         ->assertToolCalled('record_sale')
         ->assertEventEmitted(ToolInvocationCompleted::class);
});
```

### Testing Guards

```php
it('blocks requests outside business hours', function () {
    $this->travelTo('2026-07-01 22:00:00');

    $this->withGuards(BusinessHoursGuard::class)
         ->process($fakeLlm, 'Record a sale')
         ->assertGuardDenied('BusinessHoursGuard')
         ->assertOutputContains('closed');
});
```

### Testing Tools

```php
it('validates tool arguments', function () {
    $fakeLlm = FakeLlmClient::script([
        'tool_calls' => ['recordSale(amount: "invalid", item: "")'],
    ]);

    $this->withTools(SalesTools::class)
         ->process($fakeLlm, 'Record a sale')
         ->assertToolFailed('record_sale')
         ->assertEventEmitted(ToolInvocationCompleted::class);
});
```

### No Live Calls

The test toolkit guarantees no test ever hits a live LLM or channel API. `FakeLlmClient` scripts every agent response. `FakeChannelAdapter` simulates inbound messages in-process.

    </div>
</details>
@endsection
