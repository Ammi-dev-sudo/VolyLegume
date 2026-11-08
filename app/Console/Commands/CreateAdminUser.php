<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:create-admin-user')]
#[Description('Crée le compte administrateur de la plateforme à partir des variables ADMIN_*')]
class CreateAdminUser extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $email = (string) env('ADMIN_EMAIL', '');
        $password = (string) env('ADMIN_PASSWORD', '');

        if ($email === '' || $password === '') {
            $this->components->warn('ADMIN_EMAIL ou ADMIN_PASSWORD absent : aucun administrateur créé.');

            return self::SUCCESS;
        }

        $user = User::firstOrCreate(
            ['email' => $email],
            [
                'name' => (string) env('ADMIN_NAME', 'Administrateur'),
                'password' => $password,
                'is_active' => true,
                'is_platform_admin' => true,
            ],
        );

        if (! $user->wasRecentlyCreated) {
            $user->update([
                'name' => (string) env('ADMIN_NAME', $user->name),
                'password' => $password,
                'is_active' => true,
                'is_platform_admin' => true,
            ]);
        }

        $this->components->info("Administrateur {$user->email} prêt.");

        return self::SUCCESS;
    }
}
