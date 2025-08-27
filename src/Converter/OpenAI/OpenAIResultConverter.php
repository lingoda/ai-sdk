<?php

declare(strict_types=1);

namespace Lingoda\AiSdk\Converter\OpenAI;

use Lingoda\AiSdk\Converter\ResultConverterInterface;
use Lingoda\AiSdk\Enum\AIProvider;
use Lingoda\AiSdk\Exception\InvalidArgumentException;
use Lingoda\AiSdk\ModelInterface;
use Lingoda\AiSdk\Result\ResultInterface;
use Lingoda\AiSdk\Result\TextResult;
use Lingoda\AiSdk\Result\ToolCall;
use Lingoda\AiSdk\Result\ToolCallResult;
use OpenAI\Responses\Chat\CreateResponse;

/**
 * @template-implements ResultConverterInterface<CreateResponse>
 */
final class OpenAIResultConverter implements ResultConverterInterface
{
    public function supports(ModelInterface $model, mixed $response): bool
    {
        return $model->getProvider()->is(AIProvider::OPENAI) 
            && $response instanceof CreateResponse;
    }

    public function convert(ModelInterface $model, object $response): ResultInterface
    {
        if (!$response instanceof CreateResponse) {
            throw new InvalidArgumentException('Expected OpenAI CreateResponse object');
        }

        $choice = $response->choices[0];
        $message = $choice->message;
        
        // Extract metadata with support for new OpenAI response format
        $metadata = [
            'id' => $response->id,
            'object' => $response->object ?? null,
            'model' => $response->model,
            'created' => $response->created,
            'finish_reason' => $choice->finishReason,
            'usage' => [
                'prompt_tokens' => $response->usage->promptTokens ?? 0,
                'completion_tokens' => $response->usage->completionTokens ?? 0,
                'total_tokens' => $response->usage->totalTokens ?? 0,
                'prompt_tokens_details' => [
                    'cached_tokens' => $response->usage->promptTokensDetails->cachedTokens ?? 0,
                    'audio_tokens' => $response->usage->promptTokensDetails->audioTokens ?? 0,
                ],
                'completion_tokens_details' => [
                    'reasoning_tokens' => $response->usage->completionTokensDetails->reasoningTokens ?? 0,
                    'audio_tokens' => $response->usage->completionTokensDetails->audioTokens ?? 0,
                    'accepted_prediction_tokens' => $response->usage->completionTokensDetails->acceptedPredictionTokens ?? 0,
                    'rejected_prediction_tokens' => $response->usage->completionTokensDetails->rejectedPredictionTokens ?? 0,
                ],
            ],
            'system_fingerprint' => $response->systemFingerprint,
            'index' => $choice->index ?? 0,
        ];

        // Check if response contains tool calls
        if (!empty($message->toolCalls)) {
            $toolCalls = [];
            
            foreach ($message->toolCalls as $toolCall) {
                $arguments = [];
                if ($toolCall->function->arguments) {
                    /** @var array<string, mixed> $arguments */
                    $arguments = json_decode($toolCall->function->arguments, true, 512, JSON_THROW_ON_ERROR) ?? [];
                }
                
                $toolCalls[] = new ToolCall(
                    $toolCall->id,
                    $toolCall->function->name,
                    $arguments
                );
            }
            
            return new ToolCallResult($metadata, ...$toolCalls);
        }

        // Default to text result
        $content = $message->content ?? '';

        return new TextResult($content, $metadata);
    }
}