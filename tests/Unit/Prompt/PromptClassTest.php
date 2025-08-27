<?php

declare(strict_types=1);

namespace Lingoda\AiSdk\Tests\Unit\Prompt;

use Lingoda\AiSdk\Prompt\AssistantPrompt;
use Lingoda\AiSdk\Prompt\Role;
use Lingoda\AiSdk\Prompt\SystemPrompt;
use Lingoda\AiSdk\Prompt\UserPrompt;
use PHPUnit\Framework\TestCase;

final class PromptClassTest extends TestCase
{
    public function testUserPromptCreation(): void
    {
        $prompt = UserPrompt::create('Hello world');
        
        $this->assertEquals('Hello world', $prompt->getContent());
        $this->assertEquals(Role::USER, $prompt->getRole());
        $this->assertEquals('Hello world', $prompt->toString());
        $this->assertEquals(['role' => 'user', 'content' => 'Hello world'], $prompt->toArray());
    }
    
    public function testSystemPromptCreation(): void
    {
        $prompt = SystemPrompt::create('You are helpful');
        
        $this->assertEquals('You are helpful', $prompt->getContent());
        $this->assertEquals(Role::SYSTEM, $prompt->getRole());
        $this->assertEquals('You are helpful', $prompt->toString());
        $this->assertEquals(['role' => 'system', 'content' => 'You are helpful'], $prompt->toArray());
    }
    
    public function testAssistantPromptCreation(): void
    {
        $prompt = AssistantPrompt::create('I can help you');
        
        $this->assertEquals('I can help you', $prompt->getContent());
        $this->assertEquals(Role::ASSISTANT, $prompt->getRole());
        $this->assertEquals('I can help you', $prompt->toString());
        $this->assertEquals(['role' => 'assistant', 'content' => 'I can help you'], $prompt->toArray());
    }
    
    public function testPromptEquality(): void
    {
        $prompt1 = UserPrompt::create('Same content');
        $prompt2 = UserPrompt::create('Same content');
        $prompt3 = UserPrompt::create('Different content');
        $prompt4 = SystemPrompt::create('Same content');
        
        $this->assertTrue($prompt1->equals($prompt2));
        $this->assertFalse($prompt1->equals($prompt3));
        $this->assertFalse($prompt1->equals($prompt4)); // Different types
    }
    
    public function testPromptHash(): void
    {
        $prompt1 = UserPrompt::create('Content');
        $prompt2 = UserPrompt::create('Content');
        $prompt3 = SystemPrompt::create('Content');
        
        $this->assertEquals($prompt1->hash(), $prompt2->hash());
        $this->assertNotEquals($prompt1->hash(), $prompt3->hash());
    }
    
    public function testEmptyUserPromptThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Lingoda\AiSdk\Prompt\UserPrompt content cannot be empty');
        
        UserPrompt::create('');
    }
    
    public function testEmptySystemPromptThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Lingoda\AiSdk\Prompt\SystemPrompt content cannot be empty');
        
        SystemPrompt::create('');
    }
    
    public function testEmptyAssistantPromptThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Lingoda\AiSdk\Prompt\AssistantPrompt content cannot be empty');
        
        AssistantPrompt::create('');
    }

    public function testToArrayFormat(): void
    {
        $userPrompt = UserPrompt::create('User message');
        $systemPrompt = SystemPrompt::create('System message');
        $assistantPrompt = AssistantPrompt::create('Assistant message');

        $this->assertEquals([
            'role' => 'user',
            'content' => 'User message'
        ], $userPrompt->toArray());

        $this->assertEquals([
            'role' => 'system',
            'content' => 'System message'
        ], $systemPrompt->toArray());

        $this->assertEquals([
            'role' => 'assistant',
            'content' => 'Assistant message'
        ], $assistantPrompt->toArray());
    }

    public function testStringableInterface(): void
    {
        $prompt = UserPrompt::create('Test message');
        
        $this->assertEquals('Test message', (string) $prompt);
        $this->assertEquals('Test message', $prompt->__toString());
    }

    public function testPromptInequalityWithDifferentTypes(): void
    {
        $userPrompt = UserPrompt::create('Same content');
        $systemPrompt = SystemPrompt::create('Same content');
        
        $this->assertFalse($userPrompt->equals($systemPrompt));
    }
    
    public function testHashDifferenceWithDifferentTypes(): void
    {
        $userPrompt = UserPrompt::create('Same content');
        $systemPrompt = SystemPrompt::create('Same content');
        
        $this->assertNotEquals($userPrompt->hash(), $systemPrompt->hash());
    }
}