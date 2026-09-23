<?php

declare(strict_types = 1);

namespace Lingoda\AiSdk\Tests\Unit\RateLimit;

use Lingoda\AiSdk\Enum\Bedrock\ChatModel as BedrockChatModel;
use Lingoda\AiSdk\Enum\OpenAI\ChatModel;
use Lingoda\AiSdk\ModelInterface;
use Lingoda\AiSdk\Provider\BedrockProvider;
use Lingoda\AiSdk\Provider\OpenAIProvider;
use Lingoda\AiSdk\ProviderInterface;
use Lingoda\AiSdk\RateLimit\ExternalRateLimiterInterface;
use Lingoda\AiSdk\RateLimit\SymfonyRateLimiter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;

final class SymfonyRateLimiterTest extends TestCase
{
    private ModelInterface $model;

    protected function setUp(): void
    {
        $this->model = (new OpenAIProvider())->getModel(ChatModel::GPT_4O_MINI->value);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function limiterTypes(): iterable
    {
        yield 'requests' => ['requests'];
        yield 'tokens' => ['tokens'];
    }

    public function testBedrockUsesItsDeclaredTokenLimitNotTheFallback(): void
    {
        $model = (new BedrockProvider())->getModel(BedrockChatModel::NOVA_2_LITE->value);

        // AIProvider::BEDROCK allows 100,000 tokens per minute; the unknown-provider fallback allows 50,000
        $limiter = new SymfonyRateLimiter();
        $this->assertTrue($limiter->isAllowed($model, 60000));
        $this->assertTrue($limiter->isAllowed($model, 40000));
        $this->assertFalse($limiter->isAllowed($model, 1));
    }

    public function testUnknownProviderGetsTheFallbackTokenLimit(): void
    {
        $provider = $this->createStub(ProviderInterface::class);
        $provider->method('getId')->willReturn('custom');
        $model = $this->createStub(ModelInterface::class);
        $model->method('getProvider')->willReturn($provider);
        $model->method('getId')->willReturn('custom-model');

        $limiter = new SymfonyRateLimiter();
        $this->assertTrue($limiter->isAllowed($model, 50000));
        $this->assertFalse($limiter->isAllowed($model, 1));
    }

    public function testRetryAfterIsNullWhenARequestIsAvailable(): void
    {
        $this->assertNull((new SymfonyRateLimiter())->getRetryAfter($this->model));
    }

    #[DataProvider('limiterTypes')]
    public function testRetryAfterDoesNotConsume(string $type): void
    {
        $limiter = $this->limiterAllowingOnePerHour($type);

        for ($i = 0; $i < 3; ++$i) {
            $this->assertNull($limiter->getRetryAfter($this->model));
        }

        $this->assertTrue($limiter->isAllowed($this->model));
    }

    #[DataProvider('limiterTypes')]
    public function testRetryAfterReportsTheWaitOfTheExhaustedLimiter(string $type): void
    {
        $limiter = $this->limiterAllowingOnePerHour($type);
        $this->assertTrue($limiter->isAllowed($this->model));

        $retryAfter = $limiter->getRetryAfter($this->model);

        $this->assertNotNull($retryAfter);
        $this->assertGreaterThan(3500, $retryAfter);
        $this->assertLessThanOrEqual(3600, $retryAfter);
    }

    /**
     * One external limiter of the given type allows a single unit per hour; the other type uses the internal defaults.
     */
    private function limiterAllowingOnePerHour(string $type): SymfonyRateLimiter
    {
        $factory = new RateLimiterFactory(
            ['id' => 'test_' . $type, 'policy' => 'fixed_window', 'limit' => 1, 'interval' => '1 hour'],
            new InMemoryStorage()
        );

        $external = $this->createStub(ExternalRateLimiterInterface::class);
        $external->method('hasRateLimiter')->willReturnCallback(static fn (string $providerId, string $limiterType): bool => $limiterType === $type);
        $external->method('getRateLimiter')->willReturn($factory);

        return new SymfonyRateLimiter(externalRateLimiter: $external);
    }
}
