<?php

$basePath = $basePath ?? '';
$activeMenu = $activeMenu ?? '';

?>

<aside class="sidebar">

    <div class="brand">

        <div class="brand-icon">
            A&J
        </div>

        <div>

            <div class="brand-name">
                Arnold & Jackie's
            </div>

            <div class="brand-sub">
                Store Management
            </div>

        </div>

    </div>


    <div class="sidebar-content">


        <div class="nav-title">
            Main
        </div>


        <a
            href="<?= $basePath ?>dashboard.php"
            class="side-link <?= $activeMenu === 'dashboard' ? 'active' : '' ?>">

            <span class="side-icon">⌂</span>

            <span>
                Dashboard
            </span>

        </a>


        <a
            href="<?= $basePath ?>pos/"
            class="side-link <?= $activeMenu === 'pos' ? 'active' : '' ?>">

            <span class="side-icon">🛒</span>

            <span>
                POS System
            </span>

        </a>


        <a
            href="<?= $basePath ?>products/"
            class="side-link <?= $activeMenu === 'inventory' ? 'active' : '' ?>">

            <span class="side-icon">▦</span>

            <span>
                Inventory
            </span>

        </a>
        <!-- PISOWIFI -->
        <a
            href="<?= $basePath ?>pisowifi/"
            class="side-link <?= $activeMenu === 'pisowifi' ? 'active' : '' ?>">
            <span class="side-icon">📡</span>
            <span>PisoWiFi</span>
        </a>

        <!-- COMPUTER SHOP -->

        <a
            href="<?= $basePath ?>computer_shop/"
            class="side-link <?= $activeMenu === 'computer_shop' ? 'active' : '' ?>">

            <span class="side-icon">💻</span>

            <span>
                Computer Shop
            </span>

        </a>
        <a
            href="<?= $basePath ?>wallet/"
            class="side-link <?= $activeMenu === 'wallet' ? 'active' : '' ?>">

            <span class="side-icon">₱</span>

            <span>
                GCash / Maya
            </span>

        </a>


        <div class="nav-title">
            Management
        </div>


        <a
            href="<?= $basePath ?>reports/"
            class="side-link <?= $activeMenu === 'reports' ? 'active' : '' ?>">

            <span class="side-icon">▥</span>

            <span>
                Reports
            </span>

        </a>


        <a
            href="<?= $basePath ?>expenses/"
            class="side-link <?= $activeMenu === 'expenses' ? 'active' : '' ?>">

            <span class="side-icon">₱</span>

            <span>
                Expenses
            </span>

        </a>


        <div class="nav-title">
            System
        </div>


        <?php if (($_SESSION['role'] ?? '') === 'admin'): ?>

            <a
                href="<?= $basePath ?>settings/"
                class="side-link <?= $activeMenu === 'settings' ? 'active' : '' ?>">

                <span class="side-icon">⚙</span>

                <span>
                    Settings
                </span>

            </a>

        <?php endif; ?>


        <a
            href="<?= $basePath ?>logout.php"
            class="side-link logout"
            data-logout>

            <span class="side-icon">⇥</span>

            <span>
                Log Out
            </span>

        </a>


    </div>
    <script>
        (function() {

            function initializeLogoutEffect() {

                const logoutLinks = document.querySelectorAll(
                    '.side-link.logout'
                );

                if (!logoutLinks.length) {
                    return;
                }

                logoutLinks.forEach(function(logoutLink) {

                    logoutLink.addEventListener('click', function(event) {

                        event.preventDefault();

                        const logoutUrl = logoutLink.getAttribute('href');

                        if (!logoutUrl) {
                            return;
                        }

                        /*
                        |--------------------------------------------------------------------------
                        | PREVENT DOUBLE CLICK
                        |--------------------------------------------------------------------------
                        */

                        if (logoutLink.dataset.loggingOut === '1') {
                            return;
                        }

                        logoutLink.dataset.loggingOut = '1';

                        /*
                        |--------------------------------------------------------------------------
                        | CREATE LOADING OVERLAY
                        |--------------------------------------------------------------------------
                        */

                        let overlay = document.getElementById(
                            'logoutLoadingOverlay'
                        );

                        if (!overlay) {

                            overlay = document.createElement('div');

                            overlay.id = 'logoutLoadingOverlay';

                            overlay.className =
                                'logout-loading-overlay';

                            overlay.innerHTML = `
                        <div class="logout-loading-box">

                            <div class="logout-spinner"></div>

                            <div class="logout-loading-title">
                                Logging out
                            </div>

                            <div class="logout-loading-text">
                                Please wait...
                            </div>

                        </div>
                    `;

                            document.body.appendChild(overlay);
                        }

                        /*
                        |--------------------------------------------------------------------------
                        | SHOW
                        |--------------------------------------------------------------------------
                        */

                        requestAnimationFrame(function() {

                            requestAnimationFrame(function() {

                                overlay.classList.add('show');

                            });

                        });

                        /*
                        |--------------------------------------------------------------------------
                        | PREVENT SCROLLING
                        |--------------------------------------------------------------------------
                        */

                        document.body.style.overflow = 'hidden';

                        /*
                        |--------------------------------------------------------------------------
                        | GO TO LOGOUT
                        |--------------------------------------------------------------------------
                        */

                        setTimeout(function() {

                            window.location.href = logoutUrl;

                        }, 800);

                    });

                });

            }

            if (document.readyState === 'loading') {

                document.addEventListener(
                    'DOMContentLoaded',
                    initializeLogoutEffect
                );

            } else {

                initializeLogoutEffect();

            }

        })();
    </script>


</aside>