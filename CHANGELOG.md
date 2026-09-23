# Changelog

## 2.0.0 (unreleased)

### Added
- Attachments: `Attachment` value object (PDF, DOCX, text formats TXT/CSV/Markdown/HTML/JSON, JPEG, PNG, GIF, WebP; 15 MB guard), `UserPrompt::withAttachments()`, `Conversation::withAttachments()`. Text formats are sent as text, so every model reads them; DOCX is read by Bedrock Nova only.
- `Capability::DOCUMENT` for document input (PDF, DOCX where supported). `Platform` validates attachments against the model before any client call. Text attachments pass through the data sanitizer like the prompt.
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
