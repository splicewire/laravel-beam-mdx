<?php

namespace Splicewire\Beam\Mdx;

use Illuminate\Routing\Router;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Splicewire\Beam\Mdx\Console\BeamMdxDoctorCommand;
use Splicewire\Beam\Mdx\Http\Middleware\EnsurePreviewAllowed;
use Splicewire\Beam\Mdx\Routing\ContentRoutes;

class BeamMdxServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('laravel-beam-mdx')
            ->hasConfigFile('beam/mdx')
            ->hasCommand(BeamMdxDoctorCommand::class);
    }

    public function packageRegistered(): void
    {
        // Back-compat aliases for the docs-regeneration machinery relocated down from
        // Splicewire\Tower\Docs\Regenerate\* (recohere Lane A cluster 4). Guarded so a stale
        // reference to the old FQCN still resolves during the transition.
        foreach ([
            'CaptureClient', 'CompositionRecordReducer', 'DocsJson', 'GuideDefinition',
            'GuideRegistry', 'NodeVitestPmSchemaCompiler', 'PmSchemaCompiler', 'Precondition',
            'Preconditions', 'RunsInSatelliteException',
        ] as $class) {
            $new = "Splicewire\\Beam\\Mdx\\Docs\\Regenerate\\{$class}";
            $old = "Splicewire\\Tower\\Docs\\Regenerate\\{$class}";
            if (! class_exists($old, false) && ! interface_exists($old, false)) {
                class_alias($new, $old);
            }
        }

        // Back-compat aliases for the anchor-strategy chunking machinery relocated down from
        // Splicewire\Tower\Support\Anchors\* (recohere Lane A cluster 5). Guarded so a stale
        // reference to the old FQCN still resolves during the transition.
        foreach ([
            'AnchorStrategy', 'AnchorStrategyFactory', 'PagedAnchorStrategy', 'PageMap',
            'SectionedAnchorStrategy', 'SectionRangeFilter', 'SectionSplitter',
        ] as $class) {
            $new = "Splicewire\\Beam\\Mdx\\Support\\Anchors\\{$class}";
            $old = "Splicewire\\Tower\\Support\\Anchors\\{$class}";
            if (! class_exists($old, false) && ! interface_exists($old, false)) {
                class_alias($new, $old);
            }
        }
    }

    public function packageBooted(): void
    {
        // The catch-all content/essay route macros (Route::beamMdxShow / beamMdxPage).
        ContentRoutes::registerMacros();

        // The `beam-mdx.preview` alias — gate a whole surface to preview-allowlisted envs.
        $this->app->make(Router::class)->aliasMiddleware('beam-mdx.preview', EnsurePreviewAllowed::class);
    }
}
