# Logging Configuration

The SDK uses PSR-3 logging for debugging, monitoring, and security auditing.

## Quick Setup

For basic logging, use Monolog (most common PSR-3 implementation):

```bash
composer require monolog/monolog
```

```php
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Lingoda\AiSdk\Platform;

$logger = new Logger('ai-sdk');
$logger->pushHandler(new StreamHandler('php://stdout', Logger::INFO));

$platform = new Platform(
    clients: $clients,
    logger: $logger
);
```

## Platform Logging

The Platform logs various events automatically when a logger is provided:

### What Gets Logged

- **Security Events**: Data sanitization activities (WARNING level)
- **API Errors**: Failed requests and authentication issues (ERROR level)  
- **Rate Limiting**: Rate limit hits and token estimation (INFO level)
- **Model Resolution**: Model resolution and provider selection decisions (DEBUG level)
- **Request Flow**: Request/response metadata (DEBUG level)

### Security Audit Logging

```php
use Lingoda\AiSdk\Platform;
use Lingoda\AiSdk\Security\DataSanitizer;

// Logger specifically for security events
$securityLogger = new Logger('ai-security');
$securityLogger->pushHandler(
    new StreamHandler('/var/log/ai-security.log', Logger::WARNING)
);

// Sanitizer with audit logging enabled
$sanitizer = DataSanitizer::createDefault(
    PatternRegistry::createDefault(),
    $securityLogger
);

$platform = new Platform(
    clients: $clients,
    sanitizer: $sanitizer,
    logger: $securityLogger
);

// Security events are automatically logged:
// [WARNING] Property sanitized using attributes {"property":"email","pattern":"email"}
// [WARNING] Sensitive data detected and sanitized {"type":"credit_card","count":2}
```

## Logger Configurations

### Development Logging

Output everything to console with detailed formatting:

```php
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Formatter\LineFormatter;

$logger = new Logger('ai-sdk-dev');

// Console handler with custom format
$consoleHandler = new StreamHandler('php://stdout', Logger::DEBUG);
$consoleHandler->setFormatter(new LineFormatter(
    "[%datetime%] %channel%.%level_name%: %message% %context%\n",
    'Y-m-d H:i:s',
    true, // Allow inline line breaks
    true  // Ignore empty context
));

$logger->pushHandler($consoleHandler);
```

### Production Logging

Structured logging with rotation and error alerting:

```php
use Monolog\Logger;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Formatter\JsonFormatter;
use Monolog\Processor\PsrLogMessageProcessor;

$logger = new Logger('ai-sdk-prod');

// Add PSR-3 message processing
$logger->pushProcessor(new PsrLogMessageProcessor());

// Rotating file handler for general logs
$fileHandler = new RotatingFileHandler(
    '/var/log/ai-sdk.log',
    30,    // Keep 30 days
    Logger::INFO
);
$fileHandler->setFormatter(new JsonFormatter());
$logger->pushHandler($fileHandler);

// Separate error log
$errorHandler = new RotatingFileHandler(
    '/var/log/ai-sdk-error.log',
    90,    // Keep errors longer
    Logger::ERROR
);
$errorHandler->setFormatter(new JsonFormatter());
$logger->pushHandler($errorHandler);
```

### Structured Logging with Context

Add consistent context to all log entries:

```php
use Monolog\Logger;
use Monolog\Processor\ProcessIdProcessor;
use Monolog\Processor\WebProcessor;

$logger = new Logger('ai-sdk');

// Add process ID to all logs
$logger->pushProcessor(new ProcessIdProcessor());

// Add web request info (if web context)
$logger->pushProcessor(new WebProcessor());

// Custom processor for application context
$logger->pushProcessor(function ($record) {
    $record['extra']['app_version'] = '1.0.0';
    $record['extra']['environment'] = $_ENV['APP_ENV'] ?? 'unknown';
    return $record;
});
```

## Advanced Logging Patterns

### Per-Provider Logging

Create separate loggers for different AI providers:

```php
use Lingoda\AiSdk\Client\OpenAI\OpenAIClient;
use Lingoda\AiSdk\Client\Anthropic\AnthropicClient;

// OpenAI-specific logger
$openaiLogger = new Logger('openai');
$openaiLogger->pushHandler(new StreamHandler('/var/log/openai.log'));

// Anthropic-specific logger  
$anthropicLogger = new Logger('anthropic');
$anthropicLogger->pushHandler(new StreamHandler('/var/log/anthropic.log'));

// Clients can have their own loggers
$openAIClient = new OpenAIClient($httpClient, $apiKey, logger: $openAILogger);
$anthropicClient = new AnthropicClient($httpClient, $apiKey, logger: $anthropicLogger);
```

### Filtering Sensitive Information

Ensure API keys and sensitive data don't leak into logs:

```php
use Monolog\Processor\ProcessorInterface;

class SensitiveDataProcessor implements ProcessorInterface
{
    public function __invoke(LogRecord $record): LogRecord
    {
        // Remove API keys from context
        if (isset($record->context['api_key'])) {
            $record->context['api_key'] = '[REDACTED]';
        }
        
        // Sanitize message content
        $record->message = preg_replace(
            '/sk-[a-zA-Z0-9]{48}/',
            '[API_KEY_REDACTED]',
            $record->message
        );
        
        return $record;
    }
}

$logger->pushProcessor(new SensitiveDataProcessor());
```

### Performance Logging

Track API performance and token usage:

```php
class PerformanceLogger
{
    public function __construct(private Logger $logger) {}
    
    public function logApiCall(
        string $provider,
        string $model,
        int $tokens,
        float $duration,
        bool $success
    ): void {
        $this->logger->info('AI API call completed', [
            'provider' => $provider,
            'model' => $model,
            'tokens' => $tokens,
            'duration_ms' => round($duration * 1000, 2),
            'success' => $success,
            'tokens_per_second' => $tokens / $duration,
        ]);
    }
}
```

## Integration Patterns

### Symfony Integration

```php
// services.yaml
services:
    monolog.logger.ai_sdk:
        class: Monolog\Logger
        arguments: ['ai-sdk']
        calls:
            - [pushHandler, ['@monolog.handler.ai_sdk_file']]
    
    monolog.handler.ai_sdk_file:
        class: Monolog\Handler\RotatingFileHandler
        arguments:
            - '%kernel.logs_dir%/ai-sdk.log'
            - 30
            - !php/const Monolog\Logger::INFO

    Lingoda\AiSdk\Platform:
        arguments:
            $logger: '@monolog.logger.ai_sdk'
```

### Laravel Integration

```php
// config/logging.php
'channels' => [
    'ai-sdk' => [
        'driver' => 'daily',
        'path' => storage_path('logs/ai-sdk.log'),
        'level' => env('AI_SDK_LOG_LEVEL', 'info'),
        'days' => 30,
    ],
],

// In service provider
$logger = Log::channel('ai-sdk');
$platform = new Platform($clients, logger: $logger);
```

### Custom Application Integration

```php
class AiSdkLogger
{
    private Logger $logger;
    
    public function __construct()
    {
        $this->logger = new Logger('ai-sdk');
        
        // Configure based on environment
        if ($_ENV['APP_ENV'] === 'development') {
            $this->logger->pushHandler(
                new StreamHandler('php://stdout', Logger::DEBUG)
            );
        } else {
            $this->logger->pushHandler(
                new RotatingFileHandler('/var/log/ai-sdk.log', 30, Logger::INFO)
            );
        }
    }
    
    public function getLogger(): Logger
    {
        return $this->logger;
    }
}

// Usage
$aiLogger = new AiSdkLogger();
$platform = new Platform($clients, logger: $aiLogger->getLogger());
```

## Monitoring and Alerting

### Error Alerting

Send alerts for critical errors:

```php
use Monolog\Handler\SlackWebhookHandler;
use Monolog\Handler\MailHandler;

// Slack alerts for errors
$slackHandler = new SlackWebhookHandler(
    'https://hooks.slack.com/services/...',
    '#alerts',
    'AI-SDK',
    true,
    null,
    Logger::ERROR
);
$logger->pushHandler($slackHandler);

// Email alerts for critical errors
$mailHandler = new MailHandler(
    'alerts@example.com',
    'AI SDK Critical Error',
    'admin@example.com',
    Logger::CRITICAL
);
$logger->pushHandler($mailHandler);
```

### Metrics Collection

Export logs to metrics systems:

```php
use Monolog\Handler\NewRelicHandler;
use Monolog\Handler\LogglyHandler;

// New Relic integration
$logger->pushHandler(new NewRelicHandler(Logger::INFO));

// Loggly integration  
$logger->pushHandler(new LogglyHandler('your-token', Logger::INFO));
```

## Log Analysis

### Common Log Patterns

**Security Events**:
```
[WARNING] ai-security.AUDIT: Sensitive data sanitized {"pattern":"email","count":3}
[WARNING] ai-security.AUDIT: Property sanitized using attributes {"property":"creditCard"}
```

**API Errors**:
```
[ERROR] ai-sdk.CLIENT: OpenAI API error {"status":429,"message":"Rate limit exceeded"}
[ERROR] ai-sdk.CLIENT: Authentication failed {"provider":"anthropic","status":401}
```

**Performance Tracking**:
```
[INFO] ai-sdk.PERF: API call completed {"provider":"openai","tokens":150,"duration_ms":250}
[INFO] ai-sdk.PERF: Rate limit applied {"delay_ms":1000,"tokens_remaining":50}
```

### Log Parsing

Use structured logs for easy parsing:

```bash
# Find all rate limit events
grep "Rate limit" /var/log/ai-sdk.log | jq '.context'

# API error analysis  
grep "ERROR" /var/log/ai-sdk.log | jq -r '.message'

# Security event summary
grep "sanitized" /var/log/ai-security.log | jq -r '.context.pattern' | sort | uniq -c
```

## Best Practices

### Do's
- ✅ Use structured logging (JSON) in production
- ✅ Set appropriate log levels per environment
- ✅ Rotate logs to prevent disk space issues
- ✅ Monitor security events closely
- ✅ Include relevant context in log messages

### Don'ts  
- ❌ Log API keys or sensitive user data
- ❌ Use DEBUG level in production
- ❌ Ignore log rotation and cleanup
- ❌ Log excessively verbose information
- ❌ Mix application logs with SDK logs

## Next Steps

- [Security](security.md) - Understanding security audit logs
- [HTTP Clients](http-clients.md) - Client-level logging configuration
- [Advanced Usage](advanced-usage.md) - Error handling and logging patterns