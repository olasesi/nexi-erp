<?php

namespace App\Http\Resources;

use App\Models\JournalEntryLine;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin JournalEntryLine */
class JournalEntryLineResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'chart_of_account_id' => $this->chart_of_account_id,
            'account_code' => $this->chartOfAccount->code,
            'account_name' => $this->chartOfAccount->name,
            'description' => $this->description,
            'debit' => (float) $this->debit,
            'credit' => (float) $this->credit,
        ];
    }
}
