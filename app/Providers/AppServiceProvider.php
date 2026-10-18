<?php

namespace App\Providers;

use App\Policies\ActivityPolicy;
use Filament\Forms\Components\DatePicker;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Spatie\Activitylog\Models\Activity;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Public sign-up form: a few attempts per hour per IP address.
        RateLimiter::for('signup', fn (Request $request) => Limit::perHour(5)->by($request->ip())->response(
            fn () => back()->withInput()->withErrors(['form' => 'Er zijn te veel aanmeldingen vanaf uw internetverbinding. Probeer het over een uur opnieuw.']),
        ));

        // Policy for a package model (not auto-discovered).
        Gate::policy(Activity::class, ActivityPolicy::class);

        // Dutch date notation everywhere in the admin panel.
        Table::configureUsing(fn (Table $table) => $table
            ->defaultDateDisplayFormat('d-m-Y')
            ->defaultDateTimeDisplayFormat('d-m-Y H:i'));

        Schema::configureUsing(fn (Schema $schema) => $schema
            ->defaultDateDisplayFormat('d-m-Y')
            ->defaultDateTimeDisplayFormat('d-m-Y H:i'));

        DatePicker::configureUsing(fn (DatePicker $picker) => $picker
            ->native(false)
            ->displayFormat('d-m-Y')
            ->firstDayOfWeek(1));
    }
}
