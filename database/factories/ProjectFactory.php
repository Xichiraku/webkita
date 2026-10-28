<?php

namespace Database\Factories;

use App\Enums\ProjectStatus;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'plan_title' => 'Starter',
            'price_range' => 'Rp 750.000',
            'project_title' => fake()->sentence(3),
            'category' => 'website',
            'description' => fake()->paragraph(),
            'skills' => 'HTML, Tailwind CSS',
            'contact_name' => fake()->name(),
            'contact_phone' => '081234567890',
            'additional_notes' => '',
            'status' => ProjectStatus::Pending,
        ];
    }
}
