<?php

namespace Database\Factories;

use App\Enums\ContactType;
use App\Models\Company;
use App\Models\Contact;
use Illuminate\Database\Eloquent\Factories\Factory;

class ContactFactory extends Factory
{
    protected $model = Contact::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'type' => fake()->randomElement(ContactType::cases())->value,
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->phoneNumber(),
            'mobile' => fake()->phoneNumber(),
            'job_title' => fake()->jobTitle(),
            'department' => fake()->optional()->word(),
            'language' => fake()->locale(),
            'notes' => fake()->optional()->sentence(),
            'is_active' => true,
        ];
    }

    public function customer(): static
    {
        return $this->state(['type' => ContactType::Customer->value]);
    }

    public function supplier(): static
    {
        return $this->state(['type' => ContactType::Supplier->value]);
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
