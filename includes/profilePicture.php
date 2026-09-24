<?php

// If it's going to need the database, then it's 
// probably smart to require it before we start.
require_once('database.php');

class profilePicture extends DatabaseObject {
	
	protected static $tblName="profile_pics";
	static protected $tblFields  = array('id', 'fkUserId','filename', 'type', 'size', 'category','createdDate');
	
	public $id;
	public $fkUserId;
	public $filename;
	public $type;
	public $size;	
	public $createdDate;
		
	public $fileToUnlink;	
	public $root;
	private $tempPath;
	
  	protected $uploadDir="../uploads/profile-pics";
	
	
	public static  $thumbnailWidth  = 200;
	public static  $thumbnailHeight = 200;
	
  	public $errors=array();
  
	protected $uploadErrors = array(
		// http://www.php.net/manual/en/features.file-upload.errors.php
		UPLOAD_ERR_OK 			=> "No errors.",
		UPLOAD_ERR_INI_SIZE  	=> "Larger than upload_max_filesize.",
		UPLOAD_ERR_FORM_SIZE 	=> "Larger than form MAX_FILE_SIZE.",
		UPLOAD_ERR_PARTIAL 		=> "Partial upload.",
		UPLOAD_ERR_NO_FILE 		=> "", //Please Select a picture to be uploaded
		UPLOAD_ERR_NO_TMP_DIR 	=> "No temporary directory.",
		UPLOAD_ERR_CANT_WRITE 	=> "Can't write to disk.",
		UPLOAD_ERR_EXTENSION 	=> "File upload stopped by extension."
	);

	// Pass in $_FILE(['uploaded_file']) as an argument
	public function attachFile($file) {
		// Perform error checking on the form parameters
		if(!$file || empty($file) || !is_array($file)) {
			// error: nothing uploaded or wrong argument usage
			$this->errors[] = "No file was uploaded.";
			
			return false;
		} elseif($file['error'] != 0) {
			// error: report what PHP says went wrong
			$this->errors[] = $this->uploadErrors[$file['error']];
			
			return false;
		} else {

			// Set object attributes to the form parameters.
			$this->tempPath  = $file['tmp_name'];
			//$this->filename  = basename($file['name']); //orignal file name
			
			$uniqueFile		=	date("Y_m_d__h_i_s__").$_SESSION['userId'];
			$this->type      =	$file['type'];
			$explode		=	explode('/',$this->type);
			$fileExtension	=	$explode[1];
			
			/////////  Here we have changed file name to dateTime_CurrentLoggedUserId to keep a unique name for user ///////////
			$this->filename	=	$uniqueFile.'.'.$fileExtension;
			
			
			$this->size     = 	$file['size'];
			
			// Don't worry about saving anything to the database yet.
			
			return true;
		
		}
	}
	
	//make thumbnail of the profile picture
	
	public static function makeThumbnail($tempPath)
	{
		
	   list($sourceWidth, $sourceHeight, $sourceType) = getimagesize($tempPath);
	   
	   switch ($sourceType) {
		  case IMAGETYPE_GIF:
			  $sourceGdim = imagecreatefromgif($tempPath);
			  break;
		  case IMAGETYPE_JPEG:
			  $sourceGdim = imagecreatefromjpeg($tempPath);
			  break;
		  case IMAGETYPE_PNG:
			  $sourceGdim = imagecreatefrompng($tempPath);
			  break;
	  }

	  $sourceAspectRatio = $sourceWidth / $sourceHeight;
	  $desiredAspectRatio = self::$thumbnailWidth / self::$thumbnailHeight;
	  
	  if ($sourceAspectRatio > $desiredAspectRatio) {
		  /*
		   * Triggered when source image is wider
		   */
		  $tempHeight = self::$thumbnailHeight;
		  $tempWidth = ( int ) (self::$thumbnailHeight * $sourceAspectRatio);
	  } else {
		  /*
		   * Triggered otherwise (i.e. source image is similar or taller)
		   */
		  $tempWidth = self::$thumbnailWidth;
		  $tempHeight = ( int ) (self::$thumbnailWidth / $sourceAspectRatio);
	  }

	  /*
	   * Resize the image into a temporary GD image
	   */
	  
	  $tempGdim = imagecreatetruecolor($tempWidth, $tempHeight);
	  imagecopyresampled(
		  $tempGdim,
		  $sourceGdim,
		  0, 0,
		  0, 0,
		  $tempWidth, $tempHeight,
		  $sourceWidth, $sourceHeight
	  );

	  /*
	   * Copy cropped region from temporary image into the desired GD image
	   */
	  
	  $x0 = ($tempWidth - self::$thumbnailWidth) / 2;
	  $y0 = ($tempHeight - self::$thumbnailHeight) / 2;
	  $desiredGdim = imagecreatetruecolor(self::$thumbnailWidth, self::$thumbnailHeight);
	  imagecopy(
		  $desiredGdim,
		  $tempGdim,
		  0, 0,
		  $x0, $y0,
		  self::$thumbnailWidth, self::$thumbnailHeight
	  );
	  
	  return $desiredGdim;		
	
	}
	
	
  
	public function save() {
		// A new record won't have an id yet.
		if(isset($this->id)&&(isset($this->filename))) {
			 
			 $this->destroy();//dlete the picture in the folder
			 
			  //full profile picture path
			  $targetPath = FindRoot() . $this->uploadDir ."/".$this->filename;
			  //thumnail target path
			   $targetPathThumbnails =  FindRoot() . $this->uploadDir ."/thumbs/".$this->filename;
			  
			  $thumbnailImage=self::makeThumbnail($this->tempPath);
			  
			  if(move_uploaded_file($this->tempPath, $targetPath)) {
				  
				 /* move thumbnail */ 
				 
				if(strcmp($this->type,"image/png")==0)
				imagepng($thumbnailImage,$targetPathThumbnails);
				
				if(strcmp($this->type,"image/jpeg")==0)
				imagejpeg($thumbnailImage,$targetPathThumbnails);
				
				if(strcmp($this->type,"image/gif")==0)
				imagegif($thumbnailImage,$targetPathThumbnails);
				
				/* end move thumbnail*/  
				  
		  
			  // Really just to update the caption
			   $this->update();
			  
			  return true;
			  
			  }
			  else
			  {
				  echo "Picture cant be moved,plz try again latter !thanks";
				  return false;
			  }
			
		} 
		else 
		{
			// Make sure there are no errors
			
			// Can't save if there are pre-existing errors
		  if(!empty($this->errors)) { return false; }
		  
			
		  // Can't save without filename and temp location
		  if(empty($this->filename) || empty($this->tempPath)) {
		    $this->errors[] = "The file location was not available.";
		    return false;
		  }
			
			// Determine the targetPath
		  $targetPath =  FindRoot() . $this->uploadDir ."/".$this->filename;
		  $targetPathThumbnails =  FindRoot() . $this->uploadDir ."/thumbs/".$this->filename;
		  
		  //make thumbnail
		  $thumbnailImage=self::makeThumbnail($this->tempPath);
		 	
			
			if(move_uploaded_file($this->tempPath, $targetPath)) {
				
				//move thumbnail also
				if(strcmp($this->type,"image/png")==0)
				imagepng($thumbnailImage,$targetPathThumbnails);
				
				if(strcmp($this->type,"image/jpeg")==0)
				imagejpeg($thumbnailImage,$targetPathThumbnails);
				
				if(strcmp($this->type,"image/gif")==0)
				imagegif($thumbnailImage,$targetPathThumbnails);
				
				
				
		  	// Success
				// Save a corresponding entry to the database
				if($this->create()) {
					// We are done with tempPath, the file isn't there anymore
					
					//imagedestroy($thumbnailImage);
					
					unset($this->tempPath);
					return true;
					
				}
			} else {
				// File was not moved.
		    $this->errors[] = "The file upload failed, possibly due to incorrect permissions on the upload folder.";
		    return false;
			}
		}
	}
	
	// Finds file path to display all images
	public function filePath() {
		return FindRoot() . $this->uploadDir ."/".$this->filename;
	}
	public  function thumbnailPath()
	{
		return FindRoot() . $this->uploadDir ."/".$this->filename;
	}
	
	public function sizeAsText() {
		if($this->size < 1024) {
			return "{$this->size} bytes";
		} elseif($this->size < 1048576) {
			$sizeKB = round($this->size/1024);
			return "{$sizeKB} KB";
		} else {
			$sizeMB = round($this->size/1048576, 1);
			return "{$sizeMB} MB";
		}
	}
		public function destroy() {
		// First remove the database entry
			// then remove the file
		  	// Note that even though the database entry is gone, this object 
			// is still around (which lets us use $this->image_path()).
			$largeImagePath = $this->filePath();
			$thumbnailPath = $this->thumbnailPath();
			
			//delete image from the folders	
			$largeImagePath = FindRoot() .$this->uploadDir."/".$this->fileToUnlink;
			$thumbnailPath = FindRoot() .$this->uploadDir."/thumbs/".$this->fileToUnlink;
			
			$unlinkedLargeImage= unlink($largeImagePath) ? true : false;
			$unlinkedThumbnail=  unlink($thumbnailPath) ? true : false;
			if($unlinkedLargeImage && $unlinkedThumbnail)
			{
				return true;
			}
			else
			{
				return false;
			}		
	}
	
	
	// This will return  records by fkUserId in pictures table in DESC (Descending) order by picture ID
	public static function findByfkUserId($fkUserId=0) {
		global $database;
		$sql = "SELECT * FROM " . self::$tblName;
		$sql .= " WHERE fkUserId=" .$database->escapeValue($fkUserId) ." ORDER BY id DESC";
		return self::findBySql($sql);
	}
}

?>