<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Footer columns that leave site_settings. */
    private const DROPPED = ['footer_text', 'terms_url', 'privacy_url', 'facebook_url', 'twitter_url', 'linkedin_url'];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // The public footer (App\Support\Footer), edited on Platform > Content > Footer
        Schema::create('footer_settings', function (Blueprint $table) {
            $table->id();
            $table->json('content')->nullable();
            $table->timestamps();
        });

        // Carry the footer values already entered in Site settings and the landing copy
        $setting = DB::table('site_settings')->first();
        if ($setting) {
            $landing = json_decode($setting->landing ?? 'null', true);
            $content = array_filter([
                'brand' => filled($landing['footer']['tagline'] ?? null) ? ['tagline' => $landing['footer']['tagline']] : null,
                'contact' => $setting->email || $setting->phone || $setting->address
                    ? ['email' => $setting->email, 'phone' => $setting->phone, 'address' => $setting->address]
                    : null,
                'social' => array_values(array_filter([
                    $setting->facebook_url ? ['network' => 'facebook', 'url' => $setting->facebook_url] : null,
                    $setting->twitter_url ? ['network' => 'x', 'url' => $setting->twitter_url] : null,
                    $setting->linkedin_url ? ['network' => 'linkedin', 'url' => $setting->linkedin_url] : null,
                ])) ?: null,
                'bottom' => filled($setting->footer_text) ? ['copyright' => $setting->footer_text, 'back_to_top' => true] : null,
            ]);

            if ($content) {
                DB::table('footer_settings')->insert(['content' => json_encode($content), 'created_at' => now(), 'updated_at' => now()]);
            }

            if (is_array($landing) && array_key_exists('footer', $landing)) {
                unset($landing['footer']);
                DB::table('site_settings')->where('id', $setting->id)->update(['landing' => json_encode($landing)]);
            }
        }

        Schema::table('site_settings', function (Blueprint $table) {
            $table->dropColumn(self::DROPPED);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('site_settings', function (Blueprint $table) {
            $table->string('facebook_url')->nullable();
            $table->string('twitter_url')->nullable();
            $table->string('linkedin_url')->nullable();
            $table->text('footer_text')->nullable();
            $table->string('terms_url')->nullable();
            $table->string('privacy_url')->nullable();
        });

        Schema::dropIfExists('footer_settings');
    }
};
