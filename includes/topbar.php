<?php

$basePath = $basePath ?? '';

$notifications = $notifications ?? [];

$notificationCount =
    $notificationCount
    ?? count($notifications);


/*
|--------------------------------------------------------------------------
| CURRENT USER
|--------------------------------------------------------------------------
*/

$profileName =
    trim(
        $_SESSION['full_name']
            ?? 'User'
    );

$profileRole =
    trim(
        $_SESSION['role']
            ?? 'staff'
    );


/*
|--------------------------------------------------------------------------
| USER INITIALS
|--------------------------------------------------------------------------
*/

$profileInitials = '';

$nameParts =
    preg_split(
        '/\s+/',
        $profileName,
        -1,
        PREG_SPLIT_NO_EMPTY
    );


if (!empty($nameParts)) {

    /*
    |--------------------------------------------------------------------------
    | FIRST NAME INITIAL
    |--------------------------------------------------------------------------
    */

    $profileInitials =
        strtoupper(
            substr(
                $nameParts[0],
                0,
                1
            )
        );


    /*
    |--------------------------------------------------------------------------
    | LAST NAME INITIAL
    |--------------------------------------------------------------------------
    */

    if (count($nameParts) > 1) {

        $lastPart =
            $nameParts[count($nameParts) - 1];


        $profileInitials .=
            strtoupper(
                substr(
                    $lastPart,
                    0,
                    1
                )
            );
    }
}


if ($profileInitials === '') {

    $profileInitials = 'U';
}


/*
|--------------------------------------------------------------------------
| DISPLAY ROLE
|--------------------------------------------------------------------------
*/

$profileRoleLabel =
    ucfirst(
        strtolower(
            $profileRole
        )
    );

?>

<style>
    /* =========================================================
       TOPBAR PROFILE
    ========================================================== */

    .topbar-profile-wrapper {

        position: relative;

        display: flex;
        align-items: center;

    }


    .topbar-profile-button {

        display: flex;
        align-items: center;

        gap: 10px;

        padding: 5px 7px 5px 6px;

        border: 0;

        border-radius: 12px;

        background: transparent;

        color: inherit;

        cursor: pointer;

        font-family: inherit;

        text-align: left;

        transition:
            background-color .18s ease,
            transform .18s ease;

    }


    .topbar-profile-button:hover {

        background: #f8fafc;

    }


    .topbar-profile-button:active {

        transform: scale(.985);

    }


    /* =========================================================
       PROFILE AVATAR
    ========================================================== */

    .topbar-profile-avatar {

        width: 36px;

        height: 36px;

        flex: 0 0 36px;

        display: inline-flex;

        align-items: center;

        justify-content: center;

        border-radius: 11px;

        background:
            linear-gradient(135deg,
                #2563eb,
                #1d4ed8);

        color: #ffffff;

        font-size: 11px;

        font-weight: 800;

        letter-spacing: .02em;

        box-shadow:
            0 4px 12px rgba(37, 99, 235, .16);

    }


    /* =========================================================
       PROFILE TEXT
    ========================================================== */

    .topbar-profile-info {

        min-width: 0;

        display: flex;

        flex-direction: column;

        gap: 1px;

    }


    .topbar-profile-name {

        max-width: 180px;

        overflow: hidden;

        text-overflow: ellipsis;

        white-space: nowrap;

        color: #1f2937;

        font-size: 12px;

        font-weight: 700;

        line-height: 1.35;

    }


    .topbar-profile-role {

        color: #8b94a3;

        font-size: 10px;

        font-weight: 600;

        line-height: 1.3;

    }


    /* =========================================================
       CHEVRON
    ========================================================== */

    .topbar-profile-chevron {

        width: 18px;

        height: 18px;

        display: inline-flex;

        align-items: center;

        justify-content: center;

        color: #94a3b8;

        transition:
            transform .2s ease,
            color .2s ease;

    }


    .topbar-profile-button[aria-expanded="true"] .topbar-profile-chevron {

        transform: rotate(180deg);

        color: #64748b;

    }


    /* =========================================================
       PROFILE DROPDOWN
    ========================================================== */

    .profile-dropdown {

        position: absolute;

        top: calc(100% + 10px);

        right: 0;

        z-index: 1200;

        width: 285px;

        overflow: hidden;

        border: 1px solid #e5eaf0;

        border-radius: 16px;

        background: #ffffff;

        box-shadow:
            0 18px 45px rgba(15, 23, 42, .11),
            0 4px 14px rgba(15, 23, 42, .05);

        opacity: 0;

        visibility: hidden;

        transform:
            translateY(-7px) scale(.985);

        transform-origin:
            top right;

        pointer-events: none;

        transition:
            opacity .18s ease,
            transform .18s ease,
            visibility .18s ease;

    }


    .profile-dropdown.show {

        opacity: 1;

        visibility: visible;

        transform:
            translateY(0) scale(1);

        pointer-events: auto;

    }


    /* =========================================================
       PROFILE DROPDOWN HEADER
    ========================================================== */

    .profile-dropdown-header {

        padding: 16px;

        border-bottom: 1px solid #edf0f4;

        background:
            linear-gradient(180deg,
                #ffffff 0%,
                #fbfcfe 100%);

    }


    .profile-dropdown-user {

        display: flex;

        align-items: center;

        gap: 11px;

    }


    .profile-dropdown-avatar {

        width: 44px;

        height: 44px;

        flex: 0 0 44px;

        display: inline-flex;

        align-items: center;

        justify-content: center;

        border-radius: 13px;

        background:
            linear-gradient(135deg,
                #2563eb,
                #1d4ed8);

        color: #ffffff;

        font-size: 13px;

        font-weight: 800;

        box-shadow:
            0 5px 14px rgba(37, 99, 235, .17);

    }


    .profile-dropdown-name {

        margin: 0 0 2px;

        color: #202938;

        font-size: 12px;

        font-weight: 750;

        line-height: 1.4;

    }


    .profile-dropdown-role {

        margin: 0;

        display: inline-flex;

        align-items: center;

        gap: 5px;

        color: #8a93a2;

        font-size: 10px;

        font-weight: 600;

        text-transform: capitalize;

    }


    .profile-role-dot {

        width: 5px;

        height: 5px;

        border-radius: 50%;

        background: #22c55e;

    }


    /* =========================================================
       DROPDOWN SECTION
    ========================================================== */

    .profile-dropdown-section {

        padding: 7px;

    }


    .profile-dropdown-label {

        padding: 6px 10px 5px;

        color: #a0a8b5;

        font-size: 8px;

        font-weight: 800;

        letter-spacing: .08em;

        text-transform: uppercase;

    }


    /* =========================================================
       DROPDOWN ITEM
    ========================================================== */

    .profile-dropdown-item {

        width: 100%;

        min-height: 43px;

        display: flex;

        align-items: center;

        gap: 10px;

        padding: 0 10px;

        border: 0;

        border-radius: 10px;

        background: transparent;

        color: #475569;

        text-decoration: none;

        cursor: pointer;

        font-family: inherit;

        font-size: 11px;

        font-weight: 650;

        text-align: left;

        transition:
            background-color .16s ease,
            color .16s ease;

    }


    .profile-dropdown-item:hover {

        background: #f8fafc;

        color: #1f2937;

    }


    .profile-dropdown-item.logout-item:hover {

        background: #fef2f2;

        color: #dc2626;

    }


    /* =========================================================
       ITEM ICON
    ========================================================== */

    .profile-dropdown-icon {

        width: 30px;

        height: 30px;

        flex: 0 0 30px;

        display: inline-flex;

        align-items: center;

        justify-content: center;

        border-radius: 9px;

        background: #f1f5f9;

        color: #64748b;

        font-size: 14px;

        transition:
            background-color .16s ease,
            color .16s ease;

    }


    .profile-dropdown-item:hover .profile-dropdown-icon {

        background: #eff6ff;

        color: #2563eb;

    }


    .profile-dropdown-item.logout-item:hover .profile-dropdown-icon {

        background: #fee2e2;

        color: #dc2626;

    }


    .profile-dropdown-text {

        flex: 1;

        min-width: 0;

    }


    .profile-dropdown-title {

        margin: 0;

        color: inherit;

        font-size: 11px;

        font-weight: 700;

        line-height: 1.3;

    }


    .profile-dropdown-description {

        margin-top: 2px;

        color: #9aa2af;

        font-size: 9px;

        line-height: 1.3;

    }


    /* =========================================================
       RIGHT ARROW
    ========================================================== */

    .profile-dropdown-arrow {

        color: #b4bbc5;

        font-size: 14px;

        transition:
            transform .16s ease,
            color .16s ease;

    }


    .profile-dropdown-item:hover .profile-dropdown-arrow {

        transform: translateX(2px);

        color: #2563eb;

    }


    /* =========================================================
       DIVIDER
    ========================================================== */

    .profile-dropdown-divider {

        height: 1px;

        margin: 3px 7px;

        background: #edf0f4;

    }


    /* =========================================================
       MOBILE
    ========================================================== */

    @media (max-width: 600px) {

        .topbar-profile-info {

            display: none;

        }


        .topbar-profile-button {

            padding: 4px;

        }


        .topbar-profile-chevron {

            display: none;

        }


        .profile-dropdown {

            position: fixed;

            top: 63px;

            right: 12px;

            width: min(285px,
                    calc(100vw - 24px));

        }

    }
</style>


<header class="topbar">

    <div class="topbar-right">


        <!-- =====================================================
             NOTIFICATIONS
        ====================================================== -->

        <div class="notification-wrapper">

            <button
                type="button"
                class="notification-button"
                id="notificationButton"
                aria-label="Notifications"
                aria-expanded="false">

                <span class="notification-icon">

                    <svg
                        width="19"
                        height="19"
                        viewBox="0 0 24 24"
                        fill="none"
                        xmlns="http://www.w3.org/2000/svg">

                        <path
                            d="M18 8C18 4.686 15.314 2 12 2C8.686 2 6 4.686 6 8C6 15 3 15 3 17H21C21 15 18 15 18 8Z"
                            stroke="currentColor"
                            stroke-width="1.8"
                            stroke-linecap="round"
                            stroke-linejoin="round" />

                        <path
                            d="M10 21H14"
                            stroke="currentColor"
                            stroke-width="1.8"
                            stroke-linecap="round" />

                    </svg>

                </span>

            </button>


            <!-- =================================================
                 NOTIFICATION DROPDOWN
            ================================================== -->

            <div
                class="notification-dropdown"
                id="notificationDropdown">

                <div class="notification-header">

                    <div>

                        <strong>
                            Notifications
                        </strong>

                        <div class="notification-header-sub">

                            <?= $notificationCount ?>

                            active alert<?=

                                        $notificationCount == 1
                                            ? ''
                                            : 's'

                                        ?>

                        </div>

                    </div>


                    <?php if ($notificationCount > 0): ?>

                        <span class="notification-status">
                            Needs attention
                        </span>

                    <?php endif; ?>

                </div>


                <div class="notification-list">

                    <?php if ($notificationCount > 0): ?>


                        <?php foreach (
                            $notifications
                            as $notification
                        ): ?>


                            <?php

                            $isOutOfStock =
                                (float)$notification['stock']
                                <= 0;

                            ?>


                            <a
                                href="<?= $basePath ?>products/"
                                class="notification-item">


                                <div class="
                                    notification-alert-icon
                                    <?= $isOutOfStock
                                        ? 'notification-danger'
                                        : 'notification-warning' ?>
                                ">

                                    !

                                </div>


                                <div class="notification-content">

                                    <div class="notification-title">

                                        <?= $isOutOfStock
                                            ? 'Out of Stock'
                                            : 'Low Stock' ?>

                                    </div>


                                    <div class="notification-product">

                                        <?= htmlspecialchars(
                                            $notification['product_name']
                                        ) ?>

                                    </div>


                                    <div class="notification-stock">

                                        Stock:

                                        <strong>

                                            <?= rtrim(
                                                rtrim(
                                                    number_format(
                                                        $notification['stock'],
                                                        3
                                                    ),
                                                    '0'
                                                ),
                                                '.'
                                            ) ?>

                                        </strong>

                                        <?= htmlspecialchars(
                                            $notification['unit']
                                        ) ?>


                                        <span class="notification-divider">
                                            /
                                        </span>


                                        Reorder:

                                        <?= rtrim(
                                            rtrim(
                                                number_format(
                                                    $notification['reorder_level'],
                                                    3
                                                ),
                                                '0'
                                            ),
                                            '.'
                                        ) ?>

                                    </div>

                                </div>


                                <div class="notification-arrow">
                                    ›
                                </div>


                            </a>


                        <?php endforeach; ?>


                    <?php else: ?>


                        <div class="notification-empty">

                            <div class="notification-empty-icon">
                                ✓
                            </div>

                            <strong>
                                You're all caught up
                            </strong>

                            <div>
                                No inventory alerts right now.
                            </div>

                        </div>


                    <?php endif; ?>

                </div>


                <?php if ($notificationCount > 0): ?>

                    <a
                        href="<?= $basePath ?>products/"
                        class="notification-footer">

                        View Inventory

                    </a>

                <?php endif; ?>


            </div>

        </div>


        <!-- =====================================================
             USER PROFILE
        ====================================================== -->

        <div class="topbar-profile-wrapper">


            <button
                type="button"
                class="topbar-profile-button"
                id="profileButton"
                aria-label="Open profile menu"
                aria-expanded="false">


                <span class="topbar-profile-avatar">

                    <?= htmlspecialchars(
                        $profileInitials,
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>

                </span>


                <span class="topbar-profile-info">

                    <span class="topbar-profile-name">

                        <?= htmlspecialchars(
                            $profileName,
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>

                    </span>


                    <span class="topbar-profile-role">

                        <?= htmlspecialchars(
                            $profileRoleLabel,
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>

                    </span>

                </span>


                <span class="topbar-profile-chevron">

                    <svg
                        width="14"
                        height="14"
                        viewBox="0 0 24 24"
                        fill="none"
                        xmlns="http://www.w3.org/2000/svg">

                        <path
                            d="M6 9L12 15L18 9"
                            stroke="currentColor"
                            stroke-width="1.8"
                            stroke-linecap="round"
                            stroke-linejoin="round" />

                    </svg>

                </span>

            </button>


            <!-- =================================================
                 PROFILE DROPDOWN
            ================================================== -->

            <div
                class="profile-dropdown"
                id="profileDropdown">


                <!-- PROFILE HEADER -->

                <div class="profile-dropdown-header">

                    <div class="profile-dropdown-user">


                        <div class="profile-dropdown-avatar">

                            <?= htmlspecialchars(
                                $profileInitials,
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>

                        </div>


                        <div>

                            <p class="profile-dropdown-name">

                                <?= htmlspecialchars(
                                    $profileName,
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>

                            </p>


                            <p class="profile-dropdown-role">

                                <span class="profile-role-dot"></span>

                                <?= htmlspecialchars(
                                    $profileRoleLabel,
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>

                                account

                            </p>

                        </div>


                    </div>

                </div>


                <!-- ACCOUNT -->

                <div class="profile-dropdown-section">


                    <div class="profile-dropdown-label">
                        Account
                    </div>


                    <!-- PROFILE -->

                    <a
                        href="<?= $basePath ?>settings/profile.php"
                        class="profile-dropdown-item">

                        <span class="profile-dropdown-icon">

                            <svg
                                width="15"
                                height="15"
                                viewBox="0 0 24 24"
                                fill="none"
                                xmlns="http://www.w3.org/2000/svg">

                                <circle
                                    cx="12"
                                    cy="8"
                                    r="3"
                                    stroke="currentColor"
                                    stroke-width="1.8" />

                                <path
                                    d="M5 20C5.8 16.8 8.2 15 12 15C15.8 15 18.2 16.8 19 20"
                                    stroke="currentColor"
                                    stroke-width="1.8"
                                    stroke-linecap="round" />

                            </svg>

                        </span>


                        <span class="profile-dropdown-text">

                            <span class="profile-dropdown-title">
                                My Profile
                            </span>

                            <span class="profile-dropdown-description">
                                View your account information
                            </span>

                        </span>


                        <span class="profile-dropdown-arrow">
                            ›
                        </span>

                    </a>


                    <!-- SETTINGS -->

                    <a
                        href="<?= $basePath ?>settings/"
                        class="profile-dropdown-item">


                        <span class="profile-dropdown-icon">

                            <svg
                                width="15"
                                height="15"
                                viewBox="0 0 24 24"
                                fill="none"
                                xmlns="http://www.w3.org/2000/svg">

                                <path
                                    d="M12 3V5"
                                    stroke="currentColor"
                                    stroke-width="1.8"
                                    stroke-linecap="round" />

                                <path
                                    d="M12 19V21"
                                    stroke="currentColor"
                                    stroke-width="1.8"
                                    stroke-linecap="round" />

                                <path
                                    d="M3 12H5"
                                    stroke="currentColor"
                                    stroke-width="1.8"
                                    stroke-linecap="round" />

                                <path
                                    d="M19 12H21"
                                    stroke="currentColor"
                                    stroke-width="1.8"
                                    stroke-linecap="round" />

                                <circle
                                    cx="12"
                                    cy="12"
                                    r="5"
                                    stroke="currentColor"
                                    stroke-width="1.8" />

                            </svg>

                        </span>


                        <span class="profile-dropdown-text">

                            <span class="profile-dropdown-title">
                                Settings
                            </span>

                            <span class="profile-dropdown-description">
                                Manage store and system preferences
                            </span>

                        </span>


                        <span class="profile-dropdown-arrow">
                            ›
                        </span>


                    </a>


                </div>


                <div class="profile-dropdown-divider"></div>


                <!-- ACCOUNT STATUS -->

                <div class="profile-dropdown-section">


                    <div class="profile-dropdown-label">
                        Session
                    </div>


                    <div
                        class="profile-dropdown-item"
                        style="cursor:default;">


                        <span
                            class="profile-dropdown-icon"
                            style="
                                background:#ecfdf5;
                                color:#16a34a;
                            ">

                            ✓

                        </span>


                        <span class="profile-dropdown-text">

                            <span class="profile-dropdown-title">
                                Account Active
                            </span>

                            <span class="profile-dropdown-description">
                                You are currently signed in
                            </span>

                        </span>


                    </div>


                </div>


                <div class="profile-dropdown-divider"></div>


                <!-- LOG OUT -->

                <div class="profile-dropdown-section">


                    <a
                        href="<?= $basePath ?>logout.php"
                        class="profile-dropdown-item logout-item"
                        id="profileLogoutLink"
                        data-logout>


                        <span class="profile-dropdown-icon">

                            ⇥

                        </span>


                        <span class="profile-dropdown-text">

                            <span class="profile-dropdown-title">
                                Log Out
                            </span>

                            <span class="profile-dropdown-description">
                                End your current session
                            </span>

                        </span>


                        <span class="profile-dropdown-arrow">
                            ›
                        </span>


                    </a>


                </div>


            </div>


        </div>


    </div>

</header>


<!-- =========================================================
     UNIVERSAL TOPBAR JAVASCRIPT
========================================================= -->

<script>
    (function() {


        /*
        |--------------------------------------------------------------------------
        | ELEMENTS
        |--------------------------------------------------------------------------
        */

        const notificationButton =
            document.getElementById(
                'notificationButton'
            );


        const notificationDropdown =
            document.getElementById(
                'notificationDropdown'
            );


        const profileButton =
            document.getElementById(
                'profileButton'
            );


        const profileDropdown =
            document.getElementById(
                'profileDropdown'
            );


        const profileAccountButton =
            document.getElementById(
                'profileAccountButton'
            );


        const profileLogoutLink =
            document.getElementById(
                'profileLogoutLink'
            );


        /*
        |--------------------------------------------------------------------------
        | CLOSE NOTIFICATIONS
        |--------------------------------------------------------------------------
        */

        function closeNotifications() {

            if (
                notificationDropdown
            ) {

                notificationDropdown.classList.remove(
                    'show'
                );

            }


            if (
                notificationButton
            ) {

                notificationButton.setAttribute(
                    'aria-expanded',
                    'false'
                );

            }

        }


        /*
        |--------------------------------------------------------------------------
        | CLOSE PROFILE
        |--------------------------------------------------------------------------
        */

        function closeProfile() {

            if (
                profileDropdown
            ) {

                profileDropdown.classList.remove(
                    'show'
                );

            }


            if (
                profileButton
            ) {

                profileButton.setAttribute(
                    'aria-expanded',
                    'false'
                );

            }

        }


        /*
        |--------------------------------------------------------------------------
        | TOGGLE NOTIFICATIONS
        |--------------------------------------------------------------------------
        */

        if (
            notificationButton &&
            notificationDropdown
        ) {

            notificationButton.addEventListener(
                'click',
                function(event) {

                    event.preventDefault();

                    event.stopPropagation();


                    const isOpen =
                        notificationDropdown.classList.contains(
                            'show'
                        );


                    closeProfile();


                    if (isOpen) {

                        closeNotifications();

                    } else {

                        notificationDropdown.classList.add(
                            'show'
                        );


                        notificationButton.setAttribute(
                            'aria-expanded',
                            'true'
                        );

                    }

                }
            );


            notificationDropdown.addEventListener(
                'click',
                function(event) {

                    event.stopPropagation();

                }
            );

        }


        /*
        |--------------------------------------------------------------------------
        | TOGGLE PROFILE
        |--------------------------------------------------------------------------
        */

        if (
            profileButton &&
            profileDropdown
        ) {

            profileButton.addEventListener(
                'click',
                function(event) {

                    event.preventDefault();

                    event.stopPropagation();


                    const isOpen =
                        profileDropdown.classList.contains(
                            'show'
                        );


                    closeNotifications();


                    if (isOpen) {

                        closeProfile();

                    } else {

                        profileDropdown.classList.add(
                            'show'
                        );


                        profileButton.setAttribute(
                            'aria-expanded',
                            'true'
                        );

                    }

                }
            );


            profileDropdown.addEventListener(
                'click',
                function(event) {

                    event.stopPropagation();

                }
            );

        }


        /*
        |--------------------------------------------------------------------------
        | CLOSE WHEN CLICKING OUTSIDE
        |--------------------------------------------------------------------------
        */

        document.addEventListener(
            'click',
            function(event) {

                if (

                    notificationDropdown &&
                    notificationButton &&
                    !notificationDropdown.contains(
                        event.target
                    ) &&
                    !notificationButton.contains(
                        event.target
                    )

                ) {

                    closeNotifications();

                }


                if (

                    profileDropdown &&
                    profileButton &&
                    !profileDropdown.contains(
                        event.target
                    ) &&
                    !profileButton.contains(
                        event.target
                    )

                ) {

                    closeProfile();

                }

            }
        );


        /*
        |--------------------------------------------------------------------------
        | ESC KEY
        |--------------------------------------------------------------------------
        */

        document.addEventListener(
            'keydown',
            function(event) {

                if (
                    event.key === 'Escape'
                ) {

                    closeNotifications();

                    closeProfile();

                }

            }
        );


        /*
        |--------------------------------------------------------------------------
        | MY PROFILE BUTTON
        |--------------------------------------------------------------------------
        |
        | We don't have a dedicated profile.php yet.
        | For now, keep it as an account-information action.
        |
        */

        if (
            profileAccountButton
        ) {

            profileAccountButton.addEventListener(
                'click',
                function() {

                    if (
                        typeof window.showToast ===
                        'function'
                    ) {

                        window.showToast(
                            'info',
                            'Profile',
                            'Your account is currently active as ' +
                            <?= json_encode(
                                $profileRoleLabel
                            ) ?> +
                            '.',
                            3000
                        );

                    }

                    closeProfile();

                }
            );

        }


        /*
        |--------------------------------------------------------------------------
        | PROFILE LOGOUT
        |--------------------------------------------------------------------------
        */

        if (
            profileLogoutLink
        ) {

            profileLogoutLink.addEventListener(
                'click',
                function(event) {

                    event.preventDefault();


                    const logoutUrl =
                        profileLogoutLink.getAttribute(
                            'href'
                        );


                    if (!logoutUrl) {

                        return;

                    }


                    if (
                        profileLogoutLink.dataset.loggingOut ===
                        '1'
                    ) {

                        return;

                    }


                    profileLogoutLink.dataset.loggingOut =
                        '1';


                    /*
                    |--------------------------------------------------------------------------
                    | CREATE LOGOUT OVERLAY
                    |--------------------------------------------------------------------------
                    */

                    let overlay =
                        document.getElementById(
                            'logoutLoadingOverlay'
                        );


                    if (!overlay) {

                        overlay =
                            document.createElement(
                                'div'
                            );


                        overlay.id =
                            'logoutLoadingOverlay';


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


                        document.body.appendChild(
                            overlay
                        );

                    }


                    /*
                    |--------------------------------------------------------------------------
                    | SHOW OVERLAY
                    |--------------------------------------------------------------------------
                    */

                    requestAnimationFrame(
                        function() {

                            requestAnimationFrame(
                                function() {

                                    overlay.classList.add(
                                        'show'
                                    );

                                }
                            );

                        }
                    );


                    /*
                    |--------------------------------------------------------------------------
                    | PREVENT SCROLL
                    |--------------------------------------------------------------------------
                    */

                    document.body.style.overflow =
                        'hidden';


                    /*
                    |--------------------------------------------------------------------------
                    | REDIRECT
                    |--------------------------------------------------------------------------
                    */

                    setTimeout(
                        function() {

                            window.location.href =
                                logoutUrl;

                        },
                        800
                    );

                }
            );

        }


    })();
</script>


<!-- =========================================================
     UNIVERSAL TOAST NOTIFICATION CONTAINER
========================================================= -->

<div
    id="toastContainer"
    class="toast-container">
</div>