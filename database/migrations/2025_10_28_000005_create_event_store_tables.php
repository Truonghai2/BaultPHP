<?php

use Core\Schema\Blueprint;
use Core\Schema\Migration;

return new class () extends Migration {
    public function up(): void
    {
        $this->schema->create('events', function (Blueprint $table) {
            $table->id(); 
            $table->string('event_id', 36)->unique(); 
            $table->string('aggregate_type', 255)->index();
            $table->string('aggregate_id', 255)->index();
            $table->string('event_type', 255)->index();
            $table->text('event_data'); 
            $table->integer('event_version')->default(1);
            $table->integer('aggregate_version');
            $table->text('metadata')->nullable(); 
            $table->dateTime('occurred_at')->index();
            $table->timestamps();
            
            $table->index(['aggregate_type', 'aggregate_id', 'aggregate_version']);
        });

        $this->schema->create('aggregates', function (Blueprint $table) {
            $table->id();
            $table->string('aggregate_type', 255);
            $table->string('aggregate_id', 255);
            $table->integer('version')->default(0);
            $table->timestamps();
            
            $table->unique(['aggregate_type', 'aggregate_id']);
        });

        $this->schema->create('projections', function (Blueprint $table) {
            $table->id();
            $table->string('projection_name', 255)->unique();
            $table->string('last_event_id', 36)->nullable();
            $table->integer('last_position')->default(0);
            $table->string('status', 50)->default('idle'); 
            $table->timestamps();
        });

        $this->schema->create('snapshots', function (Blueprint $table) {
            $table->id();
            $table->string('aggregate_type', 255);
            $table->string('aggregate_id', 255);
            $table->integer('version');
            $table->text('state'); 
            $table->timestamps();
            
            $table->unique(['aggregate_type', 'aggregate_id', 'version']);
            $table->index(['aggregate_type', 'aggregate_id']);
        });

        echo "Event Store tables created successfully.\n";
    }

    public function down(): void
    {
        $this->schema->dropIfExists('snapshots');
        $this->schema->dropIfExists('projections');
        $this->schema->dropIfExists('aggregates');
        $this->schema->dropIfExists('events');
    }
};

