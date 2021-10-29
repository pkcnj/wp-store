<?php
class WPM2AWS_RunProcessDownload extends WP_Background_Process
{
    use WPM2AWS_Logger;
    /**
     * @var string
     */
    protected $action = 'wpm2aws-downloader-all';
    /**
     * Task
     *
     * Override this method to perform any actions required on each
     * queue item. Return the modified item for further processing
     * in the next pass through. Or, return false to remove the
     * item from the queue.
     *
     * @param mixed $item Queue item to iterate over
     *
     * @return mixed
     */
    protected function task($item)
    {
        // Sleep for 2seconds after every 5% of file downloads complete
        $this->pauseProcess();
        
        // Run the "Download File Or Directory" Process
        $downloaded = $this->backgroundDownloadToLocal($item);
    
        // Dev / Testing Logging
        if (defined('WPM2AWS_TESTING') || defined('WPM2AWS_DEBUG') || defined('WPM2AWS_DEV')) {
            $this->log("Download Result (final): " . $downloaded);
        }

        /*
        * Only Recognised responses result in successful download
        * Only allow Failed ITems to re-run 5x times
        * then log this fatal error and
        * remove item from queue
        */
        if ((string)$downloaded !== '200' && (string)$downloaded !== '404') {
            // Check if process has run max amount of times
            $permenantFail = $this->ispermenantFail($item);

            /* If processing of the item
            * has been attempted
            * to be processed more than
            * the max permissible attempts
            * the send this item into
            * the next process
            * (by assigining it a recognised response)
            * which results in it being removed from queue
            *
            * Otherwise attempt to re-process the item
            */
            if (true === $permenantFail) {
                $downloaded = '404';
            } else {
                $this->log("Download Failed - Returning to Background Queue");
                return $item;
            }
        }

        /* If sucessful, lof the result (Dev / Testing only)
        * Otherwise
        * Update Register of Failed Downloads and
        * Log the Failure
        */
        if ((string)$downloaded === '200') {
            if (defined('WPM2AWS_TESTING') || defined('WPM2AWS_DEBUG') || defined('WPM2AWS_DEV')) {
                $this->log("Download Success");
            }
        } else if ((string)$downloaded === '404') {
            $failedUserNotice = '';
            if (false !== get_option('wpm2aws_download_failures')) {
                $failedUserNotice = get_option('wpm2aws_download_failures');
            }

            // Remove any assigned suffixes for zips
            $trimmedItemName = str_replace('.wpm2awsZipDir', '', $item);
            $failedUserNotice .= $trimmedItemName . "<br>";

            // Add to register
            wpm2awsAddUpdateOptions('wpm2aws_download_failures', $failedUserNotice);      

            // Log Failure
            $this->log("Download Failed (404)");
        } else {
            $this->log("Download Failed (unknown) - " . $downloaded);
        }
        
        // Update the progress tracker
        $this->updateProgressCounter();

        // Remove item from Queue
        return false;
    }


    /**
     * Complete
     *
     * Override if applicable, but ensure that the below actions are
     * performed, or, call parent::complete().
     */
    protected function complete()
    {
        parent::complete();

        // Remove the temp directory for zipped files
        // $pluginsPath = str_replace('wp-migrate-2-aws', '', WPM2AWS_PLUGIN_DIR);
        // $zipTempDirectory = $pluginsPath . WPM2AWS_ZIP_EXPORT_PATH;

        // deleteDirectoryTree($zipTempDirectory);

        $fullZipFile = $this->zipFullZippedDirLocal();

        // Show notice to user or perform some other arbitrary task...
        $this->log('Download Process Complete - ' . date("d-m-Y @ H:i:s"));
        
        wpm2awsAddUpdateOptions('wpm2aws_download_complete', 'error');
        if (false === get_option('wpm2aws_download_errors') || empty(get_option('wpm2aws_download_errors'))) {
            wpm2awsAddUpdateOptions('wpm2aws_download_complete', 'success');
        }

        delete_option('wpm2aws_download_process_start_time');
    }
    

    /**
     * Unused function
     *
     * @return void
     */
    public function finaliseHandler()
    {
        // $this->log('*********** All Handled *************');
    }


    /**
     * Checks if an item has been attemted to be downloaded
     * a set amount of attempts - dafault 5
     * If greater than allowed number of attempts, then consider a
     * permenant fail (return true)
     *
     * @param string $downloadItem The path to item being downloaded
     * @param int $maxAttempts Number of allowed attempts - default = 5
     * @return boolean
     */
    protected function isPermenantFail(string $downloadItem, int $maxAttempts = 5)
    {
        // If there are no attempts yet made,
        // Set counter to "1" and return item to queue
        // Otherwise, get the current number of attempts
        if (false === get_option('wpm2aws_bgProcessAttempts')) {
            $attempts = 1;
            $attemptsUpdate = array(
                $downloadItem => $attempts
            );
            wpm2awsAddUpdateOptions('wpm2aws_bgProcessAttempts',  $attemptsUpdate);
            // Return to queue
            $this->log("Returning item ( " . $downloadItem . " ) to Queue - attempt: " . $attempts);
            return false;
        } else {
            $failedDownloads = get_option('wpm2aws_bgProcessAttempts');

            // If this item has already been attempted
            // and if number of attempts is less than 5
            // re-attempt the download
            // Otherwise, set the "attempts" counter as "1"
            if (isset($failedDownloads[$downloadItem])) {
                $attempts = $failedDownloads[$downloadItem];
                if ($attempts < $maxAttempts) {
                    $attempts++;
                } else {
                    $attempts = 0;
                }
            } else {
                $attempts = 1;
            }

            // Update the "attempts" option
            $failedDownloads[$downloadItem] = $attempts;
            if ($attempts < 1) {
                unset($failedDownloads[$downloadItem]);
            }
            wpm2awsAddUpdateOptions('wpm2aws_bgProcessAttempts',  $failedDownloads);
            

            // If "attempts" is greater than "0" AND less-than or equal to "max attempts"
            // then return item to the list
            // otherwise, remove from list & register in Logs 
            if ($attempts > 0 && $attempts <= $maxAttempts) {
                // Return to queue
                $this->log("Returning item to Queue - attempt: " . $attempts);
                return false;
            } else {
                // remove item from queue
                $this->log("Item is considered a permenant fail - " . $downloadItem);
                // is condsidered a permenant fail
                return true;
            }
        }
    }


    /**
     * Update the file-download progress counter
     * Add if not exits
     * Otherwise update "complete" value
     *
     * @return void
     */
    protected function updateProgressCounter()
    {
        $progress = get_option('wpm2aws_download_counter');
        $total = $progress['total'];    
        $complete = $progress['complete'];
        if (empty($complete)) {
            $complete = 1;
        } else {
            $complete++;
        }

        $update = array(
            'total' => $total,
            'complete' => $complete
        );
        update_option('wpm2aws_download_counter', $update);
        return;
    }


    /**
     * Pause background process every nth itteration
     * Default to pause for 2 seconds after every 5% comlete
     * Allow DB to be freed up for other requests
     *
     * @param integer $pauseDuration Lenght of the pause in mili-seconds (default 2 seconds)
     * @param integer $pauseFrequency How often Pause should occrur (as a percentage of overall)
     * @return void
     */
    protected function pauseProcess(int $pauseDuration = 2000000, int $pauseFrequency = 5)
    {
        // Get progress status
        $progress = get_option('wpm2aws_download_counter');
        $total = (isset($progress['total']) ? $progress['total'] : 0);
        $complete = (isset($progress['complete']) ? $progress['complete'] : 0);
        if ($total === 0) {
            $percentageComplete = 0;
        } else {
            $percentageComplete = (int)round(($complete/$total)*100);
        }

        // If this itteration is at pause-point
        if ($percentageComplete > 0 && $percentageComplete < 100) {
            if ($percentageComplete % $pauseFrequency === 0) {
                // wait for 2 (default) seconds
                $this->log("Pausing Process at: " . $percentageComplete . "% complete");
                usleep($pauseDuration);
            }
        }
        return true;
    }
}
