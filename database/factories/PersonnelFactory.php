<?php

namespace Database\Factories;

use App\Enums\EmploymentStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Auth;

class PersonnelFactory extends Factory
{
    public function definition(): array
    {
        return [
            'employee_no' => $this->faker->unique()->numerify('EMP-#####'),
            'last_name' => $this->faker->lastName(),
            'first_name' => $this->faker->firstName(),
            'middle_name' => $this->faker->lastName(),
            'email' => $this->faker->unique()->safeEmail(),
            'contact_number' => $this->faker->numerify('09##-###-####'),
            'address' => $this->faker->address(),
            'birth_date' => $this->faker->dateTimeBetween('-60 years', '-22 years'),
            'employment_status' => EmploymentStatus::Permanent,
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
