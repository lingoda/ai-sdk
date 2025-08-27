# Quick Start Guide

Get up and running with the Lingoda AI SDK in minutes.

## Basic Usage

### Simple ask() Method

```php
use Lingoda\AiSdk\Platform;
use Lingoda\AiSdk\Client\OpenAI\OpenAIClientFactory;
use Lingoda\AiSdk\Exception\ClientException;

try {
    // Create client using factory and platform
    $client = OpenAIClientFactory::createClient('your-api-key');
    $platform = new Platform([$client]);

    // Simple text generation with automatic model selection
    $result = $platform->ask('Hello, how are you?');
    echo $result->getContent();
    print_r($result->getMetadata()); // usage info, model details, etc.

    // Specify a specific model
    $result = $platform->ask('Explain quantum physics', 'gpt-4o-mini');
    echo $result->getContent();
    
} catch (ClientException $e) {
    echo "API request failed: " . $e->getMessage();
}
```

### Using Prompts with ask()

```php
use Lingoda\AiSdk\Prompt\UserPrompt;
use Lingoda\AiSdk\Result\TextResult;

// Create a UserPrompt for more complex needs
$prompt = UserPrompt::create('Hello, how are you?');
$result = $platform->ask($prompt);

if ($result instanceof TextResult) {
    echo $result->getContent();
}

// With specific model
$result = $platform->ask($prompt, 'gpt-4o-mini');
```

## Working with Prompts

### Simple User Prompts

```php
use Lingoda\AiSdk\Prompt\UserPrompt;
use Lingoda\AiSdk\Prompt\SystemPrompt;
use Lingoda\AiSdk\Prompt\Role;

// Create individual prompts with automatic validation
$userPrompt = UserPrompt::create('What is machine learning?');
$systemPrompt = SystemPrompt::create('You are a helpful assistant');

// Access prompt properties
echo $userPrompt->getContent(); // "What is machine learning?"
echo $userPrompt->getRole();    // Role::USER enum
echo $userPrompt->toString();   // "What is machine learning?"

// Convert to array format for APIs
$array = $userPrompt->toArray(); // ['role' => 'user', 'content' => '...']
```

### Parameterized Prompts

```php
// Basic parameter substitution
$personalized = UserPrompt::create('Hello, {{name}}! How can I help you with {{topic}}?', [
    'name' => 'John',
    'topic' => 'machine learning'
]);
echo $personalized->getContent(); // "Hello, John! How can I help you with machine learning?"

// Alternative: using withParameters() method
$template = UserPrompt::create('Hello, {{name}}! How can I help you with {{topic}}?');
$personalized = $template->withParameters([
    'name' => 'John',
    'topic' => 'machine learning'
]);
```

## Conversations

```php
use Lingoda\AiSdk\Prompt\Conversation;
use Lingoda\AiSdk\Prompt\UserPrompt;
use Lingoda\AiSdk\Prompt\SystemPrompt;
use Lingoda\AiSdk\Prompt\AssistantPrompt;

// Simple user prompt
$conversation = Conversation::fromUser(UserPrompt::create('What is machine learning?'));

// With system prompt
$conversation = Conversation::withSystem(
    UserPrompt::create('Explain quantum computing'),
    SystemPrompt::create('You are a helpful AI assistant')
);

// Full conversation with multiple messages
$conversation = Conversation::conversation([
    UserPrompt::create('Explain quantum computing'),
    SystemPrompt::create('You are a helpful AI assistant'),
    AssistantPrompt::create('You asked about quantum computing. It is...')
]);

// Using with ask() method (with error handling)
try {
    $result = $platform->ask($conversation);
    
    // Or with a specific model
    $result = $platform->ask($conversation, 'claude-sonnet-4');
    
} catch (ClientException $e) {
    echo "API request failed: " . $e->getMessage();
}

```

## Next Steps

- [Installation & Setup](installation.md) - Detailed installation instructions
- [Configuration](configuration.md) - API keys and multiple providers
- [Audio Guide](audio.md) - Complete audio processing documentation
- [Advanced Usage](advanced-usage.md) - Complex prompts and features
- [Security](security.md) - Data sanitization and protection
- [Examples](examples.md) - Interactive examples and testing