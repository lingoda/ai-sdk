<?php

declare(strict_types = 1);

namespace Lingoda\AiSdk\Converter\Gemini;

use Gemini\Responses\GenerativeModel\GenerateContentResponse;
use Lingoda\AiSdk\Converter\ResultConverterInterface;
use Lingoda\AiSdk\Enum\AIProvider;
use Lingoda\AiSdk\Exception\InvalidArgumentException;
use Lingoda\AiSdk\Exception\RuntimeException;
use Lingoda\AiSdk\ModelInterface;
use Lingoda\AiSdk\Result\ResultInterface;
use Lingoda\AiSdk\Result\TextResult;
use Lingoda\AiSdk\Result\ToolCall;
use Lingoda\AiSdk\Result\ToolCallResult;
use Lingoda\AiSdk\Usage\Gemini\GeminiUsageExtractor;

/**
 * @template-implements ResultConverterInterface<GenerateContentResponse>
 */
final class GeminiResultConverter implements ResultConverterInterface
{
    private GeminiUsageExtractor $usageExtractor;

    public function __construct()
    {
        $this->usageExtractor = new GeminiUsageExtractor();
    }

    public function supports(ModelInterface $model, mixed $response): bool
    {
        return $model->getProvider()->is(AIProvider::GEMINI)
            && $response instanceof GenerateContentResponse;
    }

    public function convert(ModelInterface $model, object $response): ResultInterface
    {
        if (!$response instanceof GenerateContentResponse) {
            throw new InvalidArgumentException('Expected Gemini GenerateContentResponse object');
        }

        $candidates = $response->candidates;
        if (empty($candidates)) {
            throw new RuntimeException('No candidates found in Gemini response');
        }

        $candidate = $candidates[0];

        // Extract content and check for function calls
        $content = '';
        $toolCalls = [];

        if ($candidate->content->parts) {
            foreach ($candidate->content->parts as $part) {
                if (isset($part->text)) {
                    $content .= $part->text;
                } elseif (isset($part->functionCall)) {
                    // Handle function calls (Gemini's equivalent of tool calls)
                    $functionCall = $part->functionCall;
                    $toolCalls[] = new ToolCall(
                        'gemini_' . uniqid('', true), // Generate ID since Gemini doesn't provide one
                        $functionCall->name ?? '',
                        is_array($functionCall->args ?? null) ? $functionCall->args : []
                    );
                }
            }
        }

        $rawUsage = [
            'prompt_token_count' => $response->usageMetadata->promptTokenCount ?? 0,
            'candidates_token_count' => $response->usageMetadata->candidatesTokenCount ?? 0,
            'total_token_count' => $response->usageMetadata->totalTokenCount ?? 0,
            'cached_content_token_count' => $response->usageMetadata->cachedContentTokenCount ?? 0,
            'tool_use_prompt_token_count' => $response->usageMetadata->toolUsePromptTokenCount ?? 0,
            'thoughts_token_count' => $response->usageMetadata->thoughtsTokenCount ?? 0,
        ];
        $usage = $this->usageExtractor->extract($rawUsage);

        // Extract metadata using response object properties following Gemini API format
        $metadata = [
            'id' => 'gemini_' . uniqid('', true),
            'model' => $response->modelVersion ?? $model->getId(),
            'finish_reason' => $candidate->finishReason,
            'role' => $candidate->content->role,
            'index' => $candidate->index ?? 0,
            'usage' => $rawUsage,
            'safety_ratings' => $candidate->safetyRatings ?? [],
            'prompt_feedback' => $response->promptFeedback ?? null,
            'citation_metadata' => $candidate->citationMetadata ?? null,
            'token_count' => $candidate->tokenCount ?? null,
        ];

        // Return tool calls if any exist, otherwise return text result
        if (!empty($toolCalls)) {
            return (new ToolCallResult($metadata, ...$toolCalls))->withUsage($usage);
        }

        return (new TextResult($content, $metadata))->withUsage($usage);
    }
}
