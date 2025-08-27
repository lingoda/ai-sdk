<?php

declare(strict_types=1);

namespace Lingoda\AiSdk\Tests\Unit\RateLimit;

use Lingoda\AiSdk\ModelInterface;
use Lingoda\AiSdk\RateLimit\OpenAITokenEstimator;
use Lingoda\AiSdk\RateLimit\TokenEstimatorInterface;
use PHPUnit\Framework\MockObject\MockObject;

final class OpenAITokenEstimatorTest extends TokenEstimatorTestCase
{
    protected function createEstimator(): TokenEstimatorInterface
    {
        return new OpenAITokenEstimator();
    }
    
    protected function getProviderSpecificPayload(): array
    {
        return [
            'messages' => [
                ['role' => 'system', 'content' => 'You are a helpful assistant'],
                ['role' => 'user', 'content' => 'Hello world'],
                ['role' => 'assistant', 'content' => 'Hi there! How can I help you today?'],
            ]
        ];
    }
    
    protected function getProviderId(): string
    {
        return 'openai';
    }

    public function testEstimateFromOpenAIMessages(): void
    {
        $payload = [
            'messages' => [
                ['role' => 'user', 'content' => 'Hello world'],
                ['role' => 'assistant', 'content' => 'Hi there! How can I help you today?'],
                ['role' => 'system', 'content' => 'You are a helpful assistant'],
            ]
        ];
        
        $result = $this->estimator->estimate($this->model, $payload);
        
        $this->assertGreaterThan(0, $result);
        // Should be greater than single message due to multiple messages
        $singleResult = $this->estimator->estimate($this->model, ['messages' => [['role' => 'user', 'content' => 'Hello']]]);
        $this->assertGreaterThan($singleResult, $result);
    }

    public function testEstimateFromSystemPrompt(): void
    {
        $payload = [
            'system' => 'You are a helpful assistant that provides detailed explanations',
            'messages' => [
                ['role' => 'user', 'content' => 'Hello']
            ]
        ];
        
        $result = $this->estimator->estimate($this->model, $payload);
        
        // Should be greater than without system prompt
        $withoutSystemResult = $this->estimator->estimate($this->model, ['messages' => [['role' => 'user', 'content' => 'Hello']]]);
        $this->assertGreaterThan($withoutSystemResult, $result);
    }

    public function testEstimateFromDirectRoleKeys(): void
    {
        $payload = [
            'user' => 'What is machine learning?',
            'assistant' => 'Machine learning is a subset of artificial intelligence',
            'system' => 'You are an AI expert'
        ];
        
        $result = $this->estimator->estimate($this->model, $payload);
        
        $this->assertGreaterThan(0, $result);
    }

    public function testEstimateFromMixedPayload(): void
    {
        $payload = [
            'system' => 'You are helpful',
            'messages' => [
                ['role' => 'user', 'content' => 'Hello'],
            ],
            'user' => 'Additional user content'
        ];
        
        $result = $this->estimator->estimate($this->model, $payload);
        
        $this->assertGreaterThan(0, $result);
    }

    public function testEstimateHandlesNonStringContent(): void
    {
        $payload = [
            'messages' => [
                ['role' => 'user', 'content' => 123], // Non-string content
                ['role' => 'assistant'], // Missing content
                ['role' => 'system', 'content' => 'Valid content'],
            ]
        ];
        
        $result = $this->estimator->estimate($this->model, $payload);
        
        // Should still work and only count the valid string content
        $this->assertGreaterThan(0, $result);
    }

    public function testEstimateFromInvalidMessages(): void
    {
        $payload = [
            'messages' => [
                'invalid-message', // Not an array
                ['role' => 'user'], // Missing content
                ['content' => 'Missing role'], // Missing role
            ]
        ];
        
        $result = $this->estimator->estimate($this->model, $payload);
        
        // Should return minimum since no valid content, but might be higher due to base estimation
        $this->assertGreaterThanOrEqual(1, $result);
    }


    public function testEstimateFromNonStringRoleValues(): void
    {
        $payload = [
            'user' => 123, // Non-string
            'assistant' => ['not', 'string'], // Array
            'system' => 'Valid system prompt'
        ];
        
        $result = $this->estimator->estimate($this->model, $payload);
        
        // Should only count the valid system prompt
        $this->assertGreaterThan(0, $result);
    }

    public function testTrimsWhitespace(): void
    {
        $payload = [
            'messages' => [
                ['role' => 'user', 'content' => '   Hello world   '],
                ['role' => 'assistant', 'content' => '   Hi there!   '],
            ]
        ];
        
        $result = $this->estimator->estimate($this->model, $payload);
        
        // Trimming should work correctly
        $this->assertGreaterThan(0, $result);
    }

    public function testCombinesMultipleTextSources(): void
    {
        $payload = [
            'system' => 'System instruction',
            'messages' => [
                ['role' => 'user', 'content' => 'User message'],
            ],
            'assistant' => 'Assistant message',
            'user' => 'Additional user content'
        ];
        
        $result = $this->estimator->estimate($this->model, $payload);
        
        // Should be higher than individual parts
        $systemOnly = $this->estimator->estimate($this->model, ['system' => 'System instruction']);
        $userOnly = $this->estimator->estimate($this->model, ['user' => 'User message']);
        
        $this->assertGreaterThan($systemOnly, $result);
        $this->assertGreaterThan($userOnly, $result);
    }
}