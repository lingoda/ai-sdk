<?php

declare(strict_types = 1);

namespace Lingoda\AiSdk\Tests\Unit\Usage;

use Lingoda\AiSdk\Result\Usage;
use Lingoda\AiSdk\Usage\Gemini\GeminiUsageExtractor;
use PHPUnit\Framework\TestCase;

final class GeminiUsageExtractorTest extends TestCase
{
    private GeminiUsageExtractor $extractor;

    protected function setUp(): void
    {
        $this->extractor = new GeminiUsageExtractor();
    }

    public function testExtractsUsageData(): void
    {
        $usage = [
            'prompt_token_count' => 10,
            'candidates_token_count' => 5,
            'total_token_count' => 15,
        ];

        $result = $this->extractor->extract($usage);

        $this->assertInstanceOf(Usage::class, $result);
        $this->assertEquals(10, $result->promptTokens);
        $this->assertEquals(5, $result->completionTokens);
        $this->assertEquals(15, $result->totalTokens);
    }

    public function testExtractsWithSpecialTokens(): void
    {
        $usage = [
            'prompt_token_count' => 10,
            'candidates_token_count' => 5,
            'total_token_count' => 15,
            'cached_content_token_count' => 3,
            'tool_use_prompt_token_count' => 2,
            'thoughts_token_count' => 4,
        ];

        $result = $this->extractor->extract($usage);

        $this->assertInstanceOf(Usage::class, $result);
        $this->assertEquals(10, $result->promptTokens);
        $this->assertEquals(5, $result->completionTokens);
        $this->assertEquals(15, $result->totalTokens);
        $this->assertEquals(3, $result->cachedTokens);
        $this->assertEquals(2, $result->toolUseTokens);
        $this->assertEquals(4, $result->thoughtsTokens);
    }

    public function testCalculatesTotalIfMissing(): void
    {
        $usage = [
            'prompt_token_count' => 10,
            'candidates_token_count' => 5,
        ];

        $result = $this->extractor->extract($usage);

        $this->assertInstanceOf(Usage::class, $result);
        $this->assertEquals(15, $result->totalTokens);
    }

    public function testHandlesPartialData(): void
    {
        $usage = [
            'prompt_token_count' => 10,
        ];

        $result = $this->extractor->extract($usage);

        $this->assertInstanceOf(Usage::class, $result);
        $this->assertEquals(10, $result->promptTokens);
        $this->assertEquals(0, $result->completionTokens);
        $this->assertEquals(10, $result->totalTokens);
    }

    public function testReturnsNullForEmptyUsage(): void
    {
        $usage = [
            'prompt_token_count' => 0,
            'candidates_token_count' => 0,
            'total_token_count' => 0,
        ];

        $this->assertNull($this->extractor->extract($usage));
    }

    public function testReturnsNullForNoTokenData(): void
    {
        $usage = [];

        $this->assertNull($this->extractor->extract($usage));
    }

    public function testLangfuseCompatibility(): void
    {
        $usage = [
            'prompt_token_count' => 15,
            'candidates_token_count' => 8,
            'total_token_count' => 23,
        ];

        $result = $this->extractor->extract($usage);
        $langfuseData = $result->toLangfuse();

        $this->assertEquals([
            'prompt_tokens' => 15,
            'completion_tokens' => 8,
            'total_tokens' => 23,
        ], $langfuseData);
    }
}
