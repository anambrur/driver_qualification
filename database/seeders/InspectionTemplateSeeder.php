<?php

namespace Database\Seeders;

use App\Models\InspectionTemplate;
use Illuminate\Database\Seeder;

/**
 * The default pre-trip checklist, version 1 (P2-4). A pre-trip checklist, not a DVIR (C10).
 * Safe to re-run: an existing version 1 is left as the platform screen made it.
 */
class InspectionTemplateSeeder extends Seeder
{
    public const NAME = 'Pre-trip';

    public const SECTIONS = [
        'Engine compartment' => ['Oil level', 'Coolant level', 'Power steering fluid', 'Belts and hoses', 'No leaks under the engine'],
        'Cab and controls' => ['Gauges and warning lights', 'Horn', 'Windshield wipers and washers', 'Mirrors and windshield', 'Seat belt', 'Heater and defroster'],
        'Lights' => ['Headlights, high and low beam', 'Turn signals and four-way flashers', 'Brake lights', 'Tail, clearance and marker lights', 'Reflectors'],
        'Brakes and air system' => ['Air pressure builds to governor cut-out', 'Air leakage within limits', 'Low air warning', 'Parking brake holds', 'Service brake test', 'Slack adjusters and brake chambers'],
        'Tires and wheels' => ['Tread depth and condition', 'Inflation', 'Rims and lug nuts', 'Hub oil seals'],
        'Coupling' => ['Fifth wheel locked, jaws closed', 'Kingpin and apron', 'Air and electrical lines connected', 'Landing gear raised, handle secured'],
        'Trailer' => ['Doors and latches', 'Frame and cross members', 'Suspension', 'Cargo secured'],
        'Emergency equipment' => ['Fire extinguisher charged and secured', 'Warning triangles', 'Spare fuses, if required'],
    ];

    public function run(): void
    {
        $template = InspectionTemplate::query()->where('name', self::NAME)->where('version', 1)->first();

        if ($template === null) {
            // Active unless the platform already runs another pre-trip version (one active at a time)
            $template = (new InspectionTemplate(['name' => self::NAME, 'inspection_type' => 'pre_trip']))->forceFill([
                'version' => 1,
                'is_active' => ! InspectionTemplate::query()->activeOf('pre_trip')->exists(),
                'published_at' => now(),
            ]);
            $template->save();
        }

        if ($template->items()->exists()) {
            return;
        }

        $order = 0;
        foreach (self::SECTIONS as $section => $labels) {
            foreach ($labels as $label) {
                $template->items()->create(['section' => $section, 'label' => $label, 'sort_order' => $order += 10, 'is_required' => true]);
            }
        }
    }
}
