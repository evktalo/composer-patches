<?php
/**
 * Copyright © Vaimo Group. All rights reserved.
 * See LICENSE_VAIMO.txt for license details.
 */
namespace Vaimo\ComposerPatches\Utils;

use Vaimo\ComposerPatches\Config;

class ConfigUtils
{
    public function mergeApplierConfig(array $config, array $updates)
    {
        if (isset($updates[Config::PATCHER_SECURE_HTTP])) {
            $config[Config::PATCHER_SECURE_HTTP] = $updates[Config::PATCHER_SECURE_HTTP];
        }

        if (isset($updates[Config::PATCHER_GRACEFUL])) {
            $config[Config::PATCHER_GRACEFUL] = $updates[Config::PATCHER_GRACEFUL];
        }

        if (isset($updates[Config::PATCHER_FORCE_RESET])) {
            $config[Config::PATCHER_FORCE_RESET] = $updates[Config::PATCHER_FORCE_RESET];
        }

        foreach ($config[Config::PATCHER_APPLIERS] as $code => $applier) {
            if (!isset($updates[Config::PATCHER_APPLIERS][$code])) {
                continue;
            }

            $config[Config::PATCHER_APPLIERS][$code] = array_replace(
                $config[Config::PATCHER_APPLIERS][$code],
                $updates[Config::PATCHER_APPLIERS][$code]
            );
        }

        $config[Config::PATCHER_SEQUENCE] = array_replace(
            $config[Config::PATCHER_SEQUENCE],
            isset($updates[Config::PATCHER_SEQUENCE]) ? $updates[Config::PATCHER_SEQUENCE] : array()
        );

        if (isset($updates[Config::PATCHER_SOURCES])) {
            if ($updates[Config::PATCHER_SOURCES]) {
                $config[Config::PATCHER_SOURCES] = array_replace(
                    $config[Config::PATCHER_SOURCES],
                    $updates[Config::PATCHER_SOURCES]
                );
            } else {
                $config[Config::PATCHER_SOURCES] = array();
            }
        }

        if (isset($updates[Config::PATCHER_FAILURES])) {
            foreach ($updates[Config::PATCHER_FAILURES] as $code => $patterns) {
                $config[Config::PATCHER_FAILURES][$code] = array_replace(
                    $config[Config::PATCHER_FAILURES][$code], 
                    $patterns
                );
            }
        }
        
        $config[Config::PATCHER_OPERATIONS] = array_replace(
            $config[Config::PATCHER_OPERATIONS],
            isset($updates[Config::PATCHER_OPERATIONS]) ? $updates[Config::PATCHER_OPERATIONS] : array()
        );

        $config[Config::PATCHER_LEVELS] = isset($updates[Config::PATCHER_LEVELS])
            ? $updates[Config::PATCHER_LEVELS]
            : $config[Config::PATCHER_LEVELS];

        return $config;
    }

    public function sortApplierConfig(array $config)
    {
        $sequences = $config[Config::PATCHER_SEQUENCE];
        
        $sequencedConfigItems = array_keys($sequences);

        foreach ($sequencedConfigItems as $item) {
            if (!isset($config[$item])) {
                continue;
            }

            $config[$item] = array_replace(
                array_flip($sequences[$item]),
                array_intersect_key(
                    $config[$item],
                    array_flip($sequences[$item])
                )
            );
        }

        return $config;
    }
    
    public function validateConfig(array $config)
    {
        $patchers = $this->extractArrayValue($config, Config::PATCHER_APPLIERS);
        
        $sequenceConfig = $config[Config::PATCHER_SEQUENCE];

        $patcherSequence = array_filter(
            $this->extractArrayValue($sequenceConfig, Config::PATCHER_APPLIERS)
        );
        
        if ((!$patcherSequence || array_intersect_key($patchers, array_flip($patcherSequence))) 
            && is_array($patchers) && array_filter($patchers)
        ) {
            return;
        }
        
        if ($patcherSequence) {
            $message = sprintf('No valid patchers found for sequence: %s', implode(',', $patcherSequence));
        } else {
            $message = 'No valid patchers defined';
        }
        
        throw new \Vaimo\ComposerPatches\Exceptions\ConfigValidationException($message);
    }

    private function extractArrayValue($data, $key)
    {
        return isset($data[$key]) ? $data[$key] : array();
    }
}
