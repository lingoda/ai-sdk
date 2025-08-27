# Configuration

Configure the Lingoda AI SDK with multiple AI providers and customization options.

## Single Provider Setup

### OpenAI Configuration

```php
use Lingoda\AiSdk\Client\OpenAI\OpenAIClientFactory;
use Lingoda\AiSdk\Platform;
use Lingoda\AiSdk\Enum\OpenAI\ChatModel;

// Using factory (recommended)
$client = OpenAIClientFactory::createClient('your-api-key', 'your-org-id');
$platform = new Platform([$client]);

// Get provider and model
$provider = $client->getProvider();
$model = $provider->getModel(ChatModel::GPT_4O_MINI->value);

// Alternative: direct instantiation
$client = new OpenAIClient($httpClient, $apiKey, $organization);
```

### Anthropic Configuration

```php
use Lingoda\AiSdk\Client\Anthropic\AnthropicClient;
use Lingoda\AiSdk\Enum\Anthropic\ChatModel;

$client = new AnthropicClient($httpClient, $apiKey);
$platform = new Platform([$client]);

// Get provider and model
$provider = $client->getProvider();
$model = $provider->getModel(ChatModel::CLAUDE_3_5_SONNET_20241022->value);
```

### Google Gemini Configuration

```php
use Lingoda\AiSdk\Client\Gemini\GeminiClient;
use Lingoda\AiSdk\Enum\Gemini\ChatModel;

$client = new GeminiClient($httpClient, $apiKey);
$platform = new Platform([$client]);

// Get provider and model
$provider = $client->getProvider();
$model = $provider->getModel(ChatModel::GEMINI_2_5_FLASH->value);
```

## Multi-Provider Setup

Configure multiple providers for flexible model access:

```php
use Lingoda\AiSdk\Platform;
use Lingoda\AiSdk\Client\OpenAI\OpenAIClient;
use Lingoda\AiSdk\Client\Anthropic\AnthropicClient;
use Lingoda\AiSdk\Client\Gemini\GeminiClient;

// Create clients
$openAIClient = new OpenAIClient($httpClient, $openAIApiKey);
$anthropicClient = new AnthropicClient($httpClient, $anthropicApiKey);
$geminiClient = new GeminiClient($httpClient, $geminiApiKey);

// Create platform with multiple clients
$platform = new Platform([
    $openAIClient,
    $anthropicClient,
    $geminiClient
]);

// Get providers from clients when needed
$openAIProvider = $openAIClient->getProvider();
$anthropicProvider = $anthropicClient->getProvider();
$geminiProvider = $geminiClient->getProvider();

// Access providers from the Platform as needed
```

## HTTP Client Configuration

### Basic Configuration

```php
use GuzzleHttp\Client;

$httpClient = new Client([
    'timeout' => 30,
    'connect_timeout' => 10,
    'headers' => [
        'User-Agent' => 'My-App/1.0'
    ]
]);
```

### Advanced Configuration

```php
use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;

// Create handler stack for middleware
$stack = HandlerStack::create();

// Add retry middleware
$stack->push(Middleware::retry(
    function ($retries, $request, $response, $exception) {
        return $retries < 3 && ($exception || $response->getStatusCode() >= 500);
    },
    function ($retries) {
        return $retries * 1000; // Exponential backoff
    }
));

$httpClient = new Client([
    'handler' => $stack,
    'timeout' => 60,
    'connect_timeout' => 15,
    'proxy' => 'http://proxy.example.com:8080', // Optional proxy
]);
```

## Logging Configuration

```php
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Lingoda\AiSdk\Platform;

$logger = new Logger('ai-sdk');
$logger->pushHandler(new StreamHandler('var/log/ai-sdk.log', Logger::INFO));

$platform = new Platform(
    clients: $clients,
    enableSanitization: true,
    logger: $logger
);
```

## Data Sanitization Configuration

### Default Sanitization (Recommended)

```php
$platform = new Platform($clients); // Sanitization enabled by default
```

### Custom Sanitization

```php
use Lingoda\AiSdk\Security\DataSanitizer;
use Lingoda\AiSdk\Security\Pattern\PatternRegistry;

$patternRegistry = PatternRegistry::createDefault();
$customSanitizer = DataSanitizer::createDefault($patternRegistry, $logger);

$platform = new Platform(
    clients: $clients,
    enableSanitization: true,
    sanitizer: $customSanitizer,
    logger: $logger
);
```

### Disable Sanitization (Not Recommended)

```php
$platform = new Platform($clients, enableSanitization: false);
```

## Rate Limiting Configuration

Rate limiting is built into individual clients and configured per provider.

## Model Selection

### Default Model Selection

The Platform uses the configured default provider or single available model:

```php
// Uses default model from configured default_provider
$result = $platform->ask($prompt); // uses default model
```

### Manual Selection with Capability Validation

```php
use Lingoda\AiSdk\Enum\Capability;
use Lingoda\AiSdk\Exception\ClientException;
use Lingoda\AiSdk\Exception\UnsupportedCapabilityException;
use Lingoda\AiSdk\Model\CapabilityValidator;

try {
    // Get specific model
    $openAIProvider = $openAIClient->getProvider();
    $model = $openAIProvider->getModel(ChatModel::GPT_4O_2024_11_20->value);
    
    // Validate model capabilities before use
    CapabilityValidator::requireCapability($model, Capability::VISION);
    
    $result = $platform->ask($prompt, $model->getId());
} catch (UnsupportedCapabilityException $e) {
    echo "Model lacks required capability: " . $e->getMessage();
} catch (ClientException $e) {
    echo "API request failed: " . $e->getMessage();
}
```

## Next Steps

- [Quick Start](quick-start.md) - Your first AI request
- [Audio Guide](audio.md) - Complete audio processing documentation
- [HTTP Clients](http-clients.md) - Advanced HTTP configuration
- [Logging](logging.md) - Debug and monitoring setup
- [Advanced Usage](advanced-usage.md) - Complex features and patterns
- [Security](security.md) - Data protection and sanitization
- [Examples](examples.md) - Interactive examples with real API calls