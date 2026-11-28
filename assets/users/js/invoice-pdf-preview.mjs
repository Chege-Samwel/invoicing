/* Render the same PDF used by Download on both authenticated and share pages.
 * The server-rendered HTML remains available until all pages have succeeded. */
export async function renderInvoicePdf(shell, pdfjs) {
    const status = shell.querySelector('.invoice-pdf-status');
    const pages = shell.querySelector('.invoice-pdf-pages');
    const pdfUrl = new URL(shell.dataset.invoicePdf, window.location.href);
    if (pdfUrl.origin !== window.location.origin) throw new Error('Invoice PDF must be same-origin');
    pdfjs.GlobalWorkerOptions.workerSrc = shell.dataset.pdfjsWorker;
    status.textContent = 'Loading PDF preview…';
    let documentPdf;
    try {
        documentPdf = await pdfjs.getDocument({ url: pdfUrl.href }).promise;
        const fragment = document.createDocumentFragment();
        for (let number = 1; number <= documentPdf.numPages; number++) {
            const page = await documentPdf.getPage(number);
            // Render at a consistent, print-friendly resolution; CSS scales the
            // page down for narrow screens without changing its aspect ratio.
            const viewport = page.getViewport({ scale: 2 });
            const wrapper = document.createElement('section');
            wrapper.className = 'invoice-pdf-page';
            wrapper.setAttribute('aria-label', `Invoice page ${number} of ${documentPdf.numPages}`);
            const canvas = document.createElement('canvas');
            canvas.width = Math.ceil(viewport.width);
            canvas.height = Math.ceil(viewport.height);
            canvas.setAttribute('aria-hidden', 'true');
            await page.render({ canvasContext: canvas.getContext('2d'), viewport }).promise;
            const content = await page.getTextContent();
            const text = document.createElement('div');
            text.className = 'invoice-pdf-text';
            text.textContent = content.items.map(item => item.str || '').join(' ');
            wrapper.append(canvas, text);
            fragment.appendChild(wrapper);
            page.cleanup();
        }
        pages.replaceChildren(fragment);
        pages.hidden = false;
        shell.classList.add('is-pdf-ready');
        status.textContent = '';
    } finally {
        if (documentPdf) await documentPdf.destroy();
    }
}

export async function startInvoicePdfPreview(shell) {
    try {
        const pdfjs = await import(shell.dataset.pdfjs);
        await renderInvoicePdf(shell, pdfjs);
    } catch (error) {
        // An expired session, network error or unsupported browser must never
        // leave the customer with a blank invoice or a misleading partial PDF.
        shell.querySelector('.invoice-pdf-status').textContent = 'PDF preview unavailable. The invoice is shown below; use Open PDF to try again.';
        console.error('Invoice PDF preview failed', error);
    }
}
if (typeof document !== 'undefined') {
    document.querySelectorAll('.invoice-pdf-shell').forEach(startInvoicePdfPreview);
}
