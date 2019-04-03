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

class RecipientsValidator extends Validator
{
    /**
     * @inheritdoc
     */
    public function validateAttribute($object, $attribute)
    {
        $value = $object->$attribute;

        if ($emails = explode(PHP_EOL, $value)) {
            foreach ($emails as $email) {
                if ($email) {
                    $this->validateRecipient($object, $attribute, $email);
                }
            }
        }
    }

    /**
     * Validate recipient email address
     *
     * @param $object
     * @param $attribute
     * @param $email
     * @return boolean
     */
    private function validateRecipient($object, $attribute, $email): bool
    {
        $email = trim($email);

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->addError($object, $attribute, Craft::t('s3-backups', 'Please make sure all emails are valid.'));

            return false;
        }

        return true;
    }
}
