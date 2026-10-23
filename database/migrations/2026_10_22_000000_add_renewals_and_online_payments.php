<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The welcome text as first installed; only that version gets the payment link added automatically.
     */
    private const WELCOME_PAY_BY_TRANSFER = <<<'MD'
        Wilt u dit bedrag overmaken naar:
        MD;

    private const WELCOME_PAY_ONLINE = <<<'MD'
        U betaalt eenvoudig met iDEAL via deze link: [{bedrag} betalen]({betaallink})

        Liever overmaken? Dat kan naar:
        MD;

    public function up(): void
    {
        Schema::table('clubs', function (Blueprint $table) {
            // Members of these clubs are paid for by their club, not individually.
            $table->boolean('pays_collectively')->default(false)->after('name');
            // The club everyone who signs up through the public form joins.
            $table->boolean('is_signup_club')->default(false)->after('pays_collectively');
        });

        DB::table('clubs')->whereIn('name', ['Mosa Petanca', 'JBC Amby'])->update(['pays_collectively' => true]);
        DB::table('clubs')->where('name', 'SBM Halpas')->update(['is_signup_club' => true]);

        Schema::table('payments', function (Blueprint $table) {
            // The latest Mollie payment (tr_…) started for this payment.
            $table->string('mollie_id')->nullable()->after('reference')->index();
        });

        $now = now();

        DB::table('mail_templates')->insert([
            'key' => 'renewal',
            'name' => 'Verlenging',
            'subject' => 'Verlenging lidmaatschap {jaar} — {vereniging}',
            'body' => <<<'MD'
                Beste {naam},

                Het nieuwe jaar komt eraan. Graag verlengen wij uw lidmaatschap van {vereniging} voor {jaar}.

                De contributie voor {jaar} bedraagt **{bedrag}**. U betaalt eenvoudig met iDEAL via deze link:

                [{bedrag} betalen]({betaallink})

                Liever overmaken? Dat kan naar:

                - Rekeningnummer: **{iban}**
                - Ten name van: {rekeninghouder}
                - Onder vermelding van: {betaalkenmerk}

                Zodra wij uw betaling hebben ontvangen, sturen wij u uw lidmaatschapskaart voor {jaar} per e-mail.

                Wilt u uw lidmaatschap niet verlengen? Laat het ons dan weten door op deze e-mail te reageren.

                Met vriendelijke groet,

                Ledenadministratie {vereniging}
                MD,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // Add the iDEAL link to the welcome text, unless an admin already changed that part.
        $welcome = DB::table('mail_templates')->where('key', 'welcome')->first();

        if ($welcome && str_contains($welcome->body, self::WELCOME_PAY_BY_TRANSFER) && ! str_contains($welcome->body, '{betaallink}')) {
            DB::table('mail_templates')->where('key', 'welcome')->update([
                'body' => str_replace(self::WELCOME_PAY_BY_TRANSFER, self::WELCOME_PAY_ONLINE, $welcome->body),
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        $welcome = DB::table('mail_templates')->where('key', 'welcome')->first();

        if ($welcome) {
            DB::table('mail_templates')->where('key', 'welcome')->update([
                'body' => str_replace(self::WELCOME_PAY_ONLINE, self::WELCOME_PAY_BY_TRANSFER, $welcome->body),
            ]);
        }

        DB::table('mail_templates')->where('key', 'renewal')->delete();

        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex(['mollie_id']);
            $table->dropColumn('mollie_id');
        });

        Schema::table('clubs', function (Blueprint $table) {
            $table->dropColumn(['pays_collectively', 'is_signup_club']);
        });
    }
};
