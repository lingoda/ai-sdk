# HTTP Client Configuration

The SDK requires a PSR-18 compatible HTTP client for making API requests to AI providers.

## Quick Setup

For most use cases, Symfony HTTP Client is the recommended choice:

```bash
composer require symfony/http-client
```

```php
use Symfony\Component\HttpClient\Psr18Client;

$httpClient = new Psr18Client();
```

## Available HTTP Clients

### Symfony HTTP Client (Recommended)

**Pros**: Lightweight, fast, built-in retry logic, excellent Symfony integration
**Cons**: None for typical use cases

```bash
composer require symfony/http-client
```

```php
use Symfony\Component\HttpClient\Psr18Client;
use Symfony\Component\HttpClient\HttpClient;

// Basic setup
$httpClient = new Psr18Client();

// With custom options
$httpClient = new Psr18Client(
    HttpClient::create([
        'timeout' => 30,
        'max_duration' => 60,
        'headers' => [
            'User-Agent' => 'My-App/1.0'
        ]
    ])
);
```

### Guzzle HTTP

**Pros**: Feature-rich, extensive middleware ecosystem, widely adopted
**Cons**: Heavier footprint, more complex for simple use cases

```bash
composer require guzzlehttp/guzzle
```

```php
use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;

// Basic setup
$httpClient = new Client([
    'timeout' => 30,
    'connect_timeout' => 10,
]);

// With middleware
$stack = HandlerStack::create();
$stack->push(Middleware::retry(
    function ($retries, $request, $response, $exception) {
        return $retries < 3 && ($exception || $response->getStatusCode() >= 500);
    }
));

$httpClient = new Client([
    'handler' => $stack,
    'timeout' => 60,
]);
```

### cURL (php-http/curl-client)

**Pros**: Minimal dependencies, direct cURL control
**Cons**: More manual configuration required

```bash
composer require php-http/curl-client
```

```php
use Http\Client\Curl\Client;

$httpClient = new Client();
```

## Advanced Configuration

### Timeouts

Configure appropriate timeouts for AI API calls:

```php
// Symfony HTTP Client
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpClient\Psr18Client;

$httpClient = new Psr18Client(
    HttpClient::create([
        'timeout' => 30,        // Request timeout
        'max_duration' => 120,  // Total duration limit
    ])
);

// Guzzle
use GuzzleHttp\Client;

$httpClient = new Client([
    'timeout' => 30,         // Request timeout
    'connect_timeout' => 10, // Connection timeout
    'read_timeout' => 120,   // Read timeout
]);
```

### Retry Logic

Handle transient failures with automatic retries:

```php
// Symfony HTTP Client with retry
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpClient\RetryableHttpClient;
use Symfony\Component\HttpClient\Retry\GenericRetryStrategy;

$client = HttpClient::create();
$retryableClient = new RetryableHttpClient(
    $client,
    new GenericRetryStrategy([500, 502, 503, 504], 1000) // Retry on 5xx, 1s delay
);
$httpClient = new Psr18Client($retryableClient);

// Guzzle with middleware
use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;

$stack = HandlerStack::create();
$stack->push(Middleware::retry(
    function ($retries, $request, $response, $exception) {
        if ($retries >= 3) return false;
        if ($exception) return true;
        return $response && $response->getStatusCode() >= 500;
    },
    function ($retries) {
        return $retries * 1000; // Exponential backoff
    }
));

$httpClient = new Client(['handler' => $stack]);
```

### Proxy Support

Configure proxy for corporate environments:

```php
// Symfony HTTP Client
$httpClient = new Psr18Client(
    HttpClient::create([
        'proxy' => 'http://proxy.example.com:8080',
        'no_proxy' => 'localhost,127.0.0.1', // Skip proxy for these
    ])
);

// Guzzle
$httpClient = new Client([
    'proxy' => [
        'http' => 'http://proxy.example.com:8080',
        'https' => 'http://proxy.example.com:8080',
        'no' => ['localhost', '127.0.0.1']
    ]
]);
```

### SSL/TLS Configuration

Configure SSL verification and certificates:

```php
// Symfony HTTP Client
$httpClient = new Psr18Client(
    HttpClient::create([
        'verify_peer' => true,
        'verify_host' => true,
        'cafile' => '/path/to/ca-bundle.crt', // Custom CA bundle
    ])
);

// Guzzle
$httpClient = new Client([
    'verify' => true, // Enable SSL verification
    'cert' => ['/path/to/client.pem', 'password'], // Client certificate
]);
```

### Custom Headers

Add custom headers for identification or API requirements:

```php
// Symfony HTTP Client
$httpClient = new Psr18Client(
    HttpClient::create([
        'headers' => [
            'User-Agent' => 'MyApp-AI-SDK/1.0',
            'X-Custom-Header' => 'custom-value',
        ]
    ])
);

// Guzzle
$httpClient = new Client([
    'headers' => [
        'User-Agent' => 'MyApp-AI-SDK/1.0',
        'X-Custom-Header' => 'custom-value',
    ]
]);
```

## Performance Optimization

### Connection Pooling

Reuse connections for better performance:

```php
// Symfony HTTP Client (automatic connection reuse)
$httpClient = new Psr18Client(
    HttpClient::create([
        'max_host_connections' => 10, // Max connections per host
    ])
);

// Guzzle (automatic with same client instance)
$httpClient = new Client([
    'curl' => [
        CURLOPT_MAXCONNECTS => 10, // Connection pool size
    ]
]);
```

### HTTP/2 Support

Enable HTTP/2 for better performance:

```php
// Symfony HTTP Client (automatic HTTP/2 when available)
$httpClient = new Psr18Client(
    HttpClient::create([
        'http_version' => '2.0',
    ])
);

// Guzzle
$httpClient = new Client([
    'version' => '2.0', // Force HTTP/2
    'curl' => [
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_2_0,
    ]
]);
```

## Testing and Development

### Mock Clients for Testing

```php
// Symfony HTTP Client with MockHttpClient
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

$mockClient = new MockHttpClient([
    new MockResponse('{"choices":[{"message":{"content":"Test response"}}]}'),
]);
$httpClient = new Psr18Client($mockClient);

// Guzzle with Mock Handler
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;

$mock = new MockHandler([
    new Response(200, [], '{"choices":[{"message":{"content":"Test response"}}]}'),
]);
$handlerStack = HandlerStack::create($mock);
$httpClient = new Client(['handler' => $handlerStack]);
```

### Debug Mode

Enable request/response logging for development:

```php
// Symfony HTTP Client with logging
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpClient\Psr18Client;

$httpClient = new Psr18Client(
    HttpClient::create([
        'bindto' => '0', // Log to stdout
    ])
);

// Guzzle with debug
$httpClient = new Client([
    'debug' => true, // Output to STDOUT
    // or 'debug' => fopen('debug.log', 'a') // Log to file
]);
```

## Integration with Platform

Once configured, use your HTTP client with the Platform:

```php
use Lingoda\AiSdk\Platform;
use Lingoda\AiSdk\Client\OpenAI\OpenAIClient;

// Your configured HTTP client
$httpClient = new Psr18Client(/* your configuration */);

// Create AI client
$openAIClient = new OpenAIClient($httpClient, 'your-api-key');

// Create Platform
$platform = new Platform([$openAIClient]);

// Platform handles all HTTP communication through your configured client
$result = $platform->ask($prompt, $model->getId());
```

## Troubleshooting

### Common Issues

**Connection Timeouts**: Increase timeout values for slower networks
**SSL Errors**: Check certificate configuration and proxy settings  
**Rate Limiting**: Implement retry logic with exponential backoff
**Memory Usage**: Consider streaming for large responses

### Debug Steps

1. Enable debug/logging mode
2. Check network connectivity
3. Verify SSL certificates
4. Test with curl command line
5. Check proxy configuration

## Next Steps

- [Configuration](configuration.md) - Set up API keys and providers
- [Logging](logging.md) - Debug and monitoring setup
- [Quick Start](quick-start.md) - Your first AI request