/**
 * S3 Backups plugin for Craft CMS
 *
 * S3 Backups JS
 *
 * @author    MilesHerndon
 * @copyright Copyright (c) 2019 MilesHerndon
 * @link      https://milesherndon.com
 * @package   S3Backups
 * @since     1.0.0
 */

$(document).ready(function() {

    var $s3AccessKeyIdInput = $('.s3-key-id'),
        $s3SecretAccessKeyInput = $('.s3-secret-key'),
        $s3BucketSelect = $('.s3-bucket-select > select'),
        $s3RefreshBucketsBtn = $('.s3-refresh-buckets'),
        $s3RefreshBucketsSpinner = $s3RefreshBucketsBtn.parent().next().children(),
        $s3Region = $('.s3-region'),
        refreshingS3Buckets = false,
        $enableNotifications = $('#settings-enableNotifications'),
        $enableNotificationInput = $enableNotifications.find('input');

    $s3RefreshBucketsBtn.click(function() {
        if ($s3RefreshBucketsBtn.hasClass('disabled')) return;

        $s3RefreshBucketsBtn.addClass('disabled');
        $s3RefreshBucketsSpinner.removeClass('hidden');

        var data = {
            keyId:  $s3AccessKeyIdInput.val(),
            secret: $s3SecretAccessKeyInput.val()
        };

        Craft.postActionRequest('s3-backups', data, function(response, textStatus) {
            $s3RefreshBucketsBtn.removeClass('disabled');
            $s3RefreshBucketsSpinner.addClass('hidden');

            if (textStatus == 'success') {
                if (response.error) {
                    alert(response.error);
                } else if (response.length > 0) {
                    var currentBucket = $s3BucketSelect.val(),
                        currentBucketStillExists = false;

                    refreshingS3Buckets = true;

                    $s3BucketSelect.prop('readonly', false).empty();

                    for (var i = 0; i < response.length; i++) {
                        if (response[i].bucket == currentBucket) {
                            currentBucketStillExists = true;
                        }

                        $s3BucketSelect.append('<option value="'+response[i].bucket+'" data-url-prefix="'+response[i].urlPrefix+'" data-region="'+response[i].region+'">'+response[i].bucket+'</option>');
                    }

                    if (currentBucketStillExists) {
                        $s3BucketSelect.val(currentBucket);
                    }

                    refreshingS3Buckets = false;

                    if (!currentBucketStillExists) {
                        $s3BucketSelect.trigger('change');
                    }
                }
            }
        });
    });

    $s3BucketSelect.change(function() {
        if (refreshingS3Buckets) return;

        var $selectedOption = $s3BucketSelect.children('option:selected');

        $s3Region.val($selectedOption.data('region'));
    });

    $enableNotifications.click(function() {
        if ($enableNotificationInput.val() == 1) {
            $('.email-settings').removeClass('hidden');
        } else {
            $('.email-settings').addClass('hidden');
        }
    });
});
