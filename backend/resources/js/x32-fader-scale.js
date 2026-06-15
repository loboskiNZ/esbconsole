const LINEAR_UNITY = 0.75;
const LINEAR_MAX = 1.0;

export function linearToDb(linear) {
    const value = Math.min(LINEAR_MAX, Math.max(0, linear));

    if (value >= 0.5) {
        return (value * 40) - 30;
    }

    if (value >= 0.25) {
        return (value * 80) - 50;
    }

    if (value >= 0.0625) {
        return (value * 160) - 70;
    }

    return (value * 480) - 90;
}

export function dbToLinear(db) {
    if (db <= -60) {
        return Math.max(0, (db + 90) / 480);
    }

    if (db < -30) {
        return (db + 70) / 160;
    }

    if (db < -10) {
        return (db + 50) / 80;
    }

    return Math.min(LINEAR_MAX, (db + 30) / 40);
}

export function formatDb(db) {
    if (db <= -89.5) {
        return '−∞';
    }

    const rounded = Math.round(db * 10) / 10;

    if (rounded > 0) {
        return `+${rounded.toFixed(1)}`;
    }

    if (rounded === 0) {
        return '0.0';
    }

    return rounded.toFixed(1);
}

export function linearMarkPercent(linear) {
    return Math.min(100, Math.max(0, linear * 100));
}

export function quantizeLinear(linear) {
    const clamped = Math.min(LINEAR_MAX, Math.max(0, linear));

    return Math.round(clamped * 1023) / 1023;
}

export const FADER_SCALE_MARKS = [
    { db: 10, linear: 1.0, label: '+10', major: true },
    { db: 0, linear: LINEAR_UNITY, label: '0', major: true, unity: true },
    { db: -10, linear: 0.5, label: null, major: false },
    { db: -30, linear: 0.25, label: null, major: false },
    { db: -60, linear: 0.0625, label: '−60', major: true },
];
