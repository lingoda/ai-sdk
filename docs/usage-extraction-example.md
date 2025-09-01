# Usage Data Extraction for Langfuse Integration

## Overview

The SDK now provides comprehensive usage data through a dedicated `Usage` DTO available on all result objects. Each provider's converter automatically extracts and normalizes token usage data, including detailed breakdowns when available.

## Accessing Usage Data

All result objects now implement the `getUsage(): ?Usage` method:

```php
use Lingoda\AiSdk\Result\ResultInterface;

$result = $client->chat()->completions()->create([
    'model' => 'gpt-4',
    'messages' => [['role' => 'user', 'content' => 'Hello world']],
]);

// Get comprehensive usage data
$usage = $result->getUsage();

if ($usage !== null) {
    // Basic token counts
    echo "Prompt tokens: " . $usage->promptTokens;
    echo "Completion tokens: " . $usage->completionTokens;
    echo "Total tokens: " . $usage->totalTokens;

    // Provider-specific details (when available)
    if ($usage->cachedTokens !== null) {
        echo "Cached tokens: " . $usage->cachedTokens;
    }

    if ($usage->reasoningTokens !== null) {
        echo "Reasoning tokens: " . $usage->reasoningTokens;
    }
}
```

## Usage DTO Structure

The `Usage` class provides comprehensive token information:

```php
$usage = $result->getUsage();

// Basic counts (always available)
$usage->promptTokens;      // int - Input/prompt tokens
$usage->completionTokens;  // int - Output/completion tokens
$usage->totalTokens;       // int - Total tokens used

// Optional detailed information (provider-specific)
$usage->promptDetails;     // ?TokenDetails - Detailed prompt token breakdown
$usage->completionDetails; // ?TokenDetails - Detailed completion token breakdown
$usage->cachedTokens;      // ?int - Cached tokens (Anthropic cache, OpenAI cache, Gemini cache)
$usage->toolUseTokens;     // ?int - Tool use tokens (Gemini)
$usage->reasoningTokens;   // ?int - Reasoning tokens (OpenAI o1 models)
$usage->thoughtsTokens;    // ?int - Thinking tokens (Gemini thinking models)
```

## Provider-Specific Details

### OpenAI
- Captures detailed token breakdowns including cached tokens, audio tokens, reasoning tokens
- Supports prediction tokens (accepted/rejected)
- Available through `$usage->promptDetails` and `$usage->completionDetails`

### Anthropic
- Tracks cache creation and cache read tokens
- Automatically calculates total tokens (not provided by API)
- Aggregates cache tokens in `$usage->cachedTokens`

### Gemini
- Supports tool use tokens and thinking model tokens
- Provides cached content token counts
- Rich modality breakdown support (planned)

## Advanced Usage

Get all usage data as an array:

```php
$usage = $result->getUsage();
$allData = $usage->toArray();

// Example output:
[
    'prompt_tokens' => 10,
    'completion_tokens' => 5,
    'total_tokens' => 15,
    'cached_tokens' => 3,
    'reasoning_tokens' => 2,
    'prompt_details' => [
        'cached_tokens' => 3,
        'audio_tokens' => 0,
    ],
    'completion_details' => [
        'reasoning_tokens' => 2,
        'accepted_prediction_tokens' => 0,
    ],
]
```
