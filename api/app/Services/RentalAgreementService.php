<?php

namespace App\Services;

use App\Models\RentalAgreement;
use App\Models\RentalItem;
use Illuminate\Validation\ValidationException;

class RentalAgreementService
{
    public function __construct(
        private MetricsService $metrics
    ) {}

    /**
     * Company-scoped agreement number, e.g. RN-2026-0003.
     */
    public function generateNumber(int $companyId): string
    {
        $prefix = 'RN-'.date('Y');

        $count = RentalAgreement::query()
            ->where('company_id', $companyId)
            ->where('agreement_number', 'like', "{$prefix}-%")
            ->count();

        return sprintf('%s-%04d', $prefix, $count + 1);
    }

    public function activate(RentalAgreement $agreement): RentalAgreement
    {
        if ($agreement->status !== RentalAgreement::STATUS_DRAFT) {
            throw ValidationException::withMessages([
                'status' => 'Only a draft agreement can be activated.',
            ]);
        }

        $agreement->status = RentalAgreement::STATUS_ACTIVE;
        $agreement->save();

        $this->metrics->recordVertical('rental', 'activated');

        return $agreement;
    }

    public function cancel(RentalAgreement $agreement): RentalAgreement
    {
        if (! in_array($agreement->status, [RentalAgreement::STATUS_DRAFT, RentalAgreement::STATUS_ACTIVE], true)) {
            throw ValidationException::withMessages([
                'status' => 'A returned or cancelled agreement cannot be cancelled.',
            ]);
        }

        $agreement->status = RentalAgreement::STATUS_CANCELLED;
        $agreement->save();

        $this->metrics->recordVertical('rental', 'cancelled');

        return $agreement;
    }

    /**
     * Return one line item, then close the agreement if nothing is left out.
     */
    public function returnItem(RentalItem $item, ?string $condition = null): RentalItem
    {
        $agreement = $item->agreement;

        if ($agreement === null || $agreement->status !== RentalAgreement::STATUS_ACTIVE) {
            throw ValidationException::withMessages([
                'rental_agreement_id' => 'Items can only be returned on an active agreement.',
            ]);
        }

        if ($item->isReturned()) {
            throw ValidationException::withMessages([
                'returned_at' => 'This item has already been returned.',
            ]);
        }

        $item->returned_at = now();
        $item->return_condition = $condition ?? RentalItem::CONDITION_GOOD;
        $item->save();

        $this->metrics->recordVertical('rental', 'returned');

        $agreement->load('items');

        if ($agreement->allReturned()) {
            $agreement->status = RentalAgreement::STATUS_RETURNED;
            $agreement->save();
        }

        return $item;
    }
}
