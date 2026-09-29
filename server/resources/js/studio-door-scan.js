import QrScanner from 'qr-scanner';
import { CAMERA_UNAVAILABLE_MESSAGE, INVALID_TICKET_QR_MESSAGE, ticketCodeFromQr } from './studio-door-code';

const scannerOptions = {
    preferredCamera: 'environment',
    maxScansPerSecond: 8,
    highlightScanRegion: true,
    highlightCodeOutline: true,
    returnDetailedScanResult: true,
};

export function bootDoorScanner(doc = document) {
    const form = doc.getElementById('door-check-in');
    const tokenInput = doc.getElementById('door-token');
    const startButton = doc.getElementById('door-scan-start');
    const cancelButton = doc.getElementById('door-scan-cancel');
    const panel = doc.getElementById('door-scan-panel');
    const video = doc.getElementById('door-scan-video');
    const status = doc.getElementById('door-scan-status');

    if (!form || !tokenInput || !startButton || !cancelButton || !panel || !video || !status) {
        return;
    }

    let scanner = null;
    let starting = false;
    let accepting = false;

    const showStatus = (message) => {
        status.hidden = false;
        status.textContent = message;
    };

    const clearStatus = () => {
        status.hidden = true;
        status.textContent = '';
    };

    const stopTracks = () => {
        const stream = video.srcObject;

        if (!(stream instanceof MediaStream)) {
            return;
        }

        for (const track of stream.getTracks()) {
            track.stop();
        }

        video.srcObject = null;
    };

    const releaseCamera = () => {
        const current = scanner;
        scanner = null;
        stopTracks();

        if (current) {
            try {
                current.stop();
                current.destroy();
            } catch {
                // Tracks are already stopped. A scanner teardown error must not block check-in.
            }
        }

        panel.hidden = true;
        startButton.disabled = false;
        startButton.setAttribute('aria-expanded', 'false');
    };

    const scanText = (result) => {
        if (typeof result === 'string') {
            return result;
        }

        if (result && typeof result.data === 'string') {
            return result.data;
        }

        return '';
    };

    const onDecode = (result) => {
        if (accepting) {
            return;
        }

        const code = ticketCodeFromQr(scanText(result));

        if (!code) {
            showStatus(INVALID_TICKET_QR_MESSAGE);

            return;
        }

        accepting = true;
        clearStatus();
        tokenInput.value = code;
        releaseCamera();

        if (typeof form.requestSubmit === 'function') {
            form.requestSubmit();
        } else {
            form.submit();
        }
    };

    const startScan = async () => {
        if (scanner || starting) {
            return;
        }

        starting = true;
        accepting = false;
        startButton.disabled = true;
        startButton.setAttribute('aria-expanded', 'true');
        clearStatus();
        panel.hidden = false;

        try {
            const next = new QrScanner(video, onDecode, scannerOptions);
            scanner = next;
            await next.start();

            if (accepting || scanner !== next) {
                return;
            }
        } catch {
            if (accepting) {
                return;
            }

            releaseCamera();
            showStatus(CAMERA_UNAVAILABLE_MESSAGE);
            tokenInput.focus();
        } finally {
            starting = false;
        }
    };

    startButton.addEventListener('click', () => {
        void startScan();
    });

    cancelButton.addEventListener('click', () => {
        releaseCamera();
        clearStatus();
        tokenInput.focus();
    });

    form.addEventListener('submit', () => {
        releaseCamera();
    });

    window.addEventListener('pagehide', () => {
        releaseCamera();
    });
}

if (typeof document !== 'undefined' && document.getElementById('door-check-in')) {
    bootDoorScanner(document);
}
