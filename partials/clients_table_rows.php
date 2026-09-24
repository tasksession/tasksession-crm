<?php
/**
 * Partial: Clients table rows (admin/clients.php?view=table)
 *
 * Expects:
 * - $recentlyRegisteredUsers (array of user objects)
 * - $invoiceModuleEnabled (bool)
 *
 * Optional:
 * - $clientsTableLinkBase (string, default '')
 * - $clientsTableBulkCheckboxDisabled (bool, default false)
 * - $clientsTableCtx (array for last-login formatting)
 */
global $connect, $lang, $database;

if (!isset($clientsTableLinkBase)) {
    $clientsTableLinkBase = '';
}
if (!isset($clientsTableBulkCheckboxDisabled)) {
    $clientsTableBulkCheckboxDisabled = false;
}
if (!isset($clientsTableShowBulkCheckbox)) {
    $clientsTableShowBulkCheckbox = true;
}
if (!isset($clientsTableShowSerial)) {
    $clientsTableShowSerial = true;
}

$clientsTableLinkBase = (string) $clientsTableLinkBase;
$bulkDisabledAttr = !empty($clientsTableBulkCheckboxDisabled) ? ' disabled' : '';

if (!function_exists('mega_search_people_last_login_map')) {
    require_once dirname(__DIR__) . '/includes/mega_search_render.php';
}

if ($recentlyRegisteredUsers == null || (is_array($recentlyRegisteredUsers) && $recentlyRegisteredUsers === [])) {
    $tableColspan = !empty($invoiceModuleEnabled) ? 9 : 8;
    if (empty($clientsTableShowBulkCheckbox)) {
        $tableColspan--;
    }
    if (empty($clientsTableShowSerial)) {
        $tableColspan--;
    }
    echo '<tr><td colspan="' . (int) $tableColspan . '">' . ($lang['No records Found!'] ?? 'No records Found!') . '</td></tr>';
    return;
}

$clientIds = [];
foreach ($recentlyRegisteredUsers as $u) {
    if (isset($u->id)) {
        $clientIds[] = (int) $u->id;
    }
}
$lastLoginMap = ($connect instanceof mysqli)
    ? mega_search_people_last_login_map($connect, $clientIds)
    : [];

$rowSerial = isset($start_from) ? (int) $start_from : 0;
foreach ($recentlyRegisteredUsers as $recentlyRegisteredUser) {
    $rowSerial++;
    $profilePictureObj = profilePicture::findByfkUserId($recentlyRegisteredUser->id);

    $avatarData = ['type' => 'initials', 'url' => ''];
    $filenamePicture = '';
    if ($profilePictureObj) {
        foreach ($profilePictureObj as $displayPicture) {
            $filenamePic = $displayPicture->filename;
            $filenamePicture = getProfilePicUrl($filenamePic, 130, 130);
        }
        if (!empty($filenamePicture)) {
            $avatarData = ['type' => 'image', 'url' => $filenamePicture];
        }
    } else {
        $avatarData = getUserAvatarData(
            $recentlyRegisteredUser->id,
            $recentlyRegisteredUser->firstName,
            $recentlyRegisteredUser->lastName ?? '',
            130,
            130
        );
        $filenamePicture = $avatarData['type'] === 'image' ? $avatarData['url'] : '';
    }

    $isOnline = function_exists('user_presence_from_user')
        ? user_presence_from_user($recentlyRegisteredUser)
        : (
            isset($recentlyRegisteredUser->last_seen)
            && isset($recentlyRegisteredUser->session_status)
            && $recentlyRegisteredUser->session_status === 'online'
            && (time() - (int) $recentlyRegisteredUser->last_seen) < 300
        );

    $clientId = (int) $recentlyRegisteredUser->id;
    $accountStatus = (int) ($recentlyRegisteredUser->accountStatus ?? 0);
    $projectCount = 0;
    $taskCount = 0;
    $invoiceCount = 0;

    if ($accountStatus === 2 && $connect instanceof mysqli) {
        $projectCountResult = mysqli_query($connect, 'SELECT COUNT(*) AS cnt FROM projects WHERE c_id = ' . $clientId);
        $projectCountRow = $projectCountResult ? mysqli_fetch_assoc($projectCountResult) : null;
        $projectCount = $projectCountRow ? (int) ($projectCountRow['cnt'] ?? 0) : 0;

        $projectIds = [];
        $projectIdsResult = mysqli_query($connect, 'SELECT p_id FROM projects WHERE c_id = ' . $clientId);
        if ($projectIdsResult) {
            while ($row = mysqli_fetch_assoc($projectIdsResult)) {
                $projectIds[] = (int) ($row['p_id'] ?? 0);
            }
        }
        if ($projectIds !== []) {
            $projectIdsStr = implode(',', $projectIds);
            $taskCountResult = mysqli_query($connect, 'SELECT COUNT(*) AS cnt FROM tasks WHERE project_id IN (' . $projectIdsStr . ')');
            $taskCountRow = $taskCountResult ? mysqli_fetch_assoc($taskCountResult) : null;
            $taskCount = $taskCountRow ? (int) ($taskCountRow['cnt'] ?? 0) : 0;
        }

        if (!empty($invoiceModuleEnabled)) {
            $invoiceCountQuery = 'SELECT COUNT(*) AS cnt FROM milestones m
                LEFT JOIN projects p ON m.p_id = p.p_id
                WHERE ((m.p_id IS NOT NULL AND m.p_id > 0 AND p.p_id IS NOT NULL AND (p.c_id = ' . $clientId . ' OR p.main_client_id = ' . $clientId . '))
                OR ((m.p_id IS NULL OR m.p_id = 0) AND m.c_id = ' . $clientId . '))';
            $invoiceCountResult = mysqli_query($connect, $invoiceCountQuery);
            $invoiceCountRow = $invoiceCountResult ? mysqli_fetch_assoc($invoiceCountResult) : null;
            $invoiceCount = $invoiceCountRow ? (int) ($invoiceCountRow['cnt'] ?? 0) : 0;
        }
    }

    $memberSinceText = '';
    if (isset($recentlyRegisteredUser->regDate) && !empty($recentlyRegisteredUser->regDate)) {
        $ts = strtotime((string) $recentlyRegisteredUser->regDate);
        if ($ts) {
            $memberSinceText = date('F d, Y', $ts);
        }
    }

    $lastLoginText = mega_search_format_last_login($lastLoginMap[$clientId] ?? null, $clientsTableCtx);
    $profileUrl = htmlspecialchars($clientsTableLinkBase . 'profile?user_id=' . $clientId, ENT_QUOTES, 'UTF-8');
    ?>
    <tr>
        <?php if (!empty($clientsTableShowBulkCheckbox)): ?>
        <td>
            <div class="checkbox check-invoice">
                <input type="checkbox" id="client-<?php echo $clientId; ?>" name="client-checkbox" class="client-bulk-checkbox" value="<?php echo $clientId; ?>"<?php echo $bulkDisabledAttr; ?>>
                <label for="client-<?php echo $clientId; ?>"></label>
            </div>
        </td>
        <?php endif; ?>
        <?php if (!empty($clientsTableShowSerial)): ?>
        <td><?php echo (int) $rowSerial; ?></td>
        <?php endif; ?>
        <td style="text-align: left;">
            <div class="d-flex align-items-center col-gap-10" style="min-width: 270px; text-align: left;">
                <div class="profile-img-wrapper" style="position:relative; display:inline-block;">
                    <span class="online-dot<?php echo $isOnline ? ' online' : ' offline'; ?>"></span>
                    <?php
                    if ($avatarData['type'] === 'image') {
                        echo '<img src="' . htmlspecialchars($filenamePicture, ENT_QUOTES, 'UTF-8') . '" class="img-fluid profile-img" style="width:36px; height:36px; border-radius:50%;" />';
                    } else {
                        echo getUserAvatarHtml(
                            $recentlyRegisteredUser->id,
                            $recentlyRegisteredUser->firstName,
                            $recentlyRegisteredUser->lastName ?? '',
                            36,
                            36,
                            'img-fluid rounded-circle',
                            $recentlyRegisteredUser->firstName
                        );
                    }
                    ?>
                </div>
                <div style="text-align: left;">
                    <div><?php echo htmlspecialchars((string) $recentlyRegisteredUser->firstName, ENT_QUOTES, 'UTF-8'); ?></div>
                    <?php if ($memberSinceText !== ''): ?>
                        <div class="grey font-size-12">
                            <?php echo htmlspecialchars($lang['Member since'] ?? 'Member since', ENT_QUOTES, 'UTF-8'); ?>: <?php echo htmlspecialchars($memberSinceText, ENT_QUOTES, 'UTF-8'); ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </td>
        <td style="text-align: left;"><?php echo htmlspecialchars((string) $recentlyRegisteredUser->email, ENT_QUOTES, 'UTF-8'); ?></td>
        <td><?php echo (int) $projectCount; ?></td>
        <td><?php echo (int) $taskCount; ?></td>
        <?php if (!empty($invoiceModuleEnabled)): ?>
        <td><?php echo (int) $invoiceCount; ?></td>
        <?php endif; ?>
        <td><?php echo htmlspecialchars($lastLoginText, ENT_QUOTES, 'UTF-8'); ?></td>
        <td>
            <a href="<?php echo $profileUrl; ?>" class="btn btn-outline-grey justify-content-center">
                <?php echo htmlspecialchars($lang['View Profile'] ?? 'View profile', ENT_QUOTES, 'UTF-8'); ?>
            </a>
        </td>
    </tr>
    <?php
}
