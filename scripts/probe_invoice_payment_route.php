<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel as ConsoleKernelContract;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

require __DIR__.'/../vendor/autoload.php';

function buildFreshApp(string $basePath, string $apiPath, string $webPath): Application
{
    $app = Application::configure(basePath: $basePath)
        ->withRouting(
            api: $apiPath,
            web: $webPath,
            commands: $basePath.'/routes/console.php',
            health: '/up',
        )
        ->withMiddleware(function (Middleware $middleware): void {
            // Match bootstrap/app.php behavior.
        })
        ->withExceptions(function (Exceptions $exceptions): void {
            // Match bootstrap/app.php behavior.
        })
        ->create();

    $app->instance('files', new Filesystem);

    return $app;
}

function routeDetails(Application $app): array
{
    $routes = collect($app['router']->getRoutes()->getRoutes());
    $route = $routes->first(
        fn ($candidate) => in_array('POST', $candidate->methods(), true)
            && $candidate->uri() === 'api/finance/invoices/{invoiceId}/payments'
    );

    return [
        'registered' => $route ? 'yes' : 'no',
        'total_routes' => (string) count($routes),
        'action' => $route ? ltrim($route->getActionName(), '\\') : '-',
        'middleware' => $route ? implode(', ', $route->gatherMiddleware()) : '-',
    ];
}

function injectInvoicePaymentRoute(string $apiSource): string
{
    $needle = "        Route::post('/invoices', [FinanceController::class, 'storeInvoice']);";
    $insertion = "        Route::post('/invoices', [FinanceController::class, 'storeInvoice']);\n        Route::post('/invoices/{invoiceId}/payments', [FinanceController::class, 'recordInvoicePayment']);";

    if (str_contains($apiSource, "Route::post('/invoices/{invoiceId}/payments', [FinanceController::class, 'recordInvoicePayment']);")) {
        return $apiSource;
    }

    return str_replace($needle, $insertion, $apiSource);
}

function extractRouteBlock(string $source, string $blockStart): string
{
    $start = strpos($source, $blockStart);

    if ($start === false) {
        throw new RuntimeException("Unable to find route block starting with: {$blockStart}");
    }

    $openBrace = strpos($source, '{', $start);

    if ($openBrace === false) {
        throw new RuntimeException("Unable to locate opening brace for route block: {$blockStart}");
    }

    $depth = 0;
    $length = strlen($source);

    for ($index = $openBrace; $index < $length; $index++) {
        $character = $source[$index];

        if ($character === '{') {
            $depth++;

            continue;
        }

        if ($character !== '}') {
            continue;
        }

        $depth--;

        if ($depth !== 0) {
            continue;
        }

        $semicolon = strpos($source, ';', $index);

        if ($semicolon === false) {
            throw new RuntimeException("Unable to locate closing semicolon for route block: {$blockStart}");
        }

        return trim(substr($source, $start, $semicolon - $start + 1));
    }

    throw new RuntimeException("Unable to extract route block: {$blockStart}");
}

function buildAuthSubsetApi(string $header, array $blocks): string
{
    return rtrim($header)
        .PHP_EOL.PHP_EOL
        ."Route::middleware('auth:sanctum')->group(function () {".PHP_EOL
        .implode(PHP_EOL.PHP_EOL, $blocks).PHP_EOL
        .'});'.PHP_EOL;
}

function stripFallbackWebRoute(string $webSource): string
{
    $withoutComment = preg_replace(
        '/^\s*\/\/ Keep this API-shaped route here.*$/m',
        '',
        $webSource,
    );

    $withoutRoute = preg_replace(
        '/^\s*Route::middleware\(\[\'api\', \'auth:sanctum\'\]\)->post\(\'\/api\/finance\/invoices\/\{invoiceId\}\/payments\', \[FinanceController::class, \'recordInvoicePayment\'\]\);\s*$/m',
        '',
        (string) $withoutComment,
    );

    return preg_replace("/\n{3,}/", PHP_EOL.PHP_EOL, rtrim((string) $withoutRoute)).PHP_EOL;
}

function printTable(array $headers, array $rows): void
{
    $widths = [];

    foreach ($headers as $index => $header) {
        $widths[$index] = strlen($header);
    }

    foreach ($rows as $row) {
        foreach ($row as $index => $cell) {
            $widths[$index] = max($widths[$index] ?? 0, strlen($cell));
        }
    }

    $separator = '+';
    foreach ($widths as $width) {
        $separator .= str_repeat('-', $width + 2).'+';
    }

    echo $separator.PHP_EOL;
    echo '|';
    foreach ($headers as $index => $header) {
        echo ' '.str_pad($header, $widths[$index]).' |';
    }
    echo PHP_EOL;
    echo $separator.PHP_EOL;

    foreach ($rows as $row) {
        echo '|';
        foreach ($row as $index => $cell) {
            echo ' '.str_pad($cell, $widths[$index]).' |';
        }
        echo PHP_EOL;
    }

    echo $separator.PHP_EOL;
}

$basePath = realpath(__DIR__.'/..');

if ($basePath === false) {
    fwrite(STDERR, 'Unable to resolve backend base path.'.PHP_EOL);
    exit(1);
}

$tempDir = $basePath.'/storage/framework/route-probes';

if (! is_dir($tempDir) && ! mkdir($tempDir, 0777, true) && ! is_dir($tempDir)) {
    fwrite(STDERR, "Unable to create temporary probe directory: {$tempDir}".PHP_EOL);
    exit(1);
}

$apiSource = (string) file_get_contents($basePath.'/routes/api.php');
$webSource = (string) file_get_contents($basePath.'/routes/web.php');
$emptyWebSource = "<?php\n";
$webSourceWithoutFallback = stripFallbackWebRoute($webSource);

$mainAuthAnchor = "    Route::get('/app-config', [AppConfigController::class, 'index']);";
$mainAuthAnchorOffset = strpos($apiSource, $mainAuthAnchor);

if ($mainAuthAnchorOffset === false) {
    fwrite(STDERR, 'Unable to find the main authenticated finance/dashboard section in routes/api.php.'.PHP_EOL);
    exit(1);
}

$authGroupStart = "Route::middleware('auth:sanctum')->group(function () {";
$authGroupOffset = strrpos(substr($apiSource, 0, $mainAuthAnchorOffset), $authGroupStart);

if ($authGroupOffset === false) {
    fwrite(STDERR, 'Unable to locate the main auth:sanctum route group wrapper in routes/api.php.'.PHP_EOL);
    exit(1);
}

$apiHeader = substr($apiSource, 0, $authGroupOffset);
$jobsBlock = extractRouteBlock($apiSource, "Route::prefix('jobs')->group(function () {");
$customersBlock = extractRouteBlock($apiSource, "Route::prefix('customers')->group(function () {");
$financeBlock = injectInvoicePaymentRoute(extractRouteBlock($apiSource, "Route::prefix('finance')->group(function () {"));
$inventoryBlock = extractRouteBlock($apiSource, "Route::prefix('inventory')->group(function () {");
$adminBlock = extractRouteBlock($apiSource, "Route::prefix('admin')->group(function () {");
$apiWithInjectedRoute = injectInvoicePaymentRoute($apiSource);

$webViewsOnly = <<<'PHP'
<?php

\Illuminate\Support\Facades\Route::view('/', 'welcome');
\Illuminate\Support\Facades\Route::view('/ops-preview', 'ops.preview');
PHP;

$webViewsAndLogin = <<<'PHP'
<?php

\Illuminate\Support\Facades\Route::view('/', 'welcome');
\Illuminate\Support\Facades\Route::view('/ops-preview', 'ops.preview');

\Illuminate\Support\Facades\Route::get('/login', function () {
    return redirect('/admin/login');
})->name('login');
PHP;

$webPrintOnly = <<<'PHP'
<?php

\Illuminate\Support\Facades\Route::middleware('auth')->get('/admin/invoices/{serviceJob}/print', function (\App\Models\ServiceJob $serviceJob) {
    $serviceJob->load(['customer', 'payments']);

    return view('invoices.print', [
        'job' => $serviceJob,
    ]);
})->name('invoices.print');
PHP;

$scenarios = [
    [
        'label' => 'actual api.php plus actual web.php',
        'api_contents' => $apiSource,
        'web_contents' => $webSource,
    ],
    [
        'label' => 'actual api.php plus actual web.php without fallback',
        'api_contents' => $apiSource,
        'web_contents' => $webSourceWithoutFallback,
    ],
    [
        'label' => 'actual api.php plus empty web.php',
        'api_contents' => $apiSource,
        'web_contents' => $emptyWebSource,
    ],
    [
        'label' => 'actual api.php with route plus empty web.php',
        'api_contents' => $apiWithInjectedRoute,
        'web_contents' => $emptyWebSource,
    ],
    [
        'label' => 'injected api plus views-only web',
        'api_contents' => $apiWithInjectedRoute,
        'web_contents' => $webViewsOnly,
    ],
    [
        'label' => 'injected api plus views-and-login web',
        'api_contents' => $apiWithInjectedRoute,
        'web_contents' => $webViewsAndLogin,
    ],
    [
        'label' => 'injected api plus print-only web',
        'api_contents' => $apiWithInjectedRoute,
        'web_contents' => $webPrintOnly,
    ],
    [
        'label' => 'injected api plus actual web without fallback',
        'api_contents' => $apiWithInjectedRoute,
        'web_contents' => $webSourceWithoutFallback,
    ],
    [
        'label' => 'real header plus finance block only',
        'api_contents' => buildAuthSubsetApi($apiHeader, [$financeBlock]),
        'web_contents' => $emptyWebSource,
    ],
    [
        'label' => 'real header plus jobs and finance blocks',
        'api_contents' => buildAuthSubsetApi($apiHeader, [$jobsBlock, $financeBlock]),
        'web_contents' => $emptyWebSource,
    ],
    [
        'label' => 'real header plus jobs customers finance',
        'api_contents' => buildAuthSubsetApi($apiHeader, [$jobsBlock, $customersBlock, $financeBlock]),
        'web_contents' => $emptyWebSource,
    ],
    [
        'label' => 'real header plus jobs customers finance inventory',
        'api_contents' => buildAuthSubsetApi($apiHeader, [$jobsBlock, $customersBlock, $financeBlock, $inventoryBlock]),
        'web_contents' => $emptyWebSource,
    ],
    [
        'label' => 'real header plus all auth blocks',
        'api_contents' => buildAuthSubsetApi($apiHeader, [$jobsBlock, $customersBlock, $financeBlock, $inventoryBlock, $adminBlock]),
        'web_contents' => $emptyWebSource,
    ],
    [
        'label' => 'minimal finance-only api.php plus empty web.php',
        'api_contents' => <<<'PHP'
<?php

Route::middleware('auth:sanctum')->group(function () {
    Route::prefix('finance')->group(function () {
        Route::get('/invoices', [\App\Http\Controllers\Api\FinanceController::class, 'invoices']);
        Route::post('/invoices', [\App\Http\Controllers\Api\FinanceController::class, 'storeInvoice']);
        Route::post('/invoices/{invoiceId}/payments', [\App\Http\Controllers\Api\FinanceController::class, 'recordInvoicePayment']);
        Route::get('/payments', [\App\Http\Controllers\Api\FinanceController::class, 'payments']);
    });
});
PHP,
        'web_contents' => $emptyWebSource,
    ],
];

$rows = [];

try {
    foreach ($scenarios as $index => $scenario) {
        $apiPath = sprintf('%s/fresh_probe_%02d.php', $tempDir, $index + 1);
        $webPath = sprintf('%s/fresh_probe_web_%02d.php', $tempDir, $index + 1);
        file_put_contents($apiPath, $scenario['api_contents']);
        file_put_contents($webPath, $scenario['web_contents']);

        try {
            $app = buildFreshApp($basePath, $apiPath, $webPath);
            $app->make(ConsoleKernelContract::class)->bootstrap();
            $details = routeDetails($app);

            $rows[] = [
                $scenario['label'],
                $details['registered'],
                $details['total_routes'],
                $details['action'],
                $details['middleware'],
            ];
        } catch (Throwable $exception) {
            $rows[] = [
                $scenario['label'],
                'error',
                '-',
                get_class($exception).': '.$exception->getMessage(),
                '-',
            ];
        }
    }
} finally {
    foreach (glob($tempDir.'/fresh_probe_*.php') ?: [] as $file) {
        @unlink($file);
    }
    foreach (glob($tempDir.'/fresh_probe_web_*.php') ?: [] as $file) {
        @unlink($file);
    }
}

echo 'Standalone Invoice Payment Route Probe'.PHP_EOL;
printTable(['scenario', 'registered', 'total_routes', 'action', 'middleware'], $rows);

$hasError = false;
foreach ($rows as $row) {
    if ($row[1] === 'error') {
        $hasError = true;
        break;
    }
}

if ($hasError) {
    echo 'One or more fresh-app scenarios failed during bootstrap. This indicates the probe environment still differs from normal app startup.'.PHP_EOL;
    exit(1);
}

echo 'Current interpretation: sibling api.php blocks do not suppress the injected finance payment route.'.PHP_EOL;
echo 'If the route disappears only when a real web.php variant is loaded, continue isolating the web-route interaction rather than the finance route definition itself.'.PHP_EOL;
exit(0);
