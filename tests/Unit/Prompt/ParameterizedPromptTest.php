<?php

declare(strict_types=1);

namespace Lingoda\AiSdk\Tests\Unit\Prompt;

use Lingoda\AiSdk\Prompt\AssistantPrompt;
use Lingoda\AiSdk\Prompt\Role;
use Lingoda\AiSdk\Prompt\SystemPrompt;
use Lingoda\AiSdk\Prompt\UserPrompt;
use PHPUnit\Framework\TestCase;

final class ParameterizedPromptTest extends TestCase
{
    public function testBasicParameterReplacement(): void
    {
        $prompt = UserPrompt::create('Hello, {{name}}!');
        $result = $prompt->withParameters(['name' => 'John']);
        
        $this->assertEquals('Hello, John!', $result->getContent());
    }

    public function testMultipleParameterReplacement(): void
    {
        $prompt = UserPrompt::create('{{greeting}}, {{name}}! Your balance is {{balance}}.');
        $result = $prompt->withParameters([
            'greeting' => 'Hello',
            'name' => 'John',
            'balance' => '$100'
        ]);
        
        $this->assertEquals('Hello, John! Your balance is $100.', $result->getContent());
    }

    public function testParameterReplacementWithWhitespace(): void
    {
        $prompt = UserPrompt::create('Hello, {{  name  }}! Welcome to {{ platform }}.');
        $result = $prompt->withParameters([
            'name' => 'John',
            'platform' => 'AI SDK'
        ]);
        
        $this->assertEquals('Hello, John! Welcome to AI SDK.', $result->getContent());
    }

    public function testMissingParametersKeepPlaceholder(): void
    {
        $prompt = UserPrompt::create('Hello, {{name}}! Welcome to {{platform}}.');
        $result = $prompt->withParameters(['name' => 'John']);
        
        $this->assertEquals('Hello, John! Welcome to {{platform}}.', $result->getContent());
    }

    public function testEmptyParametersReturnsSameInstance(): void
    {
        $prompt = UserPrompt::create('Hello, {{name}}!');
        $result = $prompt->withParameters([]);
        
        $this->assertSame($prompt, $result);
    }

    public function testNoParametersReturnsSameInstance(): void
    {
        $prompt = UserPrompt::create('Hello, world!');
        $result = $prompt->withParameters(['name' => 'John']);
        
        // Since the prompt has no parameters, it should return the same instance
        $this->assertSame($prompt, $result);
        $this->assertEquals('Hello, world!', $result->getContent());
    }

    public function testGetParameterNames(): void
    {
        $prompt = UserPrompt::create('As a {{criticlevel}} movie critic, do you like {{movie}}?');
        $parameterNames = $prompt->getParameterNames();
        
        $this->assertEquals(['criticlevel', 'movie'], $parameterNames);
    }

    public function testGetParameterNamesWithDuplicates(): void
    {
        $prompt = UserPrompt::create('Hello {{name}}, nice to meet you {{name}}!');
        $parameterNames = $prompt->getParameterNames();
        
        $this->assertEquals(['name', 'name'], $parameterNames);
    }

    public function testGetParameterNamesEmpty(): void
    {
        $prompt = UserPrompt::create('This has no parameters.');
        $parameterNames = $prompt->getParameterNames();
        
        $this->assertEquals([], $parameterNames);
    }

    public function testHasParametersTrue(): void
    {
        $prompt = UserPrompt::create('Hello, {{name}}!');
        
        $this->assertTrue($prompt->hasParameters());
    }

    public function testHasParametersFalse(): void
    {
        $prompt = UserPrompt::create('Hello, world!');
        
        $this->assertFalse($prompt->hasParameters());
    }

    public function testNullParameterValue(): void
    {
        $prompt = UserPrompt::create('Hello, {{name}}! Your value is {{value}}.');
        $result = $prompt->withParameters([
            'name' => 'John',
            'value' => null
        ]);
        
        $this->assertEquals('Hello, John! Your value is .', $result->getContent());
    }

    public function testNumericParameterValues(): void
    {
        $prompt = UserPrompt::create('Score: {{score}}, Count: {{count}}.');
        $result = $prompt->withParameters([
            'score' => 95.5,
            'count' => 10
        ]);
        
        $this->assertEquals('Score: 95.5, Count: 10.', $result->getContent());
    }

    public function testBooleanParameterValues(): void
    {
        $prompt = UserPrompt::create('Active: {{active}}, Enabled: {{enabled}}.');
        $result = $prompt->withParameters([
            'active' => true,
            'enabled' => false
        ]);
        
        $this->assertEquals('Active: true, Enabled: false.', $result->getContent());
    }

    public function testArrayParameterValue(): void
    {
        $prompt = UserPrompt::create('Config: {{config}}');
        $result = $prompt->withParameters([
            'config' => ['key' => 'value', 'enabled' => true]
        ]);
        
        $this->assertEquals('Config: {"key":"value","enabled":true}', $result->getContent());
    }

    public function testObjectParameterWithToString(): void
    {
        $object = new class {
            public function __toString(): string
            {
                return 'custom object';
            }
        };
        
        $prompt = UserPrompt::create('Object: {{obj}}');
        $result = $prompt->withParameters(['obj' => $object]);
        
        $this->assertEquals('Object: custom object', $result->getContent());
    }

    public function testUnmatchedOpeningTag(): void
    {
        $prompt = UserPrompt::create('Hello, {{name! Your balance is $100.');
        $result = $prompt->withParameters(['name' => 'John']);
        
        $this->assertEquals('Hello, {{name! Your balance is $100.', $result->getContent());
    }

    public function testUnmatchedClosingTag(): void
    {
        $prompt = UserPrompt::create('Hello, name}}! Your balance is $100.');
        $result = $prompt->withParameters(['name' => 'John']);
        
        $this->assertEquals('Hello, name}}! Your balance is $100.', $result->getContent());
    }

    public function testComplexTemplate(): void
    {
        $template = 'Dear {{title}} {{lastName}}, your order #{{orderId}} for {{amount}} items has been {{status}}.';
        $prompt = UserPrompt::create($template);
        
        $result = $prompt->withParameters([
            'title' => 'Mr.',
            'lastName' => 'Smith',
            'orderId' => '12345',
            'amount' => '3',
            'status' => 'shipped'
        ]);
        
        $expected = 'Dear Mr. Smith, your order #12345 for 3 items has been shipped.';
        $this->assertEquals($expected, $result->getContent());
    }

    public function testConsecutiveVariables(): void
    {
        $prompt = UserPrompt::create('{{first}}{{second}}{{third}}');
        $result = $prompt->withParameters([
            'first' => '1',
            'second' => '2',
            'third' => '3'
        ]);
        
        $this->assertEquals('123', $result->getContent());
    }

    public function testWorksWithSystemPrompt(): void
    {
        $prompt = SystemPrompt::create('You are a {{role}} assistant. Help with {{task}}.');
        $result = $prompt->withParameters([
            'role' => 'helpful',
            'task' => 'coding'
        ]);
        
        $this->assertEquals('You are a helpful assistant. Help with coding.', $result->getContent());
        $this->assertEquals(Role::SYSTEM, $result->getRole());
    }

    public function testParameterReplacementPreservesPromptType(): void
    {
        $userPrompt = UserPrompt::create('Hello, {{name}}!');
        $systemPrompt = SystemPrompt::create('You are {{role}}.');
        
        $userResult = $userPrompt->withParameters(['name' => 'John']);
        $systemResult = $systemPrompt->withParameters(['role' => 'assistant']);
        
        $this->assertInstanceOf(UserPrompt::class, $userResult);
        $this->assertInstanceOf(SystemPrompt::class, $systemResult);
        $this->assertEquals(Role::USER, $userResult->getRole());
        $this->assertEquals(Role::SYSTEM, $systemResult->getRole());
    }

    public function testNestedBraces(): void
    {
        $prompt = UserPrompt::create('Config: {{config}}');
        $result = $prompt->withParameters([
            'config' => '{"key": "value"}'
        ]);
        
        $this->assertEquals('Config: {"key": "value"}', $result->getContent());
    }

    public function testEmptyParameterValue(): void
    {
        $prompt = UserPrompt::create('Content: {{empty}} end');
        $result = $prompt->withParameters(['empty' => '']);
        
        $this->assertEquals('Content:  end', $result->getContent());
    }

    public function testConstructorWithParameters(): void
    {
        $prompt = UserPrompt::create('Hello, {{name}}! Welcome to {{platform}}.', [
            'name' => 'Alice',
            'platform' => 'AI SDK'
        ]);
        
        $this->assertEquals('Hello, Alice! Welcome to AI SDK.', $prompt->getContent());
    }

    public function testConstructorWithEmptyParameters(): void
    {
        $prompt = UserPrompt::create('Hello, {{name}}!', []);
        
        $this->assertEquals('Hello, {{name}}!', $prompt->getContent());
    }

    public function testConstructorWithoutParameters(): void
    {
        $prompt = UserPrompt::create('Hello, world!');
        
        $this->assertEquals('Hello, world!', $prompt->getContent());
    }

    public function testSystemPromptConstructorWithParameters(): void
    {
        $prompt = SystemPrompt::create('You are a {{role}} assistant specializing in {{domain}}.', [
            'role' => 'helpful',
            'domain' => 'programming'
        ]);
        
        $this->assertEquals('You are a helpful assistant specializing in programming.', $prompt->getContent());
        $this->assertEquals(Role::SYSTEM, $prompt->getRole());
    }

    public function testAssistantPromptConstructorWithParameters(): void
    {
        $prompt = AssistantPrompt::create('I understand you need help with {{topic}}.', [
            'topic' => 'databases'
        ]);
        
        $this->assertEquals('I understand you need help with databases.', $prompt->getContent());
        $this->assertEquals(Role::ASSISTANT, $prompt->getRole());
    }

    public function testConstructorParametersWithComplexTypes(): void
    {
        $prompt = UserPrompt::create('User {{id}} is {{active}} with data {{metadata}}.', [
            'id' => 12345,
            'active' => true,
            'metadata' => ['role' => 'admin']
        ]);
        
        $this->assertEquals('User 12345 is true with data {"role":"admin"}.', $prompt->getContent());
    }

    public function testParameterWithUnsupportedType(): void
    {
        // Test with an object that doesn't have __toString() method
        $plainObject = new \stdClass();
        
        $prompt = UserPrompt::create('Object: {{obj}} end');
        $result = $prompt->withParameters(['obj' => $plainObject]);
        
        // Should fallback to empty string for unsupported types
        $this->assertEquals('Object:  end', $result->getContent());
    }

    public function testParameterWithResource(): void
    {
        // Test with a resource type (if we can create one in a controlled way)
        $stream = fopen('php://memory', 'r');
        
        $prompt = UserPrompt::create('Stream: {{stream}} end');
        $result = $prompt->withParameters(['stream' => $stream]);
        
        // Should fallback to empty string for unsupported types
        $this->assertEquals('Stream:  end', $result->getContent());
        
        fclose($stream);
    }
}