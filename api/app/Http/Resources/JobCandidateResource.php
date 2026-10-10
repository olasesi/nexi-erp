<?php

namespace App\Http\Resources;

use App\Models\JobCandidate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin JobCandidate */
class JobCandidateResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'job_posting_id' => $this->job_posting_id,
            'posting' => $this->whenLoaded('posting', fn () => new JobPostingResource($this->posting)),
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'resume_url' => $this->resume_url,
            'source' => $this->source,
            'stage' => $this->stage,
            'expected_salary' => $this->expected_salary,
            'notes' => $this->notes,
            'hired_at' => $this->hired_at?->toDateTimeString(),
            'created_by' => $this->created_by,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->deleted_at,
        ];
    }
}
