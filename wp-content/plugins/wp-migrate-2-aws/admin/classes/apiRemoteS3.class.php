<?php

class WPM2AWS_ApiRemoteS3
{
    private $requestData;
    private $bucketName;
    private $bucketRegion;
    private $iamKey;
    private $iamSecret;
    
    public function __construct()
    {       
        $this->bucketName = '';
        $this->bucketRegion = '';

        if (false === get_option('wpm2aws_licence_key') || '' === get_option('wpm2aws_licence_key')) {
            $this->abortWithErrorMessage("Error! No Licence Key Saved");
        }

        if (false === get_option('wpm2aws-iamid')) {
            $this->abortWithErrorMessage("No Access Key ID Saved");
        }

        if (false === get_option('wpm2aws-iampw')) {
            $this->abortWithErrorMessage("No Secret Access Key Saved");
        }

        $this->iamKey = get_option('wpm2aws-iamid');
        $this->iamSecret = get_option('wpm2aws-iampw');

        $this->initializeBucketRegion();
    }
    
    /**
     * Determine if the given bucket name exists in the AWS Account
     */
    public function checkBucketExists()
    {
        $this->initializeBucketName();

        $this->setRequestData();
        
        $response = wp_remote_post(
            // 'http://localhost/wpm2aws-migration-api/public/api/migration/uploads/checkstore',
            'https://wponaws.migration.seahorse-data.com/api/migration/uploads/checkstore',
            array(
                'method' => 'POST',
                'timeout' => 45,
                'redirection' => 10,
                'httpversion' => '1.0',
                'blocking' => true,
                'headers' => array(
                    'Cache-Control' => 'no-cache',
                ),
                'body' => array('data' => $this->requestData),
                'cookies' => array(),
            )
        );

        $responseCode = wp_remote_retrieve_response_code($response);

        if ('200' !== $responseCode && 200 !== $responseCode) {
            $errorMessage = wp_remote_retrieve_response_message( $response );

            if ((int)$responseCode >= 400 && (int)$responseCode < 500) {
                throw new \Exception('Error! This Action Can Not Be Completed. Unauthorised Access<br>' . $errorMessage);
            } elseif ((int)$responseCode >= 500 && (int)$responseCode < 600) {
                throw new \Exception('Error! This Action Can Not Be Completed. Internal Server Error<br>' . $errorMessage);
            } else {
                throw new \Exception('Unauthorised Access<br>' . $errorMessage);
            }
        }
        
        return $response['body'];
    }

    /**
     * Get a list of all buckets in the AWS Account
     */
    public function getBucketList()
    {        
        $this->setRequestData();
        
        $response = wp_remote_post(
            // 'http://localhost/wpm2aws-migration-api/public/api/migration/uploads/getStore',
            'https://wponaws.migration.seahorse-data.com/api/migration/uploads/getStore',
            array(
                'method' => 'POST',
                'timeout' => 45,
                'redirection' => 10,
                'httpversion' => '1.0',
                'blocking' => true,
                'headers' => array(
                    'Cache-Control' => 'no-cache',
                ),
                'body' => array('data' => $this->requestData),
                'cookies' => array(),
            )
        );

        $responseCode = wp_remote_retrieve_response_code($response);

        if ('200' !== $responseCode && 200 !== $responseCode) {
            $errorMessage = wp_remote_retrieve_response_message($response);

            if ((int)$responseCode >= 400 && (int)$responseCode < 500) {
                $this->abortWithErrorMessage('Error! This Action Can Not Be Completed. Unauthorised Access<br>' . $errorMessage);
            } elseif ((int)$responseCode >= 500 && (int)$responseCode < 600) {
                $this->abortWithErrorMessage('Error! This Action Can Not Be Completed. Internal Server Error.<br>' . $errorMessage);
            } else {
                $this->abortWithErrorMessage('Unauthorised Access<br>' . $errorMessage);
            }
        }

        $responseData = json_decode( wp_remote_retrieve_body( $response ), true );

        if (array_key_exists('bucket-names', $responseData) === false) {
            return [];
        }
        
        return $responseData['bucket-names'];
    }

    /**
     * Creates a new Bucket
     * Sets a lifecycle for Trial User Buckets
     */
    public function createBucket($restricted)
    {        
        $this->initializeBucketName();
        
        $this->setRequestData();
        
        $response = wp_remote_post(
            // 'http://localhost/wpm2aws-migration-api/public/api/migration/uploads/makeStore',
            'https://wponaws.migration.seahorse-data.com/api/migration/uploads/makeStore',
            array(
                'method' => 'POST',
                'timeout' => 45,
                'redirection' => 10,
                'httpversion' => '1.0',
                'blocking' => true,
                'headers' => array(
                    'Cache-Control' => 'no-cache',
                ),
                'body' => array('data' => $this->requestData),
                'cookies' => array(),
            )
        );

        $responseCode = wp_remote_retrieve_response_code($response);

        if ('200' !== $responseCode && 200 !== $responseCode) {
            $errorMessage = wp_remote_retrieve_response_message($response);

            if ((int)$responseCode >= 400 && (int)$responseCode < 500) {
                if ($errorMessage === 'BucketAlreadyOwnedByYou') {
                    if (get_option('wpm2aws-customer-type') === 'managed') {
                        return $this->bucketName;
                    }
                    $this->abortWithErrorMessage('Error! This Action Can Not Be Completed. Bucket Already Owned By You<br>' . $errorMessage);
                }
                $this->abortWithErrorMessage('Error! This Action Can Not Be Completed. Unauthorised Access<br>' . $errorMessage);
            } elseif ((int)$responseCode >= 500 && (int)$responseCode < 600) {
                $this->abortWithErrorMessage('Error! This Action Can Not Be Completed. Internal Server Error.<br>' . $errorMessage);
            } else {
                $this->abortWithErrorMessage('Unauthorised Access<br>' . $errorMessage);
            }
        }

        $responseData = json_decode( wp_remote_retrieve_body( $response ), true );

        if (array_key_exists('bucket', $responseData) === false) {
            return false;
        }
        
        return $responseData['bucket'];
    }

    /**
     * Set the Bucket Region Value for an action
     */
    private function initializeBucketRegion()
    {
        if (false === get_option('wpm2aws-aws-s3-bucket-region') || '' === get_option('wpm2aws-aws-s3-bucket-region')) {
            if (false === get_option('wpm2aws-aws-region') || '' === get_option('wpm2aws-aws-region')) {
                $this->abortWithWarningMessage("Warning! Cannot access S3 Buckets - No Bucket Region Saved");
            }
            $this->bucketRegion = get_option('wpm2aws-aws-region');
        } else {
            $this->bucketRegion = get_option('wpm2aws-aws-s3-bucket-region');
        }
    }

    /**
     * Set the Bucket Name Value for an action
     */
    private function initializeBucketName()
    {
        if (false === get_option('wpm2aws-aws-s3-bucket-name') || '' === get_option('wpm2aws-aws-s3-bucket-name')) {
            if (false === get_option('wpm2aws-aws-s3-default-bucket-name') || '' === get_option('wpm2aws-aws-s3-default-bucket-name')) {
                $this->abortWithErrorMessage("Error! No Bucket Name Saved");
            }
            $this->bucketName = get_option('wpm2aws-aws-s3-default-bucket-name');
        } else {
            $this->bucketName = get_option('wpm2aws-aws-s3-bucket-name');
        }
    }

    private function setRequestData()
    {
        $this->requestData = array(
            'wpm2aws-licence-key' => get_option('wpm2aws_licence_key'),
            'wpm2aws-licence-email' => get_option('wpm2aws_licence_email'),
            'wpm2aws-licence-url' => get_option('wpm2aws_licence_url'),
            'wpm2aws-licence-site' => (!empty(get_site_url()) ? get_site_url() : ''),
            'wpm2aws-licence-type' => strtoupper(get_option('wpm2aws_valid_licence_type')),
            'wpm2aws_iam_key' => $this->iamKey,
            'wpm2aws_iam_secret' => $this->iamSecret,
            'wpm2aws_user_name' => get_option('wpm2aws-iam-user'),
            'wpm2aws_user_type' => get_option('wpm2aws-customer-type'),
            'wpm2aws_bucket_name' => $this->bucketName,
            'wpm2aws_aws_region' => $this->bucketRegion,
        );
    }
    
    private function abortWithErrorMessage($message)
    {
        set_transient(
            'wpm2aws_admin_error_notice_' . get_current_user_id(),
            __($message, 'migrate-2-aws')
        );
        exit(wp_redirect(admin_url('/admin.php?page=wpm2aws')));
    }

    private function abortWithWarningMessage($message)
    {
        set_transient(
            'wpm2aws_admin_warning_notice_' . get_current_user_id(),
            __($message, 'migrate-2-aws')
        );
        exit(wp_redirect(admin_url('/admin.php?page=wpm2aws')));
    }
}
