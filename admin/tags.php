<?php
/*
================================================================================
  Global Tags Management
  File: admin/tags
  UI: cloned from roles.php patterns
================================================================================
*/
ob_start();
require_once("../includes/lib-initialize.php");

$title = "Tags | " . $syatem_title;
include("../templates/header.php");

if (!$session->isLoggedIn()) {
    redirectTo($url . "index");
}
if (!isset($_SESSION['accountStatus']) || (int)$_SESSION['accountStatus'] !== 1) {
    redirectTo($url . "admin/index");
}
$id = $session->userId; // id of the current logged in user
$user = User::findById((int)$id);
$username = $user->firstName;
$email = $user->email;
$account_stat = $user->status;
$settings = settings::findById(1);
/** @var array{type:string,msg:string}|null assets/js/toast.js reads window.__toastFlash */
$toast_flash = null;

// Create category
if (isset($_POST['create_category'])) {
    $name = trim($_POST['category_name'] ?? '');
    $sort = (int)($_POST['category_sort_order'] ?? 0);
    if ($name === '') {
        $toast_flash = array(
            'type' => 'error',
            'msg' => ($lang['Name'] ?? 'Name') . ' ' . ($lang['is required'] ?? 'is required'),
        );
    } else {
        $stmt = $connect->prepare("INSERT INTO tag_categories (name, sort_order) VALUES (?, ?)");
        $stmt->bind_param('si', $name, $sort);
        $ok = $stmt->execute();
        $stmt->close();
        if ($ok) {
            header("Location: tags?message=category_created");
            exit;
        }
        $toast_flash = array('type' => 'error', 'msg' => ($lang['Error'] ?? 'Error'));
    }
}

// Delete category
if (isset($_GET['delete_category'])) {
    $id = (int)$_GET['delete_category'];
    if ($id > 0) {
        $stmt = $connect->prepare("DELETE FROM tag_categories WHERE id=?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();
    }
    header("Location: tags?message=category_deleted");
    exit;
}

// Create tag
if (isset($_POST['create_tag'])) {
    $categoryId = (int)($_POST['tag_category_id'] ?? 0);
    $name = trim($_POST['tag_name'] ?? '');
    $color = trim($_POST['tag_color_class'] ?? 'badge tags-bg');
    if ($categoryId <= 0 || $name === '') {
        $toast_flash = array('type' => 'error', 'msg' => ($lang['All fields are required'] ?? 'All fields are required'));
    } else {
        $stmt = $connect->prepare("INSERT INTO tags (category_id, name, color_class) VALUES (?, ?, ?)");
        $stmt->bind_param('iss', $categoryId, $name, $color);
        $ok = $stmt->execute();
        $stmt->close();
        if ($ok) {
            header("Location: tags?message=tag_created");
            exit;
        }
        $toast_flash = array('type' => 'error', 'msg' => ($lang['Error'] ?? 'Error'));
    }
}

// Delete tag
if (isset($_GET['delete_tag'])) {
    $id = (int)$_GET['delete_tag'];
    if ($id > 0) {
        $stmt = $connect->prepare("DELETE FROM tags WHERE id=?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();
    }
    header("Location: tags?message=tag_deleted");
    exit;
}

// Notifications
if (isset($_GET['message'])) {
    switch ($_GET['message']) {
        case 'category_created':
            $toast_flash = array('type' => 'success', 'msg' => ($lang['Created'] ?? 'Created'));
            break;
        case 'category_deleted':
            $toast_flash = array('type' => 'success', 'msg' => ($lang['Delete'] ?? 'Delete'));
            break;
        case 'tag_created':
            $toast_flash = array('type' => 'success', 'msg' => ($lang['Created'] ?? 'Created'));
            break;
        case 'tag_deleted':
            $toast_flash = array('type' => 'success', 'msg' => ($lang['Delete'] ?? 'Delete'));
            break;
    }
}

// Fetch categories & tags
$categories = [];
$res = mysqli_query($connect, "SELECT * FROM tag_categories ORDER BY sort_order ASC, id ASC");
if ($res) while ($r = mysqli_fetch_assoc($res)) $categories[] = $r;

$tags = [];
$res = mysqli_query($connect, "SELECT t.*, c.name AS category_name FROM tags t JOIN tag_categories c ON c.id=t.category_id ORDER BY c.sort_order ASC, t.id ASC");
if ($res) while ($r = mysqli_fetch_assoc($res)) $tags[] = $r;

?>

<div class="page-container">
    <div class="container-fluid">
        <div class="row row-eq-height">
            <?php include("../templates/sidebar.php"); ?>
            <div class="page-content" style="padding-bottom:0;">
                <?php include('../templates/top-header.php'); ?>
                <div class="row system-wrap h-100">
                    <?php include("../templates/system-nav.php"); ?>
                    <div class="col-md-9 ss-right h-100">
                        <h2 class="page-title mb-4">
                            <?php echo $lang['Tags Management']; ?>
                            <div class="btn-group">
                                <button type="button" class="bigbutton ss-btn alert-savestn dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                                    <?php echo $lang['Create New']; ?>
                                </button>
                                <ul class="dropdown-menu">
                                    <li><a class="dropdown-item" href="#" id="createCategoryBtn"><?php echo $lang['Category'] ?? 'Category'; ?></a></li>
                                    <li><a class="dropdown-item" href="#" id="createTagBtn"><?php echo $lang['Tag'] ?? 'Tag'; ?></a></li>
                                </ul>
                            </div>
                        </h2>

                        <div class="row">
                            <div class="col-md-12">
                                <h4 class="card-title mb-3"><?php echo $lang['Categories'] ?? 'Categories'; ?></h4>
                                <div class="scroll-x">
                                    <table class="table table-fancy">
                                        <thead>
                                        <tr>
                                            <th class="min-width-50"><?php echo $lang['No.']; ?></th>
                                            <th><?php echo $lang['Name']; ?></th>
                                            <th><?php echo $lang['Sort']; ?></th>
                                            <th class="text-align-right"><?php echo $lang['Actions']; ?></th>
                                        </tr>
                                        </thead>
                                        <tbody>
                                        <?php if (empty($categories)): ?>
                                            <tr><td colspan="4" class="text-center"><?php echo $lang['No records Found!']; ?></td></tr>
                                        <?php else: ?>
                                            <?php $no=1; foreach ($categories as $c): ?>
                                                <tr>
                                                    <td><?php echo $no++; ?></td>
                                                    <td><?php echo htmlspecialchars($c['name']); ?></td>
                                                    <td><?php echo (int)$c['sort_order']; ?></td>
                                                    <td class="text-align-right">
                                                        <div class="border-btn d-inline-block">
                                                            <a href="tags?delete_category=<?php echo (int)$c['id']; ?>" onclick="return confirm('<?php echo $lang['Delete']; ?>?')"><?php echo $lang['Delete']; ?></a>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>

                                <hr class="my-4">

                                <h4 class="card-title mb-3"><?php echo $lang['Tags']; ?></h4>
                                <div class="scroll-x">
                                    <table class="table table-fancy">
                                        <thead>
                                        <tr>
                                            <th class="min-width-50"><?php echo $lang['No.']; ?></th>
                                            <th><?php echo $lang['Category'] ?? 'Category'; ?></th>
                                            <th><?php echo $lang['Tag'] ?? 'Tag'; ?></th>
                                            <th><?php echo $lang['Color']; ?></th>
                                            <th class="text-align-right"><?php echo $lang['Actions']; ?></th>
                                        </tr>
                                        </thead>
                                        <tbody>
                                        <?php if (empty($tags)): ?>
                                            <tr><td colspan="5" class="text-center"><?php echo $lang['No records Found!']; ?></td></tr>
                                        <?php else: ?>
                                            <?php $no=1; foreach ($tags as $t): ?>
                                                <tr>
                                                    <td><?php echo $no++; ?></td>
                                                    <td><?php echo htmlspecialchars($t['category_name']); ?></td>
                                                    <td><?php echo htmlspecialchars($t['name']); ?></td>
                                                    <td><span class="badge <?php echo htmlspecialchars($t['color_class'] ?? ''); ?>"><?php echo htmlspecialchars($t['color_class'] ?? ''); ?></span></td>
                                                    <td class="text-align-right">
                                                        <div class="border-btn d-inline-block">
                                                            <a href="tags?delete_tag=<?php echo (int)$t['id']; ?>" onclick="return confirm('<?php echo $lang['Delete']; ?>?')"><?php echo $lang['Delete']; ?></a>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Create Category Modal -->
<div id="categoryModal" class="modal fade" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header d-flex align-items-center justify-content-between">
                <h4 class="card-title"><?php echo $lang['Create New']; ?> <?php echo $lang['Category'] ?? 'Category'; ?></h4>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close">
                    <?php echo ts_icon('close'); ?>
                </button>
            </div>
            <div class="modal-body modal-max-height">
                <form method="post" action="tags">
                    <div class="bg-grey pd-20">
                        <div class="form-group">
                            <label><?php echo $lang['Name']; ?>*</label>
                            <input type="text" name="category_name" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label><?php echo $lang['Sort']; ?></label>
                            <input type="number" name="category_sort_order" class="form-control" value="0">
                        </div>
                    </div>
                    <div class="pd-20 pt-0">
                        <button type="submit" name="create_category" class="btn primary-btn mt-2"><?php echo $lang['Create']; ?></button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Create Tag Modal -->
<div id="tagModal" class="modal fade" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header d-flex align-items-center justify-content-between">
                <h4 class="card-title"><?php echo $lang['Create New']; ?> <?php echo $lang['Tag'] ?? 'Tag'; ?></h4>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close">
                    <?php echo ts_icon('close'); ?>
                </button>
            </div>
            <div class="modal-body modal-max-height">
                <form method="post" action="tags">
                    <div class="bg-grey pd-20">
                        <div class="form-group">
                            <label><?php echo $lang['Category'] ?? 'Category'; ?>*</label>
                            <select class="form-control" name="tag_category_id" required>
                                <option value=""><?php echo $lang['Select']; ?></option>
                                <?php foreach ($categories as $c): ?>
                                    <option value="<?php echo (int)$c['id']; ?>"><?php echo htmlspecialchars($c['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label><?php echo $lang['Name']; ?>*</label>
                            <input type="text" name="tag_name" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label><?php echo $lang['Color']; ?></label>
                            <select class="form-control" name="tag_color_class">
                                <option value="badge tags-bg" selected>badge tags-bg</option>
                                <option value="color-todo-bg">color-todo-bg</option>
                                <option value="color-inprogress-bg">color-inprogress-bg</option>
                                <option value="color-done-bg">color-done-bg</option>
                            </select>
                        </div>
                    </div>
                    <div class="pd-20 pt-0">
                        <button type="submit" name="create_tag" class="btn primary-btn mt-2"><?php echo $lang['Create']; ?></button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="../assets/js/tags.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Handle Create Category dropdown item
    const createCategoryBtn = document.getElementById('createCategoryBtn');
    const categoryModal = document.getElementById('categoryModal');
    
    if (createCategoryBtn && categoryModal) {
        createCategoryBtn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            const modal = new bootstrap.Modal(categoryModal);
            modal.show();
        });
    }
    
    // Handle Create Tag dropdown item
    const createTagBtn = document.getElementById('createTagBtn');
    const tagModal = document.getElementById('tagModal');
    
    if (createTagBtn && tagModal) {
        createTagBtn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            const modal = new bootstrap.Modal(tagModal);
            modal.show();
        });
    }
});
</script>
<script>window.__toastFlash=<?php echo json_encode($toast_flash, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;</script>
<script src="<?php echo htmlspecialchars(rtrim((string) $url, '/') . '/assets/js/toast.js', ENT_QUOTES, 'UTF-8'); ?>" defer></script>
<?php include("../templates/main-footer.php"); ?>


