export const INVALID_TICKET_QR_MESSAGE = 'That QR code is not a valid ESB ticket code.';

export const CAMERA_UNAVAILABLE_MESSAGE = 'Camera scanning is unavailable. You can still paste or type the ticket code.';

const TICKET_CODE = /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i;

export function ticketCodeFromQr(value) {
    const text = String(value ?? '').trim();

    return TICKET_CODE.test(text) ? text : null;
}
