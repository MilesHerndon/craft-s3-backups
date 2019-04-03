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

use Aws\Credentials\Credentials;
use Aws\Handler\GuzzleV6\GuzzleHandler;
use Aws\Rekognition\RekognitionClient;
use Aws\S3\Exception\S3Exception;
use Aws\S3\Exception\MultipartUploadException;
use Aws\S3\S3Client;
use Aws\S3\MultipartUploader;
use Aws\Sts\StsClient;
use Craft;
use craft\base\Component;
use craft\helpers\FileHelper;
use craft\services\Path;
use yii\base\Exception;

/**
 * @author    MilesHerndon
 * @package   S3Backups
 * @since     1.0.0
 */
class S3Service extends Component
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

    // Private Properties
    // =========================================================================

    /**
     * @var string AWS key
     */
    private $key = '';

    /**
     * @var string AWS secret
     */
    private $secret = '';

    /**
    * @var string Bucket
    */
    private $bucket = '';

    /**
     * @var string Region
     */
    private $region = '';

    /**
     * @var string Subfolder of bucket
     */
    private $subfolder = '';

    // Public Methods
    // =========================================================================

    public function __construct()
    {
        $settings = S3Backups::getInstance()->getSettings();

        $this->key = Craft::parseEnv($settings->keyId);
        $this->secret = Craft::parseEnv($settings->secret);
        $this->bucket = Craft::parseEnv($settings->bucket);
        $this->region = Craft::parseEnv($settings->region);
        $this->subfolder = Craft::parseEnv($settings->subfolder);
    }

    /**
     * Upload object on S3.
     *
     * @param $file
     * @throws S3Exception
     */
    public function uploadToS3($file)
    {
        $config = self::_buildConfigArray($this->key, $this->secret, $this->region);
        $client = static::client($config);

        try {
            $result = $client->putObject([
                'Bucket' => $this->bucket,
                'Key'    => $this->subfolder . '/' . basename($file),
                'SourceFile' => $file
            ]);
            return $result;
        } catch (S3Exception $e) {
            return $e;
        }
    }

    /**
     * Upload object to S3 through MultiPartUpload
     *
     * @param $file
     * @throws MultipartUploadException
     */
    public function uploadToS3Multipart($file)
    {
        $config = self::_buildConfigArray($this->key, $this->secret, $this->region);
        $client = static::client($config);

        $uploader = new MultipartUploader($client, $file, [
            'Bucket' => $this->bucket,
            'Key' => $this->subfolder . '/' . basename($file),
        ]);

        try {
            $result = $uploader->upload();
            return $result;
        } catch (MultipartUploadException $e) {
            return $e;
        }
    }

    /**
     * Delete multiple object from S3.
     *
     * @param $objects
     * @throws S3Exception
     */
    public function deleteMultipleObjects($objects)
    {
        $config = self::_buildConfigArray($this->key, $this->secret, $this->region);
        $client = static::client($config);

        try {
            $client->deleteObjects([
                'Bucket'  => $this->bucket,
                'Delete' => [
                    'Objects' => array_map(function ($object) {
                        return ['Key' => $this->subfolder . '/' . $object->filename];
                    }, $objects)
                ],
            ]);
        } catch (S3Exception $e) {
            return $e;
        }
    }

    /**
     * Delete a single object from S3.
     *
     * @param $object
     * @throws S3Exception
     */
    public function deleteObject($object)
    {
        $config = self::_buildConfigArray($this->key, $this->secret, $this->region);
        $client = static::client($config);

        try {
            $client->deleteObject([
                'Bucket'  => $this->bucket,
                'Key' => $this->subfolder . '/' . $object->filename
            ]);
        } catch (S3Exception $e) {
            return $e;
        }
    }

    /**
     * Get the bucket list using the specified credentials.
     *
     * @param $keyId
     * @param $secret
     * @return array
     * @throws \InvalidArgumentException
     */
    public static function getBucketList($keyId, $secret)
    {
        $config = self::_buildConfigArray($keyId, $secret, 'us-east-1');
        $client = static::client($config);
        $objects = $client->listBuckets();

        if (empty($objects['Buckets'])) {
            return [];
        }

        $buckets = $objects['Buckets'];
        $bucketList = [];

        foreach ($buckets as $bucket) {
            try {
                $region = $client->determineBucketRegion($bucket['Name']);
            } catch (S3Exception $exception) {

                // If a bucket cannot be accessed by the current policy, move along:
                // https://github.com/craftcms/aws-s3/pull/29#issuecomment-468193410
                continue;
            }

            $bucketList[] = [
                'bucket' => $bucket['Name'],
                'urlPrefix' => 'https://s3.'.$region.'.amazonaws.com/'.$bucket['Name'].'/',
                'region' => $region
            ];
        }

        return $bucketList;
    }

    /**
     * Getter for bucket name
     *
     * @return string
     */
    public function getBucketName()
    {
        return $this->bucket;
    }

    // Protected Methods
    // =========================================================================

    /**
     * Get the Amazon S3 client.
     *
     * @param $config
     * @return S3Client
     */
    protected static function client(array $config = []): S3Client
    {
        return new S3Client($config);
    }

    // Private Methods
    // =========================================================================

    /**
     * Build the config array based on a keyID and secret
     *
     * @param $keyId
     * @param $secret
     * @param $region
     * @return array
     */
    private static function _buildConfigArray($keyId = null, $secret = null, $region = null)
    {
        $config = [
            'region' => $region,
            'version' => 'latest'
        ];

        if (empty($keyId) || empty($secret)) {
            // Assume we're running on EC2 and we have an IAM role assigned. Kick back and relax.
        } else {
            $tokenKey = static::CACHE_KEY_PREFIX . md5($keyId . $secret);
            $credentials = new Credentials($keyId, $secret);

            if (Craft::$app->cache->exists($tokenKey)) {
                $cached = Craft::$app->cache->get($tokenKey);
                $credentials->unserialize($cached);
            } else {
                $config['credentials'] = $credentials;
                $stsClient = new StsClient($config);
                $result = $stsClient->getSessionToken(['DurationSeconds' => static::CACHE_DURATION_SECONDS]);
                $credentials = $stsClient->createCredentials($result);
                Craft::$app->cache->set($tokenKey, $credentials->serialize(), static::CACHE_DURATION_SECONDS);
            }

            // TODO Add support for different credential supply methods
            $config['credentials'] = $credentials;
        }

        $client = Craft::createGuzzleClient();
        $config['http_handler'] = new GuzzleHandler($client);

        return $config;
    }
}
