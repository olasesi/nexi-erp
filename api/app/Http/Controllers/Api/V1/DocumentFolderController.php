<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\StoreDocumentFolderRequest;
use App\Http\Requests\Api\V1\UpdateDocumentFolderRequest;
use App\Http\Resources\DocumentFolderResource;
use App\Models\DocumentFolder;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class DocumentFolderController extends BaseController
{
    /** @var DocumentFolder */
    protected $model;

    protected string $resourceClass = DocumentFolderResource::class;

    protected string $storeRequestClass = StoreDocumentFolderRequest::class;

    protected string $updateRequestClass = UpdateDocumentFolderRequest::class;

    public function __construct()
    {
        $this->model = new DocumentFolder;
    }

    public function index(): JsonResponse
    {
        $query = $this->model->query();
        $query = $this->applyFilters($query);

        $perPage = request()->integer('per_page', 15);
        $items = $query->orderBy('name')->paginate(min($perPage, 100));

        return DocumentFolderResource::collection($items)->response();
    }

    public function destroy(int $id): JsonResponse
    {
        $folder = $this->model->findOrFail($id);

        if ($folder->children()->exists()) {
            throw ValidationException::withMessages([
                'parent_id' => ['Cannot delete a folder that still contains sub-folders.'],
            ]);
        }

        if ($folder->documents()->exists()) {
            throw ValidationException::withMessages([
                'folder_id' => ['Cannot delete a folder that still contains documents.'],
            ]);
        }

        $folder->delete();

        return response()->json(null, 204);
    }

    /**
     * @return list<string>
     */
    protected function getFilterableFields(): array
    {
        return ['company_id', 'parent_id'];
    }

    /**
     * @return list<string>
     */
    protected function getSearchableFields(): array
    {
        return ['name'];
    }
}
