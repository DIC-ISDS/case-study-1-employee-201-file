<?php

use App\Enums\AppointmentStatus;
use App\Enums\AppointmentType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Record ownership and soft deletes across every primary table.
 *
 * profile_change_histories is deliberately left out: it is a purely
 * system-generated, append-only audit log that already names its actor, and a
 * deletable audit trail is not an audit trail.
 */
return new class extends Migration
{
    /** Every table that carries an owner. */
    private const TABLES = [
        'offices',
        'positions',
        'personnel',
        'appointments',
        'profile_change_requests',
        'profile_change_items',
    ];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                // Added nullable so the migration can run against a database
                // that already holds rows; backfilled and tightened below.
                $blueprint->foreignId('created_by')
                    ->nullable()
                    ->after('id')
                    ->constrained('users')
                    ->restrictOnDelete();

                $blueprint->foreignId('updated_by')
                    ->nullable()
                    ->after('created_by')
                    ->constrained('users')
                    ->nullOnDelete();

                $blueprint->softDeletes();
            });
        }

        $this->backfillOwners();

        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                // created_by is required once every row has one: ownership
                // that can be null is ownership nothing can rely on.
                $blueprint->unsignedBigInteger('created_by')->nullable(false)->change();
            });
        }

        $this->rebuildUniqueIndexesForSoftDeletes();
    }

    public function down(): void
    {
        $this->restoreUniqueIndexesWithoutSoftDeletes();

        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->dropConstrainedForeignId('created_by');
                $blueprint->dropConstrainedForeignId('updated_by');
                $blueprint->dropSoftDeletes();
            });
        }
    }

    /**
     * Give pre-existing rows an owner. There is no session to read, so they
     * are attributed to the earliest user -- in practice the seeded HR account
     * that would have encoded them.
     */
    private function backfillOwners(): void
    {
        $fallback = DB::table('users')->min('id');

        if ($fallback === null) {
            return;
        }

        foreach (self::TABLES as $table) {
            DB::table($table)->whereNull('created_by')->update(['created_by' => $fallback]);
        }
    }

    /**
     * A unique index counts soft-deleted rows, which would make a deleted
     * record block its own replacement. Both rules below are therefore moved
     * onto a generated column that is NULL once the row is trashed -- MySQL
     * allows unlimited NULLs in a unique index, so trashed rows drop out of
     * the constraint while live ones stay under it.
     */
    private function rebuildUniqueIndexesForSoftDeletes(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        // --- One active primary appointment per employee --------------------
        DB::statement('DROP INDEX appointments_one_active_primary_per_personnel ON appointments');
        DB::statement('ALTER TABLE appointments DROP COLUMN active_primary_personnel_id');

        DB::statement(sprintf(
            "ALTER TABLE appointments
                ADD COLUMN active_primary_personnel_id BIGINT UNSIGNED
                GENERATED ALWAYS AS (
                    CASE
                        WHEN deleted_at IS NULL AND type = '%s' AND status = '%s'
                        THEN personnel_id
                    END
                ) VIRTUAL",
            AppointmentType::Primary->value,
            AppointmentStatus::Active->value,
        ));

        DB::statement(
            'CREATE UNIQUE INDEX appointments_one_active_primary_per_personnel
             ON appointments (active_primary_personnel_id)'
        );

        // --- One line per field per request ---------------------------------
        // MySQL is using the composite unique index to back the foreign key on
        // profile_change_request_id, and will not let it go until another
        // index covers that column.
        DB::statement(
            'CREATE INDEX profile_change_items_request_index
             ON profile_change_items (profile_change_request_id)'
        );
        DB::statement('ALTER TABLE profile_change_items DROP INDEX profile_change_items_profile_change_request_id_field_unique');

        DB::statement(
            "ALTER TABLE profile_change_items
                ADD COLUMN live_request_field VARCHAR(160)
                GENERATED ALWAYS AS (
                    CASE
                        WHEN deleted_at IS NULL
                        THEN CONCAT(profile_change_request_id, ':', field)
                    END
                ) VIRTUAL"
        );

        DB::statement(
            'CREATE UNIQUE INDEX profile_change_items_one_line_per_field
             ON profile_change_items (live_request_field)'
        );
    }

    private function restoreUniqueIndexesWithoutSoftDeletes(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement('DROP INDEX profile_change_items_one_line_per_field ON profile_change_items');
        DB::statement('ALTER TABLE profile_change_items DROP COLUMN live_request_field');
        DB::statement(
            'CREATE UNIQUE INDEX profile_change_items_profile_change_request_id_field_unique
             ON profile_change_items (profile_change_request_id, field)'
        );
        DB::statement('DROP INDEX profile_change_items_request_index ON profile_change_items');

        DB::statement('DROP INDEX appointments_one_active_primary_per_personnel ON appointments');
        DB::statement('ALTER TABLE appointments DROP COLUMN active_primary_personnel_id');
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
};
