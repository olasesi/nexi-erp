<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StoreJournalEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'company_id' => 'required|exists:companies,id',
            'entry_date' => 'required|date',
            'description' => 'nullable|string',
            'status' => 'nullable|in:draft,posted',
            'lines' => 'required|array|min:2',
            'lines.*.chart_of_account_id' => 'required_without:lines.*.account_code|exists:chart_of_accounts,id',
            'lines.*.account_code' => 'required_without:lines.*.chart_of_account_id|string|max:20',
            'lines.*.description' => 'nullable|string',
            'lines.*.debit' => 'required_without:lines.*.credit|numeric|min:0',
            'lines.*.credit' => 'required_without:lines.*.debit|numeric|min:0',
        ];
    }
}
