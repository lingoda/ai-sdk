# Security Features

The Lingoda AI SDK provides comprehensive security features to protect sensitive data in AI interactions.

## Built-in Data Sanitization

### Automatic Protection

Data sanitization is **enabled by default** and automatically protects sensitive information:

```php
use Lingoda\AiSdk\Platform;
use Lingoda\AiSdk\Prompt\UserPrompt;

// Sanitization enabled by default
$platform = new Platform($clients);

// Sensitive data is automatically redacted
$prompt = UserPrompt::create('My email is john@example.com and my phone is 555-123-4567');
$result = $platform->ask($prompt, $model->getId());
// Actual prompt sent to AI: "My email is [REDACTED_EMAIL] and my phone is [REDACTED_PHONE]"
```

### Protected Data Types

The sanitization system automatically detects and redacts:

- **Personal Information**: Email addresses, phone numbers, SSNs
- **Financial Data**: Credit card numbers, IBANs, bank accounts
- **Authentication**: API keys (Stripe, AWS), JWT tokens, passwords
- **Network**: IP addresses, MAC addresses
- **Crypto**: Cryptocurrency addresses, private keys
- **Geographic**: ZIP codes, postal codes
- **And 20+ more sensitive data patterns**

## Conversation-Level Protection

### Selective Sanitization

Only user-provided content is sanitized - system prompts remain unchanged:

```php
use Lingoda\AiSdk\Prompt\Conversation;
use Lingoda\AiSdk\Prompt\UserPrompt;
use Lingoda\AiSdk\Prompt\SystemPrompt;

$conversation = Conversation::withSystem(
    UserPrompt::create('User message with john@example.com gets sanitized'),
    SystemPrompt::create('System prompt with email@example.com stays unchanged')
);

$result = $platform->ask($conversation, $model->getId());
// System prompt: unchanged (trusted content)
// User prompt sent: "User message with [REDACTED_EMAIL] gets sanitized"
```

## Attribute-Based Security

### Field-Level Protection

Use PHP attributes to define custom security policies for object properties:

```php
use Lingoda\AiSdk\Security\Attribute\Redact;
use Lingoda\AiSdk\Security\Attribute\Sensitive;

class UserData
{
    #[Redact('/\b[\w\.-]+@[\w\.-]+\.\w+\b/', '[EMAIL_BLOCKED]')]
    public string $contactInfo;
    
    #[Sensitive(type: 'pii', redactionText: '[PII_DETECTED]')]
    public string $personalDetails;
    
    #[Redact('/\b\d{3}-\d{3}-\d{4}\b/', '[PHONE_REDACTED]')]
    #[Sensitive(redactionText: '[SENSITIVE_PHONE]')]
    public string $phoneNumber;
    
    public string $safeData; // No attributes = no sanitization
}
```

### Attribute Types

#### `#[Redact]` Attribute

Apply custom regex patterns with specific replacements:

```php
use Lingoda\AiSdk\Security\Attribute\Redact;

class Document
{
    #[Redact('/\b\d{4}-\d{4}-\d{4}-\d{4}\b/', '[CARD_NUMBER]')]
    public string $paymentInfo;
    
    #[Redact('/\bsk-[a-zA-Z0-9]{48}\b/', '[API_KEY]')]
    public string $configuration;
    
    #[Redact('/\$\d+(?:\.\d{2})?/', '[AMOUNT]')]
    public string $financialData;
}
```

#### `#[Sensitive]` Attribute

Use the built-in sensitive content filter with custom redaction text:

```php
use Lingoda\AiSdk\Security\Attribute\Sensitive;

class UserProfile
{
    #[Sensitive(type: 'pii', redactionText: '[PERSONAL_INFO]')]
    public string $biography;
    
    #[Sensitive(redactionText: '[CONFIDENTIAL]')]
    public string $notes;
    
    #[Sensitive(type: 'financial', redactionText: '[FINANCIAL_DATA]')]
    public array $transactions;
}
```

### Array and Nested Object Support

Attributes work with complex data structures:

```php
class Company
{
    #[Redact('/\b[\w\.-]+@[\w\.-]+\.\w+\b/', '[EMAIL]')]
    public array $employeeContacts; // Sanitizes email addresses in array values
    
    #[Sensitive(redactionText: '[SENSITIVE_ARRAY]')]
    public array $confidentialData; // Applies to each array element
    
    public UserProfile $profile; // Nested objects are automatically processed
    
    #[Redact('/\b[\w\.-]+@[\w\.-]+\.\w+\b/', '[EMAIL]')]
    public array $emailDirectory; // Sanitizes both keys and values if they're emails
}
```

## Custom Security Configuration

### Advanced Sanitization Setup

```php
use Lingoda\AiSdk\Security\DataSanitizer;
use Lingoda\AiSdk\Security\Pattern\PatternRegistry;
use Lingoda\AiSdk\Security\AttributeSanitizer;
use Lingoda\AiSdk\Security\SensitiveContentFilter;

// Create custom pattern registry
$patternRegistry = PatternRegistry::createDefault();

// Create sensitive content filter
$sensitiveFilter = new SensitiveContentFilter($patternRegistry, $logger);

// Create attribute sanitizer
$attributeSanitizer = AttributeSanitizer::createDefault($sensitiveFilter, $logger);

// Create main data sanitizer
$dataSanitizer = new DataSanitizer(
    filter: $sensitiveFilter,
    attributeSanitizer: $attributeSanitizer,
    logger: $logger
);

// Use with Platform
$platform = new Platform(
    clients: $clients,
    enableSanitization: true,
    sanitizer: $dataSanitizer,
    logger: $logger
);
```

### Logging and Monitoring

```php
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

$logger = new Logger('ai-security');
$logger->pushHandler(new StreamHandler('var/log/security.log', Logger::WARNING));

$platform = new Platform(
    clients: $clients,
    enableSanitization: true,
    logger: $logger // Logs when sensitive data is detected and sanitized
);
```

### Disable Sanitization (Not Recommended)

```php
// Only disable for trusted internal systems
$platform = new Platform($clients, enableSanitization: false);
```

## Security Best Practices

### 1. Always Use Sanitization

```php
// ✅ Good - Sanitization enabled (default)
$platform = new Platform($clients);

// ❌ Bad - Sanitization disabled
$platform = new Platform($clients, enableSanitization: false);
```

### 2. Log Security Events

```php
// ✅ Good - Monitor sanitization activity
$platform = new Platform($clients, true, null, $logger);

// ❌ Bad - No visibility into security events
$platform = new Platform($clients);
```

### 3. Use Appropriate Attributes

```php
class CustomerData
{
    // ✅ Good - Specific pattern for known data type
    #[Redact('/\b\d{3}-\d{2}-\d{4}\b/', '[SSN]')]
    public string $ssn;
    
    // ✅ Good - Fallback for unknown sensitive content
    #[Sensitive(redactionText: '[CUSTOMER_DATA]')]
    public string $notes;
    
    // ✅ Good - No attribute for safe data
    public string $customerName;
}
```

### 4. Test Your Security

```php
// Create test cases to verify sanitization
public function testSensitiveDataIsProtected(): void
{
    $userData = new UserData();
    $userData->contactInfo = 'Email me at john@example.com';
    
    $result = $this->platform->ask('Process: ' . serialize($userData), $this->model->getId());
    
    // Verify sensitive data was redacted
    $this->assertStringNotContains('john@example.com', $result->getContent());
}
```

## Security Architecture

### Layered Protection

1. **Input Sanitization**: User prompts are sanitized before API calls
2. **Attribute Processing**: Objects with security attributes are processed
3. **Pattern Matching**: Built-in patterns detect common sensitive data
4. **Audit Logging**: Security events are logged for monitoring
5. **Type Safety**: Strong typing prevents data leakage

### Performance Considerations

- Sanitization adds minimal overhead (~1-2ms per request)
- Regex patterns are optimized for performance
- Object processing uses reflection caching
- Only user prompts are processed (system prompts skipped)

### Compliance Features

- **GDPR**: Automatic PII detection and redaction
- **PCI DSS**: Credit card number protection
- **SOX**: Financial data sanitization
- **HIPAA**: Healthcare information protection
- **Custom**: Define organization-specific patterns

## Next Steps

- [API Reference](api-reference.md) - Complete security attribute reference
- [Examples](examples.md) - Security examples with real data
- [Advanced Usage](advanced-usage.md) - Complex security patterns