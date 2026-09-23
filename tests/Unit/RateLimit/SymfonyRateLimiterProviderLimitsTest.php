<?php

declare(strict_types = 1);

namespace Lingoda\AiSdk\Tests\Unit\RateLimit;

use Lingoda\AiSdk\Enum\Bedrock\ChatModel;
use Lingoda\AiSdk\Provider\BedrockProvider;
use Lingoda\AiSdk\RateLimit\SymfonyRateLimiter;
use PHPUnit\Framework\TestCase;

final class SymfonyRateLimiterProviderLimitsTest extends TestCase
{
    public function testBedrockUsesItsDeclaredTokenLimitNotTheFallback(): void
    {
        $model = (new BedrockProvider())->getModel(ChatModel::NOVA_2_LITE->value);

        // AIProvider::BEDROCK allows 100,000 tokens per minute; the unknown-provider fallback allows 50,000
        $limiter = new SymfonyRateLimiter();
        $this->assertTrue($limiter->isAllowed($model, 60000));
        $this->assertTrue($limiter->isAllowed($model, 40000));
        $this->assertFalse($limiter->isAllowed($model, 1));
    }
}
