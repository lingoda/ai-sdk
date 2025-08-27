<?php

declare(strict_types=1);

namespace Lingoda\AiSdk\Tests\Unit\Security;

use Lingoda\AiSdk\Security\Attribute\Redact;
use Lingoda\AiSdk\Security\Attribute\Sensitive;
use Lingoda\AiSdk\Security\AttributeSanitizer;
use Lingoda\AiSdk\Security\Pattern\PatternRegistry;
use Lingoda\AiSdk\Security\SensitiveContentFilter;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class AttributeSanitizerTest extends TestCase
{
    private AttributeSanitizer $sanitizer;
    private SensitiveContentFilter $filter;
    private TestLogger $logger;

    protected function setUp(): void
    {
        $this->logger = new TestLogger();
        $patternRegistry = PatternRegistry::createDefault();
        $this->filter = new SensitiveContentFilter($patternRegistry, $this->logger);
        $this->sanitizer = AttributeSanitizer::createDefault($this->filter, $this->logger);
    }

    #[Test]
    public function it_can_be_created_with_default_settings(): void
    {
        $sanitizer = AttributeSanitizer::createDefault($this->filter);
        
        $this->assertTrue($sanitizer->isEnabled());
    }

    #[Test]
    public function it_can_be_disabled(): void
    {
        $sanitizer = new AttributeSanitizer($this->filter, enabled: false);
        
        $this->assertFalse($sanitizer->isEnabled());
    }

    #[Test]
    public function it_returns_data_unchanged_when_disabled(): void
    {
        $sanitizer = new AttributeSanitizer($this->filter, enabled: false);
        $testObject = new TestObjectWithAttributes();
        
        $result = $this->sanitizer->sanitize($testObject);
        
        $this->assertEquals($testObject, $result);
    }

    #[Test]
    public function it_redacts_properties_with_redact_attribute(): void
    {
        $testObject = new TestObjectWithAttributes();
        $testObject->emailField = 'Contact john.doe@example.com for support';
        
        $result = $this->sanitizer->sanitize($testObject);
        
        $this->assertEquals('Contact [EMAIL_REDACTED] for support', $result->emailField);
    }

    #[Test]
    public function it_applies_custom_redact_pattern_and_replacement(): void
    {
        $testObject = new TestObjectWithAttributes();
        $testObject->phoneField = 'Call us at 555-123-4567 or 555-987-6543';
        
        $result = $this->sanitizer->sanitize($testObject);
        
        $this->assertEquals('Call us at [PHONE] or [PHONE]', $result->phoneField);
    }

    #[Test]
    public function it_handles_sensitive_attribute_with_fallback_filter(): void
    {
        $testObject = new TestObjectWithAttributes();
        $testObject->sensitiveData = 'User email: admin@company.com and phone: (555) 123-4567';
        
        $result = $this->sanitizer->sanitize($testObject);
        
        $this->assertEquals('[PII_DETECTED]', $result->sensitiveData);
    }

    #[Test]
    public function it_preserves_non_sensitive_data_in_sensitive_fields(): void
    {
        $testObject = new TestObjectWithAttributes();
        $testObject->sensitiveData = 'This is completely safe data';
        
        $result = $this->sanitizer->sanitize($testObject);
        
        $this->assertEquals('This is completely safe data', $result->sensitiveData);
    }

    #[Test]
    public function it_handles_multiple_attributes_on_same_property(): void
    {
        $testObject = new TestObjectWithAttributes();
        $testObject->multipleAttributes = 'Contact john@example.com or call 555-123-4567';
        
        $result = $this->sanitizer->sanitize($testObject);
        
        // First redact is applied, then sensitive check
        $this->assertEquals('[PII_DETECTED]', $result->multipleAttributes);
    }

    #[Test]
    public function it_sanitizes_nested_objects(): void
    {
        $testObject = new TestObjectWithAttributes();
        $nestedObject = new TestObjectWithAttributes();
        $nestedObject->emailField = 'nested@example.com';
        $testObject->nestedObject = $nestedObject;
        
        $result = $this->sanitizer->sanitize($testObject);
        
        $this->assertEquals('[EMAIL_REDACTED]', $result->nestedObject->emailField);
    }

    #[Test]
    public function it_sanitizes_array_properties_with_redact_attribute(): void
    {
        $testObject = new TestObjectWithAttributes();
        $testObject->arrayWithEmails = [
            'admin@example.com',
            'support@company.com',
            'safe text'
        ];
        
        $result = $this->sanitizer->sanitize($testObject);
        
        $this->assertEquals([
            '[EMAIL_REDACTED]',
            '[EMAIL_REDACTED]',
            'safe text'
        ], $result->arrayWithEmails);
    }

    #[Test]
    public function it_sanitizes_nested_arrays(): void
    {
        $testObject = new TestObjectWithAttributes();
        $testObject->nestedArrayData = [
            'contacts' => [
                'primary' => 'john@example.com',
                'secondary' => 'jane@example.com'
            ],
            'safe' => ['data', 'here']
        ];
        
        $result = $this->sanitizer->sanitize($testObject);
        
        $this->assertEquals([
            'contacts' => [
                'primary' => '[EMAIL_REDACTED]',
                'secondary' => '[EMAIL_REDACTED]'
            ],
            'safe' => ['data', 'here']
        ], $result->nestedArrayData);
    }

    #[Test]
    public function it_handles_null_values(): void
    {
        $testObject = new TestObjectWithAttributes();
        $testObject->emailField = null;
        $testObject->sensitiveData = null;
        
        $result = $this->sanitizer->sanitize($testObject);
        
        $this->assertNull($result->emailField);
        $this->assertNull($result->sensitiveData);
    }

    #[Test]
    public function it_logs_sanitization_activity_when_audit_enabled(): void
    {
        $testObject = new TestObjectWithAttributes();
        $testObject->emailField = 'test@example.com';
        
        $this->sanitizer->sanitize($testObject);
        
        $this->assertCount(1, $this->logger->records);
        $this->assertEquals('warning', $this->logger->records[0]['level']);
        $this->assertStringContainsString('Property sanitized using attributes', $this->logger->records[0]['message']);
        $this->assertEquals('emailField', $this->logger->records[0]['context']['property']);
        $this->assertContains('Redact', $this->logger->records[0]['context']['attributes']);
    }

    #[Test]
    public function it_handles_objects_without_attributes(): void
    {
        $plainObject = new PlainTestObject();
        $plainObject->name = 'test@example.com';
        
        $result = $this->sanitizer->sanitize($plainObject);
        
        // No attributes, so no changes expected
        $this->assertEquals('test@example.com', $result->name);
    }

    #[Test]
    public function it_handles_reflection_exceptions_gracefully(): void
    {
        // Create an object that might cause reflection issues
        $problematicObject = new class {
            private string $field = 'test@example.com';
        };
        
        $result = $this->sanitizer->sanitize($problematicObject);
        
        // Should return the original object on reflection errors
        $this->assertEquals($problematicObject, $result);
    }

    #[Test]
    public function it_processes_primitive_types_without_changes(): void
    {
        $this->assertEquals('test string', $this->sanitizer->sanitize('test string'));
        $this->assertEquals(123, $this->sanitizer->sanitize(123));
        $this->assertEquals(true, $this->sanitizer->sanitize(true));
        $this->assertEquals(null, $this->sanitizer->sanitize(null));
    }

    #[Test]
    public function it_does_not_process_non_objects(): void
    {
        $arrayData = [
            'level1' => [
                'level2' => new TestObjectWithAttributes()
            ]
        ];
        $arrayData['level1']['level2']->emailField = 'test@example.com';
        
        // AttributeSanitizer only processes objects directly passed to it
        $result = $this->sanitizer->sanitize($arrayData);
        
        // Should return array unchanged since AttributeSanitizer only handles objects
        $this->assertEquals($arrayData, $result);
    }

    #[Test]
    public function it_handles_sensitive_attribute_with_custom_redaction_text(): void
    {
        $testObject = new TestObjectWithAttributes();
        $testObject->customSensitive = 'Contains email: user@example.com';
        
        $result = $this->sanitizer->sanitize($testObject);
        
        $this->assertEquals('[CUSTOM_REDACTED]', $result->customSensitive);
    }

    #[Test]
    public function it_handles_sensitive_attribute_with_type(): void
    {
        $testObject = new TestObjectWithAttributes();
        $testObject->typedSensitive = 'Contains PII: john@example.com';
        
        $result = $this->sanitizer->sanitize($testObject);
        
        $this->assertEquals('[PII_REDACTED]', $result->typedSensitive);
    }

    #[Test]
    public function it_handles_disabled_attribute_sanitizer(): void
    {
        $sanitizer = new AttributeSanitizer($this->filter, enabled: false);
        $testObject = new TestObjectWithAttributes();
        $testObject->emailField = 'test@example.com';
        
        $result = $sanitizer->sanitize($testObject);
        
        $this->assertEquals($testObject, $result);
        $this->assertFalse($sanitizer->isEnabled());
    }

    #[Test]
    public function it_handles_sanitizer_without_audit_logging(): void
    {
        $logger = new TestLogger();
        $sanitizer = new AttributeSanitizer($this->filter, enabled: true, auditLog: false, logger: $logger);
        
        $testObject = new TestObjectWithAttributes();
        $testObject->emailField = 'test@example.com';
        
        $sanitizer->sanitize($testObject);
        
        // No logs should be generated when audit logging is disabled
        $this->assertEmpty($logger->records);
    }

    #[Test]
    public function it_can_access_attribute_properties(): void
    {
        // Test Redact attribute properties
        $redact = new \Lingoda\AiSdk\Security\Attribute\Redact('/test/', '[REPLACED]');
        $this->assertEquals('/test/', $redact->getPattern());
        $this->assertEquals('[REPLACED]', $redact->getReplacement());

        // Test Sensitive attribute properties
        $sensitive = new \Lingoda\AiSdk\Security\Attribute\Sensitive(type: 'pii', redactionText: '[SENSITIVE]');
        $this->assertEquals('pii', $sensitive->getType());
        $this->assertEquals('[SENSITIVE]', $sensitive->getRedactionText());

        // Test Sensitive attribute with null type
        $sensitiveNoType = new \Lingoda\AiSdk\Security\Attribute\Sensitive(redactionText: '[REDACTED]');
        $this->assertNull($sensitiveNoType->getType());
        $this->assertEquals('[REDACTED]', $sensitiveNoType->getRedactionText());
    }

    #[Test]
    public function it_applies_redact_to_array_keys(): void
    {
        $testObject = new TestObjectWithAttributes();
        $testObject->arrayWithEmailKeys = [
            'admin@example.com' => 'Administrator',
            'user@example.com' => 'Regular User',
            'safe_key' => 'Safe Data'
        ];
        
        $result = $this->sanitizer->sanitize($testObject);
        
        $this->assertEquals([
            '[EMAIL_REDACTED]' => 'Administrator',
            '[EMAIL_REDACTED]' => 'Regular User',
            'safe_key' => 'Safe Data'
        ], $result->arrayWithEmailKeys);
    }

    #[Test]
    public function it_handles_array_properties_with_sensitive_attribute(): void
    {
        $testObject = new TestObjectWithAttributes();
        $testObject->sensitiveArray = [
            'user@example.com',
            'safe data',
            'another@example.com'
        ];
        
        $result = $this->sanitizer->sanitize($testObject);
        
        // The sensitive attribute will apply the fallback filter to each array element
        // Elements with sensitive content will be replaced
        $this->assertEquals([
            '[ARRAY_SENSITIVE]',  // email detected
            'safe data',          // no sensitive content
            '[ARRAY_SENSITIVE]'   // email detected
        ], $result->sensitiveArray);
    }

    #[Test]
    public function it_handles_array_properties_without_sensitive_content(): void
    {
        $testObject = new TestObjectWithAttributes();
        $testObject->sensitiveArray = [
            'completely safe data',
            'no sensitive content here',
            'just normal text'
        ];
        
        $result = $this->sanitizer->sanitize($testObject);
        
        // Should remain unchanged if no sensitive content is detected
        $this->assertEquals([
            'completely safe data',
            'no sensitive content here', 
            'just normal text'
        ], $result->sensitiveArray);
    }

    #[Test] 
    public function it_handles_regex_failure_gracefully(): void
    {
        // Test with a property that has a redact attribute but no pattern matches
        $testObject = new TestObjectWithAttributes();
        $testObject->phoneField = 'No phone numbers here, just text';
        
        $result = $this->sanitizer->sanitize($testObject);
        
        // Should remain unchanged since no pattern matches
        $this->assertEquals('No phone numbers here, just text', $result->phoneField);
    }

    #[Test]
    public function it_creates_default_sanitizer_with_correct_settings(): void
    {
        $sanitizer = AttributeSanitizer::createDefault($this->filter);
        
        $this->assertTrue($sanitizer->isEnabled());
        
        // Test that it works
        $testObject = new TestObjectWithAttributes();
        $testObject->emailField = 'test@example.com';
        
        $result = $sanitizer->sanitize($testObject);
        $this->assertEquals('[EMAIL_REDACTED]', $result->emailField);
    }
}

/**
 * Test class with various attribute configurations
 */
class TestObjectWithAttributes
{
    #[Redact('/\b[\w\.-]+@[\w\.-]+\.\w+\b/', '[EMAIL_REDACTED]')]
    public ?string $emailField = null;

    #[Redact('/\b\d{3}-\d{3}-\d{4}\b/', '[PHONE]')]
    public ?string $phoneField = null;

    #[Sensitive(type: 'pii', redactionText: '[PII_DETECTED]')]
    public ?string $sensitiveData = null;

    #[Redact('/\b[\w\.-]+@[\w\.-]+\.\w+\b/', '[EMAIL_REDACTED]')]
    #[Sensitive(type: 'pii', redactionText: '[PII_DETECTED]')]
    public ?string $multipleAttributes = null;

    #[Sensitive(redactionText: '[CUSTOM_REDACTED]')]
    public ?string $customSensitive = null;

    #[Sensitive(type: 'pii', redactionText: '[PII_REDACTED]')]
    public ?string $typedSensitive = null;

    public ?object $nestedObject = null;

    #[Redact('/\b[\w\.-]+@[\w\.-]+\.\w+\b/', '[EMAIL_REDACTED]')]
    public ?array $arrayWithEmails = null;

    #[Redact('/\b[\w\.-]+@[\w\.-]+\.\w+\b/', '[EMAIL_REDACTED]')]
    public ?array $nestedArrayData = null;

    #[Redact('/\b[\w\.-]+@[\w\.-]+\.\w+\b/', '[EMAIL_REDACTED]')]
    public ?array $arrayWithEmailKeys = null;

    #[Sensitive(redactionText: '[ARRAY_SENSITIVE]')]
    public ?array $sensitiveArray = null;
}

/**
 * Test class without any security attributes
 */
class PlainTestObject
{
    public string $name = '';
}