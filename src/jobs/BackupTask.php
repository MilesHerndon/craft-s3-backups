<?php
/**
 * S3 Backups plugin for Craft CMS 3.x
 *
 * Plugin to backup Craft to AWS S3
 *
 * @link      https://milesherndon.com
 * @copyright Copyright (c) 2019 MilesHerndon
 */

namespace milesherndon\s3backups\jobs;

use milesherndon\s3backups\S3Backups;
use milesherndon\s3backups\elements\Backup as BackupElement;

use Craft;
use craft\helpers\App as CraftApp;
use craft\queue\BaseJob;
use yii\queue\RetryableJobInterface;

/**
 * @author    MilesHerndon
 * @package   S3Backups
 * @since     1.0.0
 */
class BackupTask extends BaseJob
{
    // Public Properties
    // =========================================================================

    /**
     * @var string
     */
    public $backup;

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function execute($queue)
    {
        CraftApp::maxPowerCaptain();

        $totalSteps = 3;

        try {
            $step = 1;
            $this->setProgress($queue, $step / $totalSteps);

            $files['db'] = S3Backups::$plugin->backupService->exportDatabase();
            $basename = basename($files['db'], '.sql');
            $files['files'] = S3Backups::$plugin->backupService->exportBackupFiles($basename);

            $step = 2;
            $this->setProgress($queue, $step / $totalSteps);

            $backups = [];
            foreach ($files as $key => $file) {
                $response[$key] = S3Backups::$plugin->s3Service->uploadToS3Multipart($file);
                if ($response[$key]['@metadata']['statusCode'] !== 200) {
                    return $response[$key];
                }

                $backup = new BackupElement();
                $backup->type = $key;
                $backup->bucket = S3Backups::$plugin->s3Service->getBucketName();
                $backup->basename = $basename;
                $backup->filename = basename($file);
                $backup->location = $response[$key]['ObjectURL'];
                $save = S3Backups::$plugin->backupService->saveBackup($backup);

                if ($save) {
                    $backups[] = $backup;
                }
            }

            $step = 3;
            $this->setProgress($queue, $step / $totalSteps);

            S3Backups::$plugin->backupService->cleanUpBackups();
            S3Backups::$plugin->notificationService->sendNotification($backups);
            return $response;
        } catch (\Throwable $e) {
            throw $e;
        }
    }

    // Protected Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    protected function defaultDescription(): string
    {
        return Craft::t('s3-backups', 'Running Craft Backup');
    }
}
