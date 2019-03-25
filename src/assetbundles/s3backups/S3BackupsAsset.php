<?php
/**
 * S3 Backups plugin for Craft CMS 3.x
 *
 * Plugin to backup Craft to AWS S3 
 *
 * @link      https://milesherndon.com
 * @copyright Copyright (c) 2019 MilesHerndon
 */

namespace milesherndon\s3backups\assetbundles\S3Backups;

use Craft;
use craft\web\AssetBundle;
use craft\web\assets\cp\CpAsset;

/**
 * @author    MilesHerndon
 * @package   S3Backups
 * @since     1.0.0
 */
class S3BackupsAsset extends AssetBundle
{
    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function init()
    {
        $this->sourcePath = "@milesherndon/s3backups/assetbundles/s3backups/dist";

        $this->depends = [
            CpAsset::class,
        ];

        $this->js = [
            'js/S3Backups.js',
        ];

        $this->css = [
            'css/S3Backups.css',
        ];

        parent::init();
    }
}
