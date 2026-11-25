# ePass Laravel Package

A decoupled, host-model-agnostic Laravel package providing ePass NFC passport biometric authentication, zero-knowledge attestation, step-up verification, and anti-replay session management.

---

## Architectural Philosophy: Strict Host Model Decoupling

The ePass package is built on a strict **Zero-Host-Coupling** principle:
1. **The package knows ZERO details about your host tables or columns**: It never queries `users`, `customers`, or any custom application table directly.
2. **The only integration point is `epass_uuid`**: The package verifies the physical passport identity, assigns an immutable identity UUID (`epass_uuid`), and hands it to your host application.
3. **Multi-Model & Multi-Table Support**: Host applications can configure arbitrary models (`User`, `Customer`, `Admin`, `Merchant`) mapped to aliases.
4. **Parameterized Traits**: All traits (`HandlesHostDashboardActions`, `HandlesEpassProtocol`) accept customizable options, guards, routes, and callbacks without hardcoding any host business logic.

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
                  Parameterized Handoff / Verified Context
                                             ▼
                      ┌──────────────────────────────────────────────┐
                      │           Your Host Application              │
                      │  • App\Models\User / Customer / Admin        │
                      │  • host.login / host.register routes         │
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
You can run the all-in-one installer command:
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

### 3. Generate `.env` Cryptographic Keys
The package requires Ed25519 attestation signing keys and 256-bit field encryption keys. Generate them with PHP and add the output to your host application's `.env`:

```bash
php -r "
\$pair = sodium_crypto_sign_keypair();
echo 'EPASS_ATTESTATION_SIGNING_SECRET_B64=' . base64_encode(sodium_crypto_sign_secretkey(\$pair)) . PHP_EOL;
echo 'EPASS_ATTESTATION_SIGNING_PUBLIC_B64=' . base64_encode(sodium_crypto_sign_publickey(\$pair)) . PHP_EOL;
echo 'EPASS_FIELD_ENCRYPTION_KEY=' . base64_encode(random_bytes(32)) . PHP_EOL;
echo 'EPASS_FIELD_ENCRYPTION_SALT=' . base64_encode(random_bytes(32)) . PHP_EOL;
"
```

Add the generated values to your `.env`:
```env
EPASS_ATTESTATION_MODE=standard
EPASS_ATTESTATION_SIGNING_SECRET_B64=<generated-64-byte-base64-secret>
EPASS_ATTESTATION_SIGNING_PUBLIC_B64=<generated-32-byte-base64-public>
EPASS_ATTESTATION_PACKAGE_NAME=io.github.yourorg.epassauthenticator
EPASS_FIELD_ENCRYPTION_KEY=<generated-32-byte-base64-key>
EPASS_FIELD_ENCRYPTION_SALT=<generated-32-byte-base64-salt>
```

> **Note:** Make sure the public key (`EPASS_ATTESTATION_SIGNING_PUBLIC_B64`) matches the public key embedded in your compiled Flutter/mobile ePass client if using Verified Mode.

### 4. Add `epass_uuid` to Your Host Tables
Any host table that links with ePass must include an indexed, nullable `epass_uuid` string:
```php
Schema::table('users', function (Blueprint $table) {
    $table->string('epass_uuid', 64)->nullable()->unique()->after('id');
});
```

### 5. Configure Aliases (`config/epass.php`)
Register your host models and dashboard routes by alias:
```php
return [
    'callback_host_models' => [
        'user_alias' => \App\Models\User::class,
        'customer'   => \App\Models\Customer::class,
        'admin'      => \App\Models\Admin::class,
    ],

    'dashboard_routes' => [
        'user_alias' => 'developer.dashboard',
        'customer'   => 'customer.dashboard',
        'admin'      => 'admin.dashboard',
    ],

    'auth_guard' => 'epass',
];
```

---

## Traits Reference

The package provides two lightweight, parameterizable traits for your controllers:

### 1. `HandlesHostDashboardActions`
Import into your **Dashboard Controllers**, **Auth Controllers**, and **Profile Controllers**.

```php
use Ghazali\EPassPackage\Traits\HandlesHostDashboardActions;
```

#### Available Methods:

| Method | Where to Use | Purpose | Host-Agnostic Mechanism |
| :--- | :--- | :--- | :--- |
| `handleEpassAuthHandoff($request, $options)` | Dashboard (Guest) | Bounces returning scan users (`login`/`register`) to host login/register. | Explicit route parameters: `'login_route'`, `'register_route'`. |
| `completeEpassAuthCallback($request, $action, $alias, $guard, $options)` | AuthController (`showLoginForm`, `showRegistrationForm`) | Consumes verified proof, finds/provisions host model, logs into host guard, regenerates session, redirects to dashboard. | Uses `CallbackHostModelResolver` + dynamic `epass_uuid` check + optional `'provision'` callable. |
| `handleEpassAuthenticatedAction($request, $authUser, $options)` | Dashboard (Auth) | Handles in-session `link` (with duplicate check) and `verify` (step-up). | Resolves model dynamically via `get_class($authUser)` and schema reflection. |
| `teardownEpassAccountSession($request, $user, $options)` | ProfileController (`destroyAccount`), Logout | Destroys package-owned `EpassUser`, clears step-up/callback state, logs out guards, invalidates session. | Only deletes package `EpassUser` and purges sessions; touches ZERO host tables. |
| `handleEpassLogout($request, $options)` | Dashboard / AuthController | Performs clean session and guard logout and redirects. | Parameterized `$options['guard']` and `$options['redirect_route']`. |

---

### 2. `HandlesEpassProtocol`
Import into your **Dashboard Gateway Controller** (e.g. `DeveloperDashboardController`).

```php
use Ghazali\EPassPackage\Traits\HandlesEpassProtocol;
```

#### Available Methods:
- `isEpassProtocolIntent(Request $request): bool`: Detects technical ePass requests (`intent=welcome|start|handoff|public_key|qr_status|logout`).
- `handleEpassProtocolIntent(Request $request): mixed`: Absorbs all technical handshake protocol requests into a single line.

---

## Integration Guide: Controller Examples

### A. Dashboard Controller (`DeveloperDashboardController.php`)
A complete, multi-role dashboard controller in **~70 lines of code**:

```php
namespace App\Http\Controllers;

use Ghazali\EPassPackage\Support\EpassDashboardContext;
use Ghazali\EPassPackage\Traits\{HandlesEpassProtocol, HandlesHostDashboardActions};
use Illuminate\Http\Request;

class DeveloperDashboardController extends Controller
{
    use HandlesEpassProtocol;
    use HandlesHostDashboardActions;

    public function index(Request $request)
    {
        // 1. Absorb low-level ePass handshake protocol (welcome, QR status, logout)
        if ($this->isEpassProtocolIntent($request)) {
            return $this->handleEpassProtocolIntent($request);
        }

        $authUser = $request->user();

        // ── CASE 1: UN-AUTHENTICATED VISITOR ──
        if (! $authUser) {
            // 1A. User returning from ePass app scan: redirect to host login or register
            if ($response = $this->handleEpassAuthHandoff($request, [
                'login_route'    => 'host.login',
                'register_route' => 'host.register',
            ])) {
                return $response;
            }

            // 1B. Direct guest visit: redirect to login
            return redirect()->route('host.login');
        }

        // ── CASE 2: AUTHENTICATED USER ──
        // 2A. Process in-session ePass actions (linking a passport, step-up verification)
        if ($response = $this->handleEpassAuthenticatedAction($request, $authUser)) {
            return $response;
        }

        // 2B. Render host dashboard view
        $viewData = EpassDashboardContext::orchestrate($request, 'developer.dashboard', $authUser);
        return view('developer.dashboard', $viewData);
    }
}
```

---

### B. Host Auth Controller (`AuthController.php`)
Clean views and instant ePass callback completion:

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
        // Automatically completes login callback, logs into host guard, redirects to dashboard
        if ($redirect = $this->completeEpassAuthCallback($request, 'login')) {
            return $redirect;
        }

        return view('host.auth.login');
    }

    public function showRegistrationForm(Request $request)
    {
        // Automatically completes registration callback, provisions user, redirects to dashboard
        if ($redirect = $this->completeEpassAuthCallback($request, 'register', null, null, [
            // Optional hook for custom host fields on register:
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

---

### C. Profile Controller: Account Deletion (`ProfileController.php`)
Safe, decoupled account deletion with zero orphaned passports:

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

## Security Guarantees

1. **Anti-Replay Attack Protection**: Every web callback uses single-use verified session proofs via `EpassCallbackContext::consumeVerified()`. Replaying a URL immediately returns `null` or `419`.
2. **Duplicate Owner Blocking**: `handleEpassAuthenticatedAction` checks if another host user in the same table already claimed the physical passport (`whereKeyNot(id)->where('epass_uuid')`) before allowing a link.
3. **Step-Up Verification TTL**: Sensitive actions (e.g. changing passwords, transferring funds) require fresh verification asserted via `EpassAuthContext::assertStepUpVerified()`, which automatically expires after a configurable TTL (e.g. 600s).
4. **No Orphaned Passports**: Account deletion invokes `teardownEpassAccountSession`, cleanly deleting the `EpassUser` biometric record so users are not locked out of registering in the future.

---

## License
MIT License.

