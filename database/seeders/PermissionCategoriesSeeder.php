<?php

namespace Database\Seeders;

use App\Support\PermissionCatalog;
use Illuminate\Database\Seeder;

class PermissionCategoriesSeeder extends Seeder
{
    /**
     * Categories come from the same list as the permissions; see
     * App\Support\PermissionCatalog.
     */
    public function run(): void
    {
        PermissionCatalog::sync();

        $this->command->info('Permission categories seeded successfully!');
    }
}
