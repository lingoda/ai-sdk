<?php

declare(strict_types = 1);

namespace Lingoda\AiSdk\Tests\Unit\Result;

use Lingoda\AiSdk\Exception\InvalidArgumentException;
use Lingoda\AiSdk\Result\ResultInterface;
use Lingoda\AiSdk\Result\ToolCall;
use Lingoda\AiSdk\Result\ToolCallResult;

final class ToolCallResultTest extends ResultTestCase
{
    protected function createResult(mixed $content, array $metadata = []): ResultInterface
    {
        // ToolCallResult expects metadata first, then tool calls
        if (is_array($content) && !empty($content) && $content[0] instanceof ToolCall) {
            return new ToolCallResult($metadata, ...$content);
        }

        // For base class tests, create a simple ToolCall
        $toolCall = new ToolCall('test_call', 'test_function', []);
        return new ToolCallResult($metadata, $toolCall);
    }

    protected function getExpectedContent(): mixed
    {
        return [new ToolCall('call_123', 'get_weather', ['location' => 'Paris'])];
    }

    /**
     * Create a ToolCallResult with specific tool calls.
     */
    private function createToolCallResult(array $metadata, ToolCall ...$toolCalls): ToolCallResult
    {
        return new ToolCallResult($metadata, ...$toolCalls);
    }

    /**
     * Test with single tool call.
     */
    public function testWithSingleToolCall(): void
    {
        $toolCall = new ToolCall('call_123', 'get_weather', ['location' => 'Paris']);
        $metadata = ['model' => 'gpt-4', 'provider' => 'openai'];

        $result = $this->createToolCallResult($metadata, $toolCall);

        $this->assertEquals([$toolCall], $result->getContent());
        $this->assertEquals($metadata, $result->getMetadata());
        $this->assertCount(1, $result->getContent());
    }

    /**
     * Test with multiple tool calls.
     */
    public function testWithMultipleToolCalls(): void
    {
        $toolCall1 = new ToolCall('call_123', 'get_weather', ['location' => 'Paris']);
        $toolCall2 = new ToolCall('call_456', 'send_email', ['to' => 'test@example.com']);
        $toolCall3 = new ToolCall('call_789', 'calculate', ['operation' => 'sum', 'values' => [1, 2, 3]]);
        $metadata = ['tokens_used' => 150];

        $result = $this->createToolCallResult($metadata, $toolCall1, $toolCall2, $toolCall3);

        $content = $result->getContent();
        $this->assertCount(3, $content);
        $this->assertEquals($toolCall1, $content[0]);
        $this->assertEquals($toolCall2, $content[1]);
        $this->assertEquals($toolCall3, $content[2]);
        $this->assertEquals($metadata, $result->getMetadata());
    }

    /**
     * Test that constructor throws exception with empty tool calls.
     */
    public function testConstructorThrowsWithEmptyToolCalls(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Response must have at least one tool call.');

        new ToolCallResult([]);
    }

    /**
     * Test getContent returns proper ToolCall array.
     */
    public function testGetContentReturnsToolCallsArray(): void
    {
        $toolCall1 = new ToolCall('id1', 'function1', ['arg1' => 'value1']);
        $toolCall2 = new ToolCall('id2', 'function2', ['arg2' => 'value2']);

        $result = $this->createToolCallResult([], $toolCall1, $toolCall2);
        $content = $result->getContent();

        $this->assertIsArray($content);
        $this->assertCount(2, $content);
        $this->assertContainsOnlyInstancesOf(ToolCall::class, $content);

        // Verify individual tool call access
        $this->assertEquals('id1', $content[0]->getId());
        $this->assertEquals('function1', $content[0]->getName());
        $this->assertEquals(['arg1' => 'value1'], $content[0]->getArguments());

        $this->assertEquals('id2', $content[1]->getId());
        $this->assertEquals('function2', $content[1]->getName());
        $this->assertEquals(['arg2' => 'value2'], $content[1]->getArguments());
    }

    /**
     * Test with complex tool calls and metadata.
     */
    public function testWithComplexToolCallsAndMetadata(): void
    {
        $toolCall1 = new ToolCall('weather_call', 'get_weather', [
            'location' => 'New York, NY',
            'units' => 'imperial',
            'include_forecast' => true,
            'days' => 7
        ]);

        $toolCall2 = new ToolCall('email_call', 'send_notification', [
            'recipients' => ['user@example.com', 'admin@example.com'],
            'subject' => 'Weather Update',
            'template' => 'weather_notification',
            'data' => [
                'temperature' => 75,
                'conditions' => 'sunny'
            ]
        ]);

        $metadata = [
            'request_id' => 'req_abc123',
            'model' => 'gpt-4-1106-preview',
            'tokens' => ['input' => 100, 'output' => 250],
            'timestamp' => '2024-01-01T12:00:00Z'
        ];

        $result = $this->createToolCallResult($metadata, $toolCall1, $toolCall2);

        $this->assertCount(2, $result->getContent());
        $this->assertEquals($metadata, $result->getMetadata());

        $content = $result->getContent();
        $this->assertEquals('weather_call', $content[0]->getId());
        $this->assertEquals('get_weather', $content[0]->getName());
        $this->assertEquals('email_call', $content[1]->getId());
        $this->assertEquals('send_notification', $content[1]->getName());
    }

    /**
     * Test with tool calls containing empty arguments.
     */
    public function testWithEmptyArguments(): void
    {
        $toolCall = new ToolCall('simple_call', 'no_args_function', []);
        $result = $this->createToolCallResult([], $toolCall);

        $content = $result->getContent();
        $this->assertCount(1, $content);
        $this->assertEquals([], $content[0]->getArguments());
    }

    /**
     * Test with tool calls containing nested complex data.
     */
    public function testWithNestedComplexData(): void
    {
        $toolCall = new ToolCall('complex_call', 'process_data', [
            'config' => [
                'settings' => [
                    'enabled' => true,
                    'options' => ['A', 'B', 'C']
                ],
                'metadata' => [
                    'version' => '1.0',
                    'created_by' => 'system'
                ]
            ],
            'data_points' => [
                ['x' => 1, 'y' => 2],
                ['x' => 3, 'y' => 4],
                ['x' => 5, 'y' => 6]
            ]
        ]);

        $result = $this->createToolCallResult([], $toolCall);
        $content = $result->getContent();

        $this->assertEquals('complex_call', $content[0]->getId());
        $this->assertEquals('process_data', $content[0]->getName());
        $this->assertArrayHasKey('config', $content[0]->getArguments());
        $this->assertArrayHasKey('data_points', $content[0]->getArguments());
    }
}
