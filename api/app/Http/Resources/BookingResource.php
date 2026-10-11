<?php

namespace App\Http\Resources;

use App\Models\Booking;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Booking */
class BookingResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'resource_id' => $this->resource_id,
            'resource' => $this->whenLoaded('resource', fn () => new BookingResourceResource($this->resource)),
            'title' => $this->title,
            'customer_name' => $this->customer_name,
            'contact_id' => $this->contact_id,
            'starts_at' => $this->starts_at->toDateTimeString(),
            'ends_at' => $this->ends_at->toDateTimeString(),
            'status' => $this->status,
            'notes' => $this->notes,
            'created_by' => $this->created_by,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->deleted_at,
        ];
    }
}
