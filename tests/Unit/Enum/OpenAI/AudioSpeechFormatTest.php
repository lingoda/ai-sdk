<?php

declare(strict_types=1);

namespace Lingoda\AiSdk\Tests\Unit\Enum\OpenAI;

use Lingoda\AiSdk\Enum\OpenAI\AudioSpeechFormat;
use PHPUnit\Framework\TestCase;

final class AudioSpeechFormatTest extends TestCase
{
    public function testGetMimeTypeForMp3(): void
    {
        $this->assertEquals('audio/mpeg', AudioSpeechFormat::MP3->getMimeType());
    }
    
    public function testGetMimeTypeForOpus(): void
    {
        $this->assertEquals('audio/opus', AudioSpeechFormat::OPUS->getMimeType());
    }
    
    public function testGetMimeTypeForAac(): void
    {
        $this->assertEquals('audio/aac', AudioSpeechFormat::AAC->getMimeType());
    }
    
    public function testGetMimeTypeForFlac(): void
    {
        $this->assertEquals('audio/flac', AudioSpeechFormat::FLAC->getMimeType());
    }
    
    public function testGetMimeTypeForWav(): void
    {
        $this->assertEquals('audio/wav', AudioSpeechFormat::WAV->getMimeType());
    }
    
    public function testGetMimeTypeForPcm(): void
    {
        $this->assertEquals('audio/pcm', AudioSpeechFormat::PCM->getMimeType());
    }
    
    public function testAllFormatValues(): void
    {
        $this->assertEquals('mp3', AudioSpeechFormat::MP3->value);
        $this->assertEquals('opus', AudioSpeechFormat::OPUS->value);
        $this->assertEquals('aac', AudioSpeechFormat::AAC->value);
        $this->assertEquals('flac', AudioSpeechFormat::FLAC->value);
        $this->assertEquals('wav', AudioSpeechFormat::WAV->value);
        $this->assertEquals('pcm', AudioSpeechFormat::PCM->value);
    }
    
    public function testCanCreateFromString(): void
    {
        $this->assertEquals(AudioSpeechFormat::MP3, AudioSpeechFormat::from('mp3'));
        $this->assertEquals(AudioSpeechFormat::OPUS, AudioSpeechFormat::from('opus'));
        $this->assertEquals(AudioSpeechFormat::AAC, AudioSpeechFormat::from('aac'));
        $this->assertEquals(AudioSpeechFormat::FLAC, AudioSpeechFormat::from('flac'));
        $this->assertEquals(AudioSpeechFormat::WAV, AudioSpeechFormat::from('wav'));
        $this->assertEquals(AudioSpeechFormat::PCM, AudioSpeechFormat::from('pcm'));
    }
    
    public function testTryFromWithValidValue(): void
    {
        $this->assertEquals(AudioSpeechFormat::MP3, AudioSpeechFormat::tryFrom('mp3'));
        $this->assertEquals(AudioSpeechFormat::OPUS, AudioSpeechFormat::tryFrom('opus'));
    }
    
    public function testTryFromWithInvalidValue(): void
    {
        $this->assertNull(AudioSpeechFormat::tryFrom('invalid'));
        $this->assertNull(AudioSpeechFormat::tryFrom(''));
        $this->assertNull(AudioSpeechFormat::tryFrom('MP3')); // Case sensitive
    }
}