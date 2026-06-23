<?php

namespace App\Support;

use Illuminate\Support\Facades\Schema;

class StudioLibraryAvailability
{
    /** @var list<string> */
    private const REQUIRED_TABLES = [
        'songs',
        'charts',
        'instrument_parts',
        'song_instrument_parts',
    ];

    private ?bool $available = null;

    public function connectionName(): ?string
    {
        $connection = config('portal.library_connection');

        return filled($connection) ? (string) $connection : null;
    }

    public function schemaConnectionName(): string
    {
        return $this->connectionName() ?? (string) config('database.default');
    }

    public function isAvailable(): bool
    {
        if ($this->available !== null) {
            return $this->available;
        }

        try {
            $schema = Schema::connection($this->schemaConnectionName());

            foreach (self::REQUIRED_TABLES as $table) {
                if (! $schema->hasTable($table)) {
                    return $this->available = false;
                }
            }

            return $this->available = true;
        } catch (\Throwable) {
            return $this->available = false;
        }
    }
}
