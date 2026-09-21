<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('profile_change_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('profile_change_request_id')
                ->constrained('profile_change_requests')
                ->cascadeOnDelete();
            // Which profile field this line changes, e.g. last_name, office_id.
            $table->string('field');
            // Snapshot of the value at submission time, kept verbatim so the
            // request stays readable after the personnel record moves on.
            $table->string('old_value')->nullable();
            $table->string('new_value')->nullable();
            $table->timestamps();

            $table->unique(['profile_change_request_id', 'field']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('profile_change_items');
    }
};
