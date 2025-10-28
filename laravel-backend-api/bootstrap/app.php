<?php

use App\Http\Resources\ApiErrorResource;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Enable Sanctum stateful API so SPA requests can authenticate via cookies
        $middleware->statefulApi();
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(function (Request $request, \Throwable $e): bool {
            return $request->is('api/*') || $request->expectsJson();
        });

        $exceptions->render(function (\Throwable $e, Request $request) {
            if (! ($request->is('api/*') || $request->expectsJson())) {
                return null;
            }

            $makeErrorResponse = static function (
                string $message,
                int $status,
                array $details = [],
                array $headers = []
            ) {
                $resource = ApiErrorResource::make([
                    'message' => $message,
                    'details' => $details,
                ]);

                $response = $resource->response()->setStatusCode($status);

                if (! empty($headers)) {
                    foreach ($headers as $key => $value) {
                        $response->headers->set($key, $value);
                    }
                }

                return $response;
            };

            return match (true) {
                $e instanceof HttpResponseException => $e->getResponse(),
                $e instanceof ValidationException => $makeErrorResponse(
                    'Validation failed',
                    Response::HTTP_UNPROCESSABLE_ENTITY,
                    $e->errors()
                ),
                $e instanceof AuthenticationException => $makeErrorResponse(
                    $e->getMessage() ?: 'Unauthenticated.',
                    Response::HTTP_UNAUTHORIZED
                ),
                $e instanceof AuthorizationException => $makeErrorResponse(
                    $e->getMessage() ?: 'This action is unauthorized.',
                    Response::HTTP_FORBIDDEN
                ),
                $e instanceof ModelNotFoundException => $makeErrorResponse(
                    'Resource not found.',
                    Response::HTTP_NOT_FOUND
                ),
                $e instanceof HttpExceptionInterface => $makeErrorResponse(
                    $e->getMessage() ?: (Response::$statusTexts[$e->getStatusCode()] ?? 'Error'),
                    $e->getStatusCode(),
                    [],
                    method_exists($e, 'getHeaders') ? $e->getHeaders() : []
                ),
                default => $makeErrorResponse(
                    'Server Error',
                    Response::HTTP_INTERNAL_SERVER_ERROR,
                    config('app.debug') ? [
                        'exception' => get_class($e),
                        'message' => $e->getMessage(),
                    ] : []
                ),
            };
        });
    })->create();
