---
extends: _layouts.master
title: API — Inheritance Diagram
description: Visual overview of the Emissary contract hierarchy.
---

@section('body')
<div class="tldr-box">
    <h4>TL;DR</h4>
    <p>Emissary's public API follows a layered contract hierarchy — app plugins implement the top-level interfaces; the pipeline consumes them through the bottom-level adapters.</p>
</div>

## Contract Hierarchy

<div class="mermaid">
classDiagram
    direction TB

    class AgentToolProvider {
        +pluginName() string
        +getIntents() array
        +getIntentConfig() array
        +getIntentClassificationHints() array
        +getToolDefinitions() array
        +getGuards() array
        +getSystemPromptExtension() string
        +getDocumentMappings() array
        +isIntentSupported(intent, tenant) bool
    }

    class AgentGuard {
        +checkpoint() string
        +evaluate(InboundMessage, IntentResult) GuardResult
    }

    class ChannelAdapter {
        +parse(Request) InboundMessage
        +verify(Request) bool
        +formatResponse(AgentResponse) OutboundMessage
        +send(OutboundMessage) void
    }

    class ChannelCredentialStore {
        +get(Channel) ChannelCredentials
    }

    class ChannelIdentityResolver {
        +resolve(InboundMessage) ResolvedIdentity
    }

    class ConfirmationGate {
        +isPending(string) bool
        +confirm(string) void
        +deny(string) void
        +request(string, string) void
    }

    class TenancyResolver {
        +resolve(InboundMessage) mixed
    }

    class Tool {
        <<attribute>>
        +name string
        +description string
        +parameters string
    }

    AppPlugin ..|> AgentToolProvider
    CustomGuard ..|> AgentGuard
    WahaWhatsAppAdapter ..|> ChannelAdapter
    MetaWhatsAppAdapter ..|> ChannelAdapter
    TelegramAdapter ..|> ChannelAdapter
    WhatsAppAdapter ..|> ChannelAdapter
    WebChatAdapter ..|> ChannelAdapter
    ConfigCredentialStore ..|> ChannelCredentialStore
    EncryptedCredentialStore ..|> ChannelCredentialStore
    AuthIdentityResolver ..|> ChannelIdentityResolver
    GuestCreatingResolver ..|> ChannelIdentityResolver
    DatabaseGate ..|> ConfirmationGate
    NullTenancyResolver ..|> TenancyResolver
</div>

## DTO Map

<div class="mermaid">
classDiagram
    class InboundMessage {
        +string conversationId
        +Channel channel
        +string content
        +string senderId
        +array metadata
        +string turnId
    }

    class AgentResponse {
        +string content
        +array toolCalls
        +string turnId
        +float cost
    }

    class IntentResult {
        +string intent
        +float confidence
        +string turnId
    }

    class GuardResult {
        +bool allowed
        +?string reason
        +bool pending
    }

    class ChannelCredentials {
        +Channel channel
        +array credentials
    }

    class OutboundMessage {
        +string recipientId
        +string content
        +Channel channel
        +string turnId
    }

    InboundMessage --> IntentResult
    IntentResult --> GuardResult
    GuardResult --> AgentResponse
    AgentResponse --> OutboundMessage
</div>
@endsection
