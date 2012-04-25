<?php
    
    class Application_ZendImageUploader extends Application_ZendFileUploader {
        protected $resized_dir;
        
        /**
         * set source directory of uploading image
         *
         * @param null | string $dir
         */
        public function setSourceDir($dir = null) {
            $this->setTargetDir($dir);
        }
        
        /**
         * set resized directory of uploading image
         *
         * @param null $dir
         */
        public function setResizedDir($dir = null) {
            if (is_null($dir)) {
                throw new Exception("You must set source directory of uploading files.");
            } elseif (!file_exists($dir)) {
                throw new Exception("Directory " . $dir . " does not exist in system.");
            } else {
                $this->resized_dir = $this->getTrimmedDir($dir);
            }
        }
        
        /**
         * get source directory
         *
         * @return string
         */
        public function getSourceDir() {
            return $this->getTargetDir();
        }
        
        /**
         * get resized directory
         *
         * @return string
         */
        public function getResizedDir() {
            return $this->resized_dir;
        }
        
        /**
         * upload images
         *
         * @param null|string $source_dir
         * @param array $resizes
         * 		array(
         *			[directory] => string
         *			[width] => integer
         * 			[height] => integer
         * 		)
         * @param bool $local
         * @return bool
         */
        public function httpUploadImages($source_dir = null, $resizes = array(), $local = TRUE) {
            if (!$this->httpUpload($source_dir, $local)) {
                return FALSE;
            }
            
            $flag = TRUE;
            if (!empty($resizes) && isset($resizes['width']) && isset($resizes['height'])) {
                if (isset($resizes['directory'])) {
                    $this->setResizedDir($resizes['directory']);
                }
                
                foreach($this->getFileInfo() as $file_info) {
                    if (!$this->resizeSourceImage($file_info['rename'], $resizes['width'], $resizes['height'], $this->getSourceDir(), $this->getResizedDir())) {
                        $flag = FALSE;
                    }
                }
            }
            
            return $flag;
        }
        
        /**
         * remove both source and resized images
         *
         * @param null|string $file_name
         * @param array $directories
         * 		array(
         *			[source_dir] => string
         * 			[resized_dir] => string
         * 		)
         * @return bool
         */
        public function removeImages($file_name = null, $directories = array()) {
            if (isset($directories['source_dir'])) {
                $source_dir = $directories['source_dir'];
            } else {
                $source_dir = null;
            }
            
            if (isset($directories['resized_dir'])) {
                $resized_dir = $directories['resized_dir'];
            } else {
                $resized_dir = null;
            }
            
            return $this->removeSourceImage($file_name, $source_dir) && $this->removeResizedImage($file_name, $resized_dir);
        }
        
        /**
         * remove source image
         *
         * @param null|string $file_name
         * @param null|string $source_dir
         * @return bool
         */
        public function removeSourceImage($file_name = null, $source_dir = null) {
            if (is_null($file_name)) {
                return FALSE;
            }
            
            if (!is_null($source_dir)) {
                $this->setSourceDir($source_dir);
            }
            
            return $this->removeFile($file_name, $this->getSourceDir());
        }
        
        /**
         * remove resized image
         *
         * @param null $file_name
         * @param null $resized_dir
         * @return bool
         */
        public function removeResizedImage($file_name = null, $resized_dir = null) {
            if (is_null($file_name)) {
                return FALSE;
            }
            
            if (!is_null($resized_dir)) {
                $this->setResizedDir($resized_dir);
            }
            
            return $this->removeFile($file_name, $this->getResizedDir());
        }
        
        /**
         * resize source image
         *
         * @param string $file_name
         * @param $resized_width
         * @param $resized_height
         * @param null|string $source_dir
         * @param null|string $resized_dir
         * @return bool
         */
        public function resizeSourceImage($file_name, $resized_width, $resized_height, $source_dir = null, $resized_dir = null) {
            if (!is_null($source_dir)) {
                $this->setSourceDir($source_dir);
            }
            
            if (!is_null($resized_dir)) {
                $this->setResizedDir($resized_dir);
            }
            
            $full_image_path = $this->getSourceDir() . $file_name;
            if (!file_exists($full_image_path)) {
                return FALSE;
            }
            
            $image_info = getimagesize($full_image_path);
            $image_type = $image_info[2];
            
            if ($image_type == IMAGETYPE_JPEG) {
                $image = imagecreatefromjpeg($full_image_path);
                
            } elseif ($image_type == IMAGETYPE_GIF) {
                $image = imagecreatefromgif($full_image_path);
                
            } elseif ($image_type == IMAGETYPE_PNG) {
                $image = imagecreatefrompng($full_image_path);
                
            } else {
                $image = null;
            }
            
            $original_width = imagesx($image);
            $original_height = imagesy($image);
            
            $resized_image = imagecreatetruecolor($resized_width, $resized_height);
            if (!imagecopyresampled($resized_image, $image, 0, 0, 0, 0, $resized_width, $resized_height, $original_width, $original_height)) {
                return FALSE;
            } else {
                $full_resized_image_path = $this->getResizedDir() . $file_name;
                if ($image_type == IMAGETYPE_JPEG) {
                    return imagejpeg($resized_image, $full_resized_image_path);
                    
                } elseif ($image_type == IMAGETYPE_GIF) {
                    return imagegif($resized_image, $full_resized_image_path);
                    
                } elseif ($image_type == IMAGETYPE_PNG) {
                    return imagepng($resized_image, $full_resized_image_path);
                    
                } else {
                    return FALSE;
                }
            }
        }
    }
    
    /* end of file library/Application/ZendImageUploader.php */