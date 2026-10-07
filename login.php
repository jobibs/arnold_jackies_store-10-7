<?php

require_once 'config/database.php';
require_once 'config/auth.php';

if (!empty($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

$error = '';

$username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = $pdo->prepare("
        SELECT *
        FROM users
        WHERE username = ?
          AND is_active = 1
        LIMIT 1
    ");

    $stmt->execute([$username]);

    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {

        $_SESSION['user_id'] = $user['id'];
        $_SESSION['full_name'] = $user['full_name'];
        $_SESSION['role'] = $user['role'];

        header('Location: dashboard.php');
        exit;
    }

    $error = 'Invalid username or password.';
}

?>

<!doctype html>
<html lang="en">

<head>

    <meta charset="utf-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1">

    <title>
        Login - Arnold & Jackie's Store
    </title>

    <style>
        * {
            box-sizing: border-box;
        }

        html,
        body {
            margin: 0;
            padding: 0;
            width: 100%;
            min-height: 100%;
        }

        body {
            font-family:
                Inter,
                -apple-system,
                BlinkMacSystemFont,
                "Segoe UI",
                Roboto,
                Arial,
                sans-serif;

            background: #f5f7fb;
            color: #172033;
        }


        /* =========================================================
           LOGIN PAGE
        ========================================================= */

        .login-page {

            min-height: 100vh;

            display: flex;
            align-items: center;
            justify-content: center;

            padding: 30px;

            background:
                radial-gradient(circle at 10% 20%,
                    rgba(37, 99, 235, 0.14),
                    transparent 30%),
                radial-gradient(circle at 90% 80%,
                    rgba(29, 78, 216, 0.10),
                    transparent 30%),
                #f5f7fb;
        }


        .login-container {

            width: 100%;
            max-width: 1050px;

            min-height: 620px;

            display: grid;
            grid-template-columns: 1fr 1fr;

            background: #ffffff;

            border-radius: 24px;

            overflow: hidden;

            box-shadow:
                0 25px 70px rgba(15, 23, 42, 0.12),
                0 5px 20px rgba(15, 23, 42, 0.05);
        }


        /* =========================================================
           BRAND PANEL
        ========================================================= */

        .brand-panel {

            position: relative;

            display: flex;
            flex-direction: column;
            justify-content: space-between;

            padding: 50px;

            overflow: hidden;

            background:
                linear-gradient(145deg,
                    #111827 0%,
                    #172554 55%,
                    #1d4ed8 100%);

            color: #ffffff;
        }


        .brand-panel::before {

            content: "";

            position: absolute;

            width: 320px;
            height: 320px;

            border-radius: 50%;

            background: rgba(96, 165, 250, 0.12);

            top: -130px;
            right: -100px;
        }


        .brand-panel::after {

            content: "";

            position: absolute;

            width: 260px;
            height: 260px;

            border-radius: 50%;

            background: rgba(59, 130, 246, 0.10);

            bottom: -130px;
            left: -100px;
        }


        .brand-content,
        .brand-footer {

            position: relative;
            z-index: 2;
        }


        /* =========================================================
           LOGO
        ========================================================= */

        .brand-logo {

            width: 62px;
            height: 62px;

            display: flex;
            align-items: center;
            justify-content: center;

            border-radius: 17px;

            background: rgba(255, 255, 255, 0.12);

            border: 1px solid rgba(255, 255, 255, 0.16);

            color: #ffffff;

            font-size: 20px;
            font-weight: 800;

            letter-spacing: -1px;

            box-shadow:
                0 10px 25px rgba(0, 0, 0, 0.15);

            margin-bottom: 28px;
        }


        .brand-title {

            margin: 0;

            font-size: 34px;

            line-height: 1.15;

            font-weight: 750;

            letter-spacing: -1px;
        }


        .brand-description {

            margin: 18px 0 0;

            max-width: 380px;

            color: #cbd5e1;

            font-size: 14px;

            line-height: 1.7;
        }


        .brand-features {

            margin-top: 38px;

            display: flex;
            flex-direction: column;

            gap: 15px;
        }


        .brand-feature {

            display: flex;
            align-items: center;

            gap: 12px;

            color: #e5e7eb;

            font-size: 13px;
        }


        .feature-icon {

            width: 30px;
            height: 30px;

            display: flex;
            align-items: center;
            justify-content: center;

            border-radius: 8px;

            background: rgba(255, 255, 255, 0.09);

            color: #93c5fd;

            font-size: 13px;
        }


        .brand-footer {

            color: #94a3b8;

            font-size: 11px;
        }


        /* =========================================================
           LOGIN PANEL
        ========================================================= */

        .login-panel {

            display: flex;
            align-items: center;

            padding: 55px;
        }


        .login-content {

            width: 100%;
            max-width: 390px;

            margin: 0 auto;
        }


        .login-heading {

            margin-bottom: 34px;
        }


        .login-heading h1 {

            margin: 0;

            color: #172033;

            font-size: 29px;

            font-weight: 750;

            letter-spacing: -0.6px;
        }


        .login-heading p {

            margin: 8px 0 0;

            color: #6b7280;

            font-size: 13px;

            line-height: 1.5;
        }


        /* =========================================================
           ERROR
        ========================================================= */

        .login-error {

            display: flex;
            align-items: flex-start;

            gap: 10px;

            padding: 12px 13px;

            margin-bottom: 22px;

            border-radius: 10px;

            background: #fef2f2;

            border: 1px solid #fecaca;

            color: #b91c1c;

            font-size: 12px;

            line-height: 1.5;
        }


        .error-icon {

            flex-shrink: 0;

            width: 18px;
            height: 18px;

            display: flex;
            align-items: center;
            justify-content: center;

            border-radius: 50%;

            background: #dc2626;

            color: #ffffff;

            font-size: 11px;

            font-weight: 700;
        }


        /* =========================================================
           FORM
        ========================================================= */

        .form-group {

            margin-bottom: 19px;
        }


        .form-label {

            display: block;

            margin-bottom: 8px;

            color: #374151;

            font-size: 12px;

            font-weight: 650;
        }


        .input-wrapper {

            position: relative;
        }


        .form-input {

            width: 100%;

            height: 48px;

            padding: 0 14px 0 43px;

            border: 1px solid #dbe1ea;

            border-radius: 10px;

            background: #ffffff;

            color: #172033;

            font-family: inherit;

            font-size: 13px;

            outline: none;

            transition:
                border-color .18s ease,
                box-shadow .18s ease,
                background .18s ease;
        }


        .form-input::placeholder {

            color: #9ca3af;
        }


        .form-input:hover {

            border-color: #c5ccd7;
        }


        .form-input:focus {

            border-color: #2563eb;

            background: #ffffff;

            box-shadow:
                0 0 0 3px rgba(37, 99, 235, 0.10);
        }


        .input-icon {

            position: absolute;

            left: 14px;
            top: 50%;

            transform: translateY(-50%);

            width: 18px;
            height: 18px;

            color: #9ca3af;

            pointer-events: none;
        }


        .password-input {

            padding-right: 48px;
        }


        .password-toggle {

            position: absolute;

            right: 10px;
            top: 50%;

            transform: translateY(-50%);

            width: 32px;
            height: 32px;

            display: flex;
            align-items: center;
            justify-content: center;

            border: none;

            border-radius: 7px;

            background: transparent;

            color: #9ca3af;

            cursor: pointer;

            transition: .15s;
        }


        .password-toggle:hover {

            background: #f3f4f6;

            color: #374151;
        }


        /* =========================================================
           LOGIN OPTIONS
        ========================================================= */

        .login-options {

            display: flex;
            align-items: center;
            justify-content: space-between;

            margin-top: 4px;
            margin-bottom: 24px;
        }


        .remember-me {

            display: flex;
            align-items: center;

            gap: 8px;

            color: #6b7280;

            font-size: 12px;

            cursor: pointer;
        }


        .remember-me input {

            width: 15px;
            height: 15px;

            margin: 0;

            accent-color: #2563eb;

            cursor: pointer;
        }


        /* =========================================================
           LOGIN BUTTON
        ========================================================= */

        .login-button {

            width: 100%;

            height: 48px;

            display: flex;
            align-items: center;
            justify-content: center;

            border: none;

            border-radius: 10px;

            background:
                linear-gradient(135deg,
                    #2563eb,
                    #1d4ed8);

            color: #ffffff;

            font-family: inherit;

            font-size: 13px;

            font-weight: 700;

            cursor: pointer;

            box-shadow:
                0 8px 18px rgba(37, 99, 235, 0.20);

            transition:
                transform .15s ease,
                box-shadow .15s ease,
                filter .15s ease;
        }


        .login-button:hover {

            filter: brightness(1.05);

            transform: translateY(-1px);

            box-shadow:
                0 11px 22px rgba(37, 99, 235, 0.25);
        }


        .login-button:active {

            transform: translateY(0);

            box-shadow:
                0 5px 12px rgba(37, 99, 235, 0.18);
        }


        /* =========================================================
           SECURITY NOTE
        ========================================================= */

        .login-note {

            margin-top: 22px;

            text-align: center;

            color: #9ca3af;

            font-size: 11px;

            line-height: 1.5;
        }


        /* =========================================================
           RESPONSIVE
        ========================================================= */
        /* =========================================================
   LOGIN LOADING OVERLAY
========================================================= */

        .login-loading {
            position: fixed;
            inset: 0;
            z-index: 9999;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(15, 23, 42, 0.96);
            opacity: 0;
            visibility: hidden;
            pointer-events: none;
            transition:
                opacity .25s ease,
                visibility .25s ease;
        }

        .login-loading.show {
            opacity: 1;
            visibility: visible;
            pointer-events: all;
        }

        .login-loading-content {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
        }

        .login-loading-logo {
            width: 64px;
            height: 64px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 22px;
            border-radius: 17px;
            background: rgba(255, 255, 255, 0.10);
            border: 1px solid rgba(255, 255, 255, 0.14);
            color: #ffffff;
            font-size: 20px;
            font-weight: 800;
            letter-spacing: -1px;
            animation: loginLogoPulse 1.4s ease-in-out infinite;
        }

        .login-loading-spinner {
            width: 34px;
            height: 34px;
            margin-bottom: 18px;
            border: 3px solid rgba(255, 255, 255, 0.18);
            border-top-color: #60a5fa;
            border-radius: 50%;
            animation: loginSpinner .75s linear infinite;
        }

        .login-loading-title {
            margin: 0;
            color: #ffffff;
            font-size: 15px;
            font-weight: 700;
        }

        .login-loading-text {
            margin: 7px 0 0;
            color: #94a3b8;
            font-size: 11px;
        }

        @keyframes loginSpinner {
            to {
                transform: rotate(360deg);
            }
        }

        @keyframes loginLogoPulse {

            0%,
            100% {
                transform: scale(1);
                opacity: 1;
            }

            50% {
                transform: scale(1.05);
                opacity: .85;
            }
        }

        @media (max-width: 850px) {

            .login-page {

                padding: 20px;
            }


            .login-container {

                max-width: 520px;

                min-height: auto;

                grid-template-columns: 1fr;
            }


            .brand-panel {

                padding: 35px;

                min-height: 300px;
            }


            .brand-description {

                max-width: 500px;
            }


            .brand-features {

                margin-top: 25px;

                display: grid;

                grid-template-columns: 1fr 1fr;
            }


            .brand-footer {

                margin-top: 35px;
            }


            .login-panel {

                padding: 40px 35px;
            }

        }


        @media (max-width: 500px) {

            .login-page {

                padding: 0;
            }


            .login-container {

                min-height: 100vh;

                border-radius: 0;

                box-shadow: none;
            }


            .brand-panel {

                padding: 30px;

                min-height: 280px;
            }


            .brand-title {

                font-size: 28px;
            }


            .brand-features {

                grid-template-columns: 1fr;
            }


            .login-panel {

                padding: 35px 25px;
            }


            .login-heading h1 {

                font-size: 26px;
            }

        }
    </style>

</head>


<body>

    <!-- =========================================================
     LOGIN LOADING OVERLAY
========================================================= -->

    <div
        class="login-loading"
        id="loginLoading">

        <div class="login-loading-content">

            <div class="login-loading-logo">
                A&J
            </div>

            <div class="login-loading-spinner"></div>

            <div class="login-loading-title">
                Signing you in...
            </div>

            <div class="login-loading-text">
                Preparing your store dashboard
            </div>

        </div>

    </div>

    <div class="login-page">

        <div class="login-container">


            <!-- =====================================================
                 BRAND SIDE
            ====================================================== -->

            <section class="brand-panel">

                <div class="brand-content">

                    <div class="brand-logo">
                        A&J
                    </div>


                    <h2 class="brand-title">
                        Arnold & Jackie's
                        <br>
                        Store
                    </h2>


                    <p class="brand-description">
                        A centralized management system for your
                        inventory, sales, expenses, digital wallet
                        transactions, and store operations.
                    </p>


                    <div class="brand-features">

                        <div class="brand-feature">

                            <div class="feature-icon">
                                ✓
                            </div>

                            <span>
                                Inventory management
                            </span>

                        </div>


                        <div class="brand-feature">

                            <div class="feature-icon">
                                ₱
                            </div>

                            <span>
                                Sales & financial tracking
                            </span>

                        </div>


                        <div class="brand-feature">

                            <div class="feature-icon">
                                ▦
                            </div>

                            <span>
                                Reports & store monitoring
                            </span>

                        </div>

                    </div>

                </div>


                <div class="brand-footer">

                    Arnold & Jackie's Store Management System

                </div>

            </section>


            <!-- =====================================================
                 LOGIN SIDE
            ====================================================== -->

            <section class="login-panel">

                <div class="login-content">


                    <div class="login-heading">

                        <h1>
                            Welcome back
                        </h1>

                        <p>
                            Sign in to continue to your store dashboard.
                        </p>

                    </div>


                    <?php if ($error): ?>

                        <div class="login-error">

                            <div class="error-icon">
                                !
                            </div>

                            <div>
                                <?= htmlspecialchars($error) ?>
                            </div>

                        </div>

                    <?php endif; ?>


                    <form
                        method="post"
                        autocomplete="on"
                        id="loginForm">


                        <!-- USERNAME -->

                        <div class="form-group">

                            <label
                                class="form-label"
                                for="username">

                                Username

                            </label>


                            <div class="input-wrapper">

                                <svg
                                    class="input-icon"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="1.8"
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    aria-hidden="true">

                                    <path d="M20 21a8 8 0 0 0-16 0"></path>

                                    <circle
                                        cx="12"
                                        cy="7"
                                        r="4">
                                    </circle>

                                </svg>


                                <input
                                    type="text"
                                    id="username"
                                    name="username"
                                    class="form-input"
                                    placeholder="Enter your username"
                                    value="<?= htmlspecialchars($username) ?>"
                                    autocomplete="username"
                                    required>

                            </div>

                        </div>


                        <!-- PASSWORD -->

                        <div class="form-group">

                            <label
                                class="form-label"
                                for="password">

                                Password

                            </label>


                            <div class="input-wrapper">

                                <svg
                                    class="input-icon"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="1.8"
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    aria-hidden="true">

                                    <rect
                                        x="3"
                                        y="11"
                                        width="18"
                                        height="10"
                                        rx="2">
                                    </rect>

                                    <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>

                                </svg>


                                <input
                                    type="password"
                                    id="password"
                                    name="password"
                                    class="form-input password-input"
                                    placeholder="Enter your password"
                                    autocomplete="current-password"
                                    required>


                                <button
                                    type="button"
                                    class="password-toggle"
                                    id="passwordToggle"
                                    aria-label="Show password">

                                    <svg
                                        id="eyeIcon"
                                        width="18"
                                        height="18"
                                        viewBox="0 0 24 24"
                                        fill="none"
                                        stroke="currentColor"
                                        stroke-width="1.8"
                                        stroke-linecap="round"
                                        stroke-linejoin="round">

                                        <path
                                            d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z">
                                        </path>

                                        <circle
                                            cx="12"
                                            cy="12"
                                            r="3">
                                        </circle>

                                    </svg>

                                </button>

                            </div>

                        </div>


                        <!-- OPTIONS -->

                        <div class="login-options">

                            <label class="remember-me">

                                <input
                                    type="checkbox"
                                    name="remember"
                                    value="1">

                                <span>
                                    Remember me
                                </span>

                            </label>

                        </div>


                        <!-- LOGIN -->

                        <button
                            type="submit"
                            class="login-button">

                            Sign In

                        </button>


                    </form>


                    <div class="login-note">

                        Authorized users only.
                        Your account activity is protected by
                        your login credentials.

                    </div>


                </div>

            </section>

        </div>

    </div>


    <script>
        const passwordInput =
            document.getElementById('password');

        const passwordToggle =
            document.getElementById('passwordToggle');

        const eyeIcon =
            document.getElementById('eyeIcon');


        if (passwordToggle && passwordInput) {

            passwordToggle.addEventListener('click', function() {

                const isPassword =
                    passwordInput.type === 'password';


                passwordInput.type =
                    isPassword ? 'text' : 'password';


                passwordToggle.setAttribute(
                    'aria-label',
                    isPassword ?
                    'Hide password' :
                    'Show password'
                );


                if (isPassword) {

                    eyeIcon.innerHTML = `
                        <path
                            d="M3 3l18 18">
                        </path>

                        <path
                            d="M10.6 10.6a2 2 0 0 0 2.8 2.8">
                        </path>

                        <path
                            d="M9.9 4.2A10.6 10.6 0 0 1 12 4
                               c6.5 0 10 8 10 8
                               a18.7 18.7 0 0 1-3.1 4.2">
                        </path>

                        <path
                            d="M6.1 6.1C3.4 8.2 2 12 2 12
                               s3.5 8 10 8
                               c1.5 0 2.8-.3 4-.8">
                        </path>
                    `;

                } else {

                    eyeIcon.innerHTML = `
                        <path
                            d="M2 12s3.5-7 10-7
                               10 7 10 7-3.5 7-10 7S2 12 2 12z">
                        </path>

                        <circle
                            cx="12"
                            cy="12"
                            r="3">
                        </circle>
                    `;

                }

            });

        }
        const loginForm =
            document.getElementById('loginForm');

        const loginLoading =
            document.getElementById('loginLoading');

        const loginButton =
            document.querySelector('.login-button');


        if (loginForm && loginLoading) {

            loginForm.addEventListener('submit', function(event) {

                /*
                 * Prevent the form from submitting immediately.
                 * This allows the loading animation to be shown first.
                 */
                event.preventDefault();

                loginLoading.classList.add('show');

                if (loginButton) {

                    loginButton.disabled = true;

                    loginButton.innerHTML = `
                <span>Signing in...</span>
            `;

                }

                /*
                 * Wait 1 second before actually submitting.
                 */
                setTimeout(function() {

                    loginForm.submit();

                }, 1000);

            });

        }
    </script>



</body>

</html>