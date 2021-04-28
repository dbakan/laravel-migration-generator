<?php

namespace Tests\Unit\Generators;

use Tests\TestCase;
use Illuminate\Support\Facades\Config;
use LaravelMigrationGenerator\Generators\MySQL\TableGenerator;

class MySQLTableGeneratorTest extends TestCase
{
    public function tearDown(): void
    {
        parent::tearDown();

        $path = __DIR__ . '/../../migrations';
        $this->cleanUpMigrations($path);
    }

    private function assertSchemaHas($str, $schema)
    {
        $this->assertStringContainsString($str, $schema);
    }

    public function test_runs_correctly()
    {
        $generator = TableGenerator::init('table', [
            '`id` int(9) unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY',
            '`user_id` int(9) unsigned NOT NULL',
            '`note` varchar(255) NOT NULL',
            'KEY `fk_user_id_idx` (`user_id`)',
            'CONSTRAINT `fk_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE'
        ]);

        $schema = $generator->getSchema();
        $this->assertSchemaHas('$table->increments(\'id\');', $schema);
        $this->assertSchemaHas('$table->unsignedInteger(\'user_id\');', $schema);
        $this->assertSchemaHas('$table->string(\'note\');', $schema);
        $this->assertSchemaHas('$table->foreign(\'user_id\', \'fk_user_id\')->references(\'id\')->on(\'users\')->onDelete(\'cascade\')->onUpdate(\'cascade\');', $schema);
    }

    private function cleanUpMigrations($path)
    {
        if (is_dir($path)) {
            foreach (glob($path . '/*.php') as $file) {
                unlink($file);
            }
            rmdir($path);
        }
    }

    public function test_writes()
    {
        Config::set('laravel-migration-generator.table_naming_scheme', '0000_00_00_000000_create_[TableName]_table.php');
        $generator = TableGenerator::init('table', [
            '`id` int(9) unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY',
            '`user_id` int(9) unsigned NOT NULL',
            '`note` varchar(255) NOT NULL',
            'KEY `fk_user_id_idx` (`user_id`)',
            'CONSTRAINT `fk_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE'
        ]);

        $path = __DIR__ . '/../../migrations';

        if (! is_dir($path)) {
            mkdir($path, 0777, true);
        }

        $generator->write($path);

        $this->assertFileExists($path . '/0000_00_00_000000_create_table_table.php');
    }

    public function test_cleans_up_regular_morphs()
    {
        $generator = TableGenerator::init('table', [
            '`id` int(9) unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY',
            '`user_id` int(9) unsigned NOT NULL',
            '`user_type` varchar(255) NOT NULL',
            '`note` varchar(255) NOT NULL'
        ]);

        $schema = $generator->getSchema();
        $this->assertSchemaHas('$table->morphs(\'user\');', $schema);
    }

    public function test_doesnt_clean_up_morph_looking_columns()
    {
        $generator = TableGenerator::init('table', [
            '`id` int(9) unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY',
            '`user_id` varchar(255) NOT NULL',
            '`user_type` varchar(255) NOT NULL',
            '`note` varchar(255) NOT NULL'
        ]);

        $schema = $generator->getSchema();
        $this->assertStringNotContainsString('$table->morphs(\'user\');', $schema);
    }

    public function test_cleans_up_uuid_morphs()
    {
        $generator = TableGenerator::init('table', [
            '`id` int(9) unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY',
            '`user_id` char(36) NOT NULL',
            '`user_type` varchar(255) NOT NULL',
            '`note` varchar(255) NOT NULL'
        ]);

        $schema = $generator->getSchema();
        $this->assertSchemaHas('$table->uuidMorphs(\'user\');', $schema);
    }

    public function test_cleans_up_uuid_morphs_nullable()
    {
        $generator = TableGenerator::init('table', [
            '`id` int(9) unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY',
            '`user_id` char(36) DEFAULT NULL',
            '`user_type` varchar(255) DEFAULT NULL',
            '`note` varchar(255) NOT NULL'
        ]);

        $schema = $generator->getSchema();
        $this->assertSchemaHas('$table->uuidMorphs(\'user\')->nullable();', $schema);
    }

    public function test_doesnt_clean_non_auto_inc_id_to_laravel_method()
    {
        $generator = TableGenerator::init('table', [
            '`id` int(9) unsigned NOT NULL',
            'PRIMARY KEY `id`'
        ]);

        $schema = $generator->getSchema();
        $this->assertSchemaHas('$table->unsignedInteger(\'id\')->primary();', $schema);
    }

    public function test_does_clean_auto_inc_int_to_laravel_method()
    {
        $generator = TableGenerator::init('table', [
            '`id` int(9) unsigned NOT NULL AUTO_INCREMENT',
            'PRIMARY KEY `id`'
        ]);

        $schema = $generator->getSchema();
        $this->assertSchemaHas('$table->increments(\'id\');', $schema);
    }

    public function test_does_clean_auto_inc_big_int_to_laravel_method()
    {
        $generator = TableGenerator::init('table', [
            '`id` bigint(12) unsigned NOT NULL AUTO_INCREMENT',
            'PRIMARY KEY `id`'
        ]);

        $schema = $generator->getSchema();
        $this->assertSchemaHas('$table->id();', $schema);
    }

    public function test_doesnt_clean_timestamps_with_use_current(){
        $generator = TableGenerator::init('table', [
            'id int auto_increment primary key',
            'created_at timestamp default CURRENT_TIMESTAMP not null',
            'updated_at timestamp null on update CURRENT_TIMESTAMP'
        ]);
        $schema = $generator->getSchema();
        $this->assertSchemaHas('$table->timestamp(\'created_at\')->nullable()->useCurrent()', $schema);
        $this->assertSchemaHas('$table->timestamp(\'updated_at\')->nullable()->useCurrentOnUpdate()', $schema);
    }
}
