<?php

namespace Core\Schema;

/**
 * The base class for all schema migrations.
 *
 * This class provides the basic structure for creating and reversing
 * database schema changes. Migrations extending this class will have
 * access to the schema builder via `$this->schema`.
 */
abstract class Migration
{
    /**
     * The schema manager instance.
     *
     * @var \Core\Schema\Schema
     */
    protected Schema $schema;

    /**
     * Set the schema manager instance.
     * This method is called by the MigrationManager before running the migration.
     *
     * @param  \Core\Schema\Schema  $schema
     * @return void
     */
    public function setSchema(Schema $schema): void
    {
        $this->schema = $schema;
    }

    abstract public function up(): void;
    abstract public function down(): void;
}
