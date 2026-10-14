<?php

namespace App\Http\Controllers;

use DateTimeImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Library\Application\Errors\ApiException;
use Library\Application\Ports\ReportReader;

final class ReportController
{
    public function __construct(private readonly ReportReader $reports)
    {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $data = $request->validate([
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d'],
        ]);
        if ($data['to'] < $data['from']) {
            throw new ApiException('INVALID_DATE_RANGE', 'La fecha final debe ser igual o posterior a la fecha inicial.', 422);
        }
        $from = new DateTimeImmutable($data['from'].'T00:00:00+00:00');
        $to = new DateTimeImmutable($data['to'].'T23:59:59.999999+00:00');

        return response()->json([
            'from' => $data['from'],
            'to' => $data['to'],
            'rows' => $this->reports->mostBorrowed($from, $to),
        ]);
    }
}
