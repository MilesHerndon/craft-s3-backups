<?php
/**
 * S3 Backups plugin for Craft CMS 3.x
 *
 * Plugin to backup Craft to AWS S3
 *
 * @link      https://milesherndon.com
 * @copyright Copyright (c) 2019 MilesHerndon
 */

namespace milesherndon\s3backups\helpers;

use milesherndon\s3backups\S3Backups;

use Craft;
use craft\helpers\FileHelper;
use ZipArchive;

/**
 * @author    MilesHerndon
 * @package   S3Backups
 * @since     1.0.1
 */
class ReportFileHelper extends FileHelper
{
    // Private Properties
    // =========================================================================

    /**
     * @var array Paths to skip
     */
    private $skipArray = [
        '.env',
        'vendor',
        'node_modules',
        'storage/backups',
        'storage/composer-backups',
        'storage/config-backups',
        'storage/logs',
        'storage/runtime',
        'cpresources'
    ];

    // Public Methods
    // =========================================================================

    /**
     * Create zip file
     *
     * @param $source
     * @param $destination
     * @return string|bool
     * @throws Exception
     */
    public static function createZipArchive($source, $destination)
    {
        try {
            if (extension_loaded('zip')) {
                if (file_exists($source)) {
                    $zip = new ZipArchive();
                    if ($zip->open($destination, ZIPARCHIVE::CREATE)) {
                        $source = realpath($source);
                        if (is_dir($source)) {
                            $iterator = new \RecursiveDirectoryIterator($source);
                            // skip dot files while iterating
                            $iterator->setFlags(\RecursiveDirectoryIterator::SKIP_DOTS);
                            $files = new \RecursiveIteratorIterator($iterator, \RecursiveIteratorIterator::SELF_FIRST);

                            foreach ($files as $file) {
                                $file = realpath($file);
                                if (is_dir($file) && self::checkFilePathsToSkip($file) === false) {
                                    $zip->addEmptyDir(str_replace($source . '/', '', $file . '/'));
                                } elseif (is_file($file) && self::checkFilePathsToSkip($file) === false) {
                                    $zip->addFromString(str_replace($source . '/', '', $file), file_get_contents($file));
                                }
                            }
                        } elseif (is_file($source)) {
                            $zip->addFromString(basename($source), file_get_contents($source));
                        }
                    }
                    return $zip->close();
                }
            }

            return false;
        } catch (Exception $e) {
            return $e;
        }
    }

    /**
     * Check path to verify it should be archived or skipped
     *
     * @param $pathToFind
     * @return bool
     */
    public static function checkFilePathsToSkip($pathToFind)
    {
        foreach (self::skipArray as $path) {
            if (strpos($pathToFind, $path) !== false) {
                return true;
            }
        }

        return false;
    }
}
