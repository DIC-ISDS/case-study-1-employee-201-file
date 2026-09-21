<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Auth;

class PositionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'title' => $this->faker->unique()->jobTitle(),
            'salary_grade' => $this->faker->numberBetween(1, 30),
            'is_active' => true,
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
}
