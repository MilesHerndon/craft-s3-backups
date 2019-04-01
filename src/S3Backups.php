<?php
/**
 * S3 Backups plugin for Craft CMS 3.x
 *
 * Plugin to backup Craft to AWS S3
 *
 * @link      https://milesherndon.com
 * @copyright Copyright (c) 2019 MilesHerndon
 */

namespace milesherndon\s3backups;

use milesherndon\s3backups\services\BackupService;
use milesherndon\s3backups\services\S3Service;
use milesherndon\s3backups\models\Settings;

use Craft;
use craft\base\Plugin;
// use craft\console\Application as ConsoleApplication;
use craft\services\Plugins;
use craft\events\PluginEvent;
use craft\events\RegisterUrlRulesEvent;
use craft\web\UrlManager;
use yii\base\Event;

/**
 * Class S3Backups
 *
 * @author    MilesHerndon
 * @package   S3Backups
 * @since     1.0.0
 *
 * @property  BackupServiceService $backupService
 */
class S3Backups extends Plugin
{
    // Static Properties
    // =========================================================================

    /**
     * @var S3Backups
     */
    public static $plugin;

    // Public Properties
    // =========================================================================

    /**
     * @var string
     */
    public $schemaVersion = '1.0.0';

    /**
     * @var boolean
     */
    public $hasCpSection = true;

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function init()
    {
        parent::init();
        self::$plugin = $this;

        // if (Craft::$app instanceof ConsoleApplication) {
        //     $this->controllerNamespace = 'milesherndon\s3backups\console\controllers';
        // }

        Event::on(
            UrlManager::class,
            UrlManager::EVENT_REGISTER_SITE_URL_RULES,
            function (RegisterUrlRulesEvent $event) {
                $event->rules['siteActionTrigger1'] = 's3-backups/load-bucket-data';
                $event->rules['siteActionTrigger2'] = 's3-backups/run-backup';
                $event->rules['siteActionTrigger3'] = 's3-backups/run-backup-task';
            }
        );

        Event::on(
            UrlManager::class,
            UrlManager::EVENT_REGISTER_CP_URL_RULES,
            function (RegisterUrlRulesEvent $event) {
                $event->rules['cpActionTrigger1'] = 's3-backups/default/load-bucket-data';
                $event->rules['cpActionTrigger2'] = 's3-backups/default/run-backup';
                $event->rules['cpActionTrigger3'] = 's3-backups/default/run-backup-task';
            }
        );

        Event::on(
            Plugins::class,
            Plugins::EVENT_AFTER_INSTALL_PLUGIN,
            function (PluginEvent $event) {
                if ($event->plugin === $this) {
                }
            }
        );

        Craft::info(
            Craft::t(
                's3-backups',
                '{name} plugin loaded',
                ['name' => $this->name]
            ),
            __METHOD__
        );
    }

    /**
     * @param string $message
     * @param array  $params
     * @param string $language
     *
     * @return string
     */
    public static function t(string $message, array $params = [], string $language = null): string
    {
        return Craft::t('s3-backups', $message, $params, $language);
    }

    // Protected Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    protected function createSettingsModel()
    {
        return new Settings();
    }

    /**
     * @inheritdoc
     */
    public function getCpNavItem()
    {
        $item = parent::getCpNavItem();
        $item['subnav'] = [
            'index' => ['label' => 'Backups', 'url' => 's3-backups'],
            'settings' => ['label' => 'Settings', 'url' => 'settings/plugins/s3-backups'],
        ];
        return $item;
    }

    /**
     * @inheritdoc
     */
    protected function settingsHtml(): string
    {
        return Craft::$app->view->renderTemplate(
            's3-backups/settings',
            [
                'settings' => $this->getSettings()
            ]
        );
    }

    /**
     * Runs prior to uninstall.
     *
     * @return boolean
     */
    protected function beforeUninstall(): bool
    {
        $backups = self::$plugin->backupService->getAllBackups();

        foreach ($backups as $backup) {
            Craft::$app->elements->deleteElementById($backup->id);
        }

        return true;
    }
}
