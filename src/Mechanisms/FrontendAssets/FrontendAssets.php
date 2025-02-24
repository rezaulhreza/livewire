<?php

namespace Livewire\Mechanisms\FrontendAssets;

use Livewire\Drawer\Utils;
use Illuminate\Support\Js;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Blade;

class FrontendAssets
{
    public $hasRenderedScripts = false;
    public $hasRenderedStyles = false;

    public $javaScriptRoute;

    public $scriptTagAttributes = [];

    public function register()
    {
        app()->singleton($this::class);
    }

    public function boot()
    {
        app($this::class)->setScriptRoute(function ($handle) {
            return Route::get('/livewire/livewire.js', $handle);
        });

        Blade::directive('livewireScripts', [static::class, 'livewireScripts']);
        Blade::directive('livewireScriptConfig', [static::class, 'livewireScriptConfig']);
        Blade::directive('livewireStyles', [static::class, 'livewireStyles']);
    }

    function useScriptTagAttributes($attributes)
    {
        $this->scriptTagAttributes = array_merge($this->scriptTagAttributes, $attributes);
    }

    function setScriptRoute($callback)
    {
        $route = $callback(function () {
            return $this->returnJavaScriptAsFile();
        });

        $this->javaScriptRoute = $route;
    }

    public static function livewireScripts($expression)
    {
        return '{!! \Livewire\Mechanisms\FrontendAssets\FrontendAssets::scripts('.$expression.') !!}';
    }

    public static function livewireScriptConfig($expression)
    {
        return '{!! \Livewire\Mechanisms\FrontendAssets\FrontendAssets::scriptConfig('.$expression.') !!}';
    }

    public static function livewireStyles($expression)
    {
        return '{!! \Livewire\Mechanisms\FrontendAssets\FrontendAssets::styles('.$expression.') !!}';
    }

    public function returnJavaScriptAsFile()
    {
        return Utils::pretendResponseIsFile(__DIR__.'/../../../dist/livewire.js');
    }

    public function maps()
    {
        return Utils::pretendResponseIsFile(__DIR__.'/../../../dist/livewire.js.map');
    }

    public static function styles($options = [])
    {
        app(static::class)->hasRenderedStyles = true;

        $nonce = isset($options['nonce']) ? "nonce=\"{$options['nonce']}\"" : '';

        $html = <<<HTML
        <!-- Livewire Styles -->
        <style {$nonce}>
            [wire\:loading], [wire\:loading\.delay], [wire\:loading\.inline-block], [wire\:loading\.inline], [wire\:loading\.block], [wire\:loading\.flex], [wire\:loading\.table], [wire\:loading\.grid], [wire\:loading\.inline-flex] {
                display: none;
            }

            [wire\:loading\.delay\.shortest], [wire\:loading\.delay\.shorter], [wire\:loading\.delay\.short], [wire\:loading\.delay\.long], [wire\:loading\.delay\.longer], [wire\:loading\.delay\.longest] {
                display:none;
            }

            [wire\:offline] {
                display: none;
            }

            [wire\:dirty]:not(textarea):not(input):not(select) {
                display: none;
            }
        </style>
        HTML;

        return static::minify($html);
    }

    public static function scripts($options = [])
    {
        app(static::class)->hasRenderedScripts = true;

        $debug = config('app.debug');

        $scripts = static::js($options);

        // HTML Label.
        $html = $debug ? ['<!-- Livewire Scripts -->'] : [];

        $html[] = $scripts;

        return implode("\n", $html);
    }

    public static function js($options)
    {
        // Use the default endpoint...
        $url = app(static::class)->javaScriptRoute->uri;

        // Use the configured one...
        $url = config('livewire.asset_url') ?: $url;

        // Use the legacy passed in one...
        $url = $options['asset_url'] ?? $url;

        // Use the new passed in one...
        $url = $options['url'] ?? $url;

        $url = rtrim($url, '/');

        $url = (string) str($url)->start('/');

        // Add the build manifest hash to it...
        $manifest = json_decode(file_get_contents(__DIR__.'/../../../dist/manifest.json'), true);
        $versionHash = $manifest['/livewire.js'];
        $url = "{$url}?id={$versionHash}";

        $token = app()->has('session.store') ? csrf_token() : '';

        $nonce = isset($options['nonce']) ? "nonce=\"{$options['nonce']}\"" : '';

        $updateUri = app('livewire')->getUpdateUri();

        $extraAttributes = Utils::stringifyHtmlAttributes(
            app(static::class)->scriptTagAttributes,
        );

        return <<<HTML
        <script src="{$url}" {$nonce} data-csrf="{$token}" data-uri="{$updateUri}" {$extraAttributes}></script>
        HTML;
    }

    public static function scriptConfig($options = [])
    {
        app(static::class)->hasRenderedScripts = true;

        $nonce = isset($options['nonce']) ? " nonce=\"{$options['nonce']}\"" : '';

        $attributes = json_encode([
            'csrf' => app()->has('session.store') ? csrf_token() : '',
            'uri' => app('livewire')->getUpdateUri(),
        ]);

        return <<<HTML
        <script{$nonce} data-navigate-once="true">window.livewireScriptConfig = {$attributes};</script>
        HTML;
    }

    protected static function minify($subject)
    {
        return preg_replace('~(\v|\t|\s{2,})~m', '', $subject);
    }
}
