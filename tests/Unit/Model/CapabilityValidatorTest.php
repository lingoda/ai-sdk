<?php

declare(strict_types=1);

namespace Tests\Unit\Model;

use Lingoda\AiSdk\Enum\Capability;
use Lingoda\AiSdk\Exception\UnsupportedCapabilityException;
use Lingoda\AiSdk\Model\CapabilityValidator;
use Lingoda\AiSdk\ModelInterface;
use PHPUnit\Framework\TestCase;

final class CapabilityValidatorTest extends TestCase
{
    private function createMockModel(array $capabilities, string $id = 'test-model'): ModelInterface
    {
        $model = $this->createMock(ModelInterface::class);
        $model->method('getId')->willReturn($id);
        $model->method('getCapabilities')->willReturn($capabilities);
        $model->method('hasCapability')->willReturnCallback(
            static fn(Capability $capability) => in_array($capability, $capabilities, true)
        );

        return $model;
    }

    public function test_requireCapability_passes_when_model_has_required_capability(): void
    {
        $model = $this->createMockModel([Capability::VISION, Capability::TEXT]);

        CapabilityValidator::requireCapability($model, Capability::VISION);

        // If no exception is thrown, the test passes
        $this->assertTrue(true);
    }

    public function test_requireCapability_throws_exception_when_model_lacks_required_capability(): void
    {
        $model = $this->createMockModel([Capability::TEXT], 'gpt-4o-mini');

        $this->expectException(UnsupportedCapabilityException::class);
        $this->expectExceptionMessage(
            'Model "gpt-4o-mini" does not support required capability "vision". Available capabilities: text'
        );

        CapabilityValidator::requireCapability($model, Capability::VISION);
    }

    public function test_requireCapability_includes_all_available_capabilities_in_error_message(): void
    {
        $model = $this->createMockModel([Capability::TEXT, Capability::TOOLS], 'claude-3-sonnet');

        $this->expectException(UnsupportedCapabilityException::class);
        $this->expectExceptionMessage(
            'Model "claude-3-sonnet" does not support required capability "vision". Available capabilities: text, tools'
        );

        CapabilityValidator::requireCapability($model, Capability::VISION);
    }

    public function test_requireCapabilities_passes_when_model_has_all_required_capabilities(): void
    {
        $model = $this->createMockModel([Capability::VISION, Capability::TEXT, Capability::TOOLS]);

        CapabilityValidator::requireCapabilities($model, [Capability::VISION, Capability::TEXT]);

        // If no exception is thrown, the test passes
        $this->assertTrue(true);
    }

    public function test_requireCapabilities_passes_with_empty_capabilities_array(): void
    {
        $model = $this->createMockModel([Capability::TEXT]);

        CapabilityValidator::requireCapabilities($model, []);

        // If no exception is thrown, the test passes
        $this->assertTrue(true);
    }

    public function test_requireCapabilities_throws_exception_when_model_lacks_single_capability(): void
    {
        $model = $this->createMockModel([Capability::TEXT], 'gpt-4o');

        $this->expectException(UnsupportedCapabilityException::class);
        $this->expectExceptionMessage(
            'Model "gpt-4o" does not support required capabilities: vision. Available capabilities: text'
        );

        CapabilityValidator::requireCapabilities($model, [Capability::VISION]);
    }

    public function test_requireCapabilities_throws_exception_when_model_lacks_multiple_capabilities(): void
    {
        $model = $this->createMockModel([Capability::TEXT], 'gemini-pro');

        $this->expectException(UnsupportedCapabilityException::class);
        $this->expectExceptionMessage(
            'Model "gemini-pro" does not support required capabilities: vision, tools. Available capabilities: text'
        );

        CapabilityValidator::requireCapabilities($model, [Capability::VISION, Capability::TOOLS]);
    }

    public function test_requireCapabilities_throws_exception_when_model_lacks_some_capabilities(): void
    {
        $model = $this->createMockModel([Capability::TEXT, Capability::VISION], 'claude-haiku');

        $this->expectException(UnsupportedCapabilityException::class);
        $this->expectExceptionMessage(
            'Model "claude-haiku" does not support required capabilities: tools, multimodal. Available capabilities: text, vision'
        );

        CapabilityValidator::requireCapabilities($model, [
            Capability::TEXT,       // Has this
            Capability::VISION,     // Has this
            Capability::TOOLS,      // Missing this
            Capability::MULTIMODAL  // Missing this
        ]);
    }

    public function test_requireCapabilities_includes_all_missing_capabilities_in_error_message(): void
    {
        $model = $this->createMockModel([], 'basic-model');

        $this->expectException(UnsupportedCapabilityException::class);
        $this->expectExceptionMessage(
            'Model "basic-model" does not support required capabilities: text, vision, tools. Available capabilities: '
        );

        CapabilityValidator::requireCapabilities($model, [
            Capability::TEXT,
            Capability::VISION,
            Capability::TOOLS
        ]);
    }

    public function test_requireCapabilities_handles_model_with_no_capabilities(): void
    {
        $model = $this->createMockModel([], 'empty-model');

        $this->expectException(UnsupportedCapabilityException::class);
        $this->expectExceptionMessage(
            'Model "empty-model" does not support required capabilities: vision. Available capabilities: '
        );

        CapabilityValidator::requireCapabilities($model, [Capability::VISION]);
    }

    public function test_requireCapability_handles_model_with_no_capabilities(): void
    {
        $model = $this->createMockModel([], 'no-caps-model');

        $this->expectException(UnsupportedCapabilityException::class);
        $this->expectExceptionMessage(
            'Model "no-caps-model" does not support required capability "text". Available capabilities: '
        );

        CapabilityValidator::requireCapability($model, Capability::TEXT);
    }

    public function test_requireCapabilities_preserves_order_of_missing_capabilities(): void
    {
        $model = $this->createMockModel([Capability::TEXT], 'order-test-model');

        $this->expectException(UnsupportedCapabilityException::class);
        $this->expectExceptionMessage(
            'Model "order-test-model" does not support required capabilities: vision, tools, multimodal. Available capabilities: text'
        );

        // Test that missing capabilities appear in the same order as requested
        CapabilityValidator::requireCapabilities($model, [
            Capability::VISION,     // Missing - should be first in error
            Capability::TEXT,       // Has this - should not appear in error
            Capability::TOOLS,      // Missing - should be second in error
            Capability::MULTIMODAL  // Missing - should be third in error
        ]);
    }
}