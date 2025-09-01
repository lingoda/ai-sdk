<?php

declare(strict_types = 1);

namespace Lingoda\AiSdk\Tests\Unit\Result;

use Lingoda\AiSdk\Result\TokenDetails;
use PHPUnit\Framework\TestCase;

final class TokenDetailsTest extends TestCase
{
    public function testConstructorWithAllParameters(): void
    {
        $tokenDetails = new TokenDetails(
            audioTokens: 10,
            cachedTokens: 20,
            reasoningTokens: 30,
            acceptedPredictionTokens: 40,
            rejectedPredictionTokens: 50,
            modalityBreakdown: ['text' => 100, 'image' => 50]
        );

        $this->assertEquals(10, $tokenDetails->audioTokens);
        $this->assertEquals(20, $tokenDetails->cachedTokens);
        $this->assertEquals(30, $tokenDetails->reasoningTokens);
        $this->assertEquals(40, $tokenDetails->acceptedPredictionTokens);
        $this->assertEquals(50, $tokenDetails->rejectedPredictionTokens);
        $this->assertEquals(['text' => 100, 'image' => 50], $tokenDetails->modalityBreakdown);
    }

    public function testConstructorWithDefaults(): void
    {
        $tokenDetails = new TokenDetails();

        $this->assertNull($tokenDetails->audioTokens);
        $this->assertNull($tokenDetails->cachedTokens);
        $this->assertNull($tokenDetails->reasoningTokens);
        $this->assertNull($tokenDetails->acceptedPredictionTokens);
        $this->assertNull($tokenDetails->rejectedPredictionTokens);
        $this->assertNull($tokenDetails->modalityBreakdown);
    }

    public function testHasDataReturnsTrueWhenAudioTokensSet(): void
    {
        $tokenDetails = new TokenDetails(audioTokens: 10);

        $this->assertTrue($tokenDetails->hasData());
    }

    public function testHasDataReturnsTrueWhenCachedTokensSet(): void
    {
        $tokenDetails = new TokenDetails(cachedTokens: 20);

        $this->assertTrue($tokenDetails->hasData());
    }

    public function testHasDataReturnsTrueWhenReasoningTokensSet(): void
    {
        $tokenDetails = new TokenDetails(reasoningTokens: 30);

        $this->assertTrue($tokenDetails->hasData());
    }

    public function testHasDataReturnsTrueWhenAcceptedPredictionTokensSet(): void
    {
        $tokenDetails = new TokenDetails(acceptedPredictionTokens: 40);

        $this->assertTrue($tokenDetails->hasData());
    }

    public function testHasDataReturnsTrueWhenRejectedPredictionTokensSet(): void
    {
        $tokenDetails = new TokenDetails(rejectedPredictionTokens: 50);

        $this->assertTrue($tokenDetails->hasData());
    }

    public function testHasDataReturnsTrueWhenModalityBreakdownSet(): void
    {
        $tokenDetails = new TokenDetails(modalityBreakdown: ['text' => 100]);

        $this->assertTrue($tokenDetails->hasData());
    }

    public function testHasDataReturnsFalseWhenAllNull(): void
    {
        $tokenDetails = new TokenDetails();

        $this->assertFalse($tokenDetails->hasData());
    }

    public function testToArrayWithAllData(): void
    {
        $tokenDetails = new TokenDetails(
            audioTokens: 10,
            cachedTokens: 20,
            reasoningTokens: 30,
            acceptedPredictionTokens: 40,
            rejectedPredictionTokens: 50,
            modalityBreakdown: ['text' => 100, 'image' => 50]
        );

        $expected = [
            'audio_tokens' => 10,
            'cached_tokens' => 20,
            'reasoning_tokens' => 30,
            'accepted_prediction_tokens' => 40,
            'rejected_prediction_tokens' => 50,
            'modality_breakdown' => ['text' => 100, 'image' => 50],
        ];

        $this->assertEquals($expected, $tokenDetails->toArray());
    }

    public function testToArrayWithPartialData(): void
    {
        $tokenDetails = new TokenDetails(
            audioTokens: 10,
            reasoningTokens: 30,
        );

        $expected = [
            'audio_tokens' => 10,
            'reasoning_tokens' => 30,
        ];

        $this->assertEquals($expected, $tokenDetails->toArray());
    }

    public function testToArrayExcludesNullValues(): void
    {
        $tokenDetails = new TokenDetails(
            audioTokens: 10,
            cachedTokens: null,
            reasoningTokens: 30,
            acceptedPredictionTokens: null,
        );

        $expected = [
            'audio_tokens' => 10,
            'reasoning_tokens' => 30,
        ];

        $this->assertEquals($expected, $tokenDetails->toArray());
    }

    public function testToArrayReturnsEmptyWhenNoData(): void
    {
        $tokenDetails = new TokenDetails();

        $this->assertEquals([], $tokenDetails->toArray());
    }

    public function testZeroValuesIncludedInToArray(): void
    {
        $tokenDetails = new TokenDetails(
            audioTokens: 0,
            cachedTokens: 0,
        );

        $expected = [
            'audio_tokens' => 0,
            'cached_tokens' => 0,
        ];

        $this->assertEquals($expected, $tokenDetails->toArray());
    }
}
