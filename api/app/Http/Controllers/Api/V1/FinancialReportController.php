<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\ReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FinancialReportController extends Controller
{
    public function __construct(private readonly ReportService $reports) {}

    public function profitAndLoss(Request $request): JsonResponse
    {
        $data = $request->validate([
            'company_id' => 'required|exists:companies,id',
            'from' => 'required|date',
            'to' => 'required|date|after_or_equal:from',
        ]);

        return response()->json($this->reports->profitAndLoss($data['company_id'], $data['from'], $data['to']));
    }

    public function balanceSheet(Request $request): JsonResponse
    {
        $data = $request->validate([
            'company_id' => 'required|exists:companies,id',
            'as_of' => 'required|date',
        ]);

        return response()->json($this->reports->balanceSheet($data['company_id'], $data['as_of']));
    }

    public function cashFlow(Request $request): JsonResponse
    {
        $data = $request->validate([
            'company_id' => 'required|exists:companies,id',
            'from' => 'required|date',
            'to' => 'required|date|after_or_equal:from',
        ]);

        return response()->json($this->reports->cashFlow($data['company_id'], $data['from'], $data['to']));
    }

    public function aging(Request $request): JsonResponse
    {
        $data = $request->validate([
            'company_id' => 'required|exists:companies,id',
            'type' => 'sometimes|in:receivable,payable',
        ]);

        return response()->json($this->reports->aging($data['company_id'], $data['type'] ?? 'receivable'));
    }
}
