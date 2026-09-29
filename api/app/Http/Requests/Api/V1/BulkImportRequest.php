<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

class BulkImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'company_id' => 'required|exists:companies,id',
            'file' => 'nullable|required_without:csv|file|mimes:csv,txt',
            'csv' => 'nullable|required_without:file|string',
            'dry_run' => 'nullable|boolean',
        ];
    }

    /**
     * The raw CSV payload, whether it arrived as an upload or inline text.
     */
    public function csvContents(): string
    {
        $file = $this->file('file');

        if ($file instanceof UploadedFile) {
            return (string) file_get_contents($file->getPathname());
        }

        return (string) $this->string('csv');
    }
}
