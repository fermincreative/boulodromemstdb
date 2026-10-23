<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const OLD = 'U betaalt eenvoudig met iDEAL via deze link';

    private const NEW = 'U betaalt eenvoudig met iDEAL of Bancontact via deze link';

    /**
     * Mentions Bancontact next to iDEAL in the default texts; texts an admin rewrote are left alone.
     */
    public function up(): void
    {
        $this->replace(self::OLD, self::NEW);
    }

    public function down(): void
    {
        $this->replace(self::NEW, self::OLD);
    }

    private function replace(string $from, string $to): void
    {
        DB::table('mail_templates')
            ->whereIn('key', ['welcome', 'renewal'])
            ->where('body', 'like', "%{$from}%")
            ->get()
            ->each(fn (object $template) => DB::table('mail_templates')->where('id', $template->id)->update([
                'body' => str_replace($from, $to, $template->body),
                'updated_at' => now(),
            ]));
    }
};
