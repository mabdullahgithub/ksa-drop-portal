<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * The permissions for a test role, written the short way.
     *
     * Most tests are about one feature and only need "someone who can work
     * with orders". Naming a broad permission from before the catalog
     * ('edit orders', 'manage riders', ...) gives the role every button that
     * bundle used to cover, exactly as the migration gave real roles; a
     * catalog permission is taken as it is. Tests about one button name that
     * button's permission and nothing broader.
     *
     * @param  string|\Spatie\Permission\Contracts\Permission|array<int, string|\Spatie\Permission\Contracts\Permission>  $permissions
     * @return list<string>
     */
    protected function permissions(mixed $permissions): array
    {
        $names = array_map(
            fn ($permission) => is_string($permission) ? $permission : $permission->name,
            is_array($permissions) ? $permissions : [$permissions],
        );

        $names = \App\Support\PermissionCatalog::expandLegacy($names);

        foreach ($names as $name) {
            \Spatie\Permission\Models\Permission::findOrCreate($name);
        }

        return $names;
    }

    //
}
