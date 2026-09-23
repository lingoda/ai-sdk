<?php

declare(strict_types = 1);

namespace Lingoda\AiSdk\Tests\Unit\Usage;

use Lingoda\AiSdk\Result\Usage;
use Lingoda\AiSdk\Usage\Bedrock\NovaUsageExtractor;
use PHPUnit\Framework\TestCase;

final class NovaUsageExtractorTest extends TestCase
{
    private NovaUsageExtractor $extractor;

    protected function setUp(): void
    {
        $this->extractor = new NovaUsageExtractor();
    }

    public function testExtractsCamelCaseUsage(): void
    {
        $result = $this->extractor->extract(['inputTokens' => 10, 'outputTokens' => 5, 'totalTokens' => 16]);

        $this->assertInstanceOf(Usage::class, $result);
        $this->assertSame(10, $result->promptTokens);
        $this->assertSame(5, $result->completionTokens);
        $this->assertSame(16, $result->totalTokens);
        $this->assertNull($result->cachedTokens);
    }

    public function testTotalFallsBackToSum(): void
    {
        $result = $this->extractor->extract(['inputTokens' => 10, 'outputTokens' => 5]);

        $this->assertInstanceOf(Usage::class, $result);
        $this->assertSame(15, $result->totalTokens);
    }

    public function testCacheReadAndWriteAreSummed(): void
    {
        $result = $this->extractor->extract([
            'inputTokens' => 10,
            'outputTokens' => 5,
            'cacheReadInputTokenCount' => 3,
            'cacheWriteInputTokenCount' => 2,
        ]);

        $this->assertInstanceOf(Usage::class, $result);
        $this->assertSame(5, $result->cachedTokens);
    }

    public function testIgnoresSnakeCaseKeys(): void
    {
        $this->assertNull($this->extractor->extract(['input_tokens' => 10, 'output_tokens' => 5]));
    }

    public function testReturnsNullWhenNoTokens(): void
    {
        $this->assertNull($this->extractor->extract([]));
        $this->assertNull($this->extractor->extract(['inputTokens' => 0, 'outputTokens' => 0]));
        $this->assertNull($this->extractor->extract(['inputTokens' => null, 'outputTokens' => null]));
    }
}
