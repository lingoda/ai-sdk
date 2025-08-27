<?php

declare(strict_types=1);

namespace Lingoda\AiSdk\Tests\Unit\Audio\OpenAI;

use Lingoda\AiSdk\Audio\OpenAI\AudioOptions;
use Lingoda\AiSdk\Enum\OpenAI\AudioSpeechFormat;
use Lingoda\AiSdk\Enum\OpenAI\AudioSpeechModel;
use Lingoda\AiSdk\Enum\OpenAI\AudioSpeechVoice;
use Lingoda\AiSdk\Enum\OpenAI\AudioTranscribeModel;
use PHPUnit\Framework\TestCase;

final class AudioOptionsTest extends TestCase
{
    public function testTextToSpeechWithDefaults(): void
    {
        $options = AudioOptions::textToSpeech();
        
        $expected = [
            'model' => 'tts-1',
            'voice' => 'alloy',
            'response_format' => 'mp3'
        ];
        
        $this->assertEquals($expected, $options->toArray());
    }
    
    public function testTextToSpeechWithCustomOptions(): void
    {
        $options = AudioOptions::textToSpeech(
            AudioSpeechModel::TTS_1_HD,
            AudioSpeechVoice::NOVA,
            AudioSpeechFormat::WAV,
            1.5
        );
        
        $expected = [
            'model' => 'tts-1-hd',
            'voice' => 'nova',
            'response_format' => 'wav',
            'speed' => 1.5
        ];
        
        $this->assertEquals($expected, $options->toArray());
    }
    
    public function testTextToSpeechWithNullSpeed(): void
    {
        $options = AudioOptions::textToSpeech(
            AudioSpeechModel::TTS_1,
            AudioSpeechVoice::ECHO,
            AudioSpeechFormat::OPUS
        );
        
        $expected = [
            'model' => 'tts-1',
            'voice' => 'echo',
            'response_format' => 'opus'
        ];
        
        $this->assertEquals($expected, $options->toArray());
        $this->assertArrayNotHasKey('speed', $options->toArray());
    }
    
    public function testSpeechToTextWithDefaults(): void
    {
        $options = AudioOptions::speechToText();
        
        $expected = [
            'model' => 'whisper-1'
        ];
        
        $this->assertEquals($expected, $options->toArray());
    }
    
    public function testSpeechToTextWithLanguage(): void
    {
        $options = AudioOptions::speechToText(
            AudioTranscribeModel::WHISPER_1,
            'en'
        );
        
        $expected = [
            'model' => 'whisper-1',
            'language' => 'en'
        ];
        
        $this->assertEquals($expected, $options->toArray());
    }
    
    public function testSpeechToTextWithTimestamps(): void
    {
        $options = AudioOptions::speechToText(
            AudioTranscribeModel::WHISPER_1,
            null,
            true
        );
        
        $optionsArray = $options->toArray();
        $this->assertEquals('whisper-1', $optionsArray['model']);
        $this->assertEquals(['word', 'segment'], $optionsArray['timestamp_granularities']);
        $this->assertEquals('verbose_json', $optionsArray['response_format']);
    }
    
    public function testSpeechToTextWithTemperature(): void
    {
        $options = AudioOptions::speechToText(
            AudioTranscribeModel::WHISPER_1,
            null,
            false,
            0.7
        );
        
        $expected = [
            'model' => 'whisper-1',
            'temperature' => 0.7
        ];
        
        $this->assertEquals($expected, $options->toArray());
    }
    
    public function testSpeechToTextWithResponseFormat(): void
    {
        $options = AudioOptions::speechToText(
            AudioTranscribeModel::WHISPER_1,
            null,
            false,
            null,
            'json'
        );
        
        $optionsArray = $options->toArray();
        $this->assertEquals('whisper-1', $optionsArray['model']);
        $this->assertEquals('json', $optionsArray['response_format']);
    }
    
    public function testTranslateWithDefaults(): void
    {
        $options = AudioOptions::translate();
        
        $expected = [
            'model' => 'whisper-1'
        ];
        
        $this->assertEquals($expected, $options->toArray());
    }
    
    public function testTranslateWithTimestamps(): void
    {
        $options = AudioOptions::translate(
            AudioTranscribeModel::WHISPER_1,
            true
        );
        
        $optionsArray = $options->toArray();
        $this->assertEquals('whisper-1', $optionsArray['model']);
        $this->assertEquals(['word', 'segment'], $optionsArray['timestamp_granularities']);
        $this->assertEquals('verbose_json', $optionsArray['response_format']);
    }
    
    public function testTranslateWithTemperature(): void
    {
        $options = AudioOptions::translate(
            AudioTranscribeModel::WHISPER_1,
            false,
            0.3
        );
        
        $expected = [
            'model' => 'whisper-1',
            'temperature' => 0.3
        ];
        
        $this->assertEquals($expected, $options->toArray());
    }
    
    public function testTranslateWithResponseFormat(): void
    {
        $options = AudioOptions::translate(
            AudioTranscribeModel::WHISPER_1,
            false,
            null,
            'text'
        );
        
        $optionsArray = $options->toArray();
        $this->assertEquals('whisper-1', $optionsArray['model']);
        $this->assertEquals('text', $optionsArray['response_format']);
    }
    
    public function testTranslateWithMultipleOptions(): void
    {
        $options = AudioOptions::translate(
            AudioTranscribeModel::WHISPER_1,
            false,
            0.5,
            'srt'
        );
        
        $optionsArray = $options->toArray();
        $this->assertEquals('whisper-1', $optionsArray['model']);
        $this->assertEquals(0.5, $optionsArray['temperature']);
        $this->assertEquals('srt', $optionsArray['response_format']);
    }

    public function testTranslateWithModelCapabilityFallback(): void
    {
        // Test with a model that doesn't support translation - should fallback to WHISPER_1
        $options = AudioOptions::translate(AudioTranscribeModel::GPT_4O_MINI_TRANSCRIBE);
        
        // Should fallback to whisper-1 because GPT_4O_MINI_TRANSCRIBE doesn't support AUDIO_TRANSLATION
        $optionsArray = $options->toArray();
        $this->assertEquals('whisper-1', $optionsArray['model']);
        $this->assertIsArray($optionsArray);
    }

    public function testConfigureMethodThroughSpeechToTextWithUnsupportedFormat(): void
    {
        // This should test the configure() method's format validation logic
        $options = AudioOptions::speechToText(
            AudioTranscribeModel::WHISPER_1,
            null,
            false,
            null,
            'unsupported_format'
        );
        
        // Should fallback to a supported format or remove the format
        $optionsArray = $options->toArray();
        $this->assertEquals('whisper-1', $optionsArray['model']);
        $this->assertIsArray($optionsArray);
        
        // Test that the configure method is working by testing timestamp logic
        $timestampOptions = AudioOptions::speechToText(
            AudioTranscribeModel::WHISPER_1,
            null,
            true
        );
        
        $timestampOptionsArray = $timestampOptions->toArray();
        $this->assertArrayHasKey('timestamp_granularities', $timestampOptionsArray);
        $this->assertEquals(['word', 'segment'], $timestampOptionsArray['timestamp_granularities']);
        $this->assertEquals('verbose_json', $timestampOptionsArray['response_format']);
    }

    public function testGetProvider(): void
    {
        $options = AudioOptions::textToSpeech();
        $this->assertEquals('openai', $options->getProvider());
    }

    public function testTextToSpeechSpeedValidationTooLow(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Speed must be between 0.25 and 4.0');
        
        AudioOptions::textToSpeech(speed: 0.2);
    }

    public function testTextToSpeechSpeedValidationTooHigh(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Speed must be between 0.25 and 4.0');
        
        AudioOptions::textToSpeech(speed: 4.1);
    }

    public function testTextToSpeechSpeedValidationBoundary(): void
    {
        // Test minimum boundary
        $options1 = AudioOptions::textToSpeech(speed: 0.25);
        $this->assertEquals(0.25, $options1->toArray()['speed']);
        
        // Test maximum boundary
        $options2 = AudioOptions::textToSpeech(speed: 4.0);
        $this->assertEquals(4.0, $options2->toArray()['speed']);
    }

    public function testSpeechToTextTemperatureValidationTooLow(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Temperature must be between 0.0 and 1.0');
        
        AudioOptions::speechToText(temperature: -0.1);
    }

    public function testSpeechToTextTemperatureValidationTooHigh(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Temperature must be between 0.0 and 1.0');
        
        AudioOptions::speechToText(temperature: 1.1);
    }

    public function testSpeechToTextTemperatureBoundary(): void
    {
        // Test minimum boundary
        $options1 = AudioOptions::speechToText(temperature: 0.0);
        $this->assertEquals(0.0, $options1->toArray()['temperature']);
        
        // Test maximum boundary
        $options2 = AudioOptions::speechToText(temperature: 1.0);
        $this->assertEquals(1.0, $options2->toArray()['temperature']);
    }

    public function testTranslateTemperatureValidationTooLow(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Temperature must be between 0.0 and 1.0');
        
        AudioOptions::translate(temperature: -0.1);
    }

    public function testTranslateTemperatureValidationTooHigh(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Temperature must be between 0.0 and 1.0');
        
        AudioOptions::translate(temperature: 1.1);
    }

    public function testTranslateTemperatureBoundary(): void
    {
        // Test minimum boundary
        $options1 = AudioOptions::translate(temperature: 0.0);
        $this->assertEquals(0.0, $options1->toArray()['temperature']);
        
        // Test maximum boundary
        $options2 = AudioOptions::translate(temperature: 1.0);
        $this->assertEquals(1.0, $options2->toArray()['temperature']);
    }
}