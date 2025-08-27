<?php

declare(strict_types=1);

namespace Lingoda\AiSdk\Tests\Unit\Result;

use Lingoda\AiSdk\Result\ToolCall;
use PHPUnit\Framework\TestCase;

final class ToolCallTest extends TestCase
{
    public function testConstruct(): void
    {
        $id = 'tool_call_123';
        $name = 'get_weather';
        $arguments = ['location' => 'New York', 'units' => 'celsius'];
        
        $toolCall = new ToolCall($id, $name, $arguments);
        
        $this->assertEquals($id, $toolCall->getId());
        $this->assertEquals($name, $toolCall->getName());
        $this->assertEquals($arguments, $toolCall->getArguments());
    }
    
    public function testGetId(): void
    {
        $ids = [
            'call_abc123',
            'tool_456def',
            '12345',
            'function_call_xyz',
        ];
        
        foreach ($ids as $id) {
            $toolCall = new ToolCall($id, 'test_function', []);
            $this->assertEquals($id, $toolCall->getId());
        }
    }
    
    public function testGetName(): void
    {
        $names = [
            'get_weather',
            'send_email',
            'calculate_sum',
            'fetch_user_data',
            'process_payment',
        ];
        
        foreach ($names as $name) {
            $toolCall = new ToolCall('id_123', $name, []);
            $this->assertEquals($name, $toolCall->getName());
        }
    }
    
    public function testGetArgumentsEmpty(): void
    {
        $toolCall = new ToolCall('id_123', 'simple_function', []);
        
        $this->assertEquals([], $toolCall->getArguments());
        $this->assertIsArray($toolCall->getArguments());
    }
    
    public function testGetArgumentsWithData(): void
    {
        $arguments = [
            'string_param' => 'test value',
            'int_param' => 42,
            'bool_param' => true,
            'array_param' => ['a', 'b', 'c'],
            'nested_param' => [
                'key1' => 'value1',
                'key2' => ['nested_array' => [1, 2, 3]]
            ]
        ];
        
        $toolCall = new ToolCall('id_123', 'complex_function', $arguments);
        
        $this->assertEquals($arguments, $toolCall->getArguments());
        $this->assertEquals('test value', $toolCall->getArguments()['string_param']);
        $this->assertEquals(42, $toolCall->getArguments()['int_param']);
        $this->assertTrue($toolCall->getArguments()['bool_param']);
        $this->assertEquals(['a', 'b', 'c'], $toolCall->getArguments()['array_param']);
    }
    
    public function testReadonlyBehavior(): void
    {
        $toolCall = new ToolCall('id_123', 'test_function', ['param' => 'value']);
        
        // Since the class is readonly, we can't modify properties after construction
        // This test verifies that the values remain consistent
        $originalId = $toolCall->getId();
        $originalName = $toolCall->getName();
        $originalArgs = $toolCall->getArguments();
        
        // Multiple calls should return the same values
        $this->assertEquals($originalId, $toolCall->getId());
        $this->assertEquals($originalName, $toolCall->getName());
        $this->assertEquals($originalArgs, $toolCall->getArguments());
    }
    
    public function testToolCallWithSpecialCharacters(): void
    {
        $id = 'call_áéíóú_123';
        $name = 'função_especial';
        $arguments = [
            'message' => 'Hello, 世界! 🌍',
            'unicode_key_ñ' => 'value with émojis 😀',
            'special_chars' => '!@#$%^&*()_+-=[]{}|;:,.<>?'
        ];
        
        $toolCall = new ToolCall($id, $name, $arguments);
        
        $this->assertEquals($id, $toolCall->getId());
        $this->assertEquals($name, $toolCall->getName());
        $this->assertEquals($arguments, $toolCall->getArguments());
    }
}