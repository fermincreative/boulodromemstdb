<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        DB::table('settings')->insert([
            ['key' => 'bank_iban', 'value' => json_encode('NL96 ABNA 0617 0464 84'), 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'bank_account_holder', 'value' => json_encode('Stichting Boulodrôme Maastricht'), 'created_at' => $now, 'updated_at' => $now],
        ]);

        DB::table('mail_templates')->insert([
            [
                'key' => 'welcome',
                'name' => 'Aanmelding',
                'subject' => 'Welkom bij {vereniging} — uw aanmelding',
                'body' => <<<'MD'
                    Beste {naam},

                    Hartelijk dank voor uw aanmelding als lid van {vereniging}. Uw lidnummer is {lidnummer}.

                    De contributie bedraagt **{bedrag}** ({omschrijving}). Uw lidmaatschap loopt tot en met {geldig_tot}.

                    Wilt u dit bedrag overmaken naar:

                    - Rekeningnummer: **{iban}**
                    - Ten name van: {rekeninghouder}
                    - Onder vermelding van: {betaalkenmerk}

                    Zodra wij uw betaling hebben ontvangen, sturen wij u uw lidmaatschapskaart per e-mail.

                    Met vriendelijke groet,

                    Ledenadministratie {vereniging}
                    MD,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'key' => 'payment_confirmation',
                'name' => 'Betalingsbevestiging',
                'subject' => 'Betalingsbevestiging — {vereniging}',
                'body' => <<<'MD'
                    Beste {naam},

                    Wij hebben uw betaling van **{bedrag}** voor {omschrijving} ontvangen en op {betaald_op} verwerkt. Hartelijk dank!

                    Uw lidmaatschap is geldig tot en met {geldig_tot}.

                    Met vriendelijke groet,

                    Ledenadministratie {vereniging}
                    MD,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'key' => 'cancellation',
                'name' => 'Opzegging',
                'subject' => 'Bevestiging opzegging lidmaatschap — {vereniging}',
                'body' => <<<'MD'
                    Beste {naam},

                    Hierbij bevestigen wij dat uw lidmaatschap van {vereniging} is opgezegd per {opgezegd_per}.

                    Wij danken u hartelijk voor uw lidmaatschap en wensen u het allerbeste. U bent altijd welkom om opnieuw lid te worden.

                    Met vriendelijke groet,

                    Ledenadministratie {vereniging}
                    MD,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }

    public function down(): void
    {
        DB::table('mail_templates')->whereIn('key', ['welcome', 'payment_confirmation', 'cancellation'])->delete();
        DB::table('settings')->whereIn('key', ['bank_iban', 'bank_account_holder'])->delete();
    }
};
