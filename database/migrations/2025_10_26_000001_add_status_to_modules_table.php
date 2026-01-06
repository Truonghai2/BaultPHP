<?php

use Core\Schema\Blueprint;
use Core\Schema\Migration;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Cách 1: Sử dụng hasColumn (yêu cầu PDO sẵn sàng)
        try {
            if (!$this->schema->hasColumn('modules', 'status')) {
                $this->schema->table('modules', function (Blueprint $table) {
                    $table->string('status')->default('new')->after('enabled');
                });
            }
        } catch (\Throwable $e) {
            try {
                $this->schema->table('modules', function (Blueprint $table) {
                    $table->string('status')->default('new')->after('enabled');
                });
            } catch (\Throwable $e2) {
                if (!str_contains($e2->getMessage(), 'Duplicate column')) {
                    throw $e2;
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if ($this->schema->hasColumn('modules', 'status')) {
            $this->schema->table('modules', function (Blueprint $table) {
                $table->dropColumn('status');
            });
        }
    }
};

