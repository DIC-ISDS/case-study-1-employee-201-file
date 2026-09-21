<?php

namespace Database\Factories;

use App\Enums\AppointmentStatus;
use App\Enums\AppointmentType;
use App\Models\Office;
use App\Models\Personnel;
use App\Models\Position;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Auth;

class AppointmentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'personnel_id' => Personnel::factory(),
            'office_id' => Office::factory(),
            'position_id' => Position::factory(),
            'type' => AppointmentType::Primary,
            'status' => AppointmentStatus::Active,
            'start_date' => $this->faker->dateTimeBetween('-5 years', '-1 month'),
            'created_by' => $this->owner(),
        ];
    }

    /**
     * Records always have an owner. Factories run without a session in most
     * tests, so fall back to minting the user the record belongs to.
     */
    private function owner(): callable
    {
        return fn () => Auth::id() ?? User::factory();
    }

    public function secondary(): static
    {
        return $this->state(['type' => AppointmentType::Secondary]);
    }

    public function ended(): static
    {
        return $this->state([
            'status' => AppointmentStatus::Ended,
            'end_date' => now()->subMonth()->toDateString(),
        ]);
    }
}
