# Advanced Usage

Explore advanced features and patterns in the Lingoda AI SDK.

## Advanced Prompt Management

### Parameter Discovery and Validation

```php
use Lingoda\AiSdk\Prompt\UserPrompt;

$template = UserPrompt::create('Analyze {{document}} focusing on {{analysis_type}} by {{author}}.');

// Discover parameters
$parameters = $template->getParameterNames(); // ['document', 'analysis_type', 'author']
$hasParams = $template->hasParameters();       // true

// Partial parameter substitution
$partial = $template->withParameters(['document' => 'report.pdf']);
echo $partial->getContent(); // "Analyze report.pdf focusing on {{analysis_type}} by {{author}}."
```

### Advanced Parameter Types

```php
// Supports various data types
$prompt = UserPrompt::create('User: {{id}}, Active: {{active}}, Score: {{score}}, Data: {{metadata}}');
$filled = $prompt->withParameters([
    'id' => 12345,           // number -> "12345"
    'active' => true,        // boolean -> "true"
    'score' => 98.7,         // float -> "98.7"
    'metadata' => ['x' => 1] // array -> JSON string
]);
```

## Complex Conversations

### Full Conversation Management

```php
use Lingoda\AiSdk\Prompt\Conversation;
use Lingoda\AiSdk\Prompt\UserPrompt;
use Lingoda\AiSdk\Prompt\SystemPrompt;
use Lingoda\AiSdk\Prompt\AssistantPrompt;

// Multi-role conversation
$conversation = Conversation::conversation(
    UserPrompt::create('What about its applications?'),
    SystemPrompt::create('You are a physics expert'),
    AssistantPrompt::create('I previously explained the basics...')
);

// Access individual components
$userPrompt = $conversation->getUserPrompt();     // UserPrompt object
$systemPrompt = $conversation->getSystemPrompt(); // SystemPrompt|null

// Direct content access
$userContent = $conversation->getUserContent();     // string
$systemContent = $conversation->getSystemContent(); // string|null
```

### API Format Integration

```php
// From API message arrays
$conversation = Conversation::fromArray([
    'messages' => [
        ['role' => 'system', 'content' => 'System instructions'],
        ['role' => 'user', 'content' => 'User question']
    ]
]);

// Simple content format
$conversation = Conversation::fromArray([
    'content' => 'User message'
]);
```

## Audio Processing

For comprehensive audio processing documentation, see the [Audio Guide](audio.md).

### OpenAI Audio Transcription

```php
use Lingoda\AiSdk\Audio\OpenAI\AudioOptions;
use Lingoda\AiSdk\Enum\OpenAI\AudioTranscribeModel;

// Full-featured transcription with Whisper-1
$options = AudioOptions::speechToText(
    model: AudioTranscribeModel::WHISPER_1,
    language: 'en',
    includeTimestamps: true,  // Adds timestamp_granularities
    responseFormat: 'verbose_json'
);

// GPT-4o models with graceful degradation
$options = AudioOptions::speechToText(
    model: AudioTranscribeModel::GPT_4O_MINI_TRANSCRIBE,
    language: 'fr',
    includeTimestamps: true,  // Silently ignored (not supported)
    responseFormat: 'srt'     // Falls back to 'json' (srt not supported)
);

// Translation with automatic fallback
$options = AudioOptions::translate(
    model: AudioTranscribeModel::GPT_4O_TRANSCRIBE, // Doesn't support translation
    responseFormat: 'text'
);
// Result: Uses whisper-1 automatically
```

### Model Capability Matrix

| Feature | whisper-1 | gpt-4o-mini-transcribe | gpt-4o-transcribe |
|---------|-----------|------------------------|-------------------|
| Response Formats | json, text, srt, verbose_json, vtt | json, text | json, text |
| Timestamps | ✅ (requires verbose_json) | ❌ | ❌ |
| Translation | ✅ | ❌ | ❌ |
| Temperature | ✅ | ✅ | ✅ |
| Language Detection | ✅ | ✅ | ✅ |

## Rate Limiting and Performance

### Built-in Rate Limiting

The SDK includes intelligent rate limiting with token estimation:

```php
// Rate limiting is handled automatically by each client
// No manual configuration required for basic usage

// Using ask() method (recommended)
$result = $platform->ask('Analyze this document');
```

### Batch Processing

```php
$prompts = [
    'Analyze document 1',
    'Analyze document 2', 
    'Analyze document 3',
];

$results = [];
foreach ($prompts as $prompt) {
    // Rate limiting automatically applied with ask() method
    $results[] = $platform->ask($prompt);
}
```

## Error Handling Patterns

### Comprehensive Error Handling

```php
use Lingoda\AiSdk\Exception\ClientException;
use Lingoda\AiSdk\Exception\InvalidArgumentException;
use Lingoda\AiSdk\Exception\ModelNotFoundException;
use Lingoda\AiSdk\Exception\RateLimitExceededException;
use Lingoda\AiSdk\Exception\RuntimeException;
use Lingoda\AiSdk\Exception\UnsupportedCapabilityException;

try {
    $result = $platform->ask('Analyze this content');
    echo $result->getContent();
} catch (RateLimitExceededException $e) {
    echo "Rate limit exceeded. Retry after: " . $e->getRetryAfter() . " seconds";
} catch (ModelNotFoundException $e) {
    echo "Model not found: " . $e->getMessage();
} catch (UnsupportedCapabilityException $e) {
    echo "Model lacks required capability: " . $e->getMessage();
} catch (ClientException $e) {
    echo "API communication error: " . $e->getMessage();
} catch (InvalidArgumentException $e) {
    echo "Invalid input: " . $e->getMessage();
} catch (RuntimeException $e) {
    echo "Runtime error: " . $e->getMessage();
} catch (\Exception $e) {
    echo "Unexpected error: " . $e->getMessage();
}
```

## Custom Result Processing

### Working with Different Result Types

```php
use Lingoda\AiSdk\Result\TextResult;
use Lingoda\AiSdk\Result\ToolCallResult;
use Lingoda\AiSdk\Result\ObjectResult;

$result = $platform->ask('Generate a response with tool calls');

match (true) {
    $result instanceof TextResult => processText($result->getContent()),
    $result instanceof ToolCallResult => processToolCall($result->getToolCalls()),
    $result instanceof ObjectResult => processObject($result->getObject()),
    default => throw new \UnexpectedValueException('Unknown result type')
};
```

## Model Selection and Capabilities

### Manual Model Selection

```php
use Lingoda\AiSdk\Enum\Capability;

// Check model capabilities before using
$provider = $openAIClient->getProvider();
$model = $provider->getModel('gpt-4o-2024-11-20');

if ($model->hasCapability(Capability::VISION)) {
    $result = $platform->ask('Describe this image', $model->getId());
}

// For tool calls, choose a model with TOOLS capability
if ($model->hasCapability(Capability::TOOLS)) {
    $result = $platform->ask('Prompt that calls tools', $model->getId());
}
```

### Capability Validation

Use the `CapabilityValidator` for strict capability checking:

```php
use Lingoda\AiSdk\Enum\Capability;
use Lingoda\AiSdk\Exception\UnsupportedCapabilityException;
use Lingoda\AiSdk\Model\CapabilityValidator;

try {
    $model = $provider->getModel('gpt-4o-mini-2024-07-18');
    
    // Require specific capability - throws exception if missing
    CapabilityValidator::requireCapability($model, Capability::VISION);
    
    // Or require multiple capabilities
    CapabilityValidator::requireCapabilities($model, [
        Capability::VISION,
        Capability::TOOLS
    ]);
    
    $result = $platform->ask('Analyze this image and use tools', $model->getId());
} catch (UnsupportedCapabilityException $e) {
    echo "Capability validation failed: " . $e->getMessage();
    // Fall back to a different model or approach
}
```

### Provider-Specific Features

```php
// Leverage provider-specific capabilities
$openAIProvider = $openAIClient->getProvider();
$anthropicProvider = $anthropicClient->getProvider();
$geminiProvider = $geminiClient->getProvider();

$openAIModel = $openAIProvider->getModel(ChatModel::GPT_4O_2024_11_20->value);
$claudeModel = $anthropicProvider->getModel(ChatModel::CLAUDE_3_5_SONNET_20241022->value);
$geminiModel = $geminiProvider->getModel(ChatModel::GEMINI_2_5_FLASH->value);

// Each model has unique strengths - specify model with ask()
$creativeTasks = $platform->ask($creativePrompt, 'claude-3-5-sonnet-20241022');
$analyticTasks = $platform->ask($analyticsPrompt, 'gpt-4o-2024-11-20');
$multilingualTasks = $platform->ask($multilingualPrompt, 'gemini-2.5-flash');
```

## Next Steps

- [Audio Guide](audio.md) - Comprehensive audio processing documentation
- [Security](security.md) - Data protection and attribute-based sanitization
- [API Reference](api-reference.md) - Complete model and capability reference
- [Examples](examples.md) - Interactive examples with real API calls