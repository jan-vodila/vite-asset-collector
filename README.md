# Vite AssetCollector for TYPO3

[![Maintainability](https://api.codeclimate.com/v1/badges/161b455fe0abc70be677/maintainability)](https://codeclimate.com/github/s2b/vite-asset-collector/maintainability)
[![Test Coverage](https://api.codeclimate.com/v1/badges/161b455fe0abc70be677/test_coverage)](https://codeclimate.com/github/s2b/vite-asset-collector/test_coverage)
[![tests](https://github.com/s2b/vite-asset-collector/actions/workflows/tests.yaml/badge.svg)](https://github.com/s2b/vite-asset-collector/actions/workflows/tests.yaml)
[![Total downloads](https://typo3-badges.dev/badge/vite_assetcollector/downloads/shields.svg)](https://extensions.typo3.org/extension/vite_asset_collector)
[![TYPO3 versions](https://typo3-badges.dev/badge/vite_assetcollector/typo3/shields.svg)](https://extensions.typo3.org/extension/vite_asset_collector)
[![Latest version](https://typo3-badges.dev/badge/vite_assetcollector/version/shields.svg)](https://extensions.typo3.org/extension/vite_asset_collector)

This TYPO3 extension uses TYPO3's AssetCollector API to embed frontend assets
generated with [vite](https://vitejs.dev/). This means that you can use
vite's hot reloading and hot module replacement features (and many others)
in your TYPO3 project.

This extension is inspired by
[typo3-vite-demo](https://github.com/fgeierst/typo3-vite-demo) which was created
by [Florian Geierstanger](https://github.com/fgeierst/).

## Installation

The extension can be installed via composer:

```sh
composer req praetorius/vite-asset-collector
```

## Usage

### Vite Configuration

First, you need to make sure that vite:

* generates a `manifest.json` file and
* outputs assets to a publicly accessible directory

Example **vite.config.js**:

```js
import { defineConfig } from 'vite'

export default defineConfig({
    publicDir: false,
    build: {
        manifest: true,
        rollupOptions: {
            input: 'path/to/sitepackage/Resources/Private/JavaScript/Main.js',
            output: {
                entryFileNames: "[name].js",
                assetFileNames: "[name][extname]",
            },
        },
        outDir: 'path/to/sitepackage/Resources/Public/Vite/',
    },
})
```

Note that you should not use `resolve(__dirname, ...)` for `input` because the
value is both a path and an identifier.

### Fluid Usage

Then you can use the included ViewHelper to embed your assets. Note that the
`entry` value is both a path and an identifier, which is why we cannot
use `EXT:` here. This also means that this path needs to be consistent between
your development and your production environment.

Example **Layouts/Default.html**:

```xml
<html
    data-namespace-typo3-fluid="true"
    xmlns:vac="http://typo3.org/ns/Praetorius/ViteAssetCollector/ViewHelpers"
>

...

<vac:asset.vite
    manifest="EXT:sitepackage/Resources/Public/Vite/manifest.json"
    entry="path/to/sitepackage/Resources/Private/JavaScript/Main.js"
/>
```

### Setup development environment

Development environments can be highly individual. However, if ddev is your
tool of choice for local development, a few steps can get you started with
a ready-to-use development environment with vite, composer and TYPO3.

[Instructions for DDEV](./Documentation/DdevSetup.md)

## Configuration

The extension has two configuration options to setup the vite dev server.
By default, both are set to `auto`, which means:

* Dev server will only be used in `Development` context
* Dev server uri will be determined automatically for environments with
[vite-serve for DDEV](https://github.com/torenware/ddev-viteserve) set up

You can adjust both options in your `$TYPO3_CONF_VARS`, for example:

```php
// Setup vite dev server based on configuration in .env file
// TYPO3_VITE_DEV_SERVER='https://localhost:1234'
$GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['vite_asset_collector']['useDevServer'] = (bool) getenv('TYPO3_VITE_DEV_SERVER');
$GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['vite_asset_collector']['devServerUri'] = (string) getenv('TYPO3_VITE_DEV_SERVER');
```

You can also specify a default manifest file in the extension configuration.
If specified, the `manifest` parameter of the ViewHelper can be omitted.

```php
$GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['vite_asset_collector']['defaultManifest'] = 'EXT:sitepackage/Resources/Public/Vite/manifest.json';
```

## ViewHelper Arguments

### asset.vite ViewHelper

The `asset.vite` ViewHelper embeds all JavaScript and CSS belonging to the
specified vite `entry` using TYPO3's AssetCollector API.

* `manifest` (type: `string`): Path to your manifest.json file. If omitted,
default manifest from extension configuration will be used instead.
* `entry` (type: `string`): Identifier of the desired vite entry point;
this is the value specified as `input` in the vite configuration file. Can be
omitted if manifest file exists and only one entry point is present.
* `devTagAttributes` (type: `array`): HTML attributes that should be added to
script tags that point to the vite dev server
* `scriptTagAttributes` (type: `array`): HTML attributes that should be added
to script tags for built JavaScript assets
* `cssTagAttributes` (type: `array`): HTML attributes that should be added to
css link tags for built CSS assets
* `priority` (type: `bool`, default: `false`): Include assets before other assets
in HTML
* `useNonce` (type: `bool`, default: `false`): Whether to use the global nonce value
* `addCss` (type: `bool`, default: `true`): If set to `false`, CSS files associated
with the entry point won't be added to the asset collector
* `inlineCss` (type: `bool`, default: `false`): If set to `true`, CSS files associated with the entry point will be inlined

Example:

```xml
<vac:asset.vite
    manifest="EXT:sitepackage/Resources/Public/Vite/manifest.json"
    entry="path/to/sitepackage/Resources/Private/JavaScript/Main.js"
    scriptTagAttributes="{
        type: 'text/javascript',
        async: 1
    }"
    cssTagAttributes="{
        media: 'print'
    }"
    priority="1"
/>
```

### resource.vite ViteHelper

The `resource.vite` ViewHelper extracts the uri to one specific asset file from a vite
manifest file.

* `manifest` (type: `string`): Path to your manifest.json file. If omitted,
default manifest from extension configuration will be used instead.
* `file` (type: `string`): Identifier of the desired asset file for which a uri
should be generated

This can be used to preload certain assets in the HTML `<head>` tag:

```xml
<f:section name="HeaderAssets">
    <link
        rel="preload"
        href="{vac:resource.vite(file: 'path/to/sitepackage/Resources/Private/Fonts/webfont.woff2')}"
        as="font"
        type="font/woff2"
        crossorigin
    />
</f:section>
```
