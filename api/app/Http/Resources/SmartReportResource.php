<?php

namespace App\Http\Resources;

use App\Models\SmartReport;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin SmartReport */
class SmartReportResource extends JsonResource
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
            'description' => $this->description,
            'source' => $this->source,
            'columns' => $this->columns,
            'filters' => $this->filters,
            'group_by' => $this->group_by,
            'aggregate' => $this->aggregate,
            'aggregate_field' => $this->aggregate_field,
            'chart_type' => $this->chart_type,
            'is_shared' => $this->is_shared,
            'created_by' => $this->created_by,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->deleted_at,
        ];
    }
}
