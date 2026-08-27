<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\ContactResource;
use App\Models\Contact;
use Illuminate\Http\JsonResponse;

/** @property Contact $model */
class ContactController extends BaseController
{
    protected $model;

    protected string $resourceClass = ContactResource::class;

    protected string $storeRequestClass = 'App\Http\Requests\Api\V1\StoreContactRequest';

    protected string $updateRequestClass = 'App\Http\Requests\Api\V1\UpdateContactRequest';

    public function __construct()
    {
        $this->model = new Contact;
    }

    public function index(): JsonResponse
    {
        $query = $this->model->query()->with(['addresses']);
        $query = $this->applyFilters($query);

        $perPage = request()->integer('per_page', 15);
        $items = $query->paginate(min($perPage, 100));

        return $this->resourceClass::collection($items)->response();
    }

    protected function getFilterableFields(): array
    {
        return ['type', 'company_id', 'is_active'];
    }

    protected function getSearchableFields(): array
    {
        return ['first_name', 'last_name', 'email', 'phone'];
    }
}
