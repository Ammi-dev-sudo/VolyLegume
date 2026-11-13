<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

#[Signature('app:create-admin-user')]
#[Description('Crée le compte administrateur de la plateforme à partir des variables ADMIN_*')]
class CreateAdminUser extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $email = trim((string) env('ADMIN_EMAIL', 'admin@volylegume.mg'));
        $name = (string) env('ADMIN_NAME', 'Administrateur VolyLegume');
        $password = (string) (env('ADMIN_PASSWORD') ?: env('DEMO_PASSWORD') ?: '');

        $user = User::query()->where('email', $email)->first();

        if ($password === '') {
            if ($user === null) {
                $password = Str::password(20);

                User::query()->create([
                    'name' => $name,
                    'email' => $email,
                    'email_verified_at' => now(),
                    'password' => $password,
                    'is_active' => true,
                    'is_platform_admin' => true,
                ]);

                $this->components->warn("ADMIN_PASSWORD absent : mot de passe généré pour {$email} : {$password}");

                return self::SUCCESS;
            }

            $user->update([
                'name' => $name,
                'is_active' => true,
                'is_platform_admin' => true,
            ]);

            $this->components->warn("ADMIN_PASSWORD absent : mot de passe existant conservé pour {$email}.");

            return self::SUCCESS;
        }

        if ($user === null) {
            $user = User::query()->create([
                'name' => $name,
                'email' => $email,
                'email_verified_at' => now(),
                'password' => $password,
                'is_active' => true,
                'is_platform_admin' => true,
            ]);
        } else {
            $user->update([
                'name' => $name,
                'password' => $password,
                'is_active' => true,
                'is_platform_admin' => true,
            ]);
        }

        $this->components->info("Administrateur {$user->email} prêt.");

        return self::SUCCESS;
    }
}
