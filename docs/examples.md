# Interactive Examples

This guide shows you how to run comprehensive interactive examples that demonstrate all SDK features with real AI providers.

## Running the Examples

### Offline Mode (No API Keys Required)

```bash
php docs/usage-example.php
```

Shows model information, capabilities, and demonstrates the SDK architecture without making actual API calls.

### With API Keys (Live Examples)

Set environment variables for the providers you want to test:

```bash
# OpenAI only
OPENAI_API_KEY=sk-your-actual-key php docs/usage-example.php

# Multiple providers
OPENAI_API_KEY=sk-proj-your-openai-key \
ANTHROPIC_API_KEY=sk-ant-your-anthropic-key \
GEMINI_API_KEY=your-gemini-key \
php docs/usage-example.php

# With OpenAI organization
OPENAI_API_KEY=sk-your-key \
OPENAI_ORG=org-your-org-id \
php docs/usage-example.php
```

### Alternative Environment Variable Names

The script accepts multiple environment variable names for flexibility:

- `OPENAI_API_KEY` or `OPENAI_KEY`
- `ANTHROPIC_API_KEY` or `ANTHROPIC_KEY`
- `GEMINI_API_KEY` or `GEMINI_KEY`
- `OPENAI_ORG` or `OPENAI_ORGANIZATION`

## What the Examples Demonstrate

### Always Available (Offline)
- Model information and capabilities
- Provider abstractions
- Model capability checking and information

### API-Dependent (Online with Keys)
- **Platform Usage**: Real API calls with different input formats
- **Rate Limiting**: Token-based rate limiting in action
- **Audio Capabilities**: Text-to-speech using OpenAI (requires OpenAI key)
- **Security Features**: Data sanitization and attribute-based protection
- **Client Factory Configuration**: Advanced HTTP client setup with proxies, timeouts, etc.
- **Error Handling**: Various error scenarios and exception handling

## Example Output

### Offline Mode
```
ℹ️  No API keys detected. Running offline examples only.
💡 To test with real APIs, set environment variables:
   - OPENAI_API_KEY (or OPENAI_KEY)
   - ANTHROPIC_API_KEY (or ANTHROPIC_KEY)
   - GEMINI_API_KEY (or GEMINI_KEY)
   - OPENAI_ORG (optional OpenAI organization)

=== Model Capabilities ===
GPT-4o capabilities: text, tools, vision, multimodal
GPT-5 capabilities: text, tools, vision, multimodal, reasoning
Claude-3-5-Sonnet capabilities: text, tools, vision, multimodal
```

### With API Keys
```
🔑 API Keys detected for: OpenAI, Anthropic, Gemini
🚀 Running live API examples...

=== Platform Usage Examples ===
✓ OpenAI client configured
✓ Anthropic client configured  
✓ Gemini client configured
Using model: GPT-4o Mini from OpenAI

Simple response: Hello back!
Structured response: PHP is a server-side scripting language primarily used for web development...
Controlled response: Python
```

## Features Demonstrated

### Core Platform Features
- **Multi-Provider Support**: Automatic detection and configuration of OpenAI, Anthropic, and Gemini
- **Model Capability System**: Check model capabilities and choose appropriate models
- **Type-Safe Prompts**: Strongly-typed prompt value objects with parameter substitution
- **Conversation Management**: Multi-role conversation handling

### Security Features
- **Data Sanitization**: Automatic detection and redaction of sensitive information
- **Attribute-Based Protection**: Custom security policies using PHP attributes
- **Audit Logging**: Security event monitoring and logging

### Advanced Features
- **Rate Limiting**: Built-in token-based rate limiting with intelligent estimation
- **Audio Processing**: Speech-to-text with multiple OpenAI audio models
- **Error Handling**: Comprehensive exception handling with ClientException, UnsupportedCapabilityException, and other specific error types
- **Capability Validation**: Strict model capability checking with CapabilityValidator
- **Client Configuration**: Advanced HTTP client setup with retries and proxies

## Important Notes

- The script automatically detects available API keys and only runs examples for configured providers
- Invalid API keys will show authentication errors but demonstrate that the SDK is working correctly
- Audio examples only run with OpenAI API keys (OpenAI-specific feature)
- All examples use cost-effective models (e.g., `gpt-4o-mini`, `claude-3-5-haiku-20241022`, `gemini-2.5-flash`)
- Error messages in the "Error Handling Patterns" section are **intentional demonstrations** of proper error handling, not actual issues

## Next Steps

- [Quick Start](quick-start.md) - Your first AI request
- [Configuration](configuration.md) - Detailed provider setup
- [Security](security.md) - Data protection implementation
- [Advanced Usage](advanced-usage.md) - Complex features and patterns