<?php
/**
 * S3 Backups plugin for Craft CMS 3.x
 *
 * Plugin to backup Craft to AWS S3
 *
 * @link      https://milesherndon.com
 * @copyright Copyright (c) 2019 MilesHerndon
 */

namespace milesherndon\s3backups\elements\db;

use craft\elements\db\ElementQuery;
use craft\helpers\Db;

class BackupQuery extends ElementQuery
{

    // Public Properties
    // =========================================================================
    public $id;

    /**
     * @inheritdoc
     */
    public function __construct($elementType, array $config = [])
    {
        if (!isset($config['orderBy'])) {
            $config['orderBy'] = 's3backups_records.dateCreated';
        }

        parent::__construct($elementType, $config);
    }


    // Protected Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    protected function beforePrepare(): bool
    {
        $this->joinElementTable('s3backups_records');

        $this->query->select([
            's3backups_records.basename',
            's3backups_records.filename',
            's3backups_records.bucket'
        ]);

        if ($this->orderBy !== null && empty($this->orderBy) && !$this->structureId && !$this->fixedOrder) {
            $this->orderBy = 'elements.dateCreated desc';
        }

        return parent::beforePrepare();
    }
}
