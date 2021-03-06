<?php
/**
 * Copyright © Vaimo Group. All rights reserved.
 * See LICENSE_VAIMO.txt for license details.
 */
namespace Vaimo\ComposerPatches\Patch;

use Composer\Package\RootPackage;
use Vaimo\ComposerPatches\Interfaces\PatchSourceLoaderInterface;
use Vaimo\ComposerPatches\Patch\Definition as PatchDefinition;
use Composer\Package\PackageInterface;

class Collector
{
    /**
     * @var \Vaimo\ComposerPatches\Patch\ListNormalizer
     */
    private $listNormalizer;

    /**
     * @var \Vaimo\ComposerPatches\Interfaces\PackageConfigExtractorInterface
     */
    private $infoExtractor;

    /**
     * @var PatchSourceLoaderInterface[]
     */
    private $sourceLoaders;

    /**
     * @var \Vaimo\ComposerPatches\Utils\PatchListUtils 
     */
    private $patchListUtils;

    /**
     * @param \Vaimo\ComposerPatches\Patch\ListNormalizer $listNormalizer
     * @param \Vaimo\ComposerPatches\Interfaces\PackageConfigExtractorInterface $infoExtractor
     * @param PatchSourceLoaderInterface[] $sourceLoaders
     */
    public function __construct(
        \Vaimo\ComposerPatches\Patch\ListNormalizer $listNormalizer,
        \Vaimo\ComposerPatches\Interfaces\PackageConfigExtractorInterface $infoExtractor,
        array $sourceLoaders
    ) {
        $this->listNormalizer = $listNormalizer;
        $this->infoExtractor = $infoExtractor;
        $this->sourceLoaders = $sourceLoaders;

        $this->patchListUtils = new \Vaimo\ComposerPatches\Utils\PatchListUtils();
    }

    /**
     * @param \Composer\Package\PackageInterface[] $packages
     * @return array
     */
    public function collect(array $packages)
    {
        $patchesByOwner = array();

        foreach ($packages as $package) {
            $ownerName = $package->getName();
            
            $config = array_filter(
                (array)$this->infoExtractor->getConfig(
                    $package, 
                    \Vaimo\ComposerPatches\Config::CONFIG_ROOT
                )
            );

            /** @var PatchSourceLoaderInterface[] $sourceLoaders */
            $sourceLoaders = array_intersect_key($this->sourceLoaders, $config);
            $ownerConfig = array_diff_key($config, $this->sourceLoaders);
            
            $loadedPatches = array();
            
            if (!$sourceLoaders) {
                continue;
            }
            
            foreach ($sourceLoaders as $loaderName => $source) {
                $resultGroups = $source->load($package, $config[$loaderName]);

                $loadedPatches[$loaderName] = $this->applyListManipulators(
                    $resultGroups, 
                    $ownerConfig
                );
            }
            
            $patches = array_reduce(
                array_reduce($loadedPatches, 'array_merge', array()),
                'array_merge_recursive', 
                array()
            );
            
            $patchesByOwner[$ownerName] = $this->updatePackagePatchesConfig($package, $patches);
        }
        
        return array_reduce($patchesByOwner, 'array_merge_recursive', array());
    }
    
    private function applyListManipulators(array $resultGroups, array $ownerConfig)
    {
        $normalizer = $this->listNormalizer;

        return array_map(
            function (array $results) use ($ownerConfig, $normalizer) {
                $normalizedList = $normalizer->normalize($results, $ownerConfig);

                return $this->applySharedConfig($results, $normalizedList);
            },
            $resultGroups
        );
    }
    
    private function applySharedConfig(array $configOrigin, array $patches)
    {
        $baseConfig = isset($configOrigin['_config']) ? $configOrigin['_config'] : array();

        foreach ($configOrigin as $target => $items) {
            $updates = array_replace(
                $baseConfig,
                isset($items['_config']) ? $items['_config'] : array()
            );

            if (!$updates || !isset($patches[$target])) {
                continue;
            }
            
            $patches[$target] = array_map(
                function ($config) use ($updates) {
                    return array_replace($config, $updates);
                },
                $patches[$target]
            );
        }
        
        return $patches;
    }
    
    private function updatePackagePatchesConfig(PackageInterface $package, array $patches)
    {
        return $this->patchListUtils->embedInfoToItems($patches, array(
            PatchDefinition::OWNER => $package->getName(),
            PatchDefinition::OWNER_IS_ROOT => $package instanceof RootPackage
        ));
    }
}
