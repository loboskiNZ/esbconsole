<?php

namespace App\Services\Recovery;

use Illuminate\Support\Facades\Schema;

class RecoverySchemaColumnFilter
{
  /** @param  array<string, mixed>  $row */
  public function filter(string $connection, string $table, array $row): array
  {
    if (! Schema::connection($connection)->hasTable($table)) {
      return $row;
    }

    $columns = Schema::connection($connection)->getColumnListing($table);

    return array_intersect_key($row, array_flip($columns));
  }
}
