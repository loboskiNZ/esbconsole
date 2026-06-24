<?php

namespace App\Services\Recovery;

class RecoveryDomainRegistry
{
  /** @var list<array{key: string, tables: list<string>, depends: list<string>, export: bool}> */
  public const DOMAINS = [
    ['key' => 'reference', 'tables' => ['song_moods', 'time_signatures', 'musical_keys'], 'depends' => [], 'export' => false],
    ['key' => 'bands', 'tables' => ['bands'], 'depends' => [], 'export' => true],
    ['key' => 'people', 'tables' => ['people', 'person_secure_fields', 'person_files', 'person_iem_settings', 'person_instruments'], 'depends' => ['bands'], 'export' => true],
    ['key' => 'users', 'tables' => ['users'], 'depends' => ['bands', 'people'], 'export' => true],
    ['key' => 'musicians', 'tables' => ['musicians', 'musician_band_roles'], 'depends' => ['bands', 'users'], 'export' => true],
    ['key' => 'instrument_parts', 'tables' => ['instrument_parts'], 'depends' => ['bands'], 'export' => false],
    ['key' => 'import_audit', 'tables' => ['import_batches', 'import_entity_mappings'], 'depends' => ['bands', 'users'], 'export' => false],
    ['key' => 'songs', 'tables' => ['songs'], 'depends' => ['bands'], 'export' => true],
    ['key' => 'cues', 'tables' => ['cues'], 'depends' => ['songs'], 'export' => false],
    ['key' => 'charts', 'tables' => ['charts'], 'depends' => ['songs'], 'export' => true],
    ['key' => 'song_instrument_parts', 'tables' => ['song_instrument_parts'], 'depends' => ['songs', 'instrument_parts', 'charts'], 'export' => false],
    ['key' => 'snippets', 'tables' => ['snippets'], 'depends' => ['song_instrument_parts', 'cues'], 'export' => true],
    ['key' => 'actions', 'tables' => ['action_definitions', 'action_parameters', 'cue_actions'], 'depends' => ['bands', 'cues'], 'export' => false],
    ['key' => 'shows', 'tables' => ['ableton_show_files', 'shows', 'show_playlist_items'], 'depends' => ['bands', 'songs'], 'export' => true],
    ['key' => 'performances', 'tables' => ['performances', 'performance_assignments'], 'depends' => ['shows', 'musicians'], 'export' => true],
    ['key' => 'devices', 'tables' => ['devices', 'capabilities', 'assignments'], 'depends' => ['musicians', 'instrument_parts'], 'export' => true],
    ['key' => 'venues', 'tables' => ['venues', 'festivals'], 'depends' => ['bands'], 'export' => false],
    ['key' => 'effects', 'tables' => [
      'effect_package_types', 'effects', 'effect_parameters', 'effect_definitions',
      'effect_packages', 'effect_package_items', 'effect_package_item_parameters',
      'effect_package_item_target_sections', 'song_effect_assignments',
    ], 'depends' => ['songs'], 'export' => true],
    ['key' => 'console_baselines', 'tables' => ['show_console_baselines'], 'depends' => ['bands', 'shows'], 'export' => true],
    ['key' => 'mix_moves', 'tables' => ['mix_moves'], 'depends' => [], 'export' => false],
  ];

  /** @return list<array{key: string, tables: list<string>, depends: list<string>, export: bool}> */
  public function all(): array
  {
    return self::DOMAINS;
  }

  /** @return list<array{key: string, tables: list<string>, depends: list<string>, export: bool}> */
  public function exportable(): array
  {
    return array_values(array_filter(self::DOMAINS, fn (array $d) => $d['export']));
  }

  public function find(string $key): ?array
  {
    foreach (self::DOMAINS as $domain) {
      if ($domain['key'] === $key) {
        return $domain;
      }
    }

    return null;
  }

  /** @return list<string> */
  public function migrationOrder(): array
  {
    return array_map(fn (array $d) => $d['key'], self::DOMAINS);
  }

  /** @return list<string> */
  public function fileDomains(): array
  {
    return ['charts', 'snippets', 'people_profiles', 'person_files', 'ableton_show_files'];
  }
}
