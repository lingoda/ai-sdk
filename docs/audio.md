# Audio Processing Guide

Complete guide to audio processing with the Lingoda AI SDK, including text-to-speech, speech-to-text, and translation capabilities.

## Overview

The SDK provides comprehensive audio processing through OpenAI's audio models, supporting:
- **Text-to-Speech**: Generate high-quality audio from text
- **Speech-to-Text**: Transcribe audio files to text
- **Speech Translation**: Translate audio to English text
- **Multiple Audio Formats**: Support for various input and output formats

## Text-to-Speech (TTS)

### Basic Text-to-Speech

```php
use Lingoda\AiSdk\Platform;
use Lingoda\AiSdk\Client\OpenAI\OpenAIClientFactory;
use Lingoda\AiSdk\Audio\OpenAI\AudioOptions;
use Lingoda\AiSdk\Enum\OpenAI\AudioSpeechModel;
use Lingoda\AiSdk\Enum\OpenAI\AudioSpeechVoice;
use Lingoda\AiSdk\Enum\OpenAI\AudioSpeechFormat;
use Lingoda\AiSdk\Exception\ClientException;

try {
    $client = OpenAIClientFactory::createClient('your-api-key');
    $platform = new Platform([$client]);

    // Basic text-to-speech with defaults
    $options = AudioOptions::textToSpeech();
    $audioData = $platform->textToSpeech('Hello, world!', $options);
    
    // Save the audio file
    file_put_contents('output.mp3', $audioData->getContent());
    
} catch (ClientException $e) {
    echo "Audio generation failed: " . $e->getMessage();
}
```

### Text-to-Speech with Prompts and Parameters

The `textToSpeech` method supports the same flexible input types as the `ask()` method:

```php
use Lingoda\AiSdk\Prompt\UserPrompt;
use Lingoda\AiSdk\Prompt\Conversation;
use Lingoda\AiSdk\Prompt\SystemPrompt;

// Using UserPrompt with parameters
$prompt = UserPrompt::create('Hello, {{name}}! Welcome to {{company}}.', [
    'name' => 'Alice',
    'company' => 'Lingoda'
]);

$audioData = $platform->textToSpeech($prompt, $options);
file_put_contents('personalized_greeting.mp3', $audioData->getContent());

// Using Conversation for complex content
$systemPrompt = SystemPrompt::create('You are a professional narrator.');
$userPrompt = UserPrompt::create('Please read the following announcement clearly.');
$conversation = Conversation::withSystem($userPrompt, $systemPrompt);

$audioData = $platform->textToSpeech($conversation, $options);
file_put_contents('announcement.mp3', $audioData->getContent());

// Template-based generation
$template = UserPrompt::create(
    'Today is {{date}}. The weather is {{weather}}. Temperature: {{temp}}°C.'
);

$weatherPrompt = $template->withParameters([
    'date' => date('Y-m-d'),
    'weather' => 'sunny',
    'temp' => 22
]);

$audioData = $platform->textToSpeech($weatherPrompt, $options);
```

### Advanced Text-to-Speech Options

```php
// High-quality audio with custom voice and format
$options = AudioOptions::textToSpeech(
    model: AudioSpeechModel::TTS_1_HD,    // High-definition model
    voice: AudioSpeechVoice::NOVA,        // Premium voice
    format: AudioSpeechFormat::WAV,       // Uncompressed format
    speed: 1.2                            // 20% faster playback
);

$audioData = $platform->textToSpeech('Welcome to our application!', $options);
file_put_contents('welcome.wav', $audioData);
```

### Voice Options and Characteristics

```php
// Different voices for different use cases
$voices = [
    AudioSpeechVoice::ALLOY => 'Neutral, balanced voice',
    AudioSpeechVoice::ECHO => 'Male voice, clear pronunciation', 
    AudioSpeechVoice::FABLE => 'British accent, storytelling',
    AudioSpeechVoice::ONYX => 'Deep male voice, authoritative',
    AudioSpeechVoice::NOVA => 'Warm female voice, engaging',
    AudioSpeechVoice::SHIMMER => 'Soft female voice, gentle'
];

foreach ($voices as $voice => $description) {
    echo "Generating audio with {$voice->value} voice ({$description})\n";
    
    $options = AudioOptions::textToSpeech(voice: $voice);
    $audioData = $platform->textToSpeech("This is a test with {$voice->value} voice.", $options);
    file_put_contents("sample_{$voice->value}.mp3", $audioData);
}
```

### Batch Text-to-Speech Processing

```php
$textSegments = [
    'introduction' => 'Welcome to our training course.',
    'section1' => 'In this first section, we will cover the basics.',
    'section2' => 'Moving on to advanced concepts.',
    'conclusion' => 'Thank you for completing the course.'
];
$options = AudioOptions::textToSpeech(
    model: AudioSpeechModel::TTS_1,
    voice: AudioSpeechVoice::NOVA,
    format: AudioSpeechFormat::MP3
);

foreach ($textSegments as $key => $text) {
    try {
        $audioData = $platform->textToSpeech($text, $options);
        file_put_contents("course_{$key}.mp3", $audioData);
        
        echo "Generated audio for: {$key}\n";
        
    } catch (ClientException $e) {
        echo "Failed to generate audio for {$key}: " . $e->getMessage() . "\n";
    }
}
```

## Speech-to-Text (STT)

### Basic Speech-to-Text

```php
use Lingoda\AiSdk\Audio\OpenAI\AudioOptions;
use Lingoda\AiSdk\Enum\OpenAI\AudioTranscribeModel;

try {
    $audioFile = 'path/to/audio.mp3';
    
    // Basic transcription with Whisper-1
    $options = AudioOptions::speechToText(
        model: AudioTranscribeModel::WHISPER_1
    );
    
    $transcription = $platform->transcribeAudio($audioFile, $options);
    echo "Transcription: " . $transcription->getText();
    
} catch (ClientException $e) {
    echo "Transcription failed: " . $e->getMessage();
}
```

### Advanced Speech-to-Text with Language Detection

```php
// Transcription with language hint for better accuracy
$options = AudioOptions::speechToText(
    model: AudioTranscribeModel::WHISPER_1,
    language: 'en',                        // English language hint
    temperature: 0.0,                      // Deterministic output
    responseFormat: 'json'                 // Structured response
);

$transcription = $platform->transcribeAudio('meeting.wav', $options);

// Access transcription details
echo "Text: " . $transcription->getText() . "\n";
echo "Language: " . $transcription->getLanguage() . "\n";
echo "Duration: " . $transcription->getDuration() . " seconds\n";
```

### Timestamped Transcription

```php
// Get transcription with word and segment timestamps
$options = AudioOptions::speechToText(
    model: AudioTranscribeModel::WHISPER_1,
    language: 'en',
    includeTimestamps: true,               // Enables timestamp_granularities
    responseFormat: 'verbose_json'         // Required for timestamps
);

$transcription = $platform->transcribeAudio('interview.m4a', $options);

// Access timestamps (only available with whisper-1)
if ($transcription->hasTimestamps()) {
    foreach ($transcription->getWords() as $word) {
        echo sprintf("[%.2fs-%.2fs] %s\n", 
            $word['start'], 
            $word['end'], 
            $word['word']
        );
    }
}
```

### Model-Specific Transcription

```php
// Use different models based on needs
$models = [
    AudioTranscribeModel::WHISPER_1 => 'Full-featured with timestamps and translation',
    AudioTranscribeModel::GPT_4O_MINI_TRANSCRIBE => 'Fast and cost-effective',
    AudioTranscribeModel::GPT_4O_TRANSCRIBE => 'High accuracy for complex audio'
];

$audioFile = 'presentation.mp3';

foreach ($models as $model => $description) {
    echo "Transcribing with {$model->value} ({$description})\n";
    
    try {
        $options = AudioOptions::speechToText(
            model: $model,
            language: 'en',
            temperature: 0.1
        );
        
        $transcription = $platform->transcribeAudio($audioFile, $options);
        echo "Result: " . substr($transcription->getText(), 0, 100) . "...\n\n";
        
    } catch (ClientException $e) {
        echo "Failed with {$model->value}: " . $e->getMessage() . "\n\n";
    }
}
```

## Speech Translation

### Basic Speech Translation

```php
// Translate any language audio to English text
try {
    $options = AudioOptions::translate(
        model: AudioTranscribeModel::WHISPER_1  // Only whisper-1 supports translation
    );
    
    $translation = $platform->translateAudio('spanish_audio.mp3', $options);
    echo "English translation: " . $translation->getText();
    
} catch (ClientException $e) {
    echo "Translation failed: " . $e->getMessage();
}
```

### Advanced Speech Translation

```php
// Translation with specific output format and temperature
$options = AudioOptions::translate(
    model: AudioTranscribeModel::WHISPER_1,
    responseFormat: 'text',                // Plain text output
    temperature: 0.0                       // Most deterministic translation
);

$foreignLanguageFiles = [
    'french_lesson.mp3',
    'german_meeting.wav', 
    'spanish_interview.m4a',
    'italian_podcast.mp3'
];

foreach ($foreignLanguageFiles as $file) {
    if (file_exists($file)) {
        try {
            $translation = $platform->translateAudio($file, $options);
            echo "File: {$file}\n";
            echo "Translation: " . $translation->getText() . "\n\n";
            
        } catch (ClientException $e) {
            echo "Failed to translate {$file}: " . $e->getMessage() . "\n\n";
        }
    }
}
```

### Translation with Fallback Models

```php
// Automatic fallback for models that don't support translation
try {
    // This will automatically use whisper-1 even if another model is specified
    $options = AudioOptions::translate(
        model: AudioTranscribeModel::GPT_4O_TRANSCRIBE, // Doesn't support translation
        responseFormat: 'json'
    );
    
    // SDK automatically falls back to whisper-1 for translation
    $translation = $platform->translateAudio('multilingual_conference.mp3', $options);
    echo "Translation: " . $translation->getText();
    
} catch (ClientException $e) {
    echo "Translation failed: " . $e->getMessage();
}
```

## Format Support and File Handling

### Supported Audio Formats

```php
$supportedFormats = [
    // Input formats for transcription/translation
    'mp3', 'mp4', 'm4a', 'wav', 'webm', 'flac', 'ogg', 'oga', '3gp',
    
    // Output formats for text-to-speech
    AudioSpeechFormat::MP3,      // Default, good compression
    AudioSpeechFormat::OPUS,     // Best compression for speech
    AudioSpeechFormat::AAC,      // Good quality and compatibility  
    AudioSpeechFormat::FLAC,     // Lossless compression
    AudioSpeechFormat::WAV,      // Uncompressed, highest quality
    AudioSpeechFormat::PCM       // Raw audio data
];

// Example: Convert text to different audio formats
$text = "This audio will be generated in multiple formats.";

foreach ([AudioSpeechFormat::MP3, AudioSpeechFormat::WAV, AudioSpeechFormat::FLAC] as $format) {
    $options = AudioOptions::textToSpeech(format: $format);
    $audioData = $platform->textToSpeech($text, $options);
    
    $filename = "output.{$format->value}";
    file_put_contents($filename, $audioData);
    echo "Generated: {$filename} (" . number_format(strlen($audioData) / 1024, 2) . " KB)\n";
}
```

### File Size and Duration Limits

```php
// Audio file validation before processing
function validateAudioFile(string $filePath): bool {
    if (!file_exists($filePath)) {
        echo "File not found: {$filePath}\n";
        return false;
    }
    
    $fileSize = filesize($filePath);
    $maxSize = 25 * 1024 * 1024; // 25MB limit
    
    if ($fileSize > $maxSize) {
        echo "File too large: " . number_format($fileSize / 1024 / 1024, 2) . "MB (max 25MB)\n";
        return false;
    }
    
    // Note: Duration limit is 1 hour, but we can't easily check without processing
    echo "File valid: " . number_format($fileSize / 1024 / 1024, 2) . "MB\n";
    return true;
}

// Example usage
$audioFiles = ['lecture.mp3', 'podcast.wav', 'meeting.m4a'];

foreach ($audioFiles as $file) {
    if (validateAudioFile($file)) {
        try {
            $options = AudioOptions::speechToText();
            $transcription = $platform->transcribeAudio($file, $options);
            echo "Transcribed {$file}: " . substr($transcription->getText(), 0, 100) . "...\n";
            
        } catch (ClientException $e) {
            echo "Processing failed for {$file}: " . $e->getMessage() . "\n";
        }
    }
}
```

## Error Handling and Best Practices

### Comprehensive Audio Error Handling

```php
use Lingoda\AiSdk\Exception\InvalidArgumentException;
use Lingoda\AiSdk\Exception\UnsupportedCapabilityException;
use Lingoda\AiSdk\Model\CapabilityValidator;
use Lingoda\AiSdk\Enum\Capability;

function processAudioWithErrorHandling(Platform $platform, string $audioFile): void {
    try {
        // Validate model capabilities first
        $provider = $platform->getProvider('openai');
        $model = $provider->getModel('whisper-1');
        
        CapabilityValidator::requireCapability($model, Capability::AUDIO_TRANSCRIPTION);
        
        // Configure transcription options
        $options = AudioOptions::speechToText(
            model: AudioTranscribeModel::WHISPER_1,
            language: 'auto',  // Automatic language detection
            temperature: 0.2,
            responseFormat: 'verbose_json'
        );
        
        $transcription = $platform->transcribeAudio($audioFile, $options);
        echo "Success: " . $transcription->getText() . "\n";
        
    } catch (UnsupportedCapabilityException $e) {
        echo "Model capability error: " . $e->getMessage() . "\n";
        echo "Try using a different model or check model availability.\n";
        
    } catch (InvalidArgumentException $e) {
        echo "Invalid input: " . $e->getMessage() . "\n";
        echo "Check file path, format, or audio options.\n";
        
    } catch (ClientException $e) {
        echo "API error: " . $e->getMessage() . "\n";
        
        // Handle specific API error scenarios
        if (str_contains($e->getMessage(), 'file too large')) {
            echo "Suggestion: Split the audio file into smaller segments.\n";
        } elseif (str_contains($e->getMessage(), 'unsupported format')) {
            echo "Suggestion: Convert to a supported format (mp3, wav, m4a, etc.).\n";
        } elseif (str_contains($e->getMessage(), 'rate limit')) {
            echo "Suggestion: Implement retry logic with exponential backoff.\n";
        }
        
    } catch (\Exception $e) {
        echo "Unexpected error: " . $e->getMessage() . "\n";
    }
}
```

### Retry Logic for Audio Processing

```php
function processAudioWithRetry(Platform $platform, string $audioFile, int $maxRetries = 3): ?string {
    $attempt = 0;
    
    while ($attempt < $maxRetries) {
        try {
            $options = AudioOptions::speechToText(
                model: AudioTranscribeModel::WHISPER_1,
                temperature: 0.1
            );
            
            $transcription = $platform->transcribeAudio($audioFile, $options);
            return $transcription->getText();
            
        } catch (ClientException $e) {
            $attempt++;
            
            if (str_contains($e->getMessage(), 'rate limit') && $attempt < $maxRetries) {
                $backoffSeconds = pow(2, $attempt); // Exponential backoff
                echo "Rate limited. Retrying in {$backoffSeconds} seconds... (attempt {$attempt}/{$maxRetries})\n";
                sleep($backoffSeconds);
                continue;
            }
            
            echo "Failed after {$attempt} attempts: " . $e->getMessage() . "\n";
            break;
        }
    }
    
    return null;
}
```

## Real-World Examples

### Voice Notes Application

```php
class VoiceNotesProcessor {
    public function __construct(private Platform $platform) {}
    
    public function processVoiceNote(string $audioFile, string $userId): array {
        try {
            // Step 1: Transcribe the voice note
            $transcribeOptions = AudioOptions::speechToText(
                model: AudioTranscribeModel::WHISPER_1,
                language: 'auto',
                temperature: 0.1,
                responseFormat: 'verbose_json'
            );
            
            $transcription = $this->platform->transcribeAudio($audioFile, $transcribeOptions);
            
            // Step 2: Generate a summary using AI
            $summaryPrompt = "Summarize this voice note in 2-3 sentences: " . $transcription->getText();
            $summary = $this->platform->ask($summaryPrompt);
            
            // Step 3: Create title from content
            $titlePrompt = "Create a short title (max 50 chars) for this note: " . $transcription->getText();
            $title = $this->platform->ask($titlePrompt);
            
            return [
                'user_id' => $userId,
                'title' => trim($title->getContent(), '"'),
                'full_text' => $transcription->getText(),
                'summary' => $summary->getContent(),
                'duration' => $transcription->getDuration() ?? 0,
                'language' => $transcription->getLanguage() ?? 'unknown',
                'processed_at' => date('Y-m-d H:i:s')
            ];
            
        } catch (ClientException $e) {
            throw new \RuntimeException("Voice note processing failed: " . $e->getMessage());
        }
    }
}

// Usage
$processor = new VoiceNotesProcessor($platform);
$result = $processor->processVoiceNote('user_voice_note.m4a', 'user123');
print_r($result);
```

### Multilingual Meeting Transcription

```php
class MeetingTranscriber {
    public function __construct(private Platform $platform) {}
    
    public function transcribeMeeting(string $audioFile): array {
        $results = [];
        
        try {
            // First, get full transcription with timestamps
            $transcribeOptions = AudioOptions::speechToText(
                model: AudioTranscribeModel::WHISPER_1,
                includeTimestamps: true,
                responseFormat: 'verbose_json'
            );
            
            $transcription = $this->platform->transcribeAudio($audioFile, $transcribeOptions);
            $results['original_transcript'] = $transcription->getText();
            $results['language'] = $transcription->getLanguage();
            
            // If not in English, also get translation
            if ($transcription->getLanguage() !== 'en') {
                $translateOptions = AudioOptions::translate(
                    model: AudioTranscribeModel::WHISPER_1,
                    responseFormat: 'text'
                );
                
                $translation = $this->platform->translateAudio($audioFile, $translateOptions);
                $results['english_translation'] = $translation->getText();
            }
            
            // Generate meeting summary
            $summaryText = $results['english_translation'] ?? $results['original_transcript'];
            $summaryPrompt = "Create a structured meeting summary with:\n" .
                           "1. Key discussion points\n" .
                           "2. Decisions made\n" .
                           "3. Action items\n\n" .
                           "Meeting transcript: " . $summaryText;
            
            $summary = $this->platform->ask($summaryPrompt);
            $results['summary'] = $summary->getContent();
            
            // Extract timestamps if available
            if ($transcription->hasTimestamps()) {
                $results['timestamps'] = $transcription->getSegments();
            }
            
            return $results;
            
        } catch (ClientException $e) {
            throw new \RuntimeException("Meeting transcription failed: " . $e->getMessage());
        }
    }
}
```

### Accessibility Features

```php
class AccessibilityAudioHelper {
    public function __construct(private Platform $platform) {}
    
    public function createAudioDescription(string $text, string $outputFile): bool {
        try {
            // Generate high-quality audio for accessibility
            $options = AudioOptions::textToSpeech(
                model: AudioSpeechModel::TTS_1_HD,
                voice: AudioSpeechVoice::NOVA,  // Clear, warm voice
                format: AudioSpeechFormat::WAV, // Uncompressed for clarity
                speed: 0.9                      // Slightly slower for clarity
            );
            
            $audioData = $this->platform->textToSpeech($text, $options);
            return file_put_contents($outputFile, $audioData) !== false;
            
        } catch (ClientException $e) {
            error_log("Audio description generation failed: " . $e->getMessage());
            return false;
        }
    }
    
    public function generateCaptions(string $videoAudioFile): array {
        try {
            $options = AudioOptions::speechToText(
                model: AudioTranscribeModel::WHISPER_1,
                includeTimestamps: true,
                responseFormat: 'srt'  // Subtitle format
            );
            
            $captions = $this->platform->transcribeAudio($videoAudioFile, $options);
            
            return [
                'srt_content' => $captions->getText(),
                'format' => 'srt',
                'has_timestamps' => true
            ];
            
        } catch (ClientException $e) {
            throw new \RuntimeException("Caption generation failed: " . $e->getMessage());
        }
    }
}
```

## Performance Tips

### Optimizing Audio Processing

1. **Choose the Right Model**:
   ```php
   // For quick transcription (faster, lower cost)
   AudioTranscribeModel::GPT_4O_MINI_TRANSCRIBE
   
   // For high accuracy with features (timestamps, translation)
   AudioTranscribeModel::WHISPER_1
   
   // For balanced accuracy and speed
   AudioTranscribeModel::GPT_4O_TRANSCRIBE
   ```

2. **Audio Format Optimization**:
   ```php
   // For TTS: Choose format based on use case
   AudioSpeechFormat::OPUS  // Best compression for web streaming
   AudioSpeechFormat::MP3   // Good balance for general use
   AudioSpeechFormat::WAV   // Highest quality for professional use
   ```

3. **File Size Management**:
   ```php
   // Split large files before processing
   function splitAudioFile(string $inputFile, int $segmentLengthMinutes = 30): array {
       // Implementation would use ffmpeg or similar tool
       // Return array of segment file paths
   }
   ```

## Next Steps

- [Configuration](configuration.md) - Set up OpenAI client for audio processing
- [Advanced Usage](advanced-usage.md) - Complex audio processing patterns
- [Error Handling](advanced-usage.md#error-handling-patterns) - Comprehensive error management
- [Examples](examples.md) - Interactive audio examples with real API calls