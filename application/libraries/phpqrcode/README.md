# phpqrcode

Vendored, unmodified, from https://github.com/t0k4rt/phpqrcode (master).

Licence: LGPL-3.0 — see `LICENSE` in this directory.

Used by `application/libraries/AdominQr.php` to render the verification code on
an invoice. Nothing here is called directly by the application; the wrapper is
the only entry point, and it is guarded so a missing or broken library degrades
to "no code" rather than a fatal error on a printed document.
