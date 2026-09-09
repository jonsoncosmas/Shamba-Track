<?php
require __DIR__ . '/includes/bootstrap.php';
?>
<!DOCTYPE html>
<html lang="sw">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Shamba Track</title>
    <meta name="description" content="Track poultry costs, production and profit — works offline.">
    <meta name="theme-color" content="#24402A">
    <link rel="manifest" href="manifest.webmanifest">
    <link rel="apple-touch-icon" href="assets/icons/icon-192.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="assets/css/app.css">
</head>
<body>
    <div class="app-shell">
        <header class="app-header">
            <span class="app-header__mark" aria-hidden="true">🐔</span>
            <span class="app-header__mark">Shamba Track</span>
        </header>

        <main class="app-main">
            <div id="status-banner" class="status-banner" role="status"></div>

            <!-- Progress indicator: shown only during the onboarding sequence -->
            <div id="progress-dots" class="progress-dots hidden" aria-hidden="true">
                <span class="progress-dot" data-step="login"></span>
                <span class="progress-dot" data-step="otp"></span>
                <span class="progress-dot" data-step="farm-setup"></span>
            </div>

            <!-- Loading / auto-login check -->
            <section id="screen-loading" class="screen">
                <div class="loading-shell">
                    <span class="spinner" aria-hidden="true"></span>
                    <span>Inapakia... / Loading...</span>
                </div>
            </section>

            <!-- Screen 1: phone entry -->
            <section id="screen-login" class="screen hidden">
                <div class="intro-block">
                    <span class="intro-icon" aria-hidden="true">🐔</span>
                    <h1>Karibu Shamba Track</h1>
                    <p>Fuatilia gharama, mazao, na faida ya kuku wako — hata bila mtandao.</p>
                    <p class="subtext">Track your poultry costs, production, and profit — even without internet.</p>
                </div>

                <form id="form-login" class="card" novalidate>
                    <label class="form-label" for="input-phone">Namba ya simu / Phone number</label>
                    <div class="phone-field">
                        <span class="phone-field__prefix">+255</span>
                        <input type="tel" id="input-phone" inputmode="numeric" placeholder="712 345 678" autocomplete="tel" required>
                    </div>
                    <p class="field-error" id="error-login"></p>
                    <button type="submit" class="btn-primary" id="btn-send-otp">
                        <span class="spinner" aria-hidden="true"></span>
                        <span class="btn-label">Tuma Nambari / Send Code</span>
                    </button>
                </form>
            </section>

            <!-- Screen 2: OTP verification -->
            <section id="screen-otp" class="screen hidden">
                <div class="intro-block">
                    <h2>Weka Nambari ya OTP</h2>
                    <p class="subtext">Tumetuma nambari kwa <strong id="otp-phone-display"></strong></p>
                </div>
                <div class="card">
                    <form id="form-otp" novalidate>
                        <div class="otp-group" id="otp-group">
                            <input type="text" class="otp-digit" inputmode="numeric" maxlength="1" data-otp-index="0" autocomplete="one-time-code">
                            <input type="text" class="otp-digit" inputmode="numeric" maxlength="1" data-otp-index="1">
                            <input type="text" class="otp-digit" inputmode="numeric" maxlength="1" data-otp-index="2">
                            <input type="text" class="otp-digit" inputmode="numeric" maxlength="1" data-otp-index="3">
                            <input type="text" class="otp-digit" inputmode="numeric" maxlength="1" data-otp-index="4">
                            <input type="text" class="otp-digit" inputmode="numeric" maxlength="1" data-otp-index="5">
                        </div>
                        <p class="field-error" id="error-otp"></p>
                        <button type="submit" class="btn-primary" id="btn-verify-otp">
                            <span class="spinner" aria-hidden="true"></span>
                            <span class="btn-label">Thibitisha / Verify</span>
                        </button>
                        <button type="button" class="btn-link" id="btn-resend-otp">Tuma tena / Resend code</button>
                    </form>
                </div>
            </section>

            <!-- Screen 3: farm setup -->
            <section id="screen-farm-setup" class="screen hidden">
                <div class="intro-block">
                    <h2>Weka Taarifa za Shamba</h2>
                    <p class="subtext">Set up your farm profile</p>
                </div>
                <div class="card">
                    <form id="form-farm-setup" novalidate>
                        <label class="form-label" for="input-farm-name">Jina la Shamba / Farm name</label>
                        <input type="text" id="input-farm-name" class="text-input" required>

                        <label class="form-label" for="input-region">Mkoa / Region</label>
                        <input type="text" id="input-region" class="text-input">

                        <label class="form-label" for="input-district">Wilaya / District</label>
                        <input type="text" id="input-district" class="text-input">

                        <label class="form-label" for="input-village">Kijiji / Village</label>
                        <input type="text" id="input-village" class="text-input">

                        <label class="form-label" for="input-currency-search">Sarafu / Currency</label>
                        <input type="text" id="input-currency-search" class="text-input" placeholder="TZS, Shilling, Tanzania..." autocomplete="off">
                        <div id="currency-results" class="currency-list hidden"></div>
                        <input type="hidden" id="input-currency-code">
                        <p class="field-hint" id="currency-selected-hint"></p>

                        <p class="field-error" id="error-farm-setup"></p>
                        <button type="submit" class="btn-primary" id="btn-save-farm">
                            <span class="spinner" aria-hidden="true"></span>
                            <span class="btn-label">Endelea / Continue</span>
                        </button>
                    </form>
                </div>
            </section>

            <!-- Screen 4: dashboard placeholder -->
            <section id="screen-dashboard" class="screen hidden">
                <div class="dashboard-greeting">
                    <span class="intro-icon" aria-hidden="true">🌾</span>
                    <h1>Karibu, <span id="dashboard-farm-name"></span></h1>
                    <p class="subtext">Dashibodi kamili inakuja / Full dashboard is on its way</p>
                </div>
                <div class="card">
                    <ul class="roadmap-list">
                        <li>Kuongeza kundi la kuku / Add a batch</li>
                        <li>Kurekodi chakula na mayai / Log feed and eggs</li>
                        <li>Kuona faida yako / See your profit</li>
                    </ul>
                    <button type="button" class="btn-link" id="btn-logout">Toka / Log out</button>
                </div>
            </section>
        </main>
    </div>

    <script src="assets/js/app.js"></script>
    <script src="assets/js/auth.js"></script>
</body>
</html>
