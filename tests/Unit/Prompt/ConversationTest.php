<?php

declare(strict_types=1);

namespace Lingoda\AiSdk\Tests\Unit\Prompt;

use Lingoda\AiSdk\Exception\InvalidArgumentException;
use Lingoda\AiSdk\Prompt\AssistantPrompt;
use Lingoda\AiSdk\Prompt\Conversation;
use Lingoda\AiSdk\Prompt\SystemPrompt;
use Lingoda\AiSdk\Prompt\UserPrompt;
use Lingoda\AiSdk\Security\DataSanitizer;
use PHPUnit\Framework\TestCase;
// Load helper functions
require_once __DIR__ . '/../../../src/Prompt/functions.php';

use function Lingoda\AiSdk\Prompt\assistantPrompt;
use function Lingoda\AiSdk\Prompt\systemPrompt;
use function Lingoda\AiSdk\Prompt\userPrompt;

final class ConversationTest extends TestCase
{
    public function testCreateFromUserPrompt(): void
    {
        $conversation = Conversation::fromUser(UserPrompt::create('Hello, AI!'));
        
        $this->assertEquals('Hello, AI!', $conversation->getUserContent());
        $this->assertNull($conversation->getSystemPrompt());
        $this->assertNull($conversation->getAssistantPrompt());
    }
    
    public function testCreateWithSystem(): void
    {
        $conversation = Conversation::withSystem(
            UserPrompt::create('Help me'),
            SystemPrompt::create('You are helpful')
        );
        
        $this->assertEquals('Help me', $conversation->getUserContent());
        $this->assertEquals('You are helpful', $conversation->getSystemContent());
        $this->assertNull($conversation->getAssistantPrompt());
    }
    
    public function testCreateConversation(): void
    {
        $conversation = Conversation::conversation(
            UserPrompt::create('What about now?'),
            SystemPrompt::create('You are helpful'),
            AssistantPrompt::create('I helped before')
        );
        
        $this->assertEquals('What about now?', $conversation->getUserContent());
        $this->assertEquals('You are helpful', $conversation->getSystemContent());
        $this->assertEquals('I helped before', $conversation->getAssistantContent());
    }
    
    public function testFromArrayWithContent(): void
    {
        $conversation = Conversation::fromArray(['content' => 'Test message']);
        
        $this->assertEquals('Test message', $conversation->getUserContent());
    }
    
    public function testFromArrayWithMessages(): void
    {
        $data = [
            'messages' => [
                ['role' => 'system', 'content' => 'System prompt'],
                ['role' => 'user', 'content' => 'User message'],
                ['role' => 'assistant', 'content' => 'Assistant response'],
            ]
        ];
        
        $conversation = Conversation::fromArray($data);
        
        $this->assertEquals('User message', $conversation->getUserContent());
        $this->assertEquals('System prompt', $conversation->getSystemContent());
        $this->assertEquals('Assistant response', $conversation->getAssistantContent());
    }
    
    public function testFromArrayWithCommonFields(): void
    {
        $fields = ['prompt', 'query', 'question', 'text', 'input'];
        
        foreach ($fields as $field) {
            $conversation = Conversation::fromArray([$field => 'Test content']);
            $this->assertEquals('Test content', $conversation->getUserContent());
        }
    }
    
    public function testSanitization(): void
    {
        $conversation = Conversation::fromUser(UserPrompt::create('My email is john@example.com'));
        $sanitizer = DataSanitizer::createDefault();
        
        $sanitized = $conversation->sanitize($sanitizer);
        
        $this->assertEquals('My email is [REDACTED_EMAIL]', $sanitized->getUserContent());
        $this->assertTrue($sanitized->isSanitized());
        $this->assertFalse($conversation->isSanitized());
    }
    
    public function testSanitizationPreservesSystemAndAssistant(): void
    {
        $conversation = Conversation::conversation(
            UserPrompt::create('User email: admin@company.com'),
            SystemPrompt::create('System with email@test.com'),
            AssistantPrompt::create('Assistant with phone 555-1234')
        );
        
        $sanitizer = DataSanitizer::createDefault();
        $sanitized = $conversation->sanitize($sanitizer);
        
        // Only user prompt should be sanitized
        $this->assertEquals('User email: [REDACTED_EMAIL]', $sanitized->getUserContent());
        $this->assertEquals('System with email@test.com', $sanitized->getSystemContent());
        $this->assertEquals('Assistant with phone 555-1234', $sanitized->getAssistantContent());
    }
    
    public function testToMessagesArray(): void
    {
        $conversation = Conversation::conversation(
            UserPrompt::create('User message'),
            SystemPrompt::create('System message'),
            AssistantPrompt::create('Assistant message')
        );
        
        $messages = $conversation->toArray();
        
        $this->assertCount(3, $messages);
        $this->assertEquals(['role' => 'system', 'content' => 'System message'], $messages[0]);
        $this->assertEquals(['role' => 'assistant', 'content' => 'Assistant message'], $messages[1]);
        $this->assertEquals(['role' => 'user', 'content' => 'User message'], $messages[2]);
    }
    
    public function testToMessagesArrayWithOnlyUser(): void
    {
        $conversation = Conversation::fromUser(UserPrompt::create('Just user'));
        $messages = $conversation->toArray();
        
        $this->assertCount(1, $messages);
        $this->assertEquals(['role' => 'user', 'content' => 'Just user'], $messages[0]);
    }
    
    public function testGetFullContent(): void
    {
        $conversation = Conversation::conversation(
            UserPrompt::create('User part'),
            SystemPrompt::create('System part'),
            AssistantPrompt::create('Assistant part')
        );
        
        $expected = "System part\nAssistant part\nUser part";
        $this->assertEquals($expected, $conversation->getFullContent());
    }
    
    public function testEstimateTokens(): void
    {
        $conversation = Conversation::fromUser(UserPrompt::create('Test message for token estimation'));
        
        $estimator = fn(string $text) => (int) ceil(strlen($text) / 4);
        $tokens = $conversation->estimateTokens($estimator);
        
        $this->assertEquals(9, $tokens); // 34 chars / 4 = 8.5, ceil = 9
    }
    
    public function testEquals(): void
    {
        $conversation1 = Conversation::withSystem(
            UserPrompt::create('User'),
            SystemPrompt::create('System')
        );
        $conversation2 = Conversation::withSystem(
            UserPrompt::create('User'),
            SystemPrompt::create('System')
        );
        $conversation3 = Conversation::withSystem(
            UserPrompt::create('User'),
            SystemPrompt::create('Different')
        );
        
        $this->assertTrue($conversation1->equals($conversation2));
        $this->assertFalse($conversation1->equals($conversation3));
    }
    
    public function testHash(): void
    {
        $conversation1 = Conversation::withSystem(
            UserPrompt::create('User'),
            SystemPrompt::create('System')
        );
        $conversation2 = Conversation::withSystem(
            UserPrompt::create('User'),
            SystemPrompt::create('System')
        );
        $conversation3 = Conversation::withSystem(
            UserPrompt::create('User'),
            SystemPrompt::create('Different')
        );
        
        $this->assertEquals($conversation1->hash(), $conversation2->hash());
        $this->assertNotEquals($conversation1->hash(), $conversation3->hash());
    }
    
    public function testWithSystemPrompt(): void
    {
        $conversation = Conversation::fromUser(UserPrompt::create('User message'));
        $withSystem = $conversation->withSystemPrompt(SystemPrompt::create('New system'));
        
        $this->assertNull($conversation->getSystemPrompt());
        $this->assertEquals('New system', $withSystem->getSystemContent());
        $this->assertEquals('User message', $withSystem->getUserContent());
    }
    
    public function testWithAssistantPrompt(): void
    {
        $conversation = Conversation::fromUser(UserPrompt::create('User message'));
        $withAssistant = $conversation->withAssistantPrompt(AssistantPrompt::create('Assistant says'));
        
        $this->assertNull($conversation->getAssistantPrompt());
        $this->assertEquals('Assistant says', $withAssistant->getAssistantContent());
        $this->assertEquals('User message', $withAssistant->getUserContent());
    }
    
    public function testEmptyUserPromptThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Lingoda\AiSdk\Prompt\UserPrompt content cannot be empty');
        
        UserPrompt::create('');
    }
    
    public function testFromArrayWithoutUserThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('No user message found in messages array');
        
        Conversation::fromArray([
            'messages' => [
                ['role' => 'system', 'content' => 'Only system']
            ]
        ]);
    }
    
    public function testFromArrayWithInvalidDataThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unable to extract conversation from array data');
        
        Conversation::fromArray(['invalid' => 'data']);
    }
    
    public function testHelperFunctions(): void
    {
        // Test helper functions create correct Prompt types
        $conversation = Conversation::conversation(
            userPrompt('User text'),
            systemPrompt('System text'),
            assistantPrompt('Assistant text')
        );
        
        $this->assertEquals('User text', $conversation->getUserContent());
        $this->assertEquals('System text', $conversation->getSystemContent());
        $this->assertEquals('Assistant text', $conversation->getAssistantContent());
        
        // Test the objects are correct types
        $this->assertInstanceOf(UserPrompt::class, $conversation->getUserPrompt());
        $this->assertInstanceOf(SystemPrompt::class, $conversation->getSystemPrompt());
        $this->assertInstanceOf(AssistantPrompt::class, $conversation->getAssistantPrompt());
    }

    public function testToString(): void
    {
        $conversation = Conversation::withSystem(
            UserPrompt::create('User message'),
            SystemPrompt::create('System message')
        );
        
        $toString = $conversation->toString();
        $this->assertEquals('User message', $toString);
        
        // toString only returns user content, not system content
        $this->assertStringContainsString('User message', $toString);
        $this->assertStringNotContainsString('System message', $toString);
    }

    public function testIsSanitizedDefault(): void
    {
        $conversation = Conversation::fromUser(UserPrompt::create('Test message'));
        
        $this->assertFalse($conversation->isSanitized());
    }

    public function testGetAssistantContentWithoutAssistant(): void
    {
        $conversation = Conversation::fromUser(UserPrompt::create('User message'));
        
        $this->assertNull($conversation->getAssistantContent());
        $this->assertNull($conversation->getAssistantPrompt());
    }

    public function testGetSystemContentWithoutSystem(): void
    {
        $conversation = Conversation::fromUser(UserPrompt::create('User message'));
        
        $this->assertNull($conversation->getSystemContent());
        $this->assertNull($conversation->getSystemPrompt());
    }

    public function testHashConsistency(): void
    {
        $conversation1 = Conversation::fromUser(UserPrompt::create('Test message'));
        $conversation2 = Conversation::fromUser(UserPrompt::create('Test message'));
        
        $this->assertEquals($conversation1->hash(), $conversation2->hash());
    }
    
    public function testHashDifference(): void
    {
        $conversation1 = Conversation::fromUser(UserPrompt::create('Message 1'));
        $conversation2 = Conversation::fromUser(UserPrompt::create('Message 2'));
        
        $this->assertNotEquals($conversation1->hash(), $conversation2->hash());
    }

    public function testFromArrayWithDifferentUserFields(): void
    {
        // Test the various user field names
        $fields = ['prompt', 'query', 'question', 'text', 'input', 'user'];
        
        foreach ($fields as $field) {
            $data = [$field => 'Test message'];
            $conversation = Conversation::fromArray($data);
            $this->assertEquals('Test message', $conversation->getUserContent());
        }
    }

    public function testFromArrayWithSystemAndAssistantFields(): void
    {
        $data = [
            'prompt' => 'User message',
            'system' => 'System instructions',
            'assistant' => 'Assistant response'
        ];
        
        $conversation = Conversation::fromArray($data);
        
        $this->assertEquals('User message', $conversation->getUserContent());
        $this->assertEquals('System instructions', $conversation->getSystemContent());
        $this->assertEquals('Assistant response', $conversation->getAssistantContent());
    }

    public function testFromArrayWithInvalidMessages(): void
    {
        // Test with messages array containing invalid entries that should be skipped
        $data = [
            'messages' => [
                'invalid_string', // Not an array - should be skipped
                ['incomplete' => 'message'], // Missing role/content - should be skipped
                ['role' => 'user'], // Missing content - should be skipped
                ['content' => 'Missing role'], // Missing role - should be skipped
                ['role' => 'user', 'content' => 'Valid user message'], // Valid - should be used
                ['role' => 'system', 'content' => 'Valid system message'], // Valid - should be used
            ]
        ];
        
        $conversation = Conversation::fromArray($data);
        
        $this->assertEquals('Valid user message', $conversation->getUserContent());
        $this->assertEquals('Valid system message', $conversation->getSystemContent());
    }
}