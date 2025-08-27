# Symfony Integration

The Lingoda AI Bundle provides seamless integration with Symfony's dependency injection container, making it incredibly easy to use AI services in your Symfony applications.

## Installation

Install the Symfony bundle alongside the core SDK:

```bash
composer require lingoda/ai-bundle
```

## Configuration

### Option 1: Zero Configuration (Recommended)

Just add your API keys to `.env`:

```env
OPENAI_API_KEY=sk-your-openai-key
ANTHROPIC_API_KEY=sk-ant-your-anthropic-key  
GEMINI_API_KEY=your-gemini-key
```

The bundle works out of the box with sensible defaults.

### Option 2: Custom Configuration

For advanced use cases, create `config/packages/lingoda_ai.yaml`:

```yaml
lingoda_ai:
    default_provider: openai  # Used when ask() called without specifying model
    providers:
        openai:
            api_key: '%env(OPENAI_API_KEY)%'
            default_model: 'gpt-4o-2024-11-20'
        anthropic:
            api_key: '%env(ANTHROPIC_API_KEY)%'
            default_model: 'claude-3-5-sonnet-20241022'
        gemini:
            api_key: '%env(GEMINI_API_KEY)%'
            default_model: 'gemini-2.0-pro'
    sanitization:
        enabled: true
    logging:
        enabled: true
        service: 'monolog.logger.ai'
```

## Provider-Specific Platforms

The bundle automatically registers provider-specific platform services for type safety and better developer experience:

### OpenAI Platform Service

```php
use Lingoda\AiBundle\Platform\OpenAIPlatform;

class ContentService
{
    public function __construct(
        private OpenAIPlatform $openAIPlatform
    ) {}

    public function generateContent(string $prompt): string
    {
        // Automatically uses OpenAI's default model (gpt-4o-mini)
        $result = $this->openAIPlatform->ask($prompt);
        return $result->getContent();
    }
    
    public function generateWithSpecificModel(string $prompt): string
    {
        // Use a specific OpenAI model
        $result = $this->openAIPlatform->ask($prompt, 'gpt-4o-2024-11-20');
        return $result->getContent();
    }
}
```

### Anthropic Platform Service

```php
use Lingoda\AiBundle\Platform\AnthropicPlatform;

class AnalysisService  
{
    public function __construct(
        private AnthropicPlatform $anthropicPlatform
    ) {}

    public function analyzeText(string $text): string
    {
        // Automatically uses Anthropic's default model (claude-haiku-35)
        $result = $this->anthropicPlatform->ask("Analyze this text: $text");
        return $result->getContent();
    }
    
    public function deepAnalysis(string $text): string
    {
        // Use Claude Sonnet for more complex analysis
        $result = $this->anthropicPlatform->ask(
            "Provide detailed analysis: $text", 
            'claude-3-5-sonnet-20241022'
        );
        return $result->getContent();
    }
}
```

### Gemini Platform Service

```php
use Lingoda\AiBundle\Platform\GeminiPlatform;

class TranslationService
{
    public function __construct(
        private GeminiPlatform $geminiPlatform
    ) {}

    public function translate(string $text, string $targetLanguage): string
    {
        // Automatically uses Gemini's default model (gemini-2-5-flash)
        $result = $this->geminiPlatform->ask(
            "Translate to $targetLanguage: $text"
        );
        return $result->getContent();
    }
}
```

## Generic Platform Service

You can also inject the main platform service for multi-provider scenarios:

```php
use Lingoda\AiSdk\PlatformInterface;

class SmartContentService
{
    public function __construct(
        private PlatformInterface $aiPlatform  // Uses default provider
    ) {}

    public function generateContent(string $prompt): string
    {
        // Uses the configured default provider (openai in our example)
        $result = $this->aiPlatform->ask($prompt);
        return $result->getContent();
    }
    
    public function generateWithProvider(string $prompt, string $provider): string
    {
        // Switch between providers dynamically
        $specificProvider = $this->aiPlatform->getProvider($provider);
        $model = $specificProvider->getDefaultModel();
        $result = $this->aiPlatform->ask($prompt, $model);
        return $result->getContent();
    }
}
```

## Advanced Conversation Handling

The bundle works seamlessly with conversation objects:

```php
use Lingoda\AiSdk\Prompt\Conversation;
use Lingoda\AiSdk\Prompt\UserPrompt;
use Lingoda\AiSdk\Prompt\SystemPrompt;
use Lingoda\AiBundle\Platform\OpenAIPlatform;

class ChatService
{
    public function __construct(
        private OpenAIPlatform $openAIPlatform
    ) {}

    public function handleConversation(array $messages): string
    {
        $conversation = Conversation::withSystem(
            UserPrompt::create($messages['user']),
            SystemPrompt::create('You are a helpful assistant')
        );
        
        $result = $this->openAIPlatform->ask($conversation);
        return $result->getContent();
    }
}
```

## Error Handling in Services

```php
use Lingoda\AiSdk\Exception\ClientException;
use Lingoda\AiBundle\Platform\AnthropicPlatform;
use Psr\Log\LoggerInterface;

class RobustAiService
{
    public function __construct(
        private AnthropicPlatform $anthropicPlatform,
        private LoggerInterface $logger
    ) {}

    public function generateWithFallback(string $prompt): string
    {
        try {
            $result = $this->anthropicPlatform->ask($prompt);
            return $result->getContent();
        } catch (ClientException $e) {
            $this->logger->error('AI request failed', [
                'error' => $e->getMessage(),
                'prompt' => $prompt
            ]);
            
            return 'AI service temporarily unavailable. Please try again later.';
        }
    }
}
```

## Service Configuration

Services are automatically registered! No manual configuration needed:

```yaml
# config/services.yaml - No AI configuration needed!
services:
    # Your services will automatically receive the AI platforms via autowiring
    App\Service\ContentService:
        autowire: true
        autoconfigure: true
```

## Available Services

The bundle automatically registers these services based on environment variables:

- **`OpenAIPlatform`** - Available if `OPENAI_API_KEY` is set
- **`AnthropicPlatform`** - Available if `ANTHROPIC_API_KEY` is set  
- **`GeminiPlatform`** - Available if `GEMINI_API_KEY` is set
- **`Platform`** - Generic platform with all configured clients
- **`PlatformInterface`** - Interface alias to the generic platform

## Migration from Raw SDK

If you're migrating from raw SDK usage to the bundle:

### Before (Raw SDK)
```php
$client = new OpenAIClient($httpClient, $apiKey);
$platform = new Platform([$client]);
$provider = $client->getProvider();
$model = $provider->getModel('gpt-4o-2024-11-20');
$result = $platform->ask($prompt, $model->getId());
```

### After (Bundle)
```php
// Inject provider-specific platform
public function __construct(private OpenAIPlatform $openAIPlatform) {}

// Simple one-liner
$result = $this->openAIPlatform->ask($prompt);
```

This reduces verbose setup code by ~80% while maintaining full type safety and IDE autocomplete support.

## Benefits

- **Flexible Configuration**: Works with zero config or full customization
- **Automatic Service Registration**: Services registered based on configuration
- **Type Safety**: Provider-specific platforms with full IDE autocomplete support  
- **Decoration Pattern**: Clean architecture with decorated base Platform
- **Modern Bundle Design**: Uses Bundle load() method for configuration processing
- **Dependency Injection**: Full Symfony autowiring support
- **Data Sanitization**: Built-in protection for sensitive information
- **Logging Integration**: Optional Symfony logger integration
- **Performance**: Singleton services for optimal connection reuse
- **Security**: Environment variable-based API key management

## Next Steps

- [Security](security.md) - Data protection and sanitization
- [Examples](examples.md) - Interactive examples and testing
- [API Reference](api-reference.md) - Complete API documentation