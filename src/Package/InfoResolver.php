<?php
/**
 * Copyright © Vaimo Group. All rights reserved.
 * See LICENSE_VAIMO.txt for license details.
 */
namespace Vaimo\ComposerPatches\Package;

use Composer\Package\PackageInterface;

use Vaimo\ComposerPatches\Composer\ConfigKeys;
use Vaimo\ComposerPatches\Patch\Definition as Patch;

class InfoResolver
{
    const DEFAULT_PATH = '.';
    
    /**
     * @var \Composer\Installer\InstallationManager
     */
    private $installationManager;

    /**
     * @var string
     */
    private $vendorRoot;

    /**
     * @var array
     */
    private $installPathCache = array();
    
    /**
     * @param \Composer\Installer\InstallationManager $installationManager
     * @param string $vendorRoot
     */
    public function __construct(
        \Composer\Installer\InstallationManager $installationManager,
        $vendorRoot
    ) {
        $this->installationManager = $installationManager;
        $this->vendorRoot = $vendorRoot;
    }
    
    public function getSourcePath(PackageInterface $package)
    {
        return !$package instanceof \Composer\Package\RootPackage
            ? $this->installationManager->getInstallPath($package)
            : realpath(dirname(\Composer\Factory::getComposerFile()));
    }
    
    public function resolveNamesFromPaths(array $packagesByName, array $paths)
    {
        $paths = array_unique(
            array_map('dirname', $paths)
        );

        $names = array();
        
        foreach ($paths as $path) {
            $segments = explode(DIRECTORY_SEPARATOR, $path);

            while ($chunk = array_slice($segments, 0, 2)) {
                array_shift($segments);

                $name = implode(DIRECTORY_SEPARATOR, $chunk);

                if (!isset($packagesByName[$name])) {
                    continue;
                }

                $names[] = $name;

                break;
            }
        }
        
        return $names;
    }
    
    public function getInstallPath(PackageInterface $package, $resolveMode)
    {
        $key = $package->getName() . '|' . $resolveMode;

        if (!isset($this->installPathCache[$key])) {
            switch ($resolveMode) {
                case Patch::CWD_VENDOR:
                    $this->installPathCache[$key] = $this->vendorRoot;
                    break;
                case Patch::CWD_PROJECT:
                    $this->installPathCache[$key] = getcwd();
                    break;
                case Patch::CWD_AUTOLOAD:
                    $autoloadConfig = $package->getAutoload();

                    $installPath = $this->getInstallPath($package, Patch::CWD_INSTALL);
                    
                    if (!isset($autoloadConfig[ConfigKeys::PSR4_CONFIG])) {
                        return $installPath;
                    }

                    $this->installPathCache[$key] = $installPath . DIRECTORY_SEPARATOR . 
                        reset($autoloadConfig[ConfigKeys::PSR4_CONFIG]);
                    
                    break;
                case Patch::CWD_INSTALL:
                default:
                    if ($package instanceof \Composer\Package\RootPackage) {
                        $this->installPathCache[$key] = $this->vendorRoot;
                    } else {
                        $this->installPathCache[$key] = $this->getSourcePath($package);
                    }

                    break;
            }
        }

        return $this->installPathCache[$key];
    }
}
