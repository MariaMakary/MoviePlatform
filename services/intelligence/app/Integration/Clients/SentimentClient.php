<?php

namespace App\Integration\Clients;

use App\Enums\SentimentLabel;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SentimentClient
{
    public const MAX_CHARACTERS = 1000;

    public function analyze(string $text): array
    {
        $key = (string) config('services.azure_language.key');
        $endpoint = rtrim((string) config('services.azure_language.endpoint'), '/');

        if ($key === '' || $endpoint === '') {
            throw new SentimentUnavailable('Azure AI Language is not set up: put the key in secrets/azure_language_key and the endpoint in AZURE_LANGUAGE_ENDPOINT.');
        }

        try {
            $response = Http::withHeaders(['Ocp-Apim-Subscription-Key' => $key])
                ->acceptJson()
                ->timeout(config('services.azure_language.timeout'))
                ->post($endpoint.'/language/:analyze-text?api-version='.config('services.azure_language.api_version'), [
                    'kind' => 'SentimentAnalysis',
                    'analysisInput' => ['documents' => [[
                        'id' => '1',
                        'language' => 'en',
                        'text' => mb_substr(trim($text), 0, self::MAX_CHARACTERS),
                    ]]],
                ]);
        } catch (ConnectionException $e) {
            throw $this->unavailable(null, $e->getMessage());
        }

        if (! $response->successful()) {
            throw $this->unavailable($response, $response->json('error.message'));
        }

        if ($response->json('results.errors.0') !== null) {
            throw $this->unavailable($response, $response->json('results.errors.0.error.innererror.message')
                ?? $response->json('results.errors.0.error.message'));
        }

        return $this->bestLabel($response->json('results.documents.0.confidenceScores'));
    }

    private function bestLabel(mixed $scores): array
    {
        $best = null;

        foreach (SentimentLabel::cases() as $label) {
            $score = is_array($scores) ? ($scores[$label->value] ?? null) : null;

            if (is_numeric($score) && ($best === null || $score > $best['score'])) {
                $best = ['label' => $label, 'score' => (float) $score];
            }
        }

        return $best ?? throw $this->unavailable(null, 'unexpected answer');
    }

    private function unavailable(?Response $response, ?string $error): SentimentUnavailable
    {
        Log::warning('Azure AI Language sentiment failed', [
            'status' => $response?->status(),
            'error' => $error,
        ]);

        return new SentimentUnavailable('Azure AI Language is unavailable.');
    }
}
