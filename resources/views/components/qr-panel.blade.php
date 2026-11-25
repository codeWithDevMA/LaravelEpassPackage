@props([
    'prefix' => 'epass',
    'title' => 'Link ePass',
    'description' => 'Link this signed-in package account to a passport through the secure ePass QR flow. The passport payload stays encrypted until the server decrypts it.',
    'buttonText' => 'Start Linking',
    'stepTitle' => null,
    'stepDescription' => null,
    'lead' => 'Open the ePass Authenticator app, choose the transfer or link flow, and scan the QR code shown here. When the passport is linked successfully this page will refresh.',
    'mode' => 'card', // 'card' or 'inline'
    'hidden' => true,
    'conflictMessage' => null,
    'theme' => 'dark', // 'dark' (default) or 'light'
    'titleColor' => null,
    'descriptionColor' => null,
    'leadColor' => null,
    'qrBoxBg' => null,
    'qrBoxBorder' => null,
    'statusBoxBg' => null,
    'statusBoxBorder' => null,
    'statusTitleColor' => null,
    'statusTextColor' => null,
    'dividerColor' => null,
])

@php
    $isLight = ($theme === 'light');

    // Title / Description / Lead text colors
    $effTitleColor = $titleColor ?? ($isLight ? '#0f172a' : '#ffffff');
    $effDescColor = $descriptionColor ?? ($isLight ? '#64748b' : '#94a3b8');
    $effLeadColor = $leadColor ?? ($isLight ? '#475569' : '#cbd5e1');

    // QR Box & Status Box backgrounds and borders
    $effQrBoxBg = $qrBoxBg ?? ($isLight ? '#f8fafc' : '#0f111a');
    $effQrBoxBorder = $qrBoxBorder ?? ($isLight ? 'rgba(148, 163, 184, 0.25)' : 'rgba(255, 255, 255, 0.1)');
    $effQrBoxShadow = $isLight ? '0 10px 30px rgba(15, 23, 42, 0.06)' : '0 10px 30px rgba(0, 0, 0, 0.3)';

    $effStatusBoxBg = $statusBoxBg ?? ($isLight ? '#ffffff' : '#1a1d2d');
    $effStatusBoxBorder = $statusBoxBorder ?? ($isLight ? 'rgba(148, 163, 184, 0.25)' : 'rgba(255, 255, 255, 0.1)');
    $effStatusTitleColor = $statusTitleColor ?? ($isLight ? '#64748b' : '#94a3b8');
    $effStatusTextColor = $statusTextColor ?? ($isLight ? '#0f172a' : '#ffffff');

    $effDividerColor = $dividerColor ?? ($isLight ? 'rgba(148, 163, 184, 0.25)' : 'rgba(255, 255, 255, 0.1)');
    $effStepColor = $isLight ? '#2563eb' : '#60a5fa';

    // Inline mode backgrounds & borders
    $effInlineBg = $isLight ? '#f8fafc' : 'linear-gradient(180deg,#1e293b 0%,#0f172a 100%)';
    $effInlineBorder = $isLight ? '#cbd5e1' : '#334155';
    $effInlineStatusBg = $isLight ? '#ffffff' : '#0f111a';
    $effInlineStatusText = $isLight ? '#0f172a' : '#ffffff';
    $effInlineStatusBorder = $isLight ? '#cbd5e1' : '#334155';
@endphp

@if($mode === 'card')
    <section {{ $attributes->merge(['class' => 'card', 'id' => $prefix . '-container']) }}>
        <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:16px;flex-wrap:wrap;">
            <div>
                <h2 style="color:{{ $effTitleColor }};font-weight:700;">{{ $title }}</h2>
                <p style="color:{{ $effDescColor }};">{{ $description }}</p>
            </div>
            <button id="{{ $prefix }}-toggle" class="btn btn-primary" type="button">{{ $buttonText }}</button>
        </div>

        @if(!empty($conflictMessage))
            <div style="margin-top:16px;padding:14px 16px;border-radius:16px;background:#fef2f2;border:1px solid #fecaca;color:#991b1b;font-weight:800;line-height:1.6;">
                {{ $conflictMessage }}
            </div>
        @endif

        <div id="{{ $prefix }}-panel" class="{{ $hidden ? 'hidden' : '' }}" style="border-top:1px solid {{ $effDividerColor }};padding-top:24px;margin-top:20px;">
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:24px;align-items:center;">
                <div style="text-align:center;background:{{ $effQrBoxBg }};padding:24px;border-radius:24px;border:1px solid {{ $effQrBoxBorder }};box-shadow:{{ $effQrBoxShadow }};">
                    <div id="{{ $prefix }}-qrcode" style="display:inline-block;padding:20px;background:#ffffff;border-radius:20px;border:1px solid rgba(0,0,0,0.1);box-sizing:border-box;max-width:100%;box-shadow:0 8px 24px rgba(0,0,0,0.12);"></div>
                    <div id="{{ $prefix }}-qr-step" style="margin-top:16px;font-weight:800;font-size:14px;color:{{ $effStepColor }};letter-spacing:.08em;text-transform:uppercase;">Initializing...</div>
                </div>
                <div>
                    <div id="{{ $prefix }}-status-box" style="padding:16px;border-radius:16px;background:{{ $effStatusBoxBg }};border:1px solid {{ $effStatusBoxBorder }};margin-bottom:14px;">
                        <div style="font-size:11px;font-weight:800;color:{{ $effStatusTitleColor }};text-transform:uppercase;letter-spacing:.05em;margin-bottom:4px;">Current Status</div>
                        <div id="{{ $prefix }}-status" style="font-size:14px;font-weight:700;color:{{ $effStatusTextColor }};">Preparing secure session...</div>
                    </div>
                    <div id="{{ $prefix }}-error-alert" class="hidden" style="margin:8px 0 14px;color:#f87171;background:rgba(239,68,68,0.1);border:1px solid rgba(239,68,68,0.2);padding:10px 14px;border-radius:12px;font-size:13px;font-weight:600;line-height:1.5;"></div>
                    <p class="lead" style="font-size:14px;color:{{ $effLeadColor }};line-height:1.6;">
                        {{ $lead }}
                    </p>
                    <button id="{{ $prefix }}-refresh" class="btn btn-neutral hidden" type="button" style="margin-top:12px;">Regenerate QR Code</button>
                </div>
            </div>
        </div>
    </section>
@else
    {{-- Inline panel (for modals, wizards, embedded containers) --}}
    <div {{ $attributes->merge(['class' => 'danger-panel ' . ($hidden ? 'hidden' : ''), 'id' => $prefix . '-panel']) }} style="background:{{ $effInlineBg }};border:1px solid {{ $effInlineBorder }};border-radius:16px;padding:20px;">
        @if($stepTitle || $stepDescription)
            <div style="display:grid;gap:8px;margin-bottom:12px;">
                @if($stepTitle)<strong style="font-size:15px;color:{{ $effTitleColor }};">{{ $stepTitle }}</strong>@endif
                @if($stepDescription)<span class="menu-copy" style="color:{{ $effDescColor }};font-size:13px;">{{ $stepDescription }}</span>@endif
            </div>
        @endif
        <div id="{{ $prefix }}-status" class="inline-status" style="background:{{ $effInlineStatusBg }};color:{{ $effInlineStatusText }};border:1px solid {{ $effInlineStatusBorder }};padding:12px 14px;border-radius:12px;font-size:13px;font-weight:600;">Preparing secure passport verification...</div>
        <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:12px;">
            <button type="button" id="{{ $prefix }}-refresh" class="btn btn-neutral hidden">Regenerate QR Code</button>
        </div>
        <div id="{{ $prefix }}-qr-wrap" class="hidden" style="display:grid;gap:14px;justify-items:center;padding-top:12px;">
            <div id="{{ $prefix }}-qrcode" style="display:inline-block;padding:20px;background:#ffffff;border-radius:20px;border:1px solid rgba(0,0,0,0.1);box-sizing:border-box;max-width:100%;box-shadow:0 8px 24px rgba(0,0,0,0.12);"></div>
            <div id="{{ $prefix }}-qr-step" class="pill neutral" style="margin-top:8px;font-weight:800;font-size:13px;color:{{ $effStepColor }};letter-spacing:.08em;text-transform:uppercase;">Initializing</div>
        </div>
    </div>
@endif

<style>
    #{{ $prefix }}-qrcode {
        max-width: 500px;
        width: 100%;
        box-sizing: border-box;
    }
    #{{ $prefix }}-qrcode img,
    #{{ $prefix }}-qrcode canvas {
        max-width: 100% !important;
        height: auto !important;
        display: block;
        margin: 0 auto;
    }
</style>

<script>
    (function () {
        const toggleBtn = document.getElementById('{{ $prefix }}-toggle');
        const targetEl = document.getElementById('{{ $prefix }}-qrcode') || document.getElementById('{{ $prefix }}-panel');

        function scrollToTargetCenter() {
            if (!targetEl) return;
            // Scroll vertically so the QR code is right in the center of the screen
            targetEl.scrollIntoView({
                behavior: 'smooth',
                block: 'center',
                inline: 'nearest'
            });
        }

        if (toggleBtn) {
            toggleBtn.addEventListener('click', function () {
                // Initial smooth scroll
                setTimeout(scrollToTargetCenter, 150);
                setTimeout(scrollToTargetCenter, 400);
            });
        }

        // Also observe when the QR canvas/img is inserted into the DOM
        const qrContainer = document.getElementById('{{ $prefix }}-qrcode');
        if (qrContainer && window.MutationObserver) {
            let lastScroll = 0;
            const observer = new MutationObserver(function () {
                if (qrContainer.querySelector('canvas') || qrContainer.querySelector('img')) {
                    const now = Date.now();
                    if (now - lastScroll > 1500) {
                        lastScroll = now;
                        setTimeout(scrollToTargetCenter, 100);
                    }
                }
            });
            observer.observe(qrContainer, { childList: true, subtree: true });
        }
    })();
</script>
