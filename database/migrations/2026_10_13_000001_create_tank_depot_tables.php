<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->string('role')->default('staff');
            $t->boolean('active')->default(true);
        });
        Schema::create('members', function (Blueprint $t) {
            $t->id(); $t->string('player_code')->unique(); $t->string('name');
            $t->string('email')->nullable(); $t->string('phone', 30)->nullable();
            $t->unsignedBigInteger('credits')->default(0); $t->boolean('active')->default(true); $t->timestamps();
        });
        Schema::create('tanks', function (Blueprint $t) {
            $t->id(); $t->string('sku')->unique(); $t->string('name'); $t->string('country', 2);
            $t->string('category'); $t->unsignedSmallInteger('year')->nullable();
            $t->text('description'); $t->text('history_note')->nullable();
            $t->string('source_title')->nullable(); $t->string('source_url', 1000)->nullable();
            $t->string('image_file')->unique(); $t->unsignedInteger('price');
            $t->unsignedInteger('stock')->default(0); $t->unsignedInteger('low_stock')->default(3);
            $t->boolean('active')->default(true); $t->timestamps();
        });
        Schema::create('sales', function (Blueprint $t) {
            $t->id(); $t->string('number')->unique(); $t->uuid('checkout_token')->unique();
            $t->foreignId('member_id')->constrained()->restrictOnDelete();
            $t->foreignId('user_id')->constrained()->restrictOnDelete();
            $t->string('member_name'); $t->string('player_code'); $t->string('employee_name');
            $t->unsignedBigInteger('subtotal'); $t->unsignedBigInteger('discount')->default(0);
            $t->unsignedBigInteger('total'); $t->unsignedBigInteger('balance_after');
            $t->string('payment_method')->default('game_credit'); $t->timestamps();
        });
        Schema::create('sale_items', function (Blueprint $t) {
            $t->id(); $t->foreignId('sale_id')->constrained()->restrictOnDelete();
            $t->foreignId('tank_id')->constrained()->restrictOnDelete();
            $t->string('tank_name'); $t->string('sku'); $t->unsignedInteger('quantity');
            $t->unsignedInteger('unit_price'); $t->unsignedBigInteger('line_total'); $t->timestamps();
        });
        Schema::create('credit_entries', function (Blueprint $t) {
            $t->id(); $t->foreignId('member_id')->constrained()->restrictOnDelete();
            $t->foreignId('user_id')->constrained()->restrictOnDelete();
            $t->foreignId('sale_id')->nullable()->unique()->constrained()->restrictOnDelete();
            $t->bigInteger('amount'); $t->unsignedBigInteger('balance_after');
            $t->string('note'); $t->timestamps();
        });
        Schema::create('stock_movements', function (Blueprint $t) {
            $t->id(); $t->foreignId('tank_id')->constrained()->restrictOnDelete();
            $t->foreignId('user_id')->constrained()->restrictOnDelete();
            $t->foreignId('sale_id')->nullable()->constrained()->restrictOnDelete();
            $t->integer('quantity'); $t->unsignedInteger('balance_after'); $t->string('note'); $t->timestamps();
        });
    }
    public function down(): void
    {
        foreach (['stock_movements','credit_entries','sale_items','sales','tanks','members'] as $table) Schema::dropIfExists($table);
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn(['role','active']));
    }
};
