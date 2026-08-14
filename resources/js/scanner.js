/**
 * Barcode scanner (keyboard wedge) listener.
 * Most USB/BT barcode scanners type characters rapidly then send Enter.
 */
export function createBarcodeScanner({ onScan, targetSelector = '#barcode-input', minLength = 3 }) {
    let buffer = '';
    let lastTime = 0;
    const MAX_INTERVAL = 50;

    function handleKeydown(e) {
        const target = document.querySelector(targetSelector);
        const active = document.activeElement;
        const isBarcodeField = active === target;
        const isBody = active === document.body || active === document.documentElement;

        // Allow scanner when focused on barcode field or nowhere special
        if (!isBarcodeField && !isBody && active?.tagName !== 'BODY') {
            // If typing in other inputs slowly, ignore
            if (active?.tagName === 'INPUT' || active?.tagName === 'TEXTAREA' || active?.tagName === 'SELECT') {
                if (Date.now() - lastTime > MAX_INTERVAL) {
                    buffer = '';
                    return;
                }
            }
        }

        const now = Date.now();
        if (now - lastTime > 100) {
            buffer = '';
        }
        lastTime = now;

        if (e.key === 'Enter') {
            if (buffer.length >= minLength) {
                e.preventDefault();
                onScan(buffer.trim());
            }
            buffer = '';
            return;
        }

        if (e.key.length === 1) {
            buffer += e.key;
            if (isBarcodeField && target) {
                // keep field in sync when focused
            }
        }
    }

    document.addEventListener('keydown', handleKeydown);

    if (targetSelector) {
        const el = document.querySelector(targetSelector);
        el?.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                const value = el.value.trim();
                if (value) onScan(value);
                el.value = '';
                buffer = '';
            }
        });
    }

    return {
        destroy() {
            document.removeEventListener('keydown', handleKeydown);
        },
    };
}

export default createBarcodeScanner;
