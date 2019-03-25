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
        $totalSteps = 3;

        try {
            $step = 1;
            $this->setProgress($queue, $step / $totalSteps);

            $files['backupDBPath'] = S3Backups::$plugin->backupService->exportDatabase();
            $basename = basename($files['backupDBPath'], '.sql');
            $files['backupFilePath'] = S3Backups::$plugin->backupService->exportBackupFiles($basename);

            $step = 2;
            $this->setProgress($queue, $step / $totalSteps);

            $backups = [];
            foreach ($files as $file) {
                $response = S3Backups::$plugin->s3Service->uploadToS3Multipart($file);

                $backup = new BackupElement();
                $backup->bucket = S3Backups::$plugin->s3Service->getBucketName();
                $backup->basename = $basename;
                $backup->filename = basename($file);
                S3Backups::$plugin->backupService->saveBackup($backup);

                if ($save) {
                    $backups[] = $backup;
                }
            }

            S3Backups::$plugin->notificationService->sendNotification($backups);

            $step = 3;
            $this->setProgress($queue, $step / $totalSteps);

            S3Backups::$plugin->backupService->cleanUpBackups();
        } catch (\Throwable $e) {
            return $e->getMessage();
        }
    }

    /**
     * @inheritdoc
     */
    public function getTtr()
    {
        return 3600;
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
