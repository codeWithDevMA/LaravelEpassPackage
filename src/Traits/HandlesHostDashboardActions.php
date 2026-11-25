<?php

namespace Ghazali\EPassPackage\Traits;

use Ghazali\EPassPackage\Exceptions\PassportAccountException;
use Ghazali\EPassPackage\Models\EpassUser;
use Ghazali\EPassPackage\Services\PassportAccountService;
use Ghazali\EPassPackage\Support\CallbackHostModelResolver;
use Ghazali\EPassPackage\Support\EpassAuthContext;
use Ghazali\EPassPackage\Support\EpassCallbackContext;
use Ghazali\EPassPackage\Support\EpassDashboardContext;
use Ghazali\EPassPackage\Support\EpassDashboardRouteResolver;
use Ghazali\EPassPackage\Support\EpassPackageAuth;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

/**
 * Host-application integration bridge for ePass dashboard actions.
 *
 * This trait is not pure ePass protocol logic: when used by a host
 * controller, it may read and update that host model's epass_uuid column.
 * The link duplicate check is scoped to the concrete host model class passed
 * as $hostUser. Cross-model uniqueness requires the host application to
 * coordinate its configured model tables. Host tables should enforce a
 * unique index on epass_uuid as the final race-condition safeguard.
 */
trait HandlesHostDashboardActions
{

    /**
     * Handle unauthenticated ePass callbacks (login, register handoff).
     * Returns a response/redirect if an ePass auth action was dispatched, or null.
     */
    protected function handleEpassAuthHandoff(Request $request, array $options = []): mixed
    {
        $callback = EpassCallbackContext::peek($request);
        $callbackAction = trim((string) ($callback['action'] ?? ''));
        if ($callbackAction === '') {
            $callbackAction = strtolower(trim((string) ($request->input('action') ?? $request->input('epass_callback_action') ?? '')));
        }

        if (! in_array($callbackAction, ['login', 'register'], true)) {
            return null;
        }

        $callbackEpassUuid = trim((string) ($callback['epass_uuid'] ?? $request->input('epass_callback_epass_uuid') ?? $request->input('epass_uuid') ?? ''));
        $callbackFresh = (bool) ($callback['fresh'] ?? ($request->filled('epass_callback_epass_uuid') || $request->filled('epass_uuid')));

        Log::info('[epass:dashboard-action] dispatching unauthenticated auth handoff', [
            'action' => $callbackAction,
            'epass_uuid' => $callbackEpassUuid,
            'fresh' => $callbackFresh,
        ]);

        if ($request->expectsJson() || $request->isJson()) {
            return response()->json([
                'success' => true,
                'action' => $callbackAction,
                'epass_uuid' => $callbackEpassUuid,
                'message' => ucfirst($callbackAction) . ' action dispatched successfully.',
            ]);
        }

        if ($callbackEpassUuid !== '' && $callbackFresh) {
            $loginRoute = $options['login_route'] ?? 'host.login';
            $registerRoute = $options['register_route'] ?? 'host.register';
            $targetRoute = $callbackAction === 'register' ? $registerRoute : $loginRoute;

            if (Route::has($targetRoute)) {
                return redirect()->route($targetRoute);
            }

            abort(500, "Host route [{$targetRoute}] is not configured.");
        }

        return null;
    }

    /**
     * Handle authenticated ePass callbacks (link, verify/verify_passport).
     * Returns a response/redirect if handled, or null to continue to dashboard view.
     */
    protected function handleEpassAuthenticatedAction(Request $request, mixed $authUser, array $options = []): mixed
    {
        if (! $authUser || $authUser instanceof EpassUser) {
            return null;
        }

        $callback = EpassCallbackContext::peek($request);
        $callbackAction = trim((string) ($callback['action'] ?? ''));
        if ($callbackAction === '') {
            $callbackAction = strtolower(trim((string) ($request->input('action') ?? $request->input('epass_callback_action') ?? '')));
        }

        if ($callbackAction === '') {
            return null;
        }

        $alias = $options['alias'] ?? EpassDashboardContext::resolveAlias($request);
        if ($alias === null) {
            $alias = CallbackHostModelResolver::aliasFor(get_class($authUser));
        }
        if ($alias === null || $alias === '') {
            $alias = 'user_alias';
        }

        $callbackEpassUuid = trim((string) ($callback['epass_uuid'] ?? $request->input('epass_callback_epass_uuid') ?? $request->input('epass_uuid') ?? ''));
        $callbackFresh = (bool) ($callback['fresh'] ?? ($request->filled('epass_callback_epass_uuid') || $request->filled('epass_uuid')));

        Log::info('[epass:dashboard-action] dispatching authenticated business action', [
            'action' => $callbackAction,
            'alias' => $alias,
            'epass_uuid' => $callbackEpassUuid,
            'fresh' => $callbackFresh,
        ]);

        // 1. Link passport
        if ($callbackAction === 'link') {
            $redirectUrl = EpassDashboardRouteResolver::resolveUrl($alias, [], $request);

            if ($callbackEpassUuid === '' || ! $callbackFresh) {
                Log::warning('[epass:dashboard-action] link callback rejected: stale or missing uuid', [
                    'host_user_id' => $authUser->getKey(),
                    'fresh' => $callbackFresh,
                ]);
                EpassCallbackContext::consume($request);
                return redirect($redirectUrl)
                    ->with('epass_dashboard_notice', 'ePass link verification expired. Please restart linking.')
                    ->with('epass_dashboard_notice_action', 'link');
            }

            $bindingError = $this->bindEpassUuidToHostUser($authUser, $callbackEpassUuid);
            if ($bindingError instanceof JsonResponse) {
                EpassCallbackContext::consume($request);
                $bindingData = $bindingError->getData(true);

                return redirect($redirectUrl)
                    ->with('epass_dashboard_notice', (string) ($bindingData['message'] ?? 'Unable to link this passport.'))
                    ->with('epass_dashboard_notice_action', 'link');
            }

            EpassCallbackContext::consume($request);

            if ($request->expectsJson() || $request->isJson()) {
                return response()->json([
                    'success' => true,
                    'action' => 'link',
                    'epass_uuid' => $callbackEpassUuid,
                    'message' => 'Passport linked successfully.',
                ]);
            }

            return redirect()->to($redirectUrl);
        }

        // 2. Step-up verification (verify / verify_passport)
        if (in_array($callbackAction, ['verify_passport', 'verify'], true)) {
            if ($callbackEpassUuid !== '') {
                EpassAuthContext::assertStepUpVerified($request, 'verified', $callbackEpassUuid);
            }

            if ($request->expectsJson() || $request->isJson()) {
                return response()->json([
                    'success' => true,
                    'action' => 'verify_passport',
                    'epass_uuid' => $callbackEpassUuid,
                    'message' => 'Passport verification asserted successfully.',
                ]);
            }
        }

        return null;
    }

    /**
     * Combined helper covering both unauthenticated handoffs and authenticated actions.
     */
    protected function handleEpassDashboardCallback(Request $request, mixed $authUser, array $options = []): mixed
    {
        if (! $authUser) {
            return $this->handleEpassAuthHandoff($request, $options);
        }

        return $this->handleEpassAuthenticatedAction($request, $authUser, $options);
    }

    /**
     * Complete ePass web callback for host login / register.
     * Consumes verified ePass callback context, resolves host model,
     * and provisions (if register) or finds host user.
     * Returns the host user model on success, or null if no valid callback is present.
     */
    protected function completeEpassAuthCallback(
        Request $request,
        string $expectedAction,
        ?string $callbackHostModel = null,
        ?string $guard = null,
        array $options = []
    ): ?\Illuminate\Database\Eloquent\Model {
        $verified = EpassCallbackContext::consumeVerified($request, $expectedAction);
        if (! $verified) {
            return null;
        }

        $alias = trim((string) ($callbackHostModel ?: $verified->hostModelAlias));
        $modelClass = CallbackHostModelResolver::resolve($alias);
        if (! is_string($modelClass) || $modelClass === '' || ! class_exists($modelClass)) {
            Log::warning('[epass:auth] callback missing valid host model', ['ip' => $request->ip(), 'alias' => $alias]);
            abort(422, 'Valid callback host model is required.');
        }

        $instance = new $modelClass();
        $table = $instance->getTable();
        if (! Schema::hasColumn($table, 'epass_uuid')) {
            abort(500, "Host table [{$table}] must include epass_uuid for ePass authentication.");
        }

        $hostUser = $modelClass::query()->where('epass_uuid', $verified->epassUuid)->first();

        if ($expectedAction === 'login' && ! $hostUser) {
            Log::warning('[epass:auth] login callback host user missing', [
                'model' => $modelClass,
                'epass_uuid' => $verified->epassUuid,
            ]);
            abort(401, 'This ePass is not linked to a host account. Register first.');
        }

        if ($expectedAction === 'register' && ! $hostUser) {
            if (isset($options['provision']) && is_callable($options['provision'])) {
                $hostUser = call_user_func($options['provision'], $modelClass, $verified->epassUuid);
            } else {
                $hostUser = new $modelClass();
                $hostUser->forceFill(['epass_uuid' => $verified->epassUuid])->save();
            }
            Log::info('[epass:auth] register callback host user created', [
                'model' => $modelClass,
                'host_user_id' => $hostUser->getKey(),
                'epass_uuid' => $verified->epassUuid,
            ]);
        }

        return $hostUser;
    }

    /**
     * Cleanly tear down ePass contexts, session, and guards upon account deletion or logout.
     * Strictly decoupled from host user models and host tables.
     */
    protected function teardownEpassAccountSession(
        Request $request,
        mixed $user = null,
        array $options = []
    ): void {
        // 1. Clean up package-owned EpassUser if requested and epass_uuid is present
        $deleteEpassUser = (bool) ($options['delete_epass_user'] ?? true);
        $epassUuid = (string) ($options['epass_uuid'] ?? ($user?->epass_uuid ?? ''));

        if ($deleteEpassUser && $epassUuid !== '') {
            $epassUser = EpassUser::where('uuid', $epassUuid)->first();
            if ($epassUser) {
                if (method_exists($epassUser, 'decrementOrDelete')) {
                    $epassUser->decrementOrDelete();
                } else {
                    $epassUser->delete();
                }
            }
        } elseif ($user instanceof EpassUser && $deleteEpassUser) {
            if (method_exists($user, 'decrementOrDelete')) {
                $user->decrementOrDelete();
            } else {
                $user->delete();
            }
            Log::info('[epass:teardown] deleted authenticated EpassUser record');
        }

        // 2. Clear ePass session contexts
        EpassAuthContext::clearStepUp($request, 'verified');
        EpassCallbackContext::clear($request);
        // 3. Log out guards (parameterized host guard + epass guard)
        $guard = $options['guard']
            ?? (is_object($user) && method_exists($user, 'hostGuardName') ? $user::hostGuardName() : null)
            ?? (is_object($user) && method_exists($user, 'getGuardName') ? $user->getGuardName() : null)
            ?? (method_exists($this, 'hostGuardName') ? $this->hostGuardName() : null)
            ?? config('auth.defaults.guard', 'web');

        $activeGuard = Auth::guard($guard);
        if ($activeGuard->check() && method_exists($activeGuard, 'logout')) {
            $activeGuard->logout();
        }

        if (class_exists(EpassPackageAuth::class) && EpassPackageAuth::guard()->check()) {
            EpassPackageAuth::guard()->logout();
        }

        // 4. Invalidate HTTP session
        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }
    }

    /**
     * Handle ePass logout with parameterized host guard and redirect.
     */
    protected function handleEpassLogout(
        Request $request,
        array $options = []
    ): mixed {
        if (! isset($options['guard'])) {
            $alias = $options['alias'] ?? EpassDashboardContext::resolveAlias($request) ?? null;
            if ($alias) {
                $modelClass = CallbackHostModelResolver::resolve($alias);
                if (is_string($modelClass) && class_exists($modelClass) && method_exists($modelClass, 'hostGuardName')) {
                    $options['guard'] = $modelClass::hostGuardName();
                }
            }
        }

        $this->teardownEpassAccountSession($request, null, array_merge($options, [
            'delete_epass_user' => false,
        ]));

        $redirect = $options['redirect'] ?? $options['redirect_route'] ?? null;
        if ($redirect && Route::has($redirect)) {
            $redirectUrl = route($redirect);
        } elseif (is_string($redirect) && $redirect !== '') {
            $redirectUrl = $redirect;
        } else {
            $alias = $options['alias'] ?? EpassDashboardContext::resolveAlias($request) ?? 'user_alias';
            $redirectUrl = EpassDashboardRouteResolver::resolveUrl($alias, [], $request);
        }

        if ($request->expectsJson() || $request->isJson()) {
            return response()->json([
                'success' => true,
                'redirect' => $redirectUrl,
            ]);
        }

        return redirect($redirectUrl);
    }

    /**
     * Issue an API / Bearer token for mobile app authentication.
     * Host controllers (e.g. DeveloperDashboardController) can override this method
     * to issue tokens via Laravel Sanctum, Passport, JWT, or custom tables.
     */
    public function issueHostApiToken(\Illuminate\Database\Eloquent\Model $hostUser, string $action): ?string
    {
        return null;
    }

    /**
     * Resolve and verify an ePass passport against the epass_users table.
     *
     * In 'verify' mode: reads $hostUser->epass_uuid and asserts the scanned passport matches.
     * In 'link'   mode: calls linkPassport(), then (if $hostUser given) checks for duplicate host
     *                   owners and saves epass_uuid to the host row through this host bridge.
     *                   The duplicate query covers only the concrete host model class. A unique
     *                   database index on each host table remains required for race protection.
     *
     * @param  array                                  $decryptedPassport
     * @param  string                                 $action      'verify' | 'link'
     * @param  \Illuminate\Database\Eloquent\Model|null $hostUser  The authenticated host user model.
     * @param  PassportAccountService|null            $service
     * @return EpassUser|JsonResponse
     */
    protected function resolveEpassPassport(
        array $decryptedPassport,
        string $action = 'verify',
        ?\Illuminate\Database\Eloquent\Model $hostUser = null,
        ?PassportAccountService $service = null,
    ): EpassUser|JsonResponse {
        $service = $service ?? app(PassportAccountService::class);

        try {
            if ($action === 'verify') {
                $expectedEpassUuid = (string) ($hostUser?->epass_uuid ?? '');

                if (empty($expectedEpassUuid)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Link your passport before performing this action.',
                        'code'    => 'PASSPORT_NOT_LINKED',
                    ], 409);
                }

                // Verify passport credentials against epass_users
                $epassUser = $service->login($decryptedPassport, $hostUser ? get_class($hostUser) : null);

                // Assert identity match
                if (! hash_equals($expectedEpassUuid, (string) $epassUser->getKey())) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Passport verification failed. The scanned passport does not match this account.',
                        'code'    => 'PASSPORT_MISMATCH',
                    ], 403);
                }

                return $epassUser;
            }

            if ($action === 'link') {
                // Verify ownership availability & create/update EpassUser record
                $epassUser = $service->linkPassport($hostUser, $decryptedPassport);
                $epassUuid = (string) ($epassUser->uuid ?? $epassUser->getKey());

                if ($hostUser !== null) {
                    $bindingError = $this->bindEpassUuidToHostUser($hostUser, $epassUuid);
                    if ($bindingError instanceof JsonResponse) {
                        return $bindingError;
                    }
                }

                return $epassUser;
            }

            throw new \InvalidArgumentException("Unsupported action [{$action}]. Use 'verify' or 'link'.");
        } catch (PassportAccountException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'code'    => $e->errorCode,
            ], $e->status);
        }
    }

    /**
     * Finalize a host-to-ePass binding after passport verification.
     *
     * This host-integration helper saves the host reference and increments the
     * ePass reference count in one transaction. It deliberately runs only for
     * a newly linked host row, so a replay for the same host does not inflate
     * linked_accounts_count.
     */
    protected function bindEpassUuidToHostUser(\Illuminate\Database\Eloquent\Model $hostUser, string $epassUuid): ?JsonResponse
    {
        $epassUuid = trim($epassUuid);
        if ($epassUuid === '') {
            return response()->json([
                'success' => false,
                'message' => 'Verified passport identity is missing.',
                'code'    => 'PASSPORT_IDENTITY_MISSING',
            ], 422);
        }

        $hostClass = get_class($hostUser);
        $hostTable = $hostUser->getTable();
        if (! Schema::hasColumn($hostTable, 'epass_uuid')) {
            abort(500, "Host table [{$hostTable}] must include an epass_uuid column for ePass linking.");
        }

        $duplicate = $hostClass::query()
            ->whereKeyNot($hostUser->getKey())
            ->where('epass_uuid', $epassUuid)
            ->exists();
        if ($duplicate) {
            Log::warning('[epass:host-link] duplicate host owner blocked', [
                'host_user_id' => $hostUser->getKey(),
                'host_class'   => $hostClass,
                'epass_uuid'   => $epassUuid,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'This passport is already linked to another account.',
                'code'    => 'PASSPORT_ALREADY_LINKED',
            ], 409);
        }

        $newlyLinked = false;
        $linkedAccountsCount = 0;
        try {
            DB::transaction(function () use ($hostUser, $epassUuid, &$newlyLinked, &$linkedAccountsCount): void {
                $epassUser = EpassUser::query()
                    ->whereKey($epassUuid)
                    ->lockForUpdate()
                    ->first();
                if (! $epassUser instanceof EpassUser) {
                    throw new \RuntimeException('Verified ePass identity no longer exists.');
                }

                if ((string) ($hostUser->epass_uuid ?? '') !== $epassUuid) {
                    $hostUser->forceFill(['epass_uuid' => $epassUuid])->save();
                    $newlyLinked = true;
                }

                $linkedAccountsCount = $this->countConfiguredHostLinks($epassUuid);
                if (Schema::hasColumn($epassUser->getTable(), 'linked_accounts_count')) {
                    $epassUser->forceFill([
                        'linked_accounts_count' => $linkedAccountsCount,
                    ])->save();
                }
            });
        } catch (\Illuminate\Database\UniqueConstraintViolationException) {
            Log::warning('[epass:host-link] duplicate host owner blocked by database constraint', [
                'host_user_id' => $hostUser->getKey(),
                'host_class'   => $hostClass,
                'epass_uuid'   => $epassUuid,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'This passport is already linked to another account.',
                'code'    => 'PASSPORT_ALREADY_LINKED',
            ], 409);
        } catch (\Throwable $e) {
            Log::error('[epass:host-link] failed to finalize host binding', [
                'host_user_id' => $hostUser->getKey(),
                'host_class'   => $hostClass,
                'epass_uuid'   => $epassUuid,
                'exception'    => $e::class,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unable to complete passport linking.',
                'code'    => 'PASSPORT_LINK_FAILED',
            ], 500);
        }

        Log::info('[epass:host-link] host binding finalized', [
            'host_user_id' => $hostUser->getKey(),
            'host_class'   => $hostClass,
            'epass_uuid'   => $epassUuid,
            'newly_linked'  => $newlyLinked,
            'linked_accounts_count' => $linkedAccountsCount,
        ]);

        return null;
    }

    /**
     * Count bindings across every explicitly configured host model.
     */
    private function countConfiguredHostLinks(string $epassUuid): int
    {
        $configuredModels = array_values(array_unique(CallbackHostModelResolver::map()));
        if ($configuredModels === []) {
            throw new \RuntimeException('No callback_host_models are configured for host link reconciliation.');
        }

        $count = 0;
        foreach ($configuredModels as $hostModelClass) {
            if (! class_exists($hostModelClass) || ! is_a($hostModelClass, \Illuminate\Database\Eloquent\Model::class, true)) {
                throw new \RuntimeException('Configured callback host model is invalid.');
            }

            $hostModel = new $hostModelClass();
            if (! Schema::hasColumn($hostModel->getTable(), 'epass_uuid')) {
                throw new \RuntimeException("Configured host table [{$hostModel->getTable()}] must include an epass_uuid column.");
            }

            $count += $hostModelClass::query()
                ->where('epass_uuid', $epassUuid)
                ->count();
        }

        return $count;
    }
}

