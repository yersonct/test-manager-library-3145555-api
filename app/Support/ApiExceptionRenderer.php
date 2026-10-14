<?php

namespace App\Support;

use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Library\Application\Errors\ApiException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

final class ApiExceptionRenderer
{
    public static function render(Throwable $exception): JsonResponse
    {
        if ($exception instanceof ApiException) {
            return self::response($exception->errorCode, $exception->getMessage(), $exception->status, $exception->details);
        }
        if ($exception instanceof ValidationException) {
            $details = [];
            foreach (array_keys($exception->errors()) as $field) {
                $details[] = ['field' => $field, 'issue' => 'dato obligatorio o con formato inválido'];
            }

            return self::response('VALIDATION_ERROR', 'Los datos enviados no son válidos.', 422, $details);
        }
        if ($exception instanceof QueryException) {
            $constraint = $exception->errorInfo[2] ?? '';
            foreach ([
                'uq_book_active_isbn' => ['ISBN_TAKEN', 'Ya existe un libro activo con ese ISBN.'],
                'member_document_number_unique' => ['DOCUMENT_TAKEN', 'Ya existe un socio con ese documento.'],
                'user_username_unique' => ['USERNAME_TAKEN', 'Ese nombre de usuario ya está registrado.'],
            ] as $name => [$code, $message]) {
                if (str_contains($constraint, $name)) {
                    return self::response($code, $message, 409);
                }
            }

            return self::response('INTERNAL_ERROR', 'Ocurrió un error interno.', 500);
        }
        if ($exception instanceof HttpExceptionInterface) {
            $status = $exception->getStatusCode();
            $code = match ($status) {
                401 => 'UNAUTHENTICATED',
                403 => 'FORBIDDEN_ROLE',
                404 => 'NOT_FOUND',
                409 => 'CONFLICT',
                413 => 'VALIDATION_ERROR',
                default => $status >= 500 ? 'INTERNAL_ERROR' : 'REQUEST_ERROR',
            };
            $message = match ($status) {
                401 => 'Se requiere autenticación válida.',
                403 => 'No tiene permiso para realizar esta operación.',
                404 => 'No se encontró el recurso solicitado.',
                413 => 'El archivo supera el tamaño permitido.',
                default => $status >= 500 ? 'Ocurrió un error interno.' : 'No se pudo procesar la solicitud.',
            };

            return self::response($code, $message, $status);
        }

        report($exception);

        return self::response('INTERNAL_ERROR', 'Ocurrió un error interno.', 500);
    }

    public static function response(string $code, string $message, int $status, array $details = []): JsonResponse
    {
        return response()->json([
            'code' => $code,
            'message' => $message,
            'details' => $details,
        ], $status);
    }
}
