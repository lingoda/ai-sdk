<?php

declare(strict_types=1);

namespace Lingoda\AiSdk\Tests\Unit\RateLimit;

use Lingoda\AiSdk\ModelInterface;
use Lingoda\AiSdk\RateLimit\AnthropicTokenEstimator;
use Lingoda\AiSdk\RateLimit\TokenEstimatorInterface;
use PHPUnit\Framework\MockObject\MockObject;

final class AnthropicTokenEstimatorTest extends TokenEstimatorTestCase
{
    protected function createEstimator(): TokenEstimatorInterface
    {
        return new AnthropicTokenEstimator();
    }
    
    protected function getProviderSpecificPayload(): array
    {
        return [
            'messages' => [
                [
                    'content' => [
                        ['type' => 'text', 'text' => 'Hello world']
                    ]
                ],
                [
                    'content' => [
                        ['type' => 'text', 'text' => 'How are you today?']
                    ]
                ]
            ]
        ];
    }
    
    protected function getProviderId(): string
    {
        return 'anthropic';
    }

    public function testEstimateFromAnthropicContent(): void
    {
        $payload = [
            'content' => [
                ['type' => 'text', 'text' => 'Hello world'],
                ['type' => 'text', 'text' => 'How are you today?'],
            ]
        ];
        
        $result = $this->estimator->estimate($this->model, $payload);
        
        $this->assertGreaterThan(0, $result);
        // Should be greater than single text due to multiple content blocks
        $singleResult = $this->estimator->estimate($this->model, ['content' => [['type' => 'text', 'text' => 'Hello']]]);
        $this->assertGreaterThan($singleResult, $result);
    }

    public function testEstimateFromAnthropicMessages(): void
    {
        $payload = [
            'messages' => [
                [
                    'content' => [
                        ['type' => 'text', 'text' => 'Hello world']
                    ]
                ],
                [
                    'content' => [
                        ['type' => 'text', 'text' => 'Hi there!']
                    ]
                ]
            ]
        ];
        
        $result = $this->estimator->estimate($this->model, $payload);
        
        $this->assertGreaterThan(0, $result);
    }

    public function testEstimateHandlesNonTextContent(): void
    {
        $payload = [
            'content' => [
                ['type' => 'image', 'url' => 'https://example.com/image.jpg'], // Non-text content
                ['type' => 'text', 'text' => 'Valid text content'],
                ['type' => 'unknown'], // Missing text field
            ]
        ];
        
        $result = $this->estimator->estimate($this->model, $payload);
        
        // Should still work and only count the valid text content
        $this->assertGreaterThan(0, $result);
    }

    public function testEstimateFromMixedPayload(): void
    {
        $payload = [
            'content' => [
                ['type' => 'text', 'text' => 'Direct content']
            ],
            'messages' => [
                [
                    'content' => [
                        ['type' => 'text', 'text' => 'Message content']
                    ]
                ]
            ]
        ];
        
        $result = $this->estimator->estimate($this->model, $payload);
        
        $this->assertGreaterThan(0, $result);
    }


    public function testEstimateHandlesInvalidContent(): void
    {
        $payload = [
            'content' => [
                'invalid-content', // Not an array
                ['invalid' => 'structure'], // Missing type and text
            ]
        ];
        
        $result = $this->estimator->estimate($this->model, $payload);
        
        $this->assertGreaterThanOrEqual(1, $result);
    }

    public function testEstimateFromNestedMessages(): void
    {
        $payload = [
            'messages' => [
                [
                    'content' => [
                        ['type' => 'text', 'text' => 'First message']
                    ]
                ],
                [
                    'content' => 'Direct string content' // Mixed content types
                ]
            ]
        ];
        
        $result = $this->estimator->estimate($this->model, $payload);
        
        $this->assertGreaterThan(0, $result);
    }

    public function testEstimateWithRoleBasedPayload(): void
    {
        $payload = [
            'user' => 'This is a user message',
            'assistant' => 'This is an assistant response',
            'system' => 'This is a system prompt'
        ];
        
        $result = $this->estimator->estimate($this->model, $payload);
        
        $this->assertGreaterThan(0, $result);
        
        // Test individual role fields
        $userResult = $this->estimator->estimate($this->model, ['user' => 'This is a user message']);
        $this->assertGreaterThan(0, $userResult);
        $this->assertLessThan($result, $userResult);
    }

    public function testEstimateWithSimpleStringContent(): void
    {
        $payload = [
            'content' => 'Simple string content for Anthropic'
        ];
        
        $result = $this->estimator->estimate($this->model, $payload);
        
        $this->assertGreaterThan(0, $result);
    }

    public function testProviderSpecificAdjustments(): void
    {
        // Use a standardized text to test that Anthropic adjustments are being applied
        $testText = 'This is a test message with multiple sentences. It contains various words and characters! Does it work correctly?';
        
        $result = $this->estimator->estimate($this->model, $testText);
        
        // Should use Anthropic-specific adjustments (more efficient than default)
        $this->assertGreaterThan(0, $result);
        $this->assertIsInt($result);
        
        // Verify it's reasonably sized for the input
        $this->assertGreaterThan(5, $result);  // Should be more than minimal
        $this->assertLessThan(100, $result);   // But not excessive for this text
    }
}