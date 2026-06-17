import { dbToLinear, formatDb, linearMarkPercent, linearToDb } from './x32-fader-scale';
import {
    applyGroupTrimLevels,
    trimOffsetDbFromFaderPct,
    trimOffsetFromGroupFaderDb,
} from './x32-monitors-group-trim';
import {
    applyChannelMuteVisual,
    commitChannelMute,
    getSendControlRoot,
    isStripMonitorMuted,
    sendControlConfig,
} from './x32-monitors-send-api';

function clamp(value, min, max) {
    return Math.min(max, Math.max(min, value));
}

function dbToFaderPct(levelDb) {
    if (!Number.isFinite(levelDb)) {
        return linearMarkPercent(0.75);
    }

    return linearMarkPercent(dbToLinear(levelDb));
}

function faderPctToDb(pct) {
    return linearToDb(clamp(pct, 0, 100) / 100);
}

function formatLevelDisplay(levelDb) {
    if (!Number.isFinite(levelDb)) {
        return '—';
    }

    return formatDb(levelDb);
}

function readStripLevelDb(strip) {
    const fromData = parseFloat(strip.getAttribute('data-level-db') ?? '');

    if (Number.isFinite(fromData)) {
        return fromData;
    }

    const handle = strip.querySelector('[data-channel-fader-handle]');

    if (!handle) {
        return 0;
    }

    const pct = parseFloat(handle.style.bottom) || 50;

    return faderPctToDb(pct);
}

function applyStripLevel(strip, levelDb) {
    const pct = dbToFaderPct(levelDb);
    const handle = strip.querySelector('[data-channel-fader-handle]');
    const level = strip.querySelector('[data-channel-fader-level]');

    strip.setAttribute('data-level-db', levelDb.toFixed(2));

    if (handle) {
        handle.style.bottom = `${pct}%`;
    }

    if (level) {
        level.textContent = formatLevelDisplay(levelDb);
    }
}

function syncGroupStripFaderDisplay(groupStrip, displayDb) {
    const pct = dbToFaderPct(displayDb);
    const handle = groupStrip.querySelector('[data-group-fader-handle]');
    const level = groupStrip.querySelector('[data-group-fader-level]');

    if (handle) {
        handle.style.bottom = `${pct}%`;
    }

    if (level) {
        level.textContent = formatLevelDisplay(displayDb);
    }
}

class MonitorsGroupControl {
    constructor(root) {
        this.root = root;
        this.channelsRoot = document.querySelector('[data-monitors-channels]');
        this.stripsContainer = this.channelsRoot?.querySelector('[data-channel-strips]') ?? null;
        this.pickHintEl = this.channelsRoot?.querySelector('[data-channels-pick-hint]') ?? null;
        this.pickCountEl = this.channelsRoot?.querySelector('[data-group-pick-count]') ?? null;
        this.viewButtons = [...(this.channelsRoot?.querySelectorAll('[data-channels-view]') ?? [])];
        this.selectionStatusEl = root.querySelector('[data-group-selection-status]') ?? null;
        this.groupMenuHintEl = root.querySelector('[data-group-menu-hint]') ?? null;
        this.clearPickButton = root.querySelector('[data-group-clear-pick]') ?? null;
        this.removeFromButton = root.querySelector('[data-group-remove-from]') ?? null;
        this.clearGroupButton = root.querySelector('[data-group-clear-active]') ?? null;
        this.channelStrips = [...(this.channelsRoot?.querySelectorAll('[data-channel-strip]') ?? [])];
        this.groupStrips = [...(this.channelsRoot?.querySelectorAll('[data-group-strip]') ?? [])];
        this.selectButtons = [...root.querySelectorAll('[data-group-select]')];
        this.assignments = new Map(
            this.selectButtons
                .filter((button) => button.getAttribute('data-group-key') !== '')
                .map((button) => [button.getAttribute('data-group-key') ?? '', new Set()]),
        );
        this.pickedChannels = new Set();
        this.activeKey = '';
        this.activeLabel = '';
        this.viewMode = 'all';
        this.dragState = null;
        this.groupBaselines = new Map();
        this.groupBaselineAverageDb = new Map();
        this.groupTrimDb = new Map();

        this.bind();
        this.refreshChannelMuteVisuals();
        this.syncAssignmentLabels();
        this.updatePickUi();
        this.setViewMode('all');
        this.focusGroup('');
    }

    stripForChannel(number) {
        return this.channelStrips.find((strip) => parseInt(strip.getAttribute('data-channel') ?? '0', 10) === number);
    }

    groupStripForKey(key) {
        return this.groupStrips.find((strip) => strip.getAttribute('data-group-key') === key) ?? null;
    }

    groupButtonForKey(key) {
        return this.selectButtons.find((button) => button.getAttribute('data-group-key') === key) ?? null;
    }

    channelsForGroup(key) {
        return [...(this.assignments.get(key) ?? new Set())].sort((a, b) => a - b);
    }

    groupKeyForChannel(number) {
        for (const [key, channels] of this.assignments.entries()) {
            if (channels.has(number)) {
                return key;
            }
        }

        return '';
    }

    baselineForGroup(key) {
        return this.groupBaselines.get(key) ?? null;
    }

    averageBaselineForGroup(key) {
        return this.groupBaselineAverageDb.get(key) ?? 0;
    }

    groupFaderDisplayForKey(key) {
        return this.averageBaselineForGroup(key) + (this.groupTrimDb.get(key) ?? 0);
    }

    captureGroupBaseline(key) {
        const baseline = new Map();

        for (const number of this.channelsForGroup(key)) {
            const strip = this.stripForChannel(number);

            if (strip) {
                baseline.set(number, readStripLevelDb(strip));
            }
        }

        const averageDb = baseline.size > 0
            ? [...baseline.values()].reduce((sum, level) => sum + level, 0) / baseline.size
            : 0;

        this.groupBaselines.set(key, baseline);
        this.groupBaselineAverageDb.set(key, averageDb);
        this.groupTrimDb.set(key, 0);
        this.syncGroupStripFaderDisplayForKey(key);
    }

    syncGroupStripFaderDisplayForKey(key) {
        const groupStrip = this.groupStripForKey(key);

        if (groupStrip) {
            syncGroupStripFaderDisplay(groupStrip, this.groupFaderDisplayForKey(key));
        }
    }

    applyGroupTrim(key, trimOffsetDb) {
        const baseline = this.baselineForGroup(key);

        if (!baseline || baseline.size === 0) {
            return;
        }

        this.groupTrimDb.set(key, trimOffsetDb);

        const levels = applyGroupTrimLevels(Object.fromEntries(baseline), trimOffsetDb);

        for (const [channel, levelDb] of Object.entries(levels)) {
            const strip = this.stripForChannel(Number(channel));

            if (strip) {
                applyStripLevel(strip, levelDb);
            }
        }

        this.syncGroupStripFaderDisplayForKey(key);
    }

    syncGroupStripDisplays() {
        for (const groupStrip of this.groupStrips) {
            const key = groupStrip.getAttribute('data-group-key') ?? '';

            syncGroupStripFaderDisplay(groupStrip, this.groupFaderDisplayForKey(key));
            this.syncGroupMuteVisual(groupStrip, key);
        }
    }

    muteEnabledChannelsForGroup(key) {
        return this.channelsForGroup(key).filter((number) => {
            const strip = this.stripForChannel(number);

            return strip?.dataset.sendMuteEnabled === 'true';
        });
    }

    groupMuteState(key) {
        const channels = this.muteEnabledChannelsForGroup(key);

        if (channels.length === 0) {
            return 'none';
        }

        const mutedCount = channels.filter((number) => isStripMonitorMuted(this.stripForChannel(number))).length;

        if (mutedCount === 0) {
            return 'off';
        }

        if (mutedCount === channels.length) {
            return 'all';
        }

        return 'partial';
    }

    syncGroupMuteVisual(groupStrip, key) {
        const button = groupStrip.querySelector('[data-group-mute]');

        if (!button) {
            return;
        }

        const channels = this.muteEnabledChannelsForGroup(key);
        const hasMembers = channels.length > 0;
        const { available } = sendControlConfig(getSendControlRoot());

        button.hidden = !hasMembers;
        button.disabled = !available || !hasMembers;

        const state = this.groupMuteState(key);

        button.classList.toggle('is-muted', state === 'all');
        button.classList.toggle('is-partial-muted', state === 'partial');
        button.setAttribute('aria-pressed', state === 'all' ? 'true' : 'false');

        if (state === 'all') {
            button.title = `Unmute all channels in ${groupStrip.getAttribute('data-group-label') ?? 'group'}`;
        } else if (state === 'partial') {
            button.title = `Mute all channels in ${groupStrip.getAttribute('data-group-label') ?? 'group'} (some channels are muted)`;
        } else {
            button.title = `Mute all channels in ${groupStrip.getAttribute('data-group-label') ?? 'group'}`;
        }

        groupStrip.classList.remove('is-group-mute-error');
        delete groupStrip.dataset.groupMuteError;
    }

    async commitGroupMute(key) {
        const root = getSendControlRoot();
        const channels = this.muteEnabledChannelsForGroup(key);
        const groupStrip = this.groupStripForKey(key);

        if (!root || channels.length === 0 || !groupStrip) {
            return;
        }

        const state = this.groupMuteState(key);
        const targetMuted = state !== 'all';
        const results = [];

        for (const number of channels) {
            const strip = this.stripForChannel(number);

            if (!strip) {
                continue;
            }

            results.push(await commitChannelMute(root, strip, targetMuted));
        }

        const successes = results.filter((result) => result?.success);
        const failures = results.filter((result) => !result?.success);

        this.syncGroupMuteVisual(groupStrip, key);

        if (failures.length > 0) {
            groupStrip.classList.add('is-group-mute-error');
            groupStrip.dataset.groupMuteError = failures.length === results.length
                ? 'Group mute failed — no channels were confirmed by the console.'
                : `Group mute incomplete — ${successes.length} of ${results.length} channels confirmed.`;

            return;
        }

        groupStrip.classList.remove('is-group-mute-error');
        delete groupStrip.dataset.groupMuteError;
    }

    syncAssignmentLabels() {
        for (const button of this.selectButtons) {
            const key = button.getAttribute('data-group-key') ?? '';

            if (key === '') {
                continue;
            }

            const label = button.getAttribute('data-group-label') ?? key;
            const count = this.channelsForGroup(key).length;
            const channels = this.channelsForGroup(key);

            button.setAttribute('data-group-channels', channels.join(','));
            button.textContent = count > 0 ? `${label} (${count})` : label;
            button.title = count > 0
                ? `Assign, focus, or clear ${label}`
                : `Assign selected channels to ${label}`;
        }
    }

    pickedChannelsInGroup(key) {
        return [...this.pickedChannels].filter((number) => this.assignments.get(key)?.has(number));
    }

    canRemovePickedFromGroup(key) {
        return this.viewMode === 'all' && key !== '' && this.pickedChannelsInGroup(key).length > 0;
    }

    removeChannelsFromGroup(key, channelNumbers) {
        const target = this.assignments.get(key);

        if (!target) {
            return;
        }

        for (const number of channelNumbers) {
            target.delete(number);
        }

        for (const number of channelNumbers) {
            this.pickedChannels.delete(number);
        }

        this.groupBaselines.delete(key);
        this.groupBaselineAverageDb.delete(key);
        this.groupTrimDb.delete(key);

        this.syncAssignmentLabels();
        this.syncGroupStripDisplays();
        this.refreshStripVisibility();
        this.updatePickUi();
        this.focusGroup(key);
    }

    clearGroup(key) {
        this.removeChannelsFromGroup(key, this.channelsForGroup(key));
    }

    removePickFromActiveGroup() {
        if (this.activeKey === '') {
            return;
        }

        this.removeChannelsFromGroup(this.activeKey, this.pickedChannelsInGroup(this.activeKey));
    }

    refreshAssignmentVisuals() {
        for (const strip of this.channelStrips) {
            const number = parseInt(strip.getAttribute('data-channel') ?? '0', 10);
            const groupKey = this.groupKeyForChannel(number);
            const badge = strip.querySelector('[data-group-control-badge]');
            const button = groupKey ? this.groupButtonForKey(groupKey) : null;
            const label = button?.getAttribute('data-group-label') ?? '';

            strip.classList.toggle('is-group-assigned', groupKey !== '');
            strip.setAttribute('data-assigned-group-key', groupKey);

            if (badge) {
                if (groupKey !== '' && this.viewMode === 'all') {
                    badge.hidden = false;
                    badge.textContent = label;
                } else {
                    badge.hidden = true;
                    badge.textContent = '';
                }
            }
        }
    }

    refreshStripVisibility() {
        const isGroupView = this.viewMode === 'group';

        this.channelsRoot?.classList.toggle('is-group-view', isGroupView);

        for (const groupStrip of this.groupStrips) {
            const key = groupStrip.getAttribute('data-group-key') ?? '';
            const hasMembers = this.channelsForGroup(key).length > 0;
            groupStrip.hidden = !(isGroupView && hasMembers);
        }

        for (const strip of this.channelStrips) {
            const number = parseInt(strip.getAttribute('data-channel') ?? '0', 10);
            const grouped = this.groupKeyForChannel(number) !== '';

            if (isGroupView) {
                strip.hidden = grouped;
            } else {
                strip.hidden = false;
            }
        }

        this.refreshAssignmentVisuals();
    }

    setViewMode(mode) {
        this.viewMode = mode === 'group' ? 'group' : 'all';

        for (const button of this.viewButtons) {
            const isActive = button.getAttribute('data-channels-view') === this.viewMode;
            button.classList.toggle('is-active', isActive);
            button.setAttribute('aria-pressed', isActive ? 'true' : 'false');
        }

        if (this.pickHintEl) {
            this.pickHintEl.textContent = this.viewMode === 'group'
                ? 'Group View — one fader per group plus ungrouped channels. Use Clear on a group strip to ungroup all members.'
                : 'All Channels — click strips to select, then choose a group to assign. Select members and click that group again to remove.';
        }

        if (this.viewMode === 'group') {
            this.clearPick();
        }

        this.refreshStripVisibility();
        this.syncGroupStripDisplays();
        this.updatePickUi();
    }

    updatePickUi() {
        const count = this.pickedChannels.size;
        const pickEnabled = this.viewMode === 'all';

        for (const strip of this.channelStrips) {
            const number = parseInt(strip.getAttribute('data-channel') ?? '0', 10);
            strip.classList.toggle('is-group-pick-selected', pickEnabled && this.pickedChannels.has(number));
            strip.classList.toggle('is-pick-disabled', !pickEnabled);
            strip.setAttribute('aria-pressed', pickEnabled && this.pickedChannels.has(number) ? 'true' : 'false');
        }

        if (this.pickCountEl) {
            this.pickCountEl.hidden = !pickEnabled || count === 0;
            this.pickCountEl.textContent = count === 1 ? '1 channel selected' : `${count} channels selected`;
        }

        if (this.selectionStatusEl) {
            const statusText = this.selectionStatusText(count, pickEnabled);

            this.selectionStatusEl.textContent = statusText;

            if (this.groupMenuHintEl) {
                this.groupMenuHintEl.textContent = this.groupMenuHintText(count, pickEnabled);
            }
        }

        if (this.clearPickButton) {
            this.clearPickButton.hidden = !pickEnabled || count === 0;
        }

        if (this.removeFromButton) {
            this.removeFromButton.hidden = !this.canRemovePickedFromGroup(this.activeKey);
        }

        if (this.clearGroupButton) {
            const canClear = this.activeKey !== '' && this.channelsForGroup(this.activeKey).length > 0;
            this.clearGroupButton.hidden = !canClear;
        }

        for (const button of this.selectButtons) {
            if (button.getAttribute('data-group-key') === '') {
                continue;
            }

            const key = button.getAttribute('data-group-key') ?? '';
            const pickedInGroup = this.pickedChannelsInGroup(key).length;
            const allPickedInGroup = pickEnabled && pickedInGroup > 0 && pickedInGroup === count;

            button.classList.toggle('is-assign-ready', pickEnabled && count > 0 && !allPickedInGroup);
            button.classList.toggle('is-remove-ready', allPickedInGroup);
        }
    }

    selectionStatusText(count, pickEnabled = this.viewMode === 'all') {
        if (this.viewMode === 'group') {
            return this.activeKey !== '' && this.channelsForGroup(this.activeKey).length > 0
                ? `${this.activeLabel} — use Clear group or the strip Clear button to ungroup all members`
                : 'Group View — assign groups in All Channels, then return here to mix by group';
        }

        if (count === 0) {
            return 'No channels selected';
        }

        const removable = this.activeKey !== '' ? this.pickedChannelsInGroup(this.activeKey).length : 0;

        if (removable === count && this.activeKey !== '') {
            return count === 1
                ? `1 channel selected — remove from ${this.activeLabel} or click ${this.activeLabel} again`
                : `${count} channels selected — remove from ${this.activeLabel} or click ${this.activeLabel} again`;
        }

        if (count === 1) {
            return '1 channel selected — choose a group to combine into one fader';
        }

        return `${count} channels selected — choose a group to combine into one fader`;
    }

    groupMenuHintText(count, pickEnabled = this.viewMode === 'all') {
        if (this.viewMode === 'group') {
            if (this.activeKey !== '' && this.channelsForGroup(this.activeKey).length > 0) {
                return this.activeLabel;
            }

            return 'Mix by group';
        }

        if (!pickEnabled || count === 0) {
            return this.activeKey !== '' && this.activeLabel !== ''
                ? `Focus: ${this.activeLabel}`
                : 'Tap to assign groups';
        }

        if (this.activeKey !== '' && this.activeLabel !== '') {
            return count === 1
                ? `1 selected · ${this.activeLabel}`
                : `${count} selected · ${this.activeLabel}`;
        }

        return count === 1 ? '1 channel selected' : `${count} channels selected`;
    }

    clearPick() {
        this.pickedChannels.clear();
        this.updatePickUi();
    }

    togglePick(strip) {
        if (this.viewMode !== 'all') {
            return;
        }

        const number = parseInt(strip.getAttribute('data-channel') ?? '0', 10);

        if (this.pickedChannels.has(number)) {
            this.pickedChannels.delete(number);
        } else {
            this.pickedChannels.add(number);
        }

        this.updatePickUi();
    }

    assignPickToGroup(key) {
        const target = this.assignments.get(key);

        if (!target) {
            return;
        }

        for (const number of this.pickedChannels) {
            for (const channels of this.assignments.values()) {
                channels.delete(number);
            }

            target.add(number);
        }

        this.pickedChannels.clear();
        this.captureGroupBaseline(key);
        this.syncAssignmentLabels();
        this.syncGroupStripDisplays();
        this.refreshStripVisibility();
        this.updatePickUi();
        this.focusGroup(key);
    }

    focusGroup(key) {
        const previousKey = this.activeKey;
        const fromAllChannelsView = this.viewMode === 'all';

        this.activeKey = key;
        const button = this.groupButtonForKey(key);
        this.activeLabel = key === ''
            ? ''
            : (button?.getAttribute('data-group-label') ?? '');

        if (key !== '' && this.channelsForGroup(key).length > 0) {
            if (key !== previousKey || fromAllChannelsView) {
                this.captureGroupBaseline(key);
            }
        }

        for (const candidate of this.selectButtons) {
            const isActive = candidate.getAttribute('data-group-key') === key;
            candidate.classList.toggle('is-active', isActive);
            candidate.setAttribute('aria-pressed', isActive ? 'true' : 'false');
        }

        for (const groupStrip of this.groupStrips) {
            const stripKey = groupStrip.getAttribute('data-group-key') ?? '';
            groupStrip.classList.toggle('is-focused', stripKey === key && key !== '');
        }

        this.updatePickUi();
        this.syncGroupStripDisplays();
    }

    refreshChannelMuteVisuals() {
        for (const strip of this.channelStrips) {
            applyChannelMuteVisual(strip, isStripMonitorMuted(strip));
        }
    }

    handleGroupButtonClick(key) {
        if (key === '') {
            this.clearPick();
            this.focusGroup('');

            return;
        }

        if (this.viewMode === 'all' && this.pickedChannels.size > 0) {
            const picked = [...this.pickedChannels];
            const allInTarget = picked.every((number) => this.assignments.get(key)?.has(number));

            if (allInTarget) {
                this.removeChannelsFromGroup(key, picked);

                return;
            }

            this.assignPickToGroup(key);

            return;
        }

        this.focusGroup(key);
    }

    bind() {
        for (const strip of this.channelStrips) {
            strip.addEventListener('click', (event) => {
                if (event.target.closest('a')) {
                    return;
                }

                if (event.target.closest('[data-channel-fader-control]')) {
                    return;
                }

                if (event.target.closest('[data-channel-mute]')) {
                    return;
                }

                event.preventDefault();
                this.togglePick(strip);
            });
        }

        for (const button of this.viewButtons) {
            button.addEventListener('click', () => {
                this.setViewMode(button.getAttribute('data-channels-view') ?? 'all');
            });
        }

        for (const button of this.selectButtons) {
            button.addEventListener('click', () => {
                this.handleGroupButtonClick(button.getAttribute('data-group-key') ?? '');
            });
        }

        this.clearPickButton?.addEventListener('click', () => {
            this.clearPick();
        });

        this.removeFromButton?.addEventListener('click', () => {
            this.removePickFromActiveGroup();
        });

        this.clearGroupButton?.addEventListener('click', () => {
            if (this.activeKey !== '') {
                this.clearGroup(this.activeKey);
            }
        });

        for (const groupStrip of this.groupStrips) {
            groupStrip.querySelector('[data-group-clear]')?.addEventListener('click', (event) => {
                event.stopPropagation();
                this.clearGroup(groupStrip.getAttribute('data-group-key') ?? '');
            });

            groupStrip.querySelector('[data-group-mute]')?.addEventListener('click', (event) => {
                event.preventDefault();
                event.stopPropagation();
                this.commitGroupMute(groupStrip.getAttribute('data-group-key') ?? '');
            });

            const track = groupStrip.querySelector('[data-group-fader-track]');
            const handle = groupStrip.querySelector('[data-group-fader-handle]');

            if (!track || !handle) {
                continue;
            }

            handle.addEventListener('pointerdown', (event) => {
                event.preventDefault();
                const groupKey = groupStrip.getAttribute('data-group-key') ?? '';

                if (!this.baselineForGroup(groupKey)) {
                    this.captureGroupBaseline(groupKey);
                }

                handle.setPointerCapture(event.pointerId);
                this.dragState = {
                    pointerId: event.pointerId,
                    groupKey,
                    track,
                };
            });
        }

        document.addEventListener('pointermove', (event) => {
            if (!this.dragState || event.pointerId !== this.dragState.pointerId) {
                return;
            }

            const rect = this.dragState.track.getBoundingClientRect();
            const pct = clamp(((rect.bottom - event.clientY) / rect.height) * 100, 0, 100);
            const groupKey = this.dragState.groupKey;
            const faderDb = trimOffsetDbFromFaderPct(pct);
            const trimOffsetDb = trimOffsetFromGroupFaderDb(faderDb, this.averageBaselineForGroup(groupKey));

            this.applyGroupTrim(groupKey, trimOffsetDb);
        });

        document.addEventListener('pointerup', (event) => {
            if (!this.dragState || event.pointerId !== this.dragState.pointerId) {
                return;
            }

            this.dragState = null;
        });

        document.addEventListener('pointercancel', (event) => {
            if (!this.dragState || event.pointerId !== this.dragState.pointerId) {
                return;
            }

            this.dragState = null;
        });
    }
}

export function initMonitorsGroupControls(root = document) {
    root.querySelectorAll('[data-monitors-group-control]').forEach((control) => {
        if (control.dataset.monitorsGroupControlBound === 'true') {
            return;
        }

        control.dataset.monitorsGroupControlBound = 'true';
        new MonitorsGroupControl(control);
    });
}

function bootMonitorsGroupControls() {
    initMonitorsGroupControls();
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bootMonitorsGroupControls);
} else {
    bootMonitorsGroupControls();
}
