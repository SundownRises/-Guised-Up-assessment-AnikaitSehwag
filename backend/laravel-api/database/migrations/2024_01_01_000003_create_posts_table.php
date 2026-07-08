<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('posts', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->text('text');
            $table->string('image_url')->nullable();
            $table->float('authenticity_score');
            $table->timestamps();

            $table->index('user_id');
            $table->index('created_at');
        });

        DB::statement('ALTER TABLE posts ADD COLUMN embedding vector(384);');

        try {
            DB::statement('CREATE INDEX posts_embedding_idx ON posts USING ivfflat (embedding vector_cosine_ops) WITH (lists = 10);');
        } catch (\Exception $e) {
            // IVFFlat index requires rows to build; may fail on empty table
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('posts');
    }
};
