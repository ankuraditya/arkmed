<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        foreach (['Tablets', 'Syrups', 'Inhalers', 'Gels', 'Ointments', 'Wellness'] as $index => $name) {
            Category::updateOrCreate(['slug' => str($name)->slug()], ['name' => $name, 'sort_order' => $index + 1, 'is_active' => true]);
        }
    }
}
