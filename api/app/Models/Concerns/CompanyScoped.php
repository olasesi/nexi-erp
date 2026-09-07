<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;

/**
 * Automatically scopes queries to the authenticated user's company.
 *
 * Models using this trait MUST have a `company_id` column. When no
 * authenticated API user is present (or the user has no company), the
 * scope is skipped entirely, keeping tests and CLI operations untouched.
 */
trait CompanyScoped
{
    protected static function bootCompanyScoped(): void
    {
        static::addGlobalScope('company_scope', function (Builder $builder) {
            $user = auth('api')->user();

            if ($user && $user->company_id) {
                $builder->where($builder->getModel()->getTable().'.company_id', $user->company_id);
            }
        });
    }
}
