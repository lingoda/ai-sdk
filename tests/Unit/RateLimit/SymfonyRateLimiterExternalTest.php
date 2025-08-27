<?php

declare(strict_types=1);

namespace Lingoda\AiSdk\Tests\Unit\RateLimit;

use Lingoda\AiSdk\Enum\OpenAI\ChatModel;
use Lingoda\AiSdk\Model\ConfigurableModel;
use Lingoda\AiSdk\Provider\OpenAIProvider;
use Lingoda\AiSdk\RateLimit\ExternalRateLimiterInterface;
use Lingoda\AiSdk\RateLimit\SymfonyRateLimiter;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\RateLimiter\RateLimiterFactory;

final class SymfonyRateLimiterExternalTest extends TestCase
{
    public function testUsesExternalRateLimiterWhenAvailable(): void
    {
        $logger = new NullLogger();
        $model = new ConfigurableModel(ChatModel::GPT_4O_MINI, new OpenAIProvider());

        // Mock external rate limiter
        $externalRateLimiter = $this->createMock(ExternalRateLimiterInterface::class);
        $mockRequestsFactory = $this->createMock(RateLimiterFactory::class);
        $mockTokensFactory = $this->createMock(RateLimiterFactory::class);

        // External rate limiter should indicate it has limiters available
        $externalRateLimiter->expects($this->exactly(2))
            ->method('hasRateLimiter')
            ->willReturnCallback(function (string $providerId, string $type): bool {
                return $providerId === 'openai' && in_array($type, ['requests', 'tokens'], true);
            });

        // External rate limiter should return mock factories
        $externalRateLimiter->expects($this->exactly(2))
            ->method('getRateLimiter')
            ->willReturnCallback(function (string $providerId, string $type) use ($mockRequestsFactory, $mockTokensFactory) {
                if ($type === 'requests') {
                    return $mockRequestsFactory;
                }
                return $mockTokensFactory;
            });

        $rateLimiter = new SymfonyRateLimiter($logger, null, $externalRateLimiter);

        // Mock the rate limit consumption
        $mockRequestsLimiter = $this->createMock(\Symfony\Component\RateLimiter\LimiterInterface::class);
        $mockTokensLimiter = $this->createMock(\Symfony\Component\RateLimiter\LimiterInterface::class);

        $mockRequestsFactory->expects($this->once())
            ->method('create')
            ->willReturn($mockRequestsLimiter);

        $mockTokensFactory->expects($this->once())
            ->method('create')
            ->willReturn($mockTokensLimiter);

        $mockRateLimit = $this->createMock(\Symfony\Component\RateLimiter\RateLimit::class);
        $mockRateLimit->method('isAccepted')->willReturn(true);

        $mockRequestsLimiter->expects($this->once())
            ->method('consume')
            ->willReturn($mockRateLimit);

        $mockTokensLimiter->expects($this->once())
            ->method('consume')
            ->willReturn($mockRateLimit);

        // This should not throw an exception
        $rateLimiter->consume($model, 100);

        // Test passes if no exception is thrown
        $this->assertTrue(true);
    }

    public function testFallsBackToInternalLimitersWhenExternalNotAvailable(): void
    {
        $logger = new NullLogger();
        $model = new ConfigurableModel(ChatModel::GPT_4O_MINI, new OpenAIProvider());

        // Mock external rate limiter that doesn't have limiters
        $externalRateLimiter = $this->createMock(ExternalRateLimiterInterface::class);
        $externalRateLimiter->method('hasRateLimiter')->willReturn(false);

        $rateLimiter = new SymfonyRateLimiter($logger, null, $externalRateLimiter);

        // This should not throw an exception and should use internal limiters
        $this->assertTrue($rateLimiter->isAllowed($model, 1));

        // Test passes if no exception is thrown and external fallback works
        $this->assertTrue(true);
    }

    public function testWorksWithoutExternalRateLimiter(): void
    {
        $logger = new NullLogger();
        $model = new ConfigurableModel(ChatModel::GPT_4O_MINI, new OpenAIProvider());

        $rateLimiter = new SymfonyRateLimiter($logger);

        // This should work without external rate limiter (backward compatibility)
        $this->assertTrue($rateLimiter->isAllowed($model, 1));

        // Test backward compatibility - should work without external rate limiter
        $this->assertTrue(true);
    }
}