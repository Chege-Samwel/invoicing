# Document tests

Checks for the invoice document: its layout, its QR code, its pricing terms,
and what the share page does when something is missing. They exist because
most of these failures were silent — a document that prints no QR code, or
spills a blank second page, still looks fine in a browser at 100%.

Run them all:

```
cd tests/document
npm install      # php-wasm
npm test
```

`npm test` runs every harness and prints one line each; it exits non-zero if
any assertion fails. Individual files can be run directly, and they print one
line per assertion:

| File | What it checks |
| --- | --- |
| `longinvoice.mjs` | An invoice with more rows than a sheet holds. Renders all six designs with 30 rows and fails if any row does not reach the paper. |
| `qr.mjs` | The verify URL, the 12-character token, and that all six designs draw a captioned code. Renders the real `AdominQr` and bundled `phpqrcode` through php-wasm rather than a stub. |
| `geometry.mjs` | The A4 sheet, the margins, and which band sits lowest. |
| `pagecount.mjs` | That the declared page height fits the printable area, so printing does not spill a blank second page. |
| `layout8.mjs` | No band overlaps another, and the letterhead keeps its clearance. |
| `currency.mjs` | The currency is printed once, not beside every figure. |
| `repetition.mjs` | No party is named twice; the beneficiary is suppressed only when it repeats the recipient. |
| `pdfpath.mjs` | The PDF path and the browser path are styled by the same sheets. |
| `sharepage.mjs` | The share page behaves when its data is missing. |
| `pricing.mjs`, `pricingpage.mjs`, `ownership.mjs` | 6,000 KES for life; Free at 0; Business unpriced and labelled as such. |
| `entitlements.mjs` | Plan flags, and that unbuilt features are marked "coming soon" rather than dropped. |
| `accept.mjs`, `accept_render.mjs` | Accepting an invoice by link. |
| `fixes.mjs`, `libraries.mjs`, `watermark.mjs`, `domcheck.mjs` | Regressions for individual fixes. |

## These are structural checks

There is no browser in this environment, so the tests read CSS and rendered
markup rather than measuring a live layout. Where a value matters (a page
height, a token length, a price) they compute it instead of trusting the file
that declares it.

## Renderers

`qrender.mjs` renders a template to HTML through php-wasm, and is driven by
`qr.mjs` with `REALQR=1` so the code is drawn by the real library rather than a
stub:

```
node qrender.mjs minimal > /tmp/page.html     # then open it, or feed raster.py
```

`raster.py` draws the HTML's bands to a PNG as a sanity sketch of the layout:

```
python3 raster.py /tmp/page.html /tmp/page.png
```

It is a diagram of the band geometry, not a faithful screenshot — it does not
render text, fonts, or letterhead content from the markup.

## A caveat worth keeping

Assertions that only look for the absence of a bug are weak: an early version
of `qr.mjs` passed with the shipped config still setting an empty encryption
key, because it tested the harness's own key. When adding a check, confirm it
fails when the bug is put back.
