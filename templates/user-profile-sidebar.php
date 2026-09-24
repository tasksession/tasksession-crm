<?php
/*
 ================================================================================
   Task Session – Project Management System
   File    : user-profile-sidebar.php
   Purpose : User profile sidebar template for displaying user information and details
   ================================================================================
*/

// Ensure required variables exist
if (!isset($profileUser) || !$profileUser) {
    echo "<!-- User profile sidebar not shown: profileUser not set -->";
    return;
}

// Helper function to get color index for avatar
function getAvatarColorIndex($userId) {
    return ($userId % 8) + 1;
}

// Helper function to get user initials
function getUserInitials($firstName, $lastName = '') {
    $displayName = trim($firstName . ' ' . $lastName);
    $initials = '';
    
    if (!empty($firstName)) {
        $initials = strtoupper(substr($firstName, 0, 1));
        if (!empty($lastName)) {
            $initials .= strtoupper(substr($lastName, 0, 1));
        }
    } else {
        $initials = strtoupper(substr($displayName, 0, 1));
    }
    
    return empty($initials) ? 'U' : $initials;
}

// Get user profile image
$userProfileImage = null;
if (isset($profileUser->id)) {
    $profileUserId = (int)$profileUser->id;
    $stmt = $connect->prepare("SELECT filename FROM profile_pics WHERE fkUserId = ?");
    $stmt->bind_param("i", $profileUserId);
    $stmt->execute();
    $query = $stmt->get_result();
    $row = $query->fetch_assoc();
    $userProfileImage = $row ? $row['filename'] : null;
    $stmt->close();
}

// Get user initials for avatar fallback
$userInitials = getUserInitials($profileUser->firstName, $profileUser->lastName ?? '');
$userColorIndex = getAvatarColorIndex($profileUser->id);

// Format member since date - use regDate field from User class
$memberSinceDate = 'August 6, 2025'; // Default fallback date

if (isset($profileUser->regDate) && !empty($profileUser->regDate)) {
    $dateValue = $profileUser->regDate;
    // Check if it's a valid date
    if (strtotime($dateValue) !== false) {
        $memberSinceDate = date('F j, Y', strtotime($dateValue));
    }
}

$memberSinceText = ($lang['Member since'] ?? 'Member since') . ': ' . $memberSinceDate;

// Role label for sidebar (admin / client / staff role name from DB)
$roleDisplayName = '';
$profileAcct = isset($profileUser->accountStatus) ? (int)$profileUser->accountStatus : 0;
if ($profileAcct === 1) {
    $roleDisplayName = $lang['Admin - Full access'] ?? 'Admin - Full access';
} elseif ($profileAcct === 2) {
    $roleDisplayName = $lang['Client'] ?? 'Client';
} elseif ($profileAcct === 3) {
    $roleId = isset($profileUser->role_id) ? (int)$profileUser->role_id : 0;
    if ($roleId > 0 && isset($connect) && $connect instanceof mysqli) {
        $roleStmt = $connect->prepare('SELECT name FROM roles WHERE id = ? LIMIT 1');
        if ($roleStmt) {
            $roleStmt->bind_param('i', $roleId);
            $roleStmt->execute();
            $roleRes = $roleStmt->get_result();
            $roleRow = $roleRes ? $roleRes->fetch_assoc() : null;
            $roleStmt->close();
            if ($roleRow && !empty($roleRow['name'])) {
                $roleDisplayName = $roleRow['name'];
            }
        }
    }
    if ($roleDisplayName === '') {
        $roleDisplayName = $lang['No role assigned'] ?? 'No role assigned';
    }
} else {
    $roleDisplayName = '—';
}

$isOnline = function_exists('user_presence_from_user')
    ? user_presence_from_user($profileUser)
    : (
        isset($profileUser->last_seen) &&
        isset($profileUser->session_status) &&
        $profileUser->session_status === 'online' &&
        (time() - (int)$profileUser->last_seen) < 300
    );

$profileSidebarCustomFields = [];
if (isset($connect, $profileUser) && $connect instanceof mysqli && in_array((int)$profileUser->accountStatus, [1, 2, 3], true)) {
    $cfEntity = ((int)$profileUser->accountStatus === 2)
        ? 'client'
        : (((int)$profileUser->accountStatus === 3) ? 'staff' : 'admin');
    $cfUid = (int)$profileUser->id;
    $cfSql = 'SELECT cf.label, cf.field_type, cf.list_items, cfv.field_value
        FROM custom_fields cf
        INNER JOIN user_custom_field_values cfv ON cf.id = cfv.custom_field_id AND cfv.user_id = ?
        WHERE cf.entity_type = ?
        ORDER BY cf.sort_order ASC, cf.id ASC';
    $cfStmt = @$connect->prepare($cfSql);
    if ($cfStmt) {
        $cfStmt->bind_param('is', $cfUid, $cfEntity);
        if ($cfStmt->execute()) {
            $cfRes = $cfStmt->get_result();
            if ($cfRes) {
                while ($row = $cfRes->fetch_assoc()) {
                    $ft = (string)($row['field_type'] ?? 'text');
                    $raw = $row['field_value'];
                    $has = false;
                    if ($raw !== null && $raw !== '') {
                        if ($ft === 'multiple_select') {
                            $d = json_decode((string)$raw, true);
                            $has = is_array($d) && count($d) > 0;
                        } else {
                            $has = true;
                        }
                    }
                    if (!$has) {
                        continue;
                    }
                    $label = (string)($row['label'] ?? '');
                    $display = '';
                    if ($ft === 'checkbox') {
                        $display = ((string)$raw === '1') ? ($lang['Yes'] ?? 'Yes') : ($lang['No'] ?? 'No');
                    } elseif ($ft === 'multiple_select') {
                        $d = json_decode((string)$raw, true);
                        $display = is_array($d) ? htmlspecialchars(implode(', ', array_map('strval', $d)), ENT_QUOTES, 'UTF-8') : '';
                    } elseif ($ft === 'link' && $raw !== '') {
                        $u = htmlspecialchars((string)$raw, ENT_QUOTES, 'UTF-8');
                        $display = '<a href="' . $u . '" target="_blank" rel="noopener noreferrer">' . $u . '</a>';
                    } elseif ($ft === 'date' && $raw !== '') {
                        $ts = strtotime((string)$raw);
                        $display = $ts ? htmlspecialchars(date('M j, Y', $ts), ENT_QUOTES, 'UTF-8') : htmlspecialchars((string)$raw, ENT_QUOTES, 'UTF-8');
                    } else {
                        $display = htmlspecialchars((string)$raw, ENT_QUOTES, 'UTF-8');
                    }
                    if ($display !== '') {
                        $profileSidebarCustomFields[] = ['label' => htmlspecialchars($label, ENT_QUOTES, 'UTF-8'), 'html' => $display];
                    }
                }
            }
        }
        $cfStmt->close();
    }
}
?>

<!-- Add Sidebar Toggle Button -->
<div class="filter-btn">
<button class="sidebar-toggle primary-btn d-md-none">
    <?php echo $lang['View Profile Details']; ?>
</button></div>
<!-- Add Overlay -->
<div class="sidebar-overlay"></div>
	<div class="sidebar-admin col-xl-3 col-lg-4 col-md-12 bg-white max-w-400 full-heights left-col project-sidebar" id="project-sidebar">
	<div class="cross-mobile ">
			<?php echo ts_icon('close', 'w-4'); ?>
				</div> 
				<button class="sidebar-shrink-btn" id="projectSidebarShrinkBtn" title="Shrink sidebar">
				  <?php echo ts_icon('chevron-left', 'w-2'); ?>
				</button>
	<div class="sidebar-shirk"></div>
	<div class="scroll-bar mb-height full-height pd-30 stikcy-sidebar">
        
        <!-- User Profile Card -->
        <div class="cs-card">
            <div class="card-body pt-0 text-center profile-image">
                <div class="user-profile-avatar mb-3">
                    <div class="profile-img-wrapper" style="position:relative; display:inline-block;">
                        <span class="online-dot<?php echo $isOnline ? ' online' : ' offline'; ?>" style="position:absolute;top:0;right: 10px;width: 14px;height: 14px;"></span>
                        <?php if($userProfileImage): ?>
                            <img src="<?php echo $url; ?>includes/thumbnail.php?src=<?php echo $url; ?>uploads/profile-pics/<?php echo $userProfileImage; ?>&h=100&w=100" class="rounded-circle img-fluid profile-img" alt="<?php echo $profileUser->firstName; ?>">
                        <?php else: ?>
                            <?php
                            // Use global avatar helper for initials if available, else fallback to local markup
                            if (function_exists('getUserAvatarHtml')) {
                                echo getUserAvatarHtml($profileUser->id, $profileUser->firstName, $profileUser->lastName ?? '', 80, 80, 'img-fluid rounded-circle profile-img', $profileUser->firstName);
                            } else {
                            ?>
                                <div class="avatar-initials avatar-initials-large color-<?php echo $userColorIndex; ?>" style="width: 80px; height: 80px; font-size: 32px; display: flex; align-items: center; justify-content: center; margin: 0 auto;">
                                    <?php echo $userInitials; ?>
                                </div>
                            <?php } ?>
                        <?php endif; ?>
                    </div>
                    </div>
                
                <h2 class="user-name card-title mb-1 font-size-20"><?php echo $profileUser->firstName . ' ' . ($profileUser->lastName ?? ''); ?></h2>
                <p class="user-id text-muted mb-2"><?php echo htmlspecialchars($memberSinceText); ?></p>
                <p class="member-role-line grey font-size-12 mb-2"><strong><?php echo htmlspecialchars($lang['Role'] ?? 'Role'); ?>:</strong> <?php echo htmlspecialchars($roleDisplayName); ?></p>
                <div class="grey border-btn d-flex justify-content-center">
                    <a class="left-center d-inline-flex w-auto" href="edit?editprofile=<?php echo (int)$profileUser->id; ?>">
                        <?php echo htmlspecialchars($lang['Edit Profile'] ?? 'Edit profile'); ?><?php echo ts_icon('arrow-right', 'w-2'); ?>
                    </a>
                </div>
                
            </div>
        </div>
        
        <!-- About Section -->
		<div class="cs-card contact-info">
            <div class="card-body">
                <div class="title-head mb-3">Contact Information</div>
                <div class="contact-infoo">
                    <!-- Email -->
                    <div class="contact-item d-flex align-items-center mb-2">
                        <?php echo ts_icon('emails', 'text-muted'); ?>
                        <span><strong><?php echo $lang['Email']; ?>:</strong> <?php echo $profileUser->email; ?></span>
                    </div>
                    
                    <!-- Phone -->
                    <div class="contact-item d-flex align-items-center mb-2">
                        <?php echo ts_icon('phone', 'text-muted'); ?>
                        <span><strong><?php echo $lang['Phone']; ?>:</strong> <?php echo !empty($profileUser->phone) ? $profileUser->phone : 'N/A'; ?></span>
                    </div>
                </div>
			</div>		   
		</div>		   

		<div class="cs-card contact-info">
            <div class="card-body">
                <div class="title-head mb-3">Address</div>
					   
                    <!-- Address -->
                    <div class="contact-item d-flex align-items-start mb-2">
                        <?php echo ts_icon('map-pin', 'text-muted'); ?>
                        <span><strong class="d-block"><?php echo $lang['Address']; ?>:</strong> <?php echo !empty($profileUser->address) ? $profileUser->address : 'N/A'; ?></span>
                    </div>
                         <!-- Location -->
                    <div class="contact-item d-flex align-items-center mb-2">
                        <?php echo ts_icon('map-pin', 'text-muted'); ?>
                        <span><strong class="d-block"><?php echo $lang['Location']; ?>:</strong> 
                            <?php 
                            $location = array_filter([
                                $profileUser->city,
                                $profileUser->state,
                                $profileUser->country
                            ]);
                            echo !empty($location) ? htmlspecialchars(implode(', ', $location)) : 'N/A';
                            ?>
                        </span>
                    </div>
			</div>		   
		</div>		   

					
		<div class="cs-card contact-info">
            <div class="card-body">
                <div class="title-head mb-3">Website</div>
                    <!-- Website -->
                    <div class="contact-item d-flex align-items-start mb-2">
                       <?php echo ts_icon('globe', 'text-muted'); ?>

                        <span><strong class="d-block"><?php echo $lang['Website']; ?>:</strong> <?php echo !empty($profileUser->website) ? $profileUser->website : 'N/A'; ?></span>
                    </div>
                    
                    <!-- Teams ID -->
                    <div class="contact-item d-flex align-items-center mb-2">
                    <?php echo ts_icon('identification', 'text-muted'); ?>

                        <span><strong><?php echo $lang['Teams ID']; ?>:</strong> <?php echo !empty($profileUser->teams_id) ? $profileUser->teams_id : 'N/A'; ?></span>
                    </div>

                    <!-- User ID: click value to copy (same line style as Teams ID) -->
                    <div class="contact-item d-flex align-items-center mb-2">
                    <?php echo ts_icon('identification', 'text-muted'); ?>

                        <span><strong><?php echo htmlspecialchars($lang['User ID'] ?? 'User ID', ENT_QUOTES, 'UTF-8'); ?>:</strong>
                            <button type="button" class="p-0 m-0 border-0 bg-transparent text-body shadow-none align-baseline text-decoration-none user-select-all" id="profileSidebarUserIdBtn" style="font:inherit;cursor:copy" title="<?php echo htmlspecialchars($lang['Click to copy'] ?? 'Click to copy', ENT_QUOTES, 'UTF-8'); ?>"><span id="profileSidebarUserIdCode" translate="no">#<?php echo (int)$profileUser->id; ?></span></button></span>
                    </div>
			</div>
		</div>

        <?php if (!empty($profileSidebarCustomFields)): ?>
        <div class="cs-card contact-info">
            <div class="card-body">
                <div class="title-head mb-3"><?php echo htmlspecialchars($lang['Custom fields'] ?? 'Custom fields', ENT_QUOTES, 'UTF-8'); ?></div>
                <?php foreach ($profileSidebarCustomFields as $cfRow): ?>
                    <div class="contact-item d-flex align-items-start mb-2">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="16" height="16" class="me-2 text-muted flex-shrink-0 mt-1" fill="currentColor" aria-hidden="true">
                            <circle cx="12" cy="12" r="2.25"></circle>
                        </svg>
                        <span><strong class="d-block"><?php echo $cfRow['label']; ?>:</strong> <?php echo $cfRow['html']; ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <script>
        (function () {
            var btn = document.getElementById('profileSidebarUserIdBtn');
            var code = document.getElementById('profileSidebarUserIdCode');
            var promptTitle = <?php echo json_encode($lang['Copy User ID'] ?? 'Copy User ID', JSON_HEX_TAG | JSON_HEX_APOS | JSON_UNESCAPED_UNICODE); ?>;
            if (!btn || !code) return;
            btn.addEventListener('click', function () {
                var t = (code.textContent || '').trim();
                if (!t) return;
                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(t).catch(function () {
                        window.prompt(promptTitle, t);
                    });
                } else {
                    window.prompt(promptTitle, t);
                }
            });
        })();
        </script>
			
				 
				</div>		   
						</div>		   
						 

         
     
        
        
