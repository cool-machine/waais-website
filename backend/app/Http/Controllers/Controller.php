<?php

namespace App\Http\Controllers;

use Closure;
use Illuminate\Support\Facades\App;

abstract class Controller
{
    /**
     * Run a callback after the HTTP response has been flushed to the client.
     *
     * Keeps slow side effects — notably SMTP email delivery to Azure
     * Communication Services — off the request's critical path without
     * needing a queue worker. PHP-FPM flushes the response, then Laravel
     * runs terminating callbacks, so the client gets an immediate reply
     * while the email sends a moment later in the same process.
     */
    protected function afterResponse(Closure $callback): void
    {
        App::terminating($callback);
    }
}
