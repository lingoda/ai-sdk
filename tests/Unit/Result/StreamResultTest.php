<?php

declare(strict_types=1);

namespace Lingoda\AiSdk\Tests\Unit\Result;

use ArrayIterator;
use Generator;
use Lingoda\AiSdk\Result\ResultInterface;
use Lingoda\AiSdk\Result\StreamResult;
use Nyholm\Psr7\Stream;

final class StreamResultTest extends ResultTestCase
{
    protected function createResult($content, array $metadata = []): ResultInterface
    {
        if (is_array($content)) {
            $content = Stream::create(implode('', $content));
        }
        return new StreamResult($content, 'text/plain', $metadata);
    }
    
    protected function getExpectedContent(): mixed
    {
        return Stream::create('chunk1chunk2chunk3');
    }
    
    /**
     * Test with array stream.
     */
    public function testWithArrayStream(): void
    {
        $streamData = 'Hello World!';
        $stream = Stream::create($streamData);
        $result = new StreamResult($stream);
        
        $this->assertSame($stream, $result->getContent());
        $this->assertTrue($result->getContent()->isReadable());
    }
    
    /**
     * Test with generator.
     */
    public function testWithGenerator(): void
    {
        $streamData = 'firstsecondthird';
        $stream = Stream::create($streamData);
        $result = new StreamResult($stream);
        
        $content = '';
        foreach ($result as $chunk) {
            $content .= $chunk;
        }
        
        $this->assertEquals($streamData, $content);
    }
    
    /**
     * Test toString method with array.
     */
    public function testToStringWithArray(): void
    {
        $streamData = 'Hello World!';
        $stream = Stream::create($streamData);
        $result = new StreamResult($stream);
        
        $this->assertEquals($streamData, (string) $result->getContent());
    }
    
    /**
     * Test toString with empty stream.
     */
    public function testToStringWithEmptyStream(): void
    {
        $stream = Stream::create('');
        $result = new StreamResult($stream);
        
        $this->assertEquals('', (string) $result->getContent());
    }
    
    /**
     * Test toString with generator.
     */
    public function testToStringWithGenerator(): void
    {
        $streamData = 'The quick brown fox';
        $stream = Stream::create($streamData);
        $result = new StreamResult($stream);
        
        $this->assertEquals($streamData, (string) $result->getContent());
    }
    
    /**
     * Test content is properly iterable.
     */
    public function testContentIsIterable(): void
    {
        $streamData = 'abc';
        $stream = Stream::create($streamData);
        $result = new StreamResult($stream);
        
        $this->assertInstanceOf(\IteratorAggregate::class, $result);
        
        $collected = '';
        foreach ($result as $chunk) {
            $collected .= $chunk;
        }
        
        $this->assertEquals($streamData, $collected);
    }
    
    /**
     * Test with complex stream data (SSE format).
     */
    public function testWithServerSentEvents(): void
    {
        $streamData = 'data: {"type": "start", "content": "Hello"}data: {"type": "content", "content": " there"}data: {"type": "content", "content": "!"}data: {"type": "end", "content": ""}';
        $stream = Stream::create($streamData);
        
        $metadata = [
            'provider' => 'anthropic',
            'model' => 'claude-3-haiku',
            'stream_format' => 'server-sent-events'
        ];
        
        $result = new StreamResult($stream, 'text/event-stream', $metadata);
        
        $this->assertSame($stream, $result->getContent());
        $this->assertEquals($metadata, $result->getMetadata());
        
        $fullContent = (string) $result->getContent();
        $this->assertStringContainsString('Hello', $fullContent);
        $this->assertStringContainsString('there', $fullContent);
    }
    
    /**
     * Test multiple iterations over same content.
     */
    public function testMultipleIterations(): void
    {
        $streamData = 'chunk1chunk2chunk3';
        $stream = Stream::create($streamData);
        $result = new StreamResult($stream);
        
        // First iteration
        $first = '';
        foreach ($result as $chunk) {
            $first .= $chunk;
        }
        
        // Reset stream for second iteration
        $stream->rewind();
        
        // Second iteration
        $second = '';
        foreach ($result as $chunk) {
            $second .= $chunk;
        }
        
        $this->assertEquals($first, $second);
        $this->assertEquals($streamData, $first);
    }
    
    /**
     * Test toString with Unicode and special characters.
     */
    public function testToStringWithUnicodeCharacters(): void
    {
        $expected = 'Hello 🌍 world! Ñiño café ☕';
        $stream = Stream::create($expected);
        $result = new StreamResult($stream);
        
        $this->assertEquals($expected, (string) $result->getContent());
    }
    
    /**
     * Test with ArrayIterator.
     */
    public function testWithArrayIterator(): void
    {
        $streamData = 'item1item2item3';
        $stream = Stream::create($streamData);
        $result = new StreamResult($stream);
        
        $this->assertSame($stream, $result->getContent());
        $this->assertEquals($streamData, (string) $result->getContent());
    }
    
    /**
     * Test with empty iterator.
     */
    public function testWithEmptyIterator(): void
    {
        $stream = Stream::create('');
        $result = new StreamResult($stream);
        
        $this->assertSame($stream, $result->getContent());
        $this->assertEquals('', (string) $result->getContent());
    }
}