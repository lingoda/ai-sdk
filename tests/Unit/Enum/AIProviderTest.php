<?php

declare(strict_types = 1);

namespace Lingoda\AiSdk\Tests\Unit\Enum;

use Lingoda\AiSdk\Enum\AIProvider;
use PHPUnit\Framework\TestCase;

final class AIProviderTest extends TestCase
{
    public function testProviderValues(): void
    {
        $this->assertEquals('openai', AIProvider::OPENAI->value);
        $this->assertEquals('anthropic', AIProvider::ANTHROPIC->value);
        $this->assertEquals('gemini', AIProvider::GEMINI->value);
        $this->assertEquals('bedrock', AIProvider::BEDROCK->value);
    }

    public function testProviderNames(): void
    {
        $this->assertEquals('OpenAI', AIProvider::OPENAI->getName());
        $this->assertEquals('Anthropic', AIProvider::ANTHROPIC->getName());
        $this->assertEquals('Google Gemini', AIProvider::GEMINI->getName());
        $this->assertEquals('AWS Bedrock', AIProvider::BEDROCK->getName());
    }


    public function testDefaultRateLimits(): void
    {
        $openaiLimits = AIProvider::OPENAI->getDefaultRateLimits();
        $this->assertArrayHasKey('requests_per_minute', $openaiLimits);
        $this->assertArrayHasKey('tokens_per_minute', $openaiLimits);
        $this->assertEquals(180, $openaiLimits['requests_per_minute']);
        $this->assertEquals(450000, $openaiLimits['tokens_per_minute']);

        $anthropicLimits = AIProvider::ANTHROPIC->getDefaultRateLimits();
        $this->assertEquals(100, $anthropicLimits['requests_per_minute']);
        $this->assertEquals(100000, $anthropicLimits['tokens_per_minute']);

        $geminiLimits = AIProvider::GEMINI->getDefaultRateLimits();
        $this->assertEquals(1000, $geminiLimits['requests_per_minute']);
        $this->assertEquals(1000000, $geminiLimits['tokens_per_minute']);

        $bedrockLimits = AIProvider::BEDROCK->getDefaultRateLimits();
        $this->assertEquals(60, $bedrockLimits['requests_per_minute']);
        $this->assertEquals(100000, $bedrockLimits['tokens_per_minute']);
    }

    public function testCases(): void
    {
        $this->assertSame(
            [AIProvider::OPENAI, AIProvider::ANTHROPIC, AIProvider::GEMINI, AIProvider::BEDROCK, AIProvider::TYPESAFE],
            AIProvider::cases()
        );
    }
}
