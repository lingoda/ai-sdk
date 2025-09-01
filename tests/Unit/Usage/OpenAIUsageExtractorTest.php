<?php

declare(strict_types = 1);

namespace Lingoda\AiSdk\Tests\Unit\Usage;

use Lingoda\AiSdk\Result\Usage;
use Lingoda\AiSdk\Usage\OpenAI\OpenAIUsageExtractor;
use PHPUnit\Framework\TestCase;

final class OpenAIUsageExtractorTest extends TestCase
{
    private OpenAIUsageExtractor $extractor;

    protected function setUp(): void
    {
        $this->extractor = new OpenAIUsageExtractor();
    }

    public function testExtractsUsageData(): void
    {
        $usage = [
            'prompt_tokens' => 10,
            'completion_tokens' => 5,
            'total_tokens' => 15,
        ];

        $result = $this->extractor->extract($usage);

        $this->assertInstanceOf(Usage::class, $result);
        $this->assertEquals(10, $result->promptTokens);
        $this->assertEquals(5, $result->completionTokens);
        $this->assertEquals(15, $result->totalTokens);
    }

    public function testExtractsWithDetailedTokens(): void
    {
        $usage = [
            'prompt_tokens' => 10,
            'completion_tokens' => 5,
            'total_tokens' => 15,
            'prompt_tokens_details' => [
                'cached_tokens' => 3,
                'audio_tokens' => 0,
            ],
            'completion_tokens_details' => [
                'reasoning_tokens' => 2,
                'audio_tokens' => 0,
            ],
        ];

        $result = $this->extractor->extract($usage);

        $this->assertInstanceOf(Usage::class, $result);
        $this->assertEquals(10, $result->promptTokens);
        $this->assertEquals(5, $result->completionTokens);
        $this->assertEquals(15, $result->totalTokens);
        $this->assertEquals(3, $result->cachedTokens);
        $this->assertEquals(2, $result->reasoningTokens);
        $this->assertNotNull($result->promptDetails);
        $this->assertNotNull($result->completionDetails);
    }

    public function testCalculatesTotalIfMissing(): void
    {
        $usage = [
            'prompt_tokens' => 10,
            'completion_tokens' => 5,
        ];

        $result = $this->extractor->extract($usage);

        $this->assertInstanceOf(Usage::class, $result);
        $this->assertEquals(15, $result->totalTokens);
    }

    public function testReturnsNullForEmptyUsage(): void
    {
        $usage = [
            'prompt_tokens' => 0,
            'completion_tokens' => 0,
            'total_tokens' => 0,
        ];

        $this->assertNull($this->extractor->extract($usage));
    }

    public function testReturnsNullForNoTokenData(): void
    {
        $usage = [];

        $this->assertNull($this->extractor->extract($usage));
    }

    public function testHandlesPartialData(): void
    {
        $usage = [
            'prompt_tokens' => 10,
        ];

        $result = $this->extractor->extract($usage);

        $this->assertInstanceOf(Usage::class, $result);
        $this->assertEquals(10, $result->promptTokens);
        $this->assertEquals(0, $result->completionTokens);
        $this->assertEquals(10, $result->totalTokens);
    }

    public function testLangfuseCompatibility(): void
    {
        $usage = [
            'prompt_tokens' => 10,
            'completion_tokens' => 5,
            'total_tokens' => 15,
        ];

        $result = $this->extractor->extract($usage);
        $langfuseData = $result->toLangfuse();

        $this->assertEquals([
            'prompt_tokens' => 10,
            'completion_tokens' => 5,
            'total_tokens' => 15,
        ], $langfuseData);
    }
}
