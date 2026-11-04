<?php

namespace Tests;

use App\Integration\Clients\SentimentClient;
use App\Integration\Clients\SentimentUnavailable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

class SentimentClientTest extends TestCase
{
    private const URL = 'https://router.huggingface.co/hf-inference/models/cardiffnlp/twitter-roberta-base-sentiment-latest';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.huggingface.token' => 'hf_test_token',
            'services.huggingface.url' => 'https://router.huggingface.co/hf-inference/models',
            'services.huggingface.sentiment_model' => 'cardiffnlp/twitter-roberta-base-sentiment-latest',
        ]);
    }

    private function client(): SentimentClient
    {
        return app(SentimentClient::class);
    }

    private function scores(array $scores): array
    {
        return array_map(fn ($label, $score) => ['label' => $label, 'score' => $score], array_keys($scores), $scores);
    }

    public function test_a_normal_answer_gives_the_label_with_the_highest_score(): void
    {
        Http::fake([self::URL => Http::response([$this->scores(['positive' => 0.9731, 'neutral' => 0.0212, 'negative' => 0.0057])])]);

        $this->assertSame(['label' => 'positive', 'score' => 0.9731], $this->client()->analyze('Loved the ending, the soundtrack is amazing.'));

        Http::assertSent(fn (Request $request) => $request->url() === self::URL
            && $request->method() === 'POST'
            && $request->hasHeader('Authorization', 'Bearer hf_test_token')
            && $request['inputs'] === 'Loved the ending, the soundtrack is amazing.');
    }

    public function test_the_highest_score_wins_whatever_its_position(): void
    {
        Http::fake([self::URL => Http::response([$this->scores(['positive' => 0.1, 'neutral' => 0.2, 'negative' => 0.7])])]);

        $this->assertSame(['label' => 'negative', 'score' => 0.7], $this->client()->analyze('Boring and far too long.'));
    }

    public function test_a_flat_list_and_numbered_labels_are_understood(): void
    {
        Http::fake([self::URL => Http::response($this->scores(['LABEL_0' => 0.05, 'LABEL_1' => 0.8, 'LABEL_2' => 0.15]))]);

        $this->assertSame(['label' => 'neutral', 'score' => 0.8], $this->client()->analyze('It was a movie.'));
    }

    public function test_a_long_text_is_cut_to_about_1500_characters(): void
    {
        Http::fake([self::URL => Http::response([$this->scores(['positive' => 0.6, 'neutral' => 0.3, 'negative' => 0.1])])]);

        $this->client()->analyze(str_repeat('Great movie. ', 300));

        Http::assertSent(fn (Request $request) => mb_strlen($request['inputs']) === SentimentClient::MAX_CHARACTERS);
    }

    public function test_failures_throw_so_the_event_is_retried(): void
    {
        $failures = [
            'model loading' => Http::response(['error' => 'Model is currently loading', 'estimated_time' => 20.0], 503),
            'rate limit' => Http::response(['error' => 'Rate limit reached'], 429),
            'bad token' => Http::response(['error' => 'Invalid credentials'], 401),
            'server error' => Http::response('Internal error', 500),
            'unexpected answer' => Http::response(['something' => 'else']),
        ];

        foreach ($failures as $case => $response) {
            Http::fake([self::URL => $response]);

            try {
                $this->client()->analyze('Some review.');
                $this->fail("No exception for: $case");
            } catch (SentimentUnavailable $e) {
                $this->assertSame('Hugging Face is unavailable.', $e->getMessage(), $case);
            }
        }
    }

    public function test_a_timeout_throws(): void
    {
        Http::fake(fn () => throw new ConnectionException('cURL error 28: Operation timed out'));

        $this->expectException(SentimentUnavailable::class);

        $this->client()->analyze('Some review.');
    }

    public function test_a_missing_token_throws_without_calling_hugging_face(): void
    {
        config(['services.huggingface.token' => '']);
        Http::fake();

        try {
            $this->client()->analyze('Some review.');
            $this->fail('No exception without a token.');
        } catch (SentimentUnavailable $e) {
            $this->assertStringContainsString('secrets/hf_api_token', $e->getMessage());
        }

        Http::assertNothingSent();
    }
}
