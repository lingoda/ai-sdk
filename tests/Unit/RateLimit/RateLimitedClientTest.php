<?php

declare(strict_types=1);

namespace Lingoda\AiSdk\Tests\Unit\RateLimit;

use Lingoda\AiSdk\ClientInterface;
use Lingoda\AiSdk\Exception\RateLimitExceededException;
use Lingoda\AiSdk\Exception\RuntimeException;
use Lingoda\AiSdk\Provider\OpenAIProvider;
use Lingoda\AiSdk\RateLimit\RateLimitedClient;
use Lingoda\AiSdk\RateLimit\RateLimiterInterface;
use Lingoda\AiSdk\RateLimit\TokenEstimatorRegistry;
use Lingoda\AiSdk\Result\TextResult;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use ReflectionClass;

final class RateLimitedClientTest extends TestCase
{
    public function testClientPassesThroughWhenRateLimitAllowed(): void
    {
        $provider = new OpenAIProvider();
        $model = $provider->getModel('gpt-4o-mini');

        // Mock the underlying client
        $underlyingClient = $this->createMock(ClientInterface::class);
        $underlyingClient->method('supports')->with($model)->willReturn(true);
        $underlyingClient->method('getProvider')->willReturn($provider);
        $underlyingClient->method('request')
            ->willReturn(new TextResult('Success', []));

        // Mock rate limiter that allows the request
        $rateLimiter = $this->createMock(RateLimiterInterface::class);
        $rateLimiter->expects($this->once())
            ->method('consume')
            ->with($model, $this->greaterThan(0));

        $estimatorRegistry = TokenEstimatorRegistry::createDefault();

        $testDelay = new TestDelay();

        $rateLimitedClient = new RateLimitedClient(
            $underlyingClient,
            $rateLimiter,
            $estimatorRegistry,
            delay: $testDelay
        );

        $result = $rateLimitedClient->request($model, 'Hello world');

        $this->assertInstanceOf(TextResult::class, $result);
        $this->assertEquals('Success', $result->getContent());
    }

    public function testClientRetriesOnRateLimitExceeded(): void
    {
        $provider = new OpenAIProvider();
        $model = $provider->getModel('gpt-4o-mini');

        // Mock the underlying client
        $underlyingClient = $this->createMock(ClientInterface::class);
        $underlyingClient->method('supports')->with($model)->willReturn(true);
        $underlyingClient->method('getProvider')->willReturn($provider);
        $underlyingClient->method('request')
            ->willReturn(new TextResult('Success after retry', []));

        // Mock rate limiter that fails first time, succeeds second time
        $rateLimiter = $this->createMock(RateLimiterInterface::class);
        $rateLimiter->expects($this->exactly(2))
            ->method('consume')
            ->with($model, $this->greaterThan(0))
            ->willReturnOnConsecutiveCalls(
                $this->throwException(new RateLimitExceededException(1, 'Rate limit exceeded')),
                null // Success on second call
            );

        $estimatorRegistry = TokenEstimatorRegistry::createDefault();

        $testDelay = new TestDelay();

        $rateLimitedClient = new RateLimitedClient(
            $underlyingClient,
            $rateLimiter,
            $estimatorRegistry,
            delay: $testDelay
        );

        $result = $rateLimitedClient->request($model, 'Hello world');

        $this->assertInstanceOf(TextResult::class, $result);
        $this->assertEquals('Success after retry', $result->getContent());
    }

    public function testClientSupportsCheckPassesThrough(): void
    {
        $provider = new OpenAIProvider();
        $model = $provider->getModel('gpt-4o-mini');

        $underlyingClient = $this->createMock(ClientInterface::class);
        $underlyingClient->method('supports')->with($model)->willReturn(true);
        $underlyingClient->method('getProvider')->willReturn($provider);

        $rateLimiter = $this->createMock(RateLimiterInterface::class);
        $estimatorRegistry = TokenEstimatorRegistry::createDefault();

        $testDelay = new TestDelay();

        $rateLimitedClient = new RateLimitedClient(
            $underlyingClient,
            $rateLimiter,
            $estimatorRegistry,
            delay: $testDelay
        );

        $this->assertTrue($rateLimitedClient->supports($model));
    }

    public function testClientGetProviderPassesThrough(): void
    {
        $provider = new OpenAIProvider();

        $underlyingClient = $this->createMock(ClientInterface::class);
        $underlyingClient->method('getProvider')->willReturn($provider);

        $rateLimiter = $this->createMock(RateLimiterInterface::class);
        $estimatorRegistry = TokenEstimatorRegistry::createDefault();

        $testDelay = new TestDelay();

        $rateLimitedClient = new RateLimitedClient(
            $underlyingClient,
            $rateLimiter,
            $estimatorRegistry,
            delay: $testDelay
        );

        $this->assertSame($provider, $rateLimitedClient->getProvider());
    }

    public function testClientThrowsAfterMaxRetries(): void
    {
        $provider = new OpenAIProvider();
        $model = $provider->getModel('gpt-4o-mini');

        $underlyingClient = $this->createMock(ClientInterface::class);
        $underlyingClient->method('supports')->with($model)->willReturn(true);
        $underlyingClient->method('getProvider')->willReturn($provider);

        // Mock rate limiter that always throws
        $rateLimiter = $this->createMock(RateLimiterInterface::class);
        $rateLimiter->expects($this->exactly(10)) // MAX_RETRIES
            ->method('consume')
            ->with($model, $this->greaterThan(0))
            ->willThrowException(new RateLimitExceededException(1, 'Rate limit exceeded'));

        $estimatorRegistry = TokenEstimatorRegistry::createDefault();

        $testDelay = new TestDelay();

        $rateLimitedClient = new RateLimitedClient(
            $underlyingClient,
            $rateLimiter,
            $estimatorRegistry,
            delay: $testDelay
        );

        $this->expectException(RateLimitExceededException::class);
        $this->expectExceptionMessage('Rate limit exceeded');

        $rateLimitedClient->request($model, 'Hello world');
    }

    public function testClientHandlesNonRetryableErrors(): void
    {
        $provider = new OpenAIProvider();
        $model = $provider->getModel('gpt-4o-mini');

        $underlyingClient = $this->createMock(ClientInterface::class);
        $underlyingClient->method('supports')->with($model)->willReturn(true);
        $underlyingClient->method('getProvider')->willReturn($provider);
        $underlyingClient->method('request')
            ->willThrowException(new \InvalidArgumentException('Invalid input'));

        $rateLimiter = $this->createMock(RateLimiterInterface::class);
        $rateLimiter->expects($this->once())
            ->method('consume')
            ->with($model, $this->greaterThan(0));

        $estimatorRegistry = TokenEstimatorRegistry::createDefault();

        $testDelay = new TestDelay();

        $rateLimitedClient = new RateLimitedClient(
            $underlyingClient,
            $rateLimiter,
            $estimatorRegistry,
            delay: $testDelay
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid input');

        $rateLimitedClient->request($model, 'Hello world');
    }

    public function testClientRetriesOnRetryableErrors(): void
    {
        $provider = new OpenAIProvider();
        $model = $provider->getModel('gpt-4o-mini');

        $underlyingClient = $this->createMock(ClientInterface::class);
        $underlyingClient->method('supports')->with($model)->willReturn(true);
        $underlyingClient->method('getProvider')->willReturn($provider);
        $underlyingClient->expects($this->exactly(2))
            ->method('request')
            ->willReturnOnConsecutiveCalls(
                $this->throwException(new \RuntimeException('API returned status 503')), // Retryable 5xx
                new TextResult('Success after retry', [])
            );

        $rateLimiter = $this->createMock(RateLimiterInterface::class);
        $rateLimiter->expects($this->exactly(2))
            ->method('consume')
            ->with($model, $this->greaterThan(0));

        $estimatorRegistry = TokenEstimatorRegistry::createDefault();
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('error');
        $logger->expects($this->once())->method('warning');

        $testDelay = new TestDelay();

        $rateLimitedClient = new RateLimitedClient(
            $underlyingClient,
            $rateLimiter,
            $estimatorRegistry,
            $logger,
            $testDelay
        );

        $result = $rateLimitedClient->request($model, 'Hello world');

        $this->assertInstanceOf(TextResult::class, $result);
        $this->assertEquals('Success after retry', $result->getContent());
        $this->assertEquals(1, $testDelay->getDelayCallCount()); // Should have delayed once
    }

    public function testClientThrowsRuntimeExceptionAfterMaxRetries(): void
    {
        $provider = new OpenAIProvider();
        $model = $provider->getModel('gpt-4o-mini');

        $underlyingClient = $this->createMock(ClientInterface::class);
        $underlyingClient->method('supports')->with($model)->willReturn(true);
        $underlyingClient->method('getProvider')->willReturn($provider);
        $underlyingClient->method('request')
            ->willThrowException(new \RuntimeException('API returned status 503')); // Always retryable

        $rateLimiter = $this->createMock(RateLimiterInterface::class);
        $rateLimiter->method('consume'); // Allow all calls

        $estimatorRegistry = TokenEstimatorRegistry::createDefault();
        $logger = $this->createMock(LoggerInterface::class);

        $testDelay = new TestDelay();

        $rateLimitedClient = new RateLimitedClient(
            $underlyingClient,
            $rateLimiter,
            $estimatorRegistry,
            $logger,
            $testDelay
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to execute request after maximum retries');

        $rateLimitedClient->request($model, 'Hello world');
    }

    public function testIsRetryableErrorWithVariousPatterns(): void
    {
        $provider = new OpenAIProvider();
        $underlyingClient = $this->createMock(ClientInterface::class);
        $rateLimiter = $this->createMock(RateLimiterInterface::class);
        $estimatorRegistry = TokenEstimatorRegistry::createDefault();

        $testDelay = new TestDelay();

        $rateLimitedClient = new RateLimitedClient(
            $underlyingClient,
            $rateLimiter,
            $estimatorRegistry,
            delay: $testDelay
        );

        $reflection = new ReflectionClass($rateLimitedClient);
        $method = $reflection->getMethod('isRetryableError');
        $method->setAccessible(true);

        // Test retryable patterns
        $retryableErrors = [
            new \RuntimeException('Connection timeout occurred'),
            new \RuntimeException('Network error detected'),
            new \RuntimeException('Service unavailable temporarily'),
            new \RuntimeException('Too many requests sent'),
            new \RuntimeException('Rate limit exceeded'),
            new \RuntimeException('API returned status 503'),
            new \RuntimeException('API returned status 500'),
        ];

        foreach ($retryableErrors as $error) {
            $this->assertTrue(
                $method->invokeArgs($rateLimitedClient, [$error]),
                "Expected '{$error->getMessage()}' to be retryable"
            );
        }

        // Test non-retryable errors
        $nonRetryableErrors = [
            new \InvalidArgumentException('Invalid input provided'),
            new \RuntimeException('Authentication failed'),
            new \RuntimeException('API returned status 400'),
        ];

        foreach ($nonRetryableErrors as $error) {
            $this->assertFalse(
                $method->invokeArgs($rateLimitedClient, [$error]),
                "Expected '{$error->getMessage()}' to be non-retryable"
            );
        }
    }

    public function testCalculateExponentialBackoff(): void
    {
        $provider = new OpenAIProvider();
        $underlyingClient = $this->createMock(ClientInterface::class);
        $rateLimiter = $this->createMock(RateLimiterInterface::class);
        $estimatorRegistry = TokenEstimatorRegistry::createDefault();

        $testDelay = new TestDelay();

        $rateLimitedClient = new RateLimitedClient(
            $underlyingClient,
            $rateLimiter,
            $estimatorRegistry,
            delay: $testDelay
        );

        $reflection = new ReflectionClass($rateLimitedClient);
        $method = $reflection->getMethod('calculateExponentialBackoff');
        $method->setAccessible(true);

        // Test exponential backoff progression
        $this->assertEquals(1, $method->invokeArgs($rateLimitedClient, [0])); // 1 * 2^0 = 1
        $this->assertEquals(2, $method->invokeArgs($rateLimitedClient, [1])); // 1 * 2^1 = 2
        $this->assertEquals(4, $method->invokeArgs($rateLimitedClient, [2])); // 1 * 2^2 = 4
        $this->assertEquals(8, $method->invokeArgs($rateLimitedClient, [3])); // 1 * 2^3 = 8
        $this->assertEquals(16, $method->invokeArgs($rateLimitedClient, [4])); // 1 * 2^4 = 16
        $this->assertEquals(30, $method->invokeArgs($rateLimitedClient, [5])); // Capped at 30
        $this->assertEquals(30, $method->invokeArgs($rateLimitedClient, [10])); // Still capped at 30
    }

    public function testHandleRateLimitWithRetryAfter(): void
    {
        // Test the handleRateLimit method directly to avoid sleep
        $provider = new OpenAIProvider();
        $underlyingClient = $this->createMock(ClientInterface::class);
        $rateLimiter = $this->createMock(RateLimiterInterface::class);
        $estimatorRegistry = TokenEstimatorRegistry::createDefault();
        $logger = $this->createMock(LoggerInterface::class);

        $logger->expects($this->once())->method('warning')->with(
            'Rate limit exceeded. Waiting before retry.',
            $this->callback(function ($context) {
                return $context['retry_after'] === 3 && $context['attempt'] === 1;
            })
        );

        $testDelay = new TestDelay();
        
        $rateLimitedClient = new RateLimitedClient(
            $underlyingClient,
            $rateLimiter,
            $estimatorRegistry,
            $logger,
            $testDelay
        );

        $reflection = new ReflectionClass($rateLimitedClient);
        $method = $reflection->getMethod('handleRateLimit');
        $method->setAccessible(true);

        $exception = new RateLimitExceededException(3, 'Rate limit with retry after');

        // This should complete without throwing (not last attempt)
        $method->invokeArgs($rateLimitedClient, [$exception, 0]);

        $this->assertTrue(true); // If we get here, the method completed successfully
    }

    public function testHandleRateLimitThrowsOnLastAttempt(): void
    {
        $provider = new OpenAIProvider();
        $underlyingClient = $this->createMock(ClientInterface::class);
        $rateLimiter = $this->createMock(RateLimiterInterface::class);
        $estimatorRegistry = TokenEstimatorRegistry::createDefault();

        $testDelay = new TestDelay();

        $rateLimitedClient = new RateLimitedClient(
            $underlyingClient,
            $rateLimiter,
            $estimatorRegistry,
            delay: $testDelay
        );

        $reflection = new ReflectionClass($rateLimitedClient);
        $method = $reflection->getMethod('handleRateLimit');
        $method->setAccessible(true);

        $exception = new RateLimitExceededException(5, 'Final rate limit');

        $this->expectException(RateLimitExceededException::class);
        $this->expectExceptionMessage('Final rate limit');

        $method->invokeArgs($rateLimitedClient, [$exception, 9]); // MAX_RETRIES - 1
    }
}