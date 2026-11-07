<?php

namespace Tests;

use App\Enums\SentimentLabel;
use App\Integration\Clients\SentimentClient;
use App\Integration\Clients\SentimentUnavailable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

class SentimentClientTest extends TestCase
{
    private const URL = 'https://kaia-test.cognitiveservices.azure.com/language/:analyze-text?api-version=2023-04-01';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.azure_language.key' => 'test-key',
            'services.azure_language.endpoint' => 'https://kaia-test.cognitiveservices.azure.com/',
        ]);
    }

    private function client(): SentimentClient
    {
        return app(SentimentClient::class);
    }

    private function answer(string $sentiment, array $scores): array
    {
        return [
            'kind' => 'SentimentAnalysisResults',
            'results' => [
                'documents' => [['id' => '1', 'sentiment' => $sentiment, 'confidenceScores' => $scores, 'sentences' => [], 'warnings' => []]],
                'errors' => [],
                'modelVersion' => '2025-01-01',
            ],
        ];
    }

    private function fakeAnswer(string $sentiment, array $scores): void
    {
        Http::fake(['kaia-test.cognitiveservices.azure.com/*' => Http::response($this->answer($sentiment, $scores))]);
    }

    public function test_a_normal_answer_gives_the_label_with_the_highest_score(): void
    {
        $this->fakeAnswer('positive', ['positive' => 0.97, 'neutral' => 0.02, 'negative' => 0.01]);

        $this->assertSame(
            ['label' => SentimentLabel::Positive, 'score' => 0.97],
            $this->client()->analyze('  Loved the ending, the soundtrack is amazing.  '),
        );

        Http::assertSent(fn (Request $request) => $request->url() === self::URL
            && $request->method() === 'POST'
            && $request->hasHeader('Ocp-Apim-Subscription-Key', 'test-key')
            && $request['kind'] === 'SentimentAnalysis'
            && $request['analysisInput']['documents'] === [['id' => '1', 'language' => 'en', 'text' => 'Loved the ending, the soundtrack is amazing.']]);
    }

    public function test_the_highest_score_wins_whatever_its_position(): void
    {
        $this->fakeAnswer('negative', ['positive' => 0.1, 'neutral' => 0.2, 'negative' => 0.7]);

        $this->assertSame(['label' => SentimentLabel::Negative, 'score' => 0.7], $this->client()->analyze('Boring and far too long.'));
    }

    public function test_a_mixed_answer_still_gives_one_of_the_three_labels(): void
    {
        $this->fakeAnswer('mixed', ['positive' => 0.45, 'neutral' => 0.05, 'negative' => 0.5]);

        $this->assertSame(['label' => SentimentLabel::Negative, 'score' => 0.5], $this->client()->analyze('Great actors, terrible story.'));
    }

    public function test_a_long_text_is_cut_to_1000_characters(): void
    {
        $this->fakeAnswer('positive', ['positive' => 0.6, 'neutral' => 0.3, 'negative' => 0.1]);

        $this->client()->analyze(str_repeat('Très bon film. ', 300));

        Http::assertSent(fn (Request $request) => mb_strlen($request['analysisInput']['documents'][0]['text']) === SentimentClient::MAX_CHARACTERS);
    }

    public function test_failures_throw_so_the_event_is_retried(): void
    {
        $error = fn (string $code, string $message) => ['error' => ['code' => $code, 'message' => $message]];

        $failures = [
            'bad key' => Http::response($error('401', 'Access denied due to invalid subscription key.'), 401),
            'free limit used up' => Http::response($error('403', 'Out of call volume quota.'), 403),
            'rate limit' => Http::response($error('429', 'Rate limit is exceeded.'), 429),
            'server error' => Http::response('Internal error', 500),
            'document refused' => Http::response(['kind' => 'SentimentAnalysisResults', 'results' => [
                'documents' => [],
                'errors' => [['id' => '1', 'error' => ['code' => 'InvalidArgument', 'message' => 'Invalid document in request.',
                    'innererror' => ['code' => 'InvalidDocument', 'message' => 'Document text is empty.']]]],
            ]]),
            'unexpected answer' => Http::response(['something' => 'else']),
        ];

        foreach ($failures as $case => $response) {
            Http::fake(['kaia-test.cognitiveservices.azure.com/*' => $response]);

            try {
                $this->client()->analyze('Some review.');
                $this->fail("No exception for: $case");
            } catch (SentimentUnavailable $e) {
                $this->assertSame('Azure AI Language is unavailable.', $e->getMessage(), $case);
            }
        }
    }

    public function test_a_timeout_throws(): void
    {
        Http::fake(fn () => throw new ConnectionException('cURL error 28: Operation timed out'));

        $this->expectException(SentimentUnavailable::class);

        $this->client()->analyze('Some review.');
    }

    public function test_a_missing_key_or_endpoint_throws_without_calling_azure(): void
    {
        Http::fake();

        foreach (['services.azure_language.key', 'services.azure_language.endpoint'] as $setting) {
            config(['services.azure_language.key' => 'test-key', 'services.azure_language.endpoint' => 'https://kaia-test.cognitiveservices.azure.com']);
            config([$setting => '']);

            try {
                $this->client()->analyze('Some review.');
                $this->fail("No exception without $setting.");
            } catch (SentimentUnavailable $e) {
                $this->assertStringContainsString('secrets/azure_language_key', $e->getMessage());
            }
        }

        Http::assertNothingSent();
    }
}
