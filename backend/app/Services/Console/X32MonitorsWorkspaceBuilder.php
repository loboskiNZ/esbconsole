<?php

namespace App\Services\Console;

use App\Services\X32\X32ChannelColorMap;
use App\Services\X32\X32FaderScale;

/**
 * Builds the PH043.03B2B monitor bus workspace from learned summary/configuration data.
 */
class X32MonitorsWorkspaceBuilder
{
    private const MONITOR_BUS_MIN = 1;

    private const MONITOR_BUS_MAX = 16;

    private const CHANNEL_COUNT = 32;

    /** @var list<array{key: string, label: string}> */
    private const MONITOR_SEND_GROUPS = [
        ['key' => 'drumkit', 'label' => 'Drumkit'],
        ['key' => 'bass', 'label' => 'Bass'],
        ['key' => 'guitars', 'label' => 'Guitars'],
        ['key' => 'keys', 'label' => 'Keys'],
        ['key' => 'vocals', 'label' => 'Vocals'],
        ['key' => 'horns', 'label' => 'Horns'],
        ['key' => 'tracks', 'label' => 'Tracks'],
        ['key' => 'talkback', 'label' => 'Talkback'],
    ];

    public function __construct(
        private readonly X32MonitorBusMasterEqCardBuilder $busMasterEqCardBuilder,
    ) {}

    /**
     * @param  array<string, mixed>  $summary
     * @return array<string, mixed>
     */
    public function build(array $summary, int $busNumber, ?int $selectedChannel = null): array
    {
        if ($busNumber < self::MONITOR_BUS_MIN || $busNumber > self::MONITOR_BUS_MAX) {
            throw new \InvalidArgumentException('Invalid monitor bus number.');
        }

        $configuration = is_array($summary['configuration'] ?? null) ? $summary['configuration'] : null;
        $sidebarBuses = $this->buildSidebarBuses($summary, $configuration);
        $activeBus = $this->findBus($sidebarBuses, $busNumber)
            ?? $this->fallbackBusRow($busNumber);

        if ($this->findBus($sidebarBuses, $busNumber) === null && ! $this->busExistsInRawData($summary, $configuration, $busNumber)) {
            throw new \InvalidArgumentException('Monitor bus not available.');
        }

        $busName = (string) $activeBus['display_name'];
        $selectedChannel = ($selectedChannel !== null && $selectedChannel >= 1 && $selectedChannel <= self::CHANNEL_COUNT)
            ? $selectedChannel
            : null;

        return [
            'header' => [
                'context' => 'ESB Console',
                'title' => $busName,
                'status_label' => $this->resolveWorkspaceStatusLabel($configuration),
                'status_state' => $this->resolveWorkspaceStatusState($configuration),
            ],
            'sidebar' => [
                'title' => 'Buses (Monitors)',
                'available_count' => count($sidebarBuses),
                'buses' => $sidebarBuses,
                'footer_note' => 'Main LR and mains buses are not shown. Use Routing to view Main LR assignments.',
            ],
            'active_bus_number' => $busNumber,
            'active_bus_name' => $busName,
            'selected_channel_number' => $selectedChannel,
            'eq' => $this->buildEqCard($busName, $configuration, $summary, $busNumber),
            'channels' => $this->buildChannelsCard($summary, $configuration, $busNumber, $busName),
            'channel_settings' => $this->buildChannelSettingsCard($busName, $busNumber, $selectedChannel, $summary, $configuration),
            'group_control' => $this->buildGroupControlCard(),
            'bus_master' => $this->buildBusMasterCard($summary, $configuration, $busNumber, $busName),
        ];
    }

    /**
     * @param  array<string, mixed>  $summary
     * @param  array<string, mixed>|null  $configuration
     * @return list<array{number: int, display_name: string, learned_name: string|null, is_active: bool}>
     */
    private function buildSidebarBuses(array $summary, ?array $configuration): array
    {
        $rows = [];

        foreach ($this->configuredBuses($summary, $configuration) as $bus) {
            $number = (int) ($bus['number'] ?? 0);

            if ($number < self::MONITOR_BUS_MIN || $number > self::MONITOR_BUS_MAX) {
                continue;
            }

            $learnedName = $this->learnedBusName($bus);
            $purpose = $this->fieldValue($bus['purpose'] ?? null);

            if ($this->isExcludedMainBus($learnedName, $purpose)) {
                continue;
            }

            $rows[$number] = [
                'number' => $number,
                'display_name' => $learnedName ?? sprintf('Bus %d', $number),
                'learned_name' => $learnedName,
                'is_active' => false,
            ];
        }

        if ($rows === []) {
            for ($number = self::MONITOR_BUS_MIN; $number <= self::MONITOR_BUS_MAX; $number++) {
                $rows[$number] = $this->fallbackBusRow($number);
            }
        }

        ksort($rows);

        return array_values($rows);
    }

    /**
     * @param  array<string, mixed>  $summary
     * @param  array<string, mixed>|null  $configuration
     * @return list<array<string, mixed>>
     */
    private function configuredBuses(array $summary, ?array $configuration): array
    {
        $configured = is_array($configuration['buses'] ?? null) ? $configuration['buses'] : [];

        if ($configured !== []) {
            return $configured;
        }

        $summaryBuses = is_array($summary['buses'] ?? null) ? $summary['buses'] : [];

        return array_map(function (array $bus): array {
            $number = (int) ($bus['index'] ?? $bus['number'] ?? 0);

            return [
                'number' => $number,
                'name' => $this->learnedFieldEnvelope(
                    (string) ($bus['name'] ?? ''),
                    ! $this->isGenericBusName((string) ($bus['name'] ?? ''), $number),
                ),
                'mute' => $this->learnedFieldEnvelope((bool) ($bus['mute'] ?? false), array_key_exists('mute', $bus)),
                'fader' => $this->learnedFieldEnvelope($bus['fader'] ?? null, array_key_exists('fader', $bus)),
            ];
        }, array_values(array_filter($summaryBuses, 'is_array')));
    }

    /**
     * @param  list<array{number: int, display_name: string, learned_name: string|null, is_active: bool}>  $buses
     * @return array{number: int, display_name: string, learned_name: string|null, is_active: bool}|null
     */
    private function findBus(array $buses, int $busNumber): ?array
    {
        foreach ($buses as $bus) {
            if ((int) $bus['number'] === $busNumber) {
                return $bus;
            }
        }

        return null;
    }

    /**
     * @return array{number: int, display_name: string, learned_name: string|null, is_active: bool}
     */
    private function fallbackBusRow(int $number): array
    {
        return [
            'number' => $number,
            'display_name' => sprintf('Bus %d', $number),
            'learned_name' => null,
            'is_active' => false,
        ];
    }

    /**
     * @param  array<string, mixed>  $summary
     * @param  array<string, mixed>|null  $configuration
     */
    private function busExistsInRawData(array $summary, ?array $configuration, int $busNumber): bool
    {
        foreach ($this->configuredBuses($summary, $configuration) as $bus) {
            if ((int) ($bus['number'] ?? 0) === $busNumber) {
                return true;
            }
        }

        return $busNumber >= self::MONITOR_BUS_MIN && $busNumber <= self::MONITOR_BUS_MAX;
    }

    /**
     * @param  array<string, mixed>  $bus
     */
    private function learnedBusName(array $bus): ?string
    {
        $name = trim((string) ($this->fieldValue($bus['name'] ?? null) ?? ''));

        if ($name === '') {
            return null;
        }

        $number = (int) ($bus['number'] ?? 0);

        if ($this->isGenericBusName($name, $number)) {
            return null;
        }

        return $name;
    }

    private function isGenericBusName(string $name, int $number): bool
    {
        $normalized = trim($name);

        return preg_match('/^bus\s*0?'.$number.'$/i', $normalized) === 1
            || preg_match('/^bus\s*'.str_pad((string) $number, 2, '0', STR_PAD_LEFT).'$/i', $normalized) === 1;
    }

    private function isExcludedMainBus(?string $learnedName, ?string $purpose): bool
    {
        foreach ([$learnedName, $purpose] as $candidate) {
            if ($candidate === null || trim($candidate) === '') {
                continue;
            }

            $normalized = mb_strtolower(trim($candidate));

            if (preg_match('/\b(main\s*lr|main\s*l\/r|main\s*left|main\s*right|mains|foh\s*main)\b/', $normalized) === 1) {
                return true;
            }

            if (preg_match('/^main(\s|$)/', $normalized) === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>|null  $configuration
     * @param  array<string, mixed>  $summary
     * @return array<string, mixed>
     */
    private function buildEqCard(string $busName, ?array $configuration, array $summary, int $busNumber): array
    {
        $bus = $this->resolveConfiguredBus($summary, $configuration, $busNumber);

        return $this->busMasterEqCardBuilder->build($busName, $bus);
    }

    /**
     * @param  array<string, mixed>  $summary
     * @param  array<string, mixed>|null  $configuration
     * @return array<string, mixed>
     */
    private function buildChannelsCard(array $summary, ?array $configuration, int $busNumber, string $busName): array
    {
        $channels = [];

        for ($number = 1; $number <= self::CHANNEL_COUNT; $number++) {
            $channel = $this->resolveConfiguredChannel($summary, $configuration, $number);
            $learnedName = $this->learnedChannelName($channel);
            $send = $this->resolveMonitorSend($channel, $busNumber);
            $sendLevelLearned = $this->fieldState($send['level'] ?? null) === 'learned';
            $sendOnLearned = $this->fieldState($send['on'] ?? null) === 'learned';

            $levelLinear = null;
            $levelDb = null;

            if ($sendLevelLearned) {
                $levelField = is_array($send['level'] ?? null) ? $send['level']['value'] : null;

                if (is_array($levelField)) {
                    $levelLinear = is_numeric($levelField['linear'] ?? null) ? (float) $levelField['linear'] : null;
                    $levelDb = is_numeric($levelField['value'] ?? null) ? (float) $levelField['value'] : null;
                } elseif (is_numeric($levelField)) {
                    $levelDb = (float) $levelField;
                }

                if ($levelDb === null && $levelLinear !== null) {
                    $levelDb = X32FaderScale::linearToDb($levelLinear);
                }
            }

            $colorIndex = $this->resolveChannelColorIndex($channel);
            $color = X32ChannelColorMap::resolve($colorIndex);

            $channels[] = [
                'number' => $number,
                'display_name' => $learnedName ?? sprintf('CH %d', $number),
                'learned_name' => $learnedName,
                'level_db' => $levelDb,
                'level_display' => $sendLevelLearned && $levelDb !== null
                    ? X32FaderScale::formatDb($levelDb)
                    : '—',
                'send_learned' => $sendLevelLearned,
                'send_on_learned' => $sendOnLearned,
                'mute' => $sendOnLearned
                    ? ! ((bool) $this->fieldValue($send['on'] ?? null))
                    : false,
                'mute_scope_label' => sprintf('Monitor mute · %s · CH %d', $busName, $number),
                'send_state' => $sendLevelLearned ? 'learned' : 'placeholder',
                'group_keys' => [],
                'color_index' => $color['index'],
                'color_css' => $color['css'],
                'color_text' => $color['text'],
                'color_label' => $color['label'],
                'color_learned' => $this->channelColorLearned($channel),
            ];
        }

        return [
            'title' => 'Channels',
            'bus_number' => $busNumber,
            'bus_name' => $busName,
            'strips' => $channels,
        ];
    }

    /**
     * @param  array<string, mixed>  $summary
     * @param  array<string, mixed>|null  $configuration
     * @return array<string, mixed>
     */
    private function buildChannelSettingsCard(
        string $busName,
        int $busNumber,
        ?int $selectedChannel,
        array $summary,
        ?array $configuration,
    ): array {
        $title = sprintf('%s — Channel Settings', $busName);

        if ($selectedChannel === null) {
            return [
                'title' => $title,
                'empty' => true,
                'empty_message' => sprintf(
                    'Select a channel to edit monitor-send settings for %s.',
                    $busName,
                ),
                'rows' => [],
            ];
        }

        $channel = $this->resolveConfiguredChannel($summary, $configuration, $selectedChannel);
        $learnedName = $this->learnedChannelName($channel) ?? sprintf('CH %d', $selectedChannel);
        $send = $this->resolveMonitorSend($channel, $busNumber);
        $sendLevelLearned = $this->fieldState($send['level'] ?? null) === 'learned';

        $rows = [
            ['label' => 'Channel', 'value' => sprintf('%d · %s', $selectedChannel, $learnedName)],
            ['label' => 'Monitor bus', 'value' => $busName],
        ];

        if ($sendLevelLearned) {
            $levelField = is_array($send['level']['value'] ?? null) ? $send['level']['value'] : null;
            $levelDb = is_array($levelField)
                ? ($levelField['value'] ?? null)
                : $this->fieldValue($send['level'] ?? null);
            $rows[] = [
                'label' => 'Send level',
                'value' => is_numeric($levelDb) ? X32FaderScale::formatDb((float) $levelDb).' dB' : '—',
            ];

            if ($this->fieldState($send['tap'] ?? null) === 'learned') {
                $rows[] = ['label' => 'Send tap', 'value' => (string) $this->fieldValue($send['tap'])];
            }

            if ($this->fieldState($send['pan'] ?? null) === 'learned') {
                $rows[] = ['label' => 'Send pan', 'value' => (string) $this->fieldValue($send['pan'])];
            }
        } else {
            $rows[] = ['label' => 'Send settings', 'value' => 'Not learned yet'];
        }

        return [
            'title' => $title,
            'empty' => false,
            'empty_message' => null,
            'rows' => $rows,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildGroupControlCard(): array
    {
        return [
            'title' => 'Group Control',
            'all_channels_view_label' => 'All Channels',
            'group_view_label' => 'Group View',
            'all_channels_label' => 'All Channels',
            'clear_selection_label' => 'Clear selection',
            'remove_from_group_label' => 'Remove from group',
            'clear_group_label' => 'Clear group',
            'groups' => array_map(
                static fn (array $group): array => [
                    'key' => $group['key'],
                    'label' => $group['label'],
                    'channels' => [],
                ],
                self::MONITOR_SEND_GROUPS,
            ),
            'scaffold_notice' => 'Group trim — visual only. Preserves relative channel balance. Group assignments are UI-only — not learned from the X32.',
        ];
    }

    /**
     * @param  array<string, mixed>  $channel
     * @return array<string, mixed>|null
     */
    private function resolveMonitorSend(array $channel, int $busNumber): ?array
    {
        $sends = is_array($channel['sends'] ?? null) ? $channel['sends'] : null;

        if ($sends === null) {
            return null;
        }

        $buses = is_array($sends['buses'] ?? null) ? $sends['buses'] : [];
        $key = (string) $busNumber;

        if (is_array($buses[$key] ?? null)) {
            return $buses[$key];
        }

        if (is_array($buses[$busNumber] ?? null)) {
            return $buses[$busNumber];
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $summary
     * @param  array<string, mixed>|null  $configuration
     * @return array<string, mixed>
     */
    private function buildBusMasterCard(array $summary, ?array $configuration, int $busNumber, string $busName): array
    {
        $bus = $this->resolveConfiguredBus($summary, $configuration, $busNumber);
        $fader = $this->fieldValue($bus['fader'] ?? null);
        $levelDb = is_numeric($fader) ? X32FaderScale::linearToDb((float) $fader) : null;

        return [
            'title' => 'Bus Master',
            'bus_number' => $busNumber,
            'bus_name' => $busName,
            'scope_hint' => sprintf('Master level for %s only.', $busName),
            'level_db' => $levelDb,
            'level_display' => $levelDb !== null ? X32FaderScale::formatDb($levelDb).' dB' : '—',
            'mute' => (bool) ($this->fieldValue($bus['mute'] ?? null) ?? false),
            'learned' => $this->fieldState($bus['fader'] ?? null) === 'learned',
        ];
    }

    /**
     * @param  array<string, mixed>|null  $configuration
     * @param  array<string, mixed>  $summary
     * @return array<string, mixed>
     */
    private function resolveConfiguredBus(array $summary, ?array $configuration, int $busNumber): array
    {
        foreach ($this->configuredBuses($summary, $configuration) as $bus) {
            if ((int) ($bus['number'] ?? 0) === $busNumber) {
                return $bus;
            }
        }

        foreach (is_array($summary['buses'] ?? null) ? $summary['buses'] : [] as $bus) {
            if (! is_array($bus)) {
                continue;
            }

            if ((int) ($bus['index'] ?? 0) === $busNumber) {
                return [
                    'number' => $busNumber,
                    'name' => $this->learnedFieldEnvelope(
                        (string) ($bus['name'] ?? ''),
                        ! $this->isGenericBusName((string) ($bus['name'] ?? ''), $busNumber),
                    ),
                    'mute' => $this->learnedFieldEnvelope((bool) ($bus['mute'] ?? false), array_key_exists('mute', $bus)),
                    'fader' => $this->learnedFieldEnvelope($bus['fader'] ?? null, array_key_exists('fader', $bus)),
                ];
            }
        }

        return ['number' => $busNumber];
    }

    /**
     * @param  array<string, mixed>|null  $configuration
     * @param  array<string, mixed>  $summary
     * @return array<string, mixed>
     */
    private function resolveConfiguredChannel(array $summary, ?array $configuration, int $channelNumber): array
    {
        $configured = is_array($configuration['channels'] ?? null) ? $configuration['channels'] : [];

        foreach ($configured as $channel) {
            if ((int) ($channel['number'] ?? 0) === $channelNumber) {
                return $channel;
            }
        }

        foreach (is_array($summary['channels'] ?? null) ? $summary['channels'] : [] as $channel) {
            if (! is_array($channel)) {
                continue;
            }

            if ((int) ($channel['index'] ?? 0) === $channelNumber) {
                return [
                    'number' => $channelNumber,
                    'name' => $this->learnedFieldEnvelope(
                        (string) ($channel['name'] ?? ''),
                        ! $this->isGenericChannelName((string) ($channel['name'] ?? ''), $channelNumber),
                    ),
                    'colour' => $this->learnedFieldEnvelope(
                        array_key_exists('color', $channel) ? (int) $channel['color'] : null,
                        array_key_exists('color', $channel),
                    ),
                    'mute' => $this->learnedFieldEnvelope((bool) ($channel['mute'] ?? false), array_key_exists('mute', $channel)),
                    'fader' => $this->learnedFieldEnvelope($channel['fader'] ?? null, array_key_exists('fader', $channel)),
                ];
            }
        }

        return ['number' => $channelNumber];
    }

    /**
     * @param  array<string, mixed>  $channel
     */
    private function learnedChannelName(array $channel): ?string
    {
        $name = trim((string) ($this->fieldValue($channel['name'] ?? null) ?? ''));

        if ($name === '') {
            return null;
        }

        $number = (int) ($channel['number'] ?? 0);

        if ($this->isGenericChannelName($name, $number)) {
            return null;
        }

        return $name;
    }

    private function isGenericChannelName(string $name, int $number): bool
    {
        $normalized = trim($name);

        return preg_match('/^ch\s*0?'.$number.'$/i', $normalized) === 1
            || preg_match('/^ch\s*'.str_pad((string) $number, 2, '0', STR_PAD_LEFT).'$/i', $normalized) === 1;
    }

    /**
     * @param  array<string, mixed>  $channel
     */
    private function resolveChannelColorIndex(array $channel): int
    {
        $colour = $this->fieldValue($channel['colour'] ?? null);

        if (is_numeric($colour)) {
            return (int) $colour;
        }

        if (array_key_exists('color', $channel) && is_numeric($channel['color'])) {
            return (int) $channel['color'];
        }

        $color = $this->fieldValue($channel['color'] ?? null);

        if (is_numeric($color)) {
            return (int) $color;
        }

        return 0;
    }

    /**
     * @param  array<string, mixed>  $channel
     */
    private function channelColorLearned(array $channel): bool
    {
        if ($this->fieldState($channel['colour'] ?? null) === 'learned') {
            return true;
        }

        if ($this->fieldState($channel['color'] ?? null) === 'learned') {
            return true;
        }

        return array_key_exists('color', $channel) && is_numeric($channel['color']);
    }

    /**
     * @param  array<string, mixed>|null  $configuration
     */
    private function resolveWorkspaceStatusLabel(?array $configuration): string
    {
        if ($configuration === null) {
            return 'Not learned';
        }

        return match ($this->configurationAuditState($configuration)) {
            'complete' => 'Configuration learned',
            'needs_attention' => 'Needs attention',
            'not_learned' => 'Not learned',
            default => 'Partial configuration',
        };
    }

    /**
     * @param  array<string, mixed>|null  $configuration
     */
    private function resolveWorkspaceStatusState(?array $configuration): string
    {
        if ($configuration === null) {
            return 'not-learned';
        }

        return match ($this->configurationAuditState($configuration)) {
            'complete' => 'learned',
            'needs_attention' => 'suggested',
            'not_learned' => 'not-learned',
            default => 'suggested',
        };
    }

    /**
     * @param  array<string, mixed>  $configuration
     */
    private function configurationAuditState(array $configuration): string
    {
        foreach (['identity', 'channels', 'buses'] as $section) {
            if (! is_array($configuration[$section] ?? null)) {
                return 'not_learned';
            }
        }

        if (($configuration['warnings'] ?? []) !== []) {
            return 'needs_attention';
        }

        return 'partial';
    }

    /**
     * @return array{value: mixed, state: string, reason?: string}
     */
    private function learnedFieldEnvelope(mixed $value, bool $learned, ?string $reason = null): array
    {
        if ($learned) {
            return ['value' => $value, 'state' => 'learned'];
        }

        return ['value' => null, 'state' => 'not_learned', 'reason' => $reason ?? 'not_captured'];
    }

    private function fieldValue(mixed $field): mixed
    {
        if (! is_array($field) || ! array_key_exists('value', $field)) {
            return is_array($field) ? null : $field;
        }

        return $field['value'];
    }

    private function fieldState(mixed $field): string
    {
        if (is_array($field) && is_string($field['state'] ?? null)) {
            return (string) $field['state'];
        }

        return 'not_learned';
    }
}
