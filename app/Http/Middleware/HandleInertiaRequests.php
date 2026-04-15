<?php

namespace App\Http\Middleware;

use App\Services\MenuCategoryBuilder;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'locale' => app()->getLocale(),
            'auth' => [
                'user' => fn () => $request->user()
                    ? [
                        'id' => $request->user()->id,
                        'name' => $request->user()->name,
                        'email' => $request->user()->email,
                    ]
                    : null,
            ],
            'navigation' => [
                'menu' => [
                    // Lazy share to avoid computing the menu on requests that do not need it.
                    'categories' => fn () => app(MenuCategoryBuilder::class)->build(),
                ],
            ],
            'translations' => [
                'auth' => trans('auth'),
                'ui' => trans('ui'),
                'pages' => trans('pages'),
                'menu' => trans('menu'),
            ],
            'flash' => [
                'status' => fn () => $request->session()->get('status'),
                'oauth_error' => fn () => $request->session()->get('oauth_error'),
            ],
            'settings' => [
                'access_groups' => [
                    'external_sync_enabled' => (bool) config('authentication.oauth.external_group_sync_enabled', false),
                    'external_provider_name' => (string) config('authentication.oauth.external_provider_name', 'External provider'),
                ],
                'departments' => [
                    'external_sync_enabled' => (bool) config('authentication.oauth.external_group_sync_enabled', false),
                    'external_provider_name' => (string) config('authentication.oauth.external_provider_name', 'External provider'),
                    'findings_enabled' => array_key_exists('findings', (array) config('generic_crud.resources', [])),
                ],
            ],
        ];
    }
}
