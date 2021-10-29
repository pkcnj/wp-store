<?php

class WPM2AWS_ApiRemote
{
    public function __construct()
    {
        if (false === get_option('wpm2aws_licence_key') || '' === get_option('wpm2aws_licence_key')) {
            $msg = "Error! No Licence Key Saved";
            throw new Exception($msg);
        }
    }
    
    public function createLightsailFromZip()
    {
        
        if (false === get_option('wpm2aws-aws-s3-bucket-name') || '' === get_option('wpm2aws-aws-s3-bucket-name')) {
            $msg = "Error! No Bucket Name Saved";
            throw new Exception($msg);
        }

        $bucketRegion = '';
        if (false === get_option('wpm2aws-aws-s3-bucket-region') || '' === get_option('wpm2aws-aws-s3-bucket-region')) {
            if (false === get_option('wpm2aws-aws-region') || '' === get_option('wpm2aws-aws-region')) {
                $msg = "Error! No Bucket Region Saved";
                throw new Exception($msg);
            }
            $bucketRegion = get_option('wpm2aws-aws-region');
        } else {
            $bucketRegion = get_option('wpm2aws-aws-s3-bucket-region');
        }


        if (false === get_option('wpm2aws-aws-lightsail-name') || '' === get_option('wpm2aws-aws-lightsail-name')) {
            $msg = "Error! No Lightsail Name Saved";
            throw new Exception($msg);
        }

        if (false === get_option('wpm2aws-aws-lightsail-region') || '' === get_option('wpm2aws-aws-lightsail-region')) {
            $msg = "Error! No Lightsail Region Saved";
            throw new Exception($msg);
        }

        $iam_key = '';
        if (false !== get_option('wpm2aws-iamid') && '' !== get_option('wpm2aws-iamid')) {
            $iam_key = get_option('wpm2aws-iamid');
        }

        $iam_secret = '';
        if (false !== get_option('wpm2aws-iampw') && '' !== get_option('wpm2aws-iampw')) {
            $iam_secret = get_option('wpm2aws-iampw');
        }
        
        // wp_die(get_option('wpm2aws-customer-type'));
        $requestData = array(
            'wpm2aws-licence-key' => get_option('wpm2aws_licence_key'),
            // 'wpm2aws-licence-email' => get_option('wpm2aws-licence-email'),
            // 'wpm2aws-licence-url' => get_option('wpm2aws-licence-url'),
            'wpm2aws-licence-email' => get_option('wpm2aws_licence_email'),
            'wpm2aws-licence-url' => get_option('wpm2aws_licence_url'),
            'wpm2aws-licence-site' => (!empty(get_site_url()) ? get_site_url() : ''),
            'wpm2aws-licence-type' => strtoupper(get_option('wpm2aws_valid_licence_type')),

            'wpm2aws_iam_key' => $iam_key,
            'wpm2aws_iam_secret' => $iam_secret,
            'wpm2aws_user_name' => get_option('wpm2aws-iam-user'),
            'wpm2aws_user_type' => get_option('wpm2aws-customer-type'),

            'wpm2aws_lightsail_name' => get_option('wpm2aws-aws-lightsail-name'),
            'wpm2aws_lightsail_region' => get_option('wpm2aws-aws-lightsail-region'),
            'wpm2aws_bucket_name' => get_option('wpm2aws-aws-s3-bucket-name'),
            'wpm2aws_bucket_region' => get_option('wpm2aws-aws-s3-bucket-region'),
            // 'key_pair_name' => $this->getKeyPairName($lightsailClient),
            'wpm2aws_is_mulitsite' => is_multisite(),
            
            // 'wpm2aws_launch_mulitsite' => get_option('wpm2aws-ls-launch-multisite'),
            'wpm2aws_launch_mulitsite' => defined('WPM2AWS_LAUNCH_AS_MULTI_SITE') ? WPM2AWS_LAUNCH_AS_MULTI_SITE : 0,
            'wpm2aws_db_prefx' => defined('WPM2AWS_ADJUST_PREFIX') ? WPM2AWS_ADJUST_PREFIX : 'wp_',
        );
        // wp_die(print_r($requestData));
        
        $response = wp_remote_post(
            // 'http://localhost/wpm2aws-migration-api/public/api/migration/lightsail/launch',
            // 'http://54.74.229.238/api/migration/lightsail/launch',
            'https://wponaws.migration.seahorse-data.com/api/migration/lightsail/launch',
            array(

            'method' => 'POST',
            'timeout' => 45,
            'redirection' => 10,
            'httpversion' => '1.0',
            'blocking' => true,
            'headers' => array(
                'Cache-Control' => 'no-cache',
            ),
            'body' => array('data' => $requestData),
            'cookies' => array(),
            )
        );
        // die(print_r($response));
        $responseCode = wp_remote_retrieve_response_code($response);
        // wp_die(print_r(json_decode($response['body'], true)));

        if ('200' !== $responseCode && 200 !== $responseCode) {
            $errorMessage = json_decode($response['body'], true)['message'];
            // wp_die(print_r(json_decode($response['body'], true)));
            if ((int)$responseCode >= 400 && (int)$responseCode < 500) {
                throw new Exception('Error! This Action Can Not Be Completed. Unauthorised Access<br>' . $errorMessage);
            } elseif ((int)$responseCode >= 500 && (int)$responseCode < 600) {
                throw new Exception('Error! This Action Can Not Be Completed. Internal Server Error.<br>' . $errorMessage);
            } else {
                throw new Exception('Unauthorised Access<br>' . $errorMessage);
            }
        }
        
        $responseData = json_decode($response['body'], true);

        // wp_die(print_r($responseData));
        $launchResult = array(
            'name' => $responseData['launch-name'],
            'region' => $responseData['launch-region'],
            'publicIp' => $responseData['launch-ip'],
            'accessControl' => $responseData['launch-access'],
            'details' => $responseData['launch-details'],
            'key-pair-details' => $responseData['launch-key-pair'],
        );

        return $launchResult;
    }
}
