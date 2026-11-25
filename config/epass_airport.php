<?php

/**
 * Airport evidence is opt-in and must be configured explicitly by the host.
 *
 * EPASS_AIRPORT_MODE accepts only the strings "true" or "false". Airport
 * evidence is opt-in, so deployments that do not configure it remain false.
 */
return [
    'mode' => env('EPASS_AIRPORT_MODE', 'false'),
    'csca_bundle_path' => env('EPASS_AIRPORT_CSCA_BUNDLE_PATH'),
];
