<?php

namespace App\Services\Import;

use App\Models\Contact;

class ContactImporter implements RowImporter
{
    /**
     * @return array<int, string>
     */
    public function columns(): array
    {
        return ['first_name', 'last_name', 'email', 'phone', 'type', 'notes'];
    }

    /**
     * @param  array<string, string>  $row
     */
    public function error(array $row): ?string
    {
        if (trim($row['first_name'] ?? '') === '') {
            return 'first_name is required.';
        }

        $email = trim($row['email'] ?? '');

        if ($email !== '' && ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return 'email must be a valid email address.';
        }

        $type = trim($row['type'] ?? '') ?: 'customer';

        if (! in_array($type, ['customer', 'supplier', 'both'], true)) {
            return 'type must be customer, supplier or both.';
        }

        return null;
    }

    /**
     * @param  array<string, string>  $row
     */
    public function import(array $row, int $companyId): string
    {
        $email = trim($row['email'] ?? '');

        $attributes = [
            'first_name' => trim($row['first_name'] ?? ''),
            'last_name' => trim($row['last_name'] ?? ''),
            'email' => $email === '' ? null : $email,
            'phone' => trim($row['phone'] ?? '') ?: null,
            'type' => trim($row['type'] ?? '') ?: 'customer',
            'notes' => trim($row['notes'] ?? '') ?: null,
        ];

        $existing = $email === ''
            ? null
            : Contact::where('company_id', $companyId)->where('email', $email)->first();

        if ($existing) {
            $existing->update($attributes);

            return self::UPDATED;
        }

        Contact::create($attributes + ['company_id' => $companyId]);

        return self::CREATED;
    }
}
