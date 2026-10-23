<?php

namespace Tests\Feature;

use App\Enums\FarmRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithFarm;
use Tests\TestCase;

/**
 * Les formulaires affichent des listes d'options issues d'énumérations.
 * Les contrôleurs doivent fournir une collection : un simple tableau PHP
 * provoque une erreur « mapWithKeys() on array » au rendu.
 */
class EnumOptionsRenderingTest extends TestCase
{
    use InteractsWithFarm, RefreshDatabase;

    public function test_les_pages_de_formulaire_se_rendent_avec_les_options_d_enumeration(): void
    {
        $farm = $this->createFarm();
        $this->actingAsFarmMember($farm, FarmRole::Responsable);

        $pages = [
            route('team.index'),
            route('farms.index'),
            route('farms.create'),
            route('farms.edit', $farm),
            route('inputs.index'),
            route('inputs.create'),
            route('stock-movements.index'),
            route('stock-movements.create'),
            route('campaigns.index'),
            route('campaigns.create'),
            route('activities.index'),
            route('activities.create'),
            route('expenses.index'),
            route('expenses.create'),
            route('revenues.index'),
            route('revenues.create'),
        ];

        foreach ($pages as $page) {
            $this->get($page)->assertOk();
        }
    }
}
