/**
 * Shamba Track — Auth & onboarding flow
 * Fully asynchronous: every action is a fetch() call with preventDefault()
 * on its form. No screen change in this file ever triggers a page reload —
 * screens are shown/hidden divs within the single loaded document.
 *
 * Structural note: DOMContentLoaded registration and a hard failsafe timer
 * are set up FIRST, before any element lookups that could throw. This
 * guarantees the loading screen always resolves to something — even if
 * index.php and this file ever drift out of sync (e.g. a stale cached
 * HTML file served alongside a newer JS file) — instead of leaving the
 * user stuck on "Inapakia... / Loading..." forever.
 */

(function () {
    'use strict';

    const CACHE_KEY = 'shambatrack_auth_cache';
    const ONBOARDING_STEPS = ['login', 'otp', 'farm-setup'];
    let pendingPhone = null;
    let resendTimer = null;

    function $(id) { return document.getElementById(id); }

    function showScreen(id) {
        document.querySelectorAll('.screen').forEach((el) => el.classList.add('hidden'));
        $(id).classList.remove('hidden');
        updateProgressDots(id);
    }

    function updateProgressDots(screenId) {
        const dotsEl = $('progress-dots');
        if (!dotsEl) return;
        const stepMap = { 'screen-login': 'login', 'screen-otp': 'otp', 'screen-farm-setup': 'farm-setup' };
        const currentStep = stepMap[screenId];

        if (!currentStep) {
            dotsEl.classList.add('hidden');
            return;
        }
        dotsEl.classList.remove('hidden');

        const currentIndex = ONBOARDING_STEPS.indexOf(currentStep);
        dotsEl.querySelectorAll('.progress-dot').forEach((dot, i) => {
            dot.classList.toggle('is-active', i === currentIndex);
            dot.classList.toggle('is-done', i < currentIndex);
        });
    }

    function setError(id, message) {
        const el = $(id);
        if (el) el.textContent = message || '';
    }

    function setButtonLoading(btn, isLoading) {
        btn.disabled = isLoading;
        btn.classList.toggle('is-loading', isLoading);
    }

    function readCache() {
        try {
            const raw = localStorage.getItem(CACHE_KEY);
            return raw ? JSON.parse(raw) : null;
        } catch (e) {
            return null;
        }
    }

    function writeCache(data) {
        try {
            localStorage.setItem(CACHE_KEY, JSON.stringify(data));
        } catch (e) { /* storage unavailable — app still works, just re-checks online each load */ }
    }

    function clearCache() {
        try {
            localStorage.removeItem(CACHE_KEY);
        } catch (e) { /* ignore */ }
    }

    async function api(path, options = {}) {
        const res = await fetch('/api' + path, {
            method: options.method || 'GET',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'same-origin',
            body: options.body ? JSON.stringify(options.body) : undefined,
        });
        const data = await res.json().catch(() => ({}));
        return { ok: res.ok, status: res.status, data };
    }

    function routeFromState(state) {
        if (!state || !state.authenticated) {
            showScreen('screen-login');
            return;
        }
        if (!state.hasFarm) {
            showScreen('screen-farm-setup');
            return;
        }
        $('dashboard-farm-name').textContent = (state.farm && state.farm.name) || '';
        showScreen('screen-dashboard');
    }

    // ---- initial load: instant render from cache, then reconcile with server ----
    async function init() {
        const cached = readCache();
        routeFromState(cached);

        if (!navigator.onLine) return; // trust the cache fully while offline

        const { ok, data } = await api('/auth/me');
        if (!ok) return; // network hiccup — keep showing cached state

        const state = {
            authenticated: data.authenticated,
            hasFarm: data.has_farm,
            user: data.user || null,
            farm: data.farm || null,
        };
        writeCache(state);
        routeFromState(state);
    }

    function forceShowLogin() {
        try {
            showScreen('screen-login');
        } catch (e) {
            // If even this fails, the DOM itself doesn't match what this
            // script expects — nothing more we can safely do client-side.
            console.error('[ShambaTrack] Could not recover to login screen. index.php and auth.js are likely mismatched versions.', e);
        }
    }

    // ---- Bind all form/element event listeners ----
    // Kept in its own function, called inside a try/catch below, so one
    // missing/renamed element (version skew between deployed files) logs a
    // clear diagnostic instead of silently killing the rest of the script
    // and stranding the loading screen.
    function bindEventListeners() {
        // ---- Screen 1: send OTP ----
        $('form-login').addEventListener('submit', async (e) => {
            e.preventDefault();
            setError('error-login', '');
            const localNumber = $('input-phone').value.replace(/\D/g, '');

            if (localNumber.length < 9) {
                setError('error-login', 'Weka namba sahihi ya simu. / Enter a valid phone number.');
                return;
            }

            const phone = '+255' + localNumber.replace(/^0/, '');
            const btn = $('btn-send-otp');
            setButtonLoading(btn, true);
            const { ok, data } = await api('/auth/send-otp', { method: 'POST', body: { phone_number: phone } });
            setButtonLoading(btn, false);

            if (!ok) {
                setError('error-login', data.message || 'Imeshindikana. Jaribu tena.');
                return;
            }

            pendingPhone = data.phone_number;
            $('otp-phone-display').textContent = pendingPhone;
            if (data.debug_code) {
                console.log('[Dev only] OTP code:', data.debug_code);
            }
            resetOtpBoxes();
            startResendCountdown();
            showScreen('screen-otp');
            document.querySelector('.otp-digit[data-otp-index="0"]').focus();
        });

        // ---- Screen 2: OTP digit boxes ----
        const otpDigits = () => Array.from(document.querySelectorAll('.otp-digit'));

        function resetOtpBoxes() {
            otpDigits().forEach((box) => {
                box.value = '';
                box.classList.remove('is-filled');
            });
        }

        function currentOtpCode() {
            return otpDigits().map((box) => box.value).join('');
        }

        $('otp-group').addEventListener('input', (e) => {
            const box = e.target;
            if (!box.classList.contains('otp-digit')) return;

            box.value = box.value.replace(/\D/g, '').slice(0, 1);
            box.classList.toggle('is-filled', box.value !== '');

            if (box.value) {
                const next = box.nextElementSibling;
                if (next && next.classList.contains('otp-digit')) next.focus();
            }

            if (currentOtpCode().length === 6) {
                $('form-otp').requestSubmit();
            }
        });

        $('otp-group').addEventListener('keydown', (e) => {
            const box = e.target;
            if (!box.classList.contains('otp-digit')) return;

            if (e.key === 'Backspace' && !box.value) {
                const prev = box.previousElementSibling;
                if (prev && prev.classList.contains('otp-digit')) {
                    prev.focus();
                    prev.value = '';
                    prev.classList.remove('is-filled');
                }
            }
        });

        $('otp-group').addEventListener('paste', (e) => {
            const pasted = (e.clipboardData || window.clipboardData).getData('text').replace(/\D/g, '').slice(0, 6);
            if (!pasted) return;
            e.preventDefault();
            const boxes = otpDigits();
            pasted.split('').forEach((digit, i) => {
                if (boxes[i]) {
                    boxes[i].value = digit;
                    boxes[i].classList.add('is-filled');
                }
            });
            const lastFilled = boxes[Math.min(pasted.length, 6) - 1];
            if (lastFilled) lastFilled.focus();
            if (pasted.length === 6) $('form-otp').requestSubmit();
        });

        $('form-otp').addEventListener('submit', async (e) => {
            e.preventDefault();
            setError('error-otp', '');
            const code = currentOtpCode();

            if (code.length !== 6) {
                setError('error-otp', 'Weka nambari kamili ya OTP. / Enter the full 6-digit code.');
                return;
            }

            const btn = $('btn-verify-otp');
            setButtonLoading(btn, true);
            const { ok, data } = await api('/auth/verify-otp', {
                method: 'POST',
                body: { phone_number: pendingPhone, code },
            });
            setButtonLoading(btn, false);

            if (!ok) {
                setError('error-otp', data.message || 'Nambari si sahihi. / Invalid code.');
                resetOtpBoxes();
                document.querySelector('.otp-digit[data-otp-index="0"]').focus();
                return;
            }

            clearInterval(resendTimer);
            const state = {
                authenticated: true,
                hasFarm: data.has_farm,
                user: data.user,
                farm: data.farm || null,
            };
            writeCache(state);
            routeFromState(state);
        });

        function startResendCountdown() {
            const btn = $('btn-resend-otp');
            let seconds = 45;
            btn.disabled = true;
            clearInterval(resendTimer);

            const render = () => {
                btn.textContent = seconds > 0
                    ? `Tuma tena baada ya ${seconds}s / Resend in ${seconds}s`
                    : 'Tuma tena / Resend code';
            };
            render();

            resendTimer = setInterval(() => {
                seconds -= 1;
                if (seconds <= 0) {
                    clearInterval(resendTimer);
                    btn.disabled = false;
                }
                render();
            }, 1000);
        }

        $('btn-resend-otp').addEventListener('click', async () => {
            if (!pendingPhone) return;
            await api('/auth/send-otp', { method: 'POST', body: { phone_number: pendingPhone } });
            resetOtpBoxes();
            startResendCountdown();
            document.querySelector('.otp-digit[data-otp-index="0"]').focus();
        });

        // ---- Screen 3: currency search + farm setup ----
        let currencySearchTimer = null;
        $('input-currency-search').addEventListener('input', (e) => {
            const q = e.target.value.trim();
            clearTimeout(currencySearchTimer);
            currencySearchTimer = setTimeout(() => runCurrencySearch(q), 200);
        });

        async function runCurrencySearch(query) {
            const resultsEl = $('currency-results');
            const { ok, data } = await api('/currencies?q=' + encodeURIComponent(query));

            if (!ok || !data.currencies || data.currencies.length === 0) {
                resultsEl.classList.add('hidden');
                resultsEl.innerHTML = '';
                return;
            }

            resultsEl.innerHTML = data.currencies
                .map((c) => `<button type="button" class="currency-option" data-code="${c.code}" data-label="${c.code} — ${c.name} (${c.symbol})">${c.code} — ${c.name} (${c.symbol}) · ${c.country}</button>`)
                .join('');
            resultsEl.classList.remove('hidden');
        }

        $('currency-results').addEventListener('click', (e) => {
            const btn = e.target.closest('.currency-option');
            if (!btn) return;
            $('input-currency-code').value = btn.dataset.code;
            $('currency-selected-hint').textContent = 'Umechagua: ' + btn.dataset.label;
            $('input-currency-search').value = btn.dataset.label;
            $('currency-results').classList.add('hidden');
            $('currency-results').innerHTML = '';
        });

        $('form-farm-setup').addEventListener('submit', async (e) => {
            e.preventDefault();
            setError('error-farm-setup', '');

            const payload = {
                name: $('input-farm-name').value.trim(),
                region: $('input-region').value.trim(),
                district: $('input-district').value.trim(),
                village: $('input-village').value.trim(),
                currency_code: $('input-currency-code').value.trim(),
            };

            if (!payload.name) {
                setError('error-farm-setup', 'Jina la shamba linahitajika. / Farm name is required.');
                return;
            }
            if (!payload.currency_code) {
                setError('error-farm-setup', 'Chagua sarafu. / Please select a currency.');
                return;
            }

            const btn = $('btn-save-farm');
            setButtonLoading(btn, true);
            const { ok, data } = await api('/farms', { method: 'POST', body: payload });
            setButtonLoading(btn, false);

            if (!ok) {
                setError('error-farm-setup', data.message || 'Imeshindikana. Jaribu tena.');
                return;
            }

            const cached = readCache() || {};
            const state = { ...cached, authenticated: true, hasFarm: true, farm: data.farm };
            writeCache(state);
            routeFromState(state);
        });

        // ---- Logout ----
        $('btn-logout').addEventListener('click', async () => {
            await api('/auth/logout', { method: 'POST' });
            clearCache();
            routeFromState(null);
        });
    }

    // ---- Boot sequence ----
    // 1. Register DOMContentLoaded + init FIRST, before anything risky.
    document.addEventListener('DOMContentLoaded', function () {
        try {
            init();
        } catch (err) {
            console.error('[ShambaTrack] init() failed — falling back to login screen.', err);
            forceShowLogin();
        }
    });

    // 2. Hard failsafe: if the loading screen is still showing after 4s for
    // ANY reason, force it away. Makes a permanently stuck loading screen
    // structurally impossible rather than merely "shouldn't happen".
    setTimeout(function () {
        const loadingEl = document.getElementById('screen-loading');
        if (loadingEl && !loadingEl.classList.contains('hidden')) {
            console.warn('[ShambaTrack] Still on loading screen after 4s — forcing login screen. This usually means a JS error above (check the browser console) or stale cached files (try a hard refresh / clear the service worker).');
            forceShowLogin();
        }
    }, 4000);

    // 3. Bind form/element listeners — wrapped so a missing element
    // (version skew between deployed files) can't silently break anything
    // upstream of this point.
    try {
        bindEventListeners();
    } catch (err) {
        console.error('[ShambaTrack] Failed to bind event listeners — index.php and auth.js are likely out of sync (a stale cached HTML file with a newer JS file, or vice versa). Full error:', err);
    }
})();
