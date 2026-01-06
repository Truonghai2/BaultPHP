<?php

use Core\ORM\DB;
use Core\Schema\Blueprint;
use Core\Schema\Migration;

return new class () extends Migration {
    public function up(): void
    {
        $this->schema->create('oauth_scopes', function (Blueprint $table) {
            $table->string('id', 100);
            $table->primary('id');
            $table->string('name', 255);
            $table->text('description')->nullable();
            $table->boolean('is_default')->default(false);
            $table->integer('priority')->default(0);
            $table->timestamps();
        });

        // Insert default scopes
        $this->insertDefaultScopes();
    }

    public function down(): void
    {
        $this->schema->dropIfExists('oauth_scopes');
    }

    private function insertDefaultScopes(): void
    {
        $now = date('Y-m-d H:i:s');
        $scopes = [
            [
                'id' => 'basic',
                'name' => 'Basic Access',
                'description' => 'Read basic profile information',
                'is_default' => true,
                'priority' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => 'email',
                'name' => 'Email Access',
                'description' => 'Read your email address',
                'is_default' => false,
                'priority' => 2,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ];

        try {
            foreach ($scopes as $scope) {
                DB::table('oauth_scopes')->insert($scope);
            }
        } catch (\Exception $e) {
            if (!str_contains($e->getMessage(), 'Duplicate entry')) {
                throw $e;
            }
        }
    }
};
