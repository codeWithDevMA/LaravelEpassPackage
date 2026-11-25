<?php

/**
 * ePass Package API Routes
 *
 * In accordance with the Unified ePass Embassy Architecture Blueprint:
 * All legacy hardcoded API routes have been completely absorbed into the
 * `HandlesEpassProtocol` Trait and the Host Embassy Gateway Controller.
 *
 * Incoming requests are dispatched by `intent` (technical protocol)
 * or `action` (business action) via the configured dashboard routes:
 * `config('epass.dashboard_routes')` (e.g. 'user_alias' => 'developer.dashboard').
 *
 * This eliminates package route sprawl and ensures zero pollution of `php artisan route:list`.
 */
