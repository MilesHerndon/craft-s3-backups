<?php
/**
 * S3 Backups plugin for Craft CMS 3.x
 *
 * Plugin to backup Craft to AWS S3
 *
 * @link      https://milesherndon.com
 * @copyright Copyright (c) 2019 MilesHerndon
 */

namespace milesherndon\s3backups\variables;

use milesherndon\s3backups\S3Backups;

use Craft;

/**
 * @author    MilesHerndon
 * @package   S3Backups
 * @since     1.0.0
 */
class S3BackupsVariable
{
    // Public Methods
    // =========================================================================

    /**
     * @return Settings
     */
    public function getSettings()
    {
        return S3Backups::$plugin->settingsService->getSettings();
    }
}
