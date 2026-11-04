<?php

namespace App\Integration\Clients;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/** Review sentiment from the Hugging Face Inference API (cardiffnlp/twitter-roberta-base-sentiment-latest). */
class SentimentClient
{
    /** Longer texts are cut: the model reads about 512 tokens anyway. */
    public const MAX_CHARACTERS = 1500;

    public const LABELS = ['positive', 'neutral', 'negative'];

    // Older versions of the model answer LABEL_0…2 instead of names.
    private const NUMBERED_LABELS = ['label_0' => 'negative', 'label_1' => 'neutral', 'label_2' => 'positive'];

    /**
     * @return array{label: string, score: float} the label with the highest score
     *
     * @throws SentimentUnavailable
     */
    public function analyze(string $text): array
    {
        $token = (string) config('services.huggingface.token');

        if ($token === '') {
            throw new SentimentUnavailable('Hugging Face token missing: put it in secrets/hf_api_token.');
        }

        $model = config('services.huggingface.sentiment_model');

        try {
            $response = Http::withToken($token)
                ->acceptJson()
                ->timeout(config('services.huggingface.timeout'))
                ->post(rtrim(config('services.huggingface.url'), '/').'/'.$model, [
                    'inputs' => Str::limit(trim($text), self::MAX_CHARACTERS, ''),
                ]);
        } catch (ConnectionException $e) {
            throw $this->unavailable($model, null, $e->getMessage());
        }

        if (! $response->successful()) {
            throw $this->unavailable($model, $response);
        }

        return $this->bestLabel($response->json(), $model);
    }

    private function bestLabel(mixed $body, string $model): array
    {
        // One input gives [[{label, score}, …]]; some versions answer the inner list directly.
        $scores = is_array($body) && isset($body[0]) && is_array($body[0]) && array_is_list($body[0]) ? $body[0] : $body;
        $best = null;

        foreach (is_array($scores) ? $scores : [] as $item) {
            $label = strtolower((string) ($item['label'] ?? ''));
            $label = self::NUMBERED_LABELS[$label] ?? $label;

            if (in_array($label, self::LABELS, true) && is_numeric($item['score'] ?? null)
                && ($best === null || $item['score'] > $best['score'])) {
                $best = ['label' => $label, 'score' => (float) $item['score']];
            }
        }

        return $best ?? throw $this->unavailable($model, null, 'unexpected answer');
    }

    // Logged without the token or the review text.
    private function unavailable(string $model, ?Response $response, ?string $error = null): SentimentUnavailable
    {
        Log::warning('Hugging Face sentiment failed', [
            'model' => $model,
            'status' => $response?->status(),
            'error' => $error ?? $response?->json('error'),
        ]);

        return new SentimentUnavailable('Hugging Face is unavailable.');
    }
}
