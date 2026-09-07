<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\BankAccountResource;
use App\Models\BankAccount;
use Illuminate\Http\JsonResponse;

/** @property BankAccount $model */
class BankAccountController extends BaseController
{
    protected $model;

    protected string $resourceClass = BankAccountResource::class;

    protected string $storeRequestClass = 'App\Http\Requests\Api\V1\StoreBankAccountRequest';

    protected string $updateRequestClass = 'App\Http\Requests\Api\V1\UpdateBankAccountRequest';

    public function __construct()
    {
        $this->model = new BankAccount;
    }

    public function index(): JsonResponse
    {
        $query = $this->model->query()->withCount('transactions');
        $query = $this->applyFilters($query);

        $perPage = request()->integer('per_page', 15);
        $items = $query->paginate(min($perPage, 100));

        return $this->resourceClass::collection($items)->response();
    }

    protected function getFilterableFields(): array
    {
        return ['company_id', 'account_type', 'is_active'];
    }

    protected function getSearchableFields(): array
    {
        return ['name', 'bank_name', 'account_number'];
    }
}
