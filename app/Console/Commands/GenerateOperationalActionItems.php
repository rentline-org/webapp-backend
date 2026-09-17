<?php

namespace App\Console\Commands;

use App\Models\Organization;
use App\Services\Operations\ActionItemService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

class GenerateOperationalActionItems extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'operations:generate-action-items
                            {--organization= : Generate for one organization ID}
                            {--force : Send a digest outside the configured local digest hour}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate idempotent lease and document action items and send localized daily digests';

    /** Execute the console command. */
    public function handle(ActionItemService $actionItemService): int
    {
        $query = Organization::query()->where('is_active', true)->orderBy('id');

        if ($this->option('organization')) {
            $query->whereKey((int) $this->option('organization'));
        }

        $organizations = 0;
        $items = 0;
        $digests = 0;

        $query->chunkById(100, function ($chunk) use ($actionItemService, &$organizations, &$items, &$digests): void {
            foreach ($chunk as $organization) {
                $localNow = CarbonImmutable::now($organization->timezone ?: config('app.timezone'));
                $generated = $actionItemService->generateForOrganization($organization, $localNow->startOfDay());
                $organizations++;
                $items += $generated->count();

                $digestHour = (int) data_get($organization->settings, 'operations.digest_hour', 7);
                if ($this->option('force') || $localNow->hour === $digestHour) {
                    $digests += $actionItemService->sendDailyDigests(
                        $organization,
                        $localNow->startOfDay(),
                        $generated,
                    );
                }
            }
        });

        $this->info("Processed {$organizations} organization(s), {$items} open item(s), {$digests} digest(s).");

        return self::SUCCESS;
    }
}
