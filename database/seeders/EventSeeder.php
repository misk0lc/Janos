<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Event;

class EventSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Fix dátumú példa események
        $events = [
            [
                'title' => 'Tech Conference 2024',
                'description' => 'Éves technológiai konferencia innovatív témákkal.',
                'date' => now()->addDays(30),
                'location' => 'Budapest, BME Q épület',
                'max_attendees' => 100,
            ],
            [
                'title' => 'Marketing Workshop',
                'description' => 'Gyakorlati marketing workshop digitális trendekkel.',
                'date' => now()->addDays(15),
                'location' => 'Online (Zoom)',
                'max_attendees' => 50,
            ],
            [
                'title' => 'Webfejlesztés Alapjai',
                'description' => 'Kezdőknek szóló webfejlesztési tréning.',
                'date' => now()->subDays(10), // Múltbeli
                'location' => 'Debrecen, Egyetem',
                'max_attendees' => 40,
            ],
        ];

        foreach ($events as $event) {
            Event::create($event);
        }

        // Factory-val létrehozott random események
        Event::factory()->count(10)->create();
        $this->command->info("EventSeeder: 13 esemény létrehozva (3 fix + 10 random).");
    }
}