<?php

declare(strict_types=1);

namespace Lingoda\AiSdk\Tests\Unit\Security;

use Lingoda\AiSdk\Security\Pattern\DefaultPatterns;
use Lingoda\AiSdk\Security\Pattern\PatternRegistry;
use Lingoda\AiSdk\Security\SensitiveContentFilter;
use PHPUnit\Framework\TestCase;
use Lingoda\AiSdk\Tests\Unit\Security\TestLogger;

final class SensitiveContentFilterTest extends TestCase
{
    private SensitiveContentFilter $filter;
    private TestLogger $logger;

    protected function setUp(): void
    {
        $this->logger = new TestLogger();
        $registry = PatternRegistry::createDefault();
        $this->filter = new SensitiveContentFilter($registry, $this->logger);
    }

    public function testFilterEmail(): void
    {
        $content = 'Please contact us at support@company.com for help.';
        $filtered = $this->filter->filter($content);
        
        $this->assertStringContainsString('[REDACTED_EMAIL]', $filtered);
        $this->assertFalse(str_contains($filtered, 'support@company.com'));
    }

    public function testFilterPhone(): void
    {
        $content = 'Call us at (555) 123-4567 or 555-987-6543.';
        $filtered = $this->filter->filter($content);
        
        $this->assertStringContainsString('[REDACTED_PHONE]', $filtered);
        $this->assertFalse(str_contains($filtered, '(555) 123-4567'));
        $this->assertFalse(str_contains($filtered, '555-987-6543'));
    }

    public function testFilterCreditCard(): void
    {
        $content = 'Card number: 4532 1234 5678 9012';
        $filtered = $this->filter->filter($content);
        
        $this->assertStringContainsString('[REDACTED_CREDIT_CARD]', $filtered);
        $this->assertFalse(str_contains($filtered, '4532 1234 5678 9012'));
    }

    public function testFilterApiKey(): void
    {
        $content = 'Use api_key=sk_live_abcd1234567890 for authentication';
        $filtered = $this->filter->filter($content);
        
        $this->assertStringContainsString('api_key=[REDACTED]', $filtered);
        $this->assertFalse(str_contains($filtered, 'sk_live_abcd1234567890'));
    }

    public function testFilterJWT(): void
    {
        $content = 'JWT token: eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOiIxMjM0NTY3ODkwIiwibmFtZSI6IkpvaG4gRG9lIiwiaWF0IjoxNTE2MjM5MDIyfQ.SflKxwRJSMeKKF2QT4fwpMeJf36POk6yJV_adQssw5c';
        $filtered = $this->filter->filter($content);
        
        $this->assertStringContainsString('[REDACTED_JWT]', $filtered);
        $this->assertFalse(str_contains($filtered, 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9'));
    }

    public function testFilterIPAddress(): void
    {
        $content = 'Server IP: 192.168.1.100 and backup: 10.0.0.1';
        $filtered = $this->filter->filter($content);
        
        $this->assertStringContainsString('[REDACTED_IP]', $filtered);
        $this->assertFalse(str_contains($filtered, '192.168.1.100'));
        $this->assertFalse(str_contains($filtered, '10.0.0.1'));
    }

    public function testFilterWithSpecificPattern(): void
    {
        $registry = new PatternRegistry(
            new DefaultPatterns([
                'email_pattern' => '/\b[A-Za-z0-9._%+\-]+@[A-Za-z0-9.\-]+\.[A-Z|a-z]{2,}\b/'
            ])
        );
        $filter = new SensitiveContentFilter($registry);
        
        $content = 'Email: test@example.com and phone: 555-123-4567';
        $filtered = $filter->filterWithPattern($content, 'email_pattern', '[EMAIL_REMOVED]');
        
        $this->assertStringContainsString('[EMAIL_REMOVED]', $filtered);
        $this->assertStringContainsString('555-123-4567', $filtered); // Phone should remain
    }

    public function testLogging(): void
    {
        $content = 'Contact: admin@example.com';
        $this->filter->filter($content);
        
        $this->assertTrue($this->logger->hasDebug('Sensitive content filtered'));
    }

    public function testMultiplePatternsInSingleString(): void
    {
        $content = 'User: john@company.com, Phone: 555-123-4567, Card: 4532123456789012, API: api_key=secret123';
        $filtered = $this->filter->filter($content);
        
        $this->assertStringContainsString('[REDACTED_EMAIL]', $filtered);
        $this->assertStringContainsString('[REDACTED_PHONE]', $filtered);
        $this->assertStringContainsString('[REDACTED_CREDIT_CARD]', $filtered);
        $this->assertStringContainsString('api_key=[REDACTED]', $filtered);
        
        // Original sensitive data should be gone
        $this->assertFalse(str_contains($filtered, 'john@company.com'));
        $this->assertFalse(str_contains($filtered, '555-123-4567'));
        $this->assertFalse(str_contains($filtered, '4532123456789012'));
        $this->assertFalse(str_contains($filtered, 'secret123'));
    }

    public function testEmptyStringHandling(): void
    {
        $result = $this->filter->filter('');
        $this->assertEquals('', $result);
    }

    public function testCaseInsensitivePatterns(): void
    {
        $content = 'PASSWORD=mysecretpass and API_KEY=12345';
        $filtered = $this->filter->filter($content);
        
        $this->assertStringContainsString('password=[REDACTED]', $filtered);
        $this->assertStringContainsString('api_key=[REDACTED]', $filtered);
    }
    
    public function testGetPatternRegistry(): void
    {
        $registry = $this->filter->getPatternRegistry();
        
        $this->assertInstanceOf(PatternRegistry::class, $registry);
        $this->assertNotEmpty($registry->getAllPatterns());
    }
    
    public function testFilterWithPatternInvalidPatternName(): void
    {
        $content = 'This contains sensitive data: test@example.com';
        $filtered = $this->filter->filterWithPattern($content, 'non_existent_pattern');
        
        // Should return original content unchanged
        $this->assertEquals($content, $filtered);
    }
    
    public function testFilterWithPatternWithCustomReplacement(): void
    {
        $registry = new PatternRegistry(
            new DefaultPatterns([
                'test_pattern' => '/test@[a-z]+\.[a-z]{2,}/'
            ])
        );
        $filter = new SensitiveContentFilter($registry, $this->logger);
        
        $content = 'Email: test@example.com for testing';
        $filtered = $filter->filterWithPattern($content, 'test_pattern', '[CUSTOM_REDACTED]');
        
        $this->assertStringContainsString('[CUSTOM_REDACTED]', $filtered);
        $this->assertStringNotContainsString('test@example.com', $filtered);
        $this->assertTrue($this->logger->hasInfo('Sensitive content filtered with specific pattern'));
    }
    
    public function testFilterWithPatternDefaultReplacement(): void
    {
        $registry = new PatternRegistry(
            new DefaultPatterns([
                'simple_pattern' => '/secret/'
            ])
        );
        $filter = new SensitiveContentFilter($registry, $this->logger);
        
        $content = 'This is a secret message';
        $filtered = $filter->filterWithPattern($content, 'simple_pattern');
        
        $this->assertStringContainsString('[REDACTED]', $filtered);
        $this->assertStringNotContainsString('secret', $filtered);
    }
    
    public function testFilterWithPatternNoMatches(): void
    {
        $registry = new PatternRegistry(
            new DefaultPatterns([
                'email_pattern' => '/\b[A-Za-z0-9._%+\-]+@[A-Za-z0-9.\-]+\.[A-Z|a-z]{2,}\b/'
            ])
        );
        $filter = new SensitiveContentFilter($registry, $this->logger);
        
        $content = 'This contains no emails just text';
        $filtered = $filter->filterWithPattern($content, 'email_pattern', '[EMAIL_REMOVED]');
        
        // Should return original content unchanged
        $this->assertEquals($content, $filtered);
        // No logging should occur for no matches
        $this->assertFalse($this->logger->hasInfo('Sensitive content filtered with specific pattern'));
    }
}