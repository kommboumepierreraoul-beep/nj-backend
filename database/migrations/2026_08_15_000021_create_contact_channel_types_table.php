<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Canaux de contact deja en usage actuellement ; les canaux futurs (Facebook,
    // Instagram, WeChat...) s'ajoutent par simple insertion de ligne, sans migration.
    private const DEFAULT_CHANNELS = [
        ['code' => 'EMAIL', 'label' => 'Email', 'icon' => 'mail'],
        ['code' => 'PHONE', 'label' => 'Telephone', 'icon' => 'phone'],
        ['code' => 'WHATSAPP', 'label' => 'WhatsApp', 'icon' => 'whatsapp'],
    ];

    public function up(): void
    {
        Schema::create('contact_channel_types', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('label');
            $table->string('icon')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        $now = now();

        foreach (self::DEFAULT_CHANNELS as $channel) {
            DB::table('contact_channel_types')->insert([
                'code' => $channel['code'],
                'label' => $channel['label'],
                'icon' => $channel['icon'],
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_channel_types');
    }
};
