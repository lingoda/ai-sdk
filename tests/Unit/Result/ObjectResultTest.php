<?php

declare(strict_types = 1);

namespace Lingoda\AiSdk\Tests\Unit\Result;

use ArrayObject;
use Lingoda\AiSdk\Result\ObjectResult;
use Lingoda\AiSdk\Result\ResultInterface;
use stdClass;

final class ObjectResultTest extends ResultTestCase
{
    protected function createResult(mixed $content, array $metadata = []): ResultInterface
    {
        return new ObjectResult($content, $metadata);
    }

    protected function getExpectedContent(): mixed
    {
        $obj = new stdClass();
        $obj->test = 'value';
        $obj->number = 42;
        return $obj;
    }

    /**
     * Test with stdClass object.
     */
    public function testWithStdClass(): void
    {
        $content = new stdClass();
        $content->name = 'John Doe';
        $content->age = 30;

        $result = new ObjectResult($content);

        $this->assertSame($content, $result->getContent());
        $this->assertEquals('John Doe', $result->getContent()->name);
        $this->assertEquals(30, $result->getContent()->age);
    }

    /**
     * Test with custom object.
     */
    public function testWithCustomObject(): void
    {
        $content = new class() {
            public string $property = 'test';
            public int $number = 42;

            public function getProperty(): string
            {
                return $this->property;
            }
        };

        $result = new ObjectResult($content);

        $this->assertSame($content, $result->getContent());
        $this->assertEquals('test', $result->getContent()->property);
        $this->assertEquals(42, $result->getContent()->number);
        $this->assertEquals('test', $result->getContent()->getProperty());
    }

    /**
     * Test that getContent returns exact same object instance.
     */
    public function testGetContentReturnsExactObject(): void
    {
        $originalObject = new stdClass();
        $originalObject->data = ['key' => 'value'];

        $result = new ObjectResult($originalObject);
        $retrievedObject = $result->getContent();

        $this->assertSame($originalObject, $retrievedObject);

        // Modifying the original object should affect the retrieved object
        $originalObject->newProperty = 'new value';
        $this->assertEquals('new value', $retrievedObject->newProperty);
    }

    /**
     * Test with complex nested object.
     */
    public function testWithComplexNestedObject(): void
    {
        $content = new stdClass();
        $content->user = new stdClass();
        $content->user->id = 123;
        $content->user->profile = new stdClass();
        $content->user->profile->email = 'user@example.com';
        $content->settings = [
            'theme' => 'dark',
            'notifications' => true,
            'preferences' => [
                'language' => 'en',
                'timezone' => 'UTC'
            ]
        ];

        $result = new ObjectResult($content);

        $this->assertEquals(123, $result->getContent()->user->id);
        $this->assertEquals('user@example.com', $result->getContent()->user->profile->email);
        $this->assertEquals('dark', $result->getContent()->settings['theme']);
        $this->assertEquals('en', $result->getContent()->settings['preferences']['language']);
    }

    /**
     * Test with ArrayObject.
     */
    public function testWithArrayObject(): void
    {
        $content = new ArrayObject(['key1' => 'value1', 'key2' => 'value2']);
        $result = new ObjectResult($content);

        $this->assertSame($content, $result->getContent());
        $this->assertInstanceOf(ArrayObject::class, $result->getContent());
        $this->assertEquals('value1', $result->getContent()['key1']);
        $this->assertEquals('value2', $result->getContent()['key2']);
    }

    /**
     * Test with empty object.
     */
    public function testWithEmptyObject(): void
    {
        $content = new stdClass();
        $result = new ObjectResult($content);

        $this->assertSame($content, $result->getContent());
        $this->assertEquals([], get_object_vars($result->getContent()));
    }
}
