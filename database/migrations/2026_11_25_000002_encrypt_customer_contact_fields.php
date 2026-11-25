<?php

use App\Support\BlindIndex;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Champs personnels chiffrés au repos.
     *
     * @var list<string>
     */
    private const ENCRYPTED_FIELDS = ['contact_name', 'phone', 'email', 'address', 'notes'];

    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->text('contact_name')->nullable()->change();
            $table->text('phone')->nullable()->change();
            $table->text('email')->nullable()->change();
            $table->text('address')->nullable()->change();
            $table->string('phone_index', 64)->nullable()->after('phone');
            $table->index('phone_index');
        });

        DB::table('customers')
            ->orderBy('id')
            ->chunkById(500, function ($customers): void {
                foreach ($customers as $customer) {
                    $update = [];

                    foreach (self::ENCRYPTED_FIELDS as $field) {
                        $value = $customer->{$field} ?? null;

                        if ($value === null || $value === '') {
                            continue;
                        }

                        if (! $this->isEncrypted((string) $value)) {
                            $update[$field] = Crypt::encryptString((string) $value);
                        }
                    }

                    $update['phone_index'] = BlindIndex::for($this->plaintext($customer->phone ?? null));

                    DB::table('customers')->where('id', $customer->id)->update($update);
                }
            });
    }

    private function isEncrypted(string $value): bool
    {
        try {
            Crypt::decryptString($value);

            return true;
        } catch (DecryptException) {
            return false;
        }
    }

    private function plaintext(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        try {
            return Crypt::decryptString($value);
        } catch (DecryptException) {
            return $value;
        }
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropIndex(['phone_index']);
            $table->dropColumn('phone_index');
        });
    }
};
