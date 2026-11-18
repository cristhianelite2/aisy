<?php

namespace Database\Factories;

use App\Models\Business;
use Illuminate\Database\Eloquent\Factories\Factory;

class BusinessFactory extends Factory
{
    protected $model = Business::class;

    public function definition(): array
    {
        $name = $this->faker->company();
        $branch = $this->faker->randomElement(['estetica', 'tienda', 'comandas']);

        return [
            'name' => $name,
            'slug' => Business::generateUniqueSlug($name),
            'branch' => $branch,
            'owner_name' => $this->faker->name(),
            'owner_phone' => '52155' . $this->faker->numerify('########'),
            'address' => $this->faker->address(),
            'primary_color' => '#0ea5e9',
            'secondary_color' => '#6366f1',
            'style_preset' => 'moderno',
            'features' => null,
            'ai_memory_semantic' => [
                'tone' => 'cercano, práctico y orientado a ventas',
            ],
            'provisioning_status' => 'pending',
        ];
    }
}
