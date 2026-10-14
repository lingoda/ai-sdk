# Changelog

## 2.5.0

### Added
- OpenAI `gpt-6.1-sol` ($2/$10 per MTok), and the GPT-5.6 family: `gpt-5.6-sol` ($4/$20), `gpt-5.6-terra` ($2/$12) and `gpt-5.6-luna` ($0.20/$1.20). All 1,050,000 context, 128K output, text, tools, vision, documents and reasoning. This completes the set of GPT-6 and GPT-5.6 models the API exposes; `gpt-6.1-sol-ultrafast` appears in rate-limit tables but is not served by `/v1/models` and is not included.
- `gpt-6.1-sol` has no `none` effort, as with `gpt-6-astra`: its floor is `low`, so it pins no effort and sends neither `temperature` nor `top_p`.

### Fixed
- OpenAI: GPT-5.6 is now read as reasoning-by-default. `rejectsSampling()` treated every `gpt-5.` model as defaulting to `none`, which holds for GPT-5.4 but not for GPT-5.6, whose own default is `medium`. A GPT-5.6 request with an absent effort would have kept `temperature` and been rejected.

## 2.4.2

### Fixed
- Token estimation now reads a bare list of role/content messages, which is what `Conversation::toRequestArray()` returns and what every client accepts. No provider extractor recognised that shape: they look for a `messages` key or role keys, found neither, and returned an empty string, so a 24,800 character prompt was charged to the token bucket as **1 token**. Any consumer passing a Conversation had a TPM rate limiter that did nothing. Affects OpenAI, Anthropic and Gemini.

## 2.4.1

### Fixed
- OpenAI `gpt-6-astra` no longer pins `reasoning_effort: none`, which it rejects. Its lowest effort is `low`, so it always reasons: the default options now carry neither an effort nor `temperature`/`top_p`. Sol and Luna are unaffected and still pin none. Every astra request made under 2.4.0 failed.

## 2.4.0

### Added
- OpenAI GPT-6: `gpt-6-astra`, `gpt-6-sol` and `gpt-6-luna`. 1,050,000 context window, text, tools, vision, documents and reasoning. `gpt-6-luna` is OpenAI's named replacement for `gpt-4o`, `gpt-4o-mini`, `gpt-4.1` and `gpt-4.1-mini`, which are removed from the API on 14 January 2027.
- GPT-6 models default to `reasoning_effort: none`. OpenAI's own GPT-4.x migration guide starts there, Chat Completions supports function calling only at that setting, and it is what leaves `temperature` usable. The model's default reasoning effort is `medium`, so a caller that wants reasoning asks for it.

### Changed
- OpenAI: `max_completion_tokens` now replaces `max_tokens` for GPT-6 as well as GPT-5. Reasoning tokens come out of the output budget, and these models reject `max_tokens` outright.
- OpenAI: `temperature` and `top_p` are left out for a GPT-6 model unless `reasoning_effort` is `none`. GPT-5.x treats an ABSENT effort as none; GPT-6 does not, because its own default is `medium`.

## 2.3.0

### Added
- OpenAI `gpt-5.4-mini` and `gpt-5.4-nano`, with their `2026-03-17` snapshots: 400K context window, text, tools, vision and documents.

### Changed
- OpenAI: `temperature` and `top_p` are left out of a request to a model that rejects them. GPT-5 (5.0) models always reason and take only the defaults. GPT-5.x models take them while `reasoning_effort` is `none` (their default), not once a caller asks for reasoning. Before, a GPT-5 request with `temperature: 0` failed with "Unsupported value: 'temperature'".

## 2.2.0

### Changed
- `Attachment` no longer limits the size, and `Attachment::MAX_BYTES` is gone. The 15 MB guard held for no provider: Bedrock Claude refuses an image well below it, and the providers limit the whole request, not each file.

### Added
- Bedrock Claude: an image over 5 MB as base64 (3.75 MB as a file) throws `UnsupportedCapabilityException` before the upload, which Bedrock would refuse with "image exceeds 5 MB maximum". This resolves the 2.0.0 known limitation for images.

### Not checked
- The request size. The sources disagree for Claude on Bedrock (Anthropic documents 20 MB, the InvokeModel API 25,000,000 bytes), and in September 2026 Bedrock accepted requests of about 31 MB. An oversized request fails with Bedrock's `ValidationException` ("Input is too long").

## 2.1.0

### Added
- `RateLimitedDecisionPlatform`: rate limits any `DecisionPlatformInterface`. Each `decide()` takes one request and the estimated input tokens from the limiter, waits when it is exhausted, and retries with exponential backoff (capped at 30 seconds) on 429, 529, 502, 503 and 504. With `SymfonyRateLimiter`, TypeSafe gets 1,080 requests and 13,500,000 tokens per minute, 90% of its published account limits.

## 2.0.1

### Fixed
- `ExternalRateLimiterInterface::getRateLimiter()` documented `@return RateLimiterFactory` while its signature returns `RateLimiterFactoryInterface`; implementations can now return any `RateLimiterFactoryInterface` without static analysis narrowing them to the concrete class.

## 2.0.0

### Added
- Attachments: `Attachment` value object (PDF, DOCX, text formats TXT/CSV/Markdown/HTML/JSON, JPEG, PNG, GIF, WebP; 15 MB guard), `UserPrompt::withAttachments()`, `Conversation::withAttachments()`. Text formats are sent as text, so every model reads them; DOCX is read by Bedrock Nova only.
- `Capability::DOCUMENT` for document input (PDF, DOCX where supported). `Platform` validates attachments against the model before any client call. Attachments are sent as provided: the data sanitizer only runs on the prompt text.
- Attachments on every chat provider: OpenAI (PDF, images, text; gpt-5, gpt-4.1, gpt-4.1-mini, gpt-4o), Anthropic (PDF, images, text; all Claude models except Claude 3 Haiku), Gemini (PDF, images except GIF, text; 2.5 and 3.1) and Bedrock (Nova: also DOCX).
- AWS Bedrock provider (`AIProvider::BEDROCK`): Amazon Nova (Micro, Lite, Pro, 2 Lite) and Claude (Haiku 4.5, Sonnet 4.5, Opus 4.5, Sonnet 4.6, Opus 4.6, Opus 4.7, Opus 4.8, Sonnet 5, Opus 5, Opus 5.5), text, images, PDFs (Nova also DOCX), via the optional `symfony/ai-bedrock-platform` ~0.13.0. Nova rejects conversations with an assistant prompt (it requires the user turn first); Claude accepts them. Nova PDF support through our own normalizer (upstream lacks it).
- TypeSafe Jev decisions: `DecisionPlatformInterface`, `Question`, `Answer`, `DecisionResult`, `TypeSafeDecisionPlatform`. Jev is a provider (`AIProvider::TYPESAFE`, `TypeSafeProvider`, `Enum\TypeSafe\DecisionModel`) exposed through `DecisionPlatformInterface::getProvider()`, never through `Platform::ask()`.
- `RateLimitedClient` option `retryTransportErrors` (default `true`).

### Fixed
- GPT-5 models: the token limit is sent as `max_completion_tokens` (they reject `max_tokens`), so text and attachments work on GPT-5, GPT-5 mini and GPT-5 nano.

### Changed (breaking)
- PHP ^8.4 (was ^8.3). Symfony components ^7.4 || ^8.0 (6.4 dropped).
- `ext-fileinfo` is required (attachment type detection).
- New enum cases `AIProvider::BEDROCK`, `AIProvider::TYPESAFE` and `Capability::DOCUMENT`: an exhaustive `match` over these enums needs a new arm.
- `Conversation::toArray()` adds an `attachments` key (mime and size only) to the user message when attachments are present, and `Conversation::fromArray()` refuses such arrays instead of silently dropping the attachments. Unchanged without attachments.
- `ClientInterface::request()` payload PHPDoc gains `attachments?: list<Attachment>`, and the method may throw `UnsupportedCapabilityException` for attachments or options the model cannot take.
- `AIProvider::TYPESAFE` is not a chat provider: a bundle validating `default_provider` against `AIProvider::cases()` must exclude it, since `Platform` has no chat client for it.
- `Capability::MULTIMODAL` is legacy and not used for validation. Use `VISION` and `DOCUMENT`.

### Known limitations
- The token estimator does not read the conversation payload, so every request counts as 1 token and `tokens_per_minute` limits (including Bedrock's 100k default) are not enforced. Attachments are not estimated either. This predates 2.0.
- Bedrock per-document and per-image limits are below `Attachment::MAX_BYTES`; oversized files fail with a `ClientException` (HTTP 400) after upload.

### Upgrading
- Text-only usage needs no code change: payloads, `toArray()` and hashes are identical to 1.4.1.
- Custom `ClientInterface` implementations should reject or convert `attachments`.
- Bedrock users: `composer require symfony/ai-bedrock-platform:~0.13.0 async-aws/bedrock-runtime`, then build the client with `BedrockClientFactory::createClient()`. The `BedrockClient` constructor is `@internal` (it takes Symfony AI 0.x types) and may change with any Symfony AI bump.
