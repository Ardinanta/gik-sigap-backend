<?php

namespace Database\Seeders;

use App\Models\RiskAssessment;
use App\Models\ScheduleSuggestion;
use App\Models\User;
use App\Services\AdminRiskService;
use Illuminate\Database\Seeder;

class RiskAssessmentSeeder extends Seeder
{
    public function run(AdminRiskService $riskService): void
    {
        $result = $riskService->index(null);
        $admin = User::query()->where('email', 'admin@sigap.test')->firstOrFail();
        $priorityRegion = $result['regions']
            ->first(fn (array $region): bool => in_array($region['risk_level'], ['warning', 'high'], true));

        if ($priorityRegion === null) {
            return;
        }

        $assessment = RiskAssessment::query()->findOrFail($priorityRegion['id']);
        $riskService->coordinate($admin, $assessment);
        $assessment->load('items.harvestPlan');
        $item = $assessment->items->first();

        if ($item?->harvestPlan === null) {
            return;
        }

        $plan = $item->harvestPlan;
        ScheduleSuggestion::query()->updateOrCreate(
            [
                'risk_assessment_id' => $assessment->id,
                'harvest_plan_id' => $plan->id,
                'reason' => '[DEMO] Pemerataan jadwal untuk mengurangi konsentrasi pasokan mingguan.',
            ],
            [
                'original_date' => $plan->harvest_date,
                'suggested_start_date' => $plan->harvest_date->addWeek(),
                'suggested_end_date' => $plan->harvest_date->addWeek()->addDays(2),
                'status' => 'pending',
                'responded_at' => null,
            ],
        );
    }
}
