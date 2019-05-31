<?php
/**
 * S3 Backups plugin for Craft CMS 3.x
 *
 * Plugin to backup Craft to AWS S3
 *
 * @link      https://milesherndon.com
 * @copyright Copyright (c) 2019 MilesHerndon
 */

namespace milesherndon\s3backups\services;

use milesherndon\s3backups\S3Backups;
use milesherndon\s3backups\elements\Backup as BackupElement;
use milesherndon\s3backups\records\Backup as BackupRecord;
use milesherndon\s3backups\helpers\BackupFileHelper;
use milesherndon\s3backups\jobs\BackupTask;

use Craft;
use craft\base\Component;
use craft\helpers\App as CraftApp;
use craft\services\Path;
use yii\base\Exception;

/**
 * @author    MilesHerndon
 * @package   S3Backups
 * @since     1.0.0
 */
class BackupService extends Component
{
    // Private Properties
    // =========================================================================

    /**
     * @var object Backup Record
     */
    private $backupRecord;

    /**
     * @var integer Buffer size
     */
    private $buffer = 104800000; // 100MB

    // Public Methods
    // =========================================================================

    public function init()
    {
        if (is_null($this->backupRecord)) {
            $this->backupRecord = new BackupRecord();
        }

        parent::init();
    }

    /**
     * Export Craft database
     *
     * @return Path
     * @throws Exception
     */
    public function exportDatabase()
    {
        try {
            return Craft::$app->getDb()->backup();
        } catch (\Throwable $e) {
            return $e;
        }
    }

    /**
     * Export zip of Craft files
     *
     * @return string
     * @throws Exception
     */
    public function exportBackupFiles($basename)
    {
        try {
            $craftPath = new Path;

            $zipPath = $craftPath->getDbBackupPath() . '/' . $basename . '.zip';
            BackupFileHelper::createZipArchive(CRAFT_BASE_PATH, $zipPath);

            return $zipPath;
        } catch (Exception $e) {
            return $e;
        }
    }

    /**
     * Gets all Saved Backups
     *
     * @return array|null
     */
    public function getAllBackups()
    {
        return BackupElement::find()->all();
    }

    /**
     * Creates a backup element
     *
     * @return BackupElement
     * @throws Exception
     * @throws \yii\web\ServerErrorHttpException
     * @throws \Throwable
     */
    public function initBackup()
    {
        CraftApp::maxPowerCaptain();

        $files['db'] = $this->exportDatabase();
        $basename = basename($files['db'], '.sql');
        $files['files'] = $this->exportBackupFiles($basename);

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
                BackupFileHelper::unlink($file);
            }
        }

        return $backups;
    }

    public function executeBackup()
    {
        Craft::$app->queue->push(new BackupTask());

        if (Craft::$app->getRequest()->getIsSiteRequest()) {
            Craft::$app->queue->run();
        }

        return [
            'success' => true,
            'message' => 'running'
        ];
    }

    /**
     * @param $backup BackupElement
     *
     * @return boolean
     * @throws Exception
     */
    public function saveBackup(BackupElement $backup)
    {
        if ($backup->id) {
            $backupRecord = BackupRecord::findOne($backup->id);

            if (!$backupRecord) {
                throw new Exception(Craft::t('s3-backups', 'No backup exists with the ID “{id}”', ['id' => $backup->id]));
            }
        }

        $backup->validate();
        if ($backup->hasErrors()) {
            return false;
        }

        $transaction = Craft::$app->db->beginTransaction();

        try {
            if (Craft::$app->elements->saveElement($backup)) {
                $transaction->commit();
                return true;
            }

            return false;
        } catch (Exception $e) {
            $transaction->rollback();
            return $e;
        }
    }

    /**
     * Runs cleanup to remove old elements
     *
     * @return void
     * @throws Exception
     */
    public function cleanUpBackups()
    {
        $totalBackups = Craft::parseEnv(S3Backups::getInstance()->getSettings()->totalBackups);

        if ($totalBackups > 0) {
            $records = BackupElement::find()->offset($totalBackups * 2)->orderBy('dateCreated desc')->all();
            try {
                foreach ($records as $record) {
                    Craft::$app->elements->deleteElementById($record->id);
                }
            } catch (Exception $e) {
                return $e;
            }
        }
    }
}
