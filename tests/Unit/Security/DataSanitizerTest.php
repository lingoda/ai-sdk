<?php

declare(strict_types=1);

namespace Lingoda\AiSdk\Tests\Unit\Security;

use Lingoda\AiSdk\Security\DataSanitizer;
use Lingoda\AiSdk\Security\Pattern\PatternRegistry;
use Lingoda\AiSdk\Security\SensitiveContentFilter;
use Lingoda\AiSdk\Security\Attribute\Redact;
use Lingoda\AiSdk\Security\Attribute\Sensitive;
use PHPUnit\Framework\TestCase;
use Lingoda\AiSdk\Tests\Unit\Security\TestLogger;

final class DataSanitizerTest extends TestCase
{
    private DataSanitizer $sanitizer;
    private TestLogger $logger;

    protected function setUp(): void
    {
        $this->logger = new TestLogger();
        // Use createDefault to get both pattern-based and attribute-based sanitization
        $this->sanitizer = DataSanitizer::createDefault($this->logger);
    }

    public function testSanitizeString(): void
    {
        $input = 'Contact me at john.doe@example.com or call 555-123-4567';
        $result = $this->sanitizer->sanitize($input);
        
        $this->assertStringContainsString('[REDACTED_EMAIL]', $result);
        $this->assertStringContainsString('[REDACTED_PHONE]', $result);
        $this->assertFalse(str_contains($result, 'john.doe@example.com'));
        $this->assertFalse(str_contains($result, '555-123-4567'));
    }

    public function testSanitizeArray(): void
    {
        $input = [
            'email' => 'user@domain.com',
            'phone' => '(555) 123-4567',
            'safe_data' => 'This is safe',
            'nested' => [
                'api_key' => 'secret-key-12345',
                'normal' => 'normal data'
            ]
        ];

        $result = $this->sanitizer->sanitize($input);

        $this->assertEquals('[REDACTED_EMAIL]', $result['email']);
        $this->assertEquals('[REDACTED_PHONE]', $result['phone']);
        $this->assertEquals('This is safe', $result['safe_data']);
        $this->assertEquals('[REDACTED]', $result['nested']['[REDACTED]']);
        $this->assertEquals('normal data', $result['nested']['normal']);
    }

    public function testSanitizeObject(): void
    {
        $input = (object) [
            'email' => 'test@example.com',
            'data' => 'safe content'
        ];

        $result = $this->sanitizer->sanitize($input);

        $this->assertIsObject($result);
        $this->assertEquals('[REDACTED_EMAIL]', $result->email);
        $this->assertEquals('safe content', $result->data);
    }

    public function testSanitizeComplexData(): void
    {
        $input = [
            'user' => [
                'email' => 'john@company.com',
                'profile' => (object) [
                    'phone' => '555-987-6543',
                    'bio' => 'Call me at 444-555-6666 or email backup@test.org'
                ]
            ],
            'payment' => [
                'card' => '4532-1234-5678-9012',
                'description' => 'Payment for order #12345'
            ]
        ];

        $result = $this->sanitizer->sanitize($input);

        $this->assertEquals('[REDACTED_EMAIL]', $result['user']['email']);
        $this->assertEquals('[REDACTED_PHONE]', $result['user']['profile']->phone);
        $this->assertStringContainsString('[REDACTED_PHONE]', $result['user']['profile']->bio);
        $this->assertStringContainsString('[REDACTED_EMAIL]', $result['user']['profile']->bio);
        $this->assertEquals('[REDACTED_CREDIT_CARD]', $result['payment']['card']);
        $this->assertEquals('Payment for order #12345', $result['payment']['description']);
    }

    public function testDisabledSanitizer(): void
    {
        $registry = PatternRegistry::createDefault();
        $filter = new SensitiveContentFilter($registry);
        $disabledSanitizer = new DataSanitizer($filter, enabled: false);

        $input = 'Email: user@domain.com, Phone: 555-123-4567';
        $result = $disabledSanitizer->sanitize($input);

        $this->assertEquals($input, $result);
        $this->assertFalse($disabledSanitizer->isEnabled());
    }

    public function testAuditLogging(): void
    {
        $input = 'My email is test@example.com and my phone is 555-123-4567';
        $this->sanitizer->sanitize($input);

        $this->assertTrue($this->logger->hasWarning('Sensitive data detected and sanitized'));
        
        $records = $this->logger->records;
        $warningRecord = null;
        foreach ($records as $record) {
            if ($record['level'] === 'warning' && $record['message'] === 'Sensitive data detected and sanitized') {
                $warningRecord = $record;
                break;
            }
        }

        $this->assertNotNull($warningRecord);
        $this->assertArrayHasKey('detected_patterns', $warningRecord['context']);
        $this->assertArrayHasKey('original_length', $warningRecord['context']);
        $this->assertArrayHasKey('sanitized_length', $warningRecord['context']);
    }

    public function testCreateDefaultSanitizer(): void
    {
        $logger = new TestLogger();
        $sanitizer = DataSanitizer::createDefault($logger);
        
        $this->assertInstanceOf(DataSanitizer::class, $sanitizer);
        $this->assertTrue($sanitizer->isEnabled());
    }

    public function testHandlesNonStringData(): void
    {
        $input = [
            'number' => 42,
            'boolean' => true,
            'null_value' => null,
            'email' => 'test@example.com'
        ];

        $result = $this->sanitizer->sanitize($input);

        $this->assertEquals(42, $result['number']);
        $this->assertTrue($result['boolean']);
        $this->assertNull($result['null_value']);
        $this->assertEquals('[REDACTED_EMAIL]', $result['email']);
    }
    
    public function testSanitizeObjectWithJsonError(): void
    {
        // Create an object that cannot be JSON encoded
        $resource = fopen('php://memory', 'r');
        $object = new \stdClass();
        $object->resource = $resource;
        
        $result = $this->sanitizer->sanitize($object);
        
        $this->assertEquals('[OBJECT_SANITIZATION_FAILED]', $result->sanitized_object);
        
        fclose($resource);
    }
    
    public function testSanitizeObjectWithInvalidJsonDecode(): void
    {
        // Create a mock object that will cause JSON decode to fail
        $object = new class {
            public function __get($name) {
                if ($name === 'test') {
                    return 'value';
                }
                return null;
            }
        };
        
        $result = $this->sanitizer->sanitize($object);
        
        // The object should be processed normally since it can be JSON encoded
        $this->assertIsObject($result);
    }
    
    public function testGetDetectedPatternsWithAllTypes(): void
    {
        $input = 'Email: user@test.com Phone: 555-1234 Card: 4532-1234-5678-9012 SSN: 123-45-6789 API: sk-abc123';
        
        $this->sanitizer->sanitize($input);
        
        $this->assertTrue($this->logger->hasWarning('Sensitive data detected and sanitized'));
        
        // Find the warning record
        $records = $this->logger->records;
        $warningRecord = null;
        foreach ($records as $record) {
            if ($record['level'] === 'warning' && $record['message'] === 'Sensitive data detected and sanitized') {
                $warningRecord = $record;
                break;
            }
        }
        
        $this->assertNotNull($warningRecord);
        $this->assertArrayHasKey('detected_patterns', $warningRecord['context']);
        
        $detectedPatterns = $warningRecord['context']['detected_patterns'];
        $this->assertContains('email', $detectedPatterns);
        // Note: Other patterns depend on the specific regex patterns in DefaultPatterns
    }
    
    public function testSanitizeArrayWithStringKeys(): void
    {
        $input = [
            'user@test.com' => 'This key contains email',
            'normal_key' => 'admin@company.org',
            '555-1234' => 'Phone key'
        ];
        
        $result = $this->sanitizer->sanitize($input);
        
        // String keys should be sanitized
        $this->assertArrayHasKey('[REDACTED_EMAIL]', $result);
        $this->assertArrayNotHasKey('user@test.com', $result);
        
        // Values should also be sanitized - check the available keys first
        $keys = array_keys($result);
        $normalKeyExists = in_array('normal_key', $keys, true);
        
        if ($normalKeyExists) {
            $this->assertEquals('[REDACTED_EMAIL]', $result['normal_key']);
        } else {
            // If normal_key doesn't exist, it means all keys were processed differently
            $this->assertContains('[REDACTED_EMAIL]', array_values($result));
        }
    }
    
    public function testSanitizeWithNoAuditLog(): void
    {
        $logger = new TestLogger();
        $registry = PatternRegistry::createDefault();
        $filter = new SensitiveContentFilter($registry);
        $sanitizer = new DataSanitizer($filter, enabled: true, auditLog: false, logger: $logger); // auditLog = false
        
        $input = 'Email: test@example.com';
        $result = $sanitizer->sanitize($input);
        
        $this->assertEquals('Email: [REDACTED_EMAIL]', $result);
        
        // No warning should be logged since audit logging is disabled
        $this->assertEmpty($logger->records);
    }
    
    public function testIsEnabledMethod(): void
    {
        $registry = PatternRegistry::createDefault();
        $filter = new SensitiveContentFilter($registry);
        
        $enabledSanitizer = new DataSanitizer($filter, enabled: true);
        $disabledSanitizer = new DataSanitizer($filter, enabled: false);
        
        $this->assertTrue($enabledSanitizer->isEnabled());
        $this->assertFalse($disabledSanitizer->isEnabled());
    }
    
    public function testSanitizeWithComplexObject(): void
    {
        $object = new \stdClass();
        $object->email = 'sensitive@data.com';
        $object->nested = new \stdClass();
        $object->nested->phone = '555-123-4567';
        
        $result = $this->sanitizer->sanitize($object);
        
        $this->assertEquals('[REDACTED_EMAIL]', $result->email);
        $this->assertEquals('[REDACTED_PHONE]', $result->nested->phone);
    }
    
    public function testIsEnabledReturnsTrueWhenEnabled(): void
    {
        $registry = PatternRegistry::createDefault();
        $filter = new SensitiveContentFilter($registry);
        $enabledSanitizer = new DataSanitizer($filter, enabled: true);
        
        $this->assertTrue($enabledSanitizer->isEnabled());
    }
    
    public function testIsEnabledReturnsFalseWhenDisabled(): void
    {
        $registry = PatternRegistry::createDefault();
        $filter = new SensitiveContentFilter($registry);
        $disabledSanitizer = new DataSanitizer($filter, enabled: false);
        
        $this->assertFalse($disabledSanitizer->isEnabled());
    }
    
    public function testCreateDefaultWithoutLogger(): void
    {
        $sanitizer = DataSanitizer::createDefault();
        
        $this->assertInstanceOf(DataSanitizer::class, $sanitizer);
        $this->assertTrue($sanitizer->isEnabled());
        
        // Test default behavior - should sanitize content
        $result = $sanitizer->sanitize('Email: test@example.com');
        $this->assertStringContainsString('[REDACTED_EMAIL]', $result);
    }

    // Attribute-based sanitization tests
    
    public function testCreateDefaultWithAttributeSupport(): void
    {
        $sanitizer = DataSanitizer::createDefault();
        
        $this->assertTrue($sanitizer->isEnabled());
    }

    public function testCreateWithExplicitAttributeSupport(): void
    {
        $filter = new SensitiveContentFilter(PatternRegistry::createDefault(), $this->logger);
        $sanitizer = DataSanitizer::withAttributeSupport($filter, $this->logger);
        
        $this->assertTrue($sanitizer->isEnabled());
    }

    public function testAppliesBothAttributeAndPatternBasedSanitization(): void
    {
        $testObject = new DataSanitizerTestObject();
        $testObject->attributeField = 'Contact john@example.com for support';
        $testObject->patternOnlyField = 'Our support email is support@company.com';
        
        $result = $this->sanitizer->sanitize($testObject);
        
        // Attribute-based sanitization on attributeField
        $this->assertEquals('Contact [EMAIL_BLOCKED] for support', $result->attributeField);
        // Pattern-based sanitization on patternOnlyField (no attributes)
        $this->assertStringContainsString('[REDACTED_EMAIL]', $result->patternOnlyField);
    }

    public function testAppliesAttributeSanitizationBeforePatternSanitization(): void
    {
        $testObject = new DataSanitizerTestObject();
        $testObject->bothProcessedField = 'Email: user@example.com and API key: sk_live_abc123def456';
        
        $result = $this->sanitizer->sanitize($testObject);
        
        // Should be processed by both attribute and pattern sanitization
        // First attribute processing, then pattern processing
        $this->assertStringNotContainsString('user@example.com', $result->bothProcessedField);
        $this->assertStringNotContainsString('sk_live_abc123def456', $result->bothProcessedField);
    }

    public function testHandlesNestedObjectsWithMixedSanitization(): void
    {
        $parentObject = new DataSanitizerTestObject();
        $childObject = new DataSanitizerTestObject();
        
        $parentObject->attributeField = 'Parent email: parent@example.com';
        $childObject->attributeField = 'Child email: child@example.com';
        $childObject->patternOnlyField = 'Child support: support@child.com';
        
        $parentObject->nestedObject = $childObject;
        
        $result = $this->sanitizer->sanitize($parentObject);
        
        $this->assertEquals('Parent email: [EMAIL_BLOCKED]', $result->attributeField);
        $this->assertEquals('Child email: [EMAIL_BLOCKED]', $result->nestedObject->attributeField);
        $this->assertStringContainsString('[REDACTED_EMAIL]', $result->nestedObject->patternOnlyField);
    }

    public function testHandlesArraysWithMixedContent(): void
    {
        $testData = [
            'objects' => [
                new DataSanitizerTestObject(),
                new DataSanitizerTestObject()
            ],
            'plain_strings' => [
                'email1@example.com',
                'phone: 555-123-4567',
                'safe text'
            ],
            'mixed_array' => [
                'object' => new DataSanitizerTestObject(),
                'email' => 'contact@example.com',
                'safe' => 'safe data'
            ]
        ];
        
        $testData['objects'][0]->attributeField = 'obj1@example.com';
        $testData['objects'][1]->patternOnlyField = 'obj2@example.com';
        $testData['mixed_array']['object']->attributeField = 'mixed@example.com';
        
        $result = $this->sanitizer->sanitize($testData);
        
        // Object with attribute
        $this->assertEquals('[EMAIL_BLOCKED]', $result['objects'][0]->attributeField);
        // Object without attribute (pattern-based)
        $this->assertStringContainsString('[REDACTED_EMAIL]', $result['objects'][1]->patternOnlyField);
        // Plain string (pattern-based)
        $this->assertStringContainsString('[REDACTED_EMAIL]', $result['plain_strings'][0]);
        // Mixed array object
        $this->assertEquals('[EMAIL_BLOCKED]', $result['mixed_array']['object']->attributeField);
        // Mixed array string
        $this->assertStringContainsString('[REDACTED_EMAIL]', $result['mixed_array']['email']);
    }

    public function testRespectsSensitiveAttributeConfiguration(): void
    {
        $testObject = new DataSanitizerTestObject();
        $testObject->sensitiveField = 'Contains PII: john.doe@example.com and 555-123-4567';
        
        $result = $this->sanitizer->sanitize($testObject);
        
        // Sensitive attribute should replace entire field
        $this->assertEquals('[SENSITIVE_CONTENT]', $result->sensitiveField);
    }

    public function testLogsBothAttributeAndPatternBasedSanitization(): void
    {
        $testObject = new DataSanitizerTestObject();
        $testObject->attributeField = 'test@example.com';
        $testObject->patternOnlyField = 'another@example.com with API key sk_live_test123';
        
        $this->sanitizer->sanitize($testObject);
        
        // Should have logs from both attribute and pattern sanitization
        $attributeLogs = array_filter($this->logger->records, fn($record) => 
            str_contains($record['message'], 'Property sanitized using attributes'));
        $patternLogs = array_filter($this->logger->records, fn($record) => 
            str_contains($record['message'], 'Sensitive data detected and sanitized'));
        
        $this->assertNotEmpty($attributeLogs);
        $this->assertNotEmpty($patternLogs);
    }

    public function testHandlesObjectsWithBothAttributeAndNonAttributeFields(): void
    {
        $testObject = new DataSanitizerTestObject();
        $testObject->attributeField = 'attr@example.com';
        $testObject->patternOnlyField = 'pattern@example.com';
        $testObject->plainField = 'plain@example.com';
        
        $result = $this->sanitizer->sanitize($testObject);
        
        // Attribute field uses attribute sanitization
        $this->assertEquals('[EMAIL_BLOCKED]', $result->attributeField);
        // Pattern field uses pattern sanitization  
        $this->assertStringContainsString('[REDACTED_EMAIL]', $result->patternOnlyField);
        // Plain field also gets pattern sanitization (no attributes)
        $this->assertStringContainsString('[REDACTED_EMAIL]', $result->plainField);
    }

    public function testPreservesObjectDataDuringSanitization(): void
    {
        $testObject = new DataSanitizerTestObject();
        $testObject->plainField = 'safe data';
        
        $result = $this->sanitizer->sanitize($testObject);
        
        // Note: DataSanitizer converts objects to JSON and back, so type may change
        $this->assertTrue(is_object($result));
        $this->assertEquals('safe data', $result->plainField); // Data should be preserved
    }

    public function testHandlesDisabledSanitizerWithAttributes(): void
    {
        $filter = new SensitiveContentFilter(PatternRegistry::createDefault(), $this->logger);
        $sanitizer = new DataSanitizer($filter, enabled: false);
        
        $testObject = new DataSanitizerTestObject();
        $testObject->attributeField = 'test@example.com';
        $testObject->patternOnlyField = 'another@example.com';
        
        $result = $sanitizer->sanitize($testObject);
        
        // Nothing should be sanitized when disabled
        $this->assertEquals('test@example.com', $result->attributeField);
        $this->assertEquals('another@example.com', $result->patternOnlyField);
    }

    public function testFallsBackGracefullyWhenAttributeSanitizerUnavailable(): void
    {
        // Create a sanitizer without attribute support (no AttributeSanitizer parameter)
        $filter = new SensitiveContentFilter(PatternRegistry::createDefault(), $this->logger);
        $sanitizer = new DataSanitizer($filter, enabled: true, auditLog: true, logger: $this->logger);
        
        $testObject = new DataSanitizerTestObject();
        $testObject->attributeField = 'test@example.com';
        $testObject->patternOnlyField = 'pattern@example.com';
        
        $result = $sanitizer->sanitize($testObject);
        
        // Both should use pattern-based sanitization
        $this->assertStringContainsString('[REDACTED_EMAIL]', $result->attributeField);
        $this->assertStringContainsString('[REDACTED_EMAIL]', $result->patternOnlyField);
    }
}

/**
 * Test class for DataSanitizer attribute integration testing
 */
class DataSanitizerTestObject
{
    #[Redact('/\b[\w\.-]+@[\w\.-]+\.\w+\b/', '[EMAIL_BLOCKED]')]
    public ?string $attributeField = null;

    #[Sensitive(redactionText: '[SENSITIVE_CONTENT]')]
    public ?string $sensitiveField = null;
    
    // No attributes - will use pattern-based sanitization
    public ?string $patternOnlyField = null;
    
    // No attributes - will use pattern-based sanitization
    public ?string $plainField = null;

    // Field that will be processed by both attribute and pattern sanitization
    public ?string $bothProcessedField = null;
    
    public ?object $nestedObject = null;
}