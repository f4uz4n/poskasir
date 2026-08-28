let reader = null;
let scanning = false;
let lastText = '';
let lastAt = 0;
let zxingLib = null;

async function loadZxing() {
    if (!zxingLib) {
        zxingLib = await import('@zxing/library');
    }
    return zxingLib;
}

function getModal() {
    return document.getElementById('camera-barcode-modal');
}

function getVideo() {
    return document.getElementById('camera-barcode-video');
}

function setStatus(message, isError = false) {
    const el = document.getElementById('camera-barcode-status');
    if (!el) return;
    el.textContent = message || '';
    el.classList.toggle('text-red-600', isError);
    el.classList.toggle('text-slate-500', !isError);
}

export async function stopCameraScanner() {
    scanning = false;
    lastText = '';
    lastAt = 0;

    try {
        reader?.reset();
    } catch (_) {}

    reader = null;

    const video = getVideo();
    if (video) {
        const stream = video.srcObject;
        if (stream?.getTracks) {
            stream.getTracks().forEach((track) => track.stop());
        }
        video.srcObject = null;
    }

    getModal()?.classList.remove('open');
}

/**
 * Buka modal scan barcode via kamera (EAN, Code128, QR, dll).
 * @param {{ onScan: (code: string) => void, onError?: (message: string) => void }} opts
 */
export async function openCameraBarcodeScanner({ onScan, onError } = {}) {
    if (!window.isSecureContext) {
        const msg = 'Kamera hanya bisa dipakai di HTTPS atau localhost.';
        onError?.(msg);
        throw new Error(msg);
    }

    if (!navigator.mediaDevices?.getUserMedia) {
        const msg = 'Browser tidak mendukung kamera.';
        onError?.(msg);
        throw new Error(msg);
    }

    const modal = getModal();
    const video = getVideo();
    if (!modal || !video) {
        const msg = 'Modal scanner kamera belum tersedia di halaman ini.';
        onError?.(msg);
        throw new Error(msg);
    }

    await stopCameraScanner();

    modal.classList.add('open');
    setStatus('Mengaktifkan kamera…');

    const { BrowserMultiFormatReader, NotFoundException } = await loadZxing();
    reader = new BrowserMultiFormatReader();

    try {
        const devices = await BrowserMultiFormatReader.listVideoInputDevices();
        let deviceId = undefined;

        const rear = devices.find((d) => /back|rear|environment|belakang/i.test(d.label));
        if (rear) {
            deviceId = rear.deviceId;
        } else if (devices.length > 1) {
            deviceId = devices[devices.length - 1].deviceId;
        }

        scanning = true;
        setStatus('Arahkan kamera ke barcode produk');

        await reader.decodeFromVideoDevice(deviceId, video, (result, err) => {
            if (!scanning) return;

            if (result) {
                const text = String(result.getText() || '').trim();
                if (!text) return;

                const now = Date.now();
                if (text === lastText && now - lastAt < 1500) return;
                lastText = text;
                lastAt = now;

                scanning = false;
                setStatus(`Terbaca: ${text}`);
                onScan?.(text);
                stopCameraScanner();
                return;
            }

            if (err && !(err instanceof NotFoundException)) {
                console.warn('Camera barcode scan error', err);
            }
        });
    } catch (err) {
        const msg = err?.name === 'NotAllowedError'
            ? 'Izin kamera ditolak. Aktifkan kamera untuk situs ini di pengaturan browser.'
            : (err?.message || 'Gagal membuka kamera.');
        setStatus(msg, true);
        onError?.(msg);
        throw err;
    }
}

export function initCameraBarcodeScanner() {
    document.getElementById('btn-close-camera-barcode')?.addEventListener('click', () => {
        stopCameraScanner();
    });

    document.getElementById('btn-close-camera-barcode-bottom')?.addEventListener('click', () => {
        stopCameraScanner();
    });

    getModal()?.addEventListener('click', (e) => {
        if (e.target === getModal()) stopCameraScanner();
    });

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && getModal()?.classList.contains('open')) {
            stopCameraScanner();
        }
    });
}

export default {
    openCameraBarcodeScanner,
    initCameraBarcodeScanner,
    stopCameraScanner,
};
