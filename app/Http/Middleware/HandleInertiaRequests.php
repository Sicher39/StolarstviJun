<?php

namespace App\Http\Middleware;

use App\Models\Contact;
use App\Services\Seo\SeoPageResolver;
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
            'flash' => [
                'success' => fn (): ?string => $request->session()->get('success'),
            ],
            // Resolved for every Inertia response, because the Blade root view renders the very same
            // array into the first response: one payload feeds the server side and the client side head.
            'seo' => fn (): array => app(SeoPageResolver::class)->payloadFor($request),
            // The footer renders on every page, so the contact record is shared once here instead of being
            // passed by every controller. The lookup is read only: see Contact::sharedAttributes().
            'contact' => fn (): array => Contact::sharedAttributes(),
        ];
    }
}
