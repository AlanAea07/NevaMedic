<?php

namespace Database\Seeders;

use App\Models\Sector;
use Illuminate\Database\Seeder;

class SectorSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            ['Clínico', 'clinico', 'Prendas para atención, laboratorio y consulta.'],
            ['Médico', 'medico', 'Uniformes para profesionales e instituciones de salud.'],
            ['Industrial', 'industrial', 'Resistencia y presentación para equipos de trabajo.'],
        ] as [$name, $slug, $description]) {
            Sector::firstOrCreate(['slug' => $slug], compact('name', 'description'));
        }
    }
}
