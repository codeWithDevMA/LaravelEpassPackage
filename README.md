# ePass Laravel Package

A decoupled, host-model-agnostic Laravel package providing ePass NFC passport biometric authentication, zero-knowledge attestation, step-up verification, and anti-replay session management.

---

## Architectural Philosophy: Strict Host Model Decoupling

The ePass package is built on a strict **Zero-Host-Coupling** principle:
1. **Zero Touch on Host Tables**: The package **never** modifies, locks, or queries your host tables (`users`, `customers`, `airport_travelers`, etc.) directly. All biometric/passport records live exclusively in the package-owned `epass_users` table.
2. **Single Integration Column (`epass_uuid`)**: The package verifies the physical passport identity, assigns an immutable identity UUID (`epass_uuid`), and hands it to your host application.
3. **Multi-Dashboard & Multi-Model Support**: Host applications can run multiple independent dashboards (e.g., **Developer**, **Customer**, **Airport**) backed by separate models, guards, and routes mapped by aliases in `config/epass.php`.
4. **Safe Uninstalling**: Because host tables remain untouched, running `php artisan epass:uninstall` cleanly drops `epass_users`, removes published views/assets/migrations (and optionally configs with `--delete-config`), leaving zero orphaned constraints or broken host schemas.

---

## Package Architecture Overview

```
                      ┌──────────────────────────────────────────────┐
                      │             ePass Mobile App                 │
                      │  (NFC MRZ Scan + Biometrics + ECDH / ML-KEM) │
                      └──────────────────────┬───────────────────────┘
                                             │
                       QR Scan / Direct Link │
                                             ▼
                      ┌──────────────────────────────────────────────┐
                      │            ePass Laravel Package             │
                      │  • HandlesEpassProtocol (Attestation Gateway)│
                      │  • HandlesHostDashboardActions (Callbacks)   │
                      │  • EpassCallbackContext (Single-Use Proofs)  │
                      │  • EpassAuthContext (Step-Up Verification)   │
                      └──────────────────────┬───────────────────────┘
                                             │
            Shared Protocol Bridge (Parent / Dashboard Controller)
                                             ▼
                      ┌──────────────────────────────────────────────┐
                      │           Your Host Application              │
                      │  • Multi-Dashboard Controllers               │
                      │    (Developer, Customer, Airport, etc.)      │
                      │  • App\Models\User / Customer / AirportTraveler
                      │  • Host guards ('web', 'customer', etc.)     │
                      └──────────────────────────────────────────────┘
```

---

## Installation & Configuration

### 1. Require the Package
```bash
composer require ghazali/epass-package
```

### 2. Publish Config, Migrations & Generate Keys
Run the all-in-one installer command:
```bash
php artisan epass:install
```
Or publish manually:
```bash
php artisan vendor:publish --tag=epass-config
php artisan vendor:publish --tag=epass-package-migrations
php artisan migrate
php artisan epass:keys
```

### 3. Generate `.env` Cryptographic Keys & Field Encryption Keys
The package requires Ed25519 attestation signing keys and 256-bit AES-GCM/HKDF field encryption keys.

You can run the included helper script to generate `EPASS_FIELD_ENCRYPTION_KEY` and `EPASS_FIELD_ENCRYPTION_SALT` (and auto-append them to your `.env`):
```bash
php generate-field-keys.php
```
Or generate all required keys together with PHP:
```bash
php -r "
\$pair = sodium_crypto_sign_keypair();
echo 'EPASS_ATTESTATION_SIGNING_SECRET_B64=' . base64_encode(sodium_crypto_sign_secretkey(\$pair)) . PHP_EOL;
echo 'EPASS_ATTESTATION_SIGNING_PUBLIC_B64=' . base64_encode(sodium_crypto_sign_publickey(\$pair)) . PHP_EOL;
echo 'EPASS_FIELD_ENCRYPTION_KEY=' . base64_encode(random_bytes(32)) . PHP_EOL;
echo 'EPASS_FIELD_ENCRYPTION_SALT=' . base64_encode(random_bytes(32)) . PHP_EOL;
"
```

Add the generated values to your host application's `.env`:
```env
EPASS_ATTESTATION_MODE=standard
EPASS_ATTESTATION_SIGNING_SECRET_B64=<generated-64-byte-base64-secret>
EPASS_ATTESTATION_SIGNING_PUBLIC_B64=<generated-32-byte-base64-public>
EPASS_ATTESTATION_PACKAGE_NAME=io.github.yourorg.epassauthenticator
EPASS_FIELD_ENCRYPTION_KEY=<generated-32-byte-base64-key>
EPASS_FIELD_ENCRYPTION_SALT=<generated-32-byte-base64-salt>
```

> **Note:** Make sure `EPASS_ATTESTATION_SIGNING_PUBLIC_B64` matches the public key embedded in your compiled Flutter/mobile ePass client if using Verified Mode.

### 4. Add `epass_uuid` to Your Host Tables
Any host table that links with ePass only needs a nullable `epass_uuid` string column (add `->unique()` if enforcing one account per passport at the DB level):
```php
Schema::table('users', function (Blueprint $table) {
    $table->string('epass_uuid', 64)->nullable()->unique()->after('id');
});
```

### 5. Configure Aliases (`config/epass.php`)
Use `config/epass.php` to map short aliases to your Eloquent models and their target dashboard named routes. The alias is carried through the entire callback flow so ePass knows which model and dashboard each request belongs to without hardcoding table names:

```php
return [
    'route_prefix' => trim((string) env('EPASS_ROUTE_PREFIX', 'epass'), '/'),
    'route_middleware' => ['api'],
    'auth_guard' => env('EPASS_AUTH_GUARD', 'epass'),

    /*
    |--------------------------------------------------------------------------
    | Callback Host Models (Aliases -> Eloquent Models)
    |--------------------------------------------------------------------------
    */
    'callback_host_models' => [
        'user_alias' => \App\Models\User::class,
        'customer'   => \App\Models\Customer::class,
        'airport'    => \App\Models\AirportTraveler::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Dashboard Route Map (Aliases -> Named Routes)
    |--------------------------------------------------------------------------
    */
    'dashboard_routes' => [
        'user_alias' => 'developer.dashboard', // -> DeveloperDashboardController@index
        'customer'   => 'customer.dashboard',  // -> CustomerDashboardController@index
        'airport'    => 'airport.mode',        // -> AirportDashboardController@index
    ],

    'field_encryption_key'  => env('EPASS_FIELD_ENCRYPTION_KEY', null),
    'field_encryption_salt' => env('EPASS_FIELD_ENCRYPTION_SALT', null),
];
```

---

## Multi-Dashboard Architecture & Shared Callback Protocol

ePass natively supports **multiple dashboards** (e.g., a **Developer** portal, a **Customer** portal, and an **Airport** clearance mode) within the same Laravel application.

### How the Shared Protocol Bridge Works
Your host dashboard/callback controller (either a shared parent controller such as `ParentCallbackController` or individual dashboard controllers) acts as the **shared protocol bridge** between the ePass package and your host application by composing two traits:

1. **`HandlesEpassProtocol`** — Absorbs all low-level technical ePass handshake intents (`welcome`, `start`, `handoff`, `challenge`, `exchange`, `init`, `fetch_qr2`, `receive_phone_key`, `check_status`, `verify_decrypted`, `verify`, `logout`, etc.).
2. **`HandlesHostDashboardActions`** — Bridges verified ePass callbacks (`login`, `register`, `link`, `verify` / `verify_passport`) to your host Eloquent models, guards, and sessions.

You can route each alias in `config/epass.php` to:
- **Separate controllers per dashboard** (e.g., `DeveloperDashboardController`, `CustomerDashboardController`, `AirportDashboardController`), or
- **A shared parent/gateway controller** that branches internally using `EpassDashboardContext::peekCallbackHostModel()` to render the appropriate view.

---

## Traits Reference

### 1. `HandlesHostDashboardActions`
Import into your **Dashboard Controllers**, **Auth Controllers**, and **Profile Controllers**:
```php
use Ghazali\EPassPackage\Traits\HandlesHostDashboardActions;
```

| Method | Where to Use | Purpose | Host-Agnostic Mechanism |
| :--- | :--- | :--- | :--- |
| `handleEpassAuthHandoff($request, $options)` | Dashboard (Guest) | Bounces returning scan users (`login`/`register`) to host login/register. | Explicit route parameters: `'login_route'`, `'register_route'`. |
| `completeEpassAuthCallback($request, $action, $alias, $guard, $options)` | AuthController (`showLoginForm`, `showRegistrationForm`) | Consumes verified proof, finds/provisions host model, logs into host guard, redirects to dashboard. | Uses `CallbackHostModelResolver` + `epass_uuid` lookup + optional `'provision'` callable. |
| `handleEpassAuthenticatedAction($request, $authUser, $options)` | Dashboard (Auth) | Handles in-session `link` (with duplicate check) and `verify` / `verify_passport` (step-up). | Resolves model dynamically via `get_class($authUser)`. |
| `teardownEpassAccountSession($request, $user, $options)` | ProfileController (`destroyAccount`), Logout | Destroys package-owned `EpassUser`, clears step-up/callback state, logs out guards, invalidates session. | Only deletes package `EpassUser` and purges sessions; touches ZERO host tables. |
| `handleEpassLogout($request, $options)` | Dashboard / AuthController | Performs clean session and guard logout and redirects. | Parameterized `$options['guard']` and `$options['redirect_route']`. |

### 2. `HandlesEpassProtocol`
Import into your **Dashboard Gateway / Parent Callback Controller**:
```php
use Ghazali\EPassPackage\Traits\HandlesEpassProtocol;
```
- `isEpassProtocolIntent(Request $request): bool` — Detects technical ePass protocol requests (`intent` or encrypted callback payloads).
- `handleEpassProtocolIntent(Request $request): mixed` — Dispatches all technical handshake protocol steps through a single gateway line.

---

## Blade Integration (`<x-epass::qr-panel />` & `<x-epass::scripts />`)

ePass provides ready-to-use Blade components and a frontend SDK (`window.EPassAuth`) that automatically binds to your configured alias and gateway endpoints.

### 1. Include Scripts at the Bottom of Your Blade View
Pass the alias matching `config/epass.php` (`user_alias`, `customer`, `airport`, etc.):
```blade
<x-epass::scripts :callback-host-model="$epassCallbackHostModel ?? 'customer'" />
@include('hybrid-crypto::browser-adapter')
```

### 2. Render the QR Panel Component
Use `<x-epass::qr-panel />` anywhere in your Blade view (supports `theme="light"` or `"dark"`, and `mode="card"` or `"inline"`):
```blade
@if(!$passportLinked)
    <x-epass::qr-panel
        theme="light"
        prefix="epass-link"
        title="Link ePass"
        description="Link this signed-in account to a passport through the secure ePass QR flow."
        button-text="Start Linking"
        :conflict-message="$linkConflictMessage ?? null"
    />
@endif
```

### 3. Mount the Flow in JavaScript
Match the `prefix` option in `window.EPassAuth.mount(...)` with the `prefix` attribute on `<x-epass::qr-panel />`:

#### Example A: Start Linking Flow (`flow: 'link'`)
```blade
<script>
    (function initEpassLinkFlow() {
        const linked = @json($passportLinked);
        if (linked) return;

        window.EPassAuth.mount({
            prefix: 'epass-link',
            flow: 'link',
            initialStatus: 'Preparing secure session...',
        });
    })();
</script>
```

#### Example B: Step-Up Verification Before a Critical Action (`flow: 'verify_passport'`)
```blade
<x-epass::qr-panel
    theme="dark"
    prefix="payment-verify"
    mode="inline"
    title="Confirm Payment with ePass"
    button-text="Verify Passport to Pay"
/>

<script>
    let paymentEpass = null;

    function startPaymentVerification(pendingPayload) {
        if (!paymentEpass) {
            paymentEpass = window.EPassAuth.mount({
                prefix: 'payment-verify',
                flow: 'verify_passport',
                initialStatus: 'Preparing secure passport verification...',
                getFinalPayload() {
                    return pendingPayload;
                },
                async onSuccess(response) {
                    // Executed immediately after ePass verification succeeds:
                    await openOrSubmitPayment(pendingPayload);
                }
            });
        }
    }
</script>
```

---

## Intent Semantics, Workflow Placement & Security Best Practices

### 1. Verifying Without Creating an Account (`start_linking` / `link`) & Multiple Accounts
- **Verify without creating an account:** If you want to verify a user's passport without creating a new account record, use **Start Linking** (`flow: 'link'`).
- **Allowing or disallowing multiple accounts per passport:** By default, `HandlesHostDashboardActions` prevents two host accounts in the same table from claiming the same `epass_uuid`. If your use case requires allowing multiple accounts per passport (such as Airport Mode or shared family/operator flows), customize the trait (for example, overriding or creating a dedicated trait without duplicate-owner rejection, and omitting the unique DB constraint on `epass_uuid`).
- **Important Limitation with Multiple Accounts:** If you allow multiple accounts per passport, the **`verify` (`verify_passport`) intent will NOT work**, because `verify` is tied to a single unique account/passport binding (`epass_uuid` -> 1 account) and cannot disambiguate which account is being verified.

### 2. Always Place `start_linking` and `verify` as the Final Step
- Both **`start_linking` (`link`)** and **`verify` (`verify_passport`)** should **always be the last step** in your user workflow.
- **Example (Checkout / Payment after a Form):** Have the user fill out and validate the entire form first, then trigger `start_linking` or `verify` as the final step. Once ePass verification succeeds, automatically open or execute the payment in `onSuccess` so the ePass attestation directly accompanies the payment.

### 3. The `verify` Intent Creates Zero Records
- Unlike `register` or `link`, the **`verify` (`verify_passport`)** intent **does not create or modify any database record**.
- Use `verify` exclusively for step-up re-authentication on an already-linked account—such as **changing a password**, **approving a transfer**, or **confirming sensitive account actions**.

### 4. Authentication Recommendation & Multi-Layer Input Validation
- **Recommended Flow:** Always prefer using ePass **`login`** / **`register`** intents first. Doing so issues an authenticated session and auth token that power subsequent `verify` and `start_linking` intents.
- **Standalone Security (Without Prior `login`/`register`):** Even if you invoke `start_linking` or `verify` without a prior ePass login/register token, the flow remains cryptographically secured. Every request undergoes **multiple simultaneous validations**—including session/challenge nonces, cryptographic hashes, deployment HKDF salting, ECDH/ML-KEM encrypted payload binding, Ed25519 attestation checks, and single-use proof consumption. **If any single validation check fails, the entire process is denied**, ensuring requests cannot be guessed, forged, or replayed.

---

## Integration Guide: Controller Examples

### A. Multi-Dashboard Controller (`DeveloperDashboardController.php` / `CustomerDashboardController.php`)
```php
namespace App\Http\Controllers;

use Ghazali\EPassPackage\Support\EpassDashboardContext;
use Ghazali\EPassPackage\Traits\{HandlesEpassProtocol, HandlesHostDashboardActions};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CustomerDashboardController extends Controller
{
    use HandlesEpassProtocol;
    use HandlesHostDashboardActions;

    public function index(Request $request)
    {
        // 1. Absorb low-level ePass handshake protocol (welcome, challenge, init, verify, logout)
        if ($this->isEpassProtocolIntent($request)) {
            return $this->handleEpassProtocolIntent($request);
        }

        $authUser = Auth::guard('customer')->user();

        // ── CASE 1: UN-AUTHENTICATED VISITOR ──
        if (! $authUser) {
            if ($response = $this->handleEpassAuthHandoff($request, [
                'login_route'    => 'customer.login',
                'register_route' => 'customer.register',
            ])) {
                return $response;
            }

            return redirect()->route('customer.login');
        }

        // ── CASE 2: AUTHENTICATED USER ──
        if ($response = $this->handleEpassAuthenticatedAction($request, $authUser)) {
            return $response;
        }

        // 2B. Render host dashboard view
        $viewData = EpassDashboardContext::orchestrate($request, 'customer.dashboard', $authUser);
        return view('customer.dashboard', $viewData);
    }
}
```

### B. Host Auth Controller (`AuthController.php`)
```php
namespace App\Http\Controllers\hostController;

use App\Http\Controllers\Controller;
use Ghazali\EPassPackage\Traits\HandlesHostDashboardActions;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    use HandlesHostDashboardActions;

    public function showLoginForm(Request $request)
    {
        if ($redirect = $this->completeEpassAuthCallback($request, 'login')) {
            return $redirect;
        }

        return view('host.auth.login');
    }

    public function showRegistrationForm(Request $request)
    {
        if ($redirect = $this->completeEpassAuthCallback($request, 'register', null, null, [
            'provision' => function(string $modelClass, string $epassUuid) {
                $user = new $modelClass();
                $user->forceFill([
                    'epass_uuid' => $epassUuid,
                    'status'     => 'active',
                ]);
                $user->save();
                return $user;
            },
        ])) {
            return $redirect;
        }

        return view('host.auth.register');
    }
}
```

### C. Profile Controller: Account Deletion (`ProfileController.php`)
```php
namespace App\Http\Controllers\hostController;

use App\Http\Controllers\Controller;
use Ghazali\EPassPackage\Traits\HandlesHostDashboardActions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ProfileController extends Controller
{
    use HandlesHostDashboardActions;

    public function destroyAccount(Request $request): JsonResponse
    {
        $authUser = $request->user() ?? Auth::user();
        if (! $authUser) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated.'], 401);
        }

        // 1. Package responsibility: Clean up ePass session, guards, and package-owned EpassUser
        $this->teardownEpassAccountSession($request, $authUser);

        // 2. Host responsibility: Delete host application record
        if ($authUser instanceof \Illuminate\Database\Eloquent\Model && $authUser->exists) {
            $authUser->delete();
        }

        return response()->json([
            'success'  => true,
            'message'  => 'Your account was deleted successfully.',
            'redirect' => route('host.login'),
        ]);
    }
}
```

---

## Uninstalling Safely

```bash
php artisan epass:uninstall
# Or also remove published config files:
php artisan epass:uninstall --delete-config
```
This drops the package-owned `epass_users` table, cleans up published views/assets/migrations, and leaves all host application tables (`users`, `customers`, etc.) completely intact.

---

## License
MIT License.
