<?php

namespace App\Http\Resources;

use App\Models\BusinessLocation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin BusinessLocation */
class BusinessLocationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'name' => $this->name,
            'location_id' => $this->location_id,
            'landmark' => $this->landmark,
            'city' => $this->city,
            'zip_code' => $this->zip_code,
            'state' => $this->state,
            'country' => $this->country,
            'price_group' => $this->price_group,
            'invoice_scheme' => $this->invoice_scheme,
            'invoice_layout_for_pos' => $this->invoice_layout_for_pos,
            'invoice_layout_for_sale' => $this->invoice_layout_for_sale,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
