<?php
/**
 * S3 Backups plugin for Craft CMS 3.x
 *
 * Plugin to backup Craft to AWS S3
 *
 * @link      https://milesherndon.com
 * @copyright Copyright (c) 2019 MilesHerndon
 */

namespace milesherndon\s3backups\validators;

use milesherndon\s3backups\S3Backups;

use yii\validators\Validator;
use Craft;

class NotificationValidator extends Validator
{
    public $skipOnEmpty = false;

    /**
     * Validate notification fields being submitted
     *
     * @param $object
     * @param $attribute
     */
    public function validateAttribute($object, $attribute)
    {
        Craft::dd($object);
        if ($object->enableNotifications && !$object->emailRecipients) {
            $this->addError($object, $attribute, Craft::t('s3-backups', 'Recipients cannot be blank'));
        }

        if ($object->enableNotifications && !$object->fromName) {
            $this->addError($object, $attribute, Craft::t('s3-backups', 'Sender Name cannot be blank'));
        }

        if ($object->enableNotifications && !$object->fromEmail) {
            $this->addError($object, $attribute, Craft::t('s3-backups', 'Sender Email cannot be blank'));
        }

        if ($object->enableNotifications && !$object->replyEmail) {
            $this->addError($object, $attribute, Craft::t('s3-backups', 'Reply Email cannot be blank'));
        }

        if ($object->enableNotifications && !$object->emailSubject) {
            $this->addError($object, $attribute, Craft::t('s3-backups', 'Subject cannot be blank'));
        }
    }
}
