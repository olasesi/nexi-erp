<?php

namespace App\Http\Resources;

use App\Models\RentalAgreement;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin RentalAgreement */
class RentalAgreementResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'contact_id' => $this->contact_id,
            'agreement_number' => $this->agreement_number,
            'start_at' => $this->start_at->toDateString(),
            'end_at' => $this->end_at->toDateString(),
            'status' => $this->status,
            'effective_status' => $this->effectiveStatus(),
            'deposit_amount' => $this->deposit_amount,
            'late_fee_per_day' => $this->late_fee_per_day,
            'days_overdue' => $this->daysOverdue(),
            'late_fee' => $this->lateFee(),
            'total_value' => $this->totalValue(),
            'notes' => $this->notes,
            'created_by' => $this->created_by,
            'items' => RentalItemResource::collection($this->whenLoaded('items')),
            'contact' => new ContactResource($this->whenLoaded('contact')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->deleted_at,
        ];
    }
}
