<?php

namespace Ghazali\EPassPackage\Traits;

use Ghazali\EPassPackage\Http\Controllers\AttestationAuthController;
use Ghazali\EPassPackage\Http\Controllers\HostSessionController;
use Ghazali\EPassPackage\Http\Controllers\Auth\AuthController as EpassAuthController;
use Ghazali\EPassPackage\Support\EpassCallbackContext;
use Ghazali\EPassPackage\Support\EpassDashboardRouteResolver;
use Ghazali\EPassPackage\Support\EpassPackageAuth;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

/**
 * Handles incoming ePass protocol intents for the Host Embassy controller.
 *
 * Plugs directly into DeveloperDashboardController (or any host dashboard controller)
 * to absorb all low-level technical protocol steps into a single gateway.
 */
trait HandlesEpassProtocol
{
    /**
     * Determine if the incoming request is an in-flight ePass protocol step.
     */
    public function isEpassProtocolIntent(Request $request): bool
    {
        if ($request->filled('intent')) {
            return true;
        }

        // If encrypted passport payload is submitted with an action, it is an ePass protocol request
        if ($request->filled('encrypted') && ($request->filled('action') || $request->filled('epass_callback_action'))) {
            return true;
        }

        return false;
    }

    /**
     * Dispatch the technical protocol step based on the `intent` parameter.
     */
    public function handleEpassProtocolIntent(Request $request): mixed
    {
        $intent = strtolower(trim((string) ($request->input('intent') ?? $request->input('action') ?? $request->input('epass_callback_action') ?? '')));
        $attestationController = app(AttestationAuthController::class);

        Log::info('[HANDLES_EPASS_PROTOCOL][DISPATCH_START]', [
            'intent' => $intent,
            'ip' => $request->ip(),
            'method' => $request->method(),
            'inputs' => array_keys($request->all()),
            'has_encrypted' => $request->filled('encrypted'),
        ]);

        return match ($intent) {
            'start' => app(HostSessionController::class)->startEpassSession($request),
            'welcome' => $this->handleEpassProtocolWelcome($request),
            'handoff' => $attestationController->standardHandoff($request),
            'exchange' => $attestationController->standardExchange($request),
            'challenge' => $attestationController->challenge($request),
            'fetch_qr2', 'fetch-qr2' => $attestationController->standardFetchQr2($request),
            'receive_phone_key', 'receive-phone-public-key' => $attestationController->standardReceivePhonePublicKey($request),
            'public_key', 'public-key' => app(EpassAuthController::class)->loginPublicKey(),
            'check_model', 'check-model' => app(\Ghazali\EPassPackage\Http\Controllers\PassportAuthController::class)->checkModel($request),
            'csrf' => app(EpassAuthController::class)->csrfToken(),
            'init', 'init-session' => $attestationController->standardInitSession($request),
            'mobile_direct_start' => $attestationController->mobileDirectStart($request),
            'mobile_direct_submit' => $attestationController->mobileDirectSubmit($request),
            'mobile_direct_poll' => $attestationController->mobileDirectPoll($request),
            'check_status', 'check-status' => $attestationController->standardCheckStatus($request),
            'verify_decrypted', 'verify-decrypted' => $attestationController->standardVerifyDecrypted($request),
            'verify' => ($request->has('ciphertext_b64') || $request->has('phone_public_b64'))
                ? $attestationController->verify($request)
                : $attestationController->standardVerifyDecrypted($request),
            'verify_login', 'verify-login' => $attestationController->standardVerifyLogin($request),
            'update' => $this->handleEpassProtocolUpdate($request, $attestationController),
            'logout' => $this->handleEpassProtocolLogout($request),
            'login' => app(\Ghazali\EPassPackage\Http\Controllers\PassportAuthController::class)->login($request, $this),
            'register' => app(\Ghazali\EPassPackage\Http\Controllers\PassportAuthController::class)->register($request, $this),
            'link' => app(\Ghazali\EPassPackage\Http\Controllers\PassportAuthController::class)->link($request),
            'me' => app(\Ghazali\EPassPackage\Http\Controllers\PassportAuthController::class)->me($request),
            default => (function () use ($intent, $request) {
                Log::warning('[HANDLES_EPASS_PROTOCOL][UNKNOWN_INTENT]', [
                    'intent' => $intent,
                    'all_inputs' => array_keys($request->all()),
                ]);
                return response()->json([
                    'success' => false,
                    'message' => "Unknown ePass protocol intent [{$intent}].",
                ], 400);
            })(),
        };
    }

    /**
     * Handles ePass welcome / QR display screen.
     */
    protected function handleEpassProtocolWelcome(Request $request): mixed
    {
        return view('epass::welcome');
    }

    /**
     * Handles package-internal passport renewal (update).
     * Updates epass_users record, revokes old tokens, and redirects or responds with status.
     */
    protected function handleEpassProtocolUpdate(Request $request, AttestationAuthController $controller): mixed
    {
        // Mobile / API direct encrypted passport update
        if ($request->filled('encrypted')) {
            return app(\Ghazali\EPassPackage\Http\Controllers\PassportAuthController::class)->update($request);
        }

        // Legacy web QR session flow
        if (! $request->has('mode')) {
            $request->merge(['mode' => 'update']);
        }

        return $controller->standardVerifyDecrypted($request);
    }

    /**
     * Resolve destination URL after logging out.
     */
    protected function resolvePostLogoutRedirectUrl(Request $request, ?string $alias = null): string
    {
        if ($request->filled('redirect')) {
            $candidate = $request->input('redirect');
            if (is_string($candidate) && Str::startsWith($candidate, ['/', url('/')])) {
                return $candidate;
            }
        }

        // Redirect to '/' by default (which directs unauthenticated users to host.login)
        return url('/');
    }

    /**
     * Handles ePass session logout and guard clearance.
     */
    protected function handleEpassProtocolLogout(Request $request): mixed
    {
        $alias = EpassCallbackContext::resolveHostModelAlias($request);

        $hostGuard = method_exists($this, 'hostGuardName') ? $this->hostGuardName() : null;
        if ($hostGuard && Auth::guard($hostGuard)->check()) {
            Auth::guard($hostGuard)->logout();
        }

        $defaultGuard = config('auth.defaults.guard', 'web');
        if (Auth::guard($defaultGuard)->check()) {
            Auth::guard($defaultGuard)->logout();
        }

        if (Auth::guard('customer')->check()) {
            Auth::guard('customer')->logout();
        }

        if (EpassPackageAuth::guard()->check()) {
            EpassPackageAuth::guard()->logout();
        }

        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        $redirectUrl = $this->resolvePostLogoutRedirectUrl($request, $alias);

        Log::info('[epass:logout] user logged out successfully', [
            'ip' => $request->ip(),
            'alias' => $alias,
            'redirect' => $redirectUrl,
        ]);

        if ($request->expectsJson() || $request->isJson()) {
            return response()->json([
                'success' => true,
                'redirect' => $redirectUrl,
            ]);
        }

        return redirect($redirectUrl);
    }
}
