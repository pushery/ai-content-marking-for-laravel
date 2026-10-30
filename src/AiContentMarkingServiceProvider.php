<?php

declare(strict_types=1);

namespace Pushery\AiContentMarking;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Foundation\CachesConfiguration;
use Illuminate\Contracts\Translation\Loader;
use Illuminate\Contracts\Translation\Translator;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Support\ServiceProvider;
use Override;
use Pushery\AiContentMarking\Gaps\GapReporter;
use Pushery\AiContentMarking\MediaLibrary\KeepMarkingOnConversions;
use Pushery\AiContentMarking\Models\ModelRegister;
use Pushery\AiContentMarking\Support\LanguageFallbackLoader;
use Spatie\MediaLibrary\Conversions\Events\ConversionHasBeenCompletedEvent;

/**
 * Ships the configuration, the notice component and its translations, binds the reader, the model
 * register and the gap reporter, and keeps the marking of an original on every conversion the media
 * library derives from it.
 *
 * The package adds no table and no route: a marking travels with the text or the image it
 * describes, through the host's own views, resources and media.
 */
final class AiContentMarkingServiceProvider extends ServiceProvider
{
    #[Override]
    public function register(): void
    {
        $this->mergeConfigRecursivelyFrom(__DIR__.'/../config/ai-content-marking.php', 'ai-content-marking');

        // A regional locale reads this package's catalog of its language, `pt-BR` from `pt`, in this package's
        // namespace only. Registered here so it wraps the loader before the translator is built from it.
        $this->app->extend('translation.loader', static fn (Loader $loader): Loader => new LanguageFallbackLoader($loader, 'ai-content-marking'));

        $this->app->singleton(MarkingReader::class, static function (Container $app): MarkingReader {
            // The configured ceiling, read with the same fallback the shipped file has: a host whose
            // configuration is cached is never merged, and a value no ceiling can use keeps the default.
            $configured = $app->make(Repository::class)->get('ai-content-marking.reader.max_bytes');

            return new MarkingReader(is_int($configured) && $configured >= 1 ? $configured : MarkingReader::DEFAULT_MAX_BYTES);
        });

        $this->app->singleton(ModelRegister::class, static function (Container $app): ModelRegister {
            $models = $app->make(Repository::class)->get('ai-content-marking.models');

            return ModelRegister::fromConfig(is_array($models) ? $models : []);
        });

        $this->app->singleton(GapReporter::class, static function (Container $app): GapReporter {
            // Read with the fallback the shipped file has, for the same reason as the reader's ceiling.
            $configured = $app->make(Repository::class)->get('ai-content-marking.gaps.watermark_from_tokens');

            return new GapReporter(
                $app->make(ModelRegister::class),
                $app->make(Translator::class),
                is_int($configured) && $configured >= 1 ? $configured : GapReporter::DEFAULT_WATERMARK_FROM_TOKENS,
            );
        });
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'ai-content-marking');
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'ai-content-marking');

        // A template has no container to ask, so the notice component gets its Notice from a composer.
        $this->callAfterResolving('view', function (Factory $views): void {
            $views->composer('ai-content-marking::components.notice', function (View $view): void {
                $view->with('aiContentMarkingNotice', $this->app->make(Notice::class));
            });
        });

        $this->app->make(Dispatcher::class)->listen(ConversionHasBeenCompletedEvent::class, KeepMarkingOnConversions::class);

        if ($this->app->runningInConsole()) {
            $this->registerPublishing();
        }
    }

    /**
     * Lay the shipped defaults under a published config file, all the way down.
     *
     * Laravel's own `mergeConfigFrom()` is a single `array_merge` at the top level, so it asks
     * one question per top-level key: is it there? A host that published the config file has
     * every top-level key, so a key that a later release adds inside one of those blocks would
     * never arrive, and the block the host published would win whole.
     *
     * Nothing would error or log: the new setting would read as `null` (or as `[]`). A feature
     * added in a minor release would be off for exactly the hosts that customized that area, and
     * a corrected security default would not take effect, for as long as the host keeps the file
     * it published.
     *
     * Recursion stops at a list on either side, because a list is a value, not a structure.
     * `array_merge_recursive()` concatenates lists and `array_replace_recursive()` merges them by
     * index: a host narrowing a shipped `['web', 'api']` to `['admin']` gets back
     * `['web', 'api', 'admin']` from the first and `['admin', 'api']` from the second. Both hand
     * back an entry the host removed, which on an allowlist is a security regression. Here the
     * published list stands as written.
     *
     * `array_is_list([])` is true, which is the behavior wanted here: an empty array is a host
     * saying "none", and descending into it could only re-introduce what it emptied.
     *
     * A host with a cached config is never merged at all, by the framework's design. For those
     * installations the published file is the whole truth, so a package that reads a nested key
     * needs its read-site fallback to agree with the shipped default. The two are halves of one
     * guarantee.
     */
    private function mergeConfigRecursivelyFrom(string $path, string $key): void
    {
        if ($this->app instanceof CachesConfiguration && $this->app->configurationIsCached()) {
            return;
        }

        $shipped = require $path;

        $repository = $this->app->make(Repository::class);
        $existing = $repository->get($key);

        // Both sides come off disk or out of the container, so neither is provably
        // string-keyed here — a config array is just an array. The recursion below is
        // written for exactly that: it asks whether a value is a list, never whether a key
        // is a string.
        $repository->set($key, $this->mergeConfigSections(
            is_array($shipped) ? $shipped : [],
            is_array($existing) ? $existing : [],
        ));
    }

    /**
     * @param  array<array-key, mixed>  $shipped
     * @param  array<array-key, mixed>  $published
     * @return array<array-key, mixed>
     */
    private function mergeConfigSections(array $shipped, array $published): array
    {
        foreach ($shipped as $key => $value) {
            if (! array_key_exists($key, $published)) {
                $published[$key] = $value;

                continue;
            }

            // Recurse only where both sides are maps. If either is a list, or the published
            // value is a scalar or an explicit null, what the host wrote stands. An explicit
            // null is a value like any other here, which keeps "write => null to switch this
            // off" working exactly as a shipped config file describes it.
            if (is_array($value) && is_array($published[$key]) && ! array_is_list($value) && ! array_is_list($published[$key])) {
                $published[$key] = $this->mergeConfigSections($value, $published[$key]);
            }
        }

        return $published;
    }

    private function registerPublishing(): void
    {
        // Resolve publish targets through the Application contract's path methods
        // (available via illuminate/contracts), not the config_path()/database_path()/
        // resource_path()/lang_path() global helpers. Those are Foundation helpers,
        // shipped only with laravel/framework — which this lean package does not
        // require — so the helper form would freeze a wrong dependency contract and
        // fatal in a non-Foundation host. The method form is behavior-identical.
        //
        // Each group carries the bare 'ai-content-marking' umbrella tag on top of its
        // specific one, so `vendor:publish --tag="ai-content-marking"` publishes every
        // resource at once — the tag convention Laravel's official package skeleton
        // establishes.
        //
        // The umbrella covers what a host adapts by editing a file: config, views, lang. It has
        // two exceptions, and a package can grow into either without a warning, because
        // publishing more is not an error anywhere:
        //
        //   * Migration tags split per opt-in layer. A published migration runs. A package
        //     that separates its migration tags does so that a host which enabled one layer
        //     never creates the other layers' tables. One umbrella command would undo that,
        //     and the damage would show at the host's next `php artisan migrate`, not at
        //     publish time. This package ships no migrations, so the question does not arise.
        //   * Tags that write to the same destination and exclude each other, such as two
        //     variants of one view set of which the host picks one. Under a common tag the
        //     registration order decides which survives, and the result is undefined.
        $this->publishes([
            __DIR__.'/../config/ai-content-marking.php' => $this->app->configPath('ai-content-marking.php'),
        ], ['ai-content-marking', 'ai-content-marking-config']);

        $this->publishes([
            __DIR__.'/../resources/views' => $this->app->resourcePath('views/vendor/ai-content-marking'),
        ], ['ai-content-marking', 'ai-content-marking-views']);

        $this->publishes([
            __DIR__.'/../lang' => $this->app->langPath('vendor/ai-content-marking'),
        ], ['ai-content-marking', 'ai-content-marking-lang']);
    }
}
