<?php

namespace Database\Seeders;

use App\Enums\ActivityStatus;
use App\Enums\ActivityType;
use App\Enums\BaseUnit;
use App\Enums\CampaignStatus;
use App\Enums\ExpenseCategory;
use App\Enums\FarmRole;
use App\Enums\InputCategory;
use App\Enums\ProductionUnitStatus;
use App\Enums\ReferenceType;
use App\Enums\RevenueCategory;
use App\Models\Activity;
use App\Models\CalendarStep;
use App\Models\Campaign;
use App\Models\Crop;
use App\Models\Expense;
use App\Models\Farm;
use App\Models\Input;
use App\Models\ProductionUnit;
use App\Models\Revenue;
use App\Models\User;
use App\Services\CropCalendarService;
use App\Services\HarvestService;
use App\Services\StockService;
use App\Services\TreatmentService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Données de démonstration complètes pour tous les légumes.
 *
 * Le seeder est idempotent : il complète les cultures, calendriers, campagnes et
 * opérations existantes sans dupliquer les enregistrements déjà présents.
 */
class VegetableDataSeeder extends Seeder
{
    public function run(): void
    {
        $calendarService = app(CropCalendarService::class);
        $treatmentService = app(TreatmentService::class);
        $harvestService = app(HarvestService::class);

        DB::transaction(function () use ($calendarService, $treatmentService, $harvestService) {
            Farm::query()->orderBy('id')->each(function (Farm $farm) use ($calendarService, $treatmentService, $harvestService) {
                $manager = $farm->manager;

                if ($manager === null) {
                    return;
                }

                $agent = $farm->memberships()
                    ->where('role', FarmRole::Agent->value)
                    ->where('is_active', true)
                    ->with('user')
                    ->first()?->user ?? $manager;

                $units = $this->ensureProductionUnits($farm);
                $phytosanitary = $this->ensurePhytosanitaryInput($farm, $manager);

                foreach ($this->cropDefinitions() as $name => $definition) {
                    $crop = Crop::updateOrCreate(
                        ['farm_id' => $farm->id, 'name' => $name],
                        [
                            'variety' => $definition['variety'],
                            'production_unit' => 'kg',
                            'estimated_cycle_days' => $definition['cycle'],
                            'average_yield_kg_m2' => $definition['campaign']['yield'],
                            'default_sale_price' => $definition['campaign']['price'],
                            'is_active' => true,
                        ],
                    );

                    $this->seedCalendar($farm, $crop, $definition['calendar']);
                    $this->seedCampaign(
                        $farm,
                        $manager,
                        $agent,
                        $crop,
                        $definition,
                        $units,
                        $phytosanitary,
                        $calendarService,
                        $treatmentService,
                        $harvestService,
                    );
                }
            });
        });
    }

    /**
     * @return Collection<string, ProductionUnit>
     */
    private function ensureProductionUnits(Farm $farm): Collection
    {
        $definitions = [
            ['code' => 'P01', 'name' => 'Parcelle basse', 'area_m2' => 1200, 'location' => 'Rangées 1-8'],
            ['code' => 'P02', 'name' => 'Parcelle haute', 'area_m2' => 900, 'location' => 'Rangées 9-14'],
            ['code' => 'P03', 'name' => 'Parcelle est', 'area_m2' => 700, 'location' => 'Rangées 15-20'],
            ['code' => 'P04', 'name' => 'Parcelle ouest', 'area_m2' => 600, 'location' => 'Rangées 21-25'],
        ];

        foreach ($definitions as $definition) {
            ProductionUnit::updateOrCreate(
                ['farm_id' => $farm->id, 'code' => $definition['code']],
                $definition + ['type' => 'plot', 'status' => ProductionUnitStatus::Available],
            );
        }

        return $farm->productionUnits()->get()->keyBy('code');
    }

    private function ensurePhytosanitaryInput(Farm $farm, User $manager): Input
    {
        $input = Input::firstOrCreate(
            ['farm_id' => $farm->id, 'code' => 'PHYTO-02'],
            [
                'name' => 'Fongicide mancozèbe',
                'category' => InputCategory::Phytosanitary,
                'base_unit' => BaseUnit::Liter,
                'quantity' => 0,
                'minimum_stock' => 5,
                'default_pre_harvest_days' => 14,
                'delay_source' => 'Notice fabricant',
                'is_active' => true,
            ],
        );

        if ($input->stockMovements()->count() === 0) {
            app(StockService::class)->setInitial($input, 100, $manager, 'Stock initial — données de démonstration');
        }

        return $input;
    }

    /**
     * @param  list<array{name: string, type: ActivityType, offset: int, description: ?string}>  $steps
     */
    private function seedCalendar(Farm $farm, Crop $crop, array $steps): void
    {
        foreach ($steps as $index => $step) {
            CalendarStep::updateOrCreate(
                ['crop_id' => $crop->id, 'position' => $index + 1],
                [
                    'farm_id' => $farm->id,
                    'name' => $step['name'],
                    'activity_type' => $step['type'],
                    'offset_days' => $step['offset'],
                    'description' => $step['description'],
                    'is_active' => true,
                ],
            );
        }
    }

    /**
     * Aligne les activités existantes sur le calendrier complet du légume.
     */
    private function syncCalendarActivities(Campaign $campaign): void
    {
        $campaign->loadMissing('crop.calendarSteps');

        $steps = $campaign->crop->calendarSteps->keyBy('id');

        $campaign->activities()
            ->whereNotNull('calendar_step_id')
            ->get()
            ->each(function (Activity $activity) use ($campaign, $steps): void {
                $step = $steps->get($activity->calendar_step_id);

                if ($step === null) {
                    return;
                }

                $plannedDate = $campaign->start_date->copy()->addDays($step->offset_days);

                $attributes = [
                    'type' => $step->activity_type,
                    'title' => $step->name,
                    'description' => $step->description,
                    'planned_date' => $plannedDate->toDateString(),
                ];

                if ($activity->status === ActivityStatus::Completed) {
                    $attributes['performed_at'] = $plannedDate->copy()->setTime(9, 0);
                }

                $activity->update($attributes);
            });
    }

    /**
     * @param  array{variety: string, cycle: int, calendar: list<array{name: string, type: ActivityType, offset: int, description: ?string}>, campaign: array{status: CampaignStatus, start_offset: int, unit: string, area: int, yield: float, price: float, reference: ReferenceType}}  $definition
     * @param  Collection<string, ProductionUnit>  $units
     */
    private function seedCampaign(
        Farm $farm,
        User $manager,
        User $agent,
        Crop $crop,
        array $definition,
        Collection $units,
        Input $phytosanitary,
        CropCalendarService $calendarService,
        TreatmentService $treatmentService,
        HarvestService $harvestService,
    ): void {
        $config = $definition['campaign'];

        $campaign = Campaign::query()
            ->where('farm_id', $farm->id)
            ->where('crop_id', $crop->id)
            ->orderBy('id')
            ->first();

        if ($campaign === null) {
            $start = now()->startOfDay()->addDays($config['start_offset']);
            $expectedEnd = $start->copy()->addDays($crop->estimated_cycle_days);
            $unit = $units->get($config['unit']) ?? $units->first();

            if ($unit === null) {
                return;
            }

            $status = $config['status'];

            $campaign = Campaign::create([
                'farm_id' => $farm->id,
                'production_unit_id' => $unit->id,
                'crop_id' => $crop->id,
                'name' => $crop->name.' '.$unit->code.' — campagne démo',
                'start_date' => $start->toDateString(),
                'reference_type' => $config['reference'],
                'expected_end_date' => $expectedEnd->toDateString(),
                'actual_end_date' => $status === CampaignStatus::Completed ? $expectedEnd->toDateString() : null,
                'cultivated_area_m2' => $config['area'],
                'status' => $status,
                'notes' => 'Données de démonstration — '.$crop->variety.'.',
            ]);
        } else {
            $status = $campaign->status;
        }

        $calendarService->generate($campaign, $manager);
        $this->syncCalendarActivities($campaign);

        $cutoff = $status === CampaignStatus::Completed
            ? $campaign->expected_end_date->copy()->endOfDay()
            : now()->subDays(7);

        Activity::query()
            ->where('campaign_id', $campaign->id)
            ->where('status', ActivityStatus::Planned->value)
            ->whereDate('planned_date', '<=', $cutoff->toDateString())
            ->get()
            ->each(function (Activity $activity) use ($agent): void {
                $activity->update([
                    'status' => ActivityStatus::Completed,
                    'performed_at' => $activity->planned_date->copy()->setTime(9, 0),
                    'performed_by' => $agent->id,
                ]);
            });

        if ($status !== CampaignStatus::Planned && ! $campaign->treatments()->exists()) {
            $appliedAt = $status === CampaignStatus::Completed
                ? $campaign->start_date->copy()->addDays(20)->setTime(8, 0)
                : now()->subDays(20)->setTime(8, 0);

            if ($appliedAt->lt($campaign->start_date)) {
                $appliedAt = $campaign->start_date->copy()->setTime(8, 0);
            }

            $treatmentService->record($campaign, $phytosanitary, $agent, [
                'quantity' => 0.35,
                'dose_value' => 2,
                'dose_unit' => 'L/ha',
                'pre_harvest_days' => 14,
                'applied_at' => $appliedAt->toDateTimeString(),
                'delay_source' => 'Notice fabricant',
                'notes' => 'Traitement préventif — donnée de démonstration.',
            ]);
        }

        $harvest = $campaign->harvests()->latest('harvested_at')->first();

        if ($harvest === null && $status === CampaignStatus::Completed) {
            $harvestedAt = $campaign->start_date
                ->copy()
                ->addDays(max($crop->estimated_cycle_days - 10, 1))
                ->setTime(9, 0);

            $minimum = $treatmentService->minimumHarvestAt($campaign, $harvestedAt);

            if ($minimum === null || $harvestedAt->greaterThanOrEqualTo($minimum)) {
                $harvest = $harvestService->record($campaign, $agent, [
                    'quantity' => round((float) $campaign->cultivated_area_m2 * $config['yield'], 2),
                    'harvested_at' => $harvestedAt->toDateTimeString(),
                    'quality' => 'bonne',
                    'notes' => 'Récolte de démonstration — '.$crop->name.'.',
                ]);
            }
        }

        if ($status !== CampaignStatus::Planned) {
            Expense::updateOrCreate(
                ['farm_id' => $farm->id, 'reference' => 'DEMO-'.$farm->id.'-C'.$campaign->id.'-LAB'],
                [
                    'campaign_id' => $campaign->id,
                    'category' => ExpenseCategory::Labour,
                    'description' => 'Main-d’œuvre — '.$crop->name,
                    'amount' => round((float) $campaign->cultivated_area_m2 * 1200, 2),
                    'expense_date' => $campaign->start_date->copy()->addDays(5)->toDateString(),
                    'recorded_by' => $manager->id,
                ],
            );

            if ($status === CampaignStatus::Completed) {
                Expense::updateOrCreate(
                    ['farm_id' => $farm->id, 'reference' => 'DEMO-'.$farm->id.'-C'.$campaign->id.'-INT'],
                    [
                        'campaign_id' => $campaign->id,
                        'category' => ExpenseCategory::Inputs,
                        'description' => 'Semences et intrants — '.$crop->name,
                        'amount' => round((float) $campaign->cultivated_area_m2 * 800, 2),
                        'expense_date' => $campaign->start_date->copy()->addDays(12)->toDateString(),
                        'recorded_by' => $manager->id,
                    ],
                );
            }
        }

        if ($harvest !== null) {
            Revenue::updateOrCreate(
                ['farm_id' => $farm->id, 'reference' => 'DEMO-'.$farm->id.'-C'.$campaign->id.'-SALE'],
                [
                    'campaign_id' => $campaign->id,
                    'harvest_id' => $harvest->id,
                    'category' => RevenueCategory::HarvestSale,
                    'description' => 'Vente de récolte — '.$crop->name,
                    'amount' => round((float) $harvest->quantity * $config['price'], 2),
                    'revenue_date' => $harvest->harvested_at->copy()->addDays(2)->toDateString(),
                    'recorded_by' => $manager->id,
                ],
            );
        }
    }

    /**
     * @return array<string, array{variety: string, cycle: int, calendar: list<array{name: string, type: ActivityType, offset: int, description: ?string}>, campaign: array{status: CampaignStatus, start_offset: int, unit: string, area: int, yield: float, price: float, reference: ReferenceType}}>
     */
    private function cropDefinitions(): array
    {
        return [
            'Tomate' => [
                'variety' => 'Mercure',
                'cycle' => 90,
                'calendar' => [
                    ['name' => 'Préparation du sol', 'type' => ActivityType::SoilPreparation, 'offset' => 0, 'description' => 'Labour, amendement et nivellement.'],
                    ['name' => 'Plantation', 'type' => ActivityType::Transplanting, 'offset' => 3, 'description' => 'Repiquage des plants.'],
                    ['name' => 'Fertilisation de fond', 'type' => ActivityType::Fertilization, 'offset' => 10, 'description' => 'Apport d’engrais NPK.'],
                    ['name' => 'Paillage', 'type' => ActivityType::Maintenance, 'offset' => 15, 'description' => 'Mise en place du paillage.'],
                    ['name' => 'Irrigation', 'type' => ActivityType::Irrigation, 'offset' => 20, 'description' => 'Irrigation régulière.'],
                    ['name' => 'Tuteurage', 'type' => ActivityType::Maintenance, 'offset' => 25, 'description' => 'Pose des tuteurs et attachage.'],
                    ['name' => 'Désherbage', 'type' => ActivityType::Weeding, 'offset' => 30, 'description' => 'Désherbage manuel.'],
                    ['name' => 'Traitement préventif', 'type' => ActivityType::Treatment, 'offset' => 40, 'description' => 'Traitement contre le mildiou.'],
                    ['name' => 'Fertilisation d’entretien', 'type' => ActivityType::Fertilization, 'offset' => 50, 'description' => 'Apport de potasse.'],
                    ['name' => 'Taille et effeuillage', 'type' => ActivityType::Maintenance, 'offset' => 60, 'description' => 'Taille des gourmands et effeuillage.'],
                    ['name' => 'Observation sanitaire', 'type' => ActivityType::Observation, 'offset' => 70, 'description' => 'Contrôle des maladies et ravageurs.'],
                    ['name' => 'Récolte', 'type' => ActivityType::Harvest, 'offset' => 85, 'description' => 'Récolte des fruits mûrs.'],
                    ['name' => 'Tri et conditionnement', 'type' => ActivityType::Maintenance, 'offset' => 88, 'description' => 'Tri, calibrage et conditionnement.'],
                ],
                'campaign' => [
                    'status' => CampaignStatus::Ongoing,
                    'start_offset' => -40,
                    'unit' => 'P01',
                    'area' => 400,
                    'yield' => 2.5,
                    'price' => 2500,
                    'reference' => ReferenceType::Planting,
                ],
            ],
            'Carotte' => [
                'variety' => 'New Kuroda',
                'cycle' => 100,
                'calendar' => [
                    ['name' => 'Semis', 'type' => ActivityType::Sowing, 'offset' => 0, 'description' => 'Semis direct en lignes.'],
                    ['name' => 'Désherbage', 'type' => ActivityType::Weeding, 'offset' => 20, 'description' => 'Premier désherbage.'],
                    ['name' => 'Éclaircissage', 'type' => ActivityType::Maintenance, 'offset' => 25, 'description' => 'Éclaircissage des plants.'],
                    ['name' => 'Fertilisation', 'type' => ActivityType::Fertilization, 'offset' => 35, 'description' => 'Apport de potasse.'],
                    ['name' => 'Irrigation', 'type' => ActivityType::Irrigation, 'offset' => 45, 'description' => 'Irrigation régulière.'],
                    ['name' => 'Traitement préventif', 'type' => ActivityType::Treatment, 'offset' => 50, 'description' => 'Traitement contre les maladies foliaires.'],
                    ['name' => 'Paillage', 'type' => ActivityType::Maintenance, 'offset' => 55, 'description' => 'Paillage pour limiter l’évaporation.'],
                    ['name' => 'Observation', 'type' => ActivityType::Observation, 'offset' => 65, 'description' => 'Contrôle de la croissance.'],
                    ['name' => 'Récolte', 'type' => ActivityType::Harvest, 'offset' => 95, 'description' => 'Récolte des racines.'],
                    ['name' => 'Tri et conditionnement', 'type' => ActivityType::Maintenance, 'offset' => 98, 'description' => 'Lavage, tri et conditionnement.'],
                ],
                'campaign' => [
                    'status' => CampaignStatus::Ongoing,
                    'start_offset' => -25,
                    'unit' => 'P02',
                    'area' => 300,
                    'yield' => 3.0,
                    'price' => 1800,
                    'reference' => ReferenceType::Sowing,
                ],
            ],
            'Laitue' => [
                'variety' => 'Batavia',
                'cycle' => 55,
                'calendar' => [
                    ['name' => 'Préparation du sol', 'type' => ActivityType::SoilPreparation, 'offset' => 0, 'description' => 'Préparation et amendement.'],
                    ['name' => 'Semis', 'type' => ActivityType::Sowing, 'offset' => 2, 'description' => 'Semis en pépinière.'],
                    ['name' => 'Repiquage', 'type' => ActivityType::Transplanting, 'offset' => 15, 'description' => 'Repiquage des plantules.'],
                    ['name' => 'Irrigation', 'type' => ActivityType::Irrigation, 'offset' => 25, 'description' => 'Irrigation légère et régulière.'],
                    ['name' => 'Désherbage', 'type' => ActivityType::Weeding, 'offset' => 30, 'description' => 'Désherbage manuel.'],
                    ['name' => 'Fertilisation', 'type' => ActivityType::Fertilization, 'offset' => 35, 'description' => 'Apport d’engrais azoté.'],
                    ['name' => 'Observation', 'type' => ActivityType::Observation, 'offset' => 42, 'description' => 'Contrôle des limaces et pucerons.'],
                    ['name' => 'Traitement préventif', 'type' => ActivityType::Treatment, 'offset' => 45, 'description' => 'Traitement contre les limaces.'],
                    ['name' => 'Récolte', 'type' => ActivityType::Harvest, 'offset' => 50, 'description' => 'Récolte des pommes.'],
                    ['name' => 'Tri et conditionnement', 'type' => ActivityType::Maintenance, 'offset' => 53, 'description' => 'Tri et mise en cagettes.'],
                ],
                'campaign' => [
                    'status' => CampaignStatus::Completed,
                    'start_offset' => -95,
                    'unit' => 'P03',
                    'area' => 250,
                    'yield' => 1.5,
                    'price' => 1500,
                    'reference' => ReferenceType::Planting,
                ],
            ],
            'Oignon' => [
                'variety' => 'Rouge de Tana',
                'cycle' => 120,
                'calendar' => [
                    ['name' => 'Préparation du sol', 'type' => ActivityType::SoilPreparation, 'offset' => 0, 'description' => 'Labour profond et nivellement.'],
                    ['name' => 'Semis', 'type' => ActivityType::Sowing, 'offset' => 3, 'description' => 'Semis en planches.'],
                    ['name' => 'Éclaircissage', 'type' => ActivityType::Maintenance, 'offset' => 25, 'description' => 'Éclaircissage des jeunes plants.'],
                    ['name' => 'Désherbage', 'type' => ActivityType::Weeding, 'offset' => 35, 'description' => 'Désherbage des planches.'],
                    ['name' => 'Fertilisation', 'type' => ActivityType::Fertilization, 'offset' => 45, 'description' => 'Apport d’engrais.'],
                    ['name' => 'Irrigation', 'type' => ActivityType::Irrigation, 'offset' => 55, 'description' => 'Irrigation contrôlée.'],
                    ['name' => 'Observation', 'type' => ActivityType::Observation, 'offset' => 80, 'description' => 'Contrôle de la maturité et des maladies.'],
                    ['name' => 'Traitement préventif', 'type' => ActivityType::Treatment, 'offset' => 90, 'description' => 'Traitement contre le mildiou.'],
                    ['name' => 'Récolte', 'type' => ActivityType::Harvest, 'offset' => 115, 'description' => 'Arrachage et séchage.'],
                    ['name' => 'Tri et conditionnement', 'type' => ActivityType::Maintenance, 'offset' => 118, 'description' => 'Tri et stockage.'],
                ],
                'campaign' => [
                    'status' => CampaignStatus::Completed,
                    'start_offset' => -150,
                    'unit' => 'P04',
                    'area' => 500,
                    'yield' => 2.5,
                    'price' => 2000,
                    'reference' => ReferenceType::Sowing,
                ],
            ],
            'Poivron' => [
                'variety' => 'California Wonder',
                'cycle' => 100,
                'calendar' => [
                    ['name' => 'Préparation du sol', 'type' => ActivityType::SoilPreparation, 'offset' => 0, 'description' => 'Labour et amendement.'],
                    ['name' => 'Semis', 'type' => ActivityType::Sowing, 'offset' => 5, 'description' => 'Semis en pépinière.'],
                    ['name' => 'Repiquage', 'type' => ActivityType::Transplanting, 'offset' => 30, 'description' => 'Repiquage des plants.'],
                    ['name' => 'Paillage', 'type' => ActivityType::Maintenance, 'offset' => 35, 'description' => 'Mise en place du paillage.'],
                    ['name' => 'Fertilisation', 'type' => ActivityType::Fertilization, 'offset' => 45, 'description' => 'Apport d’engrais complet.'],
                    ['name' => 'Irrigation', 'type' => ActivityType::Irrigation, 'offset' => 60, 'description' => 'Irrigation au pied.'],
                    ['name' => 'Tuteurage', 'type' => ActivityType::Maintenance, 'offset' => 65, 'description' => 'Pose des tuteurs.'],
                    ['name' => 'Traitement préventif', 'type' => ActivityType::Treatment, 'offset' => 70, 'description' => 'Traitement fongicide.'],
                    ['name' => 'Observation', 'type' => ActivityType::Observation, 'offset' => 80, 'description' => 'Contrôle des fruits et ravageurs.'],
                    ['name' => 'Récolte', 'type' => ActivityType::Harvest, 'offset' => 95, 'description' => 'Récolte des poivrons.'],
                    ['name' => 'Tri et conditionnement', 'type' => ActivityType::Maintenance, 'offset' => 98, 'description' => 'Tri et conditionnement.'],
                ],
                'campaign' => [
                    'status' => CampaignStatus::Completed,
                    'start_offset' => -130,
                    'unit' => 'P03',
                    'area' => 350,
                    'yield' => 1.8,
                    'price' => 3500,
                    'reference' => ReferenceType::Planting,
                ],
            ],
            'Concombre' => [
                'variety' => 'Marketmore',
                'cycle' => 60,
                'calendar' => [
                    ['name' => 'Préparation du sol', 'type' => ActivityType::SoilPreparation, 'offset' => 0, 'description' => 'Préparation des planches.'],
                    ['name' => 'Semis', 'type' => ActivityType::Sowing, 'offset' => 3, 'description' => 'Semis direct.'],
                    ['name' => 'Irrigation', 'type' => ActivityType::Irrigation, 'offset' => 15, 'description' => 'Irrigation fréquente.'],
                    ['name' => 'Paillage', 'type' => ActivityType::Maintenance, 'offset' => 18, 'description' => 'Mise en place du paillage.'],
                    ['name' => 'Fertilisation', 'type' => ActivityType::Fertilization, 'offset' => 25, 'description' => 'Apport de compost.'],
                    ['name' => 'Désherbage', 'type' => ActivityType::Weeding, 'offset' => 30, 'description' => 'Désherbage manuel.'],
                    ['name' => 'Tuteurage', 'type' => ActivityType::Maintenance, 'offset' => 35, 'description' => 'Pose du treillis et attachage.'],
                    ['name' => 'Observation', 'type' => ActivityType::Observation, 'offset' => 45, 'description' => 'Contrôle des fruits et maladies.'],
                    ['name' => 'Traitement préventif', 'type' => ActivityType::Treatment, 'offset' => 48, 'description' => 'Traitement contre l’oïdium.'],
                    ['name' => 'Récolte', 'type' => ActivityType::Harvest, 'offset' => 55, 'description' => 'Récolte des concombres.'],
                    ['name' => 'Tri et conditionnement', 'type' => ActivityType::Maintenance, 'offset' => 58, 'description' => 'Tri et conditionnement.'],
                ],
                'campaign' => [
                    'status' => CampaignStatus::Completed,
                    'start_offset' => -75,
                    'unit' => 'P04',
                    'area' => 300,
                    'yield' => 3.0,
                    'price' => 2000,
                    'reference' => ReferenceType::Sowing,
                ],
            ],
            'Aubergine' => [
                'variety' => 'Violette de Florence',
                'cycle' => 85,
                'calendar' => [
                    ['name' => 'Préparation du sol', 'type' => ActivityType::SoilPreparation, 'offset' => 0, 'description' => 'Labour et fumure.'],
                    ['name' => 'Semis', 'type' => ActivityType::Sowing, 'offset' => 5, 'description' => 'Semis en pépinière.'],
                    ['name' => 'Repiquage', 'type' => ActivityType::Transplanting, 'offset' => 30, 'description' => 'Repiquage des plants.'],
                    ['name' => 'Paillage', 'type' => ActivityType::Maintenance, 'offset' => 35, 'description' => 'Mise en place du paillage.'],
                    ['name' => 'Fertilisation', 'type' => ActivityType::Fertilization, 'offset' => 45, 'description' => 'Apport d’engrais.'],
                    ['name' => 'Irrigation', 'type' => ActivityType::Irrigation, 'offset' => 55, 'description' => 'Irrigation régulière.'],
                    ['name' => 'Tuteurage', 'type' => ActivityType::Maintenance, 'offset' => 60, 'description' => 'Pose des tuteurs.'],
                    ['name' => 'Observation', 'type' => ActivityType::Observation, 'offset' => 65, 'description' => 'Contrôle des fruits.'],
                    ['name' => 'Taille et effeuillage', 'type' => ActivityType::Maintenance, 'offset' => 70, 'description' => 'Taille et suppression des feuilles basses.'],
                    ['name' => 'Traitement préventif', 'type' => ActivityType::Treatment, 'offset' => 72, 'description' => 'Traitement contre les acariens.'],
                    ['name' => 'Récolte', 'type' => ActivityType::Harvest, 'offset' => 80, 'description' => 'Récolte des aubergines.'],
                    ['name' => 'Tri et conditionnement', 'type' => ActivityType::Maintenance, 'offset' => 83, 'description' => 'Tri et conditionnement.'],
                ],
                'campaign' => [
                    'status' => CampaignStatus::Ongoing,
                    'start_offset' => -50,
                    'unit' => 'P01',
                    'area' => 300,
                    'yield' => 2.0,
                    'price' => 2500,
                    'reference' => ReferenceType::Planting,
                ],
            ],
            'Courgette' => [
                'variety' => 'Zucchini',
                'cycle' => 50,
                'calendar' => [
                    ['name' => 'Préparation du sol', 'type' => ActivityType::SoilPreparation, 'offset' => 0, 'description' => 'Préparation des planches.'],
                    ['name' => 'Semis', 'type' => ActivityType::Sowing, 'offset' => 3, 'description' => 'Semis direct.'],
                    ['name' => 'Irrigation', 'type' => ActivityType::Irrigation, 'offset' => 12, 'description' => 'Irrigation régulière.'],
                    ['name' => 'Paillage', 'type' => ActivityType::Maintenance, 'offset' => 15, 'description' => 'Mise en place du paillage.'],
                    ['name' => 'Désherbage', 'type' => ActivityType::Weeding, 'offset' => 20, 'description' => 'Désherbage manuel.'],
                    ['name' => 'Fertilisation', 'type' => ActivityType::Fertilization, 'offset' => 25, 'description' => 'Apport de compost.'],
                    ['name' => 'Observation', 'type' => ActivityType::Observation, 'offset' => 35, 'description' => 'Contrôle des fruits et ravageurs.'],
                    ['name' => 'Traitement préventif', 'type' => ActivityType::Treatment, 'offset' => 38, 'description' => 'Traitement contre l’oïdium.'],
                    ['name' => 'Récolte', 'type' => ActivityType::Harvest, 'offset' => 45, 'description' => 'Récolte des courgettes.'],
                    ['name' => 'Tri et conditionnement', 'type' => ActivityType::Maintenance, 'offset' => 48, 'description' => 'Tri et conditionnement.'],
                ],
                'campaign' => [
                    'status' => CampaignStatus::Completed,
                    'start_offset' => -60,
                    'unit' => 'P02',
                    'area' => 250,
                    'yield' => 2.5,
                    'price' => 1800,
                    'reference' => ReferenceType::Sowing,
                ],
            ],
            'Chou' => [
                'variety' => 'Cœur de bœuf',
                'cycle' => 90,
                'calendar' => [
                    ['name' => 'Préparation du sol', 'type' => ActivityType::SoilPreparation, 'offset' => 0, 'description' => 'Labour et amendement.'],
                    ['name' => 'Semis', 'type' => ActivityType::Sowing, 'offset' => 3, 'description' => 'Semis en pépinière.'],
                    ['name' => 'Repiquage', 'type' => ActivityType::Transplanting, 'offset' => 25, 'description' => 'Repiquage des plants.'],
                    ['name' => 'Fertilisation', 'type' => ActivityType::Fertilization, 'offset' => 40, 'description' => 'Apport d’engrais azoté.'],
                    ['name' => 'Irrigation', 'type' => ActivityType::Irrigation, 'offset' => 55, 'description' => 'Irrigation régulière.'],
                    ['name' => 'Désherbage', 'type' => ActivityType::Weeding, 'offset' => 60, 'description' => 'Désherbage manuel.'],
                    ['name' => 'Traitement préventif', 'type' => ActivityType::Treatment, 'offset' => 65, 'description' => 'Traitement contre les chenilles.'],
                    ['name' => 'Observation', 'type' => ActivityType::Observation, 'offset' => 75, 'description' => 'Contrôle des pommes et ravageurs.'],
                    ['name' => 'Récolte', 'type' => ActivityType::Harvest, 'offset' => 85, 'description' => 'Récolte des pommes.'],
                    ['name' => 'Tri et conditionnement', 'type' => ActivityType::Maintenance, 'offset' => 88, 'description' => 'Tri et conditionnement.'],
                ],
                'campaign' => [
                    'status' => CampaignStatus::Planned,
                    'start_offset' => 7,
                    'unit' => 'P03',
                    'area' => 350,
                    'yield' => 2.2,
                    'price' => 1500,
                    'reference' => ReferenceType::Planting,
                ],
            ],
        ];
    }
}
