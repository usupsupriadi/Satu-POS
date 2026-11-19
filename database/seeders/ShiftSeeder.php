<?php

namespace Database\Seeders;

use App\Models\Shift;
use Illuminate\Database\Seeder;

class ShiftSeeder extends Seeder
{
    public function run(): void
    {
        if (Shift::query()->exists()) {
            return;
        }

        Shift::query()->create([
            'name' => 'Pagi',
            'start_time' => '08:00',
            'end_time' => '16:00',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        Shift::query()->create([
            'name' => 'Sore',
            'start_time' => '16:00',
            'end_time' => '22:00',
            'is_active' => true,
            'sort_order' => 2,
        ]);
    }
}
