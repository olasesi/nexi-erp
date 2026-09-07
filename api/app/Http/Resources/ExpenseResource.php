<?php

namespace App\Http\Resources;

use App\Models\Expense;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Expense */
class ExpenseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'expense_category_id' => $this->expense_category_id,
            'expense_category' => new ExpenseCategoryResource($this->whenLoaded('expenseCategory')),
            'reference_no' => $this->reference_no,
            'amount' => (float) $this->amount,
            'date' => $this->date,
            'payment_method' => $this->payment_method,
            'notes' => $this->notes,
            'created_by' => $this->created_by,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
