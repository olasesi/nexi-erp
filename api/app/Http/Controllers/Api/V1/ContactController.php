<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\BulkImportRequest;
use App\Http\Resources\ContactResource;
use App\Models\Contact;
use App\Services\BulkImportService;
use App\Services\Import\ContactImporter;
use App\Support\Csv;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

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

    /**
     * Create or update contacts in bulk from an uploaded or inline CSV file.
     */
    public function import(BulkImportRequest $request): JsonResponse
    {
        $data = $request->validated();
        $dryRun = (bool) ($data['dry_run'] ?? false);

        $result = app(BulkImportService::class)->run(
            new ContactImporter,
            $request->csvContents(),
            (int) $data['company_id'],
            $dryRun,
        );

        return response()->json(array_merge($result, ['dry_run' => $dryRun]));
    }

    /**
     * Download the contact list as a CSV file.
     */
    public function export(): StreamedResponse
    {
        $query = $this->model->query();

        if ($companyId = request()->integer('company_id')) {
            $query->where('company_id', $companyId);
        }

        $rows = $query->orderBy('id')->cursor()->map(fn (Contact $contact) => [
            $contact->id,
            $contact->first_name,
            $contact->last_name,
            $contact->email,
            $contact->phone,
            $contact->type,
            $contact->is_active ? 1 : 0,
        ])->all();

        return Csv::download(
            'contacts.csv',
            ['id', 'first_name', 'last_name', 'email', 'phone', 'type', 'is_active'],
            $rows,
        );
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
