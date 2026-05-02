<?php

use App\Console\Commands\SendAnnouncementEmails;
use App\Console\Commands\SendEventReminders;
use App\Http\Middleware\EnsureAdminAccess;
use App\Http\Middleware\EnsureMemberAccess;
use App\Http\Middleware\EnsureSuperAdminAccess;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withCommands([
        SendAnnouncementEmails::class,
        SendEventReminders::class,
    ])
    ->withSchedule(function (Schedule $schedule): void {
        $schedule->command('announcements:send-emails')
            ->hourly()
            ->withoutOverlapping();

        $schedule->command('events:send-reminders')
            ->dailyAt('09:00')
            ->withoutOverlapping();
    })
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->statefulApi();
        $middleware->redirectGuestsTo(fn (): ?string => null);

        $middleware->alias([
            'member.access' => EnsureMemberAccess::class,
            'admin.access' => EnsureAdminAccess::class,
            'super_admin.access' => EnsureSuperAdminAccess::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (AuthenticationException $exception, Request $request) {
            if ($request->is('api/*')) {
                return response()->json(['message' => 'Unauthenticated.'], 401);
            }

            return null;
        });
    })->create();
