# Changelog

## 2.0.0 (unreleased)

### Added
- Attachments: `Attachment` value object (PDF, JPEG, PNG, GIF, WebP, 15 MB guard), `UserPrompt::withAttachments()`, `Conversation::withAttachments()`.
- `Capability::DOCUMENT` for PDF input. `Platform` validates attachments against the model before any client call.
- Attachments on every chat provider: OpenAI (`file` / `image_url` parts; gpt-5, gpt-4.1, gpt-4.1-mini, gpt-4o), Anthropic (`document` / `image` blocks; all Claude models except Claude 3 Haiku), Gemini (inline data; 2.5 and 3.1, no GIF) and Bedrock.
- AWS Bedrock provider (`AIProvider::BEDROCK`): Nova 2 Lite and Claude Haiku 4.5, text, images and PDFs, via the optional `symfony/ai-bedrock-platform` ~0.13.0. Nova rejects conversations with an assistant prompt (it requires the user turn first); Claude accepts them. Nova PDF support through our own normalizer (upstream lacks it).
- TypeSafe Jev decisions: `DecisionPlatformInterface`, `Question`, `Answer`, `DecisionResult`, `TypeSafeDecisionPlatform`. Jev is a provider (`AIProvider::TYPESAFE`, `TypeSafeProvider`, `Enum\TypeSafe\DecisionModel`) exposed through `DecisionPlatformInterface::getProvider()`, never through `Platform::ask()`.
- `RateLimitedClient` option `retryTransportErrors` (default `true`).

### Changed (breaking)
- PHP ^8.4 (was ^8.3). Symfony components ^7.4 || ^8.0 (6.4 dropped).
- `ext-fileinfo` is required (attachment type detection).
- New enum cases `AIProvider::BEDROCK`, `AIProvider::TYPESAFE` and `Capability::DOCUMENT`: an exhaustive `match` over these enums needs a new arm.
- `Conversation::toArray()` adds an `attachments` key (mime and size only) to the user message when attachments are present. Unchanged without attachments.
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
