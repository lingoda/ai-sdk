<?php

declare(strict_types=1);

namespace Lingoda\AiSdk\Tests\Unit;

use Lingoda\AiSdk\Audio\AudioCapableInterface;
use Lingoda\AiSdk\Audio\AudioOptionsInterface;
use Lingoda\AiSdk\Audio\OpenAI\AudioOptions;
use Lingoda\AiSdk\ClientInterface;
use Lingoda\AiSdk\Exception\InvalidArgumentException;
use Lingoda\AiSdk\Exception\RuntimeException;
use Lingoda\AiSdk\Platform;
use Lingoda\AiSdk\ProviderInterface;
use Lingoda\AiSdk\Result\BinaryResult;
use Lingoda\AiSdk\Result\ResultInterface;
use Lingoda\AiSdk\Result\StreamResult;
use Lingoda\AiSdk\Result\TextResult;
use Nyholm\Psr7\Stream;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\StreamInterface;

final class PlatformAudioTest extends TestCase
{
    private function createAudioCapableClient(): AudioCapableInterface&ClientInterface
    {
        $provider = $this->createMock(ProviderInterface::class);
        $provider->method('getId')->willReturn('openai');
        
        return new class($provider) implements AudioCapableInterface, ClientInterface {
            public function __construct(private ProviderInterface $provider) {}
            
            public function getProvider(): ProviderInterface { return $this->provider; }
            public function supports($model): bool { return true; }
            public function request($model, $payload, array $options = []): ResultInterface {
                throw new \Exception('Not implemented'); 
            }
            
            public function textToSpeech(string $input, AudioOptionsInterface $options): BinaryResult {
                return new BinaryResult('audio data', 'audio/mpeg');
            }
            
            public function textToSpeechStream(string $input, AudioOptionsInterface $options): StreamResult {
                return new StreamResult(Stream::create('streaming audio data'), 'audio/mpeg');
            }
            
            public function speechToText(StreamInterface $audioStream, AudioOptionsInterface $options): TextResult {
                return new TextResult('transcribed text');
            }
            
            public function translate(StreamInterface $audioStream, AudioOptionsInterface $options): TextResult {
                return new TextResult('translated text');
            }
        };
    }

    private function createNonAudioClient(): ClientInterface
    {
        $provider = $this->createMock(ProviderInterface::class);
        $provider->method('getId')->willReturn('anthropic');
        
        return new class($provider) implements ClientInterface {
            public function __construct(private ProviderInterface $provider) {}
            
            public function getProvider(): ProviderInterface { return $this->provider; }
            public function supports($model): bool { return true; }
            public function request($model, $payload, array $options = []): ResultInterface {
                throw new \Exception('Not implemented'); 
            }
        };
    }

    public function test_textToSpeech_works_with_audio_capable_client(): void
    {
        $audioClient = $this->createAudioCapableClient();
        $platform = new Platform([$audioClient]);
        
        $options = AudioOptions::textToSpeech();
        $result = $platform->textToSpeech('Hello world', $options);

        $this->assertEquals('audio data', $result->getContent());
        $this->assertEquals('audio/mpeg', $result->getMimeType());
    }

    public function test_textToSpeech_throws_exception_for_empty_input(): void
    {
        $audioClient = $this->createAudioCapableClient();
        $platform = new Platform([$audioClient]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Text input cannot be empty for text-to-speech conversion.');

        $options = AudioOptions::textToSpeech();
        $platform->textToSpeech('   ', $options);
    }

    public function test_textToSpeech_throws_exception_when_no_audio_client(): void
    {
        $nonAudioClient = $this->createNonAudioClient();
        $platform = new Platform([$nonAudioClient]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/No audio-capable clients found/');

        $options = AudioOptions::textToSpeech();
        $platform->textToSpeech('Hello world', $options);
    }

    public function test_transcribeAudio_throws_exception_for_missing_file(): void
    {
        $audioClient = $this->createAudioCapableClient();
        $platform = new Platform([$audioClient]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Audio file not found: /nonexistent/file.mp3');

        $options = AudioOptions::speechToText();
        $platform->transcribeAudio('/nonexistent/file.mp3', $options);
    }

    public function test_translateAudio_throws_exception_for_missing_file(): void
    {
        $audioClient = $this->createAudioCapableClient();
        $platform = new Platform([$audioClient]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Audio file not found: /nonexistent/file.mp3');

        $options = AudioOptions::translate();
        $platform->translateAudio('/nonexistent/file.mp3', $options);
    }

    public function test_transcribeAudio_throws_exception_when_no_audio_client(): void
    {
        $nonAudioClient = $this->createNonAudioClient();
        $platform = new Platform([$nonAudioClient]);

        // Create a temporary file for testing
        $tempFile = tempnam(sys_get_temp_dir(), 'audio_test');
        file_put_contents($tempFile, 'dummy audio data');

        try {
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessageMatches('/No audio-capable clients found/');

            $options = AudioOptions::speechToText();
            $platform->transcribeAudio($tempFile, $options);
        } finally {
            unlink($tempFile);
        }
    }

    public function test_translateAudio_throws_exception_when_no_audio_client(): void
    {
        $nonAudioClient = $this->createNonAudioClient();
        $platform = new Platform([$nonAudioClient]);

        // Create a temporary file for testing
        $tempFile = tempnam(sys_get_temp_dir(), 'audio_test');
        file_put_contents($tempFile, 'dummy audio data');

        try {
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessageMatches('/No audio-capable clients found/');

            $options = AudioOptions::translate();
            $platform->translateAudio($tempFile, $options);
        } finally {
            unlink($tempFile);
        }
    }

    public function test_file_size_validation(): void
    {
        $audioClient = $this->createAudioCapableClient();
        $platform = new Platform([$audioClient]);

        // Create a large temporary file (larger than 25MB)
        $tempFile = tempnam(sys_get_temp_dir(), 'large_audio_test');
        $largeData = str_repeat('x', 26 * 1024 * 1024); // 26MB
        file_put_contents($tempFile, $largeData);

        try {
            $this->expectException(InvalidArgumentException::class);
            $this->expectExceptionMessageMatches('/Audio file too large.*Maximum size is 25 MB/');

            $options = AudioOptions::speechToText();
            $platform->transcribeAudio($tempFile, $options);
        } finally {
            unlink($tempFile);
        }
    }

    public function test_finds_first_audio_capable_client(): void
    {
        $nonAudioClient = $this->createNonAudioClient();
        $audioClient = $this->createAudioCapableClient();

        // Audio client is second in the list
        $platform = new Platform([$nonAudioClient, $audioClient]);
        $options = AudioOptions::textToSpeech();
        $result = $platform->textToSpeech('Hello', $options);

        $this->assertEquals('audio data', $result->getContent());
    }

    public function test_textToSpeechStream_works_with_audio_capable_client(): void
    {
        $audioClient = $this->createAudioCapableClient();
        $platform = new Platform([$audioClient]);
        
        $options = AudioOptions::textToSpeech();
        $result = $platform->textToSpeechStream('Hello world', $options);

        $this->assertEquals('audio/mpeg', $result->getMimeType());
    }

    public function test_platform_validates_provider_compatibility(): void
    {
        $audioClient = $this->createAudioCapableClient();
        $platform = new Platform([$audioClient]);
        
        // Create options for a different provider
        $incompatibleOptions = new class implements AudioOptionsInterface {
            public function toArray(): array
            {
                return ['model' => 'some-model'];
            }
            
            public function getProvider(): string
            {
                return 'anthropic'; // Different provider
            }
        };

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Audio options provider mismatch. Available audio providers: openai, but options are for "anthropic"');

        $platform->textToSpeech('Hello world', $incompatibleOptions);
    }

    public function test_platform_finds_matching_provider_client(): void
    {
        $provider1 = $this->createMock(ProviderInterface::class);
        $provider1->method('getId')->willReturn('anthropic');
        
        $provider2 = $this->createMock(ProviderInterface::class);
        $provider2->method('getId')->willReturn('openai');
        
        $nonAudioClient = new class($provider1) implements ClientInterface {
            public function __construct(private ProviderInterface $provider) {}
            public function getProvider(): ProviderInterface { return $this->provider; }
            public function supports($model): bool { return true; }
            public function request($model, $payload, array $options = []): ResultInterface {
                throw new \Exception('Not implemented'); 
            }
        };
        
        $audioClient = new class($provider2) implements AudioCapableInterface, ClientInterface {
            public function __construct(private ProviderInterface $provider) {}
            public function getProvider(): ProviderInterface { return $this->provider; }
            public function supports($model): bool { return true; }
            public function request($model, $payload, array $options = []): ResultInterface {
                throw new \Exception('Not implemented'); 
            }
            
            public function textToSpeech(string $input, AudioOptionsInterface $options): BinaryResult {
                return new BinaryResult('audio data', 'audio/mpeg');
            }
            
            public function textToSpeechStream(string $input, AudioOptionsInterface $options): StreamResult {
                return new StreamResult(Stream::create('streaming audio data'), 'audio/mpeg');
            }
            
            public function speechToText(StreamInterface $audioStream, AudioOptionsInterface $options): TextResult {
                return new TextResult('transcribed text');
            }
            
            public function translate(StreamInterface $audioStream, AudioOptionsInterface $options): TextResult {
                return new TextResult('translated text');
            }
        };

        // Platform with both clients, should find the matching audio-capable one
        $platform = new Platform([$nonAudioClient, $audioClient]);
        $options = AudioOptions::textToSpeech();
        $result = $platform->textToSpeech('Hello world', $options);

        $this->assertEquals('audio data', $result->getContent());
    }
}