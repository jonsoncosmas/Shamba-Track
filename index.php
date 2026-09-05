<?php
require __DIR__ . '/includes/bootstrap.php';

// Phase 1 will add: check auth_sessions cookie/token -> redirect to
// dashboard if logged in, or to /login if not. For Phase 0 this is a
// static branded shell used to verify the PWA installs and loads offline.
$lang = $_SESSION['lang'] ?? 'sw';
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($lang) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Shamba Track</title>
    <meta name="description" content="Track poultry costs, production and profit — works offline.">
    <meta name="theme-color" content="#2F5233">
    <link rel="manifest" href="/manifest.webmanifest">
    <link rel="apple-touch-icon" href="/assets/icons/icon-192.png">
    <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body>
    <div class="app-shell">
        <header class="app-header">
            <span class="app-header__mark" aria-hidden="true">🐔</span>
            <span class="app-header__mark">Shamba Track</span>
        </header>

        <main class="app-main">
            <div id="status-banner" class="status-banner status-banner--offline" role="status"></div>

            <section class="welcome-card">
                <h1>Karibu Shamba Track</h1>
                <p>
                    Fuatilia gharama, mazao, na faida ya kuku wako — hata bila mtandao.
                </p>
                <p style="font-size:0.9rem; color: var(--color-text-muted);">
                    Track your poultry costs, production, and profit — even without internet.
                </p>
                <button class="btn-primary" type="button" disabled title="Login arrives in Phase 1">
                    Anza / Get Started
                </button>
            </section>
        </main>
    </div>

    <script src="/assets/js/app.js"></script>
</body>
</html>
