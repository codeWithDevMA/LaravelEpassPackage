@php
    $callbackHostModel = $callbackHostModel ?? ($epassCallbackHostModel ?? null);
    $gatewayUrl = \Ghazali\EPassPackage\Support\EpassDashboardRouteResolver::resolveUrl($callbackHostModel);
    $epassAssetPath = 'vendor/epass-package/js/epass-auth.js';
    $epassAssetVersion = @filemtime(public_path($epassAssetPath)) ?: time();
    $epassAssetUrl = asset($epassAssetPath) . '?v=' . $epassAssetVersion;
@endphp
<script>
    window.ePassConfig = {
        attestationMode: @json(config('epass_attestation.mode', 'verified')),
        antiQcp: @json((bool) config('epass.anti_qcp', false)),
        callbackHostModel: @json($callbackHostModel),
        gatewayUrl: @json($gatewayUrl),
        {{-- Verified mode (attested) endpoints --}}
        challengeEndpoint: @json(\Ghazali\EPassPackage\Support\EpassDashboardRouteResolver::resolveUrl($callbackHostModel, ['intent' => 'challenge'])),
        webBindEndpoint: @json(\Ghazali\EPassPackage\Support\EpassDashboardRouteResolver::resolveUrl($callbackHostModel, ['intent' => 'web_bind'])),
        webStatusEndpoint: @json(\Ghazali\EPassPackage\Support\EpassDashboardRouteResolver::resolveUrl($callbackHostModel, ['intent' => 'web_status'])),
        verifyWebEndpoint: @json(\Ghazali\EPassPackage\Support\EpassDashboardRouteResolver::resolveUrl($callbackHostModel, ['intent' => 'web_verify'])),
        {{-- Standard mode (legacy ECDH relay) endpoints --}}
        standardInitEndpoint: @json(\Ghazali\EPassPackage\Support\EpassDashboardRouteResolver::resolveUrl($callbackHostModel, ['intent' => 'init'])),
        standardReceivePhoneKeyEndpoint: @json(\Ghazali\EPassPackage\Support\EpassDashboardRouteResolver::resolveUrl($callbackHostModel, ['intent' => 'receive_phone_key'])),
        standardCheckStatusEndpoint: @json(\Ghazali\EPassPackage\Support\EpassDashboardRouteResolver::resolveUrl($callbackHostModel, ['intent' => 'check_status'])),
        standardVerifyDecryptedEndpoint: @json(\Ghazali\EPassPackage\Support\EpassDashboardRouteResolver::resolveUrl($callbackHostModel, ['intent' => 'verify_decrypted'])),
        csrfToken: '{{ csrf_token() }}'
    };
</script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script src="{{ $epassAssetUrl }}"></script>
