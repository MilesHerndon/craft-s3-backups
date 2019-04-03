<?php
/**
 * S3 Backups plugin for Craft CMS 3.x
 *
 * Plugin to backup Craft to AWS S3
 *
 * @link      https://milesherndon.com
 * @copyright Copyright (c) 2019 MilesHerndon
 */

namespace milesherndon\s3backups\controllers;

use milesherndon\s3backups\S3Backups;
use milesherndon\s3backups\models\Settings;
use milesherndon\s3backups\services\BackupService;
use milesherndon\s3backups\services\S3Service;
use milesherndon\s3backups\records\Backup as BackupRecord;

use Craft;
use craft\web\Controller;
use yii\base\Exception;

/**
 * @author    MilesHerndon
 * @package   S3Backups
 * @since     1.0.0
 */
class DefaultController extends Controller
{
    // Protected Properties
    // =========================================================================

    /**
     * @var    bool|array Allows anonymous access to this controller's actions.
     *         The actions must be in 'kebab-case'
     * @access protected
     */
    protected $allowAnonymous = ['get-bucket-data', 'run-backup', 'run-backup-task'];

    // Public Methods
    // =========================================================================

    public function init()
    {
        parent::init();
        $this->defaultAction = 'get-bucket-data';
    }

    /**
     * Load bucket data for specified credentials.
     *
     * @return Response
     */
    public function actionGetBucketData()
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        $request = Craft::$app->getRequest();
        $keyId = Craft::parseEnv($request->getRequiredBodyParam('keyId'));
        $secret = Craft::parseEnv($request->getRequiredBodyParam('secret'));

        try {
            return $this->asJson(Settings::loadBucketList($keyId, $secret));
        } catch (\Throwable $e) {
            return $this->asErrorJson($e->getMessage());
        }
    }

    /**
     * Run backup of Craft outside of queue
     *
     * @return Response
     */
    public function actionRunBackup()
    {
        try {
            $response = S3Backups::$plugin->backupService->initBackup();
            S3Backups::$plugin->backupService->cleanUpBackups();
            S3Backups::$plugin->notificationService->sendNotification();

            return $this->asJson($response);
        } catch (\Throwable $e) {
            return $this->asErrorJson($e->getMessage());
        }
    }

    /**
     * Run backup of Craft through queue job
     *
     * @return Response
     */
    public function actionRunBackupTask()
    {
        try {
            S3Backups::$plugin->backupService->executeBackup();
            Craft::$app->getSession()->setNotice(Craft::t('s3-backups', 'Backup Task Running'));
        } catch (\Throwable $e) {
            return $this->asErrorJson($e->getMessage());
        }
    }
}
