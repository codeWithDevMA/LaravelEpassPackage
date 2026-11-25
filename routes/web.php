<?php

/**
 * ePass Package Web Routes (RETIRED)
 *
 * In accordance with the Unified ePass Embassy Architecture Blueprint:
 * All legacy hardcoded web routes have been absorbed into the `HandlesEpassProtocol` Trait
 * and the host developer's Embassy Controller (e.g. DeveloperDashboardController).
 *
 * The package registers zero routes in routes/web.php or routes/api.php, eliminating route sprawl.
 * All communication is dispatched via `intent` (protocol) or `action` (business action)
 * through the configured dashboard routes in `config('epass.dashboard_routes')`.
 */
