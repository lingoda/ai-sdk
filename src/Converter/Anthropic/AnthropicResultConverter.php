<?php

declare(strict_types=1);

namespace Lingoda\AiSdk\Converter\Anthropic;

use Anthropic\Responses\Messages\CreateResponse;
use Lingoda\AiSdk\Converter\ResultConverterInterface;
use Lingoda\AiSdk\Enum\AIProvider;
use Lingoda\AiSdk\Exception\InvalidArgumentException;
use Lingoda\AiSdk\ModelInterface;
use Lingoda\AiSdk\Result\ResultInterface;
use Lingoda\AiSdk\Result\TextResult;
use Lingoda\AiSdk\Result\ToolCall;
use Lingoda\AiSdk\Result\ToolCallResult;

/**
 * @template-implements ResultConverterInterface<CreateResponse>
 */
final class AnthropicResultConverter implements ResultConverterInterface
{
    public function supports(ModelInterface $model, mixed $response): bool
    {
        return $model->getProvider()->is(AIProvider::ANTHROPIC) 
            && $response instanceof CreateResponse;
    }

    public function convert(ModelInterface $model, object $response): ResultInterface
    {
        if (!$response instanceof CreateResponse) {
            throw new InvalidArgumentException('Expected Anthropic CreateResponse object');
        }

        $responseArray = $response->toArray();
        
        // Extract metadata with support for current Anthropic response format
        $metadata = [
            'id' => $responseArray['id'] ?? '',
            'type' => $responseArray['type'] ?? null,
            'model' => $responseArray['model'] ?? $model->getId(),
            'role' => $responseArray['role'] ?? null,
            'stop_reason' => $responseArray['stop_reason'] ?? null,
            'stop_sequence' => $responseArray['stop_sequence'] ?? null,
            'usage' => $responseArray['usage'] ?? [],
        ];

        // Check for tool calls in content
        $toolCalls = [];
        $textContent = '';
        
        $content = $responseArray['content'] ?? [];
        if (is_array($content)) {
            foreach ($content as $contentBlock) {
                if (!is_array($contentBlock)) {
                    continue;
                }
                
                $type = $contentBlock['type'] ?? '';
                
                if ($type === 'tool_use') {
                    $toolCalls[] = new ToolCall(
                        (string) ($contentBlock['id'] ?? ''),
                        (string) ($contentBlock['name'] ?? ''),
                        is_array($contentBlock['input'] ?? null) ? $contentBlock['input'] : []
                    );
                } elseif ($type === 'text') {
                    $textContent .= ($contentBlock['text'] ?? '');
                }
            }
        }

        // Return tool calls if any exist
        if (!empty($toolCalls)) {
            return new ToolCallResult($metadata, ...$toolCalls);
        }

        // Default to text result
        return new TextResult($textContent, $metadata);
    }
}