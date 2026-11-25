<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'Laravel') }}</title>
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-color: #0b0f19;
            --card-bg: rgba(255, 255, 255, 0.03);
            --border-color: rgba(255, 255, 255, 0.08);
            --text-primary: #f8fafc;
            --text-secondary: #94a3b8;
            --primary-glow: linear-gradient(135deg, #3b82f6 0%, #8b5cf6 100%);
            --accent-glow: linear-gradient(135deg, #10b981 0%, #3b82f6 100%);
            --red-glow: linear-gradient(135deg, #ef4444 0%, #f43f5e 100%);
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Outfit', sans-serif;
            background-color: var(--bg-color);
            color: var(--text-primary);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow-x: hidden;
            position: relative;
        }

        /* Decorative background glows */
        body::before, body::after {
            content: '';
            position: absolute;
            width: 300px;
            height: 300px;
            border-radius: 50%;
            background: #3b82f6;
            filter: blur(120px);
            opacity: 0.15;
            z-index: -1;
        }
        body::before { top: 10%; left: 15%; }
        body::after { bottom: 10%; right: 15%; background: #8b5cf6; }

        .container {
            max-width: 650px;
            width: 90%;
            padding: 40px;
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 24px;
            backdrop-filter: blur(16px);
            box-shadow: 0 20px 40px rgba(0,0,0,0.3);
            text-align: center;
            animation: fadeIn 0.8s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        h1 {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 12px;
            background: linear-gradient(to right, #fff, #94a3b8);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .subtitle {
            color: var(--text-secondary);
            font-size: 1.1rem;
            margin-bottom: 30px;
            line-height: 1.6;
        }

        .button-group {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            justify-content: center;
            margin-bottom: 24px;
        }

        .btn {
            padding: 12px 24px;
            font-size: 1rem;
            font-weight: 500;
            border-radius: 12px;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.3s ease;
            border: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .btn-primary {
            background: var(--primary-glow);
            color: #fff;
            box-shadow: 0 4px 15px rgba(59, 130, 246, 0.3);
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(59, 130, 246, 0.4);
        }

        .btn-accent {
            background: var(--accent-glow);
            color: #fff;
            box-shadow: 0 4px 15px rgba(16, 185, 129, 0.3);
        }
        .btn-accent:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(16, 185, 129, 0.4);
        }

        .btn-secondary {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid var(--border-color);
            color: var(--text-primary);
        }
        .btn-secondary:hover {
            background: rgba(255, 255, 255, 0.1);
            transform: translateY(-2px);
        }

        .btn-danger {
            background: var(--red-glow);
            color: #fff;
            box-shadow: 0 4px 15px rgba(239, 68, 68, 0.3);
        }
        .btn-danger:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(239, 68, 68, 0.4);
        }

        /* ePass specific styling */
        #my-qr-container {
            margin: 24px auto;
            max-width: 500px;
            width: 100%;
            height: auto;
            aspect-ratio: 1 / 1;
            background: #ffffff;
            border: 1px solid var(--border-color);
            border-radius: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #0f172a;
            font-size: 1.1rem;
            font-weight: 600;
            padding: 24px;
            box-sizing: border-box;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }

        #my-qr-container img, #my-qr-container canvas {
            max-width: 100% !important;
            height: auto !important;
            display: block;
            margin: 0 auto;
        }

        #my-status {
            font-size: 0.95rem;
            color: var(--text-secondary);
            margin-bottom: 24px;
            font-weight: 500;
        }

        hr {
            border: none;
            border-top: 1px solid var(--border-color);
            margin: 30px 0;
        }
    </style>
</head>
<body>
    @php
        $callbackContext = \Ghazali\EPassPackage\Support\EpassCallbackContext::peek(request());

        $epassActive = class_exists(\Ghazali\EPassPackage\EPassServiceProvider::class);
        $epassGuard = $epassActive
            ? \Ghazali\EPassPackage\Support\EpassPackageAuth::guardName()
            : null;

        $isAuthenticated = $epassActive && Auth::guard($epassGuard)->check();
        $authUser = $isAuthenticated
            ? Auth::guard($epassGuard)->user()
            : null;

        $authUserClass = is_object($authUser) ? get_class($authUser) : null;
        $authUserTable = is_object($authUser) && method_exists($authUser, 'getTable')
            ? $authUser->getTable()
            : null;

        \Illuminate\Support\Facades\Log::info('epass.welcome.auth_user_resolved', [
            'session_id' => request()->hasSession() ? request()->session()->getId() : null,
            'epass_active' => $epassActive,
            'selected_guard' => $epassGuard,
            'epass_check' => $epassActive ? Auth::guard($epassGuard)->check() : false,
            'epass_user_class' => ($epassActive && is_object(Auth::guard($epassGuard)->user()))
                ? get_class(Auth::guard($epassGuard)->user())
                : null,
            'resolved_user_class' => $authUserClass,
            'resolved_user_table' => $authUserTable,
        ]);

        $callbackAction = $callbackContext['action'] ?? '';
        $callbackMessage = $callbackContext['message'] ?? '';
        $callbackHostModel = $callbackContext['host_model'] ?: null;
        $callbackDashboardUrl = \Ghazali\EPassPackage\Support\EpassDashboardRouteResolver::resolveUrl($callbackHostModel);
    @endphp

    <div class="container">
        @if($epassActive)
            <h1>ePass Integration Active</h1>
            <p class="subtitle">Scan the QR code below to sign in securely.</p>

            @if($callbackMessage)
                <div id="callback-banner" style="margin:0 0 16px;padding:14px 16px;border-radius:16px;background:#ecfdf5;border:1px solid #a7f3d0;color:#14532d;font-weight:700;line-height:1.6;">
                    {{ $callbackMessage }}
                </div>
            @endif

            <div id="my-qr-container"><span>QR Code Will Appear Here</span></div>
            <div id="my-status">Waiting for action...</div>

            <div class="button-group">
                <a href="{{ url('/') }}" class="btn btn-secondary">Go Back</a>
                <button class="btn btn-primary" id="login-btn">Login with ePass</button>
                <button class="btn btn-accent" id="register-btn">Register with ePass</button>
                <button class="btn btn-secondary" id="update-btn">Update Passport</button>
            </div>

            <hr>

            @if($isAuthenticated && $authUser)
                <p style="margin-bottom:15px; color:var(--text-secondary);">ePass session is active.</p>
                <div class="button-group">
                    <a href="{{ \Ghazali\EPassPackage\Models\EpassUser::defaultDashboardUrl() }}" class="btn btn-primary">Open ePass Home</a>
                    <form method="POST" action="{{ \Ghazali\EPassPackage\Support\EpassDashboardRouteResolver::resolveUrl($callbackHostModel, ['intent' => 'logout']) }}" style="display:inline;">
                        @csrf
                        <button type="submit" class="btn btn-danger">Logout</button>
                    </form>
                </div>
            @endif
        @else
            <h1>{{ config('app.name', 'Laravel') }} Application</h1>
            <p class="subtitle">Welcome to your clean Laravel development environment.</p>
        @endif
    </div>

    @if($epassActive)
        @includeIf('epass::components.scripts', ['callbackHostModel' => $callbackHostModel])
        <script>
            function getSameOriginReferrer() {
                if (!document.referrer) {
                    return null;
                }

                try {
                    const referrer = new URL(document.referrer);
                    if (referrer.origin !== window.location.origin) {
                        return null;
                    }

                    if (referrer.href === window.location.href) {
                        return null;
                    }

                    return referrer.href;
                } catch (_) {
                    return null;
                }
            }

            function consumeStoredReturnUrl() {
                if (typeof window.sessionStorage === 'undefined') {
                    return null;
                }

                try {
                    const stored = window.sessionStorage.getItem('epass_return_url');
                    if (!stored) {
                        return null;
                    }

                    window.sessionStorage.removeItem('epass_return_url');

                    const url = new URL(stored, window.location.origin);
                    if (url.origin !== window.location.origin) {
                        return null;
                    }

                    if (url.href === window.location.href || url.pathname === '/epass') {
                        return null;
                    }

                    return url.href;
                } catch (_) {
                    try {
                        window.sessionStorage.removeItem('epass_return_url');
                    } catch (_) {
                        // Ignore cleanup errors.
                    }

                    return null;
                }
            }

            document.addEventListener('DOMContentLoaded', function() {
                const statusEl = document.getElementById('my-status');
                const callbackAction = @json($callbackAction);
                const callbackMessage = @json($callbackMessage);
                const callbackDashboardUrl = @json($callbackDashboardUrl);
                const epass = new window.EPassAuth({
                    qrContainer: 'my-qr-container',
                    statusContainer: 'my-status',
                    onSuccess: function(response) {
                        if (response.requires_login_after_update) {
                            statusEl.style.color = '#3b82f6';
                            statusEl.innerText = response.message;
                            return;
                        }

                        statusEl.style.color = '#10b981';
                        statusEl.innerText = response.message || 'Success! Redirecting...';

                        const target = consumeStoredReturnUrl() || getSameOriginReferrer() || callbackDashboardUrl;
                        if (target) {
                            window.location.href = target;
                            return;
                        }

                        console.log('User:', response.EpassUser || response.user || response);
                    },
                    onError: function(error) {
                        statusEl.style.color = '#ef4444';
                        statusEl.innerText = error.message;
                    }
                });

                if (callbackMessage && callbackAction && callbackAction !== 'update') {
                    const target = consumeStoredReturnUrl() || getSameOriginReferrer() || callbackDashboardUrl;
                    if (target) {
                        statusEl.style.color = '#10b981';
                        statusEl.innerText = callbackMessage;
                        window.location.replace(target);
                        return;
                    }
                }

                document.getElementById('login-btn').addEventListener('click', () => {
                    epass.startFlow('login');
                });

                document.getElementById('register-btn').addEventListener('click', () => {
                    epass.startFlow('register');
                });

                document.getElementById('update-btn').addEventListener('click', () => {
                    epass.startFlow('update');
                });
            });
        </script>
    @endif
</body>
</html>
