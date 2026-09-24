<?php
/*
 ================================================================================
   Task Session – Project Management System
   File    : edit.php
   Purpose : Client profile editing functionality
 ================================================================================
 */
ob_start(); 
require_once("../includes/lib-initialize.php");
$title = $lang['Edit Profile'] . " | ". $syatem_title;
include("../templates/header.php");

// Initialize variables
$error = "";
$message = "";

// Only allow logged-in clients
if (!($session->isLoggedIn()) || $_SESSION['accountStatus'] != 2) {
    redirectTo($url."index.php");
}

// Accept both 'editprofile' and 'editClient' as valid query parameters
$editProfileQueryKey = 'editClient';
if(isset($_GET['editprofile'])) {
    $_GET['editClient'] = $_GET['editprofile'];
    $editProfileQueryKey = 'editprofile';
}

if (!function_exists('client_edit_profile_url')) {
    function client_edit_profile_url(int $userId, string $messageStatus, string $queryKey = 'editClient'): string
    {
        $allowedKeys = array('editprofile', 'editClient');
        if (!in_array($queryKey, $allowedKeys, true)) {
            $queryKey = 'editClient';
        }
        return 'edit?' . rawurlencode($queryKey) . '=' . $userId . '&message=' . rawurlencode($messageStatus);
    }
}

if(isset($_GET['editClient']))
{
    $edit_client = (int) $_GET['editClient'];
    
    // Ensure client can only edit their own profile
    if ($edit_client != $session->userId) {
        redirectTo($url."client/profile.php");
    }
    
    $message = "";
    if(isset($_POST['add-client']))
    { 
        $user = user::findById($edit_client); 
        
        $flag = 0;
        
        if($flag == 0)
        {
            $user->id = $edit_client;
            $user->firstName = $_POST['firstName'];
            // Email field is disabled - clients cannot change their email
            // $user->email = $_POST['email'];
            
            // Keep the old password if no new one is entered
            $passwordChanged = false;
            if (!empty($_POST['password'])) {
                $user->password = password_hash($_POST['password'], PASSWORD_BCRYPT);
                $passwordChanged = true;
            } else {
                // Keep the old password if no new one is entered
                $user->password = $user->password;
            }
            
            $user->address = $_POST['address'];
            $user->title = $_POST['title'];
            $user->phone = $_POST['phone'];
            $user->website = $_POST['website'];
            $user->teams_id = $_POST['teams_id'];
            $user->fb = $_POST['fb'];
            $user->city = $_POST['city'];
            $user->state = $_POST['state'];
            $user->zip = $_POST['zip'];
            $user->country = $_POST['country'];
            $saveUser = $user->save();
                
            if($saveUser)
            {
                require_once(__DIR__ . '/../includes/custom-fields/user_profile_values.php');
                save_user_profile_custom_field_values(
                    $connect,
                    (int)$edit_client,
                    'client',
                    isset($_POST['custom_fields']) && is_array($_POST['custom_fields']) ? $_POST['custom_fields'] : null
                );

                // Log password change if password was updated
                if ($passwordChanged) {
                    require_once('../includes/activity_logger.php');
                    ActivityLogger::logPasswordChange($edit_client, $session->userId);
                }

                $picture = new profilePicture();
                $picture->fkUserId = $edit_client;
                $profilePicAlreadyExists = $picture->findByfkUserId($picture->fkUserId);
                $profilePicAlreadyExistsId = null;
                foreach ($profilePicAlreadyExists as $record) {
                    $profilePicAlreadyExistsId = $record->id;
                    $picture->fileToUnlink = $record->filename;
                    break;
                }
                if (isset($profilePicAlreadyExistsId)) {
                    $picture->id = $profilePicAlreadyExistsId;
                }

                $proPicFile = $_FILES['pro-pic'] ?? null;
                $hasNewProfilePic = is_array($proPicFile)
                    && isset($proPicFile['error'])
                    && (int) $proPicFile['error'] === UPLOAD_ERR_OK
                    && !empty($proPicFile['tmp_name']);

                if ($hasNewProfilePic && $picture->attachFile($proPicFile)) {
                    $picture->createdDate = strftime('%Y-%m-%d %H:%M:%S', time());
                    if ($picture->save()) {
                        unset($picture);
                        header('Location:' . client_edit_profile_url((int) $edit_client, 'success', $editProfileQueryKey));
                        exit;
                    }
                    header('Location:' . client_edit_profile_url((int) $edit_client, 'error', $editProfileQueryKey));
                    exit;
                }

                header('Location:' . client_edit_profile_url((int) $edit_client, 'success', $editProfileQueryKey));
                exit;
            }
            else
            {
                header('Location:' . client_edit_profile_url((int) $edit_client, 'fail', $editProfileQueryKey));
                exit;
            }
        }
    }
    
    $toast_flash = null;
    if (isset($_GET['message'])) {
        $msgstatus = (string) $_GET['message'];
        if ($msgstatus === 'success') {
            $toast_flash = array('type' => 'success', 'msg' => $lang['Record updated successfully']);
        } elseif ($msgstatus === 'fail') {
            $toast_flash = array('type' => 'error', 'msg' => $lang['Same record was updated.']);
        } elseif ($msgstatus === 'error') {
            $toast_flash = array('type' => 'error', 'msg' => $lang['Image formate not Supported or image is too big']);
        }
    }

    $id = $session->userId;
    $user = User::findById((int)$session->userId);
    $username = $user->firstName;
    $email = $user->email;
    $account_stat = $user->status;
    $user->regDate;

    $id1 = $edit_client;
    $user1 = User::findById($id1);
    $username1 = $user1->firstName;
    $email1 = $user1->email;
    $account_stat1 = $user1->status;
    $user1->regDate;

    $profilePictureObj1 = profilePicture::findByfkUserId($id1);
    if($profilePictureObj1){
        foreach($profilePictureObj1 as $displayPicture1)
        {
            $profilePic1 = $displayPicture1->filename;
        }
    }

    $userProfileCfInitialValues = [];
    if (isset($connect, $id1) && $connect instanceof mysqli && (int)$id1 > 0) {
        $cfUid = (int)$id1;
        $cfEnt = 'client';
        $cfStmt = @$connect->prepare(
            'SELECT cf.id, cf.field_type, cfv.field_value FROM custom_fields cf
             INNER JOIN user_custom_field_values cfv ON cf.id = cfv.custom_field_id AND cfv.user_id = ?
             WHERE cf.entity_type = ?'
        );
        if ($cfStmt) {
            $cfStmt->bind_param('is', $cfUid, $cfEnt);
            if ($cfStmt->execute()) {
                $cfRes = $cfStmt->get_result();
                if ($cfRes) {
                    while ($cfRow = $cfRes->fetch_assoc()) {
                        $fid = (int)$cfRow['id'];
                        $ft = (string)$cfRow['field_type'];
                        $fv = $cfRow['field_value'];
                        $val = $fv;
                        if ($ft === 'multiple_select' && $fv !== null && $fv !== '') {
                            $d = json_decode($fv, true);
                            $val = is_array($d) ? $d : [];
                        }
                        $userProfileCfInitialValues[] = ['id' => $fid, 'field_type' => $ft, 'value' => $val];
                    }
                }
            }
            $cfStmt->close();
        }
    }
$showUserSettingsNav = true;
if (!isset($settings) || !is_object($settings)) {
    $settings = settings::findById(1);
}
$userSettingsProfilePath = 'client/edit?editprofile=' . (int) $session->userId;
?>
    <div class="page-container">
        <div class="container-fluid">
            <div class="row row-eq-height">
                <?php include("../templates/sidebar.php"); ?>
                <div class="page-content">
                    <?php include('../templates/top-header.php'); ?>
                    <div class="row system-wrap vh-100-1">
                        <?php include('../templates/user-settings-nav.php'); ?>
                        <div class="col-md-9 ss-right">
                    <div class="add-client">
                        <h2 class="page-title"><?php echo $lang['Edit Profile']; ?></h2>
                        <form method="post" action="#" enctype="multipart/form-data" data-custom-fields-guard="1" autocomplete="off">
                            <!-- Ensure POST handler triggers even if submit button is disabled by spinner JS -->
                            <input type="hidden" name="add-client" value="1" />
                            <div class="mb-4 d-flex align-items-center col-gap dp-box upload-profile-pic">
                                <div style="position: relative; display: inline-block;">
                                    <div class="img-uploadwrap">
                                        <?php if(isset($profilePic1)){ ?> 
                                            <img src="<?php echo getProfilePicUrl($profilePic1, 150, 150); ?>" class="img-responsive" alt="Profile Picture" style="width: 100%; height: 100%; object-fit: cover;"/>
                                        <?php } else { 
                                            // Generate initials for avatar
                                            $firstName = $user->firstName ?? '';
                                            $lastName = $user->lastName ?? '';
                                            $initials = strtoupper(substr($firstName, 0, 1) . substr($lastName, 0, 1));
                                            if (empty($initials)) {
                                                $initials = strtoupper(substr($firstName, 0, 2));
                                            }
                                            if (empty($initials)) {
                                                $initials = 'U';
                                            }
                                            
                                            // Generate a color based on the name
                                            $colorIndex = (ord(strtolower($firstName[0] ?? 'a')) - 97) % 8;
                                            $colors = ['#e74c3c', '#3498db', '#2ecc71', '#f39c12', '#9b59b6', '#1abc9c', '#e67e22', '#34495e'];
                                            $color = $colors[$colorIndex];
                                            
                                            echo '<div class="avatar-initials" style="width:150px;height:150px;background-color:' . $color . ';color:white;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:48px;font-weight:bold;">' . $initials . '</div>';
                                        } ?>
                                    </div>
                                    <div class="input-btn alert-savestn pro-pic">  
                                        <span style="font-size: 24px;">+</span>
                                        <input type="file" name="pro-pic" class="pro-pic" data-clientid="<?php echo (int)$_GET['editClient']; ?>" id="imgInp" style="opacity: 0; position: absolute; width: 100%; height: 100%; cursor: pointer;"/> 
                                    </div>  
                                </div>
                                <div class="max-width-300">
                                    <h4><?php echo $lang['Change profile image']; ?></h4>
                                    <p style="color: #757575; font-size: 12px;"><?php echo $lang['Profile image must be a .jpg .png file smaller than 10MB and at least 400px by 400px.']; ?></p>
                                </div>
                            </div>
                            
                            <!-- Account Information -->
                            <div class="row user-info">
                                <div class="col-md-6">
                                    <!-- Full Name -->
                                    <div class="form-group">
                                        <label for="firstName"><?php echo $lang['Full name*']; ?></label>
                                        <input type="text" name="firstName" class="form-control only-alpha" required value="<?php echo htmlspecialchars((string)$user1->firstName, ENT_QUOTES, 'UTF-8'); ?>" autocomplete="off">
                                    </div>
                                    
                                    <!-- Password -->
                                    <div class="form-group">
                                        <label for="password"><?php echo $lang['Password*']; ?></label>
                                        <div class="eyes-row">
                                            <input type="password" name="password" class="form-control passwordfield" placeholder="<?php echo $lang['New password (blank to keep)']; ?>" value="" autocomplete="new-password">
                                            <?php echo ts_password_toggle_html($lang['Show password'] ?? 'Show password'); ?>
                                        </div>
                                    </div>
                                    
                                    <!-- Phone -->
                                    <div class="form-group">
                                        <label for="phone"><?php echo $lang['Phone']; ?></label>
                                        <input type="tel" name="phone" class="form-control only-alpha" value="<?php echo htmlspecialchars((string)$user1->phone, ENT_QUOTES, 'UTF-8'); ?>">
                                    </div>
                                    
                                    <!-- Teams ID -->
                                    <div class="form-group">
                                        <label for="teams_id"><?php echo $lang['Teams ID']; ?></label>
                                        <input type="text" name="teams_id" class="form-control only-alpha" value="<?php echo htmlspecialchars((string)$user1->teams_id, ENT_QUOTES, 'UTF-8'); ?>">
                                    </div>
                                    
                                    <!-- City -->
                                    <div class="form-group">
                                        <label for="city"><?php echo $lang['City']; ?></label>
                                        <input type="text" name="city" value="<?php echo htmlspecialchars((string)$user1->city, ENT_QUOTES, 'UTF-8'); ?>" class="form-control only-alpha">
                                    </div>
                                    
                                    <!-- Zip -->
                                    <div class="form-group">
                                        <label for="zip"><?php echo $lang['Zip']; ?></label>
                                        <input type="text" name="zip" value="<?php echo htmlspecialchars((string)$user1->zip, ENT_QUOTES, 'UTF-8'); ?>" class="form-control only-alpha">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <!-- Email -->
                                    <div class="form-group">
                                        <label for="email"><?php echo $lang['Email*']; ?></label>
                                        <input type="email" name="email" class="form-control" required value="<?php echo htmlspecialchars((string)$user1->email, ENT_QUOTES, 'UTF-8'); ?>" readonly autocomplete="off">
                                    </div>
                                    
                                    <!-- Address -->
                                    <div class="form-group">
                                        <label for="address"><?php echo $lang['Address']; ?></label>
                                        <input type="text" name="address" class="form-control only-alpha" value="<?php echo htmlspecialchars((string)$user1->address, ENT_QUOTES, 'UTF-8'); ?>">
                                    </div>
                                    
                                    <!-- Website -->
                                    <div class="form-group">
                                        <label for="website"><?php echo $lang['Website Url*']; ?></label>
                                        <input type="url" name="website" class="form-control" value="<?php echo htmlspecialchars((string)$user1->website, ENT_QUOTES, 'UTF-8'); ?>">
                                    </div>
                                    
                                    <!-- Facebook URL -->
                                    <div class="form-group">
                                        <label for="Facebook"><?php echo $lang['Facebook Url']; ?></label>
                                        <input type="url" name="fb" placeholder="https://www.facebook.com/User Id" pattern=".*\.facebook\..*" class="form-control" value="<?php echo htmlspecialchars((string)$user1->fb, ENT_QUOTES, 'UTF-8'); ?>">
                                    </div>
                                    
                                    <!-- State -->
                                    <div class="form-group">
                                        <label for="state"><?php echo $lang['State']; ?></label>
                                        <input type="text" name="state" value="<?php echo htmlspecialchars((string)$user1->state, ENT_QUOTES, 'UTF-8'); ?>" class="form-control only-alpha">
                                    </div>
                                    
                                    <!-- Country -->
                                    <div class="form-group">
                                        <label for="country"><?php echo $lang['Country']; ?></label>
                                        <select name="country" class="form-control">
                                            <option value=""><?php echo $lang['Select Country']; ?></option>
                                            <?php foreach($countries as $countrie){
                                                echo '<option ';
                                                if($user1->country == $countrie){echo 'selected ';}
                                                echo 'value="'.$countrie.'">'.$countrie.'</option>';
                                            } ?>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <div id="userProfileCustomFieldsSection" class="form-group full-grid js-user-profile-cf-section d-none" aria-hidden="true">
                                <div class="staff-heading mt-3"><h4>Custom fields</h4></div>
                                <div id="userProfileCustomFieldsContainer"></div>
                            </div>
                            
                            <div class="form-group submit-box">
                                <button type="submit" name="add-client" id="create-staff-btn" class="primary-btn"><?php echo $lang['Save Changes']; ?></button>
                            </div>
                        </form>
                    </div>
                        </div>
                    </div>
                <div class="clearfix"></div>
            </div>
        </div>
    </div>
<script>
window.USER_PROFILE_CF_CONFIG = {
  entityType: 'client',
  listUrl: '../includes/custom-fields/list.php',
  containerSelector: '#userProfileCustomFieldsContainer',
  initialValues: <?php echo json_encode($userProfileCfInitialValues, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE); ?>,
  i18n: {
    fieldRequired: <?php echo json_encode(isset($lang['This field is required.']) ? $lang['This field is required.'] : 'This field is required.', JSON_HEX_TAG | JSON_HEX_APOS); ?>
  }
};
</script>
<script src="../assets/js/user-profile-custom-fields-form.js"></script>
<script src="../assets/js/custom-fields-form-guard.js"></script>
<script>window.__toastFlash=<?php echo json_encode($toast_flash, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;</script>
<script>window.__profilePicToast=<?php echo json_encode(array(
    'success' => $lang['Profile Picture Updated'] ?? 'Profile picture updated.',
    'fail' => $lang['Image formate not Supported or image is too big'] ?? 'Image format not supported or image is too big.',
    'error' => $lang['An error occurred during upload'] ?? 'An error occurred during upload.',
), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;</script>
<script src="<?php echo htmlspecialchars(rtrim((string) $url, '/') . '/assets/js/toast.js', ENT_QUOTES, 'UTF-8'); ?>"></script>
<?php include("../templates/main-footer.php"); } ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    if (window.history && window.history.replaceState && /[?&]message=/.test(window.location.search)) {
        var cleanUrl = window.location.pathname + '?' + <?php echo json_encode($editProfileQueryKey); ?> + '=' + encodeURIComponent(<?php echo json_encode((string)(int)$edit_client); ?>);
        window.history.replaceState({}, document.title, cleanUrl);
    }
});
</script>
