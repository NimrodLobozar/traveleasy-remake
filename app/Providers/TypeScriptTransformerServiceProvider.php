<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\Facades\File;
use Spatie\LaravelTypeScriptTransformer\LaravelData\LaravelDataTypeScriptTransformerExtension;
use Spatie\LaravelTypeScriptTransformer\TypeScriptTransformerApplicationServiceProvider;
use Spatie\TypeScriptTransformer\Transformers\AttributedClassTransformer;
use Spatie\TypeScriptTransformer\Transformers\EnumTransformer;
use Spatie\TypeScriptTransformer\TypeScriptTransformerConfigFactory;
use Spatie\TypeScriptTransformer\Writers\FlatModuleWriter;

/**
 * Generates TypeScript types from PHP: laravel-data objects, enums and classes marked #[TypeScript].
 *
 * Run `php artisan typescript:transform` (or `npm run generate`, which also runs Wayfinder).
 * Output: resources/js/types/generated/index.ts, imported as `@/types/generated`.
 * The output is git-ignored and regenerated; never edit it by hand.
 */
class TypeScriptTransformerServiceProvider extends TypeScriptTransformerApplicationServiceProvider
{
    protected function configure(TypeScriptTransformerConfigFactory $config): void
    {
        // Only runs when the transformer config is resolved (the artisan command), not on web requests.
        $outputDirectory = resource_path('js/types/generated');
        File::ensureDirectoryExists($outputDirectory);

        $config
            ->extension(new LaravelDataTypeScriptTransformerExtension)
            ->transformer(AttributedClassTransformer::class)
            ->transformer(EnumTransformer::class)
            ->transformDirectories(app_path())
            ->outputDirectory($outputDirectory)
            ->writer(new FlatModuleWriter('index.ts'))
            ->withoutManifest();
    }
}
