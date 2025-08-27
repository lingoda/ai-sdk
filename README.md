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
- **🌐 Multi-Provider** - OpenAI, Anthropic, Gemini support with flexible configuration
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
- **Providers**: Manage models for each AI service (OpenAI, Anthropic, Gemini)  
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

## 🔧 Requirements

- **PHP ^8.3**
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
- Gemini 2.5: `gemini-2.5-pro`, `gemini-2.5-flash` (1M context)

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