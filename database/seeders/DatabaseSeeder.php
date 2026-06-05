<?php

namespace Database\Seeders;

use App\Enums\ContactType;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\SalesOrder;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(RolesAndPermissionsSeeder::class);

        $company = Company::factory()->create([
            'name' => 'Nexi Corp',
            'email' => 'info@nexi-corp.com',
        ]);

        User::firstOrCreate(
            ['email' => 'admin@nexi-corp.com'],
            [
                'company_id' => $company->id,
                'name' => 'Admin User',
                'password' => bcrypt('password'),
            ]
        );

        $categories = ProductCategory::factory()->count(5)->create([
            'company_id' => $company->id,
        ]);

        Product::factory()->count(20)->create([
            'company_id' => $company->id,
            'category_id' => fn () => $categories->random()->id,
        ]);

        Warehouse::factory()->count(2)->create([
            'company_id' => $company->id,
        ]);

        Contact::factory()->count(10)->create([
            'company_id' => $company->id,
        ]);

        SalesOrder::factory()->count(5)->create([
            'company_id' => $company->id,
        ]);
    }
}
