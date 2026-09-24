	<?php
/*
 ================================================================================
   Task Session – Project Management System
   File    : private-notes.php
   Purpose : Manages private notes and personal annotations
 ================================================================================
 */
ob_start(); 
require_once("../includes/lib-initialize.php");
$title = $lang['Private Notes'] . " | " . $syatem_title;
include("../templates/header.php");

// Only allow logged-in clients
if (!($session->isLoggedIn()) || $_SESSION['accountStatus'] != 2) {
    redirectTo($url."index.php");
}

$id = $session->userId; // id of the current logged in user 
$user = User::findById((int)$id); //take the record of current user in an object array 
$username = $user->firstName;
$email = $user->email;

$user_id = $_SESSION['userId'];
if (!isset($user_id) || empty($user_id)) {
    die($lang['User not logged in or userId not set.']);
}

// Handle actions: create, update, delete
$message = "";
$edit_note = null;

// Create or update note
if (isset($_POST['save_note'])) {
    $note_id = isset($_POST['note_id']) ? intval($_POST['note_id']) : 0;
    $title_val = trim($_POST['title']);
    $content = trim(sanitize_tinymce_content($_POST['note_content'] ?? ''));
    if (empty($content)) {
        $message = "<div class='alert alert-danger'>" . $lang['Note content cannot be empty.'] . "</div>";
    } else if ($note_id > 0) {
        // Update - encrypt content before saving
        $encrypted_content = encryptString($content);
        $sql = "UPDATE private_notes SET title='{$database->escapeValue($title_val)}', content='{$database->escapeValue($encrypted_content)}', updated_at=NOW() WHERE id={$note_id} AND user_id={$user_id}";
        $result = $database->query($sql);
        $message = $result ? "<div class='alert alert-success'>" . $lang['Note updated!'] . "</div>" : "<div class='alert alert-danger'>" . $lang['Error updating note.'] . "</div>";
    } else {
        // Create - encrypt content before saving
        $color_to_assign = '';
        if (isset($_GET['color_filter']) && $_GET['color_filter'] !== '') {
            $color_to_assign = $_GET['color_filter'];
        }
        $encrypted_content = encryptString($content);
        $sql = "INSERT INTO private_notes (user_id, title, content, color, created_at, updated_at) VALUES ({$user_id}, '{$database->escapeValue($title_val)}', '{$database->escapeValue($encrypted_content)}', '{$color_to_assign}', NOW(), NOW())";
        $result = $database->query($sql);
        if ($result) {
            $new_note_id = $database->insertId();
            $msg = '';
            if ($color_to_assign) {
                $msg = "&msg=created_with_color&color=" . urlencode($color_to_assign);
            }
            header("Location: private-notes.php?note_id=" . $new_note_id . $msg);
            exit;
        }
        $message = "<div class='alert alert-danger'>" . $lang['Error creating note.'] . "</div>";
    }
}

// Delete note
if (isset($_GET['delete'])) {
    $delete_id = intval($_GET['delete']);
    $sql = "DELETE FROM private_notes WHERE id={$delete_id} AND user_id={$user_id}";
    $database->query($sql);
    $message = "<div class='alert alert-success'>" . $lang['Note deleted!'] . "</div>";
}

// Handle color change
if (isset($_GET['color']) && isset($_GET['note_id'])) {
    $color = preg_replace('/[^a-zA-Z0-9_-]/', '', $_GET['color']);
    $color_allowed = ['blue', 'green', 'yellow'];
    if (in_array($color, $color_allowed)) {
        $note_id = intval($_GET['note_id']);
        $sql = "UPDATE private_notes SET color='{$color}' WHERE id={$note_id} AND user_id={$user_id}";
        $database->query($sql);
        header("Location: private-notes.php?note_id=" . $note_id);
        exit;
    }
}

// Color filter logic
$colorFilter = isset($_GET['color_filter']) ? $_GET['color_filter'] : '';
$allowedColors = ['blue', 'green', 'yellow'];

// Show message if note was created with color
if (isset($_GET['msg']) && $_GET['msg'] === 'created_with_color' && isset($_GET['color']) && in_array($_GET['color'], $allowedColors)) {
    $message = "<div class='alert alert-success'>" . $lang['Note created with color'] . " <strong>" . htmlspecialchars(ucfirst($_GET['color'])) . "</strong>!</div>";
}

$searchQuery = isset($_GET['search']) ? trim($_GET['search']) : '';
$sql = "SELECT * FROM private_notes WHERE user_id={$user_id}";
if (in_array($colorFilter, $allowedColors)) {
    $sql .= " AND color='{$colorFilter}'";
}
if ($searchQuery !== '') {
    $safeSearch = $database->escapeValue($searchQuery);
    $sql .= " AND title LIKE '%{$safeSearch}%'";
}
$sql .= " ORDER BY updated_at DESC";
$result = $database->query($sql);
$notes = [];
while ($row = $database->fetchArray($result)) {
    // Decrypt content for display
    if (!empty($row['content'])) {
        $row['content'] = decryptString($row['content']);
    }
    $notes[] = $row;
}

// Post-process search in decrypted content if search query exists
if ($searchQuery !== '') {
    $filtered_notes = [];
    foreach ($notes as $note) {
        if (stripos($note['title'], $searchQuery) !== false || 
            stripos($note['content'], $searchQuery) !== false) {
            $filtered_notes[] = $note;
        }
    }
    $notes = $filtered_notes;
}

// Fetch note to edit/view
if (isset($_GET['note_id'])) {
    $note_id = intval($_GET['note_id']);
    $sql = "SELECT * FROM private_notes WHERE id={$note_id} AND user_id={$user_id} LIMIT 1";
    $result = $database->query($sql);
    $edit_note = $database->fetchArray($result);
    // Decrypt content for editing
    if ($edit_note && !empty($edit_note['content'])) {
        $edit_note['content'] = decryptString($edit_note['content']);
    }
} else if (isset($_GET['new'])) {
    // Show blank form for new note
    $edit_note = null;
} else if (!empty($notes)) {
    // If no note_id is set, show the most recently created note by default
    $edit_note = $notes[0];
}

// Get total notes count for the current user
$sql_count = "SELECT COUNT(*) as total FROM private_notes WHERE user_id={$user_id}";
$result_count = $database->query($sql_count);
$row_count = $database->fetchArray($result_count);
$total_notes = $row_count['total'];
?>

<div class="page-container vh-100">
    <div class="container-fluid vh-100">
        <div class="row row-eq-height vh-100">
            <?php include("../templates/sidebar.php"); ?>
            <div class="page-content">
                <?php include('../templates/top-header.php'); ?>
				<div class="row bg-grey">
                    <div class="col-md-12 project-tabs">
						<div class="row">
							<div class="project-tabs-header">
								<div class="scrollable-tabs-container d-flex col-gap-40 col-gap-40-sep">
									<div class="main-heading d-flex align-items-center justify-content-between">
										<div><h1> <?php echo $lang['Private Notes']; ?><span> (<?php echo $total_notes; ?>)</span></h1></div>
									</div>
							<?php
								$showBack = false;
								if (
									(isset($_GET['search']) && trim($_GET['search']) !== '') ||
									(isset($_GET['color_filter']) && in_array($_GET['color_filter'], ['blue', 'green', 'yellow'])) ||
									(isset($_GET['new']))
								) {
									$showBack = true;
								}
								?>
								<div class="icon-container sep">
								 <?php if ($showBack): ?>
										<a href="private-notes.php"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
								  <path stroke-linecap="round" stroke-linejoin="round" d="M9 15 3 9m0 0 6-6M3 9h12a6 6 0 0 1 0 12h-3" />
								</svg>
								<?php echo $lang['Back']; ?></a>
								  <?php endif; ?>

									<?php if (!isset($_GET['new'])): ?>
										<a href="private-notes.php?new=1">
								 <?php echo $lang['Create New Note']; ?>  <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="dropdown-toggle-icon h-6"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"></path></svg>
										</a>
									<?php endif; ?>

								</div>
					 	   </div>   
					   </div> 
										<div class="search">
                                            <div class="search-icon" onclick="toggleSearch()">
                                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-6">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                                                </svg>
                                            </div>
                                            <form method="GET" action="" class="search-form" id="searchForm">
                                                <div class="input-group">
                                                   <input type="text" id="task-search" name="search" class="form-control" placeholder="<?php echo $lang['Search notes...']; ?>" value="<?php echo htmlspecialchars($searchQuery ?? ''); ?>">

                                                    <button type="submit" class="search-icon">
                                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-6">
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                                                        </svg>
                                                    </button>
                                                    <?php if(isset($searchQuery) && !empty($searchQuery)): ?>
                                                        <a href="private-notes.php" 
                                                           class="cross">
                                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-6">
                                                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                                                            </svg>
                                                        </a>
                                                    <?php endif; ?>
                                                </div>
                                            </form>
                                        </div>
											<div class="edit-overview-btn">
													 <td class="extra-height">
															                                    <div class="action-toggle" data-bs-toggle="collapse" data-bs-target="#color-menu">
																<span class="action-text"><?php echo $lang['Color Filter']; ?></span><span class="mobile-ellipsis"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
																  <path stroke-linecap="round" stroke-linejoin="round" d="M8.625 12a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H8.25m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H12m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0h-.375M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
																</svg></span>
																<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
																  <path stroke-linecap="round" stroke-linejoin="round" d="M12 3c2.755 0 5.455.232 8.083.678.533.09.917.556.917 1.096v1.044a2.25 2.25 0 0 1-.659 1.591l-5.432 5.432a2.25 2.25 0 0 0-.659 1.591v2.927a2.25 2.25 0 0 1-1.244 2.013L9.75 21v-6.568a2.25 2.25 0 0 0-.659-1.591L3.659 7.409A2.25 2.25 0 0 1 3 5.818V4.774c0-.54.384-1.006.917-1.096A48.32 48.32 0 0 1 12 3Z" />
																</svg>
															</div>
													 <div id="color-menu" class="toggle-action justify collapse shadow-dept">
																<ul>
																<li class="<?php echo !isset($_GET['color_filter']) ? 'active' : ''; ?>">
																		<a href="private-notes.php">
																			<span><?php echo $lang['All Colors']; ?></span>
																		</a>
																	</li>
																	<li class="<?php echo (isset($_GET['color_filter']) && $_GET['color_filter'] == 'blue') ? 'active' : ''; ?>">
																		<a href="private-notes.php?color_filter=blue">
																			<span style="color:#007bff;"><?php echo $lang['Blue']; ?></span>
																		</a>
																	</li>
																	<li class="<?php echo (isset($_GET['color_filter']) && $_GET['color_filter'] == 'green') ? 'active' : ''; ?>">
																		<a href="private-notes.php?color_filter=green">
																			<span style="color:#28a745;"><?php echo $lang['Green']; ?></span>
																		</a>
																	</li>
																	<li class="<?php echo (isset($_GET['color_filter']) && $_GET['color_filter'] == 'yellow') ? 'active' : ''; ?>">
																		<a href="private-notes.php?color_filter=yellow">
																			<span style="color:#ffc107;"><?php echo $lang['Yellow']; ?></span>
																		</a>
																	</li>
																</ul>
															</div>
														</td>
													</div>
				            <div class="d-none d-md-block">
								<?php
							$is_editing = (isset($edit_note['id']) && $edit_note['id']) || (isset($_GET['note_id']) && intval($_GET['note_id']));
							$is_creating = isset($_GET['new']);
							if ($is_editing) {
								// Update Note button
								echo '<button type="button" class="btn primary-btn" onclick="document.getElementById(\'hiddenSubmitBtn\').click();">Update Note</button>';
							} else if ($is_creating) {
								// Create Note button (submit form)
								echo '<button type="button" class="btn primary-btn" onclick="document.getElementById(\'hiddenSubmitBtn\').click();">Save Note <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="dropdown-toggle-icon h-6"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"></path></svg></button>';
							} else {
								// Default: show Create Note link
								echo '<a href="private-notes.php?new=1" class="btn primary-btn">Create Note <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="dropdown-toggle-icon h-6"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"></path></svg></a>';
							}
							?>   
							</div>
						</div>
                       </div>
                    </div>
                <div class="row  h-100"> 
                    <div class="col-md-12 ">
                        <div class="row  h-100">
                            <div class="col-lg-3 vh-100 br-right bg-white pd-0">
                                <ul class="list-group ">
                                    <?php if (empty($notes)): ?>
                                        <li class="list-group-item text-center text-muted" style="padding: 40px 10px;">
                                            <?php echo $lang['No notes found.']; ?>

                                        </li>
                                    <?php else: ?>
                                        <?php foreach ($notes as $note): ?>
                                            <?php
                                                $colorClass = '';
                                                if (!empty($note['color'])) {
                                                    $colorClass = 'note-card-' . htmlspecialchars($note['color']);
                                                }
                                            ?>
                                            <li class="list-group-item note-card <?php echo $colorClass; ?><?php if (isset($edit_note['id']) && $edit_note['id'] == $note['id']) echo ' active'; ?>" style="position: relative;">
                                                <a href="private-notes.php?note_id=<?php echo $note['id']; ?>" class="note-card-link" style="display: block; text-decoration: none; color: inherit; width: 100%; height: 100%;">
                                                    <div>
                                                        <strong class="card-title mb-1"><?php echo htmlspecialchars($note['title']); ?></strong>
                                                        <div class="note-preview mb-3">
                                                            <?php 
                                                            $preview = strip_tags($note['content']);
                                                            if (strlen($preview) > 80) {
                                                                $preview = mb_substr($preview, 0, 80) . '...';
                                                            }
                                                            echo htmlspecialchars($preview);
                                                            ?>
                                                        </div>
														
											   <div class=" d-flex ">
														                                                     
                                                            <?php echo date('Y-m-d', strtotime($note['created_at'])); ?>
                                                            <?php if ($note['created_at'] != $note['updated_at']) { ?>
                                                                &nbsp;|&nbsp; <span> <b><?php echo $lang['Updated']; ?>:</b> <?php echo date('Y-m-d', strtotime($note['updated_at'])); ?></span>
                                                            <?php } ?>
												</div>                                                   
												</div>
                                                </a>
                                                <div class="dropdown note-card-menu" style="position: absolute; top: 10px; right: 15px;">
                                                    <button class="btn-dots dropdown-toggle" type="button" id="dropdownMenu<?php echo $note['id']; ?>" data-bs-toggle="dropdown" aria-expanded="false" style="color: #888; text-decoration: none;">
                                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6">
																		<path stroke-linecap="round" stroke-linejoin="round" d="M12 6.75a.75.75 0 1 1 0-1.5.75.75 0 0 1 0 1.5ZM12 12.75a.75.75 0 1 1 0-1.5.75.75 0 0 1 0 1.5ZM12 18.75a.75.75 0 1 1 0-1.5.75.75 0 0 1 0 1.5Z" /></svg>
                                                    </button>
                                                    <ul class="dropdown-menu dropdown-menu-end note-actions-dropdown" aria-labelledby="dropdownMenu<?php echo $note['id']; ?>">
                                                        <li>
                                                            <a class="dropdown-item text-danger" href="private-notes.php?delete=<?php echo $note['id']; ?>" onclick="return confirm('Delete this note?')">
                                                                <?php echo $lang['Delete']; ?>

                                                            </a>
                                                        </li>
                                                        <li><hr class="dropdown-divider mb-0"></li>
                                                        <span class="dropdown-item-text" style="padding: 5px 10px; display: block;"> <?php echo $lang['Color']; ?>:</span>
                                                        <li>
                                                            <a class="dropdown-item" href="private-notes.php?color=blue&note_id=<?php echo $note['id']; ?>">
                                                                <span style="display:inline-block;width:16px;height:16px;background:#007bff;border-radius:3px;margin-right:8px;"></span> <?php echo $lang['Blue']; ?>
                                                            </a>
                                                        </li>
                                                        <li>
                                                            <a class="dropdown-item" href="private-notes.php?color=green&note_id=<?php echo $note['id']; ?>">
                                                                <span style="display:inline-block;width:16px;height:16px;background:#28a745;border-radius:3px;margin-right:8px;"></span> <?php echo $lang['Green']; ?>
                                                            </a>
                                                        </li>
                                                        <li>
                                                            <a class="dropdown-item" href="private-notes.php?color=yellow&note_id=<?php echo $note['id']; ?>">
                                                                <span style="display:inline-block;width:16px;height:16px;background:#ffc107;border-radius:3px;margin-right:8px;"></span> <?php echo $lang['Yellow']; ?>
                                                            </a>
                                                        </li>
                                                    </ul>
                                                </div>
                                            </li>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </ul>
                            </div>
                            <div class="col-lg-9 px-0 vh-100 bg-white private-note notes-editor-loading" id="privateNoteEditorPane">
                                <?php if ($message) echo $message; ?>
                                <div>
                                    <form method="post" action="" id="noteForm">
                                        <input type="hidden" name="note_id" value="<?php echo isset($edit_note['id']) ? $edit_note['id'] : (isset($_GET['note_id']) ? intval($_GET['note_id']) : ''); ?>">
                                        <div class="form-group mb-2">
                                            <textarea name="note_content" class="form-control notes-textarea" placeholder="<?php echo $lang['Type your notes here...']; ?>"><?php echo isset($edit_note['content']) ? htmlspecialchars($edit_note['content']) : ''; ?></textarea>
                                            <textarea name="title" class="note-title-under-editor" placeholder="<?php echo $lang['Title']; ?>" rows="1" required><?php echo isset($edit_note['title']) ? htmlspecialchars($edit_note['title']) : ''; ?></textarea>
                                        </div>
                                        <button type="submit" id="hiddenSubmitBtn" name="save_note" style="display:none;"></button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="../assets/js/rich-editor.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var pane = document.getElementById('privateNoteEditorPane');
    if (!pane) return;

    function mountTitleBetweenToolbarAndBody() {
        var titleField = pane.querySelector('.note-title-under-editor');
        var editorContainer = pane.querySelector('.rich-editor-container');
        var toolbar = editorContainer ? editorContainer.querySelector('.rich-editor-toolbar') : null;
        var content = editorContainer ? editorContainer.querySelector('.rich-editor-content') : null;
        if (!titleField || !editorContainer || !toolbar || !content) return false;

        if (titleField.parentNode !== editorContainer || titleField.previousElementSibling !== toolbar) {
            toolbar.insertAdjacentElement('afterend', titleField);
        }

        var resizeTitle = function () {
            titleField.style.height = 'auto';
            var minH = 60;
            var nextH = Math.max(minH, titleField.scrollHeight);
            titleField.style.height = nextH + 'px';
            titleField.style.overflowY = 'hidden';
        };
        if (!titleField.dataset.autosizeBound) {
            titleField.addEventListener('input', resizeTitle);
            window.addEventListener('resize', resizeTitle);
            titleField.dataset.autosizeBound = '1';
        }
        setTimeout(resizeTitle, 0);
        resizeTitle();
        return true;
    }

    var tries = 0;
    var maxTries = 60;
    var timer = setInterval(function () {
        var editorReady = pane.querySelector('.rich-editor-container .rich-editor-content');
        if ((editorReady && mountTitleBetweenToolbarAndBody()) || tries >= maxTries) {
            pane.classList.remove('notes-editor-loading');
            setTimeout(mountTitleBetweenToolbarAndBody, 120);
            clearInterval(timer);
        }
        tries++;
    }, 50);
});
</script>
<?php include("../templates/main-footer.php"); ?> 