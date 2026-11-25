<?php

namespace Tests\Feature;

use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\InteractsWithFarm;
use Tests\TestCase;

class CustomerEncryptionTest extends TestCase
{
    use InteractsWithFarm, RefreshDatabase;

    public function test_les_donnees_personnelles_sont_chiffrees_au_repos(): void
    {
        $farm = $this->createFarm();
        $this->actingAsFarmMember($farm);

        $payload = [
            'name' => 'Restaurant Le Jardin',
            'type' => 'restaurant',
            'contact_name' => 'Ravaka',
            'phone' => '+261 34 12 345 67',
            'email' => 'ravaka@example.test',
            'address' => 'Analakely, Antananarivo',
            'notes' => 'Livraison le matin.',
            'is_active' => true,
        ];

        $this->post(route('customers.store'), $payload)->assertRedirect(route('customers.index'));

        $customer = Customer::query()->firstOrFail();

        foreach (['contact_name', 'phone', 'email', 'address', 'notes'] as $field) {
            $stored = DB::table('customers')->where('id', $customer->id)->value($field);

            $this->assertNotSame($payload[$field], $stored, "Le champ {$field} doit être chiffré au repos.");
            $this->assertSame($payload[$field], Crypt::decryptString($stored));
            $this->assertSame($payload[$field], $customer->{$field});
        }

        $this->assertSame(Customer::blindIndex($payload['phone']), $customer->phone_index);
    }

    public function test_la_recherche_par_telephone_utilise_l_index_aveugle(): void
    {
        $farm = $this->createFarm();
        $this->actingAsFarmMember($farm);

        Customer::factory()->create([
            'farm_id' => $farm->id,
            'name' => 'Client Alpha',
            'phone' => '+261 34 12 345 67',
        ]);

        Customer::factory()->create([
            'farm_id' => $farm->id,
            'name' => 'Client Beta',
            'phone' => '+261 33 00 000 00',
        ]);

        $this->get(route('customers.index', ['search' => '+261 34 12 345 67']))
            ->assertOk()
            ->assertSee('Client Alpha')
            ->assertDontSee('Client Beta');
    }

    public function test_les_valeurs_en_clair_heritees_restent_lisibles(): void
    {
        $farm = $this->createFarm();

        $id = DB::table('customers')->insertGetId([
            'farm_id' => $farm->id,
            'code' => 'CLI-999',
            'name' => 'Client historique',
            'type' => 'individual',
            'phone' => '+261 11 22 33 44',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertSame('+261 11 22 33 44', Customer::findOrFail($id)->phone);
    }
}
