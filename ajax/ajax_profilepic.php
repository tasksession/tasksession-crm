<?php 
include('../includes/loader_ajax.php');
require_once dirname(__DIR__) . '/includes/functions.php';
require_once dirname(__DIR__) . '/includes/csrf-middleware.php';

if (!isset($session) || !$session->isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

csrf_require_for_request();

// Ensure $url is available
if (!isset($url)) {
	global $url;
	if (!isset($url)) {
		$url = '../';
	}
}
if((!empty($_FILES["pro-pic"])) && ($_FILES['pro-pic']['error'] == 0)) {
  //Check if the file is JPEG image and it's size is less than 350Kb
$fileType = $_FILES['pro-pic']['type'];
$filesize = $_FILES['pro-pic']['size'];
$fileError = $_FILES['pro-pic']['error'];
  $filename = basename($_FILES['pro-pic']['name']);
  $ext = strtolower(substr($filename, strrpos($filename, '.') + 1));
  $maxFileSize = 10485760; // 10MB
  $allowedExt = ['jpg', 'jpeg', 'png'];
  $allowedMime = ['image/jpeg', 'image/png'];
  $finfo = function_exists('finfo_open') ? finfo_open(FILEINFO_MIME_TYPE) : false;
  $detectedMime = $finfo ? finfo_file($finfo, $_FILES['pro-pic']['tmp_name']) : '';
  if ($finfo) {
      finfo_close($finfo);
  }
  if (in_array($ext, $allowedExt, true) && in_array($detectedMime, $allowedMime, true) && ($_FILES["pro-pic"]["size"] < $maxFileSize)) {
    //Determine the path to which we want to save this file
$extb = strtolower(pathinfo($_FILES['pro-pic']['name'], PATHINFO_EXTENSION));
$newFileName = microtime(true).'.'.$extb;
      $tmpImageFolder = dirname(__DIR__).'/uploads/profile-pics/';
      if (!is_dir($tmpImageFolder)) {
          mkdir($tmpImageFolder, 0755, true);
      }
      $tmpImageFolder = $tmpImageFolder . '/' . $newFileName;
      //Check if the file with the same name is already exists on the server
      if (!file_exists($tmpImageFolder)) {
        //Attempt to move the uploaded file to it's new place

        if ((move_uploaded_file($_FILES['pro-pic']['tmp_name'],$tmpImageFolder))) {

$id = (int)NULL;
// Security: Validate and sanitize user ID, default to current user if not provided or invalid
$fkUserId = isset($_POST['editClient']) ? (int)$_POST['editClient'] : (int)$session->userId;
// Security: Only allow users to update their own profile pic, or admins to update any
if ($fkUserId != $session->userId && $session->accountStatus != 1) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized to update this profile picture']);
    exit;
}

$type = $fileType;
$size = $filesize;
$createdDate = strftime("%Y-%m-%d %H:%M:%S", time());
$image =  json_encode($newFileName);
$filename = str_replace('"', "", $image);

$picture = new profilePicture();
$profilePicAlreadyExists=$picture->findByfkUserId($fkUserId);
		foreach($profilePicAlreadyExists as $record)
		{
			
		$profilePicAlreadyExistsId	= $record->id;
		}
if(isset($profilePicAlreadyExistsId)){
    $stmt = $connect->prepare('UPDATE profile_pics SET filename=?, size=?, type=? WHERE id=?');
    $ok = false;
    if ($stmt) {
        $stmt->bind_param('sisi', $filename, $size, $type, $profilePicAlreadyExistsId);
        $ok = $stmt->execute();
        $stmt->close();
    }
} else{
    $stmt = $connect->prepare('INSERT INTO profile_pics (fkUserId, filename, type, size, createdDate) VALUES (?, ?, ?, ?, ?)');
    $ok = false;
    if ($stmt) {
        $stmt->bind_param('issis', $fkUserId, $filename, $type, $size, $createdDate);
        $ok = $stmt->execute();
        $stmt->close();
    }
}
if ($ok) {
	// Return JSON with success status and image URL
	// Ensure $url is available
	if (!isset($url)) {
		global $url;
		if (!isset($url)) {
			// Try to get from settings
			require_once(dirname(__DIR__).'/includes/database-object.php');
			$settings = settings::findById(1);
			$url = isset($settings->url) ? $settings->url : '../';
		}
	}
	// Include lib-initialize.php to get getProfilePicUrl function
	require_once(dirname(__DIR__).'/includes/lib-initialize.php');
	$imageUrl = getProfilePicUrl($filename, 150, 150);
	echo json_encode(['status' => 'ok', 'filename' => $filename, 'url' => $imageUrl]);
} else{
	echo json_encode(['status' => 'error', 'message' => $connect->error]);
}
        } else {
           echo json_encode(['status' => 'error', 'message' => 'A problem occurred during file upload!']);
        }
      } else {
         echo json_encode(['status' => 'error', 'message' => 'File '.$_FILES["pro-pic"]["name"].' already exists']);
      }
  } else {
     echo json_encode(['status' => 'error', 'message' => 'Only .jpg, .png images are accepted for upload']);
  }
} else {
 echo json_encode(['status' => 'error', 'message' => 'No file uploaded']);
}