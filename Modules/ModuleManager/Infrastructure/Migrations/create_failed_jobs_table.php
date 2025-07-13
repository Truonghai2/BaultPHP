<?php

use Core\Schema\Blueprint;
use Core\Schema\Migration;

class create_failed_jobs_table extends Migration
{
    public function up(): void
    {
        $this->schema->create('failed_jobs', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('connection');
            $table->string('queue');
            $table->longText('payload');
            $table->longText('exception');
            $table->timestamp('failed_at')->useCurrent();
        });
    }

    public function down(): void
    {
        $this->schema->dropIfExists('failed_jobs');
    }
};