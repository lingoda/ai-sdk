<?php

declare(strict_types=1);

namespace Lingoda\AiSdk\Tests\Unit\RateLimit;

use Lingoda\AiSdk\ModelInterface;
use Lingoda\AiSdk\RateLimit\GeminiTokenEstimator;
use Lingoda\AiSdk\RateLimit\TokenEstimatorInterface;
use PHPUnit\Framework\MockObject\MockObject;

final class GeminiTokenEstimatorTest extends TokenEstimatorTestCase
{
    protected function createEstimator(): TokenEstimatorInterface
    {
        return new GeminiTokenEstimator();
    }
    
    protected function getProviderSpecificPayload(): array
    {
        return [
            'contents' => [
                [
                    'parts' => [
                        ['text' => 'Hello world'],
                        ['text' => 'How are you today?']
                    ]
                ],
                [
                    'parts' => [
                        ['text' => 'I am fine, thanks!']
                    ]
                ]
            ]
        ];
    }
    
    protected function getProviderId(): string
    {
        return 'gemini';
    }

    public function testEstimateFromGeminiContents(): void
    {
        $payload = [
            'contents' => [
                [
                    'parts' => [
                        ['text' => 'Hello world'],
                        ['text' => 'How are you today?']
                    ]
                ],
                [
                    'parts' => [
                        ['text' => 'I am fine, thanks!']
                    ]
                ]
            ]
        ];
        
        $result = $this->estimator->estimate($this->model, $payload);
        
        $this->assertGreaterThan(0, $result);
        // Should be greater than single part due to multiple parts
        $singleResult = $this->estimator->estimate($this->model, [
            'contents' => [
                ['parts' => [['text' => 'Hello']]]
            ]
        ]);
        $this->assertGreaterThan($singleResult, $result);
    }

    public function testEstimateHandlesNonTextParts(): void
    {
        $payload = [
            'contents' => [
                [
                    'parts' => [
                        ['image' => 'base64data'], // Non-text part
                        ['text' => 'Valid text content'],
                        ['unknown' => 'field'], // No text field
                    ]
                ]
            ]
        ];
        
        $result = $this->estimator->estimate($this->model, $payload);
        
        // Should still work and only count the valid text content
        $this->assertGreaterThan(0, $result);
    }

    public function testEstimateFromInvalidStructure(): void
    {
        $payload = [
            'contents' => [
                'invalid-content', // Not an array
                [
                    'invalid' => 'structure' // Missing parts
                ],
                [
                    'parts' => 'invalid-parts' // Parts not an array
                ]
            ]
        ];
        
        $result = $this->estimator->estimate($this->model, $payload);
        
        $this->assertGreaterThanOrEqual(1, $result);
    }


    public function testEstimateFromEmptyContents(): void
    {
        $payload = [
            'contents' => []
        ];
        
        $result = $this->estimator->estimate($this->model, $payload);
        
        $this->assertEquals(1, $result);
    }

    public function testEstimateFromEmptyParts(): void
    {
        $payload = [
            'contents' => [
                ['parts' => []]
            ]
        ];
        
        $result = $this->estimator->estimate($this->model, $payload);
        
        $this->assertEquals(1, $result);
    }

    public function testEstimateMultipleContentBlocks(): void
    {
        $payload = [
            'contents' => [
                [
                    'parts' => [
                        ['text' => 'First block content']
                    ]
                ],
                [
                    'parts' => [
                        ['text' => 'Second block content']
                    ]
                ],
                [
                    'parts' => [
                        ['text' => 'Third block content']
                    ]
                ]
            ]
        ];
        
        $result = $this->estimator->estimate($this->model, $payload);
        
        // Should be significantly higher than single block
        $singleBlockResult = $this->estimator->estimate($this->model, [
            'contents' => [
                ['parts' => [['text' => 'Single content']]]
            ]
        ]);
        
        $this->assertGreaterThan($singleBlockResult, $result);
    }

    public function testEstimateWithSystemInstruction(): void
    {
        $payload = [
            'systemInstruction' => 'You are a helpful assistant.',
            'contents' => [
                [
                    'parts' => [
                        ['text' => 'Hello, how can I help?']
                    ]
                ]
            ]
        ];
        
        $result = $this->estimator->estimate($this->model, $payload);
        
        $this->assertGreaterThan(0, $result);
        
        // Should be higher than without system instruction
        $withoutSystemResult = $this->estimator->estimate($this->model, [
            'contents' => [
                ['parts' => [['text' => 'Hello, how can I help?']]]
            ]
        ]);
        
        $this->assertGreaterThan($withoutSystemResult, $result);
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

    public function testProviderSpecificAdjustments(): void
    {
        // Use a standardized text to test that Gemini adjustments are being applied
        $testText = 'This is a test message with multiple sentences. It contains various words and characters! Does it work correctly?';
        
        $result = $this->estimator->estimate($this->model, $testText);
        
        // Should use Gemini-specific adjustments (more efficient than default)
        $this->assertGreaterThan(0, $result);
        $this->assertIsInt($result);
        
        // Verify it's reasonably sized for the input
        $this->assertGreaterThan(5, $result);  // Should be more than minimal
        $this->assertLessThan(100, $result);   // But not excessive for this text
    }
}