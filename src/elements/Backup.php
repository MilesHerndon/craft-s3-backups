<?php
/**
 * S3 Backups plugin for Craft CMS 3.x
 *
 * Plugin to backup Craft to AWS S3
 *
 * @link      https://milesherndon.com
 * @copyright Copyright (c) 2019 MilesHerndon
 */

namespace milesherndon\s3backups\elements;

use milesherndon\s3backups\elements\db\BackupQuery;
use milesherndon\s3backups\records\Backup as BackupRecord;
use milesherndon\s3backups\S3Backups;

use Craft;
use craft\base\Element;
use craft\elements\actions\Delete;
use craft\elements\db\ElementQueryInterface;
use craft\helpers\FileHelper;
use craft\helpers\UrlHelper;
use craft\services\Path;

/**
 * Backup represents a entry element.
 */
class Backup extends Element
{
    /**
     * @var integer
     */
    public $id;

    /**
     * @var string
     */
    public $type;

    /**
     * @var string
     */
    public $basename;

    /**
     * @var string
     */
    public $filename;

    /**
     * @var string
     */
    public $bucket;

    /**
     * @var string
     */
    public $location;

    /**
     * @inheritdoc
     *
     * @return BackupQuery
     */
    public static function find(): ElementQueryInterface
    {
        return new BackupQuery(static::class);
    }

    /**
     * Returns the element type name.
     *
     * @return string
     */
    public static function displayName(): string
    {
        return Craft::t('s3-backups', 'Backup');
    }

    /**
     * @inheritdoc
     */
    public static function refHandle()
    {
        return 'backup';
    }

    /**
     * @inheritdoc
     */
    public static function hasContent(): bool
    {
        return false;
    }

    /**
     * @inheritdoc
     */
    public static function hasTitles(): bool
    {
        return false;
    }

    /**
     * @inheritdoc
     */
    public static function hasStatuses(): bool
    {
        return false;
    }

    /**
     * @return null|string
     */
    public function getFileName()
    {
        if (!empty($this->filename)) {
            return $this->filename;
        }

        return null;
    }

    /**
     * @inheritdoc
     * @throws Exception
     */
    public function afterSave(bool $isNew)
    {
        if (!$isNew) {
            $record = BackupRecord::findOne($this->id);

            if (!$record) {
                throw new \Exception('Invalid Backup ID: '.$this->id);
            }
        } else {
            $record = new BackupRecord();
            $record->id = $this->id;
        }

        $record->basename = $this->basename;
        $record->filename = $this->filename;
        $record->bucket = $this->bucket;

        try {
            $record->save(false);
        } catch (\Exception $e) {
            return $e->message();
        }

        parent::afterSave($isNew);
    }

    /**
     * @inheritdoc
     */
    public function beforeDelete(): bool
    {
        $record = BackupRecord::findOne($this->id);

        S3Backups::$plugin->s3Service->deleteObject($record);

        $craftPath = new Path;
        $localFilePath = $craftPath->getDbBackupPath() . '/' . $record->filename;
        if (file_exists($localFilePath)) {
            FileHelper::unlink($localFilePath);
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    protected static function defineDefaultTableAttributes(string $source): array
    {
        return ['id', 'filename', 'dateCreated'];
    }

    /**
     * @inheritdoc
     */
    protected function tableAttributeHtml(string $attribute): string
    {
        switch ($attribute) {
            case 'filename':
                {
                    return $this->getFileName();
                }
            case 'dateCreated':
                {
                    return $this->dateCreated->format("Y-m-d H:i");
                }
        }

        return parent::tableAttributeHtml($attribute);
    }

    /**
     * @inheritdoc
     */
    protected static function defineSortOptions(): array
    {
        return [
            'elements.dateCreated' => Craft::t('s3-backups', 'Date Created')
        ];
    }

    /**
     * @inheritdoc
     */
    protected static function defineTableAttributes(): array
    {
        return [
            'id' => Craft::t('s3-backups', 'ID'),
            'filename' => Craft::t('s3-backups', 'File Name'),
            'dateCreated' => Craft::t('s3-backups', 'Date Created')
        ];
    }

    /**
     * @inheritdoc
     */
    protected static function defineActions(string $source = null): array
    {
        $actions = [];

        $actions[] = Craft::$app->getElements()->createAction([
            'type' => Delete::class,
            'confirmationMessage' => Craft::t('s3-backups', 'Are you sure you want to delete the selected backups?'),
            'successMessage' => Craft::t('s3-backups', 'Backups deleted.'),
        ]);

        return $actions;
    }

    /**
     * @inheritDoc
     */
    protected static function defineSources(string $context = null): array
    {
        return [
            [
                'key' => '*',
                'label' => 'All Backups',
                'criteria' => []
            ]
        ];
    }
}
