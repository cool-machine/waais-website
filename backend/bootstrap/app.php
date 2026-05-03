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
        // Trust the platform load balancer in front of the Laravel container so
        // Request::isSecure(), Request::getScheme(), and url() honor
        // X-Forwarded-Proto / X-Forwarded-Host / X-Forwarded-For. Used in
        // production by Azure App Service (Linux) which terminates TLS at the
        // edge and forwards plain HTTP to the worker. `at: '*'` is safe here
        // because the container is only reachable through the App Service load
        // balancer; direct port 80/443 access to the worker is not possible.
        $middleware->trustProxies(at: '*', headers: Request::HEADER_X_FORWARDED_FOR
            | Request::HEADER_X_FORWARDED_HOST
            | Request::HEADER_X_FORWARDED_PORT
            | Request::HEADER_X_FORWARDED_PROTO
            | Request::HEADER_X_FORWARDED_AWS_ELB);

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
