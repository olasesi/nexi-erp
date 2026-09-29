<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\BulkImportRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use App\Services\BulkImportService;
use App\Services\Import\ProductImporter;
use App\Support\Csv;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** @property Product $model */
class ProductController extends BaseController
{
    protected $model;

    protected string $resourceClass = ProductResource::class;

    protected string $storeRequestClass = 'App\Http\Requests\Api\V1\StoreProductRequest';

    protected string $updateRequestClass = 'App\Http\Requests\Api\V1\UpdateProductRequest';

    public function __construct()
    {
        $this->model = new Product;
    }

    public function index(): JsonResponse
    {
        $query = $this->model->query();
        $query = $this->applyFilters($query);

        $perPage = request()->integer('per_page', 15);
        $items = $query->paginate(min($perPage, 100));

        return $this->resourceClass::collection($items)->response();
    }

    /**
     * Create or update products in bulk from an uploaded or inline CSV file.
     */
    public function import(BulkImportRequest $request): JsonResponse
    {
        $data = $request->validated();
        $dryRun = (bool) ($data['dry_run'] ?? false);

        $result = app(BulkImportService::class)->run(
            new ProductImporter,
            $request->csvContents(),
            (int) $data['company_id'],
            $dryRun,
        );

        return response()->json(array_merge($result, ['dry_run' => $dryRun]));
    }

    /**
     * Download the product catalogue as a CSV file.
     */
    public function export(): StreamedResponse
    {
        $query = $this->model->query();

        if ($companyId = request()->integer('company_id')) {
            $query->where('company_id', $companyId);
        }

        $rows = $query->orderBy('id')->cursor()->map(fn (Product $product) => [
            $product->id,
            $product->name,
            $product->sku,
            $product->barcode,
            $product->type,
            $product->unit,
            (float) $product->sale_price,
            (float) $product->purchase_price,
            (int) $product->stock_quantity,
            (int) $product->min_stock_level,
        ])->all();

        return Csv::download(
            'products.csv',
            ['id', 'name', 'sku', 'barcode', 'type', 'unit', 'sale_price', 'purchase_price', 'stock_quantity', 'min_stock_level'],
            $rows,
        );
    }

    protected function getFilterableFields(): array
    {
        return ['company_id', 'category_id', 'type', 'is_active'];
    }

    protected function getSearchableFields(): array
    {
        return ['name', 'sku', 'barcode'];
    }
}
