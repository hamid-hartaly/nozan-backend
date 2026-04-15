<?php

use Illuminate\Contracts\Console\Kernel as ConsoleKernelContract;
use Illuminate\Contracts\Debug\ExceptionHandler as ExceptionHandlerContract;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Application;
use App\Services\WhatsAppService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('whatsapp:test {phone?} {name?}', function (?string $phone = null, ?string $name = null) {
    $service = new WhatsAppService();
    $diagnostics = $service->configDiagnostics();

    if (! $service->isConfigured()) {
        $this->error('WhatsApp is not configured correctly.');
        if (! empty($diagnostics['hint'])) {
            $this->warn((string) $diagnostics['hint']);
        }
        return 1;
    }

    $targetPhone = trim((string) ($phone ?: config('services.whatsapp.support_number', '07704330005')));
    $targetName = trim((string) ($name ?: 'Nozan Customer'));

    if ($targetPhone === '') {
        $this->error('No target phone number provided.');
        return 1;
    }

    $sent = $service->sendManualTestMessage($targetPhone, $targetName);

    if (! $sent) {
        $this->error('WhatsApp test message failed. Check storage/logs/laravel.log for details.');
        return 1;
    }

    $this->info("WhatsApp test message sent to {$targetPhone}.");
    return 0;
})->purpose('Send a WhatsApp test message using current backend credentials');

Artisan::command('route:diagnose-invoice-payment', function () {
    $registeredRoutes = collect(Route::getRoutes()->getRoutes())
        ->filter(fn ($route) => in_array('POST', $route->methods(), true))
        ->filter(fn ($route) => preg_match('#^api/finance/invoices/\{[^}]+\}/payments$#', $route->uri()) === 1)
        ->values();

    $apiFile = base_path('routes/api.php');
    $apiWrapperFile = base_path('routes/api_with_invoice_payment.php');
    $webFile = base_path('routes/web.php');
    $apiContents = file_exists($apiFile) ? (string) file_get_contents($apiFile) : '';
    $apiWrapperContents = file_exists($apiWrapperFile) ? (string) file_get_contents($apiWrapperFile) : '';
    $webContents = file_exists($webFile) ? (string) file_get_contents($webFile) : '';

    $apiContainsRoute = str_contains($apiContents, "finance/invoices/{invoice}/payments")
        || str_contains($apiContents, "finance/invoices/{invoiceId}/payments")
        || str_contains($apiContents, "/invoices/{invoiceId}/payments");
    $apiWrapperContainsRoute = str_contains($apiWrapperContents, "finance/invoices/{invoice}/payments")
        || str_contains($apiWrapperContents, "finance/invoices/{invoiceId}/payments")
        || str_contains($apiWrapperContents, "/invoices/{invoiceId}/payments");
    $webContainsRoute = str_contains($webContents, "api/finance/invoices/{invoice}/payments")
        || str_contains($webContents, "api/finance/invoices/{invoiceId}/payments");

    $this->newLine();
    $this->info('Invoice Payment Route Diagnostics');
    $this->line('Expected URI: POST api/finance/invoices/{invoiceId}/payments');
    $this->line('api.php contains route text: ' . ($apiContainsRoute ? 'yes' : 'no'));
    $this->line('api_with_invoice_payment.php contains route text: ' . ($apiWrapperContainsRoute ? 'yes' : 'no'));
    $this->line('web.php contains route text: ' . ($webContainsRoute ? 'yes' : 'no'));
    $this->newLine();

    if ($registeredRoutes->isEmpty()) {
        $this->error('No registered POST route matched api/finance/invoices/{...}/payments.');

        if ($apiContainsRoute || $apiWrapperContainsRoute) {
            $this->warn('An API route file contains the route text, but Laravel did not register it.');
            $this->warn('This matches the known selective route-registration issue in this repo.');
        }

        return 1;
    }

    foreach ($registeredRoutes as $route) {
        $middleware = implode(', ', $route->gatherMiddleware());

        $this->line('Registered route:');
        $this->line('  URI: ' . $route->uri());
        $this->line('  Action: ' . ltrim($route->getActionName(), '\\'));
        $this->line('  Middleware: ' . ($middleware !== '' ? $middleware : '(none)'));
        $this->newLine();
    }

    if (($apiContainsRoute || $apiWrapperContainsRoute) && $webContainsRoute) {
        $this->warn('Both route files contain invoice payment route text. Keep only the working registration.');
    } elseif (! $apiContainsRoute && $apiWrapperContainsRoute && ! $webContainsRoute) {
        $this->warn('The route is currently registered through the API wrapper file routes/api_with_invoice_payment.php.');
    } elseif (! $apiContainsRoute && ! $apiWrapperContainsRoute && $webContainsRoute) {
        $this->warn('The working route is currently registered via routes/web.php fallback.');
    } elseif (($apiContainsRoute || $apiWrapperContainsRoute) && ! $webContainsRoute) {
        $this->warn('The route text exists only in API route files. Re-check runtime registration if requests 404.');
    }

    return 0;
})->purpose('Diagnose the selective registration issue for the finance invoice payment route');

Artisan::command('route:diagnose-api-registration-matrix', function () {
    $targets = [
        [
            'label' => 'invoice payment',
            'method' => 'POST',
            'uri' => 'api/finance/invoices/{invoiceId}/payments',
            'apiNeedle' => '/invoices/{invoiceId}/payments',
            'webNeedle' => 'api/finance/invoices/{invoiceId}/payments',
        ],
        [
            'label' => 'invoice create',
            'method' => 'POST',
            'uri' => 'api/finance/invoices',
            'apiNeedle' => "Route::post('/invoices', [FinanceController::class, 'storeInvoice'])",
            'webNeedle' => "Route::middleware(['api', 'auth:sanctum'])->post('/api/finance/invoices',",
        ],
        [
            'label' => 'job payment',
            'method' => 'POST',
            'uri' => 'api/jobs/{job}/payments',
            'apiNeedle' => "Route::post('/{job}/payments', [PaymentController::class, 'store'])",
            'webNeedle' => '/api/jobs/{job}/payments',
        ],
        [
            'label' => 'job return',
            'method' => 'POST',
            'uri' => 'api/jobs/{job}/return',
            'apiNeedle' => "Route::post('/{job}/return', [JobController::class, 'createReturnJob'])",
            'webNeedle' => '/api/jobs/{job}/return',
        ],
        [
            'label' => 'inventory movement',
            'method' => 'POST',
            'uri' => 'api/inventory/{item}/movements',
            'apiNeedle' => "Route::post('/{item}/movements', [InventoryController::class, 'recordMovement'])",
            'webNeedle' => '/api/inventory/{item}/movements',
        ],
    ];

    $apiContents = (string) file_get_contents(base_path('routes/api.php'));
    $apiWrapperContents = file_exists(base_path('routes/api_with_invoice_payment.php'))
        ? (string) file_get_contents(base_path('routes/api_with_invoice_payment.php'))
        : '';
    $webContents = (string) file_get_contents(base_path('routes/web.php'));
    $routes = collect(Route::getRoutes()->getRoutes());

    $combinedApiContents = $apiContents . PHP_EOL . $apiWrapperContents;

    $rows = collect($targets)->map(function (array $target) use ($combinedApiContents, $routes, $webContents): array {
        $route = $routes->first(fn ($candidate) => in_array($target['method'], $candidate->methods(), true)
            && $candidate->uri() === $target['uri']);

        return [
            'label' => $target['label'],
            'method' => $target['method'],
            'uri' => $target['uri'],
            'registered' => $route ? 'yes' : 'no',
            'in_api' => str_contains($combinedApiContents, $target['apiNeedle']) ? 'yes' : 'no',
            'in_web' => str_contains($webContents, $target['webNeedle']) ? 'yes' : 'no',
            'action' => $route ? ltrim($route->getActionName(), '\\') : '-',
        ];
    });

    $this->newLine();
    $this->info('API Route Registration Matrix');
    $this->table(
        ['label', 'method', 'uri', 'registered', 'in_api', 'in_web', 'action'],
        $rows->all(),
    );

    $invoicePayment = $rows->firstWhere('label', 'invoice payment');
    if ($invoicePayment !== null && $invoicePayment['registered'] === 'yes' && $invoicePayment['in_api'] === 'no' && $invoicePayment['in_web'] === 'yes') {
        $this->warn('Invoice payment is the known outlier: registered from web.php fallback instead of api.php.');
    }

    return 0;
})->purpose('Compare runtime registration for invoice payment against similar API route shapes');

Artisan::command('route:probe-invoice-payment-shapes', function () {
    $makeRouter = function (): Router {
        /** @var Dispatcher $events */
        $events = app(Dispatcher::class);

        return new Router($events, app());
    };

    $scenarios = [
        [
            'label' => 'plain explicit path',
            'expected' => 'finance/invoices/{invoiceId}/payments',
            'define' => static function (Router $router): void {
                $router->post('/finance/invoices/{invoiceId}/payments', static fn () => 'ok');
            },
        ],
        [
            'label' => 'plain finance prefix',
            'expected' => 'finance/invoices/{invoiceId}/payments',
            'define' => static function (Router $router): void {
                $router->group(['prefix' => 'finance'], function () use ($router): void {
                    $router->post('/invoices/{invoiceId}/payments', static fn () => 'ok');
                });
            },
        ],
        [
            'label' => 'auth group plus finance prefix',
            'expected' => 'finance/invoices/{invoiceId}/payments',
            'define' => static function (Router $router): void {
                $router->middleware('auth:sanctum')->group(function () use ($router): void {
                    $router->group(['prefix' => 'finance'], function () use ($router): void {
                        $router->post('/invoices/{invoiceId}/payments', static fn () => 'ok');
                    });
                });
            },
        ],
        [
            'label' => 'auth group with finance siblings',
            'expected' => 'finance/invoices/{invoiceId}/payments',
            'define' => static function (Router $router): void {
                $router->middleware('auth:sanctum')->group(function () use ($router): void {
                    $router->group(['prefix' => 'finance'], function () use ($router): void {
                        $router->get('/invoices', static fn () => 'ok');
                        $router->post('/invoices', static fn () => 'ok');
                        $router->post('/invoices/{invoiceId}/payments', static fn () => 'ok');
                        $router->get('/payments', static fn () => 'ok');
                    });
                });
            },
        ],
        [
            'label' => 'full api-like nesting',
            'expected' => 'finance/invoices/{invoiceId}/payments',
            'define' => static function (Router $router): void {
                $router->middleware('auth:sanctum')->group(function () use ($router): void {
                    $router->group(['prefix' => 'jobs'], function () use ($router): void {
                        $router->post('/{job}/payments', static fn () => 'ok');
                    });

                    $router->group(['prefix' => 'customers'], function () use ($router): void {
                        $router->get('/', static fn () => 'ok');
                    });

                    $router->group(['prefix' => 'finance'], function () use ($router): void {
                        $router->get('/dashboard', static fn () => 'ok');
                        $router->get('/invoices', static fn () => 'ok');
                        $router->post('/invoices', static fn () => 'ok');
                        $router->post('/invoices/{invoiceId}/payments', static fn () => 'ok');
                        $router->get('/payments', static fn () => 'ok');
                    });
                });
            },
        ],
        [
            'label' => 'controller action under auth finance prefix',
            'expected' => 'finance/invoices/{invoiceId}/payments',
            'define' => static function (Router $router): void {
                $router->middleware('auth:sanctum')->group(function () use ($router): void {
                    $router->group(['prefix' => 'finance'], function () use ($router): void {
                        $router->post('/invoices/{invoiceId}/payments', [\App\Http\Controllers\Api\FinanceController::class, 'recordInvoicePayment']);
                    });
                });
            },
        ],
        [
            'label' => 'controller action with finance siblings',
            'expected' => 'finance/invoices/{invoiceId}/payments',
            'define' => static function (Router $router): void {
                $router->middleware('auth:sanctum')->group(function () use ($router): void {
                    $router->group(['prefix' => 'finance'], function () use ($router): void {
                        $router->get('/dashboard', [\App\Http\Controllers\Api\FinanceController::class, 'dashboard']);
                        $router->get('/invoices', [\App\Http\Controllers\Api\FinanceController::class, 'invoices']);
                        $router->post('/invoices', [\App\Http\Controllers\Api\FinanceController::class, 'storeInvoice']);
                        $router->post('/invoices/{invoiceId}/payments', [\App\Http\Controllers\Api\FinanceController::class, 'recordInvoicePayment']);
                        $router->get('/payments', [\App\Http\Controllers\Api\FinanceController::class, 'payments']);
                    });
                });
            },
        ],
    ];

    $rows = collect($scenarios)->map(function (array $scenario) use ($makeRouter): array {
        $router = $makeRouter();
        $scenario['define']($router);

        $route = collect($router->getRoutes()->getRoutes())
            ->first(fn ($candidate) => in_array('POST', $candidate->methods(), true)
                && $candidate->uri() === $scenario['expected']);

        return [
            'scenario' => $scenario['label'],
            'expected_uri' => $scenario['expected'],
            'registered' => $route ? 'yes' : 'no',
            'methods' => $route ? implode(',', array_values(array_filter($route->methods(), fn (string $method) => $method !== 'HEAD'))) : '-',
            'middleware' => $route ? implode(', ', $route->gatherMiddleware()) : '-',
        ];
    });

    $this->newLine();
    $this->info('Invoice Payment Route Shape Probes');
    $this->table(['scenario', 'expected_uri', 'registered', 'methods', 'middleware'], $rows->all());

    if ($rows->every(fn (array $row) => $row['registered'] === 'yes')) {
        $this->warn('All in-memory probes register correctly. The bug is likely in this app\'s route loading path, not Laravel route shape syntax itself.');
    }

    return 0;
})->purpose('Probe invoice payment route shapes in an isolated in-memory router');

Artisan::command('route:probe-route-file-loading', function () {
    $makeRouter = function (): Router {
        /** @var Dispatcher $events */
        $events = app(Dispatcher::class);

        return new Router($events, app());
    };

    $tempDir = storage_path('framework/route-probes');

    if (! is_dir($tempDir)) {
        mkdir($tempDir, 0777, true);
    }

    $writeProbeFile = static function (string $name, string $contents) use ($tempDir): string {
        $path = $tempDir . DIRECTORY_SEPARATOR . $name;
        file_put_contents($path, $contents);

        return $path;
    };

    $scenarios = [
        [
            'label' => 'single route file under auth group',
            'expected' => 'finance/invoices/{invoiceId}/payments',
            'file' => <<<'PHP'
<?php

$router->post('/finance/invoices/{invoiceId}/payments', static fn () => 'ok');
PHP,
            'load' => static function (Router $router, string $path): void {
                $router->middleware('auth:sanctum')->group($path);
            },
        ],
        [
            'label' => 'finance prefix route file under auth group',
            'expected' => 'finance/invoices/{invoiceId}/payments',
            'file' => <<<'PHP'
<?php

$router->prefix('finance')->group(function () use ($router): void {
    $router->post('/invoices/{invoiceId}/payments', static fn () => 'ok');
});
PHP,
            'load' => static function (Router $router, string $path): void {
                $router->middleware('auth:sanctum')->group($path);
            },
        ],
        [
            'label' => 'api-like composite route file',
            'expected' => 'finance/invoices/{invoiceId}/payments',
            'file' => <<<'PHP'
<?php

$router->prefix('jobs')->group(function () use ($router): void {
    $router->post('/{job}/payments', static fn () => 'ok');
});

$router->prefix('customers')->group(function () use ($router): void {
    $router->get('/', static fn () => 'ok');
});

$router->prefix('finance')->group(function () use ($router): void {
    $router->get('/dashboard', static fn () => 'ok');
    $router->get('/invoices', static fn () => 'ok');
    $router->post('/invoices', static fn () => 'ok');
    $router->post('/invoices/{invoiceId}/payments', static fn () => 'ok');
    $router->get('/payments', static fn () => 'ok');
});

$router->prefix('inventory')->group(function () use ($router): void {
    $router->post('/{item}/movements', static fn () => 'ok');
});
PHP,
            'load' => static function (Router $router, string $path): void {
                $router->middleware('auth:sanctum')->group($path);
            },
        ],
        [
            'label' => 'controller action route file under auth group',
            'expected' => 'finance/invoices/{invoiceId}/payments',
            'file' => <<<'PHP'
<?php

$router->group(['prefix' => 'finance'], function () use ($router): void {
    $router->post('/invoices/{invoiceId}/payments', [\App\Http\Controllers\Api\FinanceController::class, 'recordInvoicePayment']);
});
PHP,
            'load' => static function (Router $router, string $path): void {
                $router->middleware('auth:sanctum')->group($path);
            },
        ],
        [
            'label' => 'controller action api-like route file',
            'expected' => 'finance/invoices/{invoiceId}/payments',
            'file' => <<<'PHP'
<?php

$router->group(['prefix' => 'jobs'], function () use ($router): void {
    $router->post('/{job}/payments', [\App\Http\Controllers\Api\PaymentController::class, 'store']);
});

$router->group(['prefix' => 'finance'], function () use ($router): void {
    $router->get('/dashboard', [\App\Http\Controllers\Api\FinanceController::class, 'dashboard']);
    $router->get('/invoices', [\App\Http\Controllers\Api\FinanceController::class, 'invoices']);
    $router->post('/invoices', [\App\Http\Controllers\Api\FinanceController::class, 'storeInvoice']);
    $router->post('/invoices/{invoiceId}/payments', [\App\Http\Controllers\Api\FinanceController::class, 'recordInvoicePayment']);
    $router->get('/payments', [\App\Http\Controllers\Api\FinanceController::class, 'payments']);
});
PHP,
            'load' => static function (Router $router, string $path): void {
                $router->middleware('auth:sanctum')->group($path);
            },
        ],
    ];

    try {
        $rows = collect($scenarios)->map(function (array $scenario, int $index) use ($makeRouter, $writeProbeFile): array {
            $router = $makeRouter();
            $path = $writeProbeFile(sprintf('probe_%02d.php', $index + 1), $scenario['file']);

            $scenario['load']($router, $path);

            $route = collect($router->getRoutes()->getRoutes())
                ->first(fn ($candidate) => in_array('POST', $candidate->methods(), true)
                    && $candidate->uri() === $scenario['expected']);

            return [
                'scenario' => $scenario['label'],
                'registered' => $route ? 'yes' : 'no',
                'uri' => $scenario['expected'],
                'middleware' => $route ? implode(', ', $route->gatherMiddleware()) : '-',
            ];
        });

        $this->newLine();
        $this->info('Route File Loading Probes');
        $this->table(['scenario', 'registered', 'uri', 'middleware'], $rows->all());

        if ($rows->every(fn (array $row) => $row['registered'] === 'yes')) {
            $this->warn('File-based route loading also registers these probes correctly. The failure is likely specific to this app\'s real routes/api.php context.');
        }
    } finally {
        foreach (glob($tempDir . DIRECTORY_SEPARATOR . 'probe_*.php') ?: [] as $file) {
            @unlink($file);
        }
    }

    return 0;
})->purpose('Probe invoice payment registration using temporary route files loaded through Router::group()');

Artisan::command('route:probe-fresh-app-routing', function () {
    $tempDir = storage_path('framework/route-probes');

    if (! is_dir($tempDir)) {
        mkdir($tempDir, 0777, true);
    }

    $baseApiFile = base_path('routes/api.php');
    $apiSource = (string) file_get_contents($baseApiFile);

    $scenarios = [
        [
            'label' => 'actual api.php as-is',
            'contents' => $apiSource,
        ],
        [
            'label' => 'actual api.php plus invoice payment in finance group',
            'contents' => str_replace(
                "        Route::post('/invoices', [FinanceController::class, 'storeInvoice']);",
                "        Route::post('/invoices', [FinanceController::class, 'storeInvoice']);\n        Route::post('/invoices/{invoiceId}/payments', [FinanceController::class, 'recordInvoicePayment']);",
                $apiSource,
            ),
        ],
        [
            'label' => 'minimal finance-only file with controller action',
            'contents' => <<<'PHP'
<?php

use App\Http\Controllers\Api\FinanceController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::prefix('finance')->group(function () {
        Route::get('/invoices', [FinanceController::class, 'invoices']);
        Route::post('/invoices', [FinanceController::class, 'storeInvoice']);
        Route::post('/invoices/{invoiceId}/payments', [FinanceController::class, 'recordInvoicePayment']);
        Route::get('/payments', [FinanceController::class, 'payments']);
    });
});
PHP,
        ],
    ];

    $rows = [];

    try {
        foreach ($scenarios as $index => $scenario) {
            $apiPath = $tempDir . DIRECTORY_SEPARATOR . sprintf('fresh_api_probe_%02d.php', $index + 1);
            file_put_contents($apiPath, $scenario['contents']);

            try {
                $freshApp = Application::configure(basePath: base_path())
                    ->withRouting(
                        api: $apiPath,
                        web: base_path('routes/web.php'),
                        commands: base_path('routes/console.php'),
                        health: '/up',
                    )
                    ->create();

                $freshApp->instance('files', new Filesystem());
                $freshApp->instance(ExceptionHandlerContract::class, app(ExceptionHandlerContract::class));

                $freshApp->make(ConsoleKernelContract::class)->bootstrap();

                $route = collect($freshApp['router']->getRoutes()->getRoutes())
                    ->first(fn ($candidate) => in_array('POST', $candidate->methods(), true)
                        && $candidate->uri() === 'api/finance/invoices/{invoiceId}/payments');

                $totalRoutes = count($freshApp['router']->getRoutes()->getRoutes());

                $rows[] = [
                    'scenario' => $scenario['label'],
                    'registered' => $route ? 'yes' : 'no',
                    'total_routes' => (string) $totalRoutes,
                    'action' => $route ? ltrim($route->getActionName(), '\\') : '-',
                    'middleware' => $route ? implode(', ', $route->gatherMiddleware()) : '-',
                ];
            } catch (Throwable $exception) {
                $rows[] = [
                    'scenario' => $scenario['label'],
                    'registered' => 'error',
                    'total_routes' => '-',
                    'action' => class_basename($exception) . ': ' . $exception->getMessage(),
                    'middleware' => '-',
                ];
            }
        }

        $this->newLine();
        $this->info('Fresh App Routing Probes');
        $this->table(['scenario', 'registered', 'total_routes', 'action', 'middleware'], $rows);
    } finally {
        foreach (glob($tempDir . DIRECTORY_SEPARATOR . 'fresh_api_probe_*.php') ?: [] as $file) {
            @unlink($file);
        }
    }

    return 0;
})->purpose('Probe invoice payment registration through a freshly booted Laravel app with temporary api route files');

Artisan::command('jobs:promise-reminders {--dry-run : List affected jobs without sending WhatsApp messages} {--window=1 : Number of days ahead to include (1 = today+tomorrow)}', function () {
    /** @var \Illuminate\Console\Command $this */
    $service = new WhatsAppService();
    $isDryRun = (bool) $this->option('dry-run');
    $window = max(0, (int) $this->option('window'));

    $today = \Illuminate\Support\Carbon::today();
    $until = $today->copy()->addDays($window)->endOfDay();

    $dueJobs = \App\Models\ServiceJob::query()
        ->whereNotNull('promised_completion_at')
        ->whereDate('promised_completion_at', '>=', $today->toDateString())
        ->where('promised_completion_at', '<=', $until)
        ->whereIn('status', ['PENDING', 'REPAIR'])
        ->whereNotNull('customer_phone')
        ->orderBy('promised_completion_at')
        ->get();

    $overdueJobs = \App\Models\ServiceJob::query()
        ->whereNotNull('promised_completion_at')
        ->where('promised_completion_at', '<', $today)
        ->whereIn('status', ['PENDING', 'REPAIR'])
        ->whereNotNull('customer_phone')
        ->orderBy('promised_completion_at')
        ->get();

    $allJobs = $dueJobs->concat($overdueJobs);

    if ($allJobs->isEmpty()) {
        $this->info('No promise-date jobs found in the current window.');
        return 0;
    }

    $this->table(
        ['job_code', 'customer', 'model', 'status', 'promise_date', 'overdue'],
        $allJobs->map(function (\App\Models\ServiceJob $job) use ($today): array {
            $promiseDate = $job->promised_completion_at?->format('Y-m-d') ?? '-';
            $isOverdue = $job->promised_completion_at && $job->promised_completion_at->lt($today);
            return [
                (string) $job->job_code,
                (string) ($job->customer_name ?? ''),
                (string) ($job->tv_model ?? ''),
                (string) ($job->status ?? ''),
                $promiseDate,
                $isOverdue ? 'YES' : 'no',
            ];
        })->all(),
    );

    if ($isDryRun) {
        $this->warn("Dry run: {$allJobs->count()} job(s) would receive a WhatsApp reminder. Run without --dry-run to send.");
        return 0;
    }

    if (! $service->isConfigured()) {
        $this->error('WhatsApp is not configured. Use --dry-run to preview without sending.');
        return 1;
    }

    $sent = 0;
    $failed = 0;

    foreach ($allJobs as $job) {
        if ($service->sendPromiseReminderMessage($job)) {
            $sent++;
            $this->line("  ✓ Sent reminder to {$job->customer_name} ({$job->job_code})");
        } else {
            $failed++;
            $this->warn("  ✗ Failed to send reminder for {$job->job_code}");
        }
    }

    $this->newLine();
    $this->info("Done. Sent: {$sent}  Failed: {$failed}");

    return $failed > 0 ? 1 : 0;
})->purpose('Send WhatsApp promise-date reminders for jobs due today, tomorrow, or overdue (use --dry-run to preview)');
