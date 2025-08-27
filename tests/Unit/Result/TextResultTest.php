<?php

declare(strict_types=1);

namespace Lingoda\AiSdk\Tests\Unit\Result;

use Lingoda\AiSdk\Result\ResultInterface;
use Lingoda\AiSdk\Result\TextResult;

final class TextResultTest extends ResultTestCase
{
    protected function createResult($content, array $metadata = []): ResultInterface
    {
        return new TextResult($content, $metadata);
    }
    
    protected function getExpectedContent(): mixed
    {
        return 'Hello, world!';
    }
    
    /**
     * Test with empty string content.
     */
    public function testWithEmptyStringContent(): void
    {
        $result = new TextResult('');
        
        $this->assertSame('', $result->getContent());
        $this->assertSame([], $result->getMetadata());
    }
    
    /**
     * Test with multi-line text content.
     */
    public function testWithMultiLineContent(): void
    {
        $content = "Line 1\nLine 2\nLine 3";
        $result = new TextResult($content);
        
        $this->assertSame($content, $result->getContent());
        $this->assertStringContainsString("\n", $result->getContent());
    }
    
    /**
     * Test with Unicode content.
     */
    public function testWithUnicodeContent(): void
    {
        $content = 'Hello 世界 🌍 مرحبا';
        $result = new TextResult($content);
        
        $this->assertSame($content, $result->getContent());
    }
}