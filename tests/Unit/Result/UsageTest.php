<?php

declare(strict_types = 1);

namespace Lingoda\AiSdk\Tests\Unit\Result;

use Lingoda\AiSdk\Result\TokenDetails;
use Lingoda\AiSdk\Result\Usage;
use PHPUnit\Framework\TestCase;

final class UsageTest extends TestCase
{
    public function testConstructorWithRequiredParameters(): void
    {
        $usage = new Usage(
            promptTokens: 10,
            completionTokens: 5,
            totalTokens: 15
        );

        $this->assertEquals(10, $usage->promptTokens);
        $this->assertEquals(5, $usage->completionTokens);
        $this->assertEquals(15, $usage->totalTokens);
        $this->assertNull($usage->promptDetails);
        $this->assertNull($usage->completionDetails);
        $this->assertNull($usage->cachedTokens);
        $this->assertNull($usage->toolUseTokens);
        $this->assertNull($usage->reasoningTokens);
        $this->assertNull($usage->thoughtsTokens);
    }

    public function testConstructorWithAllParameters(): void
    {
        $promptDetails = new TokenDetails(audioTokens: 2, cachedTokens: 3);
        $completionDetails = new TokenDetails(reasoningTokens: 4);

        $usage = new Usage(
            promptTokens: 10,
            completionTokens: 5,
            totalTokens: 15,
            promptDetails: $promptDetails,
            completionDetails: $completionDetails,
            cachedTokens: 8,
            toolUseTokens: 2,
            reasoningTokens: 4,
            thoughtsTokens: 6
        );

        $this->assertEquals(10, $usage->promptTokens);
        $this->assertEquals(5, $usage->completionTokens);
        $this->assertEquals(15, $usage->totalTokens);
        $this->assertSame($promptDetails, $usage->promptDetails);
        $this->assertSame($completionDetails, $usage->completionDetails);
        $this->assertEquals(8, $usage->cachedTokens);
        $this->assertEquals(2, $usage->toolUseTokens);
        $this->assertEquals(4, $usage->reasoningTokens);
        $this->assertEquals(6, $usage->thoughtsTokens);
    }

    public function testTolangfuseReturnsBasicFormat(): void
    {
        $usage = new Usage(
            promptTokens: 20,
            completionTokens: 10,
            totalTokens: 30
        );

        $langfuseData = $usage->toLangfuse();

        $expected = [
            'prompt_tokens' => 20,
            'completion_tokens' => 10,
            'total_tokens' => 30,
        ];

        $this->assertEquals($expected, $langfuseData);
    }

    public function testToLangfuseIgnoresExtraDetails(): void
    {
        $promptDetails = new TokenDetails(audioTokens: 2, cachedTokens: 3);
        $completionDetails = new TokenDetails(reasoningTokens: 4);

        $usage = new Usage(
            promptTokens: 20,
            completionTokens: 10,
            totalTokens: 30,
            promptDetails: $promptDetails,
            completionDetails: $completionDetails,
            cachedTokens: 8,
            toolUseTokens: 2,
            reasoningTokens: 4,
            thoughtsTokens: 6
        );

        $langfuseData = $usage->toLangfuse();

        $expected = [
            'prompt_tokens' => 20,
            'completion_tokens' => 10,
            'total_tokens' => 30,
        ];

        $this->assertEquals($expected, $langfuseData);
    }

    public function testToArrayWithBasicData(): void
    {
        $usage = new Usage(
            promptTokens: 10,
            completionTokens: 5,
            totalTokens: 15
        );

        $expected = [
            'prompt_tokens' => 10,
            'completion_tokens' => 5,
            'total_tokens' => 15,
        ];

        $this->assertEquals($expected, $usage->toArray());
    }

    public function testToArrayWithAllData(): void
    {
        $promptDetails = new TokenDetails(audioTokens: 2, cachedTokens: 3);
        $completionDetails = new TokenDetails(reasoningTokens: 4);

        $usage = new Usage(
            promptTokens: 10,
            completionTokens: 5,
            totalTokens: 15,
            promptDetails: $promptDetails,
            completionDetails: $completionDetails,
            cachedTokens: 8,
            toolUseTokens: 2,
            reasoningTokens: 4,
            thoughtsTokens: 6
        );

        $expected = [
            'prompt_tokens' => 10,
            'completion_tokens' => 5,
            'total_tokens' => 15,
            'prompt_details' => [
                'audio_tokens' => 2,
                'cached_tokens' => 3,
            ],
            'completion_details' => [
                'reasoning_tokens' => 4,
            ],
            'cached_tokens' => 8,
            'tool_use_tokens' => 2,
            'reasoning_tokens' => 4,
            'thoughts_tokens' => 6,
        ];

        $this->assertEquals($expected, $usage->toArray());
    }

    public function testToArrayExcludesNullValues(): void
    {
        $promptDetails = new TokenDetails(audioTokens: 2);

        $usage = new Usage(
            promptTokens: 10,
            completionTokens: 5,
            totalTokens: 15,
            promptDetails: $promptDetails,
            completionDetails: null,
            cachedTokens: 8,
            toolUseTokens: null,
            reasoningTokens: null,
            thoughtsTokens: null
        );

        $expected = [
            'prompt_tokens' => 10,
            'completion_tokens' => 5,
            'total_tokens' => 15,
            'prompt_details' => [
                'audio_tokens' => 2,
            ],
            'cached_tokens' => 8,
        ];

        $this->assertEquals($expected, $usage->toArray());
    }

    public function testToArrayIncludesZeroValues(): void
    {
        $usage = new Usage(
            promptTokens: 0,
            completionTokens: 0,
            totalTokens: 0,
            cachedTokens: 0,
            toolUseTokens: 0,
            reasoningTokens: 0,
            thoughtsTokens: 0
        );

        $expected = [
            'prompt_tokens' => 0,
            'completion_tokens' => 0,
            'total_tokens' => 0,
            'cached_tokens' => 0,
            'tool_use_tokens' => 0,
            'reasoning_tokens' => 0,
            'thoughts_tokens' => 0,
        ];

        $this->assertEquals($expected, $usage->toArray());
    }

    public function testPromptDetailsOnlyIncludedWhenNotNull(): void
    {
        $usage = new Usage(
            promptTokens: 10,
            completionTokens: 5,
            totalTokens: 15,
            promptDetails: null
        );

        $result = $usage->toArray();

        $this->assertArrayNotHasKey('prompt_details', $result);
    }

    public function testCompletionDetailsOnlyIncludedWhenNotNull(): void
    {
        $usage = new Usage(
            promptTokens: 10,
            completionTokens: 5,
            totalTokens: 15,
            completionDetails: null
        );

        $result = $usage->toArray();

        $this->assertArrayNotHasKey('completion_details', $result);
    }
}
