/*
 * "Add to your desktop" - a small prompt, shown once, that stays away after.
 *
 * Three rules shaped this, and each of them is a reaction to how these prompts
 * usually go wrong:
 *
 *  - It only appears when the browser says the app can actually be installed.
 *    beforeinstallprompt fires for that reason and nothing else; without it the
 *    prompt would be a dead button, so nothing is drawn.
 *  - It asks once per login, not once per page. The dismissal is stored against
 *    the signed-in account, not the browser, so a second person using the same
 *    machine is still offered it, and the first is not asked again every week.
 *  - It is dismissible and stays dismissed. An install prompt that reappears is
 *    the fastest way to make someone distrust the rest of the page.
 *
 * Deliberately not a modal: it is a strip in the corner, it never blocks a
 * click, and it never dims anything. Installing is optional, so it must not
 * behave like something the user has to deal with.
 */
(function () {
    'use strict';

    var root = document.getElementById('adomin-install');
    if (!root) { return; }

    /*
     * Storage key. Scoped to the account so a shared machine offers it to each
     * person once, and to the prompt version so a future redesign can ask again
     * without touching what an old dismissal recorded.
     */
    var user = root.getAttribute('data-user') || 'anon';
    var KEY = 'adomin-install-dismissed:v1:' + user;

    var deferred = null;

    function dismissed() {
        try { return window.localStorage.getItem(KEY) === '1'; } catch (err) { return false; }
    }

    function remember() {
        try { window.localStorage.setItem(KEY, '1'); } catch (err) {}
    }

    function markInstalled() {
        // iOS has no prompt but can be run from the home screen; tell them how
        // rather than showing a button that cannot fire
        if (window.matchMedia && window.matchMedia('(display-mode: standalone)').matches) {
            remember();
            return true;
        }
        return false;
    }

    function build(prompt) {
        root.innerHTML = '';
        root.hidden = false;

        var text = document.createElement('p');
        text.className = 'adomin-install-text';
        text.textContent = root.getAttribute('data-text') || 'Add this app to your desktop for one-click access.';

        var add = document.createElement('button');
        add.type = 'button';
        add.className = 'adomin-install-add';
        add.textContent = root.getAttribute('data-add') || 'Add';

        var no = document.createElement('button');
        no.type = 'button';
        no.className = 'adomin-install-later';
        no.textContent = root.getAttribute('data-later') || 'Not now';
        no.setAttribute('aria-label', root.getAttribute('data-later') || 'Not now');

        add.addEventListener('click', function () {
            root.hidden = true;
            remember();
            if (!prompt) { return; }
            prompt.prompt();
            /*
             * The outcome is deliberately not reported back to the user. A
             * "user declined" state is private to them and a "dismissed" toast
             * is just noise; the browser already showed its own dialog.
             */
            if (prompt.userChoice && typeof prompt.userChoice.then === 'function') {
                prompt.userChoice.then(function () { deferred = null; });
            }
        });

        no.addEventListener('click', function () {
            root.hidden = true;
            remember();
        });

        root.appendChild(text);
        root.appendChild(add);
        root.appendChild(no);
    }

    if (markInstalled() || dismissed()) { return; }

    window.addEventListener('beforeinstallprompt', function (e) {
        // keep the event so the prompt can be shown on our own button, at a
        // moment the user chose rather than whenever the browser decided
        e.preventDefault();
        deferred = e;
        build(deferred);
    });

    window.addEventListener('appinstalled', function () {
        remember();
        root.hidden = true;
    });

    /*
     * Safari on iOS never fires beforeinstallprompt. Rather than a dead button it
     * shows the one instruction that works there - Share, then "Add to Home
     * Screen" - and only on iOS, and only in a browser rather than the installed
     * app.
     */
    var ua = navigator.userAgent || '';
    var isIos = /iPad|iPhone|iPod/.test(ua) && !window.MSStream;
    var isStandalone = window.matchMedia && window.matchMedia('(display-mode: standalone)').matches;
    if (isIos && !isStandalone && !dismissed()) {
        root.setAttribute('data-text',
            root.getAttribute('data-text-ios') || 'Add to Home Screen: tap Share, then "Add to Home Screen".');
        root.hidden = false;
        root.innerHTML = '';
        var tip = document.createElement('p');
        tip.className = 'adomin-install-text';
        tip.textContent = root.getAttribute('data-text');
        var close = document.createElement('button');
        close.type = 'button';
        close.className = 'adomin-install-later';
        close.textContent = root.getAttribute('data-later') || 'Not now';
        close.addEventListener('click', function () { root.hidden = true; remember(); });
        root.appendChild(tip);
        root.appendChild(close);
    }
})();
