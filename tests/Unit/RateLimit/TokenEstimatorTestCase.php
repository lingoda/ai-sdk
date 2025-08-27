<?php

declare(strict_types=1);

namespace Lingoda\AiSdk\Tests\Unit\RateLimit;

use Lingoda\AiSdk\ModelInterface;
use Lingoda\AiSdk\ProviderInterface;
use Lingoda\AiSdk\RateLimit\TokenEstimatorInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Base test case for TokenEstimator implementations.
 * 
 * Provides common test patterns for all TokenEstimator classes to ensure
 * consistent token estimation behavior across different provider implementations.
 */
abstract class TokenEstimatorTestCase extends TestCase
{
    /**
     * The estimator being tested.
     */
    protected TokenEstimatorInterface $estimator;
    
    /**
     * Mock model for testing.
     * 
     * @var ModelInterface&MockObject
     */
    protected ModelInterface&MockObject $model;
    
    /**
     * Create an instance of the estimator being tested.
     */
    abstract protected function createEstimator(): TokenEstimatorInterface;
    
    /**
     * Get a provider-specific payload for testing.
     * This should return a payload in the format expected by the specific provider.
     * 
     * @return array<string, mixed>
     */
    abstract protected function getProviderSpecificPayload(): array;
    
    /**
     * Get the provider ID for this estimator (e.g., 'openai', 'anthropic', 'gemini').
     */
    protected function getProviderId(): string
    {
        return 'test-provider';
    }
    
    /**
     * Set up test fixtures.
     */
    protected function setUp(): void
    {
        $this->estimator = $this->createEstimator();
        $this->model = $this->createMockModel();
    }
    
    /**
     * Create a mock model for testing.
     */
    protected function createMockModel(): ModelInterface&MockObject
    {
        $provider = $this->createMock(ProviderInterface::class);
        $provider->method('getId')->willReturn($this->getProviderId());
        
        $model = $this->createMock(ModelInterface::class);
        $model->method('getProvider')->willReturn($provider);
        
        return $model;
    }
    
    /**
     * Test estimation from simple string.
     */
    public function testEstimateFromSimpleString(): void
    {
        $result = $this->estimator->estimate($this->model, 'Hello world');
        
        $this->assertIsInt($result);
        $this->assertGreaterThan(0, $result);
        $this->assertLessThan(100, $result, 'Simple string should not have excessive token count');
    }
    
    /**
     * Test estimation from empty payload.
     */
    public function testEstimateFromEmptyPayload(): void
    {
        $result = $this->estimator->estimate($this->model, []);
        
        $this->assertEquals(1, $result, 'Empty payload should return minimum token count of 1');
    }
    
    /**
     * Test estimation from empty string.
     */
    public function testEstimateFromEmptyString(): void
    {
        $result = $this->estimator->estimate($this->model, '');
        
        $this->assertEquals(1, $result, 'Empty string should return minimum token count of 1');
    }
    
    /**
     * Test that longer text gives higher estimate.
     */
    public function testLongerTextGivesHigherEstimate(): void
    {
        $shortText = 'Hello';
        $longText = 'Hello world, this is a much longer text that should result in a higher token count estimate because it contains more words and characters.';
        
        $shortEstimate = $this->estimator->estimate($this->model, $shortText);
        $longEstimate = $this->estimator->estimate($this->model, $longText);
        
        $this->assertGreaterThan(
            $shortEstimate,
            $longEstimate,
            'Longer text should have higher token estimate'
        );
    }
    
    /**
     * Test estimation with provider-specific payload format.
     */
    public function testEstimateWithProviderSpecificPayload(): void
    {
        $payload = $this->getProviderSpecificPayload();
        $result = $this->estimator->estimate($this->model, $payload);
        
        $this->assertIsInt($result);
        $this->assertGreaterThan(0, $result);
    }
    
    /**
     * Test estimation is consistent for same input.
     */
    public function testEstimationIsConsistent(): void
    {
        $text = 'This is a test message for consistency check.';
        
        $result1 = $this->estimator->estimate($this->model, $text);
        $result2 = $this->estimator->estimate($this->model, $text);
        
        $this->assertEquals($result1, $result2, 'Same input should give same estimate');
    }
    
    /**
     * Test estimation with special characters.
     */
    public function testEstimateWithSpecialCharacters(): void
    {
        $text = 'Special chars: !@#$%^&*()_+-=[]{}|;:"<>,.?/~`';
        $result = $this->estimator->estimate($this->model, $text);
        
        $this->assertIsInt($result);
        $this->assertGreaterThan(0, $result);
    }
    
    /**
     * Test estimation with Unicode text.
     */
    public function testEstimateWithUnicodeText(): void
    {
        $text = 'Unicode: 你好世界 مرحبا بالعالم こんにちは世界 🌍🌎🌏';
        $result = $this->estimator->estimate($this->model, $text);
        
        $this->assertIsInt($result);
        $this->assertGreaterThan(0, $result);
    }
    
    /**
     * Test estimation with code blocks.
     */
    public function testEstimateWithCodeBlocks(): void
    {
        $text = '```php
        function hello() {
            echo "Hello World";
        }
        ```';
        
        $result = $this->estimator->estimate($this->model, $text);
        
        $this->assertIsInt($result);
        $this->assertGreaterThan(5, $result, 'Code blocks should have reasonable token count');
    }
    
    /**
     * Test estimation with URLs.
     */
    public function testEstimateWithUrls(): void
    {
        $text = 'Visit https://example.com and https://github.com/user/repo for more info';
        $result = $this->estimator->estimate($this->model, $text);
        
        $this->assertIsInt($result);
        $this->assertGreaterThan(0, $result);
    }
    
    /**
     * Test estimation with JSON structure.
     */
    public function testEstimateWithJsonStructure(): void
    {
        $text = '{"key": "value", "array": [1, 2, 3], "nested": {"inner": "data"}}';
        $result = $this->estimator->estimate($this->model, $text);
        
        $this->assertIsInt($result);
        $this->assertGreaterThan(0, $result);
    }
    
    /**
     * Test estimation scales reasonably with text length.
     * 
     * @dataProvider textSamplesProvider
     */
    public function testEstimationScalesWithLength(string $text, int $minTokens, int $maxTokens): void
    {
        $result = $this->estimator->estimate($this->model, $text);
        
        $this->assertGreaterThanOrEqual(
            $minTokens,
            $result,
            sprintf('Token count should be at least %d for text length %d', $minTokens, strlen($text))
        );
        
        $this->assertLessThanOrEqual(
            $maxTokens,
            $result,
            sprintf('Token count should not exceed %d for text length %d', $maxTokens, strlen($text))
        );
    }
    
    /**
     * Provide text samples with expected token ranges.
     * 
     * @return array<string, array{string, int, int}>
     */
    public static function textSamplesProvider(): array
    {
        return [
            'single word' => ['Hello', 1, 10],
            'short sentence' => ['This is a test.', 3, 10],
            'medium paragraph' => [
                'The quick brown fox jumps over the lazy dog. This pangram contains all letters of the alphabet.',
                15, 40
            ],
            'long paragraph' => [
                str_repeat('This is a longer text sample. ', 20),
                80, 200
            ],
        ];
    }
    
    /**
     * Test that estimator implements TokenEstimatorInterface.
     */
    public function testImplementsTokenEstimatorInterface(): void
    {
        $this->assertInstanceOf(TokenEstimatorInterface::class, $this->estimator);
    }
}