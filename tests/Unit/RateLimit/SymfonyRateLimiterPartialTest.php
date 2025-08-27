<?php

declare(strict_types=1);

namespace Lingoda\AiSdk\Tests\Unit\RateLimit;

use Lingoda\AiSdk\Exception\RateLimitExceededException;
use Lingoda\AiSdk\Enum\OpenAI\ChatModel;
use Lingoda\AiSdk\Model\ConfigurableModel;
use Lingoda\AiSdk\Provider\OpenAIProvider;
use Lingoda\AiSdk\RateLimit\ExternalRateLimiterInterface;
use Lingoda\AiSdk\RateLimit\SymfonyRateLimiter;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\RateLimiter\LimiterInterface;
use Symfony\Component\RateLimiter\RateLimit;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\RateLimiter\Reservation;

final class SymfonyRateLimiterPartialTest extends TestCase
{
    private LoggerInterface&MockObject $logger;
    private ExternalRateLimiterInterface&MockObject $externalRateLimiter;
    private RateLimiterFactoryInterface&MockObject $requestLimiterFactory;
    private LimiterInterface&MockObject $requestLimiter;
    private ConfigurableModel $model;

    protected function setUp(): void
    {
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->externalRateLimiter = $this->createMock(ExternalRateLimiterInterface::class);
        $this->requestLimiterFactory = $this->createMock(RateLimiterFactoryInterface::class);
        $this->requestLimiter = $this->createMock(LimiterInterface::class);
        
        $provider = new OpenAIProvider();
        $this->model = new ConfigurableModel(ChatModel::GPT_4O_MINI, $provider);
    }

    public function testSupportsRequestsOnlyRateLimiting(): void
    {
        // Setup external rate limiter with only requests
        $this->externalRateLimiter
            ->expects($this->exactly(2))
            ->method('hasRateLimiter')
            ->willReturnMap([
                ['openai', 'requests', true],   // Has requests limiter
                ['openai', 'tokens', false],    // No tokens limiter
            ]);

        $this->externalRateLimiter
            ->expects($this->once())
            ->method('getRateLimiter')
            ->with('openai', 'requests', $this->model)
            ->willReturn($this->requestLimiterFactory);

        // Setup request limiter to accept the request (called twice)
        $this->requestLimiterFactory
            ->expects($this->exactly(2))
            ->method('create')
            ->with('openai_requests')
            ->willReturn($this->requestLimiter);

        $rateLimit = $this->createMock(RateLimit::class);
        $rateLimit->expects($this->exactly(2))
            ->method('isAccepted')
            ->willReturn(true);

        $this->requestLimiter
            ->expects($this->exactly(2))
            ->method('consume')
            ->with(1)
            ->willReturn($rateLimit);

        // Create rate limiter with only external requests limiter
        $rateLimiter = new SymfonyRateLimiter(
            $this->logger,
            null,
            $this->externalRateLimiter
        );

        // This should work without throwing an exception
        $rateLimiter->consume($this->model, 1);
        
        // Verify we're using mixed external/internal limiters
        $this->logger
            ->expects($this->once())
            ->method('debug')
            ->with('Rate limit check passed', [
                'model' => 'gpt-4o-mini',
                'provider' => 'openai',
                'estimated_tokens' => 1,
                'requests_limiter' => 'enabled',
                'tokens_limiter' => 'enabled', // Internal fallback for tokens
            ]);

        // Call again to trigger the debug log
        $rateLimiter->consume($this->model, 1);
    }

    public function testSupportsTokensOnlyRateLimiting(): void
    {
        $tokenLimiterFactory = $this->createMock(RateLimiterFactory::class);
        $tokenLimiter = $this->createMock(LimiterInterface::class);

        // Setup external rate limiter with only tokens
        $this->externalRateLimiter
            ->expects($this->exactly(2))
            ->method('hasRateLimiter')
            ->willReturnMap([
                ['openai', 'requests', false],  // No requests limiter
                ['openai', 'tokens', true],     // Has tokens limiter
            ]);

        $this->externalRateLimiter
            ->expects($this->once())
            ->method('getRateLimiter')
            ->with('openai', 'tokens', $this->model)
            ->willReturn($tokenLimiterFactory);

        // Setup token limiter to accept the request
        $tokenLimiterFactory
            ->expects($this->once())
            ->method('create')
            ->with('openai_tokens')
            ->willReturn($tokenLimiter);

        $rateLimit = $this->createMock(RateLimit::class);
        $rateLimit->expects($this->once())
            ->method('isAccepted')
            ->willReturn(true);

        $tokenLimiter
            ->expects($this->once())
            ->method('consume')
            ->with(100) // Custom token count
            ->willReturn($rateLimit);

        // Create rate limiter with only external tokens limiter
        $rateLimiter = new SymfonyRateLimiter(
            $this->logger,
            null,
            $this->externalRateLimiter
        );

        // This should work without throwing an exception
        $rateLimiter->consume($this->model, 100);
    }

    public function testRequestsOnlyRateLimitingThrowsException(): void
    {
        // Setup external rate limiter with only requests that will be exceeded
        $this->externalRateLimiter
            ->expects($this->exactly(2))
            ->method('hasRateLimiter')
            ->willReturnMap([
                ['openai', 'requests', true],   // Has requests limiter
                ['openai', 'tokens', false],    // No tokens limiter
            ]);

        $this->externalRateLimiter
            ->expects($this->once())
            ->method('getRateLimiter')
            ->with('openai', 'requests', $this->model)
            ->willReturn($this->requestLimiterFactory);

        // Setup request limiter to reject the request
        $this->requestLimiterFactory
            ->expects($this->once())
            ->method('create')
            ->with('openai_requests')
            ->willReturn($this->requestLimiter);

        $rateLimit = $this->createMock(RateLimit::class);
        $rateLimit->expects($this->once())
            ->method('isAccepted')
            ->willReturn(false);
        
        $rateLimit->expects($this->once())
            ->method('getRetryAfter')
            ->willReturn(new \DateTimeImmutable('+60 seconds'));

        $this->requestLimiter
            ->expects($this->once())
            ->method('consume')
            ->with(1)
            ->willReturn($rateLimit);

        // Create rate limiter
        $rateLimiter = new SymfonyRateLimiter(
            $this->logger,
            null,
            $this->externalRateLimiter
        );

        // This should throw a rate limit exception
        $this->expectException(RateLimitExceededException::class);
        $this->expectExceptionMessage('Request rate limit exceeded');
        
        $rateLimiter->consume($this->model, 1);
    }
}