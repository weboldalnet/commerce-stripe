<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stripe beállítások tábla.
 *
 * A kulcsok és a fizetési viselkedés az admin felületről is megadhatók legyenek, ne csak .env-ből — mint a
 * commerce-barion, -gls és -szamlazzhu csomagoknál (azonos séma). A titkos kulcs és a webhook-titok titkosítva
 * tárolódik. Amíg a tábla nem létezik (migráció előtt), vagy egy kulcs nincs benne, a config/.env érvényes.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('public.commerce_stripe_settings')) {
            Schema::create('public.commerce_stripe_settings', function (Blueprint $table) {
                $table->id();
                $table->string('key')->unique();
                $table->text('value')->nullable();
                // string, boolean, encrypted
                $table->string('type')->default('string');
                $table->string('group')->default('general');
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('public.commerce_stripe_settings');
    }
};
