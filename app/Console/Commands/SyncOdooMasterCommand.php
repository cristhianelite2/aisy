<?php

namespace App\Console\Commands;

use App\Models\Business;
use App\Services\Odoo\OdooBusinessService;
use App\Services\Odoo\OdooClient;
use Illuminate\Console\Command;

class SyncOdooMasterCommand extends Command
{
    protected $signature = 'aisy:sync-odoo';

    protected $description = 'Ensure Odoo master account (maestro@aisy.ceballosleon.com) exists with multi-company access to all Aisy businesses';

    public function handle(OdooClient $odoo, OdooBusinessService $service): int
    {
        if (!$odoo->isAvailable()) {
            $this->error('Odoo (:8069) is not reachable.');

            return self::FAILURE;
        }

        $existingCompanies = collect($odoo->searchRead('res.company', [], ['id']))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        // 1. Ensure all businesses in MySQL exist as real res.company in Odoo
        foreach (Business::all() as $business) {
            if (!$business->odoo_company_id || !in_array((int) $business->odoo_company_id, $existingCompanies, true)) {
                $business->odoo_company_id = null;
                $business->save();
            }
            $res = $service->provisionCompany($business);
            $this->info("Synced business: {$business->name} -> Odoo Company #{$res['company_id']}");
        }

        $allCompanyIds = collect($odoo->searchRead('res.company', [], ['id']))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        // 2. Enable multi-company group and create/update maestro@aisy.ceballosleon.com in Odoo
        $multiGroup = $odoo->searchRead('res.groups', [['name', '=', 'Multi Companies']], ['id'], 1);
        $adminUser = $odoo->searchRead('res.users', [['login', '=', 'admin']], ['id', 'groups_id'], 1);
        $adminGroups = $adminUser[0]['groups_id'] ?? [];
        if (!empty($multiGroup[0]['id']) && !in_array((int) $multiGroup[0]['id'], $adminGroups, true)) {
            $adminGroups[] = (int) $multiGroup[0]['id'];
        }

        $companyCommands = array_map(fn (int $cid) => [4, $cid], $allCompanyIds);
        if (!empty($adminUser[0]['id'])) {
            $odoo->write('res.users', [(int) $adminUser[0]['id']], [
                'company_ids' => $companyCommands,
                'groups_id' => [[6, 0, $adminGroups]],
            ]);
        }

        $master = $odoo->searchRead('res.users', [['login', '=', 'maestro@aisy.ceballosleon.com']], ['id'], 1);
        if (empty($master[0]['id'])) {
            $masterId = $odoo->create('res.users', [
                'name' => 'Maestro Aisy',
                'login' => 'maestro@aisy.ceballosleon.com',
                'email' => 'maestro@aisy.ceballosleon.com',
                'password' => 'AisyMaster2026!',
                'company_id' => 1,
                'company_ids' => $companyCommands,
                'groups_id' => [[6, 0, $adminGroups]],
            ]);
            $this->info("Created Odoo Master Account: maestro@aisy.ceballosleon.com (ID #{$masterId})");
        } else {
            $odoo->write('res.users', [(int) $master[0]['id']], [
                'password' => 'AisyMaster2026!',
                'company_ids' => $companyCommands,
                'groups_id' => [[6, 0, $adminGroups]],
            ]);
            $this->info("Updated Odoo Master Account: maestro@aisy.ceballosleon.com (ID #{$master[0]['id']})");
        }

        return self::SUCCESS;
    }
}
