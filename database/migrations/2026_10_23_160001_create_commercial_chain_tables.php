<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('farm_id')->constrained('farms');
            $table->string('code', 30);
            $table->string('name', 150);
            $table->string('type', 20)->default('individual');
            $table->string('contact_name', 120)->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('email')->nullable();
            $table->string('address')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['farm_id', 'code']);
            $table->unique(['farm_id', 'id']);
            $table->index(['farm_id', 'is_active']);
        });

        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('farm_id')->constrained('farms');
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->string('reference', 40);
            $table->date('sale_date');
            $table->string('status', 20)->default('confirmed');
            $table->decimal('subtotal', 16, 2)->default(0);
            $table->decimal('discount_amount', 16, 2)->default(0);
            $table->decimal('total_amount', 16, 2)->default(0);
            $table->decimal('amount_paid', 16, 2)->default(0);
            $table->string('payment_status', 20)->default('unpaid');
            $table->text('notes')->nullable();
            $table->foreignId('recorded_by')->constrained('users');
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->unique(['farm_id', 'reference']);
            $table->unique(['farm_id', 'id']);
            $table->index(['farm_id', 'sale_date']);
            $table->index(['farm_id', 'payment_status']);
        });

        Schema::create('produce_lots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('farm_id')->constrained('farms');
            $table->foreignId('harvest_id')->unique()->constrained('harvests');
            $table->foreignId('campaign_id')->constrained('campaigns');
            $table->string('code', 40);
            $table->decimal('initial_quantity', 14, 3);
            $table->decimal('available_quantity', 14, 3);
            $table->string('unit', 10)->default('kg');
            $table->string('quality', 50)->nullable();
            $table->timestamp('harvested_at');
            $table->string('storage_location')->nullable();
            $table->string('status', 20)->default('available');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['farm_id', 'code']);
            $table->unique(['farm_id', 'id']);
            $table->index(['farm_id', 'status', 'harvested_at']);
        });

        Schema::create('produce_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('farm_id')->constrained('farms');
            $table->foreignId('produce_lot_id')->constrained('produce_lots');
            $table->foreignId('sale_id')->nullable()->constrained('sales')->nullOnDelete();
            $table->string('type', 20);
            $table->decimal('quantity_delta', 14, 3);
            $table->string('reason')->nullable();
            $table->timestamp('occurred_at');
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();

            $table->index(['farm_id', 'occurred_at']);
            $table->index(['produce_lot_id', 'occurred_at']);
        });

        Schema::create('sale_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('farm_id')->constrained('farms');
            $table->foreignId('sale_id')->constrained('sales')->cascadeOnDelete();
            $table->foreignId('produce_lot_id')->constrained('produce_lots');
            $table->string('description', 150);
            $table->decimal('quantity', 14, 3);
            $table->string('unit', 10)->default('kg');
            $table->decimal('unit_price', 14, 2);
            $table->decimal('line_total', 16, 2);
            $table->timestamps();

            $table->index(['farm_id', 'produce_lot_id']);
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('farm_id')->constrained('farms');
            $table->foreignId('sale_id')->constrained('sales')->cascadeOnDelete();
            $table->decimal('amount', 16, 2);
            $table->string('method', 30)->default('cash');
            $table->date('paid_at');
            $table->string('reference', 100)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('recorded_by')->constrained('users');
            $table->timestamps();

            $table->index(['farm_id', 'paid_at']);
        });

        Schema::table('revenues', function (Blueprint $table) {
            $table->foreignId('sale_id')->nullable()->unique()->after('harvest_id')
                ->constrained('sales')->nullOnDelete();
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE customers ADD CONSTRAINT customers_type_valid CHECK (type IN ('individual', 'retailer', 'restaurant', 'hotel', 'cooperative', 'other'))");
            DB::statement("ALTER TABLE sales ADD CONSTRAINT sales_status_valid CHECK (status IN ('draft', 'confirmed', 'cancelled'))");
            DB::statement("ALTER TABLE sales ADD CONSTRAINT sales_payment_status_valid CHECK (payment_status IN ('unpaid', 'partial', 'paid', 'refunded'))");
            DB::statement('ALTER TABLE sales ADD CONSTRAINT sales_amounts_non_negative CHECK (subtotal >= 0 AND discount_amount >= 0 AND total_amount >= 0 AND amount_paid >= 0)');
            DB::statement('ALTER TABLE sales ADD CONSTRAINT sales_paid_not_above_total CHECK (amount_paid <= total_amount)');
            DB::statement('ALTER TABLE produce_lots ADD CONSTRAINT produce_lots_quantities_valid CHECK (initial_quantity > 0 AND available_quantity >= 0 AND available_quantity <= initial_quantity)');
            DB::statement("ALTER TABLE produce_lots ADD CONSTRAINT produce_lots_status_valid CHECK (status IN ('available', 'reserved', 'depleted', 'lost'))");
            DB::statement("ALTER TABLE produce_movements ADD CONSTRAINT produce_movements_type_valid CHECK (type IN ('harvest', 'sale', 'loss', 'gift', 'consumption', 'return', 'adjustment'))");
            DB::statement('ALTER TABLE produce_movements ADD CONSTRAINT produce_movements_delta_non_zero CHECK (quantity_delta <> 0)');
            DB::statement('ALTER TABLE sale_items ADD CONSTRAINT sale_items_quantity_positive CHECK (quantity > 0)');
            DB::statement('ALTER TABLE sale_items ADD CONSTRAINT sale_items_prices_non_negative CHECK (unit_price >= 0 AND line_total >= 0)');
            DB::statement('ALTER TABLE payments ADD CONSTRAINT payments_amount_positive CHECK (amount > 0)');
            DB::statement("ALTER TABLE payments ADD CONSTRAINT payments_method_valid CHECK (method IN ('cash', 'mobile_money', 'bank_transfer', 'check', 'other'))");
        }
    }

    public function down(): void
    {
        Schema::table('revenues', function (Blueprint $table) {
            $table->dropForeign(['sale_id']);
            $table->dropColumn('sale_id');
        });

        Schema::dropIfExists('payments');
        Schema::dropIfExists('sale_items');
        Schema::dropIfExists('produce_movements');
        Schema::dropIfExists('produce_lots');
        Schema::dropIfExists('sales');
        Schema::dropIfExists('customers');
    }
};
