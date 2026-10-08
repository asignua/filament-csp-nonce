<?php

declare(strict_types=1);

namespace Asignua\FilamentCspNonce;

use Asignua\FilamentCspNonce\Console\PruneViolationsCommand;
use Asignua\FilamentCspNonce\Http\Controllers\ReportController;
use Asignua\FilamentCspNonce\Http\Middleware\CspNonce;
use Composer\InstalledVersions;
use Cron\CronExpression;
use Illuminate\Console\Scheduling\ManagesFrequencies;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Exceptions\Handler;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use ReflectionClass;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

final class FilamentCspNonceServiceProvider extends PackageServiceProvider
{
    public static string $name = 'csp-nonce';

    public function configurePackage(Package $package): void
    {
        $package
            ->name(self::$name)
            ->hasConfigFile()
            ->hasMigration('create_csp_violations_table')
            ->hasCommand(PruneViolationsCommand::class);
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(PolicyRegistry::class);
    }

    public function packageBooted(): void
    {
        $this->app->make(Router::class)->aliasMiddleware('csp.nonce', CspNonce::class);

        Blade::directive('cspNonce', fn (): string => '<?php echo \\'.Nonce::class.'::attribute(); ?>');

        $this->registerReportRoute();
        $this->registerBladeRewriter();
        $this->registerPruneSchedule();
    }

    private function registerPruneSchedule(): void
    {
        $this->callAfterResolving(Schedule::class, static function (Schedule $schedule): void {
            $frequency = config('csp-nonce.report.prune_schedule', 'daily');

            if (!is_string($frequency) || $frequency === '' || config('csp-nonce.report.storage', 'log') !== 'database') {
                return;
            }

            if (CronExpression::isValidExpression($frequency)) {
                $schedule->command(PruneViolationsCommand::class)->cron($frequency);

                return;
            }

            if (!self::isFrequencyMethod($frequency)) {
                // A bad value must not break schedule:run for every task of the app.
                Log::warning('csp-nonce: report.prune_schedule is neither a parameterless frequency method nor a cron expression; csp:prune is not scheduled.', ['value' => $frequency]);

                return;
            }

            $schedule->command(PruneViolationsCommand::class)->{$frequency}();
        });
    }

    /**
     * Only a public parameterless method of the scheduler's frequency trait
     * ('daily', 'hourly', 'weekly'...) is accepted, never an arbitrary method name.
     */
    private static function isFrequencyMethod(string $name): bool
    {
        $trait = new ReflectionClass(ManagesFrequencies::class);

        if (!preg_match('/^[a-zA-Z]+$/', $name) || !$trait->hasMethod($name)) {
            return false;
        }

        $method = $trait->getMethod($name);

        return $method->isPublic() && $method->getNumberOfRequiredParameters() === 0;
    }

    private function registerReportRoute(): void
    {
        if (!config('csp-nonce.enabled', true) || !config('csp-nonce.report.enabled', true)) {
            return;
        }

        Route::post((string) config('csp-nonce.report.path', 'csp/report'), ReportController::class)
            ->middleware('throttle:'.config('csp-nonce.report.throttle', '300,1'))
            ->name('csp.report');
    }

    private function registerBladeRewriter(): void
    {
        if (!config('csp-nonce.enabled', true) || !config('csp-nonce.blade.rewrite', true)) {
            return;
        }

        $rewriter = new BladeNonceRewriter($this->rewritePaths(...));

        Blade::prepareStringsForCompilationUsing(
            static fn (string $source): string => $rewriter->shouldRewrite(Blade::getPath())
                ? $rewriter->rewrite($source)
                : $source,
        );
    }

    /**
     * @return list<string>
     */
    private function rewritePaths(): array
    {
        /** @var list<string> $patterns */
        $patterns = array_values((array) config('csp-nonce.blade.packages', []));
        /** @var list<string> $paths */
        $paths = array_values((array) config('csp-nonce.blade.paths', []));

        // Error pages render inside the panel's middleware stack, so they get the enforcing header
        // too: the framework's own views (inline <style>) and the host's must carry the nonce.
        $paths[] = dirname((string) (new ReflectionClass(Handler::class))->getFileName()).'/views';

        if (is_dir($errors = resource_path('views/errors'))) {
            $paths[] = $errors;
        }

        foreach (InstalledVersions::getInstalledPackages() as $package) {
            foreach ($patterns as $pattern) {
                $installPath = fnmatch($pattern, $package) ? InstalledVersions::getInstallPath($package) : null;

                if ($installPath !== null) {
                    $paths[] = $installPath;
                }
            }
        }

        return array_values(array_unique($paths));
    }
}
