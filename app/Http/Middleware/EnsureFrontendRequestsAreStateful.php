<?php

namespace App\Http\Middleware;

use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful as SanctumStatefulRequests;
use Laravel\Sanctum\Sanctum;

class EnsureFrontendRequestsAreStateful extends SanctumStatefulRequests
{
    /**
     * Allow same-host AJAX requests to count as first-party even when the browser
     * omits Origin/Referer headers.
     */
    public static function fromFrontend($request)
    {
        $domain = $request->headers->get('referer') ?: $request->headers->get('origin');

        if ($domain !== null) {
            return parent::fromFrontend($request);
        }

        $statefulDomains = array_filter(config('sanctum.stateful', []));
        $requestHost = $request->getHttpHost();

        foreach ($statefulDomains as $uri) {
            $uri = $uri === Sanctum::$currentRequestHostPlaceholder
                ? $requestHost
                : trim($uri);

            if ($uri === $requestHost) {
                return true;
            }
        }

        return false;
    }
}
