<?php

declare(strict_types=1);

namespace Lingoda\AiSdk\Tests\Unit\RateLimit;

use Lingoda\AiSdk\ModelInterface;
use Lingoda\AiSdk\ProviderInterface;
use Lingoda\AiSdk\RateLimit\TokenEstimator;
use PHPUnit\Framework\TestCase;

final class TokenEstimatorTest extends TestCase
{
    private TokenEstimator $estimator;
    private ModelInterface $model;

    protected function setUp(): void
    {
        $this->estimator = new TokenEstimator();
        
        // Create mock model
        $provider = $this->createMock(ProviderInterface::class);
        $provider->method('getId')->willReturn('openai');
        
        $this->model = $this->createMock(ModelInterface::class);
        $this->model->method('getProvider')->willReturn($provider);
    }

    public function testEstimateFromSimpleString(): void
    {
        $result = $this->estimator->estimate($this->model, 'Hello world');
        
        $this->assertGreaterThan(0, $result);
        $this->assertIsInt($result);
    }

    public function testEstimateFromOpenAIStylePayload(): void
    {
        $payload = [
            'messages' => [
                ['role' => 'user', 'content' => 'Hello world'],
                ['role' => 'assistant', 'content' => 'Hi there!'],
            ]
        ];
        
        $result = $this->estimator->estimate($this->model, $payload);
        
        $this->assertGreaterThan(0, $result);
    }

    public function testEstimateFromGeminiStylePayload(): void
    {
        $payload = [
            'contents' => [
                [
                    'parts' => [
                        ['text' => 'Hello world']
                    ]
                ]
            ]
        ];
        
        $result = $this->estimator->estimate($this->model, $payload);
        
        $this->assertGreaterThan(0, $result);
    }

    public function testEstimateFromAnthropicStylePayload(): void
    {
        $payload = [
            'content' => [
                ['type' => 'text', 'text' => 'Hello world']
            ]
        ];
        
        $result = $this->estimator->estimate($this->model, $payload);
        
        $this->assertGreaterThan(0, $result);
    }

    public function testEmptyPayloadReturnsMinimum(): void
    {
        $result = $this->estimator->estimate($this->model, '');
        
        $this->assertEquals(1, $result);
    }

    public function testLongerTextGivesHigherEstimate(): void
    {
        $shortText = 'Hello';
        $longText = 'Hello world, this is a much longer text that should result in a higher token count estimate because it contains more words and characters.';
        
        $shortEstimate = $this->estimator->estimate($this->model, $shortText);
        $longEstimate = $this->estimator->estimate($this->model, $longText);
        
        $this->assertGreaterThan($shortEstimate, $longEstimate);
    }
    
    public function testExtractTextFromCommonFields(): void
    {
        // Test the fallback behavior when no specific estimator pattern matches
        $payload = [
            'content' => 'Content field text',
            'text' => 'Text field content',
            'message' => 'Message field data',
            'prompt' => 'Prompt field information',
            'system' => 'System message',
            'user' => 'User input',
            'assistant' => 'Assistant response'
        ];
        
        $result = $this->estimator->estimate($this->model, $payload);
        
        $this->assertGreaterThan(0, $result);
        
        // Should be significantly higher than empty payload
        $emptyResult = $this->estimator->estimate($this->model, []);
        $this->assertGreaterThan($emptyResult, $result);
    }
    
    public function testExtractTextFromArrayValues(): void
    {
        $payload = [
            'title' => 'Document title',
            'body' => 'Document body content',
            'tags' => ['tag1', 'tag2', 'tag3'], // Array of strings should be extracted
            'metadata' => [
                'author' => 'John Doe',
                'version' => 1.0
            ]
        ];
        
        $result = $this->estimator->estimate($this->model, $payload);
        
        $this->assertGreaterThan(0, $result);
    }
    
    public function testExtractTextTrimsWhitespace(): void
    {
        $payload = [
            'content' => '  Leading and trailing spaces  ',
            'text' => '\t\nTabbed and newlined content\t\n'
        ];
        
        $result = $this->estimator->estimate($this->model, $payload);
        
        $this->assertGreaterThan(0, $result);
        
        // Test that estimation handles whitespace properly
        $trimmedPayload = [
            'content' => 'Leading and trailing spaces',
            'text' => 'Tabbed and newlined content'
        ];
        $trimmedResult = $this->estimator->estimate($this->model, $trimmedPayload);
        
        // Both should be positive, specific values depend on tokenizer implementation
        $this->assertGreaterThan(0, $trimmedResult);
        $this->assertIsInt($trimmedResult);
    }
}