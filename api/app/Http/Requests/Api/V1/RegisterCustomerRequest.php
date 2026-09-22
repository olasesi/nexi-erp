<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Contact;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class RegisterCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ];
    }

    /**
     * Resolve the customer contact from the email address; the account is
     * only created for an existing active customer/both contact.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator) {
            $email = $this->input('email');

            if (! is_string($email) || $email === '') {
                return;
            }

            $contact = Contact::where('email', $email)
                ->whereIn('type', ['customer', 'both'])
                ->where('is_active', true)
                ->first();

            if (! $contact) {
                $validator->errors()->add('email', 'No active customer account was found for this email address.');

                return;
            }

            if ($contact->user()->exists()) {
                $validator->errors()->add('email', 'A portal account already exists for this customer.');
            }
        });
    }
}
