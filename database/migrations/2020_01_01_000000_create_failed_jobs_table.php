<?php

use PDO;

/**
 * Migration to create the failed_jobs table.
 * This table stores information about queued jobs that have failed to process.
 */
class CreateFailedJobsTable
{
    /**
     * Run the migrations.
     */
    public function up(PDO $pdo): void
    {
        $pdo->exec('
            CREATE TABLE IF NOT EXISTS `failed_jobs` (
                `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `uuid` VARCHAR(36) NOT NULL UNIQUE,
                `connection` TEXT NOT NULL,
                `queue` TEXT NOT NULL,
                `payload` LONGTEXT NOT NULL,
                `exception` LONGTEXT NOT NULL,
                `failed_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }

    /**
     * Reverse the migrations.
     */
    public function down(PDO $pdo): void
    {
        $pdo->exec('DROP TABLE IF EXISTS `failed_jobs`');
    }
}
