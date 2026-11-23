<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * The tests run on SQLite; the site runs on MySQL, which refuses names of indexes and foreign keys over
 * 64 characters. A migration that breaks that rule only fails when it is deployed, so it is checked here.
 */
class SchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_index_and_foreign_key_name_fits_mysql(): void
    {
        $tooLong = [];
        foreach (Schema::getTableListing(schemaQualified: false) as $table) {
            foreach (Schema::getIndexes($table) as $index) {
                if (strlen($index['name']) > 64) {
                    $tooLong[] = $table.': '.$index['name'];
                }
            }
            foreach (Schema::getForeignKeys($table) as $key) {
                // SQLite does not name foreign keys; MySQL names them table_column_foreign
                $name = $key['name'] ?: $table.'_'.implode('_', $key['columns']).'_foreign';
                if (strlen($name) > 64) {
                    $tooLong[] = $table.': '.$name;
                }
            }
            if (strlen($table) > 64) {
                $tooLong[] = $table;
            }
        }

        $this->assertSame([], $tooLong, 'Names over 64 characters fail on MySQL.');
    }
}
