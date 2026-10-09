<?php

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;

/*
| Arch rules for the whole app, plus ACL-07 (dead or contradictory access-control code) and
| ACL-08 (env() outside config/ returns null once `php artisan config:cache` has run).
*/

arch('no debug helpers in app code')
    ->expect('App')
    ->not->toUse(['dd', 'dump', 'ray', 'var_dump']);

arch('env() is only read in config files')
    ->expect('App')
    ->not->toUse('env');

/**
 * @return list<string> relative paths of files under $dir whose contents match $pattern
 */
function aclFilesMatching(string $dir, string $pattern): array
{
    return collect(File::allFiles(base_path($dir)))
        ->filter(fn (SplFileInfo $file) => preg_match($pattern, file_get_contents($file->getPathname())))
        ->map(fn (SplFileInfo $file) => str_replace(base_path().'/', '', $file->getPathname()))
        ->sort()->values()->all();
}

describe('ACL-08 → env() outside config/', function () {
    it('is not called from Blade views or route files', function () {
        expect(aclFilesMatching('resources/views', '/\benv\(/'))->toBe([])
            ->and(aclFilesMatching('routes', '/\benv\(/'))->toBe([]);
    });
});

describe('ACL-07 → dead access-control code', function () {
    it('registers every middleware class in app/Http/Middleware', function () {
        $kernel = app(\Illuminate\Contracts\Http\Kernel::class);
        $registered = collect($kernel->getMiddlewareAliases())->values()
            ->merge(collect($kernel->getMiddlewareGroups())->flatten())
            ->merge($kernel->getGlobalMiddleware())
            ->merge(collect(Route::getRoutes()->getRoutes())->flatMap(fn ($route) => $route->gatherMiddleware()))
            ->filter(fn ($m) => is_string($m))
            ->unique();

        $unregistered = collect(File::files(app_path('Http/Middleware')))
            ->map(fn (SplFileInfo $file) => 'App\\Http\\Middleware\\'.$file->getBasename('.php'))
            ->reject(fn (string $class) => $registered->contains($class))
            ->values()->all();

        expect($unregistered)->toBe([]);
    });

    it('only imports classes that exist in bootstrap/app.php', function () {
        preg_match_all('/^use ([\w\\\\]+);/m', file_get_contents(base_path('bootstrap/app.php')), $matches);

        $missing = collect($matches[1])
            ->reject(fn (string $class) => class_exists($class) || interface_exists($class))
            ->values()->all();

        expect($missing)->toBe([]);
    });

    it('uses every trait in app/Traits', function () {
        $unused = collect(File::files(app_path('Traits')))
            ->map(fn (SplFileInfo $file) => $file->getBasename('.php'))
            ->reject(fn (string $trait) => collect(aclFilesMatching('app', '/^\s*use [^;]*\b'.$trait.'\b[^;]*;/m'))
                ->reject(fn (string $path) => $path === "app/Traits/$trait.php")
                ->isNotEmpty())
            ->values()->all();

        expect($unused)->toBe([]);
    });
});
