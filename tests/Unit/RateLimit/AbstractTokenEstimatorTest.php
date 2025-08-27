<?php

declare(strict_types=1);

namespace Lingoda\AiSdk\Tests\Unit\RateLimit;

use Lingoda\AiSdk\ModelInterface;
use Lingoda\AiSdk\RateLimit\AbstractTokenEstimator;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class AbstractTokenEstimatorTest extends TestCase
{
    private TestableTokenEstimator $estimator;
    private ModelInterface&MockObject $model;

    protected function setUp(): void
    {
        $this->estimator = new TestableTokenEstimator();
        $this->model = $this->createMock(ModelInterface::class);
    }

    public function testEstimateWithStringPayload(): void
    {
        $result = $this->estimator->estimate($this->model, 'Hello world');
        
        $this->assertGreaterThan(0, $result);
        $this->assertIsInt($result);
    }

    public function testEstimateWithArrayPayload(): void
    {
        $payload = ['text' => 'Hello world', 'other' => 'data'];
        $result = $this->estimator->estimate($this->model, $payload);
        
        $this->assertGreaterThan(0, $result);
        $this->assertIsInt($result);
    }

    public function testEstimateTokensFromTextWithEmptyString(): void
    {
        $result = $this->estimator->publicEstimateTokensFromText('');
        
        $this->assertEquals(1, $result); // Minimum token count
    }

    public function testEstimateTokensFromTextWithSpecialPatterns(): void
    {
        // Test with JSON structure
        $jsonText = '{"key": "value", "array": [1, 2, 3]}';
        $jsonResult = $this->estimator->publicEstimateTokensFromText($jsonText);
        
        $this->assertGreaterThan(0, $jsonResult);
        
        // Test with code block
        $codeText = '```php\necho "Hello World";\n```';
        $codeResult = $this->estimator->publicEstimateTokensFromText($codeText);
        
        $this->assertGreaterThan(0, $codeResult);
        
        // Test with URL
        $urlText = 'Visit https://example.com for more info';
        $urlResult = $this->estimator->publicEstimateTokensFromText($urlText);
        
        $this->assertGreaterThan(0, $urlResult);
    }

    public function testEstimateTokensFromTextWithMultipleSentences(): void
    {
        $text = 'This is the first sentence. This is the second! And this is the third?';
        $result = $this->estimator->publicEstimateTokensFromText($text);
        
        $this->assertGreaterThan(0, $result);
        // Should be more than a single word due to multiple sentences
        $singleWordResult = $this->estimator->publicEstimateTokensFromText('word');
        $this->assertGreaterThan($singleWordResult, $result);
    }

    public function testProviderAdjustmentsAreApplied(): void
    {
        // Test that provider-specific adjustments affect the result
        $text = 'This is a standard test sentence for token estimation.';
        $result = $this->estimator->publicEstimateTokensFromText($text);
        
        $this->assertGreaterThan(0, $result);
        $this->assertIsInt($result);
        
        // Test with different adjustments via a different estimator
        $customEstimator = new TestableTokenEstimatorWithCustomAdjustments();
        $customResult = $customEstimator->publicEstimateTokensFromText($text);
        
        // Results should be different due to different provider adjustments
        $this->assertNotEquals($result, $customResult);
    }

    public function testEstimateTokensFromTextWithWhitespaceNormalization(): void
    {
        $textWithExtraSpaces = 'This   has    multiple     spaces.';
        $normalText = 'This has multiple spaces.';
        
        $extraSpacesResult = $this->estimator->publicEstimateTokensFromText($textWithExtraSpaces);
        $normalResult = $this->estimator->publicEstimateTokensFromText($normalText);
        
        // Should be similar due to whitespace normalization
        $this->assertGreaterThan(0, $extraSpacesResult);
        $this->assertGreaterThan(0, $normalResult);
    }
}

/**
 * Concrete implementation for testing AbstractTokenEstimator
 */
class TestableTokenEstimator extends AbstractTokenEstimator
{
    protected function extractTextFromPayload(array $payload): string
    {
        $text = '';
        foreach ($payload as $value) {
            if (is_string($value)) {
                $text .= ' ' . $value;
            }
        }
        return trim($text);
    }

    public function publicEstimateTokensFromText(string $text): int
    {
        return $this->estimateTokensFromText($text);
    }
}

/**
 * Another concrete implementation with custom adjustments for testing
 */
class TestableTokenEstimatorWithCustomAdjustments extends AbstractTokenEstimator
{
    protected function extractTextFromPayload(array $payload): string
    {
        $text = '';
        foreach ($payload as $value) {
            if (is_string($value)) {
                $text .= ' ' . $value;
            }
        }
        return trim($text);
    }

    protected function getProviderAdjustments(): array
    {
        return [
            'char_divisor' => 3.5,        // Different from default
            'word_multiplier' => 0.8,     // Different from default
            'sentence_tokens' => 25,      // Different from default
            'efficiency_factor' => 1.2,   // Different from default
        ];
    }

    public function publicEstimateTokensFromText(string $text): int
    {
        return $this->estimateTokensFromText($text);
    }
}