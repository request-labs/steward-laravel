<?php

namespace Request\StewardLaravel;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\ServiceProvider;
use Request\StewardLaravel\Commands\ReportCommand;

class StewardServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/steward-agent.php', 'steward-agent');
    }

    public function boot(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->commands([ReportCommand::class]);
        $this->publishes([__DIR__.'/../config/steward-agent.php' => config_path('steward-agent.php')], 'steward-agent');

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            if ($at = config('steward-agent.daily_at')) {
                $schedule->command('steward:report')->dailyAt($at)->withoutOverlapping()->onOneServer();
            }
        });
    }
}
