# Installation & Setup

## Requirements

- **PHP ^8.3**
- PSR-18 HTTP Client
- PSR-3 Logger (optional)

## Installation

Install the Lingoda AI SDK via Composer:

```bash
composer require lingoda/ai-sdk
```

## Basic Setup

The Platform is your main entry point for all AI operations. Here's the minimal setup:

```bash
# Install an HTTP client (required)
composer require symfony/http-client
```

```php
use Lingoda\AiSdk\Platform;
use Lingoda\AiSdk\Client\OpenAI\OpenAIClient;
use Lingoda\AiSdk\Enum\OpenAI\ChatModel;
use Lingoda\AiSdk\Prompt\UserPrompt;
use Symfony\Component\HttpClient\Psr18Client;

// Create HTTP client
$httpClient = new Psr18Client();

// Create AI client
$client = new OpenAIClient($httpClient, 'your-api-key');

// Create Platform (main entry point)
$platform = new Platform([$client]);

$result = $platform->ask('Hello!');
```

## Platform Features

The Platform provides:

- **Multi-Provider Support**: Use multiple AI providers simultaneously
- **Built-in Security**: Data sanitization enabled by default
- **Rate Limiting**: Automatic rate limiting with token estimation
- **Type Safety**: Strongly-typed results and prompts

## Verification

Test your installation without API keys:

```php
<?php

require_once 'vendor/autoload.php';

use Lingoda\AiSdk\Platform;
use Lingoda\AiSdk\Provider\OpenAIProvider;
use Lingoda\AiSdk\Enum\OpenAI\ChatModel;

// Create Platform (works without HTTP client for model info)
$provider = new OpenAIProvider();
$model = $provider->getModel(ChatModel::GPT_4O_2024_11_20->value);

echo "✅ Installation successful!\n";
echo "Model: " . $model->getName() . "\n";
echo "Capabilities: " . implode(', ', $model->getCapabilities()) . "\n";
echo "Platform ready for AI operations!\n";
```

## Development

The SDK includes development dependencies for testing and code quality:

```bash
# Run tests
vendor/bin/phpunit

# Static analysis
vendor/bin/phpstan analyse

# Code style
vendor/bin/ecs check
```

## Next Steps

| Step | Guide | Description |
|------|-------|-------------|
| 1 | [Configuration](configuration.md) | Set up API keys and providers |
| 2 | [Quick Start](quick-start.md) | Your first Platform request |
| 3 | [HTTP Clients](http-clients.md) | Advanced HTTP configuration |
| 4 | [Logging](logging.md) | Debug and monitoring setup |
| 5 | [Examples](examples.md) | Interactive examples and testing |