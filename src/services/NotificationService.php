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

use Craft;
use craft\base\Component;
use craft\mail\Message;
use craft\services\Path;
use yii\base\Exception;

/**
 * @author    MilesHerndon
 * @package   S3Backups
 * @since     1.0.0
 */
class NotificationService extends Component
{
    /**
     * Send an email notification
     *
     * @param $backups
     * @return
     */
    public function sendNotification($backups = [])
    {
        $message = new Message();
        $settings = S3Backups::getInstance()->getSettings();
        $recipients = array_map('trim', explode(PHP_EOL, $settings->emailRecipients));

        $html = $this->getEmailTemplate([
            'subject' => $settings->emailSubject,
            'backups' => $backups
        ]);

        $message->setTo($recipients);
        $message->setFrom([$settings->fromName => $settings->fromEmail]);
        $message->setReplyTo($settings->replyEmail);
        $message->setSubject($settings->emailSubject);
        $message->setHtmlBody($html);

        return Craft::$app->mailer->send($message);
    }

    /**
     * Create HTML email template
     *
     * @param $variables
     * @return string
     */
    private function getEmailTemplate($variables) : string
    {
        $template = Craft::getAlias('@milesherndon/s3backups/templates/');

        $view = Craft::$app->getView();
        $view->setTemplatesPath($template);

        return Craft::$app->view->renderTemplate('email', $variables);
    }
}
