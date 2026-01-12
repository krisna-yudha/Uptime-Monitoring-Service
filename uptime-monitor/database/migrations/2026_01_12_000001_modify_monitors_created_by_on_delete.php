<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('monitors', function (Blueprint $table) {
            // Drop existing foreign key constraint
            $table->dropForeign(['created_by']);
            
            // Make created_by nullable
            $table->unsignedBigInteger('created_by')->nullable()->change();
            
            // Re-add foreign key with set null on delete instead of cascade
            $table->foreign('created_by')
                  ->references('id')
                  ->on('users')
                  ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('monitors', function (Blueprint $table) {
            // Drop the modified constraint
            $table->dropForeign(['created_by']);
            
            // Make created_by not nullable again
            $table->unsignedBigInteger('created_by')->nullable(false)->change();
            
            // Restore original cascade delete (optional: could keep as set null for safety)
            $table->foreign('created_by')
                  ->references('id')
                  ->on('users')
                  ->onDelete('cascade');
        });
    }
};
