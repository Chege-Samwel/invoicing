/*
  --------------------------------------------------------------------------
  Adomin Technologies — UI runtime
  --------------------------------------------------------------------------
  Small, dependency free (jQuery is used only when present). Four jobs:

    1. motion     - reveal on scroll, count up report numbers, ripple on
                    buttons, animated toasts, page transition veil
    2. share link - the "Send via link" modal: fetch, copy, WhatsApp/SMS/mail
    3. PWA        - manifest + service worker registration (offline shell)
    4. cold start - Render free tier sleeps; we detect the wake-up and reload
                    once instead of showing a broken page

  Toggled server side: ADOMIN_ANIMATIONS=0 removes the body class, and the
  stylesheet itself also honours prefers-reduced-motion.
  --------------------------------------------------------------------------
*/
(function () {
    'use strict';

    var doc = document;
    var win = window;
    var REDUCED = false;
    try {
        REDUCED = !!(win.matchMedia && win.matchMedia('(prefers-reduced-motion: reduce)').matches);
    } catch (e) { REDUCED = false; }

    var motion = doc.body && doc.body.className.indexOf('adomin-motion') !== -1;

    /* ---------------------------------------------------------------- *
     * 1. helpers
     * ---------------------------------------------------------------- */
    function on(el, type, fn, opts) {
        if (el) { el.addEventListener(type, fn, opts || false); }
    }
    function all(sel, ctx) {
        return Array.prototype.slice.call((ctx || doc).querySelectorAll(sel));
    }
    function each(sel, fn) { all(sel).forEach(fn); }

    /* ---------------------------------------------------------------- *
     * 2. motion
     * ---------------------------------------------------------------- */
    function initReveal() {
        var targets = all('.invoice-box, .table-container table, .panel, .chart-container, .action-history, .store-box, .selected-page + .inner-page-menu');
        if (!targets.length) { return; }
        targets.forEach(function (el) { el.classList.add('adomin-reveal'); });

        if (!('IntersectionObserver' in win) || REDUCED) {
            targets.forEach(function (el) { el.classList.add('adomin-reveal--in'); });
            return;
        }
        var io = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (entry.isIntersecting) {
                    entry.target.classList.add('adomin-reveal--in');
                    io.unobserve(entry.target);
                }
            });
        }, { rootMargin: '0px 0px -8% 0px', threshold: 0.05 });
        targets.forEach(function (el) { io.observe(el); });
    }

    function initCountUp() {
        if (REDUCED || !motion) { return; }
        each('[data-adomin-count], .counter-num, .info-num', function (el) {
            var raw = (el.textContent || '').trim();
            var match = raw.match(/^([^\d-]*)(-?\d[\d .,]*)(.*)$/);
            if (!match) { return; }
            var prefix = match[1], suffix = match[3], body = match[2].replace(/[ .](?=\d{3}\b)/g, '').replace(',', '.');
            var target = parseFloat(body);
            if (isNaN(target)) { return; }
            var decimals = (match[2].split('.')[1] || '').length;
            var start = null, dur = 520;
            el.classList.add('adomin-count');
            function step(ts) {
                if (start === null) { start = ts; }
                var p = Math.min(1, (ts - start) / dur);
                var eased = 1 - Math.pow(1 - p, 3);
                el.textContent = prefix + (target * eased).toFixed(decimals) + suffix;
                if (p < 1) { win.requestAnimationFrame(step); }
                else { el.textContent = raw; }
            }
            if (win.requestAnimationFrame) { win.requestAnimationFrame(step); }
        });
    }

    function initRipple() {
        if (REDUCED || !motion || !doc.body) { return; }
        var style = doc.createElement('style');
        style.textContent = '.adomin-ripple{position:relative;overflow:hidden}' +
            '.adomin-ripple .adomin-ripple-dot{position:absolute;border-radius:50%;pointer-events:none;' +
            'background:rgba(255,255,255,.42);transform:scale(0);opacity:.9;' +
            'animation:adomin-ripple 480ms cubic-bezier(.16,1,.3,1) forwards}' +
            '@keyframes adomin-ripple{to{transform:scale(2.6);opacity:0}}';
        doc.head.appendChild(style);
        on(doc, 'pointerdown', function (ev) {
            var btn = ev.target.closest ? ev.target.closest('.btn, .list-action, .more-btn') : null;
            if (!btn) { return; }
            btn.classList.add('adomin-ripple');
            var rect = btn.getBoundingClientRect();
            var dot = doc.createElement('span');
            var size = Math.max(rect.width, rect.height);
            dot.className = 'adomin-ripple-dot';
            dot.style.width = dot.style.height = size + 'px';
            dot.style.left = (ev.clientX - rect.left - size / 2) + 'px';
            dot.style.top = (ev.clientY - rect.top - size / 2) + 'px';
            btn.appendChild(dot);
            setTimeout(function () { if (dot.parentNode) { dot.parentNode.removeChild(dot); } }, 520);
        });
    }

    function initToasts() {
        if (!doc.body) { return; }
        // the app shows results through geterror() -> a bootstrap .alert: give it
        // a slide in + auto dismissal so a "Link copied" never sits there forever
        each('.alert:not([data-adomin-toast])', function (el) {
            el.setAttribute('data-adomin-toast', '1');
            if (el.classList.contains('alert-success') || el.classList.contains('alert-info')) {
                setTimeout(function () {
                    el.style.transition = 'opacity 260ms ease, transform 260ms ease';
                    el.style.opacity = '0';
                    el.style.transform = 'translateY(-6px)';
                    setTimeout(function () { if (el.parentNode) { el.parentNode.removeChild(el); } }, 300);
                }, 5200);
            }
        });
    }

    win.adominToast = function (message, kind) {
        var box = doc.getElementById('adomin-toasts');
        if (!box) {
            box = doc.createElement('div');
            box.id = 'adomin-toasts';
            box.style.cssText = 'position:fixed;right:16px;bottom:16px;z-index:10000;display:flex;flex-direction:column;gap:8px;';
            doc.body.appendChild(box);
        }
        var toast = doc.createElement('div');
        toast.className = 'alert alert-' + (kind === 'error' ? 'danger' : 'success') + ' adomin-toast';
        toast.style.cssText = 'margin:0;box-shadow:0 8px 24px rgba(15,30,50,.18);min-width:220px;';
        toast.textContent = message;
        box.appendChild(toast);
        setTimeout(function () {
            toast.style.transition = 'opacity 240ms ease, transform 240ms ease';
            toast.style.opacity = '0';
            toast.style.transform = 'translateY(8px)';
            setTimeout(function () { if (toast.parentNode) { toast.parentNode.removeChild(toast); } }, 260);
        }, 3200);
    };

    /* ---------------------------------------------------------------- *
     * 3. send via link
     * ---------------------------------------------------------------- */
    function csrfToken() {
        var meta = doc.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : '';
    }

    function csrfTokenName() {
        var meta = doc.querySelector('meta[name="csrf-name"]');
        return meta && meta.getAttribute('content') ? meta.getAttribute('content') : 'csrf_test_name';
    }

    function copyText(text) {
        if (navigator.clipboard && win.isSecureContext) {
            return navigator.clipboard.writeText(text).then(function () { return true; }, function () { return legacyCopy(text); });
        }
        return Promise.resolve(legacyCopy(text));
    }
    function legacyCopy(text) {
        var ta = doc.createElement('textarea');
        ta.value = text;
        ta.setAttribute('readonly', '');
        ta.style.cssText = 'position:fixed;left:-9999px;top:0;';
        doc.body.appendChild(ta);
        ta.select();
        var ok = false;
        try { ok = doc.execCommand('copy'); } catch (e) { ok = false; }
        doc.body.removeChild(ta);
        return ok;
    }

    /*
     * "Copied" as something you can see.
     *
     * A class that is added and removed 1200ms later is invisible to anyone who
     * is not looking at that pixel, and it says nothing on a device where the
     * copy silently failed. So the label itself changes to the configured
     * confirmation, the icon becomes a tick, and a short lived element marks the
     * moment. Everything is restored afterwards, including the label, so a
     * second copy works exactly like the first.
     */
    function copied(btn) {
        if (!btn || btn.getAttribute('data-adomin-copied') === '1') { return; }
        var labels = (cfg().labels || {});
        var text = btn.getAttribute('data-adomin-label') || (btn.textContent || '').trim();
        btn.setAttribute('data-adomin-label', text);
        btn.setAttribute('data-adomin-copied', '1');
        btn.classList.add('adomin-copy-ok');
        btn.textContent = '';
        var tick = doc.createElement('i');
        tick.className = 'fa fa-check';
        tick.setAttribute('aria-hidden', 'true');
        btn.appendChild(tick);
        btn.appendChild(doc.createTextNode(' ' + (labels.copied || 'Copied')));
        if (motion && !REDUCED) {
            // a ring that expands from the button: the confirmation reads even
            // if the reader is looking at the link field, not the button
            var ring = doc.createElement('span');
            ring.className = 'adomin-copy-ring';
            ring.setAttribute('aria-hidden', 'true');
            btn.appendChild(ring);
            setTimeout(function () { if (ring.parentNode) { ring.parentNode.removeChild(ring); } }, 700);
        }
        setTimeout(function () {
            btn.classList.remove('adomin-copy-ok');
            btn.removeAttribute('data-adomin-copied');
            btn.textContent = '';
            var icon = doc.createElement('i');
            icon.className = 'fa fa-clipboard';
            icon.setAttribute('aria-hidden', 'true');
            btn.appendChild(icon);
            btn.appendChild(doc.createTextNode(' ' + text));
        }, 1400);
    }

    function cfg() {
        return win.adominShareConfig || {};
    }

    function shareUrlFor(template, id) {
        var t = template || '';
        if (t.indexOf(':id') === -1) { return ''; }
        return t.replace(':id', encodeURIComponent(id));
    }

    function esc(s) {
        return String(s === null || s === undefined ? '' : s).replace(/[&<>"]/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c];
        });
    }

    function openShareModal(button) {
        var modal = doc.getElementById('adominShareModal');
        if (!modal) { return; }
        if (win.jQuery) { win.jQuery(modal).modal('show'); }
        var id = button.getAttribute('data-invoice-id');
        var input = modal.querySelector('#adominShareUrl');
        var state = modal.querySelector('.adomin-share-state');
        var labels = cfg().labels || {};
        if (state) {
            state.innerHTML = '<span class="adomin-spinner" aria-hidden="true"></span> ' + esc(labels.busy || 'Creating link') + '\u2026';
        }
        if (input) { input.value = ''; input.classList.add('adomin-skeleton'); }
        // a URL left over from the previous invoice must not be copyable, and
        // each action reads as unavailable rather than as a dead control
        all('[data-share-action="copy"], [data-share-action="copy-pdf"]', modal).forEach(function (b) {
            b.removeAttribute('data-url');
        });
        all('[data-share-action]', modal).forEach(function (a) {
            a.setAttribute('aria-disabled', 'true');
            a.classList.add('adomin-share-pending');
        });

        var endpoint = shareUrlFor(cfg().endpoint, id);
        if (!endpoint || !id) {
            if (state) { state.textContent = 'Sharing is not enabled on this deployment.'; }
            return;
        }
        var xhr = new XMLHttpRequest();
        xhr.open('POST', endpoint, true);
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded; charset=UTF-8');
        xhr.send('id=' + encodeURIComponent(id) + '&' + csrfTokenName() + '=' + encodeURIComponent(csrfToken()));
        xhr.onreadystatechange = function () {
            if (xhr.readyState !== 4) { return; }
            var data = null;
            try { data = JSON.parse(xhr.responseText); } catch (e) { data = null; }
            if (!data || data.status !== 'success' || !data.url) {
                if (state) { state.textContent = 'Could not create the link.'; }
                if (win.jQuery) { win.jQuery(modal).modal('hide'); }
                if (win.adominToast) { win.adominToast('Could not create the share link', 'error'); }
                return;
            }
            data._invoiceId = id;
            fill(modal, data);
        };
    }

    function fill(modal, data) {
        var input = modal.querySelector('#adominShareUrl');
        if (input) {
            input.value = data.url;
            input.classList.remove('adomin-skeleton');
        }
        var state = modal.querySelector('.adomin-share-state');
        if (state) {
            state.innerHTML = (data.number ? '\u2116 <b>' + esc(data.number) + '</b>' : 'Link ready')
                + (data.total ? ' \u00b7 ' + esc(data.total) : '')
                + (data.client ? ' \u00b7 ' + esc(data.client) : '');
        }
        var msg = modal.querySelector('#adominShareMessage');
        if (msg) { msg.value = data.message || ''; }

        // the app stores no client phone/e-mail on an invoice, so the share
        // targets open empty and the user picks the contact: no wrong guesses
        var text = encodeURIComponent(msg && msg.value ? msg.value : data.url);
        var wa = modal.querySelector('[data-share-action="whatsapp"]');
        // no client phone/e-mail is stored on an invoice row, so these open the
        // picker with the message pre-filled instead of guessing a number
        if (wa) { wa.href = 'https://api.whatsapp.com/send?text=' + text; }
        var sms = modal.querySelector('[data-share-action="sms"]');
        if (sms) { sms.href = 'sms:?&body=' + text; }
        var mail = modal.querySelector('[data-share-action="email"]');
        if (mail) {
            mail.href = 'mailto:?subject=' + encodeURIComponent((data.subject || 'Document') + ' ' + (data.number || '')) + '&body=' + text;
        }
        var copy = modal.querySelector('[data-share-action="copy"]');
        if (copy) { copy.setAttribute('data-url', data.url); }
        var copyPdf = modal.querySelector('[data-share-action="copy-pdf"]');
        if (copyPdf) { copyPdf.setAttribute('data-url', data.pdfUrl || ''); }
        var open = modal.querySelector('[data-share-action="open"]');
        if (open) { open.setAttribute('data-url', data.url); }
        var pdf = modal.querySelector('[data-share-action="pdf"]');
        if (pdf && data.pdfUrl) { pdf.href = data.pdfUrl; }
        var revoke = modal.querySelector('[data-share-action="revoke"]');
        if (revoke) {
            var url = data.revokeUrl || shareUrlFor(cfg().revoke, data._invoiceId);
            if (url) { revoke.href = url; }
        }
        all('[data-share-action]', modal).forEach(function (a) {
            a.removeAttribute('aria-disabled');
            a.classList.remove('adomin-share-pending');
        });
        // keep the message and the link in sync while the user edits the text
        if (msg) {
            msg.addEventListener('input', function () {
                var t = encodeURIComponent(msg.value);
                if (wa) { wa.href = 'https://api.whatsapp.com/send?text=' + t; }
                if (sms) { sms.href = 'sms:?&body=' + t; }
                if (mail) { mail.href = 'mailto:?subject=' + encodeURIComponent((data.subject || 'Document') + ' ' + (data.number || '')) + '&body=' + t; }
            });
        }
    }

    function initShare() {
        each('[data-adomin-share]', function (btn) {
            on(btn, 'click', function (ev) {
                ev.preventDefault();
                // opened from a bootstrap dropdown: let the menu finish hiding,
                // otherwise the modal backdrop is left behind
                setTimeout(function () { openShareModal(btn); }, 60);
            });
        });
        // re-open the sheet straight from the list dropdown without a reload
        var modal = doc.getElementById('adominShareModal');
        if (!modal) { return; }
        each([modal], function (m) {
            all('[data-share-action="copy"]', m).forEach(function (btn) {
                /*
                 * The URL is read at click time, not when this handler is
                 * bound. That was the bug behind "copy only copies the base
                 * url": the handler ran once at page load, before any request
                 * had come back, so `data-url` was empty and the input still
                 * held the placeholder. Reading both now means whichever one
                 * the last successful request filled is the one that gets
                 * copied.
                 */
                on(btn, 'click', function (ev) {
                    ev.preventDefault();
                    var input = m.querySelector('#adominShareUrl');
                    var url = (input && input.value) || btn.getAttribute('data-url') || '';
                    /*
                     * Nothing to copy is a state the reader has to be told
                     * about. This used to `return` - no toast, no animation, no
                     * change at all - so pressing "Copy link" while the request
                     * was still in flight (or after it failed) looked exactly
                     * like a broken button. The button is disabled until the
                     * request lands, and the message says which it is.
                     */
                    if (!url) {
                        if (win.adominToast) {
                            win.adominToast(
                                btn.getAttribute('aria-disabled') === 'true'
                                    ? 'The link is still being created — one moment'
                                    : 'No link to copy yet — reopen "Send via link"',
                                'error'
                            );
                        }
                        return;
                    }
                    copyText(url).then(function (ok) {
                        if (win.adominToast) { win.adominToast(ok ? 'Link copied — send it over' : 'Press Ctrl+C to copy'); }
                        if (!ok && input) { input.select(); }
                        copied(btn);
                    });
                });
            });
            all('[data-share-action="copy-pdf"]', m).forEach(function (btn) {
                on(btn, 'click', function (ev) {
                    ev.preventDefault();
                    var url = btn.getAttribute('data-url') || '';
                    if (!url) {
                        if (win.adominToast) {
                            win.adominToast(
                                btn.getAttribute('aria-disabled') === 'true'
                                    ? 'The link is still being created — one moment'
                                    : 'No PDF link to copy yet',
                                'error'
                            );
                        }
                        return;
                    }
                    copyText(url).then(function (ok) {
                        if (win.adominToast) { win.adominToast(ok ? 'PDF link copied' : 'Press Ctrl+C to copy'); }
                        copied(btn);
                    });
                });
            });
            all('[data-share-action="open"]', m).forEach(function (btn) {
                on(btn, 'click', function () {
                    var input = m.querySelector('#adominShareUrl');
                    if (input && input.value) { win.open(input.value, '_blank'); }
                });
            });
        });
        // native share sheet (Android app / mobile browsers)
        each('[data-adomin-native-share]', function (btn) {
            on(btn, 'click', function (ev) {
                ev.preventDefault();
                var input = modal.querySelector('#adominShareUrl');
                var url = input ? input.value : '';
                if (!url || !navigator.share) {
                    if (win.adominToast) { win.adominToast('Sharing is not available in this browser — copy the link instead', 'error'); }
                    return;
                }
                navigator.share({ title: doc.title, text: (modal.querySelector('#adominShareMessage') || {}).value || '', url: url })
                    .catch(function () { /* the user dismissed the sheet */ });
            });
        });
    }

    /* ---------------------------------------------------------------- *
     * 4. busy state for slow actions (cold starts, PDF generation)
     * ---------------------------------------------------------------- */
    function initBusyButtons() {
        each('form button[type="submit"], form .btn[type="submit"], a.btn[href*="print"]', function (el) {
            on(el, 'click', function () {
                if (!motion || REDUCED) { return; }
                el.classList.add('adomin-is-busy');
                // a cold Render container can take ~60s; never leave a dead button
                setTimeout(function () { el.classList.remove('adomin-is-busy'); }, 8000);
            });
        });
    }

    /* ---------------------------------------------------------------- *
     * 5. PWA + cold start recovery
     * ---------------------------------------------------------------- */
    function initPwa() {
        if (!('serviceWorker' in navigator) || location.protocol !== 'https:' && location.hostname !== 'localhost') {
            return;
        }
        var scope = (doc.querySelector('meta[name="adomin-scope"]') || {}).content || '/';
        navigator.serviceWorker.register(scope.replace(/\/$/, '') + '/sw.js', { scope: scope })
            .catch(function () { /* offline support is progressive, ignore */ });

        var ios = doc.querySelector('link[rel="apple-touch-icon"]');
        if (!ios) {
            ios = doc.createElement('link');
            ios.rel = 'apple-touch-icon';
            ios.href = (doc.querySelector('meta[name="adomin-base"]') || {}).content || '';
            ios.href += 'assets/public/imgs/icons/icon-192.png';
            doc.head.appendChild(ios);
        }
    }

    function initColdStart() {
        // After a Render spin down the first HTML may arrive late or as a stale
        // error page; a single auto reload beats a confused user.
        if (!('navigation' in performance) || !performance.navigation) { return; }
        var dur = 0;
        try {
            var nav = performance.getEntriesByType ? performance.getEntriesByType('navigation')[0] : null;
            dur = nav ? (nav.responseStart - nav.requestStart) : 0;
        } catch (e) { dur = 0; }
        if (dur > 12000 && !sessionStorage.getItem('adomin-warm')) {
            sessionStorage.setItem('adomin-warm', '1');
            win.location.reload();
        }
    }

    /* ---------------------------------------------------------------- *
     * boot
     * ---------------------------------------------------------------- */
    function markAppShell() {
        // installed PWA / Capacitor WebView: bigger targets, safe areas, no
        // browser chrome to compensate for
        var standalone = false;
        try {
            standalone = (win.navigator.standalone === true)
                || (win.matchMedia && win.matchMedia('(display-mode: standalone)').matches)
                || !!win.Capacitor;
        } catch (e) { standalone = false; }
        if (standalone) {
            doc.documentElement.classList.add('adomin-app');
            doc.body.classList.add('adomin-app');
        }
    }


    /* ---------------------------------------------------------------- *
     * 6. landing page: reveal, counters, parallax
     * ---------------------------------------------------------------- */
    function initLanding() {
        var targets = all('.lp-reveal');

        // Nothing to reveal (or motion is off): make sure everything is
        // visible rather than leaving the page blank.
        if (!targets.length || REDUCED || !('IntersectionObserver' in win)) {
            targets.forEach(function (el) { el.classList.add('lp-in'); });
            return;
        }

        var io = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (!entry.isIntersecting) { return; }
                entry.target.classList.add('lp-in');
                io.unobserve(entry.target);
            });
        }, { rootMargin: '0px 0px -10% 0px', threshold: 0.08 });

        targets.forEach(function (el) { io.observe(el); });

        // Counters. The final value lives in the text, so a browser without
        // JS and a screen reader both get the real number.
        each('[data-lp-count]', function (el) {
            var target = parseInt(el.getAttribute('data-lp-count'), 10);
            if (isNaN(target)) { return; }
            var suffix = el.getAttribute('data-lp-suffix') || '';
            var started = false;
            var run = function () {
                if (started) { return; }
                started = true;
                var from = 0, dur = 900, t0 = null;
                var step = function (ts) {
                    if (t0 === null) { t0 = ts; }
                    var p = Math.min(1, (ts - t0) / dur);
                    var eased = 1 - Math.pow(1 - p, 3);
                    el.textContent = Math.round(from + (target - from) * eased) + suffix;
                    if (p < 1) { win.requestAnimationFrame(step); }
                };
                win.requestAnimationFrame(step);
            };
            if ('IntersectionObserver' in win) {
                var cio = new IntersectionObserver(function (entries) {
                    entries.forEach(function (e) {
                        if (e.isIntersecting) { run(); cio.unobserve(e.target); }
                    });
                }, { threshold: 0.4 });
                cio.observe(el);
            } else { run(); }
        });

        // Parallax on the hero photograph: one rAF, transform only, and only
        // while the hero is on screen.
        var heroImg = doc.querySelector('.lp-hero-img');
        if (heroImg && !REDUCED && win.requestAnimationFrame) {
            var ticking = false;
            var shift = function () {
                var y = win.pageYOffset || doc.documentElement.scrollTop || 0;
                if (y < win.innerHeight * 1.2) {
                    heroImg.style.transform = 'translate3d(0,' + (y * 0.16).toFixed(2) + 'px,0)';
                }
                ticking = false;
            };
            on(win, 'scroll', function () {
                if (!ticking) { ticking = true; win.requestAnimationFrame(shift); }
            }, { passive: true });
        }
    }

    function boot() {
        markAppShell();
        if (motion) { initReveal(); initCountUp(); initRipple(); }
        initToasts();
        initShare();
        initLanding();
        initBusyButtons();
        initPwa();
        initColdStart();
    }

    if (doc.readyState === 'loading') {
        on(doc, 'DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
