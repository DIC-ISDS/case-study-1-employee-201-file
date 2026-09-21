<?php

use App\Enums\AppointmentStatus;
use App\Enums\AppointmentType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('personnel_id')->constrained('personnel')->cascadeOnDelete();
            $table->foreignId('office_id')->constrained()->restrictOnDelete();
            $table->foreignId('position_id')->constrained()->restrictOnDelete();
            $table->string('type')->default(AppointmentType::Primary->value);
            $table->string('status')->default(AppointmentStatus::Active->value);
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->string('remarks')->nullable();
            $table->timestamps();

            $table->index(['personnel_id', 'type', 'status']);
        });

        // Core business rule, enforced by the database as well as by the
        // application: an employee may hold only ONE active primary
        // appointment at a time.
        //
        // MySQL has no partial indexes, so a generated column holds the
        // personnel_id only for rows that are both primary AND active, and is
        // NULL otherwise. A unique index on it therefore constrains exactly
        // those rows -- MySQL permits unlimited NULLs in a unique index, so
        // secondary and ended appointments are unaffected.
        // Guarded by driver: the generated-column syntax is MySQL's. On another
        // driver the model-level guard in App\Models\Appointment still holds
        // the rule.
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement(sprintf(
            "ALTER TABLE appointments
                ADD COLUMN active_primary_personnel_id BIGINT UNSIGNED
                GENERATED ALWAYS AS (
                    CASE WHEN type = '%s' AND status = '%s' THEN personnel_id ELSE NULL END
                ) VIRTUAL",
            AppointmentType::Primary->value,
            AppointmentStatus::Active->value,
        ));

        DB::statement(
            'CREATE UNIQUE INDEX appointments_one_active_primary_per_personnel
             ON appointments (active_primary_personnel_id)'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('appointments');
    }
};
