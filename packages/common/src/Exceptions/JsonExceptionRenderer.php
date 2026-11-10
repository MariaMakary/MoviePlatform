<?php

namespace MoviePlatform\Common\Exceptions;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

class JsonExceptionRenderer
{
    public const VALIDATION_MESSAGE = 'The given data was invalid.';

    public static function validationResponse(array $errors, int $status = 422): JsonResponse
    {
        return new JsonResponse(['message' => self::VALIDATION_MESSAGE, 'errors' => $errors], $status);
    }

    public function render(Request $request, Throwable $e): Response
    {
        if (method_exists($e, 'render') && ($response = $e->render($request))) {
            return $response;
        }

        if ($e instanceof Responsable) {
            return $e->toResponse($request);
        }

        return match (true) {
            $e instanceof HttpResponseException => $e->getResponse(),
            $e instanceof ValidationException => self::validationResponse($e->errors(), $e->status),
            $e instanceof AuthenticationException => $this->json('Unauthenticated.', 401),
            $e instanceof AuthorizationException => $this->json($e->getMessage() ?: 'This action is unauthorized.', $e->status() ?? 403),
            $e instanceof ModelNotFoundException => $this->json(($e->getModel() ? class_basename($e->getModel()).' not found.' : 'Not found.'), 404),
            $e instanceof HttpExceptionInterface => $this->json($e->getMessage() ?: $this->statusMessage($e->getStatusCode()), $e->getStatusCode(), $e->getHeaders()),
            default => $this->json('Server error.', 500),
        };
    }

    private function json(string $message, int $status, array $headers = []): JsonResponse
    {
        return new JsonResponse(['message' => $message], $status, $headers);
    }

    private function statusMessage(int $status): string
    {
        return match ($status) {
            404 => 'Not found.',
            405 => 'Method not allowed.',
            default => (Response::$statusTexts[$status] ?? 'Server error').'.',
        };
    }
}
