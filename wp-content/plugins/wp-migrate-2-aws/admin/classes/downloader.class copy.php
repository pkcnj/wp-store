<?php

class WPM2AWS_Downloader
{
    /**
     * @var WPM2AWS_RunRequestDownload
     */
    protected $process_single;
    
    /**
     * @var WPM2AWS_RunProcessDownload
     */
    protected $process_all;
    
    /**
     * Example_Background_Processing constructor.
     */
    public function __construct()
    {
        // add_action('plugins_loaded', array( $this, 'init' ));
        add_action('admin_init', array( $this, 'init' ));
        add_action('admin_init', array( $this, 'process_handler' ));
    }
    
    /**
     * Init
     */
    public function init()
    {
        require_once plugin_dir_path(__FILE__) . 'logger.class.php';
        
        
        if (! class_exists('WP_Async_Request', false)) {
            include_once WPM2AWS_PLUGIN_DIR  . '/vendor/a5hleyrich/wp-background-processing/classes/wp-async-request.php';
        }
        
        if (! class_exists('WP_Background_Process', false)) {
            include_once WPM2AWS_PLUGIN_DIR . '/vendor/a5hleyrich/wp-background-processing/classes/wp-background-process.php';
        }
        require_once WPM2AWS_PLUGIN_DIR . '/admin/abstracts/uploader/async-requests/runRequestDownload.class.php';
        
        require_once WPM2AWS_PLUGIN_DIR . '/admin/abstracts/uploader/background-processes/runProcessDownload.class.php';
        
        $this->processsingle = new WPM2AWS_RunRequestDownload();
        $this->process_all    = new WPM2AWS_RunProcessDownload();
    }
    
    
    /**
     * Process handler
     */
    public function process_handler()
    {
        if (isset($_POST['_wpnonce'])) {
            // echo "<br>In Post Section of Process Handlere";
            // if ((! isset($_POST['wpm2aws-process-once-submit']) && ! isset($_POST['wpm2aws-process-all-submit'])) || ! isset($_POST['_wpnonce'])) {
            //     wp_die('Invalid Post');
            //     return;
            // }

            if (!isset($_POST['wpm2aws-process-download-all-submit']) && !isset($_POST['wpm2aws-process-download-all-restart'])) {
                wp_die('Invalid Post');
                return;
            }


            
            if (! wp_verify_nonce($_POST['_wpnonce'], 'wpm2aws-run-fs-download')) {
                print_r($_POST);
                wp_die('Invalid Nonce');
                return;
            }
            // echo "<br>Post:";
            // print_r($_POST);
            if (isset($_POST['wpm2aws-process-download-once-submit'])) {
                // echo "<br>Process Once";
                $this->handle_single('POST_process-download-once');
            } elseif (isset($_POST['wpm2aws-process-download-all-submit'])) {
                // echo "<br>Process All";

                // Reset Log File
                wpm2awsLogResetAll();

                
                $this->handle_all('POST_process-download-all');
                exit(wp_redirect(admin_url('/admin.php?page=wpm2aws')));
            } elseif (isset($_POST['wpm2aws-process-download-all-restart'])) {
                $this->handle_restart();
                exit(wp_redirect(admin_url('/admin.php?page=wpm2aws')));
            } else {
                wp_die(print_r($_POST));
            }
        } elseif ((isset($_GET['action']) && $_GET['action'] === 'wp_wpm2aws-downloader-once') && isset($_GET['nonce'])) {
            // echo "<br>In Get Single<br>";
            $this->handle_single('GET_downloader-once');
        } elseif ((isset($_GET['action']) && $_GET['action'] === 'wp_wpm2aws-downloader-all') && isset($_GET['nonce'])) {
            // echo "<br>In Get All<br>";
            // $this->handle_all();
            return;
        } else {
            wp_die('Invalid Access');
        }
        
        print_r($_GET);
    }
    
    /**
     * Handle single
     */
    protected function handle_single($param)
    {
        // echo "<br>Handle Single";
        $names = $this->get_names($param);
        $rand  = array_rand($names, 1);
        $name  = $names[ $rand ];
        // echo "<br>Name: " . $name;
        $this->process_single->data(array( 'name' => $name ))->dispatch();
        // echo "<br>";
        // print_r($result);
        // echo "<br>Processed";
        $this->process_single->logProcessFinished();
    }
    
    /**
     * Handle all
     */
    protected function handle_all($param)
    {
        // echo "<br>Handle All";
        // $names = $this->get_names($param);
        
        wpm2awsAddUpdateOptions('wpm2aws_download_errors', array());
        $fullFilePath = '';
        
        try {
            // Check File Exists
            // $fullFilePath = get_option('wpm2aws-aws-s3-download-directory-path') . '/' . get_option('wpm2aws-aws-s3-download-directory-name');
            
            $fullFilePath .= get_option('wpm2aws-aws-s3-upload-directory-path');
            $pathSeparator = DIRECTORY_SEPARATOR;

            $fullFilePath .= $pathSeparator;
            $fullFilePath .= get_option('wpm2aws-aws-s3-upload-directory-name');

            if (!is_dir($fullFilePath)) {
                wpm2awsLogAction('Error! Directory does not exist: ' . $fullFilePath);
                throw new Exception('Error! Directory does not exist:<br><br>' . $fullFilePath);
                return false;
            }
        } catch (Exception $e) {
            wpm2awsLogAction('Directory Download Failed! Error Mgs: ' . $e->getMessage());
            return false;
        }

        if (defined('WPM2AWS_TESTING') || defined('WPM2AWS_DEBUG') || defined('WPM2AWS_DEV')) {
            wpm2awsLogAction('Source Path: ' . $fullFilePath);
        }

        
        // $names = $this->listFolderFiles($fullFilePath);

        $names = $this->listDirectoriesForZip($fullFilePath);
        // wp_die(print_r($names));

        // add-in Launch Script
        // $names['plugins\wp-migrate-2-aws__launch'] = 'plugins\wp-migrate-2-aws\libraries\unzip\zipIimporter_fs.php';
        $importScriptFile = '';
        $importScriptFile .= 'plugins';
        $importScriptFile .= DIRECTORY_SEPARATOR;
        $importScriptFile .= 'wp-migrate-2-aws';
        $importScriptFile .= DIRECTORY_SEPARATOR;
        $importScriptFile .= 'libraries';
        $importScriptFile .= DIRECTORY_SEPARATOR;
        $importScriptFile .= 'unzip';
        $importScriptFile .= DIRECTORY_SEPARATOR;
        $importScriptFile .= 'zipIimporter_fs.php';
        
        // $names['plugins\wp-migrate-2-aws__launch'] = $importScriptFile;

        // Deliberate Failure Files
        if (defined('WPM2AWS_TEST_FAILURE')) {
            $names['intentional_fail'] = 'intentional_fail.php';
            $names['intentional_fail_2'] = 'plugins/fail/intentional_fail_2.wpm2awsZipDir';
        }
        

        // wp_die(print_r($names));

        // if (defined('WPM2AWS_DEV')) {
        //     wp_die(print_r($names));
        // }

        // $directories = $this->listDirectoriesForDownload($fullFilePath);
        // if (defined('WPM2AWS_TESTING')) {
        //     wp_die(print_r($directories));
        // }
        wpm2awsAddUpdateOptions('wpm2aws_download_failures', '');
        wpm2awsAddUpdateOptions('wpm2aws_download_started', true);
        wpm2awsAddUpdateOptions('wpm2aws_download_complete', false);

        $total = 0;
        $complete = 0;
        if (!empty($names)) {
            $total = count($names);
        }
        wpm2awsAddUpdateOptions(
            'wpm2aws_download_counter',
            array(
                'total' => $total,
                'complete' => $complete
            )
        );

        if (defined('WPM2AWS_TESTING') || defined('WPM2AWS_DEBUG') || defined('WPM2AWS_DEV')) {
            foreach ($names as $name) {
                wpm2awsLogAction("Processing File: " . $name);
            }
        }

        // wp_die(print_r($names));
        foreach ($names as $name) {
            $this->process_all->push_to_queue($name);
        }
        
        wpm2awsLogAction('Download Process Started - ' . date("d-m-Y @ H:i:s"));
        $this->process_all->save()->dispatch();
    }

    protected function handle_restart()
    {
        
        // Get current batch
        global $wpdb;

        $sql = "SELECT option_id, option_name, option_value FROM {$wpdb->prefix}options WHERE option_name LIKE 'wp_wpm2aws-downloader-all_batch_%'";

        $existingBatches = $wpdb->get_results($sql, OBJECT);
        $currentBatch = array();
        if (!empty($existingBatches)) {
            foreach ($existingBatches as $batchIx => $batchDetails) {
                if (empty($currentBatch)) {
                    $currentBatch = $existingBatches[$batchIx];
                    $currentBatchOptionName = $batchDetails->option_name;
                } else {
                    if ($batchDetails->option_id > $currentBatch->option_id) {
                        $previousBatchOptionName = $currentBatchOptionName;

                        $currentBatch = $existingBatches[$batchIx];
                        $currentBatchOptionName = $batchDetails->option_name;

                        delete_option($previousBatchOptionName);
                    } else {
                        $optionName = $batchDetails->option_name;
                        delete_option($optionName);
                    }
                }
            }
        }

        // put items into array
        $items = unserialize($currentBatch->option_value);
        
        // push itmes to queue
        foreach ($items as $item) {
            $this->process_all->push_to_queue($item);
        }

        // delete old batch
        delete_option($currentBatchOptionName);

        // displatch new queue
        wpm2awsLogAction('Download Process Re-Started - ' . date("d-m-Y @ H:i:s"));
        $this->process_all->save()->dispatch();

        // return;
    }
    
    // private function listDownloadContentItems($parentDirectoryName)
    // {
    //     $fullListing = array();
        
    //     $fullListing = $this->listDownloadContentSubItems($parentDirectoryName);
    //     $directories = $fullListing['dir'];

    //     return $directories;
    //     return $fullListing;
    // }
    
    // private function listDownloadContentSubItems($directoryPath)
    // {
    //     $listing = array();
        
    //     // Get a list of items in Sub Directory
    //     $items = scandir($directoryPath);
    //     // Remove the Linux path prefixes
    //     $items = array_diff($items, array('..', '.'));
    //     $items = array_merge($items);
        
    //     // Get a list of any sub Directories
    //     $directories = array();
    //     $pathSeparator = '/';
    //     if (strpos($directoryPath, '\\') !== false) {
    //         $pathSeparator = '\\';
    //     }
    //     foreach ($items as $itemIx => $itemVal) {
    //         if (is_dir($directoryPath . $pathSeparator . $itemVal)) {
    //             array_push($directories, $directoryPath . $pathSeparator . $itemVal);
    //             unset($items[$itemIx]);
    //         }
    //     }
    //     $listing[$directoryPath] = $items;
    //     $listing['dir'] = $directories;
        
    //     // wp_die(print_r($listing));
    //     return $listing;
    // }
    
    private function listFolderFiles($dir, $parentDir = '')
    {
        $files = array();
        $counter = 0;

        $basePath = get_option('wpm2aws-aws-s3-upload-directory-path');
        $pathSeparator = '/';
        if (strpos($basePath, '\\') !== false) {
            $pathSeparator = '\\';
        }

        // $pluginsDir = str_replace(DIRECTORY_SEPARATOR . WPM2AWS_PLUGIN_NAME, '', WPM2AWS_PLUGIN_DIR);
        // $pluginsDirParentEnd = strrpos($pluginsDir, DIRECTORY_SEPARATOR);
        // $pluginsDirParentStart = strrpos($pluginsDir, DIRECTORY_SEPARATOR, $pluginsDirParentEnd - strlen($pluginsDir) - 1);
        // $pluginsDirParent = substr($pluginsDir, $pluginsDirParentStart);

        $pluginsDir = str_replace(DIRECTORY_SEPARATOR . WPM2AWS_PLUGIN_NAME, '', WPM2AWS_PLUGIN_DIR);
        //wpm2awsLogAction('Plugins Directory: ' . $pluginsDir);
        $downloadsPath = $dir;
        $pluginsDirParent = str_replace($downloadsPath, '', $pluginsDir);

        //wpm2awsLogAction('Downloads Path: ' . $downloadsPath);
        //wpm2awsLogAction('Plugins Parent Dir: ' . $pluginsDirParent);


        if (DIRECTORY_SEPARATOR === substr($pluginsDirParent, 0, 1)) {
            $pluginsDirParent = substr($pluginsDirParent, 1);
        }

        if (DIRECTORY_SEPARATOR === substr($pluginsDirParent, (strlen($pluginsDirParent) -1), 1)) {
            $pluginsDirParent = substr($pluginsDirParent, 0, (strlen($pluginsDirParent) -1));
        }


        $strippedParentDir = str_replace($downloadsPath, '', $parentDir);
        if (DIRECTORY_SEPARATOR === substr($strippedParentDir, 0, 1)) {
            $strippedParentDir = substr($strippedParentDir, 1);
        }

        if (DIRECTORY_SEPARATOR === substr($strippedParentDir, (strlen($strippedParentDir) -1), 1)) {
            $strippedParentDir = substr($strippedParentDir, 0, (strlen($strippedParentDir) -1));
        }

        $wpm2aws_exclude_wp_core_themes = array(
            'twentyfifteen',
            'twentysixteen',
            'twentyseventeen',
            'twentyeighteen',
            'twentynineteen',
            'twentytwenty'
        );

        foreach (new DirectoryIterator($dir) as $fileInfo) {
            if (!$fileInfo->isDot()) {
                if ($fileInfo->isDir()) {
                    if (
                        strpos($dir, $pluginsDir) !== false ||
                        // in_array($fileInfo->getFilename(), WPM2AWS_EXCLUDE_WP_CORE_THEMES)
                        in_array($fileInfo->getFilename(), $wpm2aws_exclude_wp_core_themes)
                    ) {
                        $files[$fileInfo->getFilename()] = $parentDir . $pathSeparator . $fileInfo->getFilename() . '.wpm2awsZipDir';
                    } else {
                        if (strpos($fileInfo->getPathname(), '.git') === false) {
                            $indexRef = $fileInfo->getFilename();
                            if ($parentDir) {
                                $indexRef = $parentDir . $pathSeparator . $fileInfo->getFilename();
                            }
                            $files[$fileInfo->getPathname()] = $this->listFolderFiles($fileInfo->getPathname(), $indexRef);
                        }
                    }
                } else {
                    $indexRef = $counter;
                    $filePath = '';
                    if ($parentDir) {
                        $counter++;
                        $indexRef = $parentDir . '__' . $counter;
                        $filePath = $parentDir . $pathSeparator;
                    }
                    $files[$indexRef] = $filePath . $fileInfo->getFilename();
                }
                $counter++;
            }
        }
        
        foreach ($files as $fileIx => $fileData) {
            if (is_array($fileData)) {
                $files = array_merge($files, $fileData);
                unset($files[$fileIx]);
            }
        }
        return $files;
    }

    private function listDirectoriesForZip($dir, $parentDir = '')
    {
        $files = array();
        $counter = 0;
        $getSubDirs = array('themes', 'uploads', 'plugins',);
        $getSubSubDirs = array('uploads');
        $subSubDirList = array();

        $basePath = get_option('wpm2aws-aws-s3-upload-directory-path');
        $pathSeparator = DIRECTORY_SEPARATOR;

        $pluginsDir = str_replace(DIRECTORY_SEPARATOR . WPM2AWS_PLUGIN_NAME, '', WPM2AWS_PLUGIN_DIR);
        $downloadsPath = $dir;
        $pluginsDirParent = str_replace($downloadsPath, '', $pluginsDir);

        if (DIRECTORY_SEPARATOR === substr($pluginsDirParent, 0, 1)) {
            $pluginsDirParent = substr($pluginsDirParent, 1);
        }

        if (DIRECTORY_SEPARATOR === substr($pluginsDirParent, (strlen($pluginsDirParent) -1), 1)) {
            $pluginsDirParent = substr($pluginsDirParent, 0, (strlen($pluginsDirParent) -1));
        }

        $strippedParentDir = str_replace($downloadsPath, '', $parentDir);
        if (DIRECTORY_SEPARATOR === substr($strippedParentDir, 0, 1)) {
            $strippedParentDir = substr($strippedParentDir, 1);
        }

        if (DIRECTORY_SEPARATOR === substr($strippedParentDir, (strlen($strippedParentDir) -1), 1)) {
            $strippedParentDir = substr($strippedParentDir, 0, (strlen($strippedParentDir) -1));
        }

        foreach (new DirectoryIterator($dir) as $fileInfo) {
            if (!$fileInfo->isDot()) {
                if ($fileInfo->isDir()) {
                    $indexRef = $fileInfo->getFilename();
                    if ($parentDir) {
                        $indexRef = $parentDir . $pathSeparator . $fileInfo->getFilename();
                    }
                        
                    $files[$indexRef] = $parentDir . $pathSeparator . $fileInfo->getFilename() . '.wpm2awsZipDir';
                        

                    // if ($fileInfo->getFilename() === 'uploads') {
                            
                    // }

                    if (in_array($fileInfo->getFilename(), $getSubDirs)) {
                        // wp_die($parentDir);

                        if (strpos($fileInfo->getPathname(), '.git') === false) {
                            $indexRef = $fileInfo->getFilename();
 

                            // if (in_array($fileInfo->getFilename(), $getSubSubDirs)) {
                            //     wp_die('sub sub dir');
                            //     $indexRef = $parentDir . $pathSeparator . $fileInfo->getFilename();
                            //     $files[$indexRef . '_sub_children'] = $this->listDirectoriesForZip($fileInfo->getPathname(), $indexRef);
                            // }


                            // if (!empty($parentDir)) {
                            //     wp_die($parentDir);
                            //     $indexRef = $parentDir . $pathSeparator . $fileInfo->getFilename();
                            //     if (in_array($parentDir, $getSubSubDirs)) {
                            //         $indexRef = $parentDir . $pathSeparator . $fileInfo->getFilename();
                            //         $files[$indexRef . '_sub_children'] = 'test';
                            //     }
                            // }

                            // if () {

                            // }
                                
                            $files[$indexRef . '_children'] = $this->listDirectoriesForZip($fileInfo->getPathname(), $indexRef);
                        }
                    }

                    if (!empty($parentDir) && in_array($strippedParentDir, $getSubSubDirs)) {
                        // wp_die($parentDir);
                        $indexRef = $parentDir . $pathSeparator . $fileInfo->getFilename();
                            
                        $files[$indexRef . '_sub_children'] = $this->listDirectoriesForZip($fileInfo->getPathname(), $indexRef);
                    }
                        
                    // if (in_array($fileInfo->getFilename(), $getSubDirs)) {
                        //     if (strpos($fileInfo->getPathname(), '.git') === false) {
                        //         $indexRef = $fileInfo->getFilename();
                        //         if ($parentDir) {
                        //             $indexRef = $parentDir . $pathSeparator . $fileInfo->getFilename();
                        //         }

                        //         $files[$indexRef . '_children'] = $this->listDirectoriesForZip($fileInfo->getPathname(), $indexRef);

                        //          if (in_array($fileInfo->getFilename(), $getSubSubDirs)) {
                        //             $indexRef = $parentDir . $pathSeparator . $fileInfo->getFilename();
                        //             $files[$indexRef . '_sub_children'] = $this->listDirectoriesForZip($fileInfo->getPathname(), $indexRef);
                        //         }
                        //     }
                        // }
                }
                $counter++;
            }
        }

       
        foreach ($files as $fileIx => $fileData) {
            if (is_array($fileData)) {
                if (strpos($fileIx, '_children') !== false && in_array(str_replace('_children', '', $fileIx), $getSubSubDirs, true)) {
                    foreach ($fileData as $fileDataIx => $fileDataData) {
                        if (substr_count($fileDataIx, DIRECTORY_SEPARATOR) === 1) {
                            unset($fileData[$fileDataIx]);
                        }
                    }
                }
                
                    
                
                
                
                // wp_die(print_r($files));
                // if (strpos($fileIx, '_children') !== false && in_array(str_replace('_children', '', $fileIx), $getSubSubDirs, true)) {
                    
                    
                //     // wp_die(print_r($fileData));
                //     // $f = array_merge($files, $fileData);
                //     // array_push($subSubDirList, $fileData);
                //     // $subSubDirList = array_merge($subSubDirList);
                //     // unset($files[$fileIx]);
                //     $subSubDirList = $fileData;
                //     foreach ($fileData as $fileDataIx => $fileDataData) {
                //         // if (strpos($fileIx, '_uploads') !== false) {
                //         //     wp_die(print_r($fileData));
                //         // }
                        
                //         // array_push($subSubDirList,$fileDataData);
                //         // if (!is_array($fileDataData)) {
                            
                //         //     // $fileData[$fileDataIx] = '';
                //         //     // unset($fileData[$fileDataIx]);
                //         // }
                //     }
                //     // $files = array_merge($files, $fileData);
                //     // exit($fileIx);
                // }
                // else {
                // $files = array_merge($files, $fileData);
                // }
                
                $files = array_merge($files, $fileData);
                unset($files[$fileIx]);
            }
        }
        

        
        // if (isset($files['upload'])) {
        //    wp_die(print_r($subSubDirList));
        // }
        
        // return $subSubDirList;
        // wp_die(print_r($files));
        // wp_die(var_dump($files));
        // return $files;
        foreach ($getSubDirs as $subDirIx => $subDirName) {
            if (isset($files[$subDirName])) {
                unset($files[$subDirName]);
            }
        }

        // foreach ($files as $fileIx => $fileData) {
        //     if (strpos($fileData)) {
        //         $files = array_merge($files, $fileData);
        //         unset($files[$fileIx]);
        //     }
        // }

        // foreach ($getSubSubDirs as $subSubDirIx => $subSubDirName) {
        //     if (substr_count($subSubDirName, DIRECTORY_SEPARATOR) === 1) {
        //         if (isset($files[$subSubDirName])) {
        //             unset($files[$subSubDirName]);
        //         }
        //     }
        // }
        // return $subSubDirList;
        return $files;
    }

    // private function listDirectoriesForDownload($dir)
    // {
    //     $directories = array();
        
    //     foreach (new DirectoryIterator($dir) as $dirInfo) {
    //         if (!$dirInfo->isDot() && $dirInfo->isDir()) {
    //             $directories[] = $dirInfo->getFilename();
    //         }
    //     }
    //     return $directories;


    //     $pluginsDir = str_replace(DIRECTORY_SEPARATOR . WPM2AWS_PLUGIN_NAME, '', WPM2AWS_PLUGIN_DIR);
    //     $pluginsDirParentEnd = strrpos($pluginsDir, DIRECTORY_SEPARATOR);
    //     $pluginsDirParentStart = strrpos($pluginsDir, DIRECTORY_SEPARATOR, $pluginsDirParentEnd - strlen($pluginsDir) - 1);
    //     $pluginsDirParent = substr($pluginsDir, $pluginsDirParentStart);

    //     $counter = 0;
    //     foreach (new DirectoryIterator($dir) as $fileInfo) {
    //         if (!$fileInfo->isDot()) {
    //             if ($fileInfo->isDir()) {
    //                 if (strpos($fileInfo->getPathname(), $pluginsDirParent) !== false ||
    //                     $fileInfo->getFilename() === 'twentysixteen' ||
    //                     $fileInfo->getFilename() === 'twentyseventeen' ||
    //                     $fileInfo->getFilename() === 'twentyeighteen' ||
    //                     $fileInfo->getFilename() === 'twentynineteen' ||
    //                     $fileInfo->getFilename() === 'twentytwenty'
    //                 ) {
    //                     $pathSeparator = '/';
    //                     if (strpos($parentDir, '\\') !== false) {
    //                         $pathSeparator = '\\';
    //                     }
    //                     $files[$fileInfo->getFilename().$indexRef] = $parentDir . $pathSeparator . $fileInfo->getFilename() . '.wpm2awsZipDir';
    //                 } else {
    //                     if (strpos($fileInfo->getPathname(), '.git') === false) {
    //                         // if($fileInfo->getPathname() !== '.git') {
    //                         $indexRef = $fileInfo->getFilename();
    //                         if ($parentDir) {
    //                             $pathSeparator = '/';
    //                             if (strpos($parentDir, '\\') !== false) {
    //                                 $pathSeparator = '\\';
    //                             }
    //                             $indexRef = $parentDir . $pathSeparator . $fileInfo->getFilename();
    //                         }
    //                         $files[$fileInfo->getPathname()] = $this->listFolderFiles($fileInfo->getPathname(), $indexRef);
    //                     }
    //                 }
    //             } else {
    //                 $indexRef = $counter;
    //                 $filePath = '';
    //                 if ($parentDir) {
    //                     $counter++;
    //                     $indexRef = $parentDir . '__' . $counter;
    //                     $pathSeparator = '/';
    //                     if (strpos($parentDir, '\\') !== false) {
    //                         $pathSeparator = '\\';
    //                     }
    //                     $filePath = $parentDir . $pathSeparator;
    //                 }
    //                 $files[$indexRef] = $filePath . $fileInfo->getFilename();
    //             }
    //             $counter++;
    //         }
    //     }
        
    //     foreach ($files as $fileIx => $fileData) {
    //         if (is_array($fileData)) {
    //             $files = array_merge($files, $fileData);
    //             unset($files[$fileIx]);
    //         }
    //     }
    //     return $files;
    // }
}
