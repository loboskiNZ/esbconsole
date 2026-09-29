import assert from 'node:assert/strict';
import { test } from 'node:test';
import { CAMERA_UNAVAILABLE_MESSAGE, INVALID_TICKET_QR_MESSAGE, ticketCodeFromQr } from './studio-door-code.js';

const ticketCode = '550e8400-e29b-41d4-a716-446655440000';

test('a ticket QR payload is the ticket public id', () => {
    assert.equal(ticketCodeFromQr(ticketCode), ticketCode);
    assert.equal(ticketCodeFromQr(`  ${ticketCode.toUpperCase()}  `), ticketCode.toUpperCase());
});

test('a non-uuid QR is rejected and is not submitted as a code', () => {
    assert.equal(ticketCodeFromQr('https://edandtheshadowboys.com/tickets/' + ticketCode), null);
    assert.equal(ticketCodeFromQr('not-a-ticket'), null);
    assert.equal(ticketCodeFromQr(''), null);
    assert.equal(ticketCodeFromQr(ticketCode.replaceAll('-', '')), null);
    assert.equal(INVALID_TICKET_QR_MESSAGE, 'That QR code is not a valid ESB ticket code.');
});

test('camera failure copy leaves manual entry available', () => {
    assert.equal(
        CAMERA_UNAVAILABLE_MESSAGE,
        'Camera scanning is unavailable. You can still paste or type the ticket code.',
    );
});
