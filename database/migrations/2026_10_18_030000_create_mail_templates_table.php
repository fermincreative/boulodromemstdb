<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mail_templates', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('name');
            $table->string('subject');
            $table->text('body');
            $table->timestamps();
        });

        DB::table('mail_templates')->insert([
            'key' => 'membership_card',
            'name' => 'Lidmaatschapskaart',
            'subject' => 'Uw lidmaatschapskaart {jaar} — {vereniging}',
            'body' => <<<'MD'
                Beste {naam},

                Hierbij ontvangt u uw lidmaatschapskaart voor {jaar} van {vereniging}. De kaart is geldig tot en met {geldig_tot}.

                U vindt twee versies in de bijlagen:

                - **Telefoon**: bewaar dit bestand op uw telefoon en toon de QR-code bij de ingang.
                - **Print**: print deze pagina op ware grootte (100%, niet "passend maken") en knip de kaart uit langs de snijtekens. Lamineren of in een pashouder steken maakt de kaart duurzamer.

                U hoeft maar één van beide te gebruiken.

                Kloppen uw gegevens niet? Laat het ons dan weten door op deze e-mail te reageren.

                Met vriendelijke groet,

                Ledenadministratie {vereniging}

                Uw lidnummer is {lidnummer}.
                MD,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('mail_templates');
    }
};
