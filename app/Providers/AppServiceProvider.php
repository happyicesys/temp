<?php

namespace App\Providers;

use App\Services\Sensor\Jaalee\JaaleeApiClient;
use App\Services\Sensor\SensorVendorClientResolver;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(SensorVendorClientResolver::class, function ($app): SensorVendorClientResolver {
            $resolver = new SensorVendorClientResolver;

            $http = $app->make(HttpFactory::class);
            $config = $app['config']->get('services');

            // Jaalee is the default (and currently only) vendor. To add a new
            // manufacturer: subclass AbstractSensorVendorClient and register
            // an instance here.
            $resolver->register(new JaaleeApiClient(
                http: $http,
                baseUrl: (string) ($config['jaalee']['base_url'] ?? ''),
                token: (string) ($config['jaalee']['token'] ?? ''),
                timeoutSeconds: (int) ($config['jaalee']['timeout'] ?? 10),
            ));

            return $resolver;
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
