<?php

declare(strict_types=1);

namespace Praetorius\ViteAssetCollector\Service;

use Praetorius\ViteAssetCollector\Exception\ViteException;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UriInterface;
use TYPO3\CMS\Core\Cache\Frontend\FrontendInterface;
use TYPO3\CMS\Core\Page\AssetCollector;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\PathUtility;

class ViteService
{
    public const DEFAULT_PORT = 5173;

    public function __construct(
        private readonly FrontendInterface $cache,
        protected AssetCollector $assetCollector
    ) {
    }

    public function determineDevServer(ServerRequestInterface $request): UriInterface
    {
        $vitePort = getenv('VITE_PRIMARY_PORT') ?: self::DEFAULT_PORT;
        return $request->getUri()->withPath('')->withPort((int)$vitePort);
    }

    public function addAssetsFromDevServer(
        UriInterface $devServerUri,
        string $entry,
        array $assetOptions = [],
        array $scriptTagAttributes = []
    ): void {
        $scriptTagAttributes = $this->prepareScriptAttributes($scriptTagAttributes);
        $this->assetCollector->addJavaScript(
            'vite',
            (string)$devServerUri->withPath('@vite/client'),
            ['type' => 'module', ...$scriptTagAttributes],
            $assetOptions
        );
        $this->assetCollector->addJavaScript(
            "vite:{$entry}",
            (string)$devServerUri->withPath($entry),
            ['type' => 'module', ...$scriptTagAttributes],
            $assetOptions
        );
    }

    public function determineEntrypointFromManifest(string $manifestFile): string
    {
        $manifestFile = $this->resolveManifestFile($manifestFile);
        $manifest = $this->parseManifestFile($manifestFile);

        $entrypoints = [];
        foreach ($manifest as $entrypoint => $assetData) {
            if (!empty($assetData['isEntry'])) {
                $entrypoints[] = $entrypoint;
            }
        }

        if (count($entrypoints) !== 1) {
            throw new ViteException(sprintf(
                'Appropriate vite entrypoint could not be determined automatically. Expected 1 entrypoint in "%s", found %d.',
                $manifestFile,
                count($entrypoints)
            ), 1683552723);
        }

        return $entrypoints[0];
    }

    public function addAssetsFromManifest(
        string $manifestFile,
        string $entry,
        bool $addCss = true,
        array $assetOptions = [],
        array $scriptTagAttributes = [],
        array $cssTagAttributes = [],
        bool $inlineCss = false
    ): void {
        $manifestFile = $this->resolveManifestFile($manifestFile);
        $manifestDir = dirname($manifestFile) . '/';
        $manifest = $this->parseManifestFile($manifestFile);

        if (!isset($manifest[$entry]) || empty($manifest[$entry]['isEntry'])) {
            throw new ViteException(sprintf(
                'Invalid vite entry point "%s" in manifest file "%s".',
                $entry,
                $manifestFile
            ), 1683200524);
        }

        $scriptTagAttributes = $this->prepareScriptAttributes($scriptTagAttributes);
        $this->assetCollector->addJavaScript(
            "vite:{$entry}",
            $manifestDir . $manifest[$entry]['file'],
            ['type' => 'module', ...$scriptTagAttributes],
            $assetOptions
        );

        if ($addCss && !empty($manifest[$entry]['css'])) {
            $cssTagAttributes = $this->prepareCssAttributes($cssTagAttributes);
            foreach ($manifest[$entry]['css'] as $file) {
                $identifier = "vite:{$entry}:{$file}";

                if ($inlineCss) {
                    $styleSheetContent = file_get_contents($manifestDir . $file);

                    if ($styleSheetContent === false) {
                        throw new ViteException(sprintf(
                            'Unable to open stylesheet file "%s".',
                            $manifestDir . $file
                        ), 1684256597);
                    }

                    $this->assetCollector->addInlineStyleSheet(
                        $identifier,
                        $styleSheetContent,
                        $cssTagAttributes,
                        array_merge($assetOptions, ['priority' => true])
                    );
                } else {
                    $this->assetCollector->addStyleSheet(
                        $identifier,
                        $manifestDir . $file,
                        $cssTagAttributes,
                        $assetOptions
                    );
                }
            }
        }
    }

    public function getAssetPathFromManifest(
        string $manifestFile,
        string $assetFile
    ): string {
        $manifestFile = $this->resolveManifestFile($manifestFile);
        $manifest = $this->parseManifestFile($manifestFile);
        if (!isset($manifest[$assetFile])) {
            throw new ViteException(sprintf(
                'Invalid asset file "%s" in vite manifest file "%s".',
                $assetFile,
                $manifestFile
            ), 1690735353);
        }
        return PathUtility::getAbsoluteWebPath(dirname($manifestFile) . '/' . $manifest[$assetFile]['file']);
    }

    protected function resolveManifestFile(string $manifestFile): string
    {
        $resolvedManifestFile = GeneralUtility::getFileAbsFileName($manifestFile);
        if ($resolvedManifestFile === '' || !file_exists($resolvedManifestFile)) {
            throw new ViteException(sprintf(
                'Vite manifest file "%s" was resolved to "%s" and cannot be opened.',
                $manifestFile,
                $resolvedManifestFile
            ), 1683200522);
        }
        return $resolvedManifestFile;
    }

    protected function parseManifestFile(string $manifestFile): array
    {
        $cacheIdentifier = md5($manifestFile);
        $manifest = $this->cache->get($cacheIdentifier);
        if ($manifest === false) {
            $manifestContent = file_get_contents($manifestFile);
            if ($manifestContent === false) {
                throw new ViteException(sprintf(
                    'Unable to open manifest file "%s".',
                    $manifestFile
                ), 1684256597);
            }

            $manifest = json_decode($manifestContent, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new ViteException(sprintf(
                    'Invalid vite manifest file "%s": %s.',
                    $manifestFile,
                    json_last_error_msg()
                ), 1683200523);
            }
            $this->cache->set($cacheIdentifier, $manifest);
        }
        return $manifest;
    }

    protected function prepareScriptAttributes(array $attributes): array
    {
        foreach (['async', 'defer', 'nomodule'] as $attr) {
            if ($attributes[$attr] ?? false) {
                $attributes[$attr] = $attr;
            }
        }
        return $attributes;
    }

    protected function prepareCssAttributes(array $attributes): array
    {
        if ($attributes['disabled'] ?? false) {
            $attributes['disabled'] = 'disabled';
        }
        return $attributes;
    }
}
