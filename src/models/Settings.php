<?php
/**
 * S3 Backups plugin for Craft CMS 3.x
 *
 * Plugin to backup Craft to AWS S3
 *
 * @link      https://milesherndon.com
 * @copyright Copyright (c) 2019 MilesHerndon
 */

namespace milesherndon\s3backups\models;

use milesherndon\s3backups\S3Backups;
use milesherndon\s3backups\services\S3Service;
use milesherndon\s3backups\validators\NotificationValidator;
use milesherndon\s3backups\validators\RecipientsValidator;

use Craft;
use craft\behaviors\EnvAttributeParserBehavior;
use craft\base\Model;
use craft\helpers\ArrayHelper;

/**
 * @author    MilesHerndon
 * @package   S3Backups
 * @since     1.0.0
 */
class Settings extends Model
{
    // Constants
    // =========================================================================

    /**
     * Cache key to use for caching purposes
     */
    const CACHE_KEY_PREFIX = 'aws.';

    /**
     * Cache duration for access token
     */
    const CACHE_DURATION_SECONDS = 3600;

    // Public Properties
    // =========================================================================

    /**
     * @var string
     */
    public $keyId = '';

    /**
     * @var string
     */
    public $secret = '';

    /**
     * @var string
     */
    public $bucketSelectionMode = 'choose';

    /**
     * @var string
     */
    public $bucket = '';

    /**
     * @var string
     */
    public $region = '';

    /**
     * @var string
     */
    public $subfolder = '';

    /**
     * @var string
     */
    public $totalBackups = 30;

    /**
     * @var boolean
     */
    public $enableNotifications = false;

    /**
     * @var string
     */
    public $emailRecipients = '';

    /**
     * @var string
     */
    public $emailSubject = '';

    /**
     * @var string
     */
    public $fromName = '';

    /**
     * @var string
     */
    public $fromEmail = '';

    /**
     * @var string
     */
    public $replyEmail = '';

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function __construct(array $config = [])
    {
        if (isset($config['manualBucket'])) {
            if (isset($config['bucketSelectionMode']) && $config['bucketSelectionMode'] === 'manual') {
                $config['bucket'] = ArrayHelper::remove($config, 'manualBucket');
                $config['region'] = ArrayHelper::remove($config, 'manualRegion');
            } else {
                unset($config['manualBucket'], $config['manualRegion']);
            }
        }

        parent::__construct($config);
    }

    /**
     * @inheritdoc
     */
    public function behaviors()
    {
        $behaviors = parent::behaviors();
        $behaviors['parser'] = [
            'class' => EnvAttributeParserBehavior::class,
            'attributes' => [
                'keyId',
                'secret',
                'bucket',
                'region',
                'subfolder',
                'totalBackups',
            ],
        ];
        return $behaviors;
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        $rules = parent::rules();
        $rules = [
            [['keyId', 'secret', 'bucket', 'region'], 'required'],
            ['totalBackups', 'integer'],
            [
                ['enableNotifications'],
                NotificationValidator::class,
                'on' => 'notification'
            ],
            [
                ['fromEmail', 'replyEmail'], 'email',
                'on' => 'notification',
                'when' => function($model) {
                    return $model->enableNotifications;
                }
            ],
            [
                ['emailRecipients'],
                RecipientsValidator::class,
                'on' => 'notification',
                'when' => function($model) {
                    return $model->enableNotifications;
                }
            ],
        ];

        return $rules;
    }

    /**
     * Get the list of buckets
     *
     * @param $keyId
     * @param $secret
     * @return array
     * @throws \InvalidArgumentException
     */
    public static function loadBucketList($keyId, $secret)
    {
        return S3Backups::$plugin->s3Service->getBucketList($keyId, $secret);
    }

}
