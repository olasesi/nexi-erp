<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Concerns\IssuesPasswordTokens;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\RegisterCustomerRequest;
use App\Http\Resources\ContactResource;
use App\Http\Resources\InvoiceResource;
use App\Http\Resources\PaymentResource;
use App\Http\Resources\QuotationResource;
use App\Http\Resources\SalesOrderResource;
use App\Models\Contact;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Quotation;
use App\Models\SalesOrder;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class CustomerPortalController extends Controller
{
    use IssuesPasswordTokens;

    /**
     * Register a portal account for a customer contact.
     */
    public function register(RegisterCustomerRequest $request): JsonResponse
    {
        $data = $request->validated();

        $contact = Contact::where('email', $data['email'])
            ->whereIn('type', ['customer', 'both'])
            ->where('is_active', true)
            ->firstOrFail();

        $user = User::create([
            'company_id' => $contact->company_id,
            'contact_id' => $contact->id,
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
        ]);

        $customerRole = Role::firstOrCreate(['name' => 'customer', 'guard_name' => 'web']);
        $portalAccess = Permission::firstOrCreate(['name' => 'portal.access', 'guard_name' => 'web']);
        $customerRole->givePermissionTo($portalAccess);
        $user->assignRole($customerRole);

        return $this->issuePasswordToken([
            'email' => $data['email'],
            'password' => $data['password'],
        ])->setStatusCode(JsonResponse::HTTP_CREATED);
    }

    /**
     * The customer's own profile with their linked contact.
     */
    public function me(Request $request): JsonResponse
    {
        $user = $this->customerUser($request);

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
            'company_id' => $user->company_id,
            'contact' => new ContactResource($user->contact),
        ]);
    }

    public function invoices(Request $request): JsonResponse
    {
        return InvoiceResource::collection($this->customerQuery(Invoice::class, $this->customerUser($request))
            ->get())
            ->response();
    }

    public function showInvoice(Request $request, int $invoice): JsonResponse
    {
        $item = $this->customerQuery(Invoice::class, $this->customerUser($request))->findOrFail($invoice);

        return (new InvoiceResource($item))->response();
    }

    public function quotations(Request $request): JsonResponse
    {
        return QuotationResource::collection($this->customerQuery(Quotation::class, $this->customerUser($request))
            ->get())
            ->response();
    }

    public function showQuotation(Request $request, int $quotation): JsonResponse
    {
        $item = $this->customerQuery(Quotation::class, $this->customerUser($request))->findOrFail($quotation);

        return (new QuotationResource($item))->response();
    }

    public function salesOrders(Request $request): JsonResponse
    {
        return SalesOrderResource::collection($this->customerQuery(SalesOrder::class, $this->customerUser($request))
            ->get())
            ->response();
    }

    public function showSalesOrder(Request $request, int $salesOrder): JsonResponse
    {
        $item = $this->customerQuery(SalesOrder::class, $this->customerUser($request))->findOrFail($salesOrder);

        return (new SalesOrderResource($item))->response();
    }

    public function payments(Request $request): JsonResponse
    {
        return PaymentResource::collection($this->customerQuery(Payment::class, $this->customerUser($request))
            ->get())
            ->response();
    }

    public function showPayment(Request $request, int $payment): JsonResponse
    {
        $item = $this->customerQuery(Payment::class, $this->customerUser($request))->findOrFail($payment);

        return (new PaymentResource($item))->response();
    }

    /**
     * The authenticated customer; the middleware guarantees this is a
     * contact-linked user, so an unauthenticated request aborts here.
     */
    private function customerUser(Request $request): User
    {
        $user = $request->user();

        if (! $user) {
            abort(401);
        }

        return $user;
    }

    /**
     * Query builder restricted to the authenticated customer's contact
     * (and, via global scopes, their company).
     *
     * @return Builder<Model>
     */
    private function customerQuery(string $model, User $user)
    {
        return $model::query()->where('contact_id', $user->contact_id);
    }
}
