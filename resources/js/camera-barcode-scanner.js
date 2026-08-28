let reader = null;
let scanning = false;
let lastText = '';
let lastAt = 0;
let scanTimer = null;
let captureCanvas = null;
let captureCtx = null;
let zxingLib = null;

async function loadZxing() {
    if (!zxingLib) {
        zxingLib = await import('@zxing/library');
    }
    return zxingLib;
}

function sleep(ms) {
    return new Promise((resolve) => setTimeout(resolve, ms));
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

function isNotFoundError(err) {
    if (!err) return false;
    const name = String(err.name || err.constructor?.name || '');
    const msg = String(err.message || '');
    return name === 'NotFoundException' || /not found/i.test(msg);
}

function buildReader(lib) {
    const { BrowserMultiFormatReader, DecodeHintType, BarcodeFormat } = lib;
    const hints = new Map();
    hints.set(DecodeHintType.TRY_HARDER, true);
    hints.set(DecodeHintType.POSSIBLE_FORMATS, [
        BarcodeFormat.EAN_13,
        BarcodeFormat.EAN_8,
        BarcodeFormat.UPC_A,
        BarcodeFormat.UPC_E,
        BarcodeFormat.CODE_128,
        BarcodeFormat.CODE_39,
        BarcodeFormat.CODE_93,
        BarcodeFormat.ITF,
        BarcodeFormat.QR_CODE,
    ]);

    return new BrowserMultiFormatReader(hints, 100);
}

async function waitForVideoReady(video, timeoutMs = 10000) {
    const start = Date.now();
    while (Date.now() - start < timeoutMs) {
        if (video.readyState >= 2 && video.videoWidth > 0 && video.videoHeight > 0) {
            return;
        }
        await sleep(80);
    }
    throw new Error('Kamera belum siap. Tunggu sebentar lalu coba lagi.');
}

async function openCameraStream(preferredDeviceId = null) {
    const attempts = [
        {
            audio: false,
            video: {
                facingMode: { ideal: 'environment' },
                width: { ideal: 1920, min: 640 },
                height: { ideal: 1080, min: 480 },
            },
        },
        preferredDeviceId
            ? {
                audio: false,
                video: {
                    deviceId: { exact: preferredDeviceId },
                    width: { ideal: 1280, min: 640 },
                    height: { ideal: 720, min: 480 },
                },
            }
            : null,
        {
            audio: false,
            video: {
                width: { ideal: 1280, min: 640 },
                height: { ideal: 720, min: 480 },
            },
        },
    ].filter(Boolean);

    let lastErr = null;
    for (const constraints of attempts) {
        try {
            return await navigator.mediaDevices.getUserMedia(constraints);
        } catch (err) {
            lastErr = err;
        }
    }

    throw lastErr || new Error('Tidak bisa membuka kamera.');
}

function ensureCaptureCanvas() {
    if (!captureCanvas) {
        captureCanvas = document.createElement('canvas');
        captureCtx = captureCanvas.getContext('2d', { willReadFrequently: true });
    }
}

function startScanLoop(video, onScan) {
    ensureCaptureCanvas();

    const tick = () => {
        if (!scanning) return;

        if (video.readyState >= 2 && video.videoWidth > 0 && video.videoHeight > 0) {
            const vw = video.videoWidth;
            const vh = video.videoHeight;

            // Crop area tengah (sesuai kotak panduan) — barcode 1D lebih mudah terbaca
            const cropW = Math.floor(vw * 0.9);
            const cropH = Math.floor(vh * 0.45);
            const sx = Math.floor((vw - cropW) / 2);
            const sy = Math.floor((vh - cropH) / 2);

            captureCanvas.width = cropW;
            captureCanvas.height = cropH;
            captureCtx.drawImage(video, sx, sy, cropW, cropH, 0, 0, cropW, cropH);

            try {
                const result = reader.decode(captureCanvas);
                const text = String(result?.getText?.() || result?.text || '').trim();
                if (text) {
                    const now = Date.now();
                    if (text !== lastText || now - lastAt > 600) {
                        lastText = text;
                        lastAt = now;
                        scanning = false;
                        setStatus(`Terbaca: ${text}`);
                        onScan?.(text);
                        stopCameraScanner();
                        return;
                    }
                }
            } catch (err) {
                if (!isNotFoundError(err)) {
                    console.warn('Scan barcode:', err);
                }
            }
        }

        scanTimer = window.setTimeout(tick, 100);
    };

    tick();
}

export async function stopCameraScanner() {
    scanning = false;
    lastText = '';
    lastAt = 0;

    if (scanTimer) {
        clearTimeout(scanTimer);
        scanTimer = null;
    }

    try {
        reader?.stopContinuousDecode?.();
    } catch (_) {}

    try {
        reader?.reset?.();
    } catch (_) {}

    reader = null;

    const video = getVideo();
    if (video) {
        const stream = video.srcObject;
        if (stream?.getTracks) {
            stream.getTracks().forEach((track) => track.stop());
        }
        video.srcObject = null;
        video.removeAttribute('src');
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

    const lib = await loadZxing();
    reader = buildReader(lib);

    try {
        let preferredDeviceId = null;

        try {
            // Izin kamera dulu agar label perangkat terbaca
            const temp = await navigator.mediaDevices.getUserMedia({
                audio: false,
                video: { facingMode: { ideal: 'environment' } },
            });
            temp.getTracks().forEach((t) => t.stop());

            const devices = await reader.listVideoInputDevices();
            const rear = devices.find((d) => /back|rear|environment|belakang/i.test(d.label || ''));
            if (rear?.deviceId) {
                preferredDeviceId = rear.deviceId;
            }
        } catch (_) {
            // Lanjut dengan facingMode default
        }

        const stream = await openCameraStream(preferredDeviceId);
        video.srcObject = stream;
        video.setAttribute('playsinline', 'true');
        video.setAttribute('muted', 'true');
        video.muted = true;
        video.playsInline = true;

        try {
            await video.play();
        } catch (_) {}

        await waitForVideoReady(video);

        scanning = true;
        setStatus('Arahkan barcode ke dalam kotak putih. Dekatkan & pastikan cukup terang.');

        startScanLoop(video, onScan);
    } catch (err) {
        await stopCameraScanner();
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
