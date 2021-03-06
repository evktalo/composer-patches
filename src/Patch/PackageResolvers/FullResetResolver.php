<?php
/**
 * Copyright © Vaimo Group. All rights reserved.
 * See LICENSE_VAIMO.txt for license details.
 */
namespace Vaimo\ComposerPatches\Patch\PackageResolvers;

class FullResetResolver implements \Vaimo\ComposerPatches\Interfaces\PatchPackagesResolverInterface
{
    /**
     * @var \Vaimo\ComposerPatches\Utils\PackagePatchDataUtils 
     */
    private $packagePatchDataUtils;
    
    /**
     * @var \Vaimo\ComposerPatches\Utils\PackageUtils
     */
    private $packageUtils;

    public function __construct()
    {
        $this->packagePatchDataUtils = new \Vaimo\ComposerPatches\Utils\PackagePatchDataUtils();
        $this->packageUtils = new \Vaimo\ComposerPatches\Utils\PackageUtils();
    }

    public function resolve(array $patches, array $repositoryState)
    {
        $matches = array();

        foreach ($repositoryState as $name => $packageState) {
            if (!$this->packagePatchDataUtils->shouldReinstall($packageState, array()) && !isset($patches[$name])) {
                continue;
            }

            $matches[] = $name;
        }

        return $matches;
    }
}
