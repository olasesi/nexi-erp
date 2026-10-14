<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Generic file upload endpoint. Validates the payload against the allow-list in
 * config/uploads.php, stores it on the configured disk under a date-based
 * folder, and returns a metadata envelope callers can persist against any
 * record (attachments, avatars, product images, ...).
 */
class UploadController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $max = (int) config('uploads.max_kilobytes', 10240);
        $mimes = implode(',', (array) config('uploads.mimes', []));

        $request->validate([
            'file' => ['required', 'file', 'max:'.$max, 'mimes:'.$mimes],
        ]);

        $file = $request->file('file');
        $disk = (string) config('uploads.disk', 'public');
        $base = trim((string) config('uploads.path', 'uploads'), '/');
        $directory = $base.'/'.now()->format('Y/m');

        $path = $file->store($directory, $disk);

        if ($path === false) {
            throw new RuntimeException('The uploaded file could not be stored.');
        }

        return response()->json([
            'data' => [
                'path' => $path,
                'url' => Storage::disk($disk)->url($path),
                'name' => $file->getClientOriginalName(),
                'mime' => $file->getClientMimeType(),
                'size' => $file->getSize(),
            ],
        ], 201);
    }
}
