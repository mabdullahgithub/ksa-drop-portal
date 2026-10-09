<?php

namespace Tests\Feature\Permissions;

use App\Support\PermissionCatalog;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Symfony\Component\Finder\SplFileInfo;
use Tests\TestCase;

/**
 * The catalog is the one list of permissions. A name used by a route or a
 * button that is not in it would be a gate nobody can open, or a button that
 * silently never shows; a name in it that nothing uses would be a tick box in
 * the role editor that does nothing.
 */
class PermissionCatalogTest extends TestCase
{
    /** Routes for a page that does not exist yet; their permissions are not in the catalog. */
    private const NOT_IN_CATALOG = ['view teams', 'create teams', 'edit teams', 'delete teams'];

    public function test_every_permission_is_named_once(): void
    {
        $names = PermissionCatalog::names();

        $this->assertSame($names, array_values(array_unique($names)));
        $this->assertGreaterThan(150, count($names));
    }

    public function test_every_required_permission_exists(): void
    {
        foreach (PermissionCatalog::modules() as $module) {
            foreach ($module['groups'] as $group) {
                foreach ($group['permissions'] as $permission) {
                    if ($permission['requires'] !== null) {
                        $this->assertTrue(
                            PermissionCatalog::has($permission['requires']),
                            "'{$permission['name']}' requires '{$permission['requires']}', which is not in the catalog."
                        );
                    }
                }
            }
        }
    }

    public function test_every_route_permission_is_in_the_catalog(): void
    {
        foreach ($this->routePermissions() as $permission) {
            if (in_array($permission, self::NOT_IN_CATALOG, true)) {
                continue;
            }

            $this->assertTrue(PermissionCatalog::has($permission), "A route is gated on '{$permission}', which is not in the catalog.");
        }
    }

    public function test_every_permission_checked_in_the_backend_is_in_the_catalog(): void
    {
        foreach ($this->files(app_path(), ['php']) as $file) {
            if ($file->getFilename() === 'PermissionCatalog.php') {
                continue;
            }

            foreach ($this->checkedIn($file->getContents(), ['can', 'canAny']) as $permission) {
                $this->assertTrue(PermissionCatalog::has($permission), "{$file->getRelativePathname()} checks '{$permission}', which is not in the catalog.");
            }
        }
    }

    public function test_every_permission_checked_in_the_frontend_is_in_the_catalog(): void
    {
        foreach ($this->files(resource_path('js'), ['ts', 'tsx']) as $file) {
            $source = $file->getContents();

            $checked = [
                ...$this->checkedIn($source, ['can', 'canOrPortal', 'canAll']),
                // <Can permission='…'> and the sidebar's `permission: '…'`.
                ...$this->literals($source, '/\bpermission\s*[=:]\s*\{?\s*(\[[^\]]*\]|\'[^\']*\'|"[^"]*")/'),
            ];

            foreach ($checked as $permission) {
                $this->assertTrue(PermissionCatalog::has($permission), "{$file->getRelativePathname()} checks '{$permission}', which is not in the catalog.");
            }
        }
    }

    public function test_every_permission_in_the_catalog_gates_something(): void
    {
        $routes = $this->routePermissions();

        $source = '';
        foreach ([...$this->files(app_path(), ['php']), ...$this->files(resource_path('js'), ['ts', 'tsx'])] as $file) {
            if ($file->getFilename() !== 'PermissionCatalog.php') {
                $source .= $file->getContents();
            }
        }

        foreach (PermissionCatalog::names() as $permission) {
            $used = in_array($permission, $routes, true)
                || str_contains($source, "'{$permission}'")
                || str_contains($source, "\"{$permission}\"");

            $this->assertTrue($used, "'{$permission}' is in the catalog but no route, controller or button uses it.");
        }
    }

    public function test_ticking_a_button_brings_the_page_it_sits_on(): void
    {
        $this->assertEqualsCanonicalizing(
            ['view orders', 'view order details', 'view shipments', 'cancel shipments'],
            PermissionCatalog::withRequired(['cancel shipments']),
        );
    }

    /** @return list<string> */
    private function routePermissions(): array
    {
        $permissions = [];

        foreach (Route::getRoutes() as $route) {
            foreach ($route->gatherMiddleware() as $middleware) {
                if (is_string($middleware) && str_starts_with($middleware, 'permission:')) {
                    array_push($permissions, ...array_map('trim', explode('|', substr($middleware, 11))));
                }
            }
        }

        return array_values(array_unique($permissions));
    }

    /**
     * The permission names passed as literals to any of `$functions`.
     *
     * @param  list<string>  $functions
     * @return list<string>
     */
    private function checkedIn(string $source, array $functions): array
    {
        $names = implode('|', $functions);

        return $this->literals($source, '/(?:->|\b)(?:' . $names . ')\(\s*(\[[^\]]*\]|\'[^\']*\'|"[^"]*")/');
    }

    /**
     * Quoted strings inside the first capture of `$pattern`, leaving out
     * anything built at run time.
     *
     * @return list<string>
     */
    private function literals(string $source, string $pattern): array
    {
        preg_match_all($pattern, $source, $matches);

        $names = [];
        foreach ($matches[1] as $argument) {
            preg_match_all('/\'([^\'{$]+)\'|"([^"{$]+)"/', $argument, $strings);
            array_push($names, ...array_filter([...$strings[1], ...$strings[2]]));
        }

        return array_values(array_unique($names));
    }

    /**
     * @param  list<string>  $extensions
     * @return list<SplFileInfo>
     */
    private function files(string $directory, array $extensions): array
    {
        return array_values(array_filter(
            File::allFiles($directory),
            fn (SplFileInfo $file) => in_array($file->getExtension(), $extensions, true),
        ));
    }
}
