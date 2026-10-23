<?php

namespace Database\Seeders;

use App\Enums\ActivityType;
use App\Enums\BaseUnit;
use App\Enums\CampaignStatus;
use App\Enums\ExpenseCategory;
use App\Enums\FarmRole;
use App\Enums\FarmStatus;
use App\Enums\InputCategory;
use App\Enums\ProductionUnitStatus;
use App\Enums\ReferenceType;
use App\Enums\RevenueCategory;
use App\Models\CalendarStep;
use App\Models\Campaign;
use App\Models\Crop;
use App\Models\Expense;
use App\Models\Farm;
use App\Models\FarmUser;
use App\Models\Input;
use App\Models\ProductionUnit;
use App\Models\Revenue;
use App\Models\User;
use App\Services\CropCalendarService;
use App\Services\HarvestService;
use App\Services\StockService;
use App\Services\TreatmentService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedFarm(
            'Ferme Ambohipo',
            'Ambohipo, Antananarivo',
            'responsable@volylegume.mg',
            withOperations: true,
        );

        $this->seedFarm(
            'Exploitation Itasy',
            'Analavory, Itasy',
            'responsable.itasy@volylegume.mg',
            withOperations: false,
        );

        $this->seedTestAccounts();
        $this->call(VegetableDataSeeder::class);
        $this->call(CropCatalogSeeder::class);
        $this->call(CropGuideSeeder::class);
        $this->call(CommercialDataSeeder::class);
    }

    /**
     * Comptes de démonstration pour tester tous les rôles.
     *
     * L'administrateur est rattaché à toutes les exploitations ; le technicien
     * (rôle Agent) est rattaché à la première exploitation.
     */
    private function seedTestAccounts(): void
    {
        $admin = User::updateOrCreate(
            ['email' => 'admin@volylegume.mg'],
            ['name' => 'Rasoa Administratrice', 'password' => Hash::make('password'), 'is_active' => true, 'is_platform_admin' => true],
        );

        $technician = User::updateOrCreate(
            ['email' => 'technicien@volylegume.mg'],
            ['name' => 'Technicien Test', 'password' => Hash::make('password'), 'is_active' => true],
        );

        $farmIds = Farm::query()->orderBy('id')->pluck('id');

        foreach ($farmIds as $farmId) {
            FarmUser::updateOrCreate(
                ['farm_id' => $farmId, 'user_id' => $admin->id],
                ['role' => FarmRole::Admin, 'is_active' => true],
            );
        }

        if ($farmIds->isNotEmpty()) {
            FarmUser::updateOrCreate(
                ['farm_id' => $farmIds->first(), 'user_id' => $technician->id],
                ['role' => FarmRole::Agent, 'is_active' => true],
            );
        }
    }

    private function seedFarm(string $name, string $location, string $managerEmail, bool $withOperations): void
    {
        $responsable = User::factory()->create([
            'name' => $managerEmail === 'responsable@volylegume.mg' ? 'Rakoto Responsable' : 'Rasoa Itasy',
            'email' => $managerEmail,
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);

        $slug = strtolower(str_replace(' ', '', explode(',', $location)[0]));

        $agent = User::factory()->create([
            'name' => 'Jean Agent',
            'email' => 'agent+'.$slug.'@volylegume.mg',
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);

        $farm = Farm::create([
            'name' => $name,
            'location' => $location,
            'type' => 'market_gardening',
            'area_m2' => 3200,
            'manager_id' => $responsable->id,
            'status' => FarmStatus::Active,
            'currency' => 'MGA',
        ]);

        FarmUser::create(['farm_id' => $farm->id, 'user_id' => $responsable->id, 'role' => FarmRole::Responsable, 'is_active' => true]);
        FarmUser::create(['farm_id' => $farm->id, 'user_id' => $agent->id, 'role' => FarmRole::Agent, 'is_active' => true]);

        $parcels = collect([
            ['code' => 'P01', 'name' => 'Parcelle basse', 'area_m2' => 1200, 'location' => 'Rangées 1-8'],
            ['code' => 'P02', 'name' => 'Parcelle haute', 'area_m2' => 900, 'location' => 'Rangées 9-14'],
        ])->map(fn (array $data) => ProductionUnit::create($data + [
            'farm_id' => $farm->id,
            'type' => 'plot',
            'status' => ProductionUnitStatus::Available,
        ]));

        $tomate = Crop::create(['farm_id' => $farm->id, 'name' => 'Tomate', 'variety' => 'Mercure', 'production_unit' => 'kg', 'estimated_cycle_days' => 90, 'is_active' => true]);
        $carotte = Crop::create(['farm_id' => $farm->id, 'name' => 'Carotte', 'variety' => 'New Kuroda', 'production_unit' => 'kg', 'estimated_cycle_days' => 100, 'is_active' => true]);
        Crop::create(['farm_id' => $farm->id, 'name' => 'Laitue', 'variety' => 'Batavia', 'production_unit' => 'kg', 'estimated_cycle_days' => 55, 'is_active' => true]);

        foreach ($this->tomatoCalendar() as $position => $step) {
            CalendarStep::create($step + ['farm_id' => $farm->id, 'crop_id' => $tomate->id, 'position' => $position + 1, 'is_active' => true]);
        }
        foreach ($this->carotteCalendar() as $position => $step) {
            CalendarStep::create($step + ['farm_id' => $farm->id, 'crop_id' => $carotte->id, 'position' => $position + 1, 'is_active' => true]);
        }

        $engrais = Input::create([
            'farm_id' => $farm->id, 'code' => 'FERT-01', 'name' => 'Engrais NPK 11-22-16',
            'category' => InputCategory::Fertilizer, 'base_unit' => BaseUnit::Kg,
            'quantity' => 0, 'minimum_stock' => 20, 'is_active' => true,
        ]);

        $phytosanitaire = Input::create([
            'farm_id' => $farm->id, 'code' => 'PHYTO-01', 'name' => 'Fongicide cuivre',
            'category' => InputCategory::Phytosanitary, 'base_unit' => BaseUnit::Liter,
            'quantity' => 0, 'minimum_stock' => 5,
            'default_pre_harvest_days' => 14, 'delay_source' => 'Notice fabricant',
            'is_active' => true,
        ]);

        $potasse = Input::create([
            'farm_id' => $farm->id, 'code' => 'FERT-02', 'name' => 'Engrais potasse',
            'category' => InputCategory::Fertilizer, 'base_unit' => BaseUnit::Kg,
            'quantity' => 0, 'minimum_stock' => 10, 'is_active' => true,
        ]);

        $semence = Input::create([
            'farm_id' => $farm->id, 'code' => 'SEM-01', 'name' => 'Semence tomate Mercure',
            'category' => InputCategory::Seed, 'base_unit' => BaseUnit::Kg,
            'quantity' => 0, 'minimum_stock' => 0.5, 'is_active' => true,
        ]);

        $stock = app(StockService::class);
        $stock->setInitial($engrais, 150, $responsable, 'Stock initial');
        $stock->setInitial($phytosanitaire, 3, $responsable, 'Stock initial — niveau bas (affiché en alerte sur le tableau de bord)');
        $stock->setInitial($potasse, 2, $responsable, 'Stock initial — sous le seuil minimum (alerte tableau de bord)');
        $stock->setInitial($semence, 2, $responsable, 'Stock initial');

        if (! $withOperations) {
            return;
        }

        $campaign = Campaign::create([
            'farm_id' => $farm->id,
            'production_unit_id' => $parcels[0]->id,
            'crop_id' => $tomate->id,
            'name' => 'Tomate P01 — campagne 1',
            'start_date' => now()->subDays(40)->toDateString(),
            'reference_type' => ReferenceType::Planting,
            'expected_end_date' => now()->addDays(50)->toDateString(),
            'cultivated_area_m2' => 400,
            'status' => CampaignStatus::Ongoing,
        ]);

        app(CropCalendarService::class)->generate($campaign, $responsable);

        app(TreatmentService::class)->record($campaign, $phytosanitaire, $agent, [
            'quantity' => 0.5,
            'dose_value' => 2.5,
            'dose_unit' => 'L/ha',
            'pre_harvest_days' => 14,
            'applied_at' => now()->subDays(20)->toDateTimeString(),
            'delay_source' => 'Notice fabricant',
            'notes' => 'Traitement préventif mildiou.',
        ]);

        app(HarvestService::class)->record($campaign, $agent, [
            'quantity' => 120,
            'harvested_at' => now()->subDays(2)->toDateTimeString(),
            'quality' => 'bien',
            'notes' => 'Première récolte.',
        ]);

        Expense::create([
            'farm_id' => $farm->id, 'campaign_id' => $campaign->id, 'category' => ExpenseCategory::Labour,
            'description' => 'Main-d’œuvre repiquage', 'amount' => 180000,
            'expense_date' => now()->subDays(35)->toDateString(), 'recorded_by' => $responsable->id,
        ]);

        Revenue::create([
            'farm_id' => $farm->id, 'campaign_id' => $campaign->id, 'category' => RevenueCategory::HarvestSale,
            'description' => 'Vente marché Analakely', 'amount' => 320000,
            'revenue_date' => now()->subDay()->toDateString(), 'recorded_by' => $responsable->id,
        ]);

        $campaignCarotte = Campaign::create([
            'farm_id' => $farm->id,
            'production_unit_id' => $parcels[1]->id,
            'crop_id' => $carotte->id,
            'name' => 'Carotte P02 — campagne 1',
            'start_date' => now()->subDays(25)->toDateString(),
            'reference_type' => ReferenceType::Planting,
            'expected_end_date' => now()->addDays(75)->toDateString(),
            'cultivated_area_m2' => 300,
            'status' => CampaignStatus::Ongoing,
        ]);

        app(CropCalendarService::class)->generate($campaignCarotte, $responsable);

        app(TreatmentService::class)->record($campaignCarotte, $phytosanitaire, $agent, [
            'quantity' => 0.4,
            'dose_value' => 2,
            'dose_unit' => 'L/ha',
            'pre_harvest_days' => 21,
            'applied_at' => now()->subDays(3)->toDateTimeString(),
            'delay_source' => 'Notice fabricant',
            'notes' => 'Traitement fongicide — la récolte est bloquée jusqu’à la borne de délai (démonstration RG08/RG09).',
        ]);
    }

    /**
     * @return list<array{name: string, activity_type: ActivityType, offset_days: int, description: ?string}>
     */
    private function tomatoCalendar(): array
    {
        return [
            ['name' => 'Préparation du sol', 'activity_type' => ActivityType::SoilPreparation, 'offset_days' => 0, 'description' => 'Labour et amendement.'],
            ['name' => 'Plantation', 'activity_type' => ActivityType::Transplanting, 'offset_days' => 3, 'description' => 'Repiquage des plants.'],
        ];
    }

    /**
     * @return list<array{name: string, activity_type: ActivityType, offset_days: int, description: ?string}>
     */
    private function carotteCalendar(): array
    {
        return [
            ['name' => 'Semis', 'activity_type' => ActivityType::Sowing, 'offset_days' => 0, 'description' => null],
            ['name' => 'Désherbage', 'activity_type' => ActivityType::Weeding, 'offset_days' => 20, 'description' => null],
        ];
    }
}
