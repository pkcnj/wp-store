<?php
// https://github.com/coderatio/simple-backup

// patch applied
// src/SimpleBackup.php:442
// From: $this->tables_to_include = array_filter($tables, static function($table) {
// To: $this->tables_to_include = array_filter($tables, function($table) {
    
use Coderatio\SimpleBackup\SimpleBackup;
use Coderatio\SimpleBackup\Exceptions\NoTablesFoundException;
use Coderatio\SimpleBackup\Exceptions\RuntimeException;

class WPM2AWS_DbDownloader
{
    /**
     * Set Constructor.
     */
    public function __construct()
    {
        add_action('admin_init', array( $this, 'init'));
    }


    /** Run applicable method based on DB Size */
    public function init()
    {
        wpm2awsLogRAction('wpm2aws-run-db-download', 'DB Download Actioned');

        if (!defined('DB_NAME')) {
            wpm2awsLogRAction('wpm2aws-run-db-download', 'Error! DB Name Not Defined');
            set_transient('wpm2aws_admin_error_notice_' . get_current_user_id(), __('Error!<br><br>Database Name not set in wp-config file.', 'migrate-2-aws'));
            exit(wp_safe_redirect(admin_url('/admin.php?page=wpm2aws')));
        }

        if (!defined('DB_USER')) {
            wpm2awsLogRAction('wpm2aws-run-db-download', 'Error! DB User Not Defined');
            set_transient('wpm2aws_admin_error_notice_' . get_current_user_id(), __('Error!<br><br>Database User not set in wp-config file.', 'migrate-2-aws'));
            exit(wp_safe_redirect(admin_url('/admin.php?page=wpm2aws')));
        }

        if (!defined('DB_PASSWORD')) {
            wpm2awsLogRAction('wpm2aws-run-db-download', 'Error! DB Password Not Defined');
            set_transient('wpm2aws_admin_error_notice_' . get_current_user_id(), __('Error!<br><br>Database Password not set in wp-config file.', 'migrate-2-aws'));
            exit(wp_safe_redirect(admin_url('/admin.php?page=wpm2aws')));
        }

        if (!defined('DB_HOST')) {
            wpm2awsLogRAction('wpm2aws-run-db-download', 'Error! DB Host Not Defined');
            set_transient('wpm2aws_admin_error_notice_' . get_current_user_id(), __('Error!<br><br>Database Host not set in wp-config file.', 'migrate-2-aws'));
            exit(wp_safe_redirect(admin_url('/admin.php?page=wpm2aws')));
        }
        
        // Get size of DB
        try {
            $dbSize = $this->getDatabaseSize(DB_NAME);
            wpm2awsLogRAction('wpm2aws-run-db-download', 'DB Size: ' . $dbSize);
        } catch (Throwable $e) {
            wpm2awsLogRAction('wpm2aws-run-db-download', 'Error! DB Get Size Throwable');
            set_transient('wpm2aws_admin_error_notice_' . get_current_user_id(), __('Error!<br><br>Prepare Database Error (Ini.1).', 'migrate-2-aws'));
            exit(wp_safe_redirect(admin_url('/admin.php?page=wpm2aws')));
        } catch (NoTablesFoundException $e) {
            wpm2awsLogRAction('wpm2aws-run-db-download', 'Error! DB Get Size NoTablesFoundException');
            set_transient('wpm2aws_admin_error_notice_' . get_current_user_id(), __('Error!<br><br>Prepare Database Error (Ini.2).', 'migrate-2-aws'));
            exit(wp_safe_redirect(admin_url('/admin.php?page=wpm2aws')));
        } catch (RuntimeException $e) {
            wpm2awsLogRAction('wpm2aws-run-db-download', 'Error! DB Get Size RuntimeException');
            set_transient('wpm2aws_admin_error_notice_' . get_current_user_id(), __('Error!<br><br>Prepare Database Error (Ini.3).', 'migrate-2-aws'));
            exit(wp_safe_redirect(admin_url('/admin.php?page=wpm2aws')));
        } catch (Exception $e) {
            wpm2awsLogRAction('wpm2aws-run-db-download', 'Error! DB Get Size Exception');
            set_transient('wpm2aws_admin_error_notice_' . get_current_user_id(), __('Error!<br><br>Prepare Database Error (Ini.4).', 'migrate-2-aws'));
            exit(wp_safe_redirect(admin_url('/admin.php?page=wpm2aws')));
        }
        

        if ($dbSize > WPM2AWS_MAX_DB_EXPORT) {
            try {
                $dbTables = $this->listDbTables();
                wpm2awsLogRAction('wpm2aws-run-db-download', 'DB List Tables: Success');
            } catch (Throwable $e) {
                wpm2awsLogRAction('wpm2aws-run-db-download', 'Error! DB List Tables Throwable');
                set_transient('wpm2aws_admin_error_notice_' . get_current_user_id(), __('Error!<br><br>Prepare Database Error (Ini.5).', 'migrate-2-aws'));
                exit(wp_safe_redirect(admin_url('/admin.php?page=wpm2aws')));
            } catch (NoTablesFoundException $e) {
                wpm2awsLogRAction('wpm2aws-run-db-download', 'Error! DB List Tables NoTablesFoundException');
                set_transient('wpm2aws_admin_error_notice_' . get_current_user_id(), __('Error!<br><br>Prepare Database Error (Ini.6).', 'migrate-2-aws'));
                exit(wp_safe_redirect(admin_url('/admin.php?page=wpm2aws')));
            } catch (RuntimeException $e) {
                wpm2awsLogRAction('wpm2aws-run-db-download', 'Error! DB List Tables RuntimeException');
                set_transient('wpm2aws_admin_error_notice_' . get_current_user_id(), __('Error!<br><br>Prepare Database Error (Ini.7).', 'migrate-2-aws'));
                exit(wp_safe_redirect(admin_url('/admin.php?page=wpm2aws')));
            } catch (Exception $e) {
                wpm2awsLogRAction('wpm2aws-run-db-download', 'Error! DB List Tables Exception');
                set_transient('wpm2aws_admin_error_notice_' . get_current_user_id(), __('Error!<br><br>Prepare Database Error (Ini.8).', 'migrate-2-aws'));
                exit(wp_safe_redirect(admin_url('/admin.php?page=wpm2aws')));
            }
            
            if (empty($dbTables)) {
                wpm2awsLogRAction('wpm2aws-run-db-download', 'Error! DB List Tables Empty List');
                set_transient('wpm2aws_admin_error_notice_' . get_current_user_id(), __('Error!<br><br>Prepare Database Error (Ini.9).', 'migrate-2-aws'));
                exit(wp_safe_redirect(admin_url('/admin.php?page=wpm2aws')));
            }

            // Run Table Export on each
            $response = array_filter($dbTables, array($this, 'exportDbTable'));
            
            if (empty($response)) {
                wpm2awsLogRAction('wpm2aws-run-db-download', 'Error! DB Download Complete - with Errors: No Tables Processed');
                set_transient('wpm2aws_admin_error_notice_' . get_current_user_id(), __('Error!<br><br>Prepare Database Complete - No Tables Processed.', 'migrate-2-aws'));
            } elseif (count($response) === count($dbTables)) {
                wpm2awsLogRAction('wpm2aws-run-db-download', 'Success! DB Download Complete - Table Count: ' . count($dbTables));
                set_transient('wpm2aws_admin_success_notice_' . get_current_user_id(), __('Success!<br><br>Prepare Database Complete.', 'migrate-2-aws'));
                wpm2awsAddUpdateOptions('wpm2aws_current_active_step', 3);
            } else {
                wpm2awsLogRAction('wpm2aws-run-db-download', 'Error! DB Download Complete - with Errors: All Tables may not have been processed');
                set_transient('wpm2aws_admin_warning_notice_' . get_current_user_id(), __('Warning!<br><br>Prepare Database Complete - All Tables may not have been processed.', 'migrate-2-aws'));
            }

            wpm2awsAddUpdateOptions('wpm2aws_download_db_complete', 'success');

            exit(wp_safe_redirect(admin_url('/admin.php?page=wpm2aws')));
        } else {
            $this->exportFullDb();
        }
    }

    public function exportFullDb()
    {
        // Set the database to backup
        try {
            $simpleBackup = SimpleBackup::setDatabase(
                [
                    DB_NAME,
                    DB_USER,
                    DB_PASSWORD,
                    DB_HOST
                ]
            )->storeAfterExportTo(
                WPM2AWS_PLUGIN_DIR . '/libraries/db',
                'db.sql'
            );
        } catch (Throwable $e) {
            wpm2awsLogAction('Error! DB Download Failed: ' . $e->getMessage());
            wpm2awsLogRAction('wpm2aws-run-db-download', 'wpm2aws_download_db_complete: Fail');
            wpm2awsAddUpdateOptions('wpm2aws_download_db_complete', 'error');
            set_transient('wpm2aws_admin_error_notice_' . get_current_user_id(), __('Error!<br><br>Prepare Database Failed. (1)<br>Fatal Error', 'migrate-2-aws'));
            exit(wp_safe_redirect(admin_url('/admin.php?page=wpm2aws')));
        } catch (NoTablesFoundException $e) {
            wpm2awsLogAction('Error! DB Download Failed: ' . $e->getMessage());
            wpm2awsLogRAction('wpm2aws-run-db-download', 'wpm2aws_download_db_complete: Fail');
            wpm2awsAddUpdateOptions('wpm2aws_download_db_complete', 'error');
            set_transient('wpm2aws_admin_error_notice_' . get_current_user_id(), __('Error!<br><br>Prepare Database Failed. (2)<br>Fatal Error', 'migrate-2-aws'));
            exit(wp_safe_redirect(admin_url('/admin.php?page=wpm2aws')));
        } catch (RuntimeException $e) {
            wpm2awsLogAction('Error! DB Download Failed: ' . $e->getMessage());
            wpm2awsLogRAction('wpm2aws-run-db-download', 'wpm2aws_download_db_complete: Fail');
            wpm2awsAddUpdateOptions('wpm2aws_download_db_complete', 'error');
            set_transient('wpm2aws_admin_error_notice_' . get_current_user_id(), __('Error!<br><br>Prepare Database Failed. (3)<br>Fatal Error', 'migrate-2-aws'));
            exit(wp_safe_redirect(admin_url('/admin.php?page=wpm2aws')));
        } catch (Exception $e) {
            wpm2awsLogAction('Error! DB Download Failed: ' . $e->getMessage());
            wpm2awsLogRAction('wpm2aws-run-db-download', 'wpm2aws_download_db_complete: Fail');
            wpm2awsAddUpdateOptions('wpm2aws_download_db_complete', 'error');
            set_transient('wpm2aws_admin_error_notice_' . get_current_user_id(), __('Error!<br><br>Prepare Database Failed. (4)<br>Fatal Error', 'migrate-2-aws'));
            exit(wp_safe_redirect(admin_url('/admin.php?page=wpm2aws')));
        }
   
        try {
            $response = $simpleBackup->getResponse();
            wpm2awsLogRAction('wpm2aws-run-db-download', 'Simple Backup Response (fdb): ' . json_encode($response));
        } catch (Throwable $e) {
            wpm2awsLogRAction('wpm2aws-run-db-download', 'Simple Backup Response Error: ' . $e->getMessage());
            $response = (object) array(
                'status' => false,
                'message' => 'Could Not Determine Response',
            );
        } catch (Exception $e) {
            wpm2awsLogRAction('wpm2aws-run-db-download', 'Simple Backup Response Error: ' . $e->getMessage());
            $response = (object) array(
                'status' => false,
                'message' => 'Could Not Determine Response',
            );
        }

        // Throw error if Response is not as expected
        if ('object' !== gettype($response)) {
            wpm2awsLogAction('Error! DB Download Failed - Bad Response: Not Object (fdb)' . json_encode($response));
            wpm2awsLogRAction('wpm2aws-run-db-download', 'Error! DB Download Failed - Bad Response: Not Object (fdb)' . json_encode($response));
            wpm2awsAddUpdateOptions('wpm2aws_download_db_complete', 'error');
            set_transient('wpm2aws_admin_error_notice_' . get_current_user_id(), __('Error!<br><br>Prepare Database Failed. <br>Error Ref: Bad Response - No Object (fdb)', 'migrate-2-aws'));
            exit(wp_safe_redirect(admin_url('/admin.php?page=wpm2aws')));
        }

        // Throw Error if we cannot determine the Response Status
        if (property_exists($response, 'status') === false) {
            $responseStatus = 'Status Not Found';

            // Get or Create Message
            if (property_exists($response, 'message') === true) {
                $responseMessage = $response->message;
            }
            if (property_exists($response, 'message') === false || empty($responseMessage)) {
                $responseMessage = 'Could Not Determine Response Message';
            }
            
            wpm2awsLogAction('Error! DB Download Failed - Bad Response: (fdb) Status=' . $responseStatus . ' | Message='  . $responseMessage . ' | Response='  . json_encode($response));
            wpm2awsLogRAction('wpm2aws-run-db-download', 'Error! DB Download Failed - Bad Response: (fdb) Status=' . $responseStatus . ' | Message='  . $responseMessage . ' | Response='  . json_encode($response));
            wpm2awsAddUpdateOptions('wpm2aws_download_db_complete', 'error');
            set_transient('wpm2aws_admin_error_notice_' . get_current_user_id(), __('Error!<br><br>Prepare Database Failed. <br>Error Ref: ' . $responseMessage, 'migrate-2-aws'));
            exit(wp_safe_redirect(admin_url('/admin.php?page=wpm2aws')));
        }

        // Return Success or Throw Error if Status is not TRUE
        if ($response->status === true) {
            wpm2awsAddUpdateOptions('wpm2aws_download_db_complete', 'success');
            wpm2awsLogRAction('wpm2aws-run-db-download', 'wpm2aws_download_db_complete (fdb): Success');
            set_transient('wpm2aws_admin_success_notice_' . get_current_user_id(), __('Success!<br><br>Prepare Database Complete.', 'migrate-2-aws'));
            wpm2awsAddUpdateOptions('wpm2aws_current_active_step', 3);
        } else {
            // Get or Create Message
            if (property_exists($response, 'message') === true) {
                $responseMessage = $response->message;
            }
            if (property_exists($response, 'message') === false || empty($responseMessage)) {
                $responseMessage = 'Could Not Determine Response Message';
            }

            wpm2awsLogAction('Error! DB Download Failed - Bad Response: (fdb) Status=' . $response->status . ' | Message='  . $responseMessage . ' | Response='  . json_encode($response));
            wpm2awsLogRAction('wpm2aws-run-db-download', 'wpm2aws_download_db_complete (fdb): Error: Status=' . $response->status . ' |  Message='  . $responseMessage . ' | Response='  . json_encode($response));
            wpm2awsAddUpdateOptions('wpm2aws_download_db_complete', 'error');
            set_transient('wpm2aws_admin_error_notice_' . get_current_user_id(), __('Error!<br><br>Prepare Database Failed. <br>Error Ref: ' . $responseMessage, 'migrate-2-aws'));
        }

        exit(wp_safe_redirect(admin_url('/admin.php?page=wpm2aws')));
    }

    public function exportDbTable($tableName)
    {
        // wpm2awsLogRAction('wpm2aws-run-db-download', 'exportDbTable: Table : ' . json_encode($tableName));
        
        if (is_array($tableName)) {
            if (isset($tableName[0])) {
                $exportTable = $tableName[0];
            } else {
                $passedTable = json_encode($tableName);
                wpm2awsLogRAction('wpm2aws-run-db-download', 'Error! DB Download Failed: Bad Parameter: Table Name  => ' . $passedTable);
                wpm2awsLogAction('Error! DB Download Failed: Bad Parameter: Table Name  => ' . $passedTable);
                wpm2awsAddUpdateOptions('wpm2aws_download_db_complete', 'error');
                set_transient('wpm2aws_admin_error_notice_' . get_current_user_id(), __('Error!<br><br>Prepare Database Failed. <br>Fatal Error', 'migrate-2-aws'));
                exit(wp_safe_redirect(admin_url('/admin.php?page=wpm2aws')));
            }
        } else {
            $exportTable = $tableName;
        }
        
        $exportTable = array($exportTable);
        
        
        // Dev Logging
        // $exportTableString = json_encode($exportTable);
        // wpm2awsLogAction('Export Table: Table Name  => ' . $exportTableString);
        
        $compressionLevel = 'Gzip';
        try {
            $testGzipInstalled = gzencode('Test Gzip');
        } catch (Exception $e) {
            wpm2awsLogRAction('Warning! Gzip Error, defaultign to no-compression: ' . $e->getMessage());
            $compressionLevel = 'None';
        } catch (Throwable $e) {
            wpm2awsLogRAction('Error! Gzip Error, defaultign to no-compression: ' . $e->getMessage());
            $compressionLevel = 'None';
        }
        // $compressionLevel = 'None';
        // wpm2awsLogRAction('wpm2aws-run-db-download', 'Compression Level  => ' . $compressionLevel);
        // Set the database to backup
        try {
            // $tableName = 'wp_users';
            $simpleBackup = SimpleBackup::start()
                ->setDbHost(DB_HOST)
                ->setDbName(DB_NAME)
                ->setDbUser(DB_USER)
                ->setDbPassword(DB_PASSWORD)
                ->setCompressionLevel($compressionLevel)
                ->includeOnly($exportTable)
                ->then()->storeAfterExportTo(
                    WPM2AWS_DB_TABLES_EXPORT_PATH,
                    $exportTable[0] . '.sql'
                );
                

            // $simpleBackup = SimpleBackup::setDatabase(
            //     [
            //         DB_NAME,
            //         DB_USER,
            //         DB_PASSWORD,
            //         DB_HOST
            //     ]
            // );
            // $simpleBackup->includeOnly($exportTable)
            // ->then()->storeAfterExportTo(
            //     WPM2AWS_PLUGIN_DIR . '/libraries/db/tables',
            //     $exportTable[0] . '.sql'
            // );
        } catch (Throwable $e) {
            wpm2awsLogRAction('Error! DB Download Failed: ' . $e->getMessage() . ' | Table: ' . $exportTable[0] . '.sql');
            wpm2awsAddUpdateOptions('wpm2aws_download_db_complete', 'error');
            set_transient('wpm2aws_admin_error_notice_' . get_current_user_id(), __('Error!<br><br>Prepare Database Failed (EDT.1). <br>Fatal Error', 'migrate-2-aws'));
            exit(wp_safe_redirect(admin_url('/admin.php?page=wpm2aws')));
        } catch (NoTablesFoundException $e) {
            wpm2awsLogRAction('Error! DB Download Failed: ' . $e->getMessage() . ' | Table: ' . $exportTable[0] . '.sql');
            wpm2awsAddUpdateOptions('wpm2aws_download_db_complete', 'error');
            set_transient('wpm2aws_admin_error_notice_' . get_current_user_id(), __('Error!<br><br>Prepare Database Failed (EDT.2). <br>Fatal Error', 'migrate-2-aws'));
            exit(wp_safe_redirect(admin_url('/admin.php?page=wpm2aws')));
        } catch (RuntimeException $e) {
            wpm2awsLogRAction('Error! DB Download Failed: ' . $e->getMessage() . ' | Table: ' . $exportTable[0] . '.sql');
            wpm2awsAddUpdateOptions('wpm2aws_download_db_complete', 'error');
            set_transient('wpm2aws_admin_error_notice_' . get_current_user_id(), __('Error!<br><br>Prepare Database Failed (EDT.3). <br>Fatal Error', 'migrate-2-aws'));
            exit(wp_safe_redirect(admin_url('/admin.php?page=wpm2aws')));
        } catch (Exception $e) {
            wpm2awsLogRAction('Error! DB Download Failed: ' . $e->getMessage() . ' | Table: ' . $exportTable[0] . '.sql');
            wpm2awsAddUpdateOptions('wpm2aws_download_db_complete', 'error');
            set_transient('wpm2aws_admin_error_notice_' . get_current_user_id(), __('Error!<br><br>Prepare Database Failed (EDT.4). <br>Fatal Error', 'migrate-2-aws'));
            exit(wp_safe_redirect(admin_url('/admin.php?page=wpm2aws')));
        }


        try {
            $response = $simpleBackup->getResponse();
            // wpm2awsLogRAction('wpm2aws-run-db-download', 'Simple Backup Response: ' . json_encode($response));
        } catch (Throwable $e) {
            wpm2awsLogRAction('wpm2aws-run-db-download', 'Simple Backup Response Error: ' . $e->getMessage());
            $response = (object) array(
                'status' => false,
                'message' => 'Could Not Determine Response',
            );
        } catch (Exception $e) {
            wpm2awsLogRAction('wpm2aws-run-db-download', 'Simple Backup Response Error: ' . $e->getMessage());
            $response = (object) array(
                'status' => false,
                'message' => 'Could Not Determine Response',
            );
        }

        // Throw error if Response is not as expected
        if ('object' !== gettype($response)) {
            wpm2awsLogAction('Error! DB Download Failed - Bad Response: Not Object ' . json_encode($response));
            wpm2awsLogRAction('wpm2aws-run-db-download', 'Error! DB Download Failed - Bad Response: Not Object ' . json_encode($response));
            wpm2awsAddUpdateOptions('wpm2aws_download_db_complete', 'error');
            set_transient('wpm2aws_admin_error_notice_' . get_current_user_id(), __('Error!<br><br>Prepare Database Failed. <br>Error Ref: Bad Response - No Object', 'migrate-2-aws'));
            exit(wp_safe_redirect(admin_url('/admin.php?page=wpm2aws')));
        }

        // Throw Error if we cannot determine the Response Status
        if (property_exists($response, 'status') === false) {
            $responseStatus = 'Status Not Found';

            // Get or Create Message
            if (property_exists($response, 'message') === true) {
                $responseMessage = $response->message;
            }
            if (property_exists($response, 'message') === false || empty($responseMessage)) {
                $responseMessage = 'Could Not Determine Response Message';
            }
            
            wpm2awsLogAction('Error! DB Download Failed - Bad Response: Status=' . $responseStatus . ' | Message='  . $responseMessage . ' | Response='  . json_encode($response));
            wpm2awsLogRAction('wpm2aws-run-db-download', 'Error! DB Download Failed - Bad Response:  Status=' . $responseStatus . ' | Message='  . $responseMessage . ' | Response='  . json_encode($response));
            wpm2awsAddUpdateOptions('wpm2aws_download_db_complete', 'error');
            set_transient('wpm2aws_admin_error_notice_' . get_current_user_id(), __('Error!<br><br>Prepare Database Failed. <br>Error Ref: ' . $responseMessage, 'migrate-2-aws'));
            exit(wp_safe_redirect(admin_url('/admin.php?page=wpm2aws')));
        }

        return $response->status;
    }

    public function listDbTables()
    {
        global $wpdb;
        
        $sql = "SHOW TABLES LIKE '%'";
        try {
            $results = $wpdb->get_results($sql, ARRAY_N);
        } catch (Throwable $e) {
            wpm2awsLogRAction('Error! DB Download Failed: List Tables: ' . $e->getMessage());
            throw new Exception('Error Listing Tables');
        } catch (NoTablesFoundException $e) {
            wpm2awsLogRAction('Error! DB Download Failed: List Tables: ' . $e->getMessage());
            throw new Exception('Error Listing Tables');
        } catch (RuntimeException $e) {
            wpm2awsLogRAction('Error! DB Download Failed: List Tables: ' . $e->getMessage());
            throw new Exception('Error Listing Tables');
        } catch (Exception $e) {
            wpm2awsLogRAction('Error! DB Download Failed: List Tables: ' . $e->getMessage());
            throw new Exception('Error Listing Tables');
        }

        return $results;
    }

    private function getDatabaseSize($dbName)
    {
        global $wpdb;
        
        $sql = 'SELECT table_schema AS "Database", 
        ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS "Size (MB)" 
        FROM information_schema.TABLES 
        WHERE table_schema = "' . $dbName . '"
        GROUP BY table_schema';
        
        try {
            $result = $wpdb->get_results($sql, ARRAY_N);
        } catch (Throwable $e) {
            wpm2awsLogRAction('Error! DB Download Failed: Get Database Size: ' . $e->getMessage());
            throw new Exception('Error Listing Tables');
        } catch (NoTablesFoundException $e) {
            wpm2awsLogRAction('Error! DB Download Failed: Get Database Size: ' . $e->getMessage());
            throw new Exception('Error Getting Database Size');
        } catch (RuntimeException $e) {
            wpm2awsLogRAction('Error! DB Download Failed: Get Database Size: ' . $e->getMessage());
            throw new Exception('Error Getting Database Sizes');
        } catch (Exception $e) {
            wpm2awsLogRAction('Error! DB Download Failed: Get Database Size: ' . $e->getMessage());
            throw new Exception('Error Getting Database Size');
        }
        
        $tableSize = 0;
        if (!empty($result[0]) && !empty($result[0][1])) {
            $tableSize = $result[0][1];
        };
        return $tableSize;
    }
}
