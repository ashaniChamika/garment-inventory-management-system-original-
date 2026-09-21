/* =============================================================================
   GIMS — Barcode / QR / Scan client
   ============================================================================= */

(function () {
    'use strict';

    const G = window.GIMS || {};
    const api = (u, o) => window.GIMS_APP.api(u, o);
    const toast = (m, t) => window.GIMS_APP.toast(m, t || 'info');
    const esc = (s) => String(s == null ? '' : s)
        .replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')
        .replace(/"/g,'&quot;').replace(/'/g,'&#39;');

    /* ==================================================================
       BARCODE GENERATE (barcode/generate-barcode.php)
       ================================================================== */
    if (document.getElementById('prodSelect')) initBarcodeGen();

    function initBarcodeGen() {
        const sel = document.getElementById('prodSelect');
        const search = document.getElementById('prodSearch');
        const qtyEl = document.getElementById('labelQty');
        const fmtEl = document.getElementById('barFormat');
        const preview = document.getElementById('preview');
        const labelsCont = document.getElementById('labelsContainer');
        const printBtn = document.getElementById('printBtn');

        search.addEventListener('input', () => {
            const q = search.value.toLowerCase();
            [...sel.options].forEach(opt => {
                opt.style.display = opt.textContent.toLowerCase().includes(q) ? '' : 'none';
            });
        });

        document.getElementById('generateBtn').addEventListener('click', generate);

        function generate() {
            const opt = sel.selectedOptions[0];
            if (!opt) { toast('Select a product.', 'warning'); return; }

            const id = opt.value;
            const name = opt.dataset.name;
            const sku = opt.dataset.sku;
            const cat = opt.dataset.cat;
            let code = opt.dataset.code;

            if (!code) code = 'GIMS' + String(id).padStart(8, '0');

            const qty = Math.max(1, Math.min(100, parseInt(qtyEl.value, 10) || 1));
            const fmt = fmtEl.value;

            // EAN13/UPC need digits only — replace non-numeric
            let finalCode = code;
            if (fmt === 'EAN13' || fmt === 'UPC') {
                finalCode = code.replace(/\D/g, '');
                if (finalCode.length < 11) finalCode = finalCode.padStart(11, '0');
            }

            const labels = [];
            for (let i = 0; i < qty; i++) labels.push(i);

            labelsCont.innerHTML = labels.map(i => `
                <div class="gims-label">
                    <div class="brand">${esc(G.currentUser ? 'GIMS' : 'GIMS')}</div>
                    <div class="name">${esc(name)}</div>
                    <div class="sku">${esc(sku)}</div>
                    <svg class="barcode-svg" data-code="${esc(finalCode)}" data-format="${esc(fmt)}" data-idx="${i}"></svg>
                    <div class="sku mt-1">${esc(cat)}</div>
                </div>
            `).join('');

            labelsCont.classList.remove('d-none');
            preview.classList.add('d-none');

            if (typeof JsBarcode !== 'undefined') {
                labelsCont.querySelectorAll('.barcode-svg').forEach(svg => {
                    try {
                        JsBarcode(svg, svg.dataset.code, {
                            format: svg.dataset.format,
                            width: 1.6,
                            height: 55,
                            fontSize: 11,
                            margin: 4,
                            displayValue: true
                        });
                    } catch (e) {
                        svg.outerHTML = `<div class="text-danger small">Invalid code for ${svg.dataset.format}</div>`;
                    }
                });
            } else {
                toast('Barcode library not loaded.', 'danger');
            }

            printBtn.disabled = false;
        }

        printBtn.addEventListener('click', () => window.print());
    }

    /* ==================================================================
       QR GENERATE (barcode/generate-qr.php)
       ================================================================== */
    if (document.getElementById('qrSelect')) initQrGen();

    function initQrGen() {
        const sel = document.getElementById('qrSelect');
        const search = document.getElementById('qrSearch');
        const sizeEl = document.getElementById('qrSize');
        const preview = document.getElementById('qrPreview');
        const info = document.getElementById('qrInfo');
        const printBtn = document.getElementById('qrPrint');

        search.addEventListener('input', () => {
            const q = search.value.toLowerCase();
            [...sel.options].forEach(opt => {
                opt.style.display = opt.textContent.toLowerCase().includes(q) ? '' : 'none';
            });
        });

        document.getElementById('qrGenerate').addEventListener('click', () => {
            const opt = sel.selectedOptions[0];
            if (!opt) { toast('Select a product.', 'warning'); return; }
            if (typeof QRCode === 'undefined') { toast('QR library not loaded.', 'danger'); return; }

            const size = parseInt(sizeEl.value, 10);
            const payload = JSON.stringify({
                id: parseInt(opt.value, 10),
                sku: opt.dataset.sku,
                name: opt.dataset.name,
                price: parseFloat(opt.dataset.price || 0),
                url: G.baseUrl + '/products/view.php?id=' + opt.value
            });

            preview.innerHTML = '';
            preview.classList.remove('py-5', 'text-muted');

            const holder = document.createElement('div');
            holder.style.display = 'inline-block';
            holder.style.padding = '16px';
            holder.style.background = '#fff';
            holder.style.borderRadius = '12px';
            holder.style.boxShadow = '0 8px 24px -12px rgba(0,0,0,.25)';
            preview.appendChild(holder);

            new QRCode(holder, {
                text: payload,
                width: size,
                height: size,
                colorDark: '#0f172a',
                colorLight: '#ffffff',
                correctLevel: QRCode.CorrectLevel.H
            });

            document.getElementById('qrProductName').textContent = opt.dataset.name;
            document.getElementById('qrProductSku').textContent = opt.dataset.sku;
            document.getElementById('qrProductPrice').textContent = G.currency + ' ' + parseFloat(opt.dataset.price || 0).toLocaleString();
            document.getElementById('qrProductCat').textContent = opt.dataset.cat || '—';
            info.classList.remove('d-none');
            printBtn.disabled = false;
        });

        printBtn.addEventListener('click', () => window.print());
    }

    /* ==================================================================
       SCAN LOOKUP (barcode/scan.php)
       ================================================================== */
    if (document.getElementById('scanInput')) initScan();

    function initScan() {
        const input = document.getElementById('scanInput');
        const resultCard = document.getElementById('resultCard');
        const errorBox = document.getElementById('resultError');

        async function lookup() {
            const code = input.value.trim();
            if (!code) return;
            errorBox.classList.add('d-none');
            resultCard.style.display = 'none';

            const r = await api(`${G.baseUrl}/api/barcode.php?action=lookup_barcode&code=${encodeURIComponent(code)}`);
            if (!r.success) {
                errorBox.textContent = r.message || 'Not found.';
                errorBox.classList.remove('d-none');
                input.select();
                return;
            }

            const d = r.data.data;
            const type = r.data.type;
            document.getElementById('resultType').textContent = type === 'variant' ? 'Variant' : 'Product';
            document.getElementById('rName').textContent = d.name;
            document.getElementById('rSku').textContent = (d.sku || '') + (d.variant_sku ? ' / ' + d.variant_sku : '');
            document.getElementById('rUnit').textContent = d.unit || 'pcs';
            document.getElementById('rPrice').textContent = G.currency + ' ' + parseFloat(d.selling_price || 0).toLocaleString();
            document.getElementById('rVariant').textContent = type === 'variant' ? (d.color + ' / ' + d.size) : '—';

            const pid = d.product_id || d.id;
            document.getElementById('rView').href = `${G.baseUrl}/products/view.php?id=${pid}`;
            document.getElementById('rBarcode').href = `${G.baseUrl}/barcode/generate-barcode.php?id=${pid}`;

            resultCard.style.display = '';
            input.value = '';
            input.focus();
        }

        input.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') { e.preventDefault(); lookup(); }
        });
        document.getElementById('lookupBtn').addEventListener('click', lookup);
    }
})();