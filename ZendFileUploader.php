<?php
    
    class Application_ZendFileUploader {
        protected $tmp_dir;
        protected $target_dir;
        protected $file_info = array();
        
        public function __construct() {
            $this->setTmpDir();
        }
        
        /**
         * trim directory
         *
         * @param null|string $dir
         * @return string
         */
        public function getTrimmedDir($dir = null) {
            if (is_null($dir)) {
                return null;
            }
            
            return rtrim($dir, '/') . '/';
        }
        
        /**
         * set target directory of uploading file
         *
         * @param null | string $dir
         */
        public function setTargetDir($dir = null) {
            if (is_null($dir)) {
                throw new Exception("You must set target directory of uploading files.");
            } elseif (!file_exists($dir)) {
                throw new Exception("Directory " . $dir . " does not exist in system.");
            } else {
                $this->target_dir = $this->getTrimmedDir($dir);
            }
        }
        
        /**
         * set temporary directory for uploading file
         *
         * @param null | string $dir
         */
        public function setTmpDir($dir = null) {
            if (is_null($dir) || !file_exists($dir)) {
                $default_tmp_dir = APPLICATION_PATH . '/tmp/uploads/';
                if (!file_exists($default_tmp_dir)) {
                    if (!mkdir($default_tmp_dir, 0777, TRUE)) {
                        throw new Exception("Failed to create /tmp/uploads/ directory");
                    }
                }
                $this->tmp_dir = $default_tmp_dir;
            } else {
                $this->tmp_dir = $this->getTrimmedDir($dir);
            }
        }
        
        /**
         * get target directory
         *
         * @return string
         */
        public function getTargetDir() {
            return $this->target_dir;
        }
        
        /**
         * get temporary directory
         *
         * @return string
         */
        public function getTmpDir() {
            return $this->tmp_dir;
        }
        
        /**
         * upload file from http request
         * Zend_File_Transfer_Adapter_Http has been used
         *
         * @param null | string $target_dir
         * @param bool $local
         * @return bool
         */
        public function httpUpload($target_dir = null, $local = TRUE) {
            // get file from http request
            $upload = new Zend_File_Transfer_Adapter_Http();
            $upload->setDestination($this->getTmpDir());
            if ($upload->receive()) { // store file into temporary directory
                $file_info = $upload->getFileInfo();
                
                // restructure file info from Zend_File_Transfer_Adapter_Http's value
                foreach ($file_info as $key => $each_file_info) {
                    $path_parts = pathinfo($each_file_info['tmp_name']);
                    $each_file_info['extension'] = $path_parts['extension'];
                    $each_file_info['tmp_destination'] = $each_file_info['destination'];
                    $each_file_info['rename'] = '';
                    $each_file_info['rename_full_path'] = '';
                    
                    if (!is_null($target_dir)) { // rename process
                        // set target dir
                        $this->setTargetDir($target_dir);
                        
                        // generate a file name
                        $rename_file = $this->generateFileName($path_parts['extension']);
                        
                        $rename_file_path = $this->getTargetDir() . $rename_file;
                        $this->renameOneFile($each_file_info['tmp_name'], $rename_file_path);
                        
                        // store file info
                        $each_file_info['rename'] = $rename_file;
                        $each_file_info['rename_full_path'] = $rename_file_path;
                        $each_file_info['destination'] = $this->getTargetDir();
                    }
                    
                    $this->file_info[$key] = $each_file_info;
                }
                return TRUE;
                
            } else {
                return FALSE;
            }
        }
        
        /**
         * remove given file from target directory
         *
         * @param null | string $file_name
         * @param null $target_dir
         * @return bool
         */
        public function removeFile($file_name = null, $target_dir = null) {
            if (is_null($file_name)) {
                return FALSE;
            }
            
            if (is_null($target_dir)) {
                $target_dir = $this->getTargetDir();
            } else {
                $target_dir = $this->getTrimmedDir($target_dir);
            }
            
            if (!unlink($target_dir . $file_name)) {
                return FALSE;
            }
            
            return TRUE;
        }
        
        /**
         * move uploaded file from temporary directory to target directory
         *
         * @static
         * @param string $tmp_file_path
         * @param string $rename_file_path
         * @throws Exception|Zend_Filter_Exception
         */
        public static function renameOneFile($tmp_file_path, $rename_file_path) {
            if (!isset($tmp_file_path)) {
                throw new Exception("No value passed for tmp_file.");
            } else {
                if (!file_exists($tmp_file_path)) {
                    throw new Exception("You must have temporary file path, " . $tmp_file_path . ", in system.");
                }
            }
            
            if (!isset($rename_file_path)) {
                throw new Exception("No value passed for rename_file.");
            } else {
                $path_parts = pathinfo($rename_file_path);
                if (!is_writable($path_parts['dirname'])) {
                    throw new Exception("You must have target file path, " . $path_parts['dirname'] . ", in system and must be writable.");
                }
            }
            
            // rename uploaded file using Zend Framework
            try {
                $filter_file_rename = new Zend_Filter_File_Rename(array('target' => $rename_file_path, 'overwrite' => true));
                $filter_file_rename->filter($tmp_file_path);
                
            } catch (Zend_Filter_Exception $e) {
                throw $e;
            }
            
        }
        
        /**
         * generate a new file name by UUID
         *
         * @static
         * @param $extension
         * @return string
         */
        public static function generateFileName($extension) {
            $ret = Application_UUID::mint(4) . '.' . $extension;
            return $ret;
        }
        
        /**
         * return renamed uploaded file. ('rename' value from file info)
         *
         * @param null | string $http_file_key
         * @return string
         */
        public function getRenamedFileName($http_file_key = null) {
            $filename = '';
            if (is_null($http_file_key)) { // if no key passed, just return the first key's filename
                foreach ($this->getFileInfo() as $each_file_info) {
                    $filename = $each_file_info['rename'];
                    break;
                }
            } else {
                if ($this->checkFileKeyExist($http_file_key)) {
                    $file_info = $this->getFileInfo();
                    $filename = $file_info[$http_file_key]['rename'];
                }
            }
            return $filename;
        }
        
        /**
         * return original name of uploaded file. ('name' value from file info)
         *
         * @param null | string $http_file_key
         * @return string
         */
        public function getOriginalFileName($http_file_key = null) {
            $filename = '';
            if (is_null($http_file_key)) { // if no key passed, just return the first key's filename
                foreach ($this->getFileInfo() as $each_file_info) {
                    $filename = $each_file_info['name'];
                    break;
                }
            } else {
                if ($this->checkFileKeyExist($http_file_key)) {
                    $file_info = $this->getFileInfo();
                    $filename = $file_info[$http_file_key]['name'];
                }
            }
            return $filename;
        }
        
        /**
         * return all uploaded file info
         *
         * @return array
         */
        public function getFileInfo() {
            return $this->file_info;
        }
        
        /**
         * @param string $key
         * @param array $each_file_info
         */
        public function setFileInfo($key, $each_file_info) {
            if (!isset($key)) {
                throw new Exception("You must set a key for each uploading file info.");
            }
            
            if (!isset($each_file_info) || !is_array($each_file_info)) {
                throw new Exception("You must pass file info.");
            }
            
            $this->file_info[$key] = $each_file_info;
        }
        
        /**
         * check $_FILE key exist
         *
         * @param string $http_file_key
         * @return bool
         */
        public function checkFileKeyExist($http_file_key) {
            return array_key_exists($http_file_key, $this->getFileInfo());
        }
        
    }
    
    /* end of file library/Application/ZendFileUploader.php */