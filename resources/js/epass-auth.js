class EPassAuth {
    constructor(options = {}) {
        this.config = window.ePassConfig || {};
        this.qrContainer = document.getElementById(options.qrContainer || 'epass-qrcode');
        this.statusContainer = document.getElementById(options.statusContainer || 'epass-status');
        this.onSuccess = options.onSuccess || ((data) => {
            const target = this.resolveSuccessRedirectUrl(data);
            if (target) window.location.href = target;
        });
        this.onError = options.onError || ((err) => {
            console.error('ePass Error:', err);
            if (this.statusContainer) this.statusContainer.innerText = 'Error: ' + err.message;
        });
        this.onStatusChange = options.onStatusChange || ((msg) => {
            if (this.statusContainer) this.statusContainer.innerText = msg;
        });
        this.activeChallenge = null;
        this.activeSessionId = null;
        this.isSubmitting = false;
        this.pollTimer = null;
        this.lastQr2Fingerprint = null;
        this.currentMode = null; 
        this.currentFlowMode = null; 
        this.getFinalPayload = options.getFinalPayload || null;
    }
    logDebug(event, details = {}) {
        try {
            console.debug('[ePassAuth]', event, details);
        } catch (_) {
        }
    }
    summarizeQrPayload(payload) {
        if (!payload || typeof payload !== 'object') {
            return {
                type: typeof payload,
                isNull: payload === null,
            };
        }
        const summary = {
            keys: Object.keys(payload),
        };
        for (const [key, value] of Object.entries(payload)) {
            if (typeof value === 'string') {
                summary[key] = {
                    type: 'string',
                    length: value.length,
                    preview: value.slice(0, 24),
                };
            } else if (typeof value === 'number' || typeof value === 'boolean') {
                summary[key] = value;
            } else if (value && typeof value === 'object') {
                summary[key] = {
                    type: Array.isArray(value) ? 'array' : 'object',
                    keys: Object.keys(value).slice(0, 10),
                };
            } else {
                summary[key] = {
                    type: typeof value,
                    isNull: value === null,
                };
            }
        }
        return summary;
    }
    resolveCallbackHostModel() {
        const candidates = [];
        const configValue = this.config.callbackHostModel;
        if (typeof configValue === 'string' && configValue.trim() !== '') {
            candidates.push(configValue.trim());
        }
        if (typeof window !== 'undefined' && window.location && window.location.search) {
            try {
                const queryValue = new URLSearchParams(window.location.search).get('callback_host_model');
                if (typeof queryValue === 'string' && queryValue.trim() !== '') {
                    candidates.push(queryValue.trim());
                }
            } catch (_) {
            }
        }
        if (typeof window !== 'undefined' && typeof window.sessionStorage !== 'undefined') {
            try {
                const storedValue = window.sessionStorage.getItem('epass_callback_host_model');
                if (typeof storedValue === 'string' && storedValue.trim() !== '') {
                    candidates.push(storedValue.trim());
                }
            } catch (_) {
            }
        }
        const resolved = candidates.find((value) => typeof value === 'string' && value.trim() !== '') || null;
        if (resolved && typeof window !== 'undefined' && typeof window.sessionStorage !== 'undefined') {
            try {
                window.sessionStorage.setItem('epass_callback_host_model', resolved);
            } catch (_) {
            }
        }
        this.logDebug('resolveCallbackHostModel', {
            resolved,
            hasConfigValue: typeof configValue === 'string' && configValue.trim() !== '',
            hasQueryValue: typeof window !== 'undefined' && window.location && typeof window.location.search === 'string'
                ? new URLSearchParams(window.location.search).has('callback_host_model')
                : false,
        });
        return resolved;
    }
    async fetchJson(url, options = {}) {
        const headers = {
            'Accept': 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': this.config.csrfToken,
            ...(options.headers || {}),
        };
        const response = await fetch(url, { credentials: 'include', ...options, headers });
        const data = await response.json();
        return { response, data };
    }
    stopPolling() {
        if (this.pollTimer !== null) {
            clearTimeout(this.pollTimer);
            this.pollTimer = null;
        }
    }
    renderQr(payload) {
        if (!this.qrContainer) return;
        this.qrContainer.innerHTML = '';
        const qrText = JSON.stringify(payload);
        this.logDebug('renderQr.payload', this.summarizeQrPayload(payload));
        this.logDebug('renderQr.text', {
            length: typeof qrText === 'string' ? qrText.length : null,
            startsWith: typeof qrText === 'string' ? qrText.slice(0, 64) : null,
        });
        if (typeof QRCode !== 'undefined') {
            try {
                new QRCode(this.qrContainer, {
                    text: qrText,
                    width: 500,
                    height: 500,
                    colorDark: '#000000',
                    colorLight: '#ffffff',
                    correctLevel: QRCode.CorrectLevel.L,
                });
                try {
                    setTimeout(() => {
                        if (this.qrContainer && typeof this.qrContainer.scrollIntoView === 'function') {
                            this.qrContainer.scrollIntoView({
                                behavior: 'smooth',
                                block: 'center',
                                inline: 'nearest'
                            });
                        }
                    }, 100);
                } catch (_) {
                }
            } catch (error) {
                this.logDebug('renderQr.failure', {
                    message: error && error.message ? error.message : String(error),
                    stack: error && error.stack ? error.stack : null,
                    payload: this.summarizeQrPayload(payload),
                });
                throw error;
            }
        } else {
            this.onError(new Error('QRCode library is not loaded. Please include qrcode.min.js.'));
        }
    }
    async startFlow(mode = 'login') {
        this.stopPolling();
        this.activeChallenge = null;
        this.activeSessionId = null;
        this.isSubmitting = false;
        this.lastQr2Fingerprint = null;
        this.currentMode = 'standard';
        this.currentFlowMode = mode;
        this.saveReturnUrl();
        await this.startStandardFlow(mode);
    }
    async startStandardFlow(mode) {
        this.currentMode = 'standard';
        this.saveReturnUrl();
        const callbackHostModel = this.resolveCallbackHostModel();
        this.onStatusChange('Standard mode: Generating QR...');
        try {
            const { response, data } = await this.fetchJson(this.config.standardInitEndpoint, {
                method: 'POST',
                body: JSON.stringify({
                    mode,
                    callback_host_model: callbackHostModel,
                }),
            });
            if (!response.ok || data.status !== 'ok') {
                throw new Error(data.message || 'Failed to start standard QR session.');
            }
            const sessionId = typeof data.session_id === 'string' ? data.session_id.trim() : '';
            if (sessionId === '') {
                throw new Error('Server did not return a standard QR session ID.');
            }
            this.activeSessionId = sessionId;
            const qr1Payload = {
                session_id: sessionId,
                qrnumber1: 1,
                api_url: this.config.standardReceivePhoneKeyEndpoint,
                mode,
                anti_qcp: typeof data.anti_qcp === 'boolean'
                    ? data.anti_qcp
                    : !!this.config.antiQcp,
                server_public: data.server_public || '',
            };
            this.renderQr(qr1Payload);
            this.onStatusChange('QR1 ready. Scan with ePass Authenticator.');
            this.startPolling();
        } catch (error) {
            this.onError(error);
        }
    }
    saveReturnUrl() {
        if (typeof window === 'undefined' || !window.location || typeof window.sessionStorage === 'undefined') {
            return;
        }
        try {
            const currentUrl = new URL(window.location.href);
            if (currentUrl.origin !== window.location.origin) {
                return;
            }
            window.sessionStorage.setItem('epass_return_url', currentUrl.href);
        } catch (_) {
        }
    }
    consumeReturnUrl() {
        if (typeof window === 'undefined' || typeof window.sessionStorage === 'undefined') {
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
            if (window.location && url.href === window.location.href) {
                return null;
            }
            if (url.pathname === '/epass') {
                return null;
            }
            return url.href;
        } catch (_) {
            try {
                window.sessionStorage.removeItem('epass_return_url');
            } catch (_) {
            }
            return null;
        }
    }
    getSameOriginReferrer() {
        if (typeof document === 'undefined' || typeof document.referrer !== 'string' || document.referrer.trim() === '') {
            return null;
        }
        try {
            const referrer = new URL(document.referrer);
            if (!window.location || referrer.origin !== window.location.origin) {
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
    resolveSuccessRedirectUrl(data = {}) {
        if (data && typeof data === 'object') {
            if (typeof data.redirect === 'string' && data.redirect.trim() !== '') {
                return data.redirect.trim();
            }
            if (typeof data.redirect_url === 'string' && data.redirect_url.trim() !== '') {
                return data.redirect_url.trim();
            }
            if (typeof data.handoff_url === 'string' && data.handoff_url.trim() !== '') {
                return data.handoff_url.trim();
            }
        }
        const rememberedTarget = this.consumeReturnUrl();
        if (rememberedTarget) {
            return rememberedTarget;
        }
        const referrerTarget = this.getSameOriginReferrer();
        if (referrerTarget) {
            return referrerTarget;
        }
        if (this.config.gatewayUrl) {
            return this.config.gatewayUrl;
        }
        return null;
    }
    startPolling() {
        this.stopPolling();
        this.pollTimer = setTimeout(() => this.pollStatus(), 1500);
    }
    async pollStatus() {
        if (this.isSubmitting) return;
        await this.pollStandardStatus();
    }
    async pollStandardStatus() {
        if (!this.activeSessionId) return;
        try {
            const { response, data } = await this.fetchJson(this.config.standardCheckStatusEndpoint, {
                method: 'POST',
                body: JSON.stringify({ session_id: this.activeSessionId }),
            });
            if (!response.ok || data.status === 'error' || data.status === 'expired') {
                throw new Error(data.message || 'Session expired or error occurred.');
            }
            if (data.status === 'phone_public_ready' && data.qr2) {
                const fingerprint = JSON.stringify(data.qr2);
                if (this.lastQr2Fingerprint !== fingerprint) {
                    this.lastQr2Fingerprint = fingerprint;
                    this.renderQr(data.qr2);
                    this.onStatusChange('QR2 ready. Scan it on the same phone.');
                }
            }
            if (data.status === 'encrypted_ready') {
                this.onStatusChange('Encrypted data received. Verifying...');
                await this.completeStandardFlow(data);
                return;
            }
            this.pollTimer = setTimeout(() => this.pollStandardStatus(), 1800);
        } catch (error) {
            this.onError(error);
        }
    }
    async completeStandardFlow(encryptedData) {
        if (this.isSubmitting) return;
        this.isSubmitting = true;
        this.stopPolling();
        this.onStatusChange('Finalizing standard mode session...');
        try {
            const finalPayload = typeof this.getFinalPayload === 'function' ? this.getFinalPayload() : {};
            const { response, data } = await this.fetchJson(this.config.standardVerifyDecryptedEndpoint, {
                method: 'POST',
                body: JSON.stringify({
                    session_id: this.activeSessionId,
                    ...finalPayload
                }),
            });
            if (!response.ok || (data.status !== 'ok' && data.success !== true)) {
                throw new Error(data.message || 'Failed to verify passport.');
            }
            this.onStatusChange('Success!');
            if (data.handoff_url) {
                window.location.href = data.handoff_url;
                return;
            }
            this.onSuccess(data);
        } catch (error) {
            this.onError(error);
        }
    }
    static mount(options = {}) {
        const prefix = options.prefix || 'epass';
        const flow = options.flow || 'link';
        const qrContainerId = options.qrContainer || `${prefix}-qrcode`;
        const statusContainerId = options.statusContainer || `${prefix}-status`;
        const stepId = options.qrStep || `${prefix}-qr-step`;
        const wrapId = options.qrWrap || `${prefix}-qr-wrap`;
        const panelId = options.panel || `${prefix}-panel`;
        const toggleBtnId = options.toggleBtn || `${prefix}-toggle`;
        const refreshBtnId = options.refreshBtn || `${prefix}-refresh`;
        const errorAlertId = options.errorAlert || `${prefix}-error-alert`;
        const statusNode = document.getElementById(statusContainerId);
        const qrStepNode = document.getElementById(stepId);
        const qrWrapNode = document.getElementById(wrapId);
        const panelNode = document.getElementById(panelId);
        const toggleBtnNode = document.getElementById(toggleBtnId);
        const refreshBtnNode = document.getElementById(refreshBtnId);
        const errorAlertNode = document.getElementById(errorAlertId);
        const instance = new EPassAuth({
            qrContainer: qrContainerId,
            statusContainer: statusContainerId,
            getFinalPayload: options.getFinalPayload || null,
            onStatusChange(message) {
                if (statusNode) statusNode.innerText = message;
                if (qrStepNode) {
                    if (message && message.toLowerCase().includes('qr2')) {
                        qrStepNode.innerText = 'QR2 Ready';
                    } else if (message && message.toLowerCase().includes('qr1')) {
                        qrStepNode.innerText = 'QR1 Ready';
                    }
                }
                if (typeof options.onStatusChange === 'function') {
                    options.onStatusChange(message);
                }
            },
            async onSuccess(response) {
                if (statusNode && response && response.message) {
                    statusNode.innerText = response.message;
                }
                if (qrStepNode) qrStepNode.innerText = 'Verified';
                if (qrWrapNode) qrWrapNode.classList.add('hidden');
                if (refreshBtnNode) refreshBtnNode.classList.add('hidden');
                if (typeof options.onSuccess === 'function') {
                    await options.onSuccess(response);
                } else {
                    window.location.reload();
                }
            },
            onError(error) {
                const msg = error?.message || 'Verification failed.';
                if (statusNode) statusNode.innerText = msg;
                if (qrStepNode) qrStepNode.innerText = 'Retry Required';
                if (refreshBtnNode) refreshBtnNode.classList.remove('hidden');
                if (errorAlertNode) {
                    errorAlertNode.textContent = msg;
                    errorAlertNode.classList.remove('hidden');
                }
                if (typeof options.onError === 'function') {
                    options.onError(error);
                }
            }
        });
        const start = async () => {
            if (panelNode) {
                panelNode.classList.remove('hidden');
                panelNode.style.display = '';
            }
            if (qrWrapNode) {
                qrWrapNode.classList.remove('hidden');
                qrWrapNode.style.display = '';
            }
            if (qrStepNode) qrStepNode.innerText = 'Initializing...';
            if (statusNode) statusNode.innerText = options.initialStatus || 'Preparing secure session...';
            if (refreshBtnNode) {
                refreshBtnNode.classList.add('hidden');
                refreshBtnNode.style.display = 'none';
            }
            if (errorAlertNode) {
                errorAlertNode.textContent = '';
                errorAlertNode.classList.add('hidden');
                errorAlertNode.style.display = 'none';
            }
            try {
                const target = document.getElementById(qrContainerId) || panelNode;
                if (target && typeof target.scrollIntoView === 'function') {
                    setTimeout(() => {
                        target.scrollIntoView({
                            behavior: 'smooth',
                            block: 'center',
                            inline: 'nearest'
                        });
                    }, 150);
                }
            } catch (_) {}
            await instance.startFlow(flow);
        };
        if (toggleBtnNode) {
            toggleBtnNode.addEventListener('click', start);
        }
        if (refreshBtnNode) {
            refreshBtnNode.addEventListener('click', start);
        }
        return {
            instance,
            start,
        };
    }
}
window.EPassAuth = EPassAuth;
