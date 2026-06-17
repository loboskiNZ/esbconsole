import { linearToDb } from './x32-fader-scale';

export const GROUP_SEND_DB_MIN = -90;
export const GROUP_SEND_DB_MAX = 10;

export function clampSendLevelDb(db) {
    return Math.min(GROUP_SEND_DB_MAX, Math.max(GROUP_SEND_DB_MIN, db));
}

/**
 * Apply the same dB trim offset to each channel baseline, preserving relative balance.
 *
 * @param {Record<number, number>} baselineByChannel
 * @param {number} trimOffsetDb
 * @return {Record<number, number>}
 */
export function applyGroupTrimLevels(baselineByChannel, trimOffsetDb) {
    const levels = {};

    for (const [channel, baselineDb] of Object.entries(baselineByChannel)) {
        if (!Number.isFinite(baselineDb)) {
            continue;
        }

        levels[Number(channel)] = clampSendLevelDb(baselineDb + trimOffsetDb);
    }

    return levels;
}

export function averageBaselineDb(baselineByChannel) {
    const levels = Object.values(baselineByChannel).filter((db) => Number.isFinite(db));

    if (levels.length === 0) {
        return 0;
    }

    return levels.reduce((sum, level) => sum + level, 0) / levels.length;
}

export function groupFaderDisplayDb(averageBaselineDb, trimOffsetDb) {
    if (!Number.isFinite(averageBaselineDb)) {
        return trimOffsetDb;
    }

    return averageBaselineDb + trimOffsetDb;
}

export function trimOffsetFromGroupFaderDb(groupFaderDb, averageBaselineDb) {
    if (!Number.isFinite(averageBaselineDb)) {
        return groupFaderDb;
    }

    return groupFaderDb - averageBaselineDb;
}

export function trimOffsetDbFromFaderPct(pct) {
    const linear = Math.min(1, Math.max(0, pct / 100));

    return linearToDb(linear);
}
