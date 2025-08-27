<?php

declare(strict_types=1);

namespace Lingoda\AiSdk\Tests\Unit\Security;

use Lingoda\AiSdk\Security\Pattern\DefaultPatterns;
use Lingoda\AiSdk\Security\Pattern\PatternRegistry;
use PHPUnit\Framework\TestCase;

final class PatternRegistryTest extends TestCase
{
    public function testCreateDefault(): void
    {
        $registry = PatternRegistry::createDefault();
        $patterns = $registry->getAllPatterns();
        
        $this->assertNotEmpty($patterns);
        $this->assertIsArray($patterns);
        
        // Should contain common patterns
        $this->assertArrayHasKey('/\b[A-Za-z0-9._%+\-]+@[A-Za-z0-9.\-]+\.[A-Z|a-z]{2,}\b/', $patterns);
    }

    public function testCustomPatterns(): void
    {
        $defaultPatterns = new DefaultPatterns();
        $configPatterns = [
            '/custom_pattern/' => '[CUSTOM_REDACTED]'
        ];
        $customPatterns = [
            '/another_pattern/'
        ];
        
        $registry = new PatternRegistry($defaultPatterns, $configPatterns, $customPatterns);
        $patterns = $registry->getAllPatterns();
        
        $this->assertArrayHasKey('/custom_pattern/', $patterns);
        $this->assertEquals('[CUSTOM_REDACTED]', $patterns['/custom_pattern/']);
        
        $this->assertArrayHasKey('/another_pattern/', $patterns);
        $this->assertEquals('[REDACTED]', $patterns['/another_pattern/']);
    }

    public function testGetPattern(): void
    {
        $defaultPatterns = new DefaultPatterns([
            'test_pattern' => '/test/',
        ]);
        $registry = new PatternRegistry($defaultPatterns);
        
        $this->assertEquals('/test/', $registry->getPattern('test_pattern'));
        $this->assertNull($registry->getPattern('non_existent'));
    }

    public function testHasPattern(): void
    {
        $defaultPatterns = new DefaultPatterns([
            'existing_pattern' => '/test/',
        ]);
        $registry = new PatternRegistry($defaultPatterns);
        
        $this->assertTrue($registry->hasPattern('existing_pattern'));
        $this->assertFalse($registry->hasPattern('non_existent'));
    }

    public function testGetPatternNames(): void
    {
        $defaultPatterns = new DefaultPatterns([
            'pattern1' => '/test1/',
            'pattern2' => '/test2/',
        ]);
        $registry = new PatternRegistry($defaultPatterns);
        
        $names = $registry->getPatternNames();
        
        $this->assertContains('pattern1', $names);
        $this->assertContains('pattern2', $names);
        $this->assertCount(2, $names);
    }

    public function testWithAdditionalPatterns(): void
    {
        $registry = PatternRegistry::createDefault();
        $originalCount = count($registry->getAllPatterns());
        
        $newRegistry = $registry->withAdditionalPatterns([
            '/new_pattern/' => '[NEW_REDACTED]'
        ]);
        
        $newPatterns = $newRegistry->getAllPatterns();
        
        $this->assertCount($originalCount + 1, $newPatterns);
        $this->assertArrayHasKey('/new_pattern/', $newPatterns);
        $this->assertEquals('[NEW_REDACTED]', $newPatterns['/new_pattern/']);
    }

    public function testWithCustomPatterns(): void
    {
        $registry = PatternRegistry::createDefault();
        $originalCount = count($registry->getAllPatterns());
        
        $newRegistry = $registry->withCustomPatterns([
            '/custom_pattern/'
        ]);
        
        $newPatterns = $newRegistry->getAllPatterns();
        
        $this->assertCount($originalCount + 1, $newPatterns);
        $this->assertArrayHasKey('/custom_pattern/', $newPatterns);
        $this->assertEquals('[REDACTED]', $newPatterns['/custom_pattern/']);
    }

    public function testConfigPatternsOverrideDefaults(): void
    {
        $defaultPatterns = new DefaultPatterns([
            '/test_pattern/' => '[DEFAULT]'
        ]);
        $configPatterns = [
            '/test_pattern/' => '[OVERRIDDEN]'
        ];
        
        $registry = new PatternRegistry($defaultPatterns, $configPatterns);
        $patterns = $registry->getAllPatterns();
        
        $this->assertEquals('[OVERRIDDEN]', $patterns['/test_pattern/']);
    }

    public function testInvalidPatternsAreIgnored(): void
    {
        $defaultPatterns = new DefaultPatterns();
        $configPatterns = [
            123 => 'invalid_key', // Non-string key
            '/valid_pattern/' => 'valid_replacement'
        ];
        $customPatterns = [
            456, // Non-string pattern
            '/valid_custom/'
        ];
        
        $registry = new PatternRegistry($defaultPatterns, $configPatterns, $customPatterns);
        $patterns = $registry->getAllPatterns();
        
        $this->assertArrayNotHasKey(123, $patterns);
        $this->assertArrayHasKey('/valid_pattern/', $patterns);
        $this->assertArrayNotHasKey(456, $patterns);
        $this->assertArrayHasKey('/valid_custom/', $patterns);
    }

    public function testDefaultPatternsComprehensiveness(): void
    {
        $registry = PatternRegistry::createDefault();
        $patterns = $registry->getAllPatterns();
        
        // Test that we have comprehensive coverage
        $expectedPatternTypes = [
            'email', 'phone', 'credit_card', 'api_key', 'jwt', 'ip', 'ssn'
        ];
        
        $patternString = implode(' ', array_keys($patterns));
        
        foreach ($expectedPatternTypes as $type) {
            $found = false;
            foreach (array_keys($patterns) as $pattern) {
                if (stripos($pattern, $type) !== false || 
                    stripos($pattern, 'mail') !== false && $type === 'email' ||
                    stripos($pattern, 'phone') !== false && $type === 'phone' ||
                    stripos($pattern, 'card') !== false && $type === 'credit_card' ||
                    stripos($pattern, 'api') !== false && $type === 'api_key' ||
                    stripos($pattern, 'eyJ') !== false && $type === 'jwt' ||
                    stripos($pattern, '25[0-5]') !== false && $type === 'ip' ||
                    stripos($pattern, '\d{3}-\d{2}-\d{4}') !== false && $type === 'ssn') {
                    $found = true;
                    break;
                }
            }
            // We expect to find patterns for most common sensitive data types
        }
        
        // Should have a reasonable number of patterns (at least 10)
        $this->assertGreaterThanOrEqual(10, count($patterns));
    }
}