# Lingoda AI SDK

Framework-agnostic PHP SDK for AI providers with typed results and platform abstraction.

## 🚀 Quick Start

```php
use Lingoda\AiSdk\Platform;
use Lingoda\AiSdk\Client\OpenAI\OpenAIClientFactory;

// Create client using factory
$client = OpenAIClientFactory::createClient('your-api-key');
$platform = new Platform([$client]);

// Simple ask() method - automatically uses default model
$result = $platform->ask('Hello, AI!');
echo $result->getContent(); // TextResult

// Or specify a specific model
$result = $platform->ask('Hello, AI!', 'gpt-4o-mini');
echo $result->getContent();

// Audio capabilities
$audioResult = $platform->textToSpeech('Hello world', $audioOptions);
$transcription = $platform->transcribeAudio('/path/to/audio.mp3', $options);
```

## 📚 Documentation

| Guide | Description |
|-------|-------------|
| [Installation](docs/installation.md) | Setup and Platform basics |
| [Configuration](docs/configuration.md) | API keys and multi-provider setup |
| [Quick Start](docs/quick-start.md) | Your first AI request |
| [Symfony Integration](docs/symfony-integration.md) | Bundle configuration and provider-specific platforms |
| [HTTP Clients](docs/http-clients.md) | Advanced HTTP configuration |
| [Logging](docs/logging.md) | Debug and monitoring setup |
| [Advanced Usage](docs/advanced-usage.md) | Complex features and patterns |
| [Security](docs/security.md) | Data protection and sanitization |
| [API Reference](docs/api-reference.md) | Complete API documentation |
| [Audio](docs/audio.md) | Speech synthesis and transcription |
| [Interactive Examples](docs/examples.md) | Live examples with real APIs |

## ✨ Key Features

- **🔌 Framework Agnostic** - No dependencies on Symfony or other frameworks
- **🛡️ Security First** - Built-in data sanitization and attribute-based protection
- **🎯 Type Safe** - Strongly-typed results and prompt value objects
- **🌐 Multi-Provider** - OpenAI, Anthropic, Gemini and AWS Bedrock (optional) with flexible configuration
- **📎 Attachments** - PDFs, DOCX, text files and images on the user prompt, validated per model, never written to traces
- **⚖️ Decisions** - Structured yes/no, choice and score answers via TypeSafe Jev
- **🎭 Capabilities** - Models declare supported features (vision, tools, audio, streaming, reasoning)
- **⚡ Performance** - Built-in rate limiting and token estimation with exponential backoff
- **📝 Rich Prompts** - Parameterized prompts and conversation management
- **🎵 Audio Support** - Text-to-speech, transcription, and translation with multiple formats
- **🔄 Streaming** - Real-time response streaming support

## 🏗️ Architecture

```
Platform → Providers → Models → Clients → AI APIs
    ↓
Results ← Security ← Capabilities ← Response
```

- **Platform**: Main entry point for AI operations
- **Providers**: Manage models for each AI service (OpenAI, Anthropic, Gemini, AWS Bedrock; TypeSafe Jev for decisions)
- **Models**: Individual AI models with declared capabilities
- **Clients**: Handle API communication with rate limiting
- **Results**: Type-safe responses (`TextResult`, `BinaryResult`, `StreamResult`, `ObjectResult`, `ToolCallResult`)

## 🎨 Usage Patterns

### Simple Text Generation
```php
$result = $platform->ask('Explain AI');
echo $result->getContent(); // string
print_r($result->getMetadata()); // usage, model info, etc.
```

### Parameterized Prompts
```php
$template = UserPrompt::create('Hello {{name}}, tell me about {{topic}}');
$prompt = $template->withParameters([
    'name' => 'Alice',
    'topic' => 'machine learning'
]);

// Use ask() method with prompt objects
$result = $platform->ask($prompt);
```

### Conversations with Context
```php
$conversation = Conversation::withSystem(
    UserPrompt::create('What is quantum computing?'),
    SystemPrompt::create('You are a helpful physics expert')
);

// ask() method supports Conversation objects
$result = $platform->ask($conversation, 'claude-sonnet-4');
```

### Automatic Data Protection
```php
// Sensitive data is automatically sanitized
$prompt = UserPrompt::create('My email is john@example.com');
// Sent to AI as: "My email is [REDACTED_EMAIL]"
$result = $platform->ask($prompt);

// Disable sanitization if needed
$platform = new Platform([$client], enableSanitization: false);
```

### Documents and Images
```php
use Lingoda\AiSdk\Prompt\Attachment;

$conversation = Conversation::fromUser(UserPrompt::create('Extract the voucher fields as JSON.'))
    ->withAttachments(Attachment::fromBytes($pdfBytes, 'application/pdf'));

$result = $platform->ask($conversation, 'amazon.nova-2-lite-v1:0');
```
- Up to `Attachment::MAX_BYTES` (15 MB). The mime type is detected when omitted; passing it explicitly is more reliable for text formats.
- Unsupported combinations throw `UnsupportedCapabilityException` before any request.
- Attachments are sent as provided: the data sanitizer only runs on the prompt text, since pattern redaction corrupts data files (long ids read as phone numbers). Redact attachments yourself if they must not reach the provider.

| Type | OpenAI | Anthropic | Gemini | Bedrock Nova | Bedrock Claude | Needs |
|---|---|---|---|---|---|---|
| PDF | yes | yes | yes | yes | yes | `Capability::DOCUMENT` |
| DOCX | no | no | no | yes | no | `Capability::DOCUMENT` |
| Text: TXT, CSV, Markdown, HTML, JSON (UTF-8) | yes | yes | yes | yes | yes | nothing: sent as text |
| JPEG, PNG, WebP | yes | yes | yes | yes | yes | `Capability::VISION` |
| GIF | yes | yes | no | yes | yes | `Capability::VISION` |

Attachments come before the text of the user message.
- `Conversation::toArray()` (what tracing sees) carries only `mime` and `size`, never the bytes. There is no filename on purpose.

### AWS Bedrock (optional)
```bash
composer require symfony/ai-bedrock-platform:~0.13.0 async-aws/bedrock-runtime
```
```php
use AsyncAws\BedrockRuntime\BedrockRuntimeClient;
use Lingoda\AiSdk\Client\Bedrock\BedrockClientFactory;

// Let async-aws build its own HTTP client: only then does it retry 429, 5xx and throttling
$bedrock = BedrockClientFactory::createClient(new BedrockRuntimeClient(['region' => 'eu-west-1']), $logger);
$platform = new Platform([$openAiClient, $bedrock], defaultProvider: 'openai');

$platform->ask($conversation, 'amazon.nova-2-lite-v1:0');                    // Nova 2 Lite
$platform->ask($conversation, 'anthropic.claude-haiku-4-5-20251001-v1:0');   // Claude Haiku 4.5
```
- Models are addressed by their Bedrock base id and sent through the region's cross-region inference profile (`eu.` or `us.`), which also ends up in `metadata.model`. Keeping data in the EU is up to you: pick an `eu-` region.
- Nova needs the conversation to open with the user message: an assistant prompt before it throws `UnsupportedCapabilityException`. Claude accepts it.
- Only `temperature`, `max_tokens` (default 4096) and `response_format` are passed on; other options are dropped. `response_format` works on Claude Haiku 4.5, Sonnet 4.5/4.6 and Opus 4.5/4.6 and is rejected elsewhere. Claude Opus 4.7+, Sonnet 5 and Opus 5.x do not take a temperature, so it is dropped for them.
- Claude Opus 5.x thinks before answering: give it enough `max_tokens`, or the response has no text.
- Errors become `ClientException` with the HTTP status as code, without the previous exception and without the payload, so documents cannot leak into logs or Sentry. Never enable async-aws `debug` in production: it logs the full request body, document included.
- The runtime client needs an explicit region (config, `AWS_REGION` or `~/.aws/config`); the silent `us-east-1` fallback is refused, and only `eu-` and `us-` regions are accepted.
- Wrap it in `RateLimitedClient` with `retryTransportErrors: false`, so async-aws stays the only transport retry layer.

### Decisions with TypeSafe Jev
```php
use Lingoda\AiSdk\Client\TypeSafe\TypeSafeDecisionPlatform;
use Lingoda\AiSdk\Decision\Question;

$jev = new TypeSafeDecisionPlatform(HttpClient::create(), $apiKey);

$result = $jev->decide('Teacher log text', [
    'on_topic' => Question::noul('Is the log about the lesson?'),
    'outlook' => Question::choice('How likely is the student to pass?', ['green' => 'on track', 'red' => 'unlikely']),
]);

$result->getAnswer('on_topic')->isTrue();       // noul probability >= 0.5
$result->getAnswer('outlook')->choice;          // 'green'
```
Jev is a provider (`AIProvider::TYPESAFE`, models `jev-1.13.0`, `jev-latest`, `jev-preview`, exposed through `$jev->getProvider()`), but a decision provider, not a chat provider: it lives behind `DecisionPlatformInterface` and is never registered on `Platform`, so `ask()` cannot route to it. Unknown model ids throw `ModelNotFoundException` before any request. The data sanitizer does not run on the state.

## 🔧 Requirements

- **PHP ^8.4**
- ext-fileinfo
- Symfony components ^7.4 or ^8.0
- Optional for Bedrock: `symfony/ai-bedrock-platform` ~0.13.0 and `async-aws/bedrock-runtime`
- PSR-18 HTTP Client (Symfony HTTP Client included)
- PSR-7 HTTP Messages (nyholm/psr7 included)
- PSR-3 Logger (optional)

## 🎵 Audio Features

```php
use Lingoda\AiSdk\Audio\OpenAI\AudioOptions;

// Text-to-Speech
$options = AudioOptions::textToSpeech(
    model: AudioSpeechModel::TTS_1,
    voice: AudioSpeechVoice::NOVA,
    format: AudioSpeechFormat::MP3
);
$audioResult = $platform->textToSpeech('Hello world', $options);
file_put_contents('speech.mp3', $audioResult->getContent());

// Speech-to-Text
$transcription = $platform->transcribeAudio('audio.mp3', $transcriptionOptions);
echo $transcription->getContent(); // "Hello world"

// Translation to English
$translation = $platform->translateAudio('spanish-audio.mp3', $translationOptions);
```

## 📦 Installation

```bash
composer require lingoda/ai-sdk
```

## 🤖 Supported Models

**OpenAI Models:**
- GPT-5 series: `gpt-5`, `gpt-5-mini`, `gpt-5-nano` (latest)
- GPT-4.1 series: `gpt-4.1`, `gpt-4.1-mini`, `gpt-4.1-nano` (1M context)
- GPT-4o series: `gpt-4o`, `gpt-4o-mini` (128K context)
- Audio models: `whisper-1`, `tts-1`, `tts-1-hd`

**Anthropic Models:**
- Claude 4.1: `claude-opus-4-1-20250805`
- Claude 4.0: `claude-opus-4`, `claude-sonnet-4`
- Claude 3.7: `claude-3-7-sonnet`
- Claude 3.5: `claude-3-5-haiku`

**Google Gemini Models:**
- Gemini 3.1: `gemini-3.1-flash-lite` (1M context)
- Gemini 2.5: `gemini-2.5-pro`, `gemini-2.5-flash` (1M context)

**AWS Bedrock Models** (optional; ids are Bedrock base ids, sent through the region's `eu.`/`us.` inference profile):
- Amazon Nova: `amazon.nova-2-lite-v1:0` (default), `amazon.nova-pro-v1:0`, `amazon.nova-lite-v1:0`, `amazon.nova-micro-v1:0` (text only)
- Claude: `anthropic.claude-haiku-4-5-20251001-v1:0`, `anthropic.claude-sonnet-4-5-20250929-v1:0`, `anthropic.claude-opus-4-5-20251101-v1:0`, `anthropic.claude-sonnet-4-6`, `anthropic.claude-opus-4-6-v1`, `anthropic.claude-opus-4-7`, `anthropic.claude-opus-4-8`, `anthropic.claude-sonnet-5`, `anthropic.claude-opus-5`, `anthropic.claude-opus-5-5`

**TypeSafe Jev** (decisions, not chat, text only): `jev-1.13.0` (default), `jev-latest`, `jev-preview`

## 🚦 Quick Test

Run interactive examples to test the SDK:

```bash
# Offline examples (no API keys needed)
php docs/usage-example.php

# With OpenAI
OPENAI_API_KEY=your-key php docs/usage-example.php

# Multiple providers
OPENAI_API_KEY=sk-proj-... \
ANTHROPIC_API_KEY=sk-ant-... \
GEMINI_API_KEY=AIza... \
php docs/usage-example.php
```

## 🛠️ Development

```bash
# Install dependencies
composer install

# Run tests
vendor/bin/phpunit

# Static analysis
vendor/bin/phpstan analyse

# Code style
vendor/bin/ecs check --fix
```

## 🤝 Contributing

1. Fork the repository
2. Create a feature branch
3. Add tests for your changes
4. Ensure all tests pass
5. Submit a pull request

## 📄 License

MIT License. See [LICENSE](LICENSE) for details.

---

**Get Started**: [Installation Guide](docs/installation.md) | **Try Examples**: [Interactive Examples](docs/examples.md) | **Join Discussion**: [GitHub Issues](https://github.com/lingoda/ai-sdk/issues)
