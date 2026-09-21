<?php

use App\Enums\EmploymentStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('personnel', function (Blueprint $table) {
            $table->id();
            $table->string('employee_no')->unique();
            $table->string('last_name');
            $table->string('first_name');
            $table->string('middle_name')->nullable();
            $table->string('email')->unique();
            $table->string('contact_number')->nullable();
            $table->string('address')->nullable();
            $table->date('birth_date')->nullable();
            $table->string('employment_status')->default(EmploymentStatus::Permanent->value);
            // The login that belongs to this employee. Nullable: HR can encode a
            // personnel record before the person has an account.
            $table->foreignId('user_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->index('employment_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('personnel');
    }
};
