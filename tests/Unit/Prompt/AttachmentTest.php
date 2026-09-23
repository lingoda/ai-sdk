<?php

declare(strict_types = 1);

namespace Lingoda\AiSdk\Tests\Unit\Prompt;

use Lingoda\AiSdk\Exception\InvalidArgumentException;
use Lingoda\AiSdk\Prompt\Attachment;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class AttachmentTest extends TestCase
{
    private const string PNG_1X1_BASE64 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';
    private const string PDF_BYTES = "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\ntrailer\n<< /Root 1 0 R >>\n%%EOF\n";
    private const string MARKER = 'SECRET_BYTES_MARKER_4711';

    /**
     * @return iterable<string, array{string}>
     */
    public static function allowedMimeTypes(): iterable
    {
        yield 'pdf' => ['application/pdf'];
        yield 'jpeg' => ['image/jpeg'];
        yield 'png' => ['image/png'];
        yield 'gif' => ['image/gif'];
        yield 'webp' => ['image/webp'];
        yield 'docx' => [Attachment::DOCX];
        yield 'plain text' => ['text/plain'];
        yield 'csv' => ['text/csv'];
        yield 'markdown' => ['text/markdown'];
        yield 'html' => ['text/html'];
        yield 'json' => ['application/json'];
    }

    #[DataProvider('allowedMimeTypes')]
    public function testAcceptsAllowedMimeTypes(string $mimeType): void
    {
        $attachment = Attachment::fromBytes('data', $mimeType);

        $this->assertSame($mimeType, $attachment->mimeType);
    }

    public function testRejectsUnsupportedMimeType(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported attachment type "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"');

        Attachment::fromBytes('hello', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    public function testRejectsDetectedUnsupportedMimeType(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported attachment type');

        // Undetectable binary: detected as application/octet-stream, which is not allowed
        Attachment::fromBytes("PK\x03\x04" . str_repeat("\x00", 26) . 'content');
    }

    public function testRejectsEmptyBytes(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Attachment cannot be empty.');

        Attachment::fromBytes('', 'application/pdf');
    }

    public function testAcceptsExactlyMaxBytes(): void
    {
        $attachment = Attachment::fromBytes(str_repeat('a', Attachment::MAX_BYTES), 'application/pdf');

        $this->assertSame(Attachment::MAX_BYTES, $attachment->size());
    }

    public function testRejectsMoreThanMaxBytes(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(sprintf('Attachment exceeds the size limit of %d bytes.', Attachment::MAX_BYTES));

        Attachment::fromBytes(str_repeat('a', Attachment::MAX_BYTES + 1), 'application/pdf');
    }

    public function testDetectsPdfMimeType(): void
    {
        $attachment = Attachment::fromBytes(self::PDF_BYTES);

        $this->assertSame('application/pdf', $attachment->mimeType);
        $this->assertFalse($attachment->isImage());
    }

    public function testDetectsPngMimeType(): void
    {
        $bytes = base64_decode(self::PNG_1X1_BASE64, true);
        $this->assertIsString($bytes);

        $attachment = Attachment::fromBytes($bytes);

        $this->assertSame('image/png', $attachment->mimeType);
        $this->assertTrue($attachment->isImage());
    }

    public function testExplicitMimeTypeSkipsDetection(): void
    {
        $attachment = Attachment::fromBytes(self::PDF_BYTES, 'image/png');

        $this->assertSame('image/png', $attachment->mimeType);
    }

    public function testSha256IsHashOfBytes(): void
    {
        $attachment = Attachment::fromBytes(self::PDF_BYTES);

        $this->assertSame(hash('sha256', self::PDF_BYTES), $attachment->sha256);
    }

    public function testBytesSizeAndMetadata(): void
    {
        $attachment = Attachment::fromBytes(self::PDF_BYTES);

        $this->assertSame(self::PDF_BYTES, $attachment->bytes());
        $this->assertSame(mb_strlen(self::PDF_BYTES, '8bit'), $attachment->size());
        $this->assertSame(['mime' => 'application/pdf', 'size' => mb_strlen(self::PDF_BYTES, '8bit')], $attachment->toMetadata());
    }

    public function testIsImageForImageTypesOnly(): void
    {
        $this->assertTrue(Attachment::fromBytes('x', 'image/jpeg')->isImage());
        $this->assertTrue(Attachment::fromBytes('x', 'image/gif')->isImage());
        $this->assertTrue(Attachment::fromBytes('x', 'image/webp')->isImage());
        $this->assertFalse(Attachment::fromBytes('x', 'application/pdf')->isImage());
    }

    /**
     * @return iterable<string, array{string, bool, bool}>
     */
    public static function typeClassification(): iterable
    {
        yield 'pdf' => [Attachment::PDF, false, false];
        yield 'docx' => [Attachment::DOCX, false, false];
        yield 'jpeg' => ['image/jpeg', true, false];
        yield 'png' => ['image/png', true, false];
        yield 'gif' => ['image/gif', true, false];
        yield 'webp' => ['image/webp', true, false];
        yield 'plain text' => ['text/plain', false, true];
        yield 'csv' => ['text/csv', false, true];
        yield 'markdown' => ['text/markdown', false, true];
        yield 'html' => ['text/html', false, true];
        yield 'json' => ['application/json', false, true];
    }

    #[DataProvider('typeClassification')]
    public function testIsImageAndIsTextPerType(string $mimeType, bool $isImage, bool $isText): void
    {
        $attachment = Attachment::fromBytes('x', $mimeType);

        $this->assertSame($isImage, $attachment->isImage());
        $this->assertSame($isText, $attachment->isText());
    }

    public function testMimeTypesListsEveryType(): void
    {
        $this->assertSame(
            [Attachment::PDF, Attachment::DOCX, ...Attachment::IMAGE_TYPES, ...Attachment::TEXT_TYPES],
            Attachment::MIME_TYPES
        );
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function textMimeTypes(): iterable
    {
        foreach (Attachment::TEXT_TYPES as $mimeType) {
            yield $mimeType => [$mimeType];
        }
    }

    #[DataProvider('textMimeTypes')]
    public function testRejectsInvalidUtf8Text(string $mimeType): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(sprintf('Text attachment "%s" must be valid UTF-8.', $mimeType));

        Attachment::fromBytes("a,b\n\xC3\x28", $mimeType);
    }

    public function testAcceptsMultibyteUtf8Text(): void
    {
        $attachment = Attachment::fromBytes("name,city\nJos\u{00E9},M\u{00FC}nchen", 'text/csv');

        $this->assertSame("name,city\nJos\u{00E9},M\u{00FC}nchen", $attachment->bytes());
    }

    public function testInvalidUtf8IsAllowedForBinaryTypes(): void
    {
        $this->assertSame(Attachment::PDF, Attachment::fromBytes("%PDF-1.4 \xC3\x28\xFF", Attachment::PDF)->mimeType);
        $this->assertSame(Attachment::DOCX, Attachment::fromBytes("PK\x03\x04\xFF", Attachment::DOCX)->mimeType);
    }

    public function testDebugOutputDoesNotContainBytes(): void
    {
        $attachment = Attachment::fromBytes('%PDF-1.4 ' . self::MARKER, 'application/pdf');

        $printed = print_r($attachment, true);

        ob_start();
        var_dump($attachment);
        $dumped = (string) ob_get_clean();

        $this->assertStringNotContainsString(self::MARKER, $printed);
        $this->assertStringNotContainsString(self::MARKER, $dumped);
        $this->assertStringContainsString('application/pdf', $printed);
        $this->assertSame($attachment->toMetadata(), $attachment->__debugInfo());
    }

    public function testCannotBeSerialized(): void
    {
        $this->expectException(\LogicException::class);

        serialize(Attachment::fromBytes('%PDF-1.4 x', 'application/pdf'));
    }

    public function testBytesAreRedactedFromExceptionTraces(): void
    {
        try {
            Attachment::fromBytes('SECRET-TRACE-MARKER', 'application/zip');
            $this->fail('Expected InvalidArgumentException');
        } catch (\Lingoda\AiSdk\Exception\InvalidArgumentException $e) {
            foreach ($e->getTrace() as $frame) {
                foreach ($frame['args'] ?? [] as $arg) {
                    $this->assertNotSame('SECRET-TRACE-MARKER', $arg);
                }
            }
            $this->assertStringNotContainsString('SECRET-TRACE-MARKER', $e->getTraceAsString());
        }
    }

    public function testCannotBeUnserialized(): void
    {
        $class = Attachment::class;

        $this->expectException(\LogicException::class);

        unserialize(sprintf('O:%d:"%s":0:{}', mb_strlen($class, '8bit'), $class));
    }

    public function testNoObjectExportReachesTheBytes(): void
    {
        $attachment = Attachment::fromBytes('%PDF-1.4 EXPORT-MARKER', 'application/pdf');

        ob_start();
        var_dump($attachment);
        $dumped = (string) ob_get_clean();

        foreach ([
            'var_export' => var_export($attachment, true),
            'array cast' => print_r((array) $attachment, true),
            'get_object_vars' => print_r(get_object_vars($attachment), true),
            'print_r' => print_r($attachment, true),
            'var_dump' => $dumped,
            'json_encode' => (string) json_encode($attachment),
        ] as $path => $output) {
            $this->assertStringNotContainsString('EXPORT-MARKER', $output, $path);
        }

        $this->assertSame('%PDF-1.4 EXPORT-MARKER', $attachment->bytes());
    }

    public function testCannotBeCloned(): void
    {
        $this->expectException(\Error::class);

        $attachment = Attachment::fromBytes('%PDF-1.4 x', 'application/pdf');
        $copy = clone $attachment;
        unset($copy);
    }
}
