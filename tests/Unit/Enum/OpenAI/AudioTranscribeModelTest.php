<?php

declare(strict_types=1);

namespace Lingoda\AiSdk\Tests\Unit\Enum\OpenAI;

use Lingoda\AiSdk\Enum\Capability;
use Lingoda\AiSdk\Enum\OpenAI\AudioTranscribeModel;
use PHPUnit\Framework\TestCase;

final class AudioTranscribeModelTest extends TestCase
{
    public function testCapabilities(): void
    {
        // Whisper-1 supports all audio capabilities
        $whisperCapabilities = AudioTranscribeModel::WHISPER_1->getCapabilities();
        $this->assertContains(Capability::AUDIO_TRANSCRIPTION, $whisperCapabilities);
        $this->assertContains(Capability::AUDIO_TRANSLATION, $whisperCapabilities);
        $this->assertContains(Capability::AUDIO_TIMESTAMPS, $whisperCapabilities);
        
        // GPT-4o models only support transcription
        $gpt4oMiniCapabilities = AudioTranscribeModel::GPT_4O_MINI_TRANSCRIBE->getCapabilities();
        $this->assertContains(Capability::AUDIO_TRANSCRIPTION, $gpt4oMiniCapabilities);
        $this->assertNotContains(Capability::AUDIO_TRANSLATION, $gpt4oMiniCapabilities);
        $this->assertNotContains(Capability::AUDIO_TIMESTAMPS, $gpt4oMiniCapabilities);
        
        $gpt4oCapabilities = AudioTranscribeModel::GPT_4O_TRANSCRIBE->getCapabilities();
        $this->assertContains(Capability::AUDIO_TRANSCRIPTION, $gpt4oCapabilities);
        $this->assertNotContains(Capability::AUDIO_TRANSLATION, $gpt4oCapabilities);
        $this->assertNotContains(Capability::AUDIO_TIMESTAMPS, $gpt4oCapabilities);
    }

    public function testHasCapability(): void
    {
        // Whisper-1 supports translation and timestamps
        $this->assertTrue(AudioTranscribeModel::WHISPER_1->hasCapability(Capability::AUDIO_TRANSCRIPTION));
        $this->assertTrue(AudioTranscribeModel::WHISPER_1->hasCapability(Capability::AUDIO_TRANSLATION));
        $this->assertTrue(AudioTranscribeModel::WHISPER_1->hasCapability(Capability::AUDIO_TIMESTAMPS));
        
        // GPT-4o models do not support translation or timestamps
        $this->assertTrue(AudioTranscribeModel::GPT_4O_MINI_TRANSCRIBE->hasCapability(Capability::AUDIO_TRANSCRIPTION));
        $this->assertFalse(AudioTranscribeModel::GPT_4O_MINI_TRANSCRIBE->hasCapability(Capability::AUDIO_TRANSLATION));
        $this->assertFalse(AudioTranscribeModel::GPT_4O_MINI_TRANSCRIBE->hasCapability(Capability::AUDIO_TIMESTAMPS));
        
        $this->assertTrue(AudioTranscribeModel::GPT_4O_TRANSCRIBE->hasCapability(Capability::AUDIO_TRANSCRIPTION));
        $this->assertFalse(AudioTranscribeModel::GPT_4O_TRANSCRIBE->hasCapability(Capability::AUDIO_TRANSLATION));
        $this->assertFalse(AudioTranscribeModel::GPT_4O_TRANSCRIBE->hasCapability(Capability::AUDIO_TIMESTAMPS));
    }

    public function testGetSupportedResponseFormats(): void
    {
        // Whisper-1 supports all formats
        $whisperFormats = AudioTranscribeModel::WHISPER_1->getSupportedResponseFormats();
        $expectedWhisperFormats = ['json', 'text', 'srt', 'verbose_json', 'vtt'];
        $this->assertEquals($expectedWhisperFormats, $whisperFormats);
        
        // GPT-4o models have limited format support
        $gpt4oMiniFormats = AudioTranscribeModel::GPT_4O_MINI_TRANSCRIBE->getSupportedResponseFormats();
        $gpt4oFormats = AudioTranscribeModel::GPT_4O_TRANSCRIBE->getSupportedResponseFormats();
        $expectedLimitedFormats = ['json', 'text'];
        
        $this->assertEquals($expectedLimitedFormats, $gpt4oMiniFormats);
        $this->assertEquals($expectedLimitedFormats, $gpt4oFormats);
    }

    public function testGetMaxFileSize(): void
    {
        $expected25MB = 25 * 1024 * 1024; // 25 MB in bytes
        
        $this->assertEquals($expected25MB, AudioTranscribeModel::WHISPER_1->getMaxFileSize());
        $this->assertEquals($expected25MB, AudioTranscribeModel::GPT_4O_MINI_TRANSCRIBE->getMaxFileSize());
        $this->assertEquals($expected25MB, AudioTranscribeModel::GPT_4O_TRANSCRIBE->getMaxFileSize());
    }

    public function testGetSupportedFormats(): void
    {
        $expectedFormats = [
            'flac', 'mp3', 'mp4', 'mpeg', 'mpga', 'm4a', 'ogg', 'wav', 'webm'
        ];
        
        // All models should support the same audio formats
        $this->assertEquals($expectedFormats, AudioTranscribeModel::WHISPER_1->getSupportedFormats());
        $this->assertEquals($expectedFormats, AudioTranscribeModel::GPT_4O_MINI_TRANSCRIBE->getSupportedFormats());
        $this->assertEquals($expectedFormats, AudioTranscribeModel::GPT_4O_TRANSCRIBE->getSupportedFormats());
        
        // Verify common formats are included
        $this->assertContains('mp3', AudioTranscribeModel::WHISPER_1->getSupportedFormats());
        $this->assertContains('wav', AudioTranscribeModel::WHISPER_1->getSupportedFormats());
        $this->assertContains('m4a', AudioTranscribeModel::WHISPER_1->getSupportedFormats());
    }

    public function testGetSupportedLanguages(): void
    {
        $supportedLanguages = AudioTranscribeModel::WHISPER_1->getSupportedLanguages();
        
        // All models should support the same languages
        $this->assertEquals($supportedLanguages, AudioTranscribeModel::GPT_4O_MINI_TRANSCRIBE->getSupportedLanguages());
        $this->assertEquals($supportedLanguages, AudioTranscribeModel::GPT_4O_TRANSCRIBE->getSupportedLanguages());
        
        // Verify it's an array of language codes
        $this->assertIsArray($supportedLanguages);
        $this->assertNotEmpty($supportedLanguages);
        
        // Verify common languages are included
        $this->assertContains('en', $supportedLanguages); // English
        $this->assertContains('es', $supportedLanguages); // Spanish
        $this->assertContains('fr', $supportedLanguages); // French
        $this->assertContains('de', $supportedLanguages); // German
        $this->assertContains('ja', $supportedLanguages); // Japanese
        $this->assertContains('zh', $supportedLanguages); // Chinese
        
        // Verify languages are in ISO 639-1 format (2-letter codes)
        foreach ($supportedLanguages as $language) {
            $this->assertIsString($language);
            $this->assertEquals(2, strlen($language));
            $this->assertMatchesRegularExpression('/^[a-z]{2}$/', $language);
        }
    }

    public function testGetMaxDuration(): void
    {
        $expectedOneHour = 3600; // 1 hour in seconds
        
        $this->assertEquals($expectedOneHour, AudioTranscribeModel::WHISPER_1->getMaxDuration());
        $this->assertEquals($expectedOneHour, AudioTranscribeModel::GPT_4O_MINI_TRANSCRIBE->getMaxDuration());
        $this->assertEquals($expectedOneHour, AudioTranscribeModel::GPT_4O_TRANSCRIBE->getMaxDuration());
    }

    public function testEnumValues(): void
    {
        // Test that enum values match expected OpenAI model names
        $this->assertEquals('whisper-1', AudioTranscribeModel::WHISPER_1->value);
        $this->assertEquals('gpt-4o-mini-transcribe', AudioTranscribeModel::GPT_4O_MINI_TRANSCRIBE->value);
        $this->assertEquals('gpt-4o-transcribe', AudioTranscribeModel::GPT_4O_TRANSCRIBE->value);
    }

    public function testAllEnumCasesAreCovered(): void
    {
        // Ensure all enum cases are tested by checking each method works for all cases
        $allCases = AudioTranscribeModel::cases();
        
        $this->assertCount(3, $allCases);
        
        foreach ($allCases as $model) {
            // These methods should work for all models without throwing exceptions
            $this->assertIsArray($model->getCapabilities());
            $this->assertIsBool($model->hasCapability(Capability::AUDIO_TRANSCRIPTION));
            $this->assertIsArray($model->getSupportedResponseFormats());
            $this->assertIsInt($model->getMaxFileSize());
            $this->assertIsArray($model->getSupportedFormats());
            $this->assertIsArray($model->getSupportedLanguages());
            $this->assertIsInt($model->getMaxDuration());
            
            // Verify constraints are reasonable
            $this->assertGreaterThan(0, $model->getMaxFileSize());
            $this->assertGreaterThan(0, $model->getMaxDuration());
            $this->assertNotEmpty($model->getSupportedResponseFormats());
            $this->assertNotEmpty($model->getSupportedFormats());
            $this->assertNotEmpty($model->getSupportedLanguages());
            
            // All models should at least support transcription
            $this->assertTrue($model->hasCapability(Capability::AUDIO_TRANSCRIPTION));
        }
    }

    public function testResponseFormatValidation(): void
    {
        // Test that response format arrays contain expected values
        $whisperFormats = AudioTranscribeModel::WHISPER_1->getSupportedResponseFormats();
        
        $this->assertContains('json', $whisperFormats);
        $this->assertContains('text', $whisperFormats);
        $this->assertContains('verbose_json', $whisperFormats);
        
        // GPT-4o models should have limited formats
        $gpt4oFormats = AudioTranscribeModel::GPT_4O_MINI_TRANSCRIBE->getSupportedResponseFormats();
        
        $this->assertContains('json', $gpt4oFormats);
        $this->assertContains('text', $gpt4oFormats);
        $this->assertNotContains('verbose_json', $gpt4oFormats);
        $this->assertNotContains('srt', $gpt4oFormats);
    }

    public function testCapabilityConsistency(): void
    {
        // Test that capabilities are consistent with response format support
        
        // If a model supports timestamps, it should support verbose_json
        if (AudioTranscribeModel::WHISPER_1->hasCapability(Capability::AUDIO_TIMESTAMPS)) {
            $this->assertContains('verbose_json', AudioTranscribeModel::WHISPER_1->getSupportedResponseFormats());
        }
        
        // GPT-4o models don't support timestamps and shouldn't have verbose_json
        $this->assertFalse(AudioTranscribeModel::GPT_4O_MINI_TRANSCRIBE->hasCapability(Capability::AUDIO_TIMESTAMPS));
        $this->assertNotContains('verbose_json', AudioTranscribeModel::GPT_4O_MINI_TRANSCRIBE->getSupportedResponseFormats());
        
        $this->assertFalse(AudioTranscribeModel::GPT_4O_TRANSCRIBE->hasCapability(Capability::AUDIO_TIMESTAMPS));
        $this->assertNotContains('verbose_json', AudioTranscribeModel::GPT_4O_TRANSCRIBE->getSupportedResponseFormats());
    }
}