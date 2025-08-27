# API Reference

Complete reference for models, capabilities, and features in the Lingoda AI SDK.

## Supported Models

### OpenAI Models

#### GPT-5 Series (Latest - August 2025)
- `gpt-5` - Flagship GPT-5 model with advanced reasoning
- `gpt-5-2025-08-07` - Specific GPT-5 version (August 2025)
- `gpt-5-mini` - Efficient GPT-5 variant
- `gpt-5-mini-2025-08-07` - Specific GPT-5 Mini version
- `gpt-5-nano` - Lightweight GPT-5 variant
- `gpt-5-nano-2025-08-07` - Specific GPT-5 Nano version

**Context Window**: 2M tokens (flagship), 1M tokens (mini/nano)
**Capabilities**: Text, Tools, Vision, Multimodal, Reasoning

#### GPT-4.1 Series (April 2025) 
- `gpt-4.1` - Latest GPT-4.1 model
- `gpt-4.1-2025-04-14` - Specific GPT-4.1 version
- `gpt-4.1-mini` - Efficient GPT-4.1 variant
- `gpt-4.1-mini-2025-04-14` - Specific GPT-4.1 Mini version
- `gpt-4.1-nano` - Lightweight GPT-4.1 variant
- `gpt-4.1-nano-2025-04-14` - Specific GPT-4.1 Nano version

**Context Window**: 1M tokens
**Capabilities**: Text, Tools, Vision, Multimodal (flagship/mini), Text + Tools (nano)

#### GPT-4o Series (Current)
- `gpt-4o` - Latest GPT-4o model
- `gpt-4o-2024-11-20` - Specific GPT-4o version
- `gpt-4o-mini` - Cost-effective GPT-4o variant  
- `gpt-4o-mini-2024-07-18` - Specific GPT-4o Mini version

**Context Window**: 128K tokens
**Capabilities**: Text, Tools, Vision, Multimodal

#### GPT-4 Turbo (Legacy)
- `gpt-4-turbo` - GPT-4 Turbo model
- `gpt-4-turbo-2024-04-09` - Specific GPT-4 Turbo version

**Context Window**: 128K tokens
**Capabilities**: Text, Tools, Vision

#### Audio Transcription Models
- `whisper-1` - Full-featured speech-to-text with translation
- `gpt-4o-mini-transcribe` - GPT-4o Mini for transcription only
- `gpt-4o-transcribe` - GPT-4o for transcription only

**Features**: Max 25MB file size, 1 hour duration, 60+ languages

```php
use Lingoda\AiSdk\Provider\OpenAIProvider;
use Lingoda\AiSdk\Enum\OpenAI\ChatModel;
use Lingoda\AiSdk\Exception\ModelNotFoundException;

try {
    $provider = new OpenAIProvider();
    $model = $provider->getModel(ChatModel::GPT_4O_MINI->value);
    // or use string directly: $model = $provider->getModel('gpt-4o-mini');
    
    echo "Model: " . $model->getDisplayName() . "\n";
    echo "Max Tokens: " . $model->getMaxTokens() . "\n";
    
    // Convert enum capabilities to strings for display
    $capabilities = array_map(fn($cap) => $cap->value, $model->getCapabilities());
    echo "Capabilities: " . implode(', ', $capabilities) . "\n";
} catch (ModelNotFoundException $e) {
    echo "Model not available: " . $e->getMessage();
}
```

### Anthropic Models

#### Claude 4.1 Series (Latest - August 2025)
- `claude-opus-4-1-20250805` - Most advanced Claude model

**Context Window**: 200K tokens, **Output**: 32K tokens
**Capabilities**: Text, Tools, Vision, Multimodal

#### Claude 4.0 Series (Current - May 2025)
- `claude-opus-4-20250514` - Premium Claude 4 model
- `claude-sonnet-4-20250514` - Balanced Claude 4 model

**Context Window**: 200K tokens
**Output**: 32K tokens (Opus), 64K tokens (Sonnet)
**Capabilities**: Text, Tools, Vision, Multimodal

#### Claude 3.7 Series (Current - February 2025)
- `claude-3-7-sonnet-20250219` - Enhanced Sonnet model

**Context Window**: 200K tokens, **Output**: 64K tokens
**Capabilities**: Text, Tools, Vision, Multimodal

#### Claude 3.5 Series (Active)
- `claude-3-5-haiku-20241022` - Fast and efficient model

**Context Window**: 200K tokens, **Output**: 8K tokens
**Capabilities**: Text, Tools, Vision, Multimodal

#### Claude 3 Series (Active)
- `claude-3-haiku-20240307` - Lightweight model

**Context Window**: 200K tokens, **Output**: 4K tokens
**Capabilities**: Text, Tools, Vision, Multimodal

```php
use Lingoda\AiSdk\Provider\AnthropicProvider;
use Lingoda\AiSdk\Enum\Anthropic\ChatModel;
use Lingoda\AiSdk\Exception\ModelNotFoundException;
use Lingoda\AiSdk\Exception\ClientException;

try {
    $provider = new AnthropicProvider();
    $model = $provider->getModel(ChatModel::CLAUDE_3_5_SONNET_20241022->value);
} catch (ModelNotFoundException $e) {
    echo "Model not available: " . $e->getMessage();
}
```

### Google Gemini Models

#### Gemini 2.5 Series (Current)
- `gemini-2.5-pro` - Advanced reasoning and complex tasks
- `gemini-2.5-flash` - Fast responses and efficient processing

**Context Window**: 1M tokens
**Capabilities**: Text, Tools, Vision, Multimodal
**Parameters**: Temperature, Max Output Tokens, Top-P, Top-K

```php
use Lingoda\AiSdk\Provider\GeminiProvider;
use Lingoda\AiSdk\Enum\Gemini\ChatModel;
use Lingoda\AiSdk\Exception\ModelNotFoundException;
use Lingoda\AiSdk\Exception\ClientException;

try {
    $provider = new GeminiProvider();
    $model = $provider->getModel(ChatModel::GEMINI_2_5_FLASH->value);
} catch (ModelNotFoundException $e) {
    echo "Model not available: " . $e->getMessage();
}
```

## Model Capabilities

### Capability System

Models declare their supported features through the capability system:

```php
use Lingoda\AiSdk\Enum\Capability;
use Lingoda\AiSdk\Exception\UnsupportedCapabilityException;
use Lingoda\AiSdk\Model\CapabilityValidator;

$capabilities = $model->getCapabilities();

// Check for specific capabilities using the hasCapability method
$supportsVision = $model->hasCapability(Capability::VISION);
$supportsTools = $model->hasCapability(Capability::TOOLS);
$supportsStreaming = $model->hasCapability(Capability::STREAMING);

// Strict capability validation with exceptions
try {
    CapabilityValidator::requireCapability($model, Capability::VISION);
    CapabilityValidator::requireCapabilities($model, [
        Capability::VISION,
        Capability::TOOLS
    ]);
} catch (UnsupportedCapabilityException $e) {
    echo "Capability validation failed: " . $e->getMessage();
}
```

### Available Capabilities

#### `Capability::TEXT`
Basic text generation and completion
- **Supported by**: All models
- **Use case**: General text generation, Q&A, content creation

#### `Capability::TOOLS`
Function calling and tool usage
- **Supported by**: All current models (GPT-5/4.1/4o, Claude 4/3, Gemini 2.5)
- **Use case**: API calls, calculations, structured data extraction

#### `Capability::VISION`
Image analysis and understanding
- **Supported by**: All current models (GPT-5/4.1/4o, Claude 4/3, Gemini 2.5)
- **Use case**: Image description, visual analysis, chart reading

#### `Capability::MULTIMODAL`
Combined text, image, and multimedia processing
- **Supported by**: GPT-5/4.1/4o, Claude 4/3, Gemini 2.5
- **Use case**: Complex multimedia analysis, cross-modal tasks

#### `Capability::REASONING`
Advanced reasoning and complex problem-solving
- **Supported by**: GPT-5 series
- **Use case**: Mathematical reasoning, logical deduction, complex analysis

#### Audio Capabilities

#### `Capability::AUDIO_TRANSCRIPTION`
Convert speech to text
- **Supported by**: All OpenAI audio models
- **Use case**: Speech-to-text conversion

#### `Capability::AUDIO_TRANSLATION`
Translate speech to English text
- **Supported by**: Whisper-1 only
- **Use case**: Multilingual audio to English translation

#### `Capability::AUDIO_TIMESTAMPS`
Include timing information in transcriptions
- **Supported by**: Whisper-1 only
- **Use case**: Subtitles, timed transcriptions

## Audio Transcription Reference

### Model Comparison

| Model | Response Formats | Timestamps | Translation | Language Detection |
|-------|------------------|------------|-------------|-------------------|
| `whisper-1` | json, text, srt, verbose_json, vtt | ✅ | ✅ | ✅ |
| `gpt-4o-mini-transcribe` | json, text | ❌ | ❌ | ✅ |
| `gpt-4o-transcribe` | json, text | ❌ | ❌ | ✅ |

**Common Features**: Max 25MB file size, 1 hour duration, Temperature control

### AudioOptions API

```php
use Lingoda\AiSdk\Audio\OpenAI\AudioOptions;
use Lingoda\AiSdk\Enum\OpenAI\AudioTranscribeModel;

// Speech-to-text
$options = AudioOptions::speechToText(
    model: AudioTranscribeModel::WHISPER_1,
    language: 'en',                    // Optional: Language code
    includeTimestamps: true,           // Optional: Add timestamps (whisper-1 only)
    responseFormat: 'verbose_json',    // Optional: Response format
    temperature: 0.0                   // Optional: Sampling temperature
);

// Translation to English
$options = AudioOptions::translate(
    model: AudioTranscribeModel::WHISPER_1,
    responseFormat: 'text',
    temperature: 0.0
);
```

### Response Formats

- **`json`** - Basic JSON response with text
- **`text`** - Plain text response
- **`srt`** - SubRip subtitle format (whisper-1 only)
- **`verbose_json`** - JSON with timestamps and metadata
- **`vtt`** - WebVTT subtitle format (whisper-1 only)

### Graceful Degradation

The SDK automatically handles unsupported features:

```php
// Unsupported features are silently ignored or replaced with alternatives
$options = AudioOptions::speechToText(
    model: AudioTranscribeModel::GPT_4O_MINI_TRANSCRIBE,
    includeTimestamps: true,  // Ignored - not supported
    responseFormat: 'srt'     // Falls back to 'json'
);
```

## Result Types

### TextResult

Standard text responses from AI models:

```php
use Lingoda\AiSdk\Result\TextResult;

if ($result instanceof TextResult) {
    $content = $result->getContent();      // string
    $metadata = $result->getMetadata();    // array
}
```

### ToolCallResult

Function calling and tool usage results:

```php
use Lingoda\AiSdk\Result\ToolCallResult;

if ($result instanceof ToolCallResult) {
    $toolCalls = $result->getToolCalls();  // array
    $metadata = $result->getMetadata();    // array
}
```

### ObjectResult

Structured object responses:

```php
use Lingoda\AiSdk\Result\ObjectResult;

if ($result instanceof ObjectResult) {
    $object = $result->getObject();        // object
    $metadata = $result->getMetadata();    // array
}
```

## Security Attributes Reference

### Redact Attribute

Apply custom regex patterns with specific replacements:

```php
use Lingoda\AiSdk\Security\Attribute\Redact;

#[Redact(pattern: '/regex/', replacement: '[REDACTED]')]
```

**Parameters:**
- `pattern` (string): Regex pattern to match
- `replacement` (string): Replacement text (default: `[REDACTED]`)

**Examples:**
```php
#[Redact('/\b[\w\.-]+@[\w\.-]+\.\w+\b/', '[EMAIL]')]
public string $email;

#[Redact('/\b\d{4}-\d{4}-\d{4}-\d{4}\b/', '[CARD]')]
public string $creditCard;

#[Redact('/\bsk-[a-zA-Z0-9]{48}\b/', '[API_KEY]')]
public string $openaiKey;
```

### Sensitive Attribute

Use built-in sensitive content detection:

```php
use Lingoda\AiSdk\Security\Attribute\Sensitive;

#[Sensitive(type: 'pii', redactionText: '[PII_DETECTED]')]
```

**Parameters:**
- `type` (string|null): Optional sensitivity type for categorization
- `redactionText` (string): Replacement text (default: `[REDACTED]`)

**Examples:**
```php
#[Sensitive(redactionText: '[CONFIDENTIAL]')]
public string $notes;

#[Sensitive(type: 'pii', redactionText: '[PERSONAL_INFO]')]
public string $personalData;

#[Sensitive(type: 'financial', redactionText: '[FINANCIAL]')]
public array $transactions;
```

### Attribute Features

- **Repeatable**: Multiple attributes can be applied to the same property
- **Array Support**: Automatically processes array values and keys
- **Nested Objects**: Recursively processes nested object properties
- **Type Safety**: Works with all property types (string, array, object)

## Prompt Classes Reference

### UserPrompt

User messages in conversations with full parameter support:

```php
use Lingoda\AiSdk\Prompt\UserPrompt;
use Lingoda\AiSdk\Prompt\Role;

// Basic creation
$prompt = UserPrompt::create('Hello, how are you?');

// With parameters at creation
$prompt = UserPrompt::create('Hello, {{name}}! Tell me about {{topic}}.', [
    'name' => 'Alice',
    'topic' => 'machine learning'
]);

// Using withParameters method
$template = UserPrompt::create('Analyze {{document}} for {{criteria}}');
$prompt = $template->withParameters([
    'document' => 'report.pdf',
    'criteria' => 'security vulnerabilities'
]);

// Parameter discovery
$paramNames = $template->getParameterNames(); // ['document', 'criteria']
$hasParams = $template->hasParameters();      // true

// Data type support
$prompt = UserPrompt::create('User {{id}}: {{active}}, Score: {{score}}', [
    'id' => 12345,           // number → "12345"
    'active' => true,        // boolean → "true"  
    'score' => 98.7,         // float → "98.7"
    'metadata' => ['x' => 1] // array → JSON string
]);

// Access methods
$content = $prompt->getContent();      // string (with parameters substituted)
$role = $prompt->getRole();           // Role::USER
$string = $prompt->toString();        // string (same as getContent)
$array = $prompt->toArray();          // ['role' => 'user', 'content' => '...']

// Validation
// UserPrompt::create(''); // throws InvalidArgumentException for empty content
```

### SystemPrompt

System instructions for AI models with parameter support:

```php
use Lingoda\AiSdk\Prompt\SystemPrompt;

// Basic system instruction
$prompt = SystemPrompt::create('You are a helpful assistant');

// With parameters
$prompt = SystemPrompt::create('You are a {{role}} assistant specializing in {{domain}}.', [
    'role' => 'helpful',
    'domain' => 'software development'
]);

// Constructor vs method approach
$template = SystemPrompt::create('You are a {{expertise}} expert. {{instructions}}');
$customized = $template->withParameters([
    'expertise' => 'cybersecurity',
    'instructions' => 'Always prioritize security best practices.'
]);

// All UserPrompt methods available
$role = $prompt->getRole(); // Role::SYSTEM
$content = $prompt->getContent();
$array = $prompt->toArray(); // ['role' => 'system', 'content' => '...']
```

### AssistantPrompt

Previous assistant responses for multi-turn conversations:

```php
use Lingoda\AiSdk\Prompt\AssistantPrompt;
use Lingoda\AiSdk\Prompt\UserPrompt;
use Lingoda\AiSdk\Prompt\SystemPrompt;
use Lingoda\AiSdk\Prompt\Conversation;

// Build a conversation with previous context
$systemPrompt = SystemPrompt::create('You are a helpful AI tutor explaining complex topics.');

// Assistant's previous response (from earlier in conversation)
$assistantPrompt = AssistantPrompt::create(
    'I explained that neural networks consist of interconnected layers of nodes. ' .
    'Each node performs weighted calculations and applies activation functions.'
);

// User's follow-up question
$userPrompt = UserPrompt::create('Can you explain backpropagation in more detail?');

// Create conversation with full context
$conversation = Conversation::conversation($userPrompt, $systemPrompt, $assistantPrompt);

// Send to AI with previous context (with error handling)
try {
    $result = $platform->ask($conversation);
} catch (ClientException $e) {
    echo "API request failed: " . $e->getMessage();
}

// Access individual parts
$role = $assistantPrompt->getRole(); // Role::ASSISTANT
$content = $assistantPrompt->getContent(); // Previous response text
```

### Conversation

Combines multiple prompts for structured conversations:

```php
use Lingoda\AiSdk\Prompt\Conversation;

// Factory methods for different conversation patterns
$conversation = Conversation::fromUser($userPrompt);
$conversation = Conversation::withSystem($userPrompt, $systemPrompt);
$conversation = Conversation::conversation($userPrompt, $systemPrompt, $assistantPrompt);

// From array formats (API compatibility)
$conversation = Conversation::fromArray(['content' => 'Simple user message']);

$conversation = Conversation::fromArray([
    'messages' => [
        ['role' => 'system', 'content' => 'You are a helpful assistant'],
        ['role' => 'user', 'content' => 'What is quantum computing?'],
        ['role' => 'assistant', 'content' => 'Quantum computing is...']
    ]
]);

// Access individual prompts (returns Prompt objects)
$userPrompt = $conversation->getUserPrompt();        // UserPrompt
$systemPrompt = $conversation->getSystemPrompt();    // SystemPrompt|null
$assistantPrompt = $conversation->getAssistantPrompt(); // AssistantPrompt|null

// Direct content access (convenience methods)
$userContent = $conversation->getUserContent();      // string
$systemContent = $conversation->getSystemContent();  // string|null

// Convert to API format
$array = $conversation->toArray(); // Full conversation as array

// Usage with Platform (with error handling)
try {
    $result = $platform->ask($conversation);
} catch (ClientException $e) {
    echo "API request failed: " . $e->getMessage();
}
```

### Parameter Substitution Features

All prompt types support the same parameter system:

```php
// Template syntax
$template = UserPrompt::create('Process {{file}} with {{settings}} configuration');

// Parameter discovery
$params = $template->getParameterNames(); // ['file', 'settings']
$hasParams = $template->hasParameters();   // true

// Partial substitution (missing params preserved)
$partial = $template->withParameters(['file' => 'document.pdf']);
echo $partial->getContent(); 
// "Process document.pdf with {{settings}} configuration"

// Type conversion
$prompt = UserPrompt::create('Data: {{data}}', [
    'data' => ['key' => 'value'] // Converts to JSON: "Data: {\"key\":\"value\"}"
]);

// Immutability (original unchanged)
$original = UserPrompt::create('Hello {{name}}');
$personalized = $original->withParameters(['name' => 'World']);
// $original still contains "Hello {{name}}"
// $personalized contains "Hello World"
```

### Role Enum

Type-safe role identification:

```php
use Lingoda\AiSdk\Prompt\Role;

// Enum values
Role::USER      // 'user'
Role::SYSTEM    // 'system' 
Role::ASSISTANT // 'assistant'

// Usage in conditionals
if ($prompt->getRole() === Role::USER) {
    echo "This is a user prompt";
}

// Role-based processing
match ($prompt->getRole()) {
    Role::USER => $this->processUserInput($prompt),
    Role::SYSTEM => $this->validateSystemPrompt($prompt),
    Role::ASSISTANT => $this->handleAssistantContext($prompt),
};
```

## Platform Configuration

### Constructor Parameters

```php
use Lingoda\AiSdk\Platform;

$platform = new Platform(
    clients: array $clients,                              // Required: Array of AI clients
    enableSanitization: bool $enableSanitization = true,  // Optional: Enable data sanitization
    sanitizer: ?DataSanitizer $sanitizer = null,          // Optional: Custom sanitizer
    logger: ?LoggerInterface $logger = null               // Optional: PSR-3 logger
);
```

### Method Reference

```php
// Main invocation method
public function ask(string|Prompt|Conversation $input, ?string $model = null, array $options = []): ResultInterface

// Provider management
public function getProvider(string $name): ProviderInterface
public function getAvailableProviders(): array
public function hasProvider(string $name): bool
```

## Exception Hierarchy

### Base Exceptions

- `AiSdkException` - Base exception for all SDK errors
- `ClientException` - Client-related errors (HTTP, API communication)
- `InvalidArgumentException` - Invalid input parameters  
- `RuntimeException` - Runtime execution errors

### Specific Exceptions

- `ModelNotFoundException` - Requested model not available
- `RateLimitExceededException` - Rate limiting errors (extends RuntimeException)
- `UnsupportedCapabilityException` - Model lacks required capability (extends InvalidArgumentException)

```php
use Lingoda\AiSdk\Exception\ModelNotFoundException;
use Lingoda\AiSdk\Exception\RateLimitExceededException;
use Lingoda\AiSdk\Exception\InvalidArgumentException;
use Lingoda\AiSdk\Exception\RuntimeException;

try {
    $result = $platform->ask($prompt);
} catch (RateLimitExceededException $e) {
    // Handle rate limiting
    $retryAfter = $e->getRetryAfter();
    echo "Rate limited. Retry after: {$retryAfter} seconds";
} catch (ModelNotFoundException $e) {
    // Handle missing model
    echo "Model not found: " . $e->getMessage();
} catch (InvalidArgumentException $e) {
    // Handle invalid input
    echo "Invalid input: " . $e->getMessage();
} catch (RuntimeException $e) {
    // Handle runtime errors
    echo "Runtime error: " . $e->getMessage();
}
```

## Next Steps

- [Audio Guide](audio.md) - Complete audio processing documentation  
- [Examples](examples.md) - Interactive examples with all features
- [Security](security.md) - Data protection implementation
- [Advanced Usage](advanced-usage.md) - Complex patterns and optimization