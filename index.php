<?php
require __DIR__ . '/includes/bootstrap.php';

// Compute the URL path this app is actually mounted under, so every link,
// the manifest, the service worker, and every fetch() call work whether
// this is served from a domain root (production plan) or a local subfolder
// (e.g. XAMPP htdocs/shamba-track/) — with zero manual configuration.
// dirname('/index.php') => '\' on some setups, normalize to '/'; dirname
// of a subfolder request ('/shamba-track/index.php') => '/shamba-track'.
$scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
$basePath = rtrim($scriptDir, '/'); // '' at root, '/shamba-track' in a subfolder
$asset = fn(string $path) => $basePath . '/' . ltrim($path, '/');
?>
<!DOCTYPE html>
<html lang="sw">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Shamba Track</title>
    <meta name="description" content="Track poultry costs, production and profit — works offline.">
    <meta name="theme-color" content="#24402A">
    <link rel="manifest" href="<?= htmlspecialchars($asset('manifest.webmanifest')) ?>">
    <link rel="apple-touch-icon" href="<?= htmlspecialchars($asset('assets/icons/icon-192.png')) ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="<?= htmlspecialchars($asset('assets/css/app.css')) ?>">
    <script>window.ST_BASE = <?= json_encode($basePath) ?>;</script>
</head>
<body>
    <div class="app-shell">
        <header class="app-header">
            <span class="app-header__mark" aria-hidden="true">🐔</span>
            <span class="app-header__mark">Shamba Track</span>
        </header>

        <main class="app-main">
            <div id="status-banner" class="status-banner" role="status"></div>

            <div id="progress-dots" class="progress-dots hidden" aria-hidden="true">
                <span class="progress-dot" data-step="login"></span>
                <span class="progress-dot" data-step="otp"></span>
                <span class="progress-dot" data-step="farm-setup"></span>
            </div>

            <section id="screen-loading" class="screen">
                <div class="loading-shell">
                    <span class="spinner" aria-hidden="true"></span>
                    <span>Inapakia... / Loading...</span>
                </div>
            </section>

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

            <section id="screen-dashboard" class="screen hidden">
                <div class="dashboard-greeting">
                    <span class="intro-icon" aria-hidden="true">🌾</span>
                    <h1>Karibu, <span id="dashboard-farm-name"></span></h1>
                </div>

                <button type="button" class="btn-primary" id="btn-open-daily-log" style="margin-top:0;">
                    <span class="btn-label">📋 Rekodi ya Leo / Daily Log</span>
                </button>

                <div class="card">
                    <div class="dashboard-section-header">
                        <h2>Makundi ya Kuku / Batches</h2>
                        <button type="button" class="btn-small" id="btn-add-batch">+ Ongeza / Add</button>
                    </div>
                    <ul class="data-list" id="dashboard-batches-list"></ul>
                </div>

                <div class="card">
                    <div class="dashboard-section-header">
                        <h2>Gharama za Miundombinu / Infrastructure</h2>
                        <button type="button" class="btn-small" id="btn-add-infrastructure">+ Ongeza / Add</button>
                    </div>
                    <ul class="data-list" id="dashboard-infra-list"></ul>
                </div>

                <button type="button" class="btn-link" id="btn-logout">Toka / Log out</button>
            </section>

            <!-- Add batch -->
            <section id="screen-add-batch" class="screen hidden">
                <div class="intro-block">
                    <h2>Ongeza Kundi la Kuku</h2>
                    <p class="subtext">Add a poultry batch</p>
                </div>
                <div class="card">
                    <form id="form-add-batch" novalidate>
                        <label class="form-label">Aina ya Kuku / Breed</label>
                        <div class="tap-grid" id="breed-picker">
                            <button type="button" class="breed-option" data-breed="layers">🥚<span>Layers</span></button>
                            <button type="button" class="breed-option" data-breed="broilers">🍗<span>Broilers</span></button>
                            <button type="button" class="breed-option" data-breed="kienyeji">🐓<span>Kienyeji Bora</span></button>
                            <button type="button" class="breed-option" data-breed="sasso">🐔<span>Sasso</span></button>
                        </div>
                        <input type="hidden" id="input-batch-breed">

                        <label class="form-label" for="input-batch-quantity">Idadi ya Kuku / Quantity</label>
                        <input type="number" id="input-batch-quantity" class="text-input" inputmode="numeric" min="1" required>

                        <label class="form-label" for="input-batch-date">Tarehe ya Kupata / Date acquired</label>
                        <input type="date" id="input-batch-date" class="text-input" required>

                        <label class="form-label" for="input-batch-cost">Gharama kwa Kila Kuku / Cost per bird</label>
                        <input type="number" id="input-batch-cost" class="text-input" inputmode="decimal" min="0" step="0.01">

                        <label class="form-label" for="input-batch-source">Chanzo / Source (optional)</label>
                        <input type="text" id="input-batch-source" class="text-input" placeholder="e.g. Hatchery name">

                        <label class="form-label" for="input-batch-notes">Maelezo / Notes (optional)</label>
                        <input type="text" id="input-batch-notes" class="text-input">

                        <p class="field-error" id="error-add-batch"></p>
                        <button type="submit" class="btn-primary">
                            <span class="spinner" aria-hidden="true"></span>
                            <span class="btn-label">Hifadhi / Save</span>
                        </button>
                        <button type="button" class="btn-link" data-back-to-dashboard>Ghairi / Cancel</button>
                    </form>
                </div>
            </section>

            <!-- Add infrastructure cost -->
            <section id="screen-add-infrastructure" class="screen hidden">
                <div class="intro-block">
                    <h2>Ongeza Gharama</h2>
                    <p class="subtext">Add an infrastructure or equipment cost</p>
                </div>
                <div class="card">
                    <form id="form-add-infrastructure" novalidate>
                        <label class="form-label">Aina / Category</label>
                        <div class="tap-grid" id="category-picker">
                            <button type="button" class="category-option" data-category="coop">🏚️<span>Banda / Coop</span></button>
                            <button type="button" class="category-option" data-category="land">🌍<span>Shamba / Land</span></button>
                            <button type="button" class="category-option" data-category="equipment">🧰<span>Vifaa / Equipment</span></button>
                        </div>
                        <input type="hidden" id="input-infra-category">

                        <div id="land-status-field" class="hidden">
                            <label class="form-label">Umenunua au Kupanga? / Bought or Rented?</label>
                            <select id="input-infra-land-status" class="text-input">
                                <option value="">—</option>
                                <option value="bought">Nimenunua / Bought</option>
                                <option value="rented">Nimepanga / Rented</option>
                            </select>
                        </div>

                        <label class="form-label" for="input-infra-name">Jina la Kipengele / Item name</label>
                        <input type="text" id="input-infra-name" class="text-input" placeholder="e.g. Feeders x10" required>

                        <label class="form-label" for="input-infra-amount">Kiasi / Amount</label>
                        <input type="number" id="input-infra-amount" class="text-input" inputmode="decimal" min="0" step="0.01" required>

                        <label class="form-label" for="input-infra-date">Tarehe / Date</label>
                        <input type="date" id="input-infra-date" class="text-input" required>

                        <label class="form-label" for="input-infra-notes">Maelezo / Notes (optional)</label>
                        <input type="text" id="input-infra-notes" class="text-input">

                        <p class="field-error" id="error-add-infrastructure"></p>
                        <button type="submit" class="btn-primary">
                            <span class="spinner" aria-hidden="true"></span>
                            <span class="btn-label">Hifadhi / Save</span>
                        </button>
                        <button type="button" class="btn-link" data-back-to-dashboard>Ghairi / Cancel</button>
                    </form>
                </div>
            </section>
            <!-- Daily log hub -->
            <section id="screen-daily-log" class="screen hidden">
                <div class="intro-block">
                    <h2>Rekodi ya Leo</h2>
                    <p class="subtext">Daily Log</p>
                </div>

                <div class="tap-grid log-hub-grid">
                    <button type="button" class="breed-option" data-open-log="feed-purchase">🌾<span>Chakula Kununuliwa / Feed Bought</span></button>
                    <button type="button" class="breed-option" data-open-log="feed-consumption">🍽️<span>Chakula Kutumika / Feed Used</span></button>
                    <button type="button" class="breed-option" data-open-log="eggs">🥚<span>Mayai / Eggs</span></button>
                    <button type="button" class="breed-option" data-open-log="mortality">⚠️<span>Vifo / Mortality</span></button>
                    <button type="button" class="breed-option" data-open-log="cost">💵<span>Gharama Nyingine / Other Costs</span></button>
                </div>

                <div class="card">
                    <h2 style="font-size:1.05rem;">Shughuli za Hivi Karibuni / Recent Activity</h2>
                    <ul class="data-list" id="recent-activity-list"></ul>
                </div>

                <button type="button" class="btn-link" data-back-to-dashboard>Rudi / Back to dashboard</button>
            </section>

            <!-- Log: feed purchase -->
            <section id="screen-log-feed-purchase" class="screen hidden">
                <div class="intro-block"><h2>Chakula Kilichonunuliwa</h2><p class="subtext">Log a feed purchase</p></div>
                <div class="card">
                    <form id="form-log-feed-purchase" novalidate>
                        <label class="form-label">Chanzo / Source</label>
                        <div class="tap-grid two-col">
                            <button type="button" class="mini-option" data-field="source" data-value="bought">Nimenunua / Bought</button>
                            <button type="button" class="mini-option" data-field="source" data-value="home_made">Nimetengeneza / Home-made</button>
                        </div>
                        <input type="hidden" id="input-feedpurchase-source" value="bought">

                        <label class="form-label">Aina ya Chakula / Feed type</label>
                        <div class="tap-grid two-col">
                            <button type="button" class="mini-option" data-field="feed_type" data-value="starter">Starter</button>
                            <button type="button" class="mini-option" data-field="feed_type" data-value="grower">Grower</button>
                            <button type="button" class="mini-option" data-field="feed_type" data-value="layers_mash">Layers Mash</button>
                            <button type="button" class="mini-option" data-field="feed_type" data-value="other">Nyingine / Other</button>
                        </div>
                        <input type="hidden" id="input-feedpurchase-feedtype">

                        <label class="form-label" for="input-feedpurchase-batch">Kundi / Batch (optional)</label>
                        <select id="input-feedpurchase-batch" class="text-input batch-select"><option value="">Yote / All batches</option></select>

                        <label class="form-label" for="input-feedpurchase-qty">Kiasi (kg) / Quantity (kg)</label>
                        <input type="number" id="input-feedpurchase-qty" class="text-input" inputmode="decimal" min="0.1" step="0.1" required>

                        <label class="form-label" for="input-feedpurchase-cost">Gharama Jumla / Total cost</label>
                        <input type="number" id="input-feedpurchase-cost" class="text-input" inputmode="decimal" min="0" step="0.01" required>

                        <label class="form-label" for="input-feedpurchase-date">Tarehe / Date</label>
                        <input type="date" id="input-feedpurchase-date" class="text-input" required>

                        <label class="form-label" for="input-feedpurchase-notes">Maelezo / Notes (optional)</label>
                        <input type="text" id="input-feedpurchase-notes" class="text-input">

                        <p class="field-error" id="error-log-feed-purchase"></p>
                        <button type="submit" class="btn-primary"><span class="spinner" aria-hidden="true"></span><span class="btn-label">Hifadhi / Save</span></button>
                        <button type="button" class="btn-link" data-back-to-daily-log>Ghairi / Cancel</button>
                    </form>
                </div>
            </section>

            <!-- Log: feed consumption -->
            <section id="screen-log-feed-consumption" class="screen hidden">
                <div class="intro-block"><h2>Chakula Kilichotumika</h2><p class="subtext">Log feed consumption</p></div>
                <div class="card">
                    <form id="form-log-feed-consumption" novalidate>
                        <label class="form-label" for="input-feedcons-batch">Kundi / Batch</label>
                        <select id="input-feedcons-batch" class="text-input batch-select" required><option value="">Chagua kundi / Select a batch</option></select>

                        <label class="form-label" for="input-feedcons-qty">Kiasi Kilichotumika (kg) / Quantity consumed (kg)</label>
                        <input type="number" id="input-feedcons-qty" class="text-input" inputmode="decimal" min="0.1" step="0.1" required>

                        <label class="form-label" for="input-feedcons-date">Tarehe / Date</label>
                        <input type="date" id="input-feedcons-date" class="text-input" required>

                        <label class="form-label" for="input-feedcons-notes">Maelezo / Notes (optional)</label>
                        <input type="text" id="input-feedcons-notes" class="text-input">

                        <p class="field-error" id="error-log-feed-consumption"></p>
                        <button type="submit" class="btn-primary"><span class="spinner" aria-hidden="true"></span><span class="btn-label">Hifadhi / Save</span></button>
                        <button type="button" class="btn-link" data-back-to-daily-log>Ghairi / Cancel</button>
                    </form>
                </div>
            </section>

            <!-- Log: eggs -->
            <section id="screen-log-eggs" class="screen hidden">
                <div class="intro-block"><h2>Mayai</h2><p class="subtext">Log egg production</p></div>
                <div class="card">
                    <form id="form-log-eggs" novalidate>
                        <label class="form-label" for="input-eggs-batch">Kundi / Batch</label>
                        <select id="input-eggs-batch" class="text-input batch-select" required><option value="">Chagua kundi / Select a batch</option></select>

                        <label class="form-label" for="input-eggs-whole">Mayai Mazima / Whole eggs</label>
                        <input type="number" id="input-eggs-whole" class="text-input" inputmode="numeric" min="0" value="0" required>

                        <label class="form-label" for="input-eggs-broken">Mayai Yaliyovunjika / Broken eggs</label>
                        <input type="number" id="input-eggs-broken" class="text-input" inputmode="numeric" min="0" value="0">

                        <label class="form-label" for="input-eggs-date">Tarehe / Date</label>
                        <input type="date" id="input-eggs-date" class="text-input" required>

                        <label class="form-label" for="input-eggs-notes">Maelezo / Notes (optional)</label>
                        <input type="text" id="input-eggs-notes" class="text-input">

                        <p class="field-error" id="error-log-eggs"></p>
                        <button type="submit" class="btn-primary"><span class="spinner" aria-hidden="true"></span><span class="btn-label">Hifadhi / Save</span></button>
                        <button type="button" class="btn-link" data-back-to-daily-log>Ghairi / Cancel</button>
                    </form>
                </div>
            </section>

            <!-- Log: mortality -->
            <section id="screen-log-mortality" class="screen hidden">
                <div class="intro-block"><h2>Vifo</h2><p class="subtext">Log mortality</p></div>
                <div class="card">
                    <form id="form-log-mortality" novalidate>
                        <label class="form-label" for="input-mortality-batch">Kundi / Batch</label>
                        <select id="input-mortality-batch" class="text-input batch-select" required><option value="">Chagua kundi / Select a batch</option></select>

                        <label class="form-label" for="input-mortality-qty">Idadi Iliyokufa / Quantity</label>
                        <input type="number" id="input-mortality-qty" class="text-input" inputmode="numeric" min="1" required>

                        <label class="form-label">Sababu / Cause (optional)</label>
                        <div class="tap-grid two-col">
                            <button type="button" class="mini-option" data-field="cause" data-value="Ugonjwa / Disease">Ugonjwa / Disease</button>
                            <button type="button" class="mini-option" data-field="cause" data-value="Mnyama / Predator">Mnyama / Predator</button>
                            <button type="button" class="mini-option" data-field="cause" data-value="Joto / Heat">Joto / Heat</button>
                            <button type="button" class="mini-option" data-field="cause" data-value="Haijulikani / Unknown">Haijulikani / Unknown</button>
                        </div>
                        <input type="hidden" id="input-mortality-cause">

                        <label class="form-label" for="input-mortality-date">Tarehe / Date</label>
                        <input type="date" id="input-mortality-date" class="text-input" required>

                        <label class="form-label" for="input-mortality-notes">Maelezo / Notes (optional)</label>
                        <input type="text" id="input-mortality-notes" class="text-input">

                        <p class="field-error" id="error-log-mortality"></p>
                        <button type="submit" class="btn-primary"><span class="spinner" aria-hidden="true"></span><span class="btn-label">Hifadhi / Save</span></button>
                        <button type="button" class="btn-link" data-back-to-daily-log>Ghairi / Cancel</button>
                    </form>
                </div>
            </section>

            <!-- Log: other costs (labor, utilities, medication, transport) -->
            <section id="screen-log-cost" class="screen hidden">
                <div class="intro-block"><h2>Gharama Nyingine</h2><p class="subtext">Labor, utilities, medication, transport</p></div>
                <div class="card">
                    <form id="form-log-cost" novalidate>
                        <label class="form-label">Aina / Category</label>
                        <div class="tap-grid" id="cost-category-picker">
                            <button type="button" class="category-option" data-category="labor">👷<span>Nguvu Kazi / Labor</span></button>
                            <button type="button" class="category-option" data-category="utilities">💡<span>Huduma / Utilities</span></button>
                            <button type="button" class="category-option" data-category="medication">💊<span>Dawa / Medication</span></button>
                            <button type="button" class="category-option" data-category="transport">🚚<span>Usafiri / Transport</span></button>
                        </div>
                        <input type="hidden" id="input-cost-category">

                        <div id="labor-subtype-field" class="hidden">
                            <label class="form-label">Posho au Mshahara? / Allowance or Salary?</label>
                            <select id="input-cost-subtype" class="text-input">
                                <option value="">—</option>
                                <option value="allowance">Posho / Allowance</option>
                                <option value="salary">Mshahara / Salary</option>
                            </select>
                        </div>

                        <label class="form-label" for="input-cost-label" id="label-cost-label">Maelezo Mafupi / Short label</label>
                        <input type="text" id="input-cost-label" class="text-input" placeholder="e.g. Amporium, Feeders, Water bill" required>

                        <label class="form-label" for="input-cost-batch">Kundi / Batch (optional)</label>
                        <select id="input-cost-batch" class="text-input batch-select"><option value="">Yote / All batches</option></select>

                        <label class="form-label" for="input-cost-amount">Kiasi / Amount</label>
                        <input type="number" id="input-cost-amount" class="text-input" inputmode="decimal" min="0" step="0.01" required>

                        <label class="form-label" for="input-cost-date">Tarehe / Date</label>
                        <input type="date" id="input-cost-date" class="text-input" required>

                        <label class="form-label" for="input-cost-notes">Maelezo Zaidi / Notes (optional)</label>
                        <input type="text" id="input-cost-notes" class="text-input">

                        <p class="field-error" id="error-log-cost"></p>
                        <button type="submit" class="btn-primary"><span class="spinner" aria-hidden="true"></span><span class="btn-label">Hifadhi / Save</span></button>
                        <button type="button" class="btn-link" data-back-to-daily-log>Ghairi / Cancel</button>
                    </form>
                </div>
            </section>
        </main>
    </div>

    <script src="<?= htmlspecialchars($asset('assets/js/app.js')) ?>"></script>
    <script src="<?= htmlspecialchars($asset('assets/js/db.js')) ?>"></script>
    <script src="<?= htmlspecialchars($asset('assets/js/sync.js')) ?>"></script>
    <script src="<?= htmlspecialchars($asset('assets/js/auth.js')) ?>"></script>
    <script src="<?= htmlspecialchars($asset('assets/js/batches.js')) ?>"></script>
    <script src="<?= htmlspecialchars($asset('assets/js/logs.js')) ?>"></script>
</body>
</html>
