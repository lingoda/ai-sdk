<?php

declare(strict_types=1);

namespace Lingoda\AiSdk\Tests\Unit\Result;

use Lingoda\AiSdk\Result\BinaryResult;
use Lingoda\AiSdk\Result\ResultInterface;

final class BinaryResultTest extends ResultTestCase
{
    private const string DEFAULT_MIME_TYPE = 'application/octet-stream';
    
    protected function createResult($content, array $metadata = []): ResultInterface
    {
        // BinaryResult requires a mime type, use a default for base tests
        return new BinaryResult($content, self::DEFAULT_MIME_TYPE, $metadata);
    }
    
    protected function getExpectedContent(): string
    {
        return 'binary content data';
    }
    
    /**
     * Create a BinaryResult with specific mime type.
     */
    private function createBinaryResult(string $content, string $mimeType, array $metadata = []): BinaryResult
    {
        return new BinaryResult($content, $mimeType, $metadata);
    }
    
    /**
     * Test constructor with mime type.
     */
    public function testConstructWithMimeType(): void
    {
        $content = 'binary data';
        $mimeType = 'audio/mp3';
        $metadata = ['duration' => 30, 'bitrate' => '128k'];
        
        $result = $this->createBinaryResult($content, $mimeType, $metadata);
        
        $this->assertEquals($content, $result->getContent());
        $this->assertEquals($mimeType, $result->getMimeType());
        $this->assertEquals($metadata, $result->getMetadata());
    }
    
    /**
     * Test getMimeType method.
     */
    public function testGetMimeType(): void
    {
        $mimeTypes = [
            'image/png',
            'audio/mp3',
            'video/mp4',
            'application/pdf',
            'text/plain'
        ];
        
        foreach ($mimeTypes as $mimeType) {
            $result = $this->createBinaryResult('content', $mimeType);
            $this->assertEquals($mimeType, $result->getMimeType());
        }
    }
    
    /**
     * Test toBase64 method.
     */
    public function testToBase64(): void
    {
        $content = 'Hello World';
        $expectedBase64 = base64_encode($content);
        
        $result = $this->createBinaryResult($content, 'text/plain');
        
        $this->assertEquals($expectedBase64, $result->toBase64());
    }
    
    /**
     * Test toBase64 with actual binary data.
     */
    public function testToBase64WithBinaryData(): void
    {
        $content = pack('H*', 'deadbeef'); // Binary data from hex
        $expectedBase64 = '3q2+7w=='; // base64 for 'deadbeef'
        
        $result = $this->createBinaryResult($content, 'application/octet-stream');
        
        $this->assertEquals(base64_encode($content), $result->toBase64());
        $this->assertEquals($expectedBase64, $result->toBase64());
    }
    
    /**
     * Test toBase64 with empty data.
     */
    public function testToBase64WithEmptyData(): void
    {
        $result = $this->createBinaryResult('', 'application/octet-stream');
        
        $this->assertEquals('', $result->toBase64());
    }
    
    /**
     * Test with various audio mime types.
     */
    public function testWithAudioMimeTypes(): void
    {
        $audioMimeTypes = ['audio/mp3', 'audio/wav', 'audio/mpeg', 'audio/opus'];
        
        foreach ($audioMimeTypes as $mimeType) {
            $result = $this->createBinaryResult('audio data', $mimeType);
            $this->assertStringStartsWith('audio/', $result->getMimeType());
        }
    }
    
    /**
     * Test with image mime types.
     */
    public function testWithImageMimeTypes(): void
    {
        $imageMimeTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        
        foreach ($imageMimeTypes as $mimeType) {
            $result = $this->createBinaryResult('image data', $mimeType);
            $this->assertStringStartsWith('image/', $result->getMimeType());
        }
    }
}