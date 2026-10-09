<?php

namespace App\Http\Resources;

use App\Models\CoworkingBooking;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin CoworkingBooking */
class CoworkingBookingResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'unit_id' => $this->unit_id,
            'unit' => $this->whenLoaded('unit', fn () => new CoworkingUnitResource($this->unit)),
            'contact_id' => $this->contact_id,
            'start_at' => $this->start_at->toDateTimeString(),
            'end_at' => $this->end_at->toDateTimeString(),
            'seats' => $this->seats,
            'status' => $this->status,
            'price' => $this->price,
            'notes' => $this->notes,
            'created_by' => $this->created_by,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->deleted_at,
        ];
    }
}
