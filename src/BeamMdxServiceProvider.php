<?php

namespace Schemastud\BeamMdx;

use Illuminate\Routing\Router;
use Schemastud\BeamMdx\Console\BeamMdxDoctorCommand;
use Schemastud\BeamMdx\Http\Middleware\EnsurePreviewAllowed;
use Schemastud\BeamMdx\Routing\ContentRoutes;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class BeamMdxServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('laravel-beam-mdx')
            ->hasConfigFile('beam-mdx')
            ->hasCommand(BeamMdxDoctorCommand::class);
    }

    public function packageBooted(): void
    {
        // The catch-all content/essay route macros (Route::beamMdxShow / beamMdxPage).
        ContentRoutes::registerMacros();

        // The `beam-mdx.preview` alias — gate a whole surface to preview-allowlisted envs.
        $this->app->make(Router::class)->aliasMiddleware('beam-mdx.preview', EnsurePreviewAllowed::class);
    }
}
