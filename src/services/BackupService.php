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
use milesherndon\s3backups\jobs\BackupTask;

use Craft;
use craft\base\Component;
use craft\helpers\FileHelper;
use craft\services\Path;
use yii\base\Exception;
use ZipArchive;

/**
 * @author    MilesHerndon
 * @package   S3Backups
 * @since     1.0.0
 */
class BackupService extends Component
{
    private $skipArray = [
        CRAFT_BASE_PATH.'/.env',
        CRAFT_BASE_PATH.'/vendor',
        CRAFT_BASE_PATH.'/node_modules',
        CRAFT_BASE_PATH.'/storage/backups',
        CRAFT_BASE_PATH.'/storage/composer-backups',
        CRAFT_BASE_PATH.'/storage/config-backups',
        CRAFT_BASE_PATH.'/storage/logs',
        CRAFT_BASE_PATH.'/storage/runtime',
        'cpresources'
    ];

    private $backupRecord;

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

    /*
     * Export Craft database
     *
     * @return Path|error
     */
    public function exportDatabase()
    {
        try {
            return Craft::$app->getDb()->backup();
        } catch (\Throwable $e) {
            return $e->getMessage();
        }
    }

    /*
     * Export zip of Craft files
     *
     * @return Path|error
     */
    public function exportBackupFiles($basename)
    {
        try {
            $craftPath = new Path;

            $zipPath = $craftPath->getDbBackupPath() . '/' . $basename . '.zip';
            $this->zipData(CRAFT_BASE_PATH, $zipPath);

            return $zipPath;
        } catch (\Exception $e) {
            return $e->getMessage();
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
     * This function creates a default backup and generates the id
     *
     * @return BackupElement
     * @throws \Exception
     * @throws \yii\web\ServerErrorHttpException
     * @throws \Throwable
     */
    public function initBackup()
    {
        $files['backupDBPath'] = $this->exportDatabase();
        $basename = basename($files['backupDBPath'], '.sql');
        $files['backupFilePath'] = $this->exportBackupFiles($basename);

        foreach ($files as $file) {
            $response[] = S3Backups::$plugin->s3Service->uploadToS3Multipart($file);

            $backup = new BackupElement();
            $backup->bucket = S3Backups::$plugin->s3Service->getBucketName();
            $backup->basename = $basename;
            $backup->filename = basename($file);
            $this->saveBackup($backup);
        }

        return $response;
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
                throw new Exception(Backup::t('No Backup exists with the ID “{id}”', ['id' => $backup->id]));
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
        } catch (\Exception $e) {
            $transaction->rollback();
            throw $e;
        }
    }

    public function zipData($source, $destination)
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
                                if (is_dir($file) && $this->checkPathsToSkip($file) === false) {
                                    $zip->addEmptyDir(str_replace($source . '/', '', $file . '/'));
                                } elseif (is_file($file) && $this->checkPathsToSkip($file) === false) {
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
        } catch (\Exception $e) {
            return $e->getMessage();
        }
    }

    public function checkPathsToSkip($pathToFind)
    {
        foreach ($this->skipArray as $path) {
            if (strpos($pathToFind, $path) !== false) {
                return true;
            }
        }

        return false;
    }

    public function cleanUpBackups()
    {
        $totalBackups = Craft::parseEnv(S3Backups::getInstance()->getSettings()->totalBackups);

        if ($totalBackups > 0) {

            // TODO: Update to dynamically determine how many files are created for the backup | 2 for zip + sql
            $records = BackupElement::find()->offset($totalBackups * 2)->orderBy('dateCreated desc')->all();

            try {
                foreach ($records as $record) {
                    // Delete DB Record
                    Craft::$app->elements->deleteElementById($record->id);
                }
            } catch (Exception $e) {
                return $e->getMessage();
            }
        }
    }

    public function createFileChunks($file)
    {
        $largeFile = fopen($file, 'r');
        $size = filesize($file);
        $parts = $size / $this->buffer;

        $fileParts = array();

        $name = basename($file);

        for ($i=0;$i<$parts;$i++) {
            $part = fread($largeFile, $this->buffer);

            $craftPath = new Path;
            $partPath = $craftPath->getDbBackupPath() . '/' . $name . ".part$i";

            $newFile = fopen($partPath, 'w+');

            fwrite($newFile, $part);
            array_push($fileParts, $partPath);
            fclose($newFile);
        }

        fclose($largeFile);

        return $fileParts;
    }
}
