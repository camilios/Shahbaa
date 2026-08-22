<?php

namespace Database\Seeders;

use App\Models\Governorate;
use Illuminate\Database\Seeder;

class GovernorateSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            'Damascus',
            'Rif Dimashq',
            'Aleppo',
            'Homs',
            'Hama',
            'Latakia',
            'Tartus',
            'Idlib',
            'Daraa',
            'As-Suwayda',
            'Quneitra',
            'Deir ez-Zor',
            'Raqqa',
            'Al-Hasakah',
        ] as $name) {
            Governorate::firstOrCreate(['name' => $name]);
        }
    }
}
