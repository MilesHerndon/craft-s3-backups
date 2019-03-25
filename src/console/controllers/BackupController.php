<?php
/**
 * S3 Backups plugin for Craft CMS 3.x
 *
 * Plugin to backup Craft to AWS S3
 *
 * @link      https://milesherndon.com
 * @copyright Copyright (c) 2019 MilesHerndon
 */

namespace milesherndon\s3backups\console\controllers;

use milesherndon\s3backups\S3Backups;

use Craft;
use yii\console\Controller;
use yii\helpers\Console;

/**
 * @author    MilesHerndon
 * @package   S3Backups
 * @since     1.0.0
 */
class BackupController extends Controller
{
    // Public Methods
    // =========================================================================

    /**
     * Handle console-command/backup console commands
     *
     * @return mixed
     */
    public function actionIndex()
    {
        $result = 'something';

        echo "Welcome to the console BackupController actionIndex() method\n";

        return $result;
    }

    /**
     * Handle console-command/backup/do-something console commands
     *
     * @return mixed
     */
    public function actionDoSomething()
    {
        $result = 'something';

        echo "Welcome to the console BackupController actionDoSomething() method\n";

        return $result;
    }
}
