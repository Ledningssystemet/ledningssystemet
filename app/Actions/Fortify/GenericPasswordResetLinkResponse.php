<?php

namespace App\Actions\Fortify;

use Illuminate\Http\JsonResponse;
use Laravel\Fortify\Contracts\FailedPasswordResetLinkRequestResponse as FailedPasswordResetLinkRequestResponseContract;
use Laravel\Fortify\Contracts\SuccessfulPasswordResetLinkRequestResponse as SuccessfulPasswordResetLinkRequestResponseContract;

/**
 * A single response used for both the "success" and "failure" outcomes of a
 * password reset link request.
 *
 * Laravel/Fortify's default behaviour returns a different HTTP status code
 * and a different message ("We can't find a user with that email address.")
 * when the submitted email address does not belong to an existing user.
 * That makes it trivial for an attacker to enumerate valid accounts by
 * repeatedly submitting the "forgot password" form. To avoid leaking this
 * information, we always return the same generic message with the same
 * HTTP status, regardless of whether the account exists, is disabled, or
 * was recently sent a reset link.
 */
class GenericPasswordResetLinkResponse implements
    FailedPasswordResetLinkRequestResponseContract,
    SuccessfulPasswordResetLinkRequestResponseContract
{
    public function __construct(protected string $status)
    {
    }

    /**
     * Create an HTTP response that represents the object.
     */
    public function toResponse($request)
    {
        $message = __('If an account exists for that email address, we have sent a password reset link to it.');

        if ($request->wantsJson()) {
            return new JsonResponse(['message' => $message], 200);
        }

        return back()->with('status', $message);
    }
}
