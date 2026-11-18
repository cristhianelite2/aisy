<?php

namespace App\Console\Commands;

use App\Models\Business;
use App\Services\Ai\DeadTimeRecommendationService;
use Illuminate\Console\Command;

class AnalyzeDeadTimesCommand extends Command
{
    protected $signature = 'aisy:dead-times {--slug= : Specific business slug}';

    protected $description = 'Analyze Odoo sales/appointments for dead times and send proactive WhatsApp recommendations';

    public function handle(DeadTimeRecommendationService $service): int
    {
        $query = Business::query()->where('provisioning_status', 'ready');
        if ($slug = $this->option('slug')) {
            $query->where('slug', $slug);
        }

        $count = 0;
        foreach ($query->get() as $business) {
            $res = $service->analyzeAndRecommend($business, true);
            if ($res['recommended']) {
                $count++;
                $this->info("Recommendation sent for {$business->slug}");
            }
        }

        $this->info("Dead-time scan finished. Recommendations triggered: {$count}");

        return self::SUCCESS;
    }
}
