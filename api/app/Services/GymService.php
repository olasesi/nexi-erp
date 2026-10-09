<?php

namespace App\Services;

use App\Models\GymMember;
use App\Models\GymVisit;
use Illuminate\Validation\ValidationException;

class GymService
{
    public function __construct(
        private MetricsService $metrics
    ) {}

    /**
     * Company-scoped member number, e.g. GM-26-0003.
     */
    public function generateMemberNumber(int $companyId): string
    {
        $prefix = 'GM-'.date('y');

        $count = GymMember::query()
            ->where('company_id', $companyId)
            ->where('member_number', 'like', "{$prefix}-%")
            ->count();

        return sprintf('%s-%04d', $prefix, $count + 1);
    }

    public function monthlyVisitCount(GymMember $member): int
    {
        return (int) $member->visits()
            ->where('checked_in_at', '>=', now()->startOfMonth())
            ->count();
    }

    /**
     * Admit a member, enforcing two invariants: the membership is live, and a
     * capped plan still has visits left this calendar month.
     */
    public function checkIn(GymMember $member, ?int $staffId = null): GymVisit
    {
        if (! $member->isActive()) {
            throw ValidationException::withMessages([
                'gym_member_id' => "This member is {$member->effectiveStatus()} and cannot check in.",
            ]);
        }

        $plan = $member->plan;

        if ($plan !== null && $plan->max_monthly_visits !== null) {
            $used = $this->monthlyVisitCount($member);

            if ($used >= $plan->max_monthly_visits) {
                throw ValidationException::withMessages([
                    'gym_member_id' => "This member has used all {$plan->max_monthly_visits} visits this month.",
                ]);
            }
        }

        $visit = GymVisit::create([
            'company_id' => $member->company_id,
            'gym_member_id' => $member->id,
            'checked_in_at' => now(),
            'checked_in_by' => $staffId,
        ]);

        $this->metrics->recordVertical('gym', 'checked_in');

        return $visit;
    }

    public function checkOut(GymVisit $visit): GymVisit
    {
        if ($visit->checked_out_at !== null) {
            throw ValidationException::withMessages([
                'checked_out_at' => 'This visit is already closed.',
            ]);
        }

        $visit->checked_out_at = now();
        $visit->save();

        $this->metrics->recordVertical('gym', 'checked_out');

        return $visit;
    }

    public function suspend(GymMember $member): GymMember
    {
        if ($member->status === GymMember::STATUS_CANCELLED) {
            throw ValidationException::withMessages([
                'status' => 'A cancelled member cannot be suspended.',
            ]);
        }

        $member->status = GymMember::STATUS_SUSPENDED;
        $member->save();

        $this->metrics->recordVertical('gym', 'suspended');

        return $member;
    }

    public function activate(GymMember $member): GymMember
    {
        if ($member->status === GymMember::STATUS_CANCELLED) {
            throw ValidationException::withMessages([
                'status' => 'A cancelled member cannot be reactivated.',
            ]);
        }

        $member->status = GymMember::STATUS_ACTIVE;
        $member->save();

        $this->metrics->recordVertical('gym', 'activated');

        return $member;
    }
}
