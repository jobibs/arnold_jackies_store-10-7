/*
|--------------------------------------------------------------------------
| ARNOLD & JACKIE'S STORE
| UNIVERSAL ADMIN JAVASCRIPT
|--------------------------------------------------------------------------
| Handles:
| - Notification dropdown
| - Toast notifications
| - Logout loading animation
| - Small global UI helpers
|--------------------------------------------------------------------------
*/

'use strict';


/* =========================================================
   UNIVERSAL NOTIFICATION DROPDOWN
========================================================= */

(function () {

    const notificationButton =
        document.getElementById('notificationButton');

    const notificationDropdown =
        document.getElementById('notificationDropdown');


    if (!notificationButton || !notificationDropdown) {
        return;
    }


    /*
     * Toggle notification dropdown
     */
    notificationButton.addEventListener('click', function (event) {

        event.preventDefault();
        event.stopPropagation();

        notificationDropdown.classList.toggle('show');

        const isOpen =
            notificationDropdown.classList.contains('show');

        notificationButton.setAttribute(
            'aria-expanded',
            isOpen ? 'true' : 'false'
        );

    });


    /*
     * Close when clicking outside
     */
    document.addEventListener('click', function (event) {

        if (
            !notificationDropdown.contains(event.target) &&
            !notificationButton.contains(event.target)
        ) {

            notificationDropdown.classList.remove('show');

            notificationButton.setAttribute(
                'aria-expanded',
                'false'
            );

        }

    });


    /*
     * Prevent dropdown clicks from closing it
     */
    notificationDropdown.addEventListener(
        'click',
        function (event) {

            event.stopPropagation();

        }
    );


    /*
     * Close notification dropdown with ESC
     */
    document.addEventListener('keydown', function (event) {

        if (event.key === 'Escape') {

            notificationDropdown.classList.remove('show');

            notificationButton.setAttribute(
                'aria-expanded',
                'false'
            );

        }

    });

})();


/* =========================================================
   UNIVERSAL TOAST NOTIFICATIONS
========================================================= */

(function () {


    /*
     * Get or create toast container
     */
    function getToastContainer() {

        let container =
            document.getElementById('toastContainer');


        if (!container) {

            container =
                document.createElement('div');

            container.id =
                'toastContainer';

            container.className =
                'toast-container';

            document.body.appendChild(container);

        }


        return container;

    }


    /*
     * Escape HTML
     *
     * Prevents user-generated text from being
     * inserted as HTML.
     */
    function escapeToastHtml(value) {

        const div =
            document.createElement('div');

        div.textContent =
            value ?? '';

        return div.innerHTML;

    }


    /*
     * Remove toast
     */
    function removeToast(toast) {

        if (
            !toast ||
            toast.classList.contains('toast-removing')
        ) {

            return;

        }


        toast.classList.add(
            'toast-removing'
        );


        setTimeout(function () {

            if (
                toast &&
                toast.parentNode
            ) {

                toast.parentNode.removeChild(
                    toast
                );

            }

        }, 300);

    }


    /*
     * Global showToast function
     *
     * Usage:
     *
     * showToast(
     *     'success',
     *     'Success',
     *     'Product added successfully.'
     * );
     */
    window.showToast = function (
        type,
        title,
        message,
        duration = 4000
    ) {


        const container =
            getToastContainer();


        /*
         * Allowed toast types
         */
        const allowedTypes = [
            'success',
            'error',
            'warning',
            'info'
        ];


        if (
            !allowedTypes.includes(type)
        ) {

            type = 'info';

        }


        /*
         * Toast icons
         */
        const icons = {

            success: '✓',
            error: '×',
            warning: '!',
            info: 'i'

        };


        /*
         * Create toast
         */
        const toast =
            document.createElement('div');


        toast.className =
            'toast-notification toast-' + type;


        toast.setAttribute(
            'role',
            'alert'
        );


        toast.setAttribute(
            'aria-live',
            'polite'
        );


        toast.innerHTML = `

            <div class="toast-content">

                <div class="toast-icon">
                    ${icons[type]}
                </div>


                <div class="toast-text">

                    <div class="toast-title">
                        ${escapeToastHtml(title)}
                    </div>

                    <div class="toast-message">
                        ${escapeToastHtml(message)}
                    </div>

                </div>

            </div>


            <button
                type="button"
                class="toast-close"
                aria-label="Close notification">

                ×

            </button>


            <div class="toast-progress"></div>

        `;


        /*
         * Add to container
         */
        container.appendChild(
            toast
        );


        /*
         * Progress bar
         */
        const progress =
            toast.querySelector(
                '.toast-progress'
            );


        if (progress) {

            progress.style.animationDuration =
                duration + 'ms';

        }


        /*
         * Automatic removal
         */
        let removeTimer =
            setTimeout(function () {

                removeToast(toast);

            }, duration);


        /*
         * Close button
         */
        const closeButton =
            toast.querySelector(
                '.toast-close'
            );


        if (closeButton) {

            closeButton.addEventListener(
                'click',
                function () {

                    clearTimeout(
                        removeTimer
                    );

                    removeToast(
                        toast
                    );

                }
            );

        }


        /*
         * Pause timer while hovering
         */
        let remainingTime =
            duration;

        let startTime =
            Date.now();


        toast.addEventListener(
            'mouseenter',
            function () {

                clearTimeout(
                    removeTimer
                );

                remainingTime -=
                    Date.now() - startTime;

            }
        );


        toast.addEventListener(
            'mouseleave',
            function () {

                startTime =
                    Date.now();


                removeTimer =
                    setTimeout(function () {

                        removeToast(
                            toast
                        );

                    }, Math.max(
                        remainingTime,
                        500
                    ));

            }
        );


        return toast;

    };


})();


/* =========================================================
   GLOBAL LOGOUT LOADING EFFECT
========================================================= */

(function () {


    let logoutInProgress =
        false;


    /*
     * Create logout loading screen
     */
    function createLogoutOverlay() {

        let overlay =
            document.getElementById(
                'logoutLoading'
            );


        if (overlay) {
            return overlay;
        }


        overlay =
            document.createElement('div');


        overlay.id =
            'logoutLoading';


        overlay.className =
            'logout-loading';


        overlay.innerHTML = `

            <div class="logout-loading-content">

                <div class="logout-loading-logo">
                    A&J
                </div>

                <div class="logout-loading-spinner"></div>

                <div class="logout-loading-title">
                    Signing you out...
                </div>

                <div class="logout-loading-text">
                    Please wait
                    <span class="logout-loading-dots">
                        <span></span>
                        <span></span>
                        <span></span>
                    </span>
                </div>

            </div>

        `;


        document.body.appendChild(
            overlay
        );


        return overlay;

    }


    /*
     * Handle logout
     */
    document.addEventListener(
        'click',
        function (event) {

            const logoutLink =
                event.target.closest(
                    '[data-logout]'
                );


            if (!logoutLink) {
                return;
            }


            /*
             * Stop normal navigation
             */
            event.preventDefault();


            /*
             * Prevent double-clicking
             */
            if (logoutInProgress) {
                return;
            }


            logoutInProgress =
                true;


            logoutLink.dataset.loggingOut =
                '1';


            /*
             * Get destination
             */
            const logoutUrl =
                logoutLink.href;


            if (!logoutUrl) {

                logoutInProgress =
                    false;

                return;

            }


            /*
             * Create overlay
             */
            const overlay =
                createLogoutOverlay();


            /*
             * Prevent page interaction
             */
            document.body.style.overflow =
                'hidden';


            /*
             * Show animation
             */
            requestAnimationFrame(function () {

                overlay.classList.add(
                    'show'
                );

            });


            /*
             * Navigate after animation
             */
            setTimeout(function () {

                window.location.href =
                    logoutUrl;

            }, 1800);

        }
    );


})();


/* =========================================================
   GLOBAL BUTTON LOADING HELPER
========================================================= */

/*
 * Usage:
 *
 * setButtonLoading(button, true);
 *
 * setButtonLoading(button, false);
 */

window.setButtonLoading =
    function (
        button,
        loading = true
    ) {

        if (!button) {
            return;
        }


        if (loading) {

            /*
             * Store original content
             */
            if (
                !button.dataset.originalContent
            ) {

                button.dataset.originalContent =
                    button.innerHTML;

            }


            button.disabled =
                true;


            button.classList.add(
                'is-loading'
            );


            button.innerHTML = `

                <span
                    class="spinner-border spinner-border-sm"
                    role="status"
                    aria-hidden="true">
                </span>

                <span>
                    Please wait...
                </span>

            `;

        } else {

            button.disabled =
                false;


            button.classList.remove(
                'is-loading'
            );


            if (
                button.dataset.originalContent
            ) {

                button.innerHTML =
                    button.dataset.originalContent;

            }

        }

    };


/* =========================================================
   GLOBAL CONFIRM ACTION
========================================================= */

/*
 * Allows:
 *
 * data-confirm="Are you sure?"
 *
 * Example:
 *
 * <button
 *     data-confirm="Delete this product?"
 * >
 *     Delete
 * </button>
 */

document.addEventListener(
    'click',
    function (event) {

        const element =
            event.target.closest(
                '[data-confirm]'
            );


        if (!element) {
            return;
        }


        const message =
            element.dataset.confirm;


        if (!message) {
            return;
        }


        const confirmed =
            window.confirm(
                message
            );


        if (!confirmed) {

            event.preventDefault();

        }

    }
);


/* =========================================================
   PAGE LOAD ANIMATION
========================================================= */

document.addEventListener(
    'DOMContentLoaded',
    function () {

        document.body.classList.add(
            'page-ready'
        );

    }
);


/* =========================================================
   PREVENT DOUBLE SUBMISSION
========================================================= */

document.addEventListener(
    'submit',
    function (event) {

        const form =
            event.target;


        if (
            !form ||
            form.dataset.noDoubleSubmit === 'true'
        ) {

            return;

        }


        if (
            form.dataset.submitting === 'true'
        ) {

            event.preventDefault();

            return;

        }


        form.dataset.submitting =
            'true';


        /*
         * Re-enable after 10 seconds as a
         * safety fallback for failed requests.
         */
        setTimeout(function () {

            form.dataset.submitting =
                'false';

        }, 10000);

    }
);


/* =========================================================
   SMOOTH PAGE TRANSITION
========================================================= */

document.addEventListener(
    'click',
    function (event) {

        const link =
            event.target.closest(
                'a'
            );


        if (!link) {
            return;
        }


        /*
         * Ignore special links
         */
        if (
            link.target === '_blank' ||
            link.hasAttribute('download') ||
            link.href.startsWith('javascript:') ||
            link.href.startsWith('#') ||
            link.dataset.logout !== undefined
        ) {

            return;

        }


        /*
         * Only same-origin links
         */
        if (
            link.origin !== window.location.origin
        ) {

            return;

        }


        /*
         * Don't interfere with modifier keys
         */
        if (
            event.ctrlKey ||
            event.shiftKey ||
            event.altKey ||
            event.metaKey
        ) {

            return;

        }


        /*
         * Small transition effect
         */
        document.body.classList.add(
            'page-leaving'
        );

    }
);


/* =========================================================
   CONSOLE BRANDING
========================================================= */

console.log(
    '%cArnold & Jackie\'s Store',
    'font-size:18px;font-weight:700;color:#0d6efd;'
);

console.log(
    '%cAdmin interface initialized.',
    'font-size:12px;color:#64748b;'
);