<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class BackupDatabase extends Command
{
    protected $signature = 'db:backup
        {--path= : Map voor de back-ups (standaard storage/app/private/backups)}
        {--keep=14 : Aantal dagelijkse back-ups dat bewaard blijft}';

    protected $description = 'Maak een consistente kopie van de SQLite-database (VACUUM INTO)';

    public function handle(): int
    {
        if (DB::connection()->getDriverName() !== 'sqlite') {
            $this->components->error('db:backup werkt alleen met SQLite.');

            return self::FAILURE;
        }

        $directory = $this->option('path') ?? storage_path('app/private/backups');
        File::ensureDirectoryExists($directory, 0700);

        $target = $directory.'/ledenadmin-'.now()->format('Y-m-d-His').'.sqlite';

        // VACUUM INTO produces a consistent snapshot, also while WAL writes are in progress.
        DB::statement('VACUUM INTO ?', [$target]);
        chmod($target, 0600);

        $backups = collect(File::glob($directory.'/ledenadmin-*.sqlite'))->sort()->values();
        $backups->slice(0, max(0, $backups->count() - (int) $this->option('keep')))
            ->each(fn (string $file) => File::delete($file));

        $this->components->info("Back-up gemaakt: {$target}");

        return self::SUCCESS;
    }
}
