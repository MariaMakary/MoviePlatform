<?php

namespace MoviePlatform\Common\Tests;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;
use MoviePlatform\Common\Exceptions\JsonExceptionRenderer;
use MoviePlatform\Common\Http\Resources\PaginatedCollection;
use Orchestra\Testbench\TestCase;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class JsonExceptionRendererTest extends TestCase
{
    private function render(\Throwable $e): array
    {
        $response = (new JsonExceptionRenderer())->render(Request::create('/'), $e);

        return [$response->getStatusCode(), json_decode($response->getContent(), true)];
    }

    public function test_validation_errors_have_message_and_errors(): void
    {
        [$status, $body] = $this->render(ValidationException::withMessages(['email' => ['Taken.']]));

        $this->assertSame(422, $status);
        $this->assertSame(['message' => 'The given data was invalid.', 'errors' => ['email' => ['Taken.']]], $body);
    }

    public function test_http_errors_map_to_their_status(): void
    {
        $this->assertSame([401, ['message' => 'Unauthenticated.']], $this->render(new AuthenticationException()));
        $this->assertSame([403, ['message' => 'This action is unauthorized.']], $this->render(new AuthorizationException()));
        $this->assertSame([404, ['message' => 'Not found.']], $this->render(new NotFoundHttpException()));
        $this->assertSame([405, ['message' => 'Method not allowed.']], $this->render(new MethodNotAllowedHttpException(['GET'])));
        $this->assertSame([503, ['message' => 'Catalog service is unavailable.']], $this->render(new HttpException(503, 'Catalog service is unavailable.')));
    }

    public function test_unexpected_errors_hide_their_details(): void
    {
        $this->assertSame([500, ['message' => 'Server error.']], $this->render(new RuntimeException('password=s3cret')));
    }

    public function test_pagination_has_exactly_three_meta_fields_and_caps_per_page(): void
    {
        $request = Request::create('/?per_page=1000&page=0');
        $this->assertSame(100, PaginatedCollection::perPage($request));
        $this->assertSame(1, PaginatedCollection::page($request));
        $this->assertSame(20, PaginatedCollection::perPage(Request::create('/?per_page=abc')));

        $paginator = new LengthAwarePaginator([['n' => 1]], 41, 20, 1);
        $body = (new PaginatedCollection($paginator, JsonResource::class))->toResponse($request)->getData(true);

        $this->assertSame(['data' => [['n' => 1]], 'meta' => ['current_page' => 1, 'last_page' => 3, 'total' => 41]], $body);
    }
}
