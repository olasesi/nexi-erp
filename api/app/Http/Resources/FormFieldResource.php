<?php

namespace App\Http\Resources;

use App\Models\FormField;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin FormField */
class FormFieldResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'form_id' => $this->form_id,
            'label' => $this->label,
            'name' => $this->name,
            'type' => $this->type,
            'placeholder' => $this->placeholder,
            'options' => $this->options,
            'is_required' => $this->is_required,
            'position' => $this->position,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->deleted_at,
        ];
    }
}
