# Architecture

> Pipeline shape and the 7 stages. Component behaviour lives in 03-pipeline.md.

---

## Purpose

A standalone, reusable PHP library that adds agentic, multi-channel conversational capabilities to existing Laravel applications. Developers integrate by defining three things: **intents**, **tools**, and **guards**. The library handles everything else — routing, LLM calls, tool execution, memory, confirmation gates, and channel delivery.

---

## Architecture Overview

The library is a **pipeline-based conversational agent** that:

1. Receives messages from channels (WhatsApp, Telegram, Web)
2. Resolves the sender to an optional tenant/context via an injectable `TenancyResolver`
3. Evaluates guards at defined checkpoints in the pipeline
4. Classifies intent using an LLM
5. Runs a tool-calling agent loop (OpenRouter/OpenAI-compatible API)
6. Persists all activity (messages, events, costs)
7. Delivers responses back through the originating channel

```
                    ┌─────────────┐
                    │  Channel    │
                    │  Adapter    │
                    └──────┬──────┘
                           │ InboundMessage
                           ▼
                    ┌─────────────┐
                    │  Message    │
                    │  Bridge     │ ← tenancy resolution, conversation mgmt
                    └──────┬──────┘
                           │
                    ┌──────▼──────┐
                    │   Guard     │ ← beforeIntent checkpoint
                    │  Registry   │
                    └──────┬──────┘
                           │ dispatch(conversationId)
                           ▼
              ┌────────────────────────┐
              │    Process Pipeline    │
              │                        │
              │  ┌──────────────────┐  │
              │  │ Intent Router    │  │ ← LLM classification
              │  └────────┬─────────┘  │
              │           │            │
              │  ┌────────▼─────────┐  │
              │  │ Guard Registry   │  │ ← beforeExecution checkpoint
              │  └────────┬─────────┘  │
              │           │            │
              │  ┌────────▼─────────┐  │
              │  │ Model Selector   │  │ ← config-driven routing
              │  └────────┬─────────┘  │
              │           │            │
              │  ┌────────▼─────────┐  │
              │  │ Tool Registry    │  │ ← resolve tools for intent
              │  └────────┬─────────┘  │
              │           │            │
              │  ┌────────▼─────────┐  │
              │  │ Task Agent       │  │ ← LLM + tool loop
              │  │  (per tool call) │  │
              │  │  Guard::before   │  │ ← beforeTool checkpoint
              │  │  Tool            │  │
              │  └────────┬─────────┘  │
              │           │            │
              │  ┌────────▼─────────┐  │
              │  │ Memory           │  │ ← session-based context
              │  └──────────────────┘  │
              └────────────┬───────────┘
                           │ AgentResponse
                           ▼
                    ┌─────────────┐
                    │  Message    │
                    │  Bridge     │ → ChannelAdapter::formatResponse()
                    └─────────────┘   → ChannelAdapter::send()
```
