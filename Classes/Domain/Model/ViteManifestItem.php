<?php

declare(strict_types=1);

namespace Praetorius\ViteAssetCollector\Domain\Model;

final class ViteManifestItem
{
    public function __construct(
        public readonly string $identifier,
        public readonly ?string $src,
        public readonly string $file,
        public readonly bool $isEntry,
        public readonly bool $isDynamicEntry,
        public readonly array $assets,
        public readonly array $css,
        public readonly array $imports,
        public readonly array $dynamicImports,
    ) {}

    public static function fromArray(array $item, string $identifier): static
    {
        return new static(
            $identifier,
            $item['src'] ?? null,
            $item['file'],
            (bool)($item['isEntry'] ?? false),
            (bool)($item['isDynamicEntry'] ?? false),
            (array)($item['assets'] ?? []),
            (array)($item['css'] ?? []),
            (array)($item['imports'] ?? []),
            (array)($item['dynamicImports'] ?? []),
        );
    }
}
