<?php

namespace App\Providers;

use App\Policies\ActivityPolicy;
use Filament\Forms\Components\DatePicker;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\ValidateSignature;
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
        // The language choice may be added to signed links (payment page, e-mail confirmation) without breaking them.
        ValidateSignature::except(['lang']);

        // Login links: a few per address and per connection, so nobody can flood a member's mailbox.
        RateLimiter::for('member-login', function (Request $request) {
            $tooMany = fn () => back()->withInput()->withErrors(['email' => __('portal.login.too_many')]);

            return [
                Limit::perHour(5)->by('email:'.mb_strtolower(trim((string) $request->input('email'))))->response($tooMany),
                Limit::perHour(20)->by('ip:'.$request->ip())->response($tooMany),
            ];
        });

        RateLimiter::for('signup', fn (Request $request) => Limit::perHour(5)->by($request->ip())->response(
            fn () => back()->withInput()->withErrors(['form' => __('signup.validation.too_many')]),
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
