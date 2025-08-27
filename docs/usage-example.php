<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Lingoda\AiSdk\ModelInterface;
use Lingoda\AiSdk\Platform;
use Lingoda\AiSdk\Prompt\Conversation;
use Lingoda\AiSdk\Prompt\SystemPrompt;
use Lingoda\AiSdk\Prompt\UserPrompt;
use Lingoda\AiSdk\Provider\OpenAIProvider;
use Lingoda\AiSdk\Provider\AnthropicProvider;
use Lingoda\AiSdk\Provider\GeminiProvider;
use Lingoda\AiSdk\Client\OpenAI\OpenAIClientFactory;
use Lingoda\AiSdk\Client\Anthropic\AnthropicClientFactory;
use Lingoda\AiSdk\Client\Gemini\GeminiClientFactory;
use Lingoda\AiSdk\Enum\Capability;
use Lingoda\AiSdk\RateLimit\RateLimitedClient;
use Lingoda\AiSdk\RateLimit\TokenEstimatorRegistry;
use Lingoda\AiSdk\RateLimit\SymfonyRateLimiter;
use Lingoda\AiSdk\Client\OpenAI\OpenAIClient;
use Lingoda\AiSdk\Security\DataSanitizer;
use Lingoda\AiSdk\Security\Pattern\PatternRegistry;
use Lingoda\AiSdk\Security\SensitiveContentFilter;
use Lingoda\AiSdk\Security\Attribute\Redact;
use Lingoda\AiSdk\Security\Attribute\Sensitive;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpClient\Psr18Client;
use function Lingoda\AiSdk\Prompt\systemPrompt;
use function Lingoda\AiSdk\Prompt\userPrompt;

// Example 1: Basic Setup - Create providers and get models
$openAIProvider = new OpenAIProvider();
$anthropicProvider = new AnthropicProvider();
$geminiProvider = new GeminiProvider();

// Get specific models from providers
$gpt4o = $openAIProvider->getModel('gpt-4o');
$gpt5 = $openAIProvider->getModel('gpt-5');
$claude4 = $anthropicProvider->getModel('claude-sonnet-4-20250514');
$gemini25 = $geminiProvider->getModel('gemini-2.5-pro');

echo "=== Model Information ===\n";
echo "GPT-4o: " . $gpt4o->getDisplayName() . " (Max tokens: " . $gpt4o->getMaxTokens() . ")\n";
echo "GPT-5: " . $gpt5->getDisplayName() . " (Max tokens: " . $gpt5->getMaxTokens() . ")\n";
echo "Claude 4: " . $claude4->getDisplayName() . " (Max tokens: " . $claude4->getMaxTokens() . ")\n";
echo "Gemini 2.5: " . $gemini25->getDisplayName() . " (Max tokens: " . $gemini25->getMaxTokens() . ")\n\n";

// Example 2: Check model capabilities
echo "=== Model Capabilities ===\n";
echo "GPT-4o Vision: " . ($gpt4o->hasCapability(Capability::VISION) ? 'Yes' : 'No') . "\n";
echo "GPT-5 Reasoning: " . ($gpt5->hasCapability(Capability::REASONING) ? 'Yes' : 'No') . "\n";
echo "Claude 4 Tools: " . ($claude4->hasCapability(Capability::TOOLS) ? 'Yes' : 'No') . "\n";
echo "Gemini 2.5 Multimodal: " . ($gemini25->hasCapability(Capability::MULTIMODAL) ? 'Yes' : 'No') . "\n\n";

// Example 3: Working with model options
echo "=== Model Options ===\n";
$gpt4oOptions = $gpt4o->getOptions();
$claude4Options = $claude4->getOptions();
echo "GPT-4o options: " . json_encode($gpt4oOptions, JSON_THROW_ON_ERROR) . "\n";
echo "Claude 4 options: " . json_encode($claude4Options, JSON_THROW_ON_ERROR) . "\n\n";

// Helper function to check which API keys are available
function getAvailableApiKeys(): array
{
    return [
        'openai' => getenv('OPENAI_API_KEY') ?: getenv('OPENAI_KEY') ?: null,
        'anthropic' => getenv('ANTHROPIC_API_KEY') ?: getenv('ANTHROPIC_KEY') ?: null,
        'gemini' => getenv('GEMINI_API_KEY') ?: getenv('GEMINI_KEY') ?: null,
        'openai_org' => getenv('OPENAI_ORG') ?: getenv('OPENAI_ORGANIZATION') ?: null,
    ];
}

// Example 4: Complete Platform Setup with Client Factories
function createPlatform(): Platform
{
    $apiKeys = getAvailableApiKeys();
    $clients = [];
    
    // All providers and converters are now created internally by the clients
    
    // Create OpenAI client if API key is available
    if ($apiKeys['openai']) {
        $openAIFactory = new OpenAIClientFactory(
            apiKey: $apiKeys['openai'],
            organization: $apiKeys['openai_org'], // Optional: your OpenAI organization
            timeout: 30
        );
        $clients[] = $openAIFactory->create();
        echo "✓ OpenAI client configured\n";
    }
    
    // Create Anthropic client if API key is available
    if ($apiKeys['anthropic']) {
        $anthropicFactory = new AnthropicClientFactory(
            apiKey: $apiKeys['anthropic'],
            timeout: 30
        );
        $clients[] = $anthropicFactory->create();
        echo "✓ Anthropic client configured\n";
    }
    
    // Create Gemini client if API key is available
    if ($apiKeys['gemini']) {
        $geminiFactory = new GeminiClientFactory(
            apiKey: $apiKeys['gemini'],
            timeout: 30
        );
        $clients[] = $geminiFactory->create();
        echo "✓ Gemini client configured\n";
    }
    
    if (empty($clients)) {
        throw new RuntimeException('No API keys provided. Set OPENAI_API_KEY, ANTHROPIC_API_KEY, or GEMINI_API_KEY environment variables.');
    }
    
    return new Platform($clients);
}

// Helper function to get an available model
function getAvailableModel(): ?ModelInterface
{
    $apiKeys = getAvailableApiKeys();
    
    // Try OpenAI first (usually fastest)
    if ($apiKeys['openai']) {
        $provider = new OpenAIProvider();
        return $provider->getModel('gpt-4o-mini'); // Most cost-effective OpenAI model
    }
    
    // Try Anthropic
    if ($apiKeys['anthropic']) {
        $provider = new AnthropicProvider();
        return $provider->getModel('claude-3-5-haiku-20241022'); // Most cost-effective Anthropic model
    }
    
    // Try Gemini
    if ($apiKeys['gemini']) {
        $provider = new GeminiProvider();
        return $provider->getModel('gemini-2.5-flash'); // Most cost-effective Gemini model
    }
    
    return null;
}

// Example 5: Platform Usage Patterns
function demonstratePlatformUsage(): void
{
    echo "=== Platform Usage Examples ===\n";
    
    try {
        $platform = createPlatform();
        $model = getAvailableModel();
        
        if (!$model) {
            echo "No API keys provided for testing. Skipping platform usage examples.\n\n";
            return;
        }
        
        echo "Using model: " . $model->getDisplayName() . " from " . $model->getProvider()->getName() . "\n\n";
        
        // Simple string input
        try {
            $result = $platform->ask('Hello! Please respond with just "Hello back!" and nothing else.', $model->getId());
            echo "Simple response: " . trim($result->getContent()) . "\n";
        } catch (Exception $e) {
            echo "Simple request failed: " . $e->getMessage() . "\n";
        }
        
        // Structured conversation
        try {
            $conversation = Conversation::withSystem(
                userPrompt('What is PHP in one sentence?'),
                systemPrompt('You are a helpful assistant. Keep your responses very brief.')
            );
            $result = $platform->ask($conversation, $model->getId());
            echo "Structured response: " . substr($result->getContent(), 0, 100) . "...\n";
        } catch (Exception $e) {
            echo "Structured request failed: " . $e->getMessage() . "\n";
        }
        
        // With custom options
        try {
            $options = ['temperature' => 0.1, 'max_tokens' => 50]; // Low temperature for consistent results
            $result = $platform->ask('Name one programming language.', $model->getId(), $options);
            echo "Controlled response: " . trim($result->getContent()) . "\n";
        } catch (Exception $e) {
            echo "Controlled request failed: " . $e->getMessage() . "\n";
        }
        
    } catch (Exception $e) {
        echo "Platform creation failed: " . $e->getMessage() . "\n";
    }
    
    echo "\n";
}

// Example 6: Rate Limited Usage
function demonstrateRateLimiting(): void
{
    echo "=== Rate Limited Client Example ===\n";
    
    $apiKeys = getAvailableApiKeys();
    
    // Only demonstrate if OpenAI key is available (rate limiting works with any provider)
    if (!$apiKeys['openai']) {
        echo "OpenAI API key not provided. Skipping rate limiting example.\n\n";
        return;
    }
    
    try {
        // Create improved token estimator with better pattern recognition
        $estimatorRegistry = TokenEstimatorRegistry::createDefault();
        
        // Create rate limiter
        $logger = new NullLogger();
        $rateLimiter = new SymfonyRateLimiter($logger);
        
        // Create underlying client using factory
        $clientFactory = new OpenAIClientFactory($apiKeys['openai'], timeout: 30);
        $underlyingClient = $clientFactory->create();
        
        // Wrap in rate limited client
        $rateLimitedClient = new RateLimitedClient($underlyingClient, $rateLimiter, $estimatorRegistry);
        
        // Use with platform
        $platform = new Platform([$rateLimitedClient]);
        $model = $underlyingClient->getProvider()->getModel('gpt-4o-mini');
        
        $result = $platform->ask('Say "Rate limiting works!"', $model->getId());
        echo "Rate limited response: " . trim($result->getContent()) . "\n";
        
    } catch (Exception $e) {
        echo "Rate limited request failed: " . $e->getMessage() . "\n";
    }
    
    echo "\n";
}

// Example 7: Prompt Value Objects and Conversation Usage
function demonstratePromptValueObject(): void
{
    echo "=== Prompt Value Objects & Conversation Example ===\n";
    
    try {
        $platform = createPlatform();
        $model = getAvailableModel();
        
        if (!$model) {
            echo "No API keys provided. Skipping conversation examples.\n\n";
            return;
        }
        
        // Demonstrate Prompt value objects
        echo "--- Prompt Value Objects ---\n";
        $userPrompt = UserPrompt::create('What is PHP?');
        $systemPrompt = SystemPrompt::create('You are helpful');
        
        echo "User prompt: " . $userPrompt->getContent() . " (role: " . $userPrompt->getRole()->value . ")\n";
        echo "System prompt: " . $systemPrompt->getContent() . " (role: " . $systemPrompt->getRole()->value . ")\n\n";
        
        // Simple user prompt
        $conversation = Conversation::fromUser(UserPrompt::create('What is PHP in one sentence?'));
        $result = $platform->ask($conversation, $model->getId());
        echo "Simple conversation response: " . substr($result->getContent(), 0, 80) . "...\n";
        
        // With system prompt
        $conversation = Conversation::withSystem(
            UserPrompt::create('What is machine learning?'),
            SystemPrompt::create('You are a helpful assistant that gives brief answers.')
        );
        $result = $platform->ask($conversation, $model->getId());
        echo "System+User conversation response: " . substr($result->getContent(), 0, 80) . "...\n";
        
        // Full conversation context
        $conversation = Conversation::conversation(
            UserPrompt::create('What about its applications?'),
            SystemPrompt::create('You are an AI expert'),
            \Lingoda\AiSdk\Prompt\AssistantPrompt::create('I just explained machine learning basics...')
        );
        $result = $platform->ask($conversation, $model->getId());
        echo "Full conversation response: " . substr($result->getContent(), 0, 80) . "...\n";
        
        // Create from array (common API format)
        $conversation = Conversation::fromArray([
            'messages' => [
                ['role' => 'system', 'content' => 'Be concise'],
                ['role' => 'user', 'content' => 'Explain REST APIs in one line']
            ]
        ]);
        $result = $platform->ask($conversation, $model->getId());
        echo "Array-based conversation response: " . substr($result->getContent(), 0, 80) . "...\n";
        
        // Demonstrate sanitization with Conversation VO - only user prompts are sanitized
        $conversation = Conversation::withSystem(
            UserPrompt::create('User message with john@example.com gets sanitized'),
            SystemPrompt::create('System message with email@example.com stays unchanged')
        );
        
        echo "\nSanitization with Conversation VO:\n";
        echo "System (not sanitized): " . substr($conversation->getSystemContent(), 0, 50) . "...\n";
        echo "User (will be sanitized): " . substr($conversation->getUserContent(), 0, 50) . "...\n";
        
        // Demonstrate Prompt object access
        echo "\nPrompt objects access:\n";
        echo "User Prompt object: " . get_class($conversation->getUserPrompt()) . "\n";
        echo "System Prompt object: " . get_class($conversation->getSystemPrompt()) . "\n";
        
        $result = $platform->ask($conversation, $model->getId());
        echo "Response: " . substr($result->getContent(), 0, 80) . "...\n";
        
    } catch (Exception $e) {
        echo "Conversation example failed: " . $e->getMessage() . "\n";
    }
    
    echo "\n";
}

// Example 8: Audio Capabilities (OpenAI only)
function demonstrateAudioCapabilities(): void
{
    echo "=== Audio Capabilities Example ===\n";
    
    $apiKeys = getAvailableApiKeys();
    
    // Audio capabilities are only available with OpenAI
    if (!$apiKeys['openai']) {
        echo "OpenAI API key not provided. Skipping audio capabilities example.\n\n";
        return;
    }
    
    try {
        // Create OpenAI client with audio support using factory
        $clientFactory = new OpenAIClientFactory($apiKeys['openai'], timeout: 60); // Longer timeout for audio
        $client = $clientFactory->create();
        
        // Text to Speech
        $audioResult = $client->textToSpeech('Hello! This is a test of the AI SDK audio capabilities.');
        echo "✓ Generated audio with MIME type: " . $audioResult->getMimeType() . "\n";
        echo "✓ Audio data length: " . strlen($audioResult->getContent()) . " bytes\n";
        echo "✓ Audio generation successful!\n";
        
    } catch (Exception $e) {
        echo "Audio capabilities failed: " . $e->getMessage() . "\n";
    }
    
    echo "\n";
}

// Example 8: Model Selection Based on Capabilities
function selectBestModel(array $models, array $requiredCapabilities): ?ModelInterface
{
    foreach ($models as $model) {
        $hasAllCapabilities = true;
        foreach ($requiredCapabilities as $capability) {
            if (!$model->hasCapability($capability)) {
                $hasAllCapabilities = false;
                break;
            }
        }
        if ($hasAllCapabilities) {
            return $model;
        }
    }
    return null;
}

function demonstrateModelSelection(): void
{
    echo "=== Intelligent Model Selection ===\n";
    
    $openAIProvider = new OpenAIProvider();
    $anthropicProvider = new AnthropicProvider();
    $geminiProvider = new GeminiProvider();
    
    $models = [
        $openAIProvider->getModel('gpt-4o'),
        $openAIProvider->getModel('gpt-5'),
        $anthropicProvider->getModel('claude-sonnet-4-20250514'),
        $geminiProvider->getModel('gemini-2.5-pro'),
    ];
    
    // Find best model for vision tasks
    $visionModel = selectBestModel($models, [Capability::VISION]);
    echo "Best model for vision: " . ($visionModel?->getDisplayName() ?? 'None found') . "\n";
    
    // Find best model for reasoning tasks
    $reasoningModel = selectBestModel($models, [Capability::REASONING]);
    echo "Best model for reasoning: " . ($reasoningModel?->getDisplayName() ?? 'None found') . "\n";
    
    // Find best model for tool usage
    $toolModel = selectBestModel($models, [Capability::TOOLS]);
    echo "Best model for tools: " . ($toolModel?->getDisplayName() ?? 'None found') . "\n";
    
    echo "\n";
}

// Example 8: Token Estimation Features  
function demonstrateTokenEstimation(): void
{
    echo "=== Token Estimation Features ===\n";
    
    try {
        // Create improved token estimator
        $estimatorRegistry = TokenEstimatorRegistry::createDefault();
        
        // Get a model for estimation
        $provider = new OpenAIProvider();
        $model = $provider->getModel('gpt-4o');
        
        // Demonstrate token estimation for different content types
        $simpleText = "Hello world!";
        $jsonContent = '{"message": "Hello", "data": [1, 2, 3]}';
        $codeContent = "```php\necho 'Hello World';\n```";
        $urlContent = "Check out https://example.com for more info!";
        
        echo "Token Estimation Examples:\n";
        echo "- Simple text: {$estimatorRegistry->estimate($model, $simpleText)} tokens\n";
        echo "- JSON content: {$estimatorRegistry->estimate($model, $jsonContent)} tokens\n";
        echo "- Code block: {$estimatorRegistry->estimate($model, $codeContent)} tokens\n";
        echo "- URL content: {$estimatorRegistry->estimate($model, $urlContent)} tokens\n\n";
        
        // Demonstrate OpenAI message format
        $openaiPayload = [
            'messages' => [
                ['role' => 'system', 'content' => 'You are a helpful assistant.'],
                ['role' => 'user', 'content' => 'What is PHP?'],
            ]
        ];
        echo "OpenAI payload: {$estimatorRegistry->estimate($model, $openaiPayload)} tokens\n";
        
        // Demonstrate Anthropic format
        $anthropicPayload = [
            'content' => [
                ['type' => 'text', 'text' => 'Explain PHP in one sentence.']
            ]
        ];
        echo "Anthropic payload: {$estimatorRegistry->estimate($model, $anthropicPayload)} tokens\n";
        
        // Demonstrate Gemini format
        $geminiPayload = [
            'contents' => [
                ['parts' => [['text' => 'What is PHP?']], 'role' => 'user']
            ]
        ];
        echo "Gemini payload: {$estimatorRegistry->estimate($model, $geminiPayload)} tokens\n\n";
        
        echo "✓ Token estimator handles all provider formats and special patterns!\n";
        
    } catch (Exception $e) {
        echo "Token estimation demonstration failed: " . $e->getMessage() . "\n";
    }
    
    echo "\n";
}

// Example 9: Advanced Client Factory Configuration
function demonstrateAdvancedFactoryUsage(): void
{
    echo "=== Advanced Client Factory Configuration ===\n";
    
    // Custom HTTP client with proxy settings
    $httpClient = new Psr18Client(HttpClient::create([
        'proxy' => 'http://proxy.example.com:8080',
        'timeout' => 60,
        'headers' => [
            'User-Agent' => 'MyApp/1.0',
        ],
        'max_redirects' => 3,
    ]));
    
    // OpenAI factory with custom HTTP client and organization
    $openAIFactory = new OpenAIClientFactory(
        apiKey: 'your-openai-api-key',
        organization: 'org-1234567890abcdef', // Your OpenAI organization ID
        timeout: 60,
        httpClient: $httpClient
    );
    
    // Anthropic factory with custom HTTP client
    $anthropicFactory = new AnthropicClientFactory(
        apiKey: 'your-anthropic-api-key',
        timeout: 120, // Longer timeout for complex requests
        httpClient: $httpClient
    );
    
    // Gemini factory with custom HTTP client
    $geminiFactory = new GeminiClientFactory(
        apiKey: 'your-gemini-api-key',
        timeout: 90,
        httpClient: $httpClient
    );

    // All providers and converters are now created internally by the clients

    // Create clients with custom logger
    $logger = new NullLogger(); // In practice, use a real logger like Monolog

    $openAIClient = $openAIFactory->create($logger);
    $anthropicClient = $anthropicFactory->create($logger);
    $geminiClient = $geminiFactory->create($logger);
    
    echo "Created clients with custom HTTP configuration and logging\n";
    echo "All clients have custom timeouts and proxy configuration\n\n";
}

// Example 10: Error Handling Patterns
function demonstrateErrorHandling(): void
{
    echo "=== Error Handling Patterns ===\n";
    
    try {
        $platform = createPlatform();
        $provider = new OpenAIProvider();
        
        // Try to get a non-existent model
        $model = $provider->getModel('non-existent-model');
    } catch (\InvalidArgumentException $e) {
        echo "Model not found: " . $e->getMessage() . "\n";
    }
    
    try {
        // Try to invoke with invalid input
        $platform = createPlatform();
        $provider = new OpenAIProvider();
        $model = $provider->getModel('gpt-4o-mini');
        
        $result = $platform->ask([], $model->getId()); // Empty array should trigger validation
    } catch (\InvalidArgumentException $e) {
        echo "Invalid input: " . $e->getMessage() . "\n";
    } catch (Exception $e) {
        echo "Other error: " . $e->getMessage() . "\n";
    }
    
    echo "\n";
}

// Example 10: Data Sanitization
function demonstrateDataSanitization(): void
{
    echo "=== Data Sanitization ===\n";
    
    try {
        $httpClient = HttpClient::create();
        $psr18Client = new Psr18Client($httpClient);
        
        // Create regular client
        $apiKey = $_ENV['OPENAI_API_KEY'] ?? 'test-key';
        $client = new OpenAIClient(
            OpenAI::factory()
                ->withApiKey($apiKey)
                ->withHttpClient($psr18Client)
                ->make());
        
        // Example with sensitive data that will be sanitized
        $sensitiveInput = "Please email me at john.doe@example.com or call 555-123-4567. My API key is sk_live_abc123def456.";
        
        echo "Original input: $sensitiveInput\n";
        echo "Note: The SDK will automatically sanitize this before sending to the AI\n\n";
        
        // Direct sanitization demonstration
        $sanitizer = DataSanitizer::createDefault();
        $sanitizedText = $sanitizer->sanitize($sensitiveInput);
        echo "Sanitized version: $sanitizedText\n\n";
        
        // Complex data sanitization
        $complexData = [
            'user_email' => 'admin@company.com',
            'phone' => '(555) 987-6543',
            'credit_card' => '4532-1234-5678-9012',
            'api_credentials' => [
                'key' => 'sk_live_abcdef123456',
                'secret' => 'secret_xyz789'
            ],
            'safe_data' => 'This is completely safe text',
            'order_id' => '#12345'
        ];
        
        echo "Complex data before sanitization:\n";
        print_r($complexData);
        
        $sanitizedData = $sanitizer->sanitize($complexData);
        echo "\nComplex data after sanitization:\n";
        print_r($sanitizedData);
        
        // Pattern detection
        $filter = new SensitiveContentFilter(PatternRegistry::createDefault());
        
        $testContent = "Contact support@example.com or visit our API docs with token: Bearer eyJhbGci.eyJzdWI.SflKxwRJ";
        $filtered = $filter->filter($testContent);
        
        echo "\nOriginal content:\n";
        echo $testContent . "\n";
        echo "\nFiltered content:\n";
        echo $filtered . "\n";
        
    } catch (Exception $e) {
        echo "Error demonstrating sanitization: " . $e->getMessage() . "\n";
    }
    
    echo "\n";
}

// Example 11: Attribute-Based Data Sanitization
function demonstrateAttributeSanitization(): void
{
    echo "=== Attribute-Based Data Sanitization ===\n";
    
    try {
        // Create a sanitizer with attribute support (default includes attribute support)
        $sanitizer = DataSanitizer::createDefault();
        
        // Create test object with security attributes
        $userProfile = new ExampleUserProfile();
        $userProfile->email = 'john.doe@example.com';
        $userProfile->phone = '555-123-4567';
        $userProfile->sensitiveNotes = 'Contains PII: SSN 123-45-6789 and email support@company.com';
        $userProfile->creditCard = '4532-1234-5678-9012';
        $userProfile->publicName = 'John Doe';
        $userProfile->preferences = [
            'contact_email' => 'personal@example.com',
            'emergency_phone' => '555-987-6543',
            'safe_setting' => 'dark_mode'
        ];
        
        echo "User profile before sanitization:\n";
        echo "- Email: {$userProfile->email}\n";
        echo "- Phone: {$userProfile->phone}\n";
        echo "- Sensitive Notes: {$userProfile->sensitiveNotes}\n";
        echo "- Credit Card: {$userProfile->creditCard}\n";
        echo "- Public Name: {$userProfile->publicName}\n";
        echo "- Preferences: " . json_encode($userProfile->preferences) . "\n\n";
        
        // Sanitize using attributes
        $sanitizedProfile = $sanitizer->sanitize($userProfile);
        
        echo "User profile after attribute-based sanitization:\n";
        echo "- Email: {$sanitizedProfile->email}\n";
        echo "- Phone: {$sanitizedProfile->phone}\n";
        echo "- Sensitive Notes: {$sanitizedProfile->sensitiveNotes}\n";
        echo "- Credit Card: {$sanitizedProfile->creditCard}\n";
        echo "- Public Name: {$sanitizedProfile->publicName}\n";
        echo "- Preferences: " . json_encode($sanitizedProfile->preferences) . "\n\n";
        
        echo "✓ Attribute-based sanitization applied successfully!\n";
        echo "✓ Fields with #[Redact] attributes were pattern-matched and redacted\n";
        echo "✓ Fields with #[Sensitive] attributes were checked and replaced\n";
        echo "✓ Fields without attributes were processed using pattern-based fallback\n";
        echo "✓ Safe fields without sensitive content remained unchanged\n\n";
        
    } catch (Exception $e) {
        echo "Error demonstrating attribute sanitization: " . $e->getMessage() . "\n";
    }
    
    echo "\n";
}

/**
 * Example class demonstrating security attributes usage
 */
class ExampleUserProfile
{
    #[Redact('/\b[\w\.-]+@[\w\.-]+\.\w+\b/', '[EMAIL_REDACTED]')]
    public string $email = '';

    #[Redact('/\b\d{3}-\d{3}-\d{4}\b/', '[PHONE_REDACTED]')]
    public string $phone = '';

    #[Sensitive(type: 'pii', redactionText: '[SENSITIVE_DATA_REMOVED]')]
    public string $sensitiveNotes = '';

    #[Redact('/\b\d{4}[-\s]?\d{4}[-\s]?\d{4}[-\s]?\d{4}\b/', '[CARD_REDACTED]')]
    public string $creditCard = '';

    // No attributes - safe field
    public string $publicName = '';

    #[Redact('/\b[\w\.-]+@[\w\.-]+\.\w+\b/', '[EMAIL_REDACTED]')]
    #[Redact('/\b\d{3}-\d{3}-\d{4}\b/', '[PHONE_REDACTED]')]
    public array $preferences = [];
}

// Run all examples
echo "Lingoda AI SDK Usage Examples\n";
echo "=============================\n\n";

// Check available API keys
$apiKeys = getAvailableApiKeys();
$availableProviders = [];
if ($apiKeys['openai']) $availableProviders[] = 'OpenAI';
if ($apiKeys['anthropic']) $availableProviders[] = 'Anthropic'; 
if ($apiKeys['gemini']) $availableProviders[] = 'Gemini';

if (!empty($availableProviders)) {
    echo "🔑 API Keys detected for: " . implode(', ', $availableProviders) . "\n";
    echo "🚀 Running live API examples...\n\n";
} else {
    echo "ℹ️  No API keys detected. Running offline examples only.\n";
    echo "💡 To test with real APIs, set environment variables:\n";
    echo "   - OPENAI_API_KEY (or OPENAI_KEY)\n";
    echo "   - ANTHROPIC_API_KEY (or ANTHROPIC_KEY)  \n";
    echo "   - GEMINI_API_KEY (or GEMINI_KEY)\n";
    echo "   - OPENAI_ORG (optional OpenAI organization)\n\n";
    echo "Example: OPENAI_API_KEY=sk-... php docs/usage-example.php\n\n";
}

// These examples show model information without making API calls
demonstrateModelSelection();
demonstrateTokenEstimation();
demonstrateDataSanitization();
demonstrateAttributeSanitization();

// API-dependent examples (run automatically if keys are available)
if (!empty($availableProviders)) {
    demonstratePlatformUsage();
    demonstratePromptValueObject();
    
    if ($apiKeys['openai']) {
        demonstrateRateLimiting();
        demonstrateAudioCapabilities();
    }
    
    demonstrateAdvancedFactoryUsage();
    demonstrateErrorHandling();
} else {
    echo "📝 API-dependent examples skipped. Set API keys to run them.\n\n";
}

echo "✅ Examples completed!\n";