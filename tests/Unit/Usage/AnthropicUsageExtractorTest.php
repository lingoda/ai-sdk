<?php

declare(strict_types = 1);

namespace Lingoda\AiSdk\Tests\Unit\Usage;

use Lingoda\AiSdk\Result\Usage;
use Lingoda\AiSdk\Usage\Anthropic\AnthropicUsageExtractor;
use PHPUnit\Framework\TestCase;

final class AnthropicUsageExtractorTest extends TestCase
{
    private AnthropicUsageExtractor $extractor;

    protected function setUp(): void
    {
        $this->extractor = new AnthropicUsageExtractor();
    }

    public function testExtractsUsageData(): void
    {
        $usage = [
            'input_tokens' => 10,
            'output_tokens' => 5,
        ];

        $result = $this->extractor->extract($usage);

        $this->assertInstanceOf(Usage::class, $result);
        $this->assertEquals(10, $result->promptTokens);
        $this->assertEquals(5, $result->completionTokens);
        $this->assertEquals(15, $result->totalTokens); // Should calculate total
    }

    public function testExtractsWithCacheTokens(): void
    {
        $usage = [
            'input_tokens' => 10,
            'output_tokens' => 5,
            'cache_creation_input_tokens' => 2,
            'cache_read_input_tokens' => 3,
        ];

        $result = $this->extractor->extract($usage);

        $this->assertInstanceOf(Usage::class, $result);
        $this->assertEquals(10, $result->promptTokens);
        $this->assertEquals(5, $result->completionTokens);
        $this->assertEquals(15, $result->totalTokens);
        $this->assertEquals(5, $result->cachedTokens); // 2 + 3
    }

    public function testHandlesOnlyInputTokens(): void
    {
        $usage = [
            'input_tokens' => 10,
        ];

        $result = $this->extractor->extract($usage);

        $this->assertInstanceOf(Usage::class, $result);
        $this->assertEquals(10, $result->promptTokens);
        $this->assertEquals(0, $result->completionTokens);
        $this->assertEquals(10, $result->totalTokens);
    }

    public function testHandlesOnlyOutputTokens(): void
    {
        $usage = [
            'output_tokens' => 5,
        ];

        $result = $this->extractor->extract($usage);

        $this->assertInstanceOf(Usage::class, $result);
        $this->assertEquals(0, $result->promptTokens);
        $this->assertEquals(5, $result->completionTokens);
        $this->assertEquals(5, $result->totalTokens);
    }

    public function testReturnsNullForEmptyUsage(): void
    {
        $usage = [
            'input_tokens' => 0,
            'output_tokens' => 0,
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
            'input_tokens' => 20,
            'output_tokens' => 10,
        ];

        $result = $this->extractor->extract($usage);
        $langfuseData = $result->toLangfuse();

        $this->assertEquals([
            'prompt_tokens' => 20,
            'completion_tokens' => 10,
            'total_tokens' => 30,
        ], $langfuseData);
    }
}
