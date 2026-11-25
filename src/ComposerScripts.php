<?php

namespace Ghazali\EPassPackage;

use Composer\Installer\PackageEvent;

class ComposerScripts
{
    public static function prePackageUninstall(PackageEvent $event)
    {
        // Check if the current composer command is removing ghazali/epass-package
        $argv = $_SERVER['argv'] ?? [];
        $isUninstalling = false;
        $hasRemove = false;
        $hasPackage = false;
        foreach ($argv as $arg) {
            if ($arg === 'remove') {
                $hasRemove = true;
            }
            if (str_contains($arg, 'ghazali/epass-package')) {
                $hasPackage = true;
            }
        }
        $isUninstalling = $hasRemove && $hasPackage;

        // Run uninstallation logic ONCE when the first prePackageUninstall event triggers,
        // so that all dependencies and autoload files are still fully intact in vendor/.
        static $alreadyRun = false;

        if ($isUninstalling && !$alreadyRun) {
            $alreadyRun = true;
            
            $io = $event->getIO();
            $io->write('<info>ePass Package:</info> Running automatic uninstallation...');
            
            // Run the uninstall command synchronously in a clean process
            passthru('php artisan epass:uninstall --delete-config');
            
            // Clean up the script from composer.json
            try {
                $composerJsonPath = getcwd() . '/composer.json';
                if (file_exists($composerJsonPath)) {
                    $content = file_get_contents($composerJsonPath);
                    $data = json_decode($content, true);
                    if (is_array($data) && isset($data['scripts']['pre-package-uninstall'])) {
                        $callback = "Ghazali\\EPassPackage\\ComposerScripts::prePackageUninstall";
                        if (is_array($data['scripts']['pre-package-uninstall'])) {
                            $data['scripts']['pre-package-uninstall'] = array_filter(
                                $data['scripts']['pre-package-uninstall'],
                                fn($val) => $val !== $callback
                            );
                            if (empty($data['scripts']['pre-package-uninstall'])) {
                                unset($data['scripts']['pre-package-uninstall']);
                            } else {
                                $data['scripts']['pre-package-uninstall'] = array_values($data['scripts']['pre-package-uninstall']);
                            }
                        } elseif ($data['scripts']['pre-package-uninstall'] === $callback) {
                            unset($data['scripts']['pre-package-uninstall']);
                        }
                        
                        if (empty($data['scripts'])) {
                            unset($data['scripts']);
                        }
                        
                        file_put_contents(
                            $composerJsonPath,
                            json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL
                        );
                        $io->write('<info>ePass Package:</info> Cleaned up composer.json scripts.');
                    }
                }
            } catch (\Throwable $e) {
                // Silently ignore any errors during cleanup
            }
        }
    }
}
