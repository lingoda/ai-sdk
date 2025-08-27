<?php

declare(strict_types = 1);

namespace Lingoda\AiSdk\Prompt;

use Webmozart\Assert\Assert;

/**
 * Base value object for all prompt types.
 * Ensures prompts are never empty and provides common functionality.
 * Supports parameter substitution using {{variable}} syntax.
 */
abstract readonly class Prompt implements \Stringable
{
    private const string PARAMETER_OPENING = '{{';
    private const string PARAMETER_CLOSING = '}}';

    protected string $content;

    /**
     * @param array<string, mixed> $parameters Optional parameters to substitute in the content
     */
    public function __construct(string $content, array $parameters = [])
    {
        if (!empty($parameters)) {
            $content = $this->compileTemplate($content, $parameters);
        }
        
        Assert::stringNotEmpty($content, sprintf('%s content cannot be empty', static::class));
        $this->content = $content;
    }
    
    /**
     * Get the prompt content
     */
    public function getContent(): string
    {
        return $this->content;
    }
    
    /**
     * Get the role identifier for this prompt type
     */
    abstract public function getRole(): Role;
    
    /**
     * Check if this prompt equals another
     */
    public function equals(self $other): bool
    {
        return $this::class === $other::class && $this->content === $other->content;
    }
    
    /**
     * Create a hash for caching purposes
     */
    public function hash(): string
    {
        return md5($this->getRole()->value . ':' . $this->content);
    }
    
    /**
     * Convert to string representation
     */
    public function toString(): string
    {
        return $this->content;
    }
    
    /**
     * Convert to array format for API calls
     *
     * @return array{role: string, content: string}
     */
    public function toArray(): array
    {
        return [
            'role' => $this->getRole()->value,
            'content' => $this->content,
        ];
    }

    public function __toString(): string
    {
        return $this->toString();
    }

    /**
     * Create a new prompt instance with parameters substituted
     *
     * @param array<string, mixed> $parameters
     */
    public function withParameters(array $parameters): static
    {
        // Check if the content has any parameters to replace
        if (empty($parameters) || !$this->hasParameters()) {
            return $this;
        }

        $compiledContent = $this->compileTemplate($this->content, $parameters);
        
        // If no changes were made, return the same instance
        if ($compiledContent === $this->content) {
            return $this;
        }
        
        return new static($compiledContent); // @phpstan-ignore new.static
    }

    /**
     * Get all parameter names found in the prompt content
     *
     * @return list<string>
     */
    public function getParameterNames(): array
    {
        return $this->findVariableNames($this->content);
    }

    /**
     * Check if the prompt contains any parameters
     */
    public function hasParameters(): bool
    {
        return !empty($this->getParameterNames());
    }

    /**
     * Compile template with parameter substitution
     *
     * @param array<string, mixed> $data
     */
    private function compileTemplate(string $content, array $data): string
    {
        $resultList = [];
        $currIdx = 0;

        while ($currIdx < mb_strlen($content)) {
            $result = $this->parseNextVariable($content, $currIdx);

            if ($result === null) {
                $resultList[] = mb_substr($content, $currIdx);
                break;
            }

            [$variableName, $varStart, $varEnd] = $result;
            $resultList[] = mb_substr($content, $currIdx, $varStart - $currIdx);

            if (array_key_exists($variableName, $data)) {
                $value = $data[$variableName];
                $resultList[] = $this->convertToString($value);
            } else {
                // Keep original variable placeholder if no replacement found
                $resultList[] = mb_substr($content, $varStart, $varEnd - $varStart);
            }

            $currIdx = $varEnd;
        }

        return implode('', $resultList);
    }

    /**
     * Find all variable names in the content
     *
     * @return list<string>
     */
    private function findVariableNames(string $content): array
    {
        $names = [];
        $currIdx = 0;

        while ($currIdx < mb_strlen($content)) {
            $result = $this->parseNextVariable($content, $currIdx);
            if ($result === null) {
                break;
            }
            [$variableName, , $endPos] = $result;
            $names[] = $variableName;
            $currIdx = $endPos;
        }

        return $names;
    }

    /**
     * Parse the next variable in content starting from startIdx
     *
     * @return array{string, int, int}|null Returns [variableName, startPos, endPos] or null if no variable found
     */
    private function parseNextVariable(string $content, int $startIdx): ?array
    {
        $varStart = mb_strpos($content, self::PARAMETER_OPENING, $startIdx);
        if ($varStart === false) {
            return null;
        }

        $varEnd = mb_strpos($content, self::PARAMETER_CLOSING, $varStart);
        if ($varEnd === false) {
            return null;
        }

        $variableName = trim(mb_substr(
            $content,
            $varStart + mb_strlen(self::PARAMETER_OPENING),
            $varEnd - $varStart - mb_strlen(self::PARAMETER_OPENING)
        ));

        return [$variableName, $varStart, $varEnd + mb_strlen(self::PARAMETER_CLOSING)];
    }

    /**
     * Convert various types to string safely
     */
    private function convertToString(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_string($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (string) $value;
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_object($value) && method_exists($value, '__toString')) {
            return (string) $value;
        }

        if (is_array($value)) {
            return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES) ?: '';
        }

        return '';
    }
}
