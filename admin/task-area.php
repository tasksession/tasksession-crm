<?php
/*
 ================================================================================
   Task Session – Project Management System
   File    : profile.php
   Purpose : Manages staff / clients profiles
 ================================================================================
 */
ob_start();
require_once("../includes/lib-initialize.php");
$title = "Profile | ". $syatem_title;
include("../templates/header.php");
?>
<?php
if(!($session->isLoggedIn())){
	redirectTo($url."index.php");
}
if($_SESSION['accountStatus'] == 2){
	redirectTo($url."client/index.php");
}
if($_SESSION['accountStatus'] == 3){
	redirectTo($url."staff/index.php");
} 
if(isset($_POST['user_id'])){
$SESSION['user_id'] = $_POST['user_id'];
}
//condition check for login

$user = User::findById((int)$id); //take the record of current user in an object array 	
$username=$user->firstName;;

$user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
if ($user_id <= 0) {
	echo '<div class="alert alert-danger">Invalid user ID.</div>';
	include("../templates/main-footer.php");
	exit;
}
$tab = isset($_GET['tab']) ? $_GET['tab'] : 'overview';

// Calculate user-specific project statistics for counter boxes
$user_projects = projects::findBySql("SELECT * FROM projects WHERE (c_id = $user_id OR main_client_id = $user_id OR FIND_IN_SET($user_id, c_ids) > 0 OR FIND_IN_SET($user_id, s_ids) > 0) AND archive = 0 AND trash != 1");
if (!is_array($user_projects)) { $user_projects = []; }

// Calculate user-specific milestone counts for counter boxes
$user_paid_milestones_query = "SELECT COUNT(*) as count FROM milestones m INNER JOIN projects p ON m.p_id = p.p_id WHERE m.status = 1 AND (p.c_id = $user_id OR p.main_client_id = $user_id OR FIND_IN_SET($user_id, p.c_ids) > 0)";
$user_paid_milestones_result = $database->query($user_paid_milestones_query);
$user_paid_milestones_count = $database->fetchArray($user_paid_milestones_result)['count'] ?? 0;

$user_unpaid_milestones_query = "SELECT COUNT(*) as count FROM milestones m INNER JOIN projects p ON m.p_id = p.p_id WHERE m.status = 0 AND (p.c_id = $user_id OR p.main_client_id = $user_id OR FIND_IN_SET($user_id, p.c_ids) > 0)";
$user_unpaid_milestones_result = $database->query($user_unpaid_milestones_query);
$user_unpaid_milestones_count = $database->fetchArray($user_unpaid_milestones_result)['count'] ?? 0;

/**
 * Get currency symbol for display
 */
function getCurrencySymbol($currencyString) {
    if (empty($currencyString)) {
        return '$';
    }
    
    // If it's already just a symbol, return it
    if (strlen($currencyString) <= 3) {
        return $currencyString;
    }
    
    // Extract symbol from "CODE,SYMBOL" format
    $parts = explode(',', $currencyString);
    if (count($parts) > 1) {
        $symbol = trim($parts[1]);
        // Remove any colon or space from the symbol
        $symbol = str_replace([':', ' '], '', $symbol);
        return $symbol;
    }
    
    return '$';
}

/**
 * Get currency country name for display
 */
function getCurrencyCountryName($currencyCode) {
    if (empty($currencyCode)) {
        return 'United States ($)'; // Default
    }
    
    // Clean the currency code
    $cleanCurrencyCode = explode(',', $currencyCode)[0];
    
    // Currency to country mapping
    $currencyCountries = [
        'PKR' => 'Pakistan (Rs)',
        'Rs' => 'Pakistan (Rs)', // Handle Rs,Rs format
        'USD' => 'United States ($)',
        'CAD' => 'Canada (C$)',
        'EUR' => 'European Union (€)',
        'GBP' => 'United Kingdom (£)',
        'INR' => 'India (₹)',
        'JPY' => 'Japan (¥)',
        'AUD' => 'Australia (A$)',
        'CHF' => 'Switzerland (CHF)',
        'CNY' => 'China (¥)',
    ];
    
    $countryName = $currencyCountries[$cleanCurrencyCode] ?? 'United States ($)';
    
    // Ensure we don't return duplicates
    return $countryName;
}

?>
<div class="modal fade" id="edit-milestone1" tabindex="-1" aria-labelledby="edit-milestone1-label" aria-hidden="true">
           <div class="modal-dialog modal-lg">
			<!-- Modal content-->
			<div class="modal-content">
				<div class="modal-header d-flex align-items-center justify-content-between">
					<h4 class="card-title" id="edit-milestone1-label"><?php echo $lang['Invoice']; ?></h4>
					<div class="d-flex align-items-center col-gap-10">
						
						<div class="icons-btn d-flex">
							<a href="#" class="prnintpage me-2" onclick="window.print(); return false;">
							<?php echo ts_icon('printer', 'h-6'); ?>
								<?php echo $lang['Print Invoice']; ?>
							</a>
							<form action="<?php echo htmlspecialchars(tasksession_download_pdf_href(), ENT_QUOTES, 'UTF-8'); ?>" method="post" target="_blank" enctype="multipart/form-data">
								<input type="hidden" value="<?php if(isset($_POST['edit_id1'])){echo $_POST['edit_id1'];}?>" name="milestone_id" />
								<button type="submit" name="mile_submit">
								<?php echo ts_icon('download', 'h-6'); ?>
								<?php echo $lang['Download PDF']; ?>
							</button>
							</form>
						</div>
						<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close">
							<?php echo ts_icon('close'); ?>
						</button>
					</div>
				</div>
				<div class="modal-body">
					<div id="invoicecont" class="invoice-box">
						<div id="editor"></div>
						<?php if(isset($_POST['edit_id1'])){
							  $edit_id1=$_POST['edit_id1'];
							  $latestMile1=milestone::findByMilestoneId($edit_id1);
							  $latestProj1=projects::findByProjectId($latestMile1->p_id);
							  $latestUser1=user::findById($latestProj1->c_id);		  
							  $adminUser1=user::findById(1);
							  
							  // Get currency symbol for the invoice
							  $currency_symbol = getCurrencySymbol($latestMile1->currency);
							  
							  // Include the global invoice template
							  include("../templates/invoice-modal.php");
							}?>
					</div>
				</div>
			</div>
		</div>
	</div>
		<!-- Add Milestone Modal -->
	<div id="add-milestone" class="modal fade" tabindex="-1" aria-labelledby="add-milestone-label" aria-hidden="true">
		<div class="modal-dialog">
			<!-- Modal content-->
			<div class="modal-content">
				<div class="modal-header">
					<h4 class="modal-title modal-left" id="add-milestone-label"><?php echo $lang['Add Project Milestone']; ?> </h4>
					<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close">
							<?php echo ts_icon('close'); ?>
						</button>
				</div>
				<div class="modal-body">
					<form method="post" class="milestonefrm1" action="#" enctype="multipart/form-data">
						<div class="form-group">
							<div class="col-sm-12">
								<div class="field-label">
									<label for="firstName">
										<?php echo $lang['Project title']; ?>*</label>
								</div>
								<input type="text" name="title" class="form-control only-alpha" required> </div>
							<div class="clearfix"></div>
						</div>
						<div class="form-group">
							<div class="col-sm-12">
								<div class="field-label">
									<label for="amount">
										<?php echo $lang['Amount']; ?> (
											<?php echo $currency_symbol ; ?>)</label>
								</div>
								<input type="number" name="amount" class="form-control" required> </div>
							<div class="clearfix"></div>
						</div>
						<div class="form-group">
							<div class="col-sm-12">
								<div class="field-label">
									<label for="status">
										<?php echo $lang['Status']; ?>
									</label>
								</div>
								<select class="ui dropdown form-control status1" name="status">
									<option value="0">
										<?php echo $lang['Unpaid']; ?>
									</option>
									<option value="1">
										<?php echo $lang['Paid manually']; ?>
									</option>
								</select>
							</div>
							<div class="clearfix"></div>
						</div>
						<div class="form-group">
							<div class="col-sm-12">
								<div class="field-label">
									<label for="deadline">
										<?php echo $lang['Deadline']; ?>
									</label>
								</div>
								<input type="text" name="deadline" autocomplete="off" class="form-control datepicker" required>
								<input type="hidden" name="releaseDate" class="form-control datepicker releaseDate" required value="1970-01-01"> </div>
							<div class="clearfix"></div>
						</div>
						<div class="form-group">
							<div class="col-sm-12">
								<input type="hidden" name="projId" value="<?php echo $projectId ;?>" />
								<input type="hidden" name="clientId" value="<?php echo $clientId ;?>" />
								<input type="submit" name="add-milestone" value="<?php echo $lang['Add milestone']; ?>" class="btn bigbutton" /> </div>
						</div>
						<div class="clearfix"></div>
					</form>
				</div>
			</div>
		</div>
	</div>
	<!-- Modal -->
	<div id="edit-milestone" class="modal fade" tabindex="-1" aria-labelledby="edit-milestone-label" aria-hidden="true">
		<div class="modal-dialog">
			<!-- Modal content-->
			<div class="modal-content">
				<div class="modal-header">
					<h4 class="card-title" id="edit-milestone-label"><?php echo $lang['Edit Invoice']; ?></h4>
					<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close">
							<?php echo ts_icon('close'); ?>
						</button>
				</div>
				<div class="modal-body">
				<?php if(isset($_POST['edit_id'])){
					  $edit_id=$_POST['edit_id'];
					  $latestMile=milestone::findByMilestoneId($edit_id);
					  ?>
						<form method="post" class="milestonefrm1" action="#" enctype="multipart/form-data">
							<div class="form-group">
								<div class="col-sm-12">
									<div class="field-label">
										<label for="firstName">
											<?php echo $lang['Project title'];?>*</label>
									</div>
									<input type="text" name="title1" class="form-control" value="<?php echo $latestMile->title;?>" required> </div>
								<div class="clearfix"></div>
							</div>
							<div class="form-group">
								<div class="col-sm-12">
									<div class="field-label">
										<label for="amount">
											<?php echo $lang['Amount']; ?> (
												<?php echo getCurrencySymbol($latestMile->currency); ?>)</label>
									</div>
									<input type="number" name="amount1" class="form-control" value="<?php echo $latestMile->budget;?>" required> </div>
								<div class="clearfix"></div>
							</div>
							<div class="form-group">
								<div class="col-sm-12">
									<div class="field-label">
										<label for="status">
											<?php echo $lang['Status']; ?>
										</label>
									</div>
									<select class="ui dropdown form-control status1" name="status1">
										<?php if($latestMile->status==0){?>
											<option value="0" checked>
												<?php echo $lang['Unpaid']; ?>
											</option>
											<option value="1">
												<?php echo $lang['Paid manually']; ?>
											</option>
											<?php }else{?>
												<option value="0">
													<?php echo $lang['Unpaid']; ?>
												</option>
												<option value="1" checked>
													<?php echo $lang['Paid manually']; ?>
												</option>
												<?php } ?>
									</select>
								</div>
								<div class="clearfix"></div>
							</div>
							<div class="form-group">
								<div class="col-sm-12">
									<div class="field-label">
										<label for="deadline">
											<?php echo $lang['Deadline']; ?>
										</label>
									</div>
									<input type="text" name="deadline1" class="form-control datepicker" required value="<?php echo $latestMile->deadline; ?>">
									<?php 
									$relDate = $latestMile->releaseDate;
									?>
										<input type="hidden" name="releaseDate" class="form-control datepicker releaseDate" required value="<?php if($relDate){echo $latestMile->releaseDate;} else{ echo '1970-01-01';} ?>"> </div>
								<div class="clearfix"></div>
							</div>
							<div class="form-group">
								<div class="col-sm-12">
									<input type="hidden" name="editId" value="<?php echo  $edit_id ;?>" />
									<!-- Email Notification Toggle -->
									<div class="input-notify">
										<div class="form-group d-block d-md-flex justify-content-between">
											<div class="d-flex col-gap align-items-center">
												<div class="checkbox-wrapper-6">
													<input class="tgl tgl-light" id="notifyMilestonePaid" name="notifyMilestonePaid" type="checkbox" checked />
													<label class="tgl-btn" for="notifyMilestonePaid"></label>
												</div>
												<div>
													<label for="notifyMilestonePaid" class="permission-label"><?php echo $lang['Email Notification']; ?></label>
													<p class="permission-description"><?php echo $lang['Notify the client invoice is paid']; ?></p>
												</div>
											</div>
											<input type="submit" name="edit-milestone-1" value="<?php echo $lang['Update']; ?>" class="btn bigbutton" />
										</div>
									</div>
								</div>
							</div>
						</form>
						<?php
					}?>
				</div>
			</div>
		</div>
	</div>
		<div class="page-container vh-100">
		<div class="container-fluid vh-100">
			<div class="row row-eq-height vh-100">
				<?php  include("../templates/sidebar.php"); ?>
					<div class="page-content">
						<?php include('../templates/top-header.php'); ?>
						<div class="row bg-grey">
							<div class="col-md-12 project-tabs">
							 <div class="row">
								<div class="project-tabs-header">
									<div class="scrollable-tabs-container d-flex col-gap-40 col-gap-40-sep">
										<div class="main-heading"><h1><?php echo $lang['Profile Overview'];?></h1></div>
													<?php
											  $profileUser = User::findById($user_id);
											?>
											<?php if ($profileUser->accountStatus != 1): ?>
											<div class="icon-container sep">
											  <a href="profile?user_id=<?php echo $user_id; ?>&tab=overview"
												 class="<?php echo ($tab == 'overview') ? 'active' : ''; ?>">
												<?php echo ts_icon('chart-pie'); ?>
											<?php echo $lang['Profile Stats']; ?>
											  </a>
											  <a href="profile?user_id=<?php echo $user_id; ?>&tab=projects"
												 class="<?php echo ($tab == 'projects') ? 'active' : ''; ?>">
												<?php echo ts_icon('folder'); ?><?php echo $lang['Projects']; ?>
									  </a>
									  <a href="profile?user_id=<?php echo $user_id; ?>&tab=tasks"
										 class="<?php echo ($tab == 'tasks') ? 'active' : ''; ?>">
										<?php echo ts_icon('document-text'); ?><?php echo $lang['Tasks']; ?>
											  </a>
									  <?php if ($profileUser->accountStatus != 3): // only show invoice for non-staff profiles ?>
										<a href="profile?user_id=<?php echo $user_id; ?>&tab=invoice"
										   class="<?php echo ($tab == 'invoice') ? 'active' : ''; ?>">
										  <?php echo ts_icon('bookmark'); ?>
										  <?php echo $lang['Invoice']; ?>
										</a>
									  <?php endif; ?>
									  <a href="profile?user_id=<?php echo $user_id; ?>&tab=activity"
										 class="<?php echo ($tab == 'activity') ? 'active' : ''; ?>">
										<?php echo ts_icon('clock'); ?>
										<?php echo $lang['Activity Log']; ?>
									  </a>
									</div>
								<?php endif; ?>
					       </div>   
					   </div> 
					 <div class="headers-icons d-flex">
						<div class="icon-container">
							<a href="kanban" class="ms-2">
							</a>
						</div>
					</div>
					<div class="search">
						   <div class="search-icon" onclick="toggleSearch()">
							 <?php echo ts_icon('search', 'w-6'); ?>
							</div>
							  <form method="GET" action="profile" class="search-form" id="searchForm">
								<input type="hidden" name="user_id" value="<?php echo $user_id; ?>">
								<input type="hidden" name="tab" value="<?php echo htmlspecialchars($tab); ?>">
								<div class="input-group">
								<input type="text" id="client-search" name="search" class="form-control" placeholder="<?php echo $lang['Search Projects, Invoice']; ?>" value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>">
								<button type="submit" class="search-icon">
								<?php echo ts_icon('search', 'w-6'); ?>
								</button>
								<?php if(isset($_GET['search']) && !empty($_GET['search'])): ?>
								<a href="clients" class="cross">
								<?php echo ts_icon('close', 'w-6'); ?></a>
								<?php endif; ?>
								</div>
							</form>
					  </div>
					  		 <div class="edit-overview-btn">
							        <?php if ($tab == 'activity'): ?>
							        <!-- Date Range Filter for Activity Log -->
							        <div class="action-toggle collapsed" data-bs-toggle="collapse" data-bs-target="#dateRangeFilterDropdown" aria-expanded="false">
                                        <span class="action-text"> 
                                            <?php
                                            $dateFilter = isset($_GET['date_filter']) ? $_GET['date_filter'] : 'all';
                                            $dateText = 'All Time';
                                            
                                            if ($dateFilter === 'today') {
                                                $dateText = 'Today';
                                            } elseif ($dateFilter === 'yesterday') {
                                                $dateText = 'Yesterday';
                                            } elseif ($dateFilter === 'this_week') {
                                                $dateText = 'This Week';
                                            } elseif ($dateFilter === 'last_week') {
                                                $dateText = 'Last Week';
                                            } elseif ($dateFilter === 'this_month') {
                                                $dateText = 'This Month';
                                            } elseif ($dateFilter === 'last_month') {
                                                $dateText = 'Last Month';
                                            } elseif ($dateFilter === 'this_year') {
                                                $dateText = 'This Year';
                                            } elseif ($dateFilter === 'custom') {
                                                $fromDate = isset($_GET['from_date']) ? $_GET['from_date'] : '';
                                                $toDate = isset($_GET['to_date']) ? $_GET['to_date'] : '';
                                                if ($fromDate && $toDate) {
                                                    $dateText = date('M j', strtotime($fromDate)) . ' - ' . date('M j, Y', strtotime($toDate));
                                                } else {
                                                    $dateText = 'Custom Range';
                                                }
                                            }
                                            
                                            echo $dateText;
                                            ?>
                                         </span><span class="mobile-ellipsis"><?php echo ts_icon('ellipsis'); ?></span>
										<?php echo ts_icon('calendar'); ?>
                                    </div>
							        <?php elseif ($tab == 'tasks'): ?>
							        <!-- Task Filter for Tasks Tab -->
							        <div class="action-toggle collapsed" data-bs-toggle="collapse" data-bs-target="#taskFilterDropdown" aria-expanded="false">
                                        <span class="action-text"> 
                                            <?php
                                            $statusFilter = isset($_GET['status']) && in_array($_GET['status'], ['todo', 'inprogress', 'review', 'done']) ? $_GET['status'] : null;
                                            $dateFilter = isset($_GET['date_filter']) ? $_GET['date_filter'] : null;
                                            
                                            if ($dateFilter === 'custom' && isset($_GET['from_date']) && isset($_GET['to_date'])) {
                                                $fromDate = $_GET['from_date'];
                                                $toDate = $_GET['to_date'];
                                                echo date('M j', strtotime($fromDate)) . ' - ' . date('M j, Y', strtotime($toDate));
                                            } elseif (!$statusFilter) {
                                                echo $lang['All Tasks'];
                                            } else {
                                                $statusLabels = [
                                                    'todo' => 'To Do',
                                                    'inprogress' => 'In Progress', 
                                                    'review' => 'In Review',
                                                    'done' => 'Completed'
                                                ];
                                                echo $statusLabels[$statusFilter];
                                            }
                                            ?>
                                         </span><span class="mobile-ellipsis"><?php echo ts_icon('ellipsis'); ?></span>
										<?php echo ts_icon('filter'); ?>
                                    </div>
							        <?php endif; ?>
										
                                    
                                    <!-- Task Filter Dropdown for Tasks Tab -->
                                    <?php if ($tab == 'tasks'): ?>
                                    <div id="taskFilterDropdown" class="toggle-action justify collapse shadow-dept">
                                        <ul>
                                            <?php
                                            // Get task counts for filter badges
                                            $taskCounts = [];
                                            $statusTypes = ['todo', 'inprogress', 'review', 'done'];
                                            $statusLabels = [
                                                'todo' => 'To Do',
                                                'inprogress' => 'In Progress',
                                                'review' => 'In Review',
                                                'done' => 'Completed'
                                            ];
                                            $statusColors = [
                                                'todo' => 'todo todo-bg-op',
                                                'inprogress' => 'inprogress inprogress-bg-op',
                                                'review' => 'review review-bg-op',
                                                'done' => 'done review done-bg-op'
                                            ];
                                            
                                            foreach($statusTypes as $status) {
                                                if ($profileUser->accountStatus == 3) {
                                                    // Staff - tasks assigned to them
                                                    $countQuery = "SELECT COUNT(*) as count FROM tasks WHERE FIND_IN_SET($user_id, assigned_to) > 0 AND status = '$status'";
                                                } else {
                                                    // Client - tasks from projects where they are main client or additional client
                                                    $countQuery = "SELECT COUNT(*) as count FROM tasks t 
                                                                 LEFT JOIN projects p ON t.project_id = p.p_id 
                                                                 WHERE t.project_id > 0 AND t.status = '$status' AND (
                                                                     p.c_id = $user_id OR 
                                                                     p.main_client_id = $user_id OR 
                                                                     FIND_IN_SET($user_id, p.c_ids) > 0
                                                                 )";
                                                }
                                                
                                                // Add date filtering to dropdown count queries
                                                if ($dateFilter === 'custom' && $fromDate && $toDate) {
                                                    $fromDateSafe = $database->escapeValue($fromDate);
                                                    $toDateSafe = $database->escapeValue($toDate);
                                                    $countQuery .= " AND DATE(t.created_at) >= '$fromDateSafe' AND DATE(t.created_at) <= '$toDateSafe'";
                                                }
                                                
                                                $countResult = $database->query($countQuery);
                                                $countData = $database->fetchArray($countResult);
                                                $taskCounts[$status] = $countData['count'];
                                            }
                                            
                                            $statusFilter = isset($_GET['status']) && in_array($_GET['status'], $statusTypes) ? $_GET['status'] : null;
                                            ?>
                                            
                                            <li class="<?php echo !$statusFilter ? 'active' : ''; ?>">
                                                <a href="?user_id=<?php echo $user_id; ?>&tab=tasks">
                                                    <span><?php echo $lang['All Tasks']; ?></span>
                                                </a>
                                            </li>
                                            <?php foreach ($statusTypes as $status): ?>
                                            <li class="<?php echo $statusFilter === $status ? 'active' : ''; ?>">
                                                <a href="?user_id=<?php echo $user_id; ?>&tab=tasks&status=<?php echo $status; ?>">
                                                    <span><?php echo $statusLabels[$status]; ?></span>
                                                    <span class="badge <?php echo $statusColors[$status]; ?>"><?php echo $taskCounts[$status]; ?></span>
                                                </a>
                                            </li>
                                            <?php endforeach; ?>
                                            <li class="divider"></li>
                                            <li class="<?php echo isset($_GET['date_filter']) && $_GET['date_filter'] === 'custom' ? 'active' : ''; ?>">
                                                <a href="#" onclick="showCustomDateRange()">
                                                    <span><?php echo $lang['Custom Date Range']; ?></span>
                                                </a>
                                            </li>
                                        </ul>
                                        
                                        <!-- Custom Date Range Form for Tasks -->
                                        <div id="customTaskDateRangeForm" style="display: none; padding: 15px; border-top: 1px solid #eee;">
                                            <form method="GET" action="">
                                                <input type="hidden" name="user_id" value="<?php echo $user_id; ?>">
                                                <input type="hidden" name="tab" value="tasks">
                                                <?php if($statusFilter): ?>
                                                    <input type="hidden" name="status" value="<?php echo htmlspecialchars($statusFilter); ?>">
                                                <?php endif; ?>
                                                <input type="hidden" name="date_filter" value="custom">
                                                
                                                <div class="mb-3">
                                                    <label class="form-label">From Date:</label>
                                                    <input type="date" name="from_date" class="form-control" value="<?php echo htmlspecialchars((string)($_GET['from_date'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" required>
                                    </div>
                                                
                                                <div class="mb-3">
                                                    <label class="form-label">To Date:</label>
                                                    <input type="date" name="to_date" class="form-control" value="<?php echo htmlspecialchars((string)($_GET['to_date'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" required>
                                                </div>
                                                
                                                <div class="d-flex gap-2">
                                                    <button type="submit" class="btn btn-primary btn-sm">Apply Filter</button>
                                                    <button type="button" class="btn btn-secondary btn-sm" onclick="hideCustomTaskDateRange()">Cancel</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <!-- Date Range Filter Dropdown for Activity Tab -->
                                    <?php if ($tab == 'activity'): ?>
                                    <div id="dateRangeFilterDropdown" class="toggle-action justify collapse shadow-dept">
                                        <ul>
                                            <li class="<?php echo $dateFilter === 'all' ? 'active' : ''; ?>">
                                                <a href="?user_id=<?php echo $user_id; ?>&tab=activity&date_filter=all">
                                                    <span>All Time</span>
                                                </a>
                                            </li>
                                            <li class="<?php echo $dateFilter === 'today' ? 'active' : ''; ?>">
                                                <a href="?user_id=<?php echo $user_id; ?>&tab=activity&date_filter=today">
                                                    <span>Today</span>
                                                </a>
                                            </li>
                                            <li class="<?php echo $dateFilter === 'yesterday' ? 'active' : ''; ?>">
                                                <a href="?user_id=<?php echo $user_id; ?>&tab=activity&date_filter=yesterday">
                                                    <span>Yesterday</span>
                                                </a>
                                            </li>
                                            <li class="<?php echo $dateFilter === 'this_week' ? 'active' : ''; ?>">
                                                <a href="?user_id=<?php echo $user_id; ?>&tab=activity&date_filter=this_week">
                                                    <span>This Week</span>
                                                </a>
                                            </li>
                                            <li class="<?php echo $dateFilter === 'last_week' ? 'active' : ''; ?>">
                                                <a href="?user_id=<?php echo $user_id; ?>&tab=activity&date_filter=last_week">
                                                    <span>Last Week</span>
                                                </a>
                                            </li>
                                            <li class="<?php echo $dateFilter === 'this_month' ? 'active' : ''; ?>">
                                                <a href="?user_id=<?php echo $user_id; ?>&tab=activity&date_filter=this_month">
                                                    <span>This Month</span>
                                                </a>
                                            </li>
                                            <li class="<?php echo $dateFilter === 'last_month' ? 'active' : ''; ?>">
                                                <a href="?user_id=<?php echo $user_id; ?>&tab=activity&date_filter=last_month">
                                                    <span>Last Month</span>
                                                </a>
                                            </li>
                                            <li class="<?php echo $dateFilter === 'this_year' ? 'active' : ''; ?>">
                                                <a href="?user_id=<?php echo $user_id; ?>&tab=activity&date_filter=this_year">
                                                    <span>This Year</span>
                                                </a>
                                            </li>
                                            <li class="<?php echo $dateFilter === 'custom' ? 'active' : ''; ?>">
                                                <a href="#" onclick="showCustomActivityDateRange()">
                                                    <span>Custom Range</span>
                                                </a>
                                            </li>
                                        </ul>
                                        
                                        <!-- Custom Date Range Form -->
                                        <div id="customDateRangeForm" style="display: none; padding: 15px; border-top: 1px solid #eee;">
                                            <form method="GET" action="">
                                                <input type="hidden" name="user_id" value="<?php echo $user_id; ?>">
                                                <input type="hidden" name="tab" value="activity">
                                                <input type="hidden" name="date_filter" value="custom">
                                                
                                                <div class="mb-3">
                                                    <label class="form-label">From Date:</label>
                                                    <input type="date" name="from_date" class="form-control" value="<?php echo htmlspecialchars((string)($_GET['from_date'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" required>
                                                </div>
                                                
                                                <div class="mb-3">
                                                    <label class="form-label">To Date:</label>
                                                    <input type="date" name="to_date" class="form-control" value="<?php echo htmlspecialchars((string)($_GET['to_date'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" required>
                                                </div>
                                                
                                                <div class="d-flex gap-2">
                                                    <button type="submit" class="btn btn-primary btn-sm">Apply Filter</button>
                                                    <button type="button" class="btn btn-secondary btn-sm" onclick="hideCustomDateRange()">Cancel</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                
                            </div>
                            
                            <!-- JavaScript for Date Range Filter -->
                            <script>
                            function showCustomDateRange() {
                                document.getElementById('customDateRangeForm').style.display = 'block';
                            }
                            
                            function hideCustomDateRange() {
                                document.getElementById('customDateRangeForm').style.display = 'none';
                            }
                            </script>
							<div class="d-none d-md-block">
						<a href="edit?editprofile=<?php echo $user_id; ?>" class="btn primary-btn"><?php echo $lang['Edit Profile']; ?></a>
					</div>
				</div>
			   </div>
			</div>
			
			
			
			
			
			
			
			
			
						<div class="row vh-100 profile">
					<!-- Modern UI Layout -->
					<div class="container-fluid vh-100">
						<div class="row vh-100">
							<?php include("../templates/user-profile-sidebar.php"); ?>
							<div class="col-xl-9 col-lg-8 col-md-12 flex-grow-1 right-col pd-0">
								<!-- Profile Details -->
								<div class="mb-4">
									<div class="card-body Project-details">
									
									
						
										
										
										
							
										
										
										
										
										
										
										
										
										
										
										
										
										
										
			
			
			
			
			
			
			
			
			
			
			
			
			
			
			
			
			
			
			
			
			
			
			
			
		<?php if(isset($message) && (!empty($message))){echo $message;} ?>
							<?php
                                $user = User::findById($user_id);
                                if (($tab == 'overview' || !isset($_GET['tab'])) && $user) {
                                    // Get profile picture using global avatar function
                                    $avatarData = getUserAvatarData($user_id, $user->firstName, $user->lastName ?? '', 150, 150);
                                    $profilePic = $avatarData['type'] === 'image' ? $avatarData['url'] : '';
                                    // Get user statistics
                                    if ($user->accountStatus == 3) { // staff
                                        // Projects where staff is assigned (s_ids contains user_id)
                                        $totalProjectsQuery = "SELECT COUNT(*) as total FROM projects WHERE FIND_IN_SET($user_id, s_ids)";
                                        $totalProjectsResult = $database->query($totalProjectsQuery);
                                        $totalProjects = $database->fetchArray($totalProjectsResult)['total'] ?? 0;
                                        // Tasks where staff is assigned (assigned_to contains user_id)
                                        $totalTasksQuery = "SELECT COUNT(*) as total FROM tasks WHERE FIND_IN_SET($user_id, assigned_to)";
                                        $totalTasksResult = $database->query($totalTasksQuery);
                                        $totalTasks = $database->fetchArray($totalTasksResult)['total'] ?? 0;
                                    } else { // client
                                        $totalProjectsQuery = "SELECT COUNT(*) as total FROM projects WHERE c_id = $user_id";
                                        $totalProjectsResult = $database->query($totalProjectsQuery);
                                        $totalProjects = $database->fetchArray($totalProjectsResult)['total'] ?? 0;
                                        // Total Tasks
                                        $projectIdsResult = $database->query("SELECT p_id FROM projects WHERE c_id = $user_id");
                                        $projectIds = [];
                                        while ($row = $database->fetchArray($projectIdsResult)) {
                                            $projectIds[] = $row['p_id'];
                                        }
                                        $totalTasks = 0;
                                        if (!empty($projectIds)) {
                                            $projectIdsStr = implode(',', $projectIds);
                                            $taskCountResult = $database->query("SELECT COUNT(*) as cnt FROM tasks WHERE project_id IN ($projectIdsStr)");
                                            $taskCountRow = $database->fetchArray($taskCountResult);
                                            $totalTasks = $taskCountRow ? $taskCountRow['cnt'] : 0;
                                        }
                                    }
                                    // Get payment statistics with currency filter
                                    $statsCurrencyFilter = isset($_GET['stats_currency']) ? $_GET['stats_currency'] : 'all';
                                    
                                    // Get task statistics
                                    if ($profileUser->accountStatus == 3) { // staff
                                        $inProgressTasksQuery = "SELECT COUNT(*) as total FROM tasks WHERE assigned_to LIKE '%$user_id%' AND status = 'inprogress'";
                                        $completedTasksQuery = "SELECT COUNT(*) as total FROM tasks WHERE assigned_to LIKE '%$user_id%' AND status = 'done'";
                                    } else { // client
                                        $projectIdsResult = $database->query("SELECT p_id FROM projects WHERE c_id = $user_id OR main_client_id = $user_id OR FIND_IN_SET($user_id, c_ids) > 0");
                                        $projectIds = [];
                                        while ($row = $database->fetchArray($projectIdsResult)) {
                                            $projectIds[] = $row['p_id'];
                                        }
                                        $inProgressTasks = 0;
                                        $completedTasks = 0;
                                        if (!empty($projectIds)) {
                                            $projectIdsStr = implode(',', $projectIds);
                                            $inProgressResult = $database->query("SELECT COUNT(*) as total FROM tasks WHERE project_id IN ($projectIdsStr) AND status = 'inprogress'");
                                            $completedResult = $database->query("SELECT COUNT(*) as total FROM tasks WHERE project_id IN ($projectIdsStr) AND status = 'done'");
                                            $inProgressRow = $database->fetchArray($inProgressResult);
                                            $completedRow = $database->fetchArray($completedResult);
                                            $inProgressTasks = $inProgressRow ? $inProgressRow['total'] : 0;
                                            $completedTasks = $completedRow ? $completedRow['total'] : 0;
                                        }
                                    }
                                    
                                    if ($profileUser->accountStatus == 3) {
                                        $inProgressResult = $database->query($inProgressTasksQuery);
                                        $completedResult = $database->query($completedTasksQuery);
                                        $inProgressRow = $database->fetchArray($inProgressResult);
                                        $completedRow = $database->fetchArray($completedResult);
                                        $inProgressTasks = $inProgressRow ? $inProgressRow['total'] : 0;
                                        $completedTasks = $completedRow ? $completedRow['total'] : 0;
                                    }
                                    
                                    // Get all currencies for this user
                                    $userCurrenciesQuery = "SELECT DISTINCT m.currency 
                                                           FROM milestones m 
                                                           INNER JOIN projects p ON m.p_id = p.p_id 
                                                           WHERE p.c_id = $user_id 
                                                           AND m.currency IS NOT NULL 
                                                           AND m.currency != ''
                                                           ORDER BY m.currency";
                                    $userCurrenciesResult = $database->query($userCurrenciesQuery);
                                    $userCurrencies = [];
                                    while ($row = $database->fetchArray($userCurrenciesResult)) {
                                        $userCurrencies[] = $row['currency'];
                                    }
                                    
                                    // If "all" is selected, we'll show separate rows for each currency
                                    if ($statsCurrencyFilter === 'all') {
                                        $currencyStats = [];
                                        foreach ($userCurrencies as $currency) {
                                            $cleanCurrency = explode(',', $currency)[0];
                                            $currencySql = $database->escapeValue($cleanCurrency);
                                            
                                            $whereConditions = ["p.c_id = $user_id"];
                                            $whereConditions[] = "(m.currency LIKE '$currencySql,%' OR m.currency = '$currencySql' OR m.currency LIKE '%,$currencySql')";
                                            $whereClause = implode(' AND ', $whereConditions);
                                            
                                            $totalQuery = "SELECT SUM(m.budget) as total FROM milestones m 
                                                          INNER JOIN projects p ON m.p_id = p.p_id 
                                                          WHERE $whereClause";
                                            $totalResult = $database->query($totalQuery);
                                            $total = $database->fetchArray($totalResult)['total'] ?? 0;
                                            
                                            $paidQuery = "SELECT SUM(m.budget) as total FROM milestones m 
                                                         INNER JOIN projects p ON m.p_id = p.p_id 
                                                         WHERE $whereClause AND m.status = 1";
                                            $paidResult = $database->query($paidQuery);
                                            $paid = $database->fetchArray($paidResult)['total'] ?? 0;
                                            $unpaid = $total - $paid;
                                            
                                            if ($total > 0) {
                                                $currencyStats[] = [
                                                    'currency' => $currency,
                                                    'symbol' => getCurrencySymbol($currency),
                                                    'total' => $total,
                                                    'paid' => $paid,
                                                    'unpaid' => $unpaid
                                                ];
                                            }
                                        }
                                    } else {
                                        // Single currency filter
                                        $cleanCurrencyFilter = explode(',', $statsCurrencyFilter)[0];
                                        $statsCurrencyFilterSql = $database->escapeValue($cleanCurrencyFilter);
                                        $statsWhereConditions = ["p.c_id = $user_id"];
                                        $statsWhereConditions[] = "(m.currency LIKE '$statsCurrencyFilterSql,%' OR m.currency = '$statsCurrencyFilterSql' OR m.currency LIKE '%,$statsCurrencyFilterSql')";
                                        $statsWhereClause = implode(' AND ', $statsWhereConditions);
                                        
                                        $totalAmountQuery = "SELECT SUM(m.budget) as total FROM milestones m 
                                                           INNER JOIN projects p ON m.p_id = p.p_id 
                                                           WHERE $statsWhereClause";
                                        $totalAmountResult = $database->query($totalAmountQuery);
                                        $totalAmount = $database->fetchArray($totalAmountResult)['total'] ?? 0;
                                        
                                        $paidAmountQuery = "SELECT SUM(m.budget) as total FROM milestones m 
                                                          INNER JOIN projects p ON m.p_id = p.p_id 
                                                          WHERE $statsWhereClause AND m.status = 1";
                                        $paidAmountResult = $database->query($paidAmountQuery);
                                        $paidAmount = $database->fetchArray($paidAmountResult)['total'] ?? 0;
                                        $unpaidAmount = $totalAmount - $paidAmount;
                                        
                                        $currencySymbol = getCurrencySymbol($statsCurrencyFilter);
                                    }
                                ?>
							<div class="center-col max-width-850 ">
                       <div class="row counter-align">
                          
                               
                           
								  <div class="col-sm-3 col-6"> 
                              <div class="widget-card dash-counter">    
                           <div class="grey"><span><?php echo $lang['Total Projects']; ?></span></div>
                                <div class="counts dash-rttb"> <?php echo is_array($user_projects) ? count($user_projects) : 0; ?></div>
                                <!-- Clients: Users Icon -->
                                <?php echo ts_icon('user-group'); ?>
                                <div class="grey"> <a class="left-center" href="profile?user_id=<?php echo $user_id; ?>&tab=projects"><?php echo $lang['View all']; ?><?php echo ts_icon('arrow-right', 'w-2'); ?></a>
								</div>
                            </div>
                          </div>
						<div class="col-sm-3 col-6">
							<div class="widget-card">    
							   <div class="grey"><span><?php echo $lang['Total Tasks']; ?></span></div>
									<div class="counts dash-rttb"><?php echo $totalTasks; ?></div>
									<!-- Staff: User Group Icon -->
									<?php echo ts_icon('user-group'); ?>
									<div class="grey"> <a class="left-center" href="profile?user_id=<?php echo $user_id; ?>&tab=tasks"><?php echo $lang['View all']; ?><?php echo ts_icon('arrow-right', 'w-2'); ?></a>
								</div>
							</div>
						</div>
						<div class="col-sm-3 col-6">
							<div class="widget-card">
                                <div class="grey"><span><?php echo $lang['Unpaid Invoices']; ?></span></div>
                                <div class="counts dash-rttb"><?php echo $user_unpaid_milestones_count; ?></div>
                                <!-- Unpaid Invoices: Clock Icon -->
                                <?php echo ts_icon('clock'); ?>
                                <div class="grey"><a class="left-center" href="profile?user_id=<?php echo $user_id; ?>&tab=invoice"><?php echo $lang['View Invoices']; ?><?php echo ts_icon('arrow-right', 'w-2'); ?></a>
								</div>
                            </div>
							</div>
						<div class="col-sm-3 col-6"> 
							   <div class="widget-card">
								<div class="grey"><span><?php echo $lang['Paid Invoices']; ?></span></div>
								<div class="counts dash-rttb"><?php echo $user_paid_milestones_count; ?></div>
								<!-- Paid Invoices: Receipt Icon -->
								<?php echo ts_icon('currency-dollar'); ?>
								<div class="grey"><a class="left-center" href="profile?user_id=<?php echo $user_id; ?>&tab=invoice"><?php echo $lang['View Invoices']; ?><?php echo ts_icon('arrow-right', 'w-2'); ?></a>
								</div>
							  </div>
						</div>
									
                                        </div>
									
									                                <div class="profile-content">

                                    <!-- Financials Section -->
                                    <div class="financials-section mt-4">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <div class="card-title">
                                                <h3 style="color: #007bff;"><?php echo $lang['Financials']; ?></h3>
                                                    </div>

                                            <div class="d-flex align-items-center col-gap-10">
                                        <!-- Currency Filter Dropdown -->
                                        <div class="dropdown-btn">
                                            <div class="dropdown">
                                                <?php
                                                // Get currencies used by this user's milestones only
                                                $userCurrenciesQuery = "SELECT DISTINCT m.currency 
                                                                       FROM milestones m 
                                                                       INNER JOIN projects p ON m.p_id = p.p_id 
                                                                       WHERE (p.c_id = $user_id OR p.main_client_id = $user_id OR FIND_IN_SET($user_id, p.c_ids) > 0)
                                                                       AND m.currency IS NOT NULL 
                                                                       AND m.currency != ''
                                                                       ORDER BY m.currency";
                                                $userCurrenciesResult = $database->query($userCurrenciesQuery);
                                                $userCurrencies = [];
                                                while ($row = $database->fetchArray($userCurrenciesResult)) {
                                                    $userCurrencies[] = $row['currency'];
                                                }
                                                
                                                // Debug: Log what currencies are found
                                                error_log("User $user_id currencies found: " . implode(', ', $userCurrencies));
                                                
                                                // Get user's preferred currency (from add-client.php logic)
                                                $userPreferredCurrency = $profileUser->currency ?: 'USD,$';
                                                $defaultCurrencyDisplay = getCurrencyCountryName($userPreferredCurrency);
                                                
                                                // If user has invoices, prioritize their preferred currency
                                                $orderedCurrencies = [];
                                                if ($userPreferredCurrency && in_array($userPreferredCurrency, $userCurrencies)) {
                                                    $orderedCurrencies[] = $userPreferredCurrency;
                                                }
                                                foreach ($userCurrencies as $currency) {
                                                    if ($currency !== $userPreferredCurrency) {
                                                        $orderedCurrencies[] = $currency;
                                                    }
                                                }
                                                
                                                // Set default currency for JavaScript
                                                $defaultCurrency = !empty($orderedCurrencies) ? $orderedCurrencies[0] : $userPreferredCurrency;
                                                
                                                // Fallback: If still no currency, use first available currency
                                                if (empty($defaultCurrency) && !empty($userCurrencies)) {
                                                    $defaultCurrency = $userCurrencies[0];
                                                }
                                                
                                                // Debug: Log ordered currencies
                                                error_log("Ordered currencies for dropdown: " . implode(', ', $orderedCurrencies));
                                                error_log("Final defaultCurrency: " . $defaultCurrency);
                                                ?>
                                                <button class="btn btn-light dropdown-toggle" type="button" id="currencyFilterDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                                                    <?php echo $defaultCurrencyDisplay; ?>
                                                </button>
                                                <ul class="dropdown-menu p-3" aria-labelledby="currencyFilterDropdown" style="min-width: 200px;">
                                                    <?php
                                                    foreach ($orderedCurrencies as $currency) {
                                                        $currency = trim($currency);
                                                        if (!empty($currency)) {
                                                            $currencyParts = explode(',', $currency);
                                                            $currencyCode = trim($currencyParts[0]);
                                                            $currencySymbol = isset($currencyParts[1]) ? trim($currencyParts[1]) : $currencyCode;
                                                            
                                                            // Get country name for display
                                                            $countryName = '';
                                                            switch ($currencyCode) {
                                                                case 'USD':
                                                                    $countryName = 'United States';
                                                                    break;
                                                                case 'PKR':
                                                                    $countryName = 'Pakistan';
                                                                    break;
                                                                case 'Rs':
                                                                    $countryName = 'Pakistan';
                                                                    break;
                                                                case 'CAD':
                                                                    $countryName = 'Canada';
                                                                    break;
                                                                case 'EUR':
                                                                    $countryName = 'European Union';
                                                                    break;
                                                                case 'GBP':
                                                                    $countryName = 'United Kingdom';
                                                                    break;
                                                                case 'INR':
                                                                    $countryName = 'India';
                                                                    break;
                                                                case 'JPY':
                                                                    $countryName = 'Japan';
                                                                    break;
                                                                case 'AUD':
                                                                    $countryName = 'Australia';
                                                                    break;
                                                                case 'CHF':
                                                                    $countryName = 'Switzerland';
                                                                    break;
                                                                case 'CNY':
                                                                    $countryName = 'China';
                                                                    break;
                                                                default:
                                                                    $countryName = $currencyCode;
                                                            }
                                                            
                                                            $displayName = $countryName . ' (' . $currencySymbol . ')';
                                                            echo '<li><button class="dropdown-item" type="button" onclick="selectCurrency(\'' . $currency . '\')">' . $displayName . '</button></li>';
                                                        }
                                                    }
                                                    ?>
                                                </ul>
                                            </div>
                                        </div>
                                                
                                                <!-- Date Range Dropdown -->
                                                <div class="dropdown-btn">
                                                    <div class="dropdown">
                                                        <button class="btn btn-light dropdown-toggle" type="button" id="customRangeDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                                                            <?php echo $lang['This Year (Jan - Today)']; ?>
                                                        </button>
                                                        <ul class="dropdown-menu p-3" aria-labelledby="customRangeDropdown" style="min-width: 300px;">
                                                            <li><button class="dropdown-item" type="button" onclick="selectQuickRange('last7')"><?php echo $lang['Last 7 days']; ?></button></li>
                                                            <li><button class="dropdown-item" type="button" onclick="selectQuickRange('last30')"><?php echo $lang['Last 30 days']; ?></button></li>
                                                            <li><button class="dropdown-item" type="button" onclick="selectQuickRange('last90')"><?php echo $lang['Last 90 days']; ?></button></li>
                                                            <li><button class="dropdown-item" type="button" onclick="selectQuickRange('last6months')"><?php echo $lang['Last 6 months']; ?></button></li>
                                                            <li><button class="dropdown-item" type="button" onclick="selectQuickRange('thisYear')"><?php echo $lang['This Year (Jan - Today)']; ?></button></li>
                                                            <li><button class="dropdown-item" type="button" onclick="selectQuickRange('february2025')">February 2025 (Payment Date)</button></li>
                                                            <li><hr class="dropdown-divider"></li>
                                                            <li>
                                                                <div class="px-2">
                                                                    <label><?php echo $lang['Custom']; ?>:</label>
                                                                    <input type="date" id="customStart" class="form-control mb-2">
                                                                    <input type="date" id="customEnd" class="form-control mb-2">
                                                                    <button class="primary-btn w-100" type="button" onclick="selectCustomRange()"><?php echo $lang['Apply']; ?></button>
                                            </div>
                                                            </li>
                                                        </ul>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        
                                
                                        
                                        <!-- Financial Chart - Same as index.php -->
                                        <div class="monthly-rev mb-4">
                                            <div class="d-flex">
                                                <div class="month-rps flex-grow" id="month-rps">
                                                    <div class="earnings-block">
                                                        <div class="green font-size-14"><?php echo $lang['Total Paid']; ?></div>
                                                        <div id="totalPaidDisplay"><?php echo getCurrencySymbol($defaultCurrency); ?><?php echo number_format($user_total_paid); ?></div>
                                                        <!-- Debug: defaultCurrency = <?php echo $defaultCurrency; ?> -->
                                                    </div>
                                                    <div class="unpaid-block">
                                                        <div class="red font-size-14"><?php echo $lang['Total Unpaid']; ?></div>
                                                        <div id="totalUnpaidDisplay"><?php echo getCurrencySymbol($defaultCurrency); ?><?php echo number_format($user_total_unpaid); ?></div>
                                                    </div>
                                                </div>
                                                <div class="chart-filter">
                                                    <span class="legend-dot earnings d-flex"><i class="dot bg-green"></i> <?php echo $lang['Earnings']; ?></span>
                                                    <span class="legend-dot invoices d-flex"><i class="dot bg-red"></i> <?php echo $lang['Unpaid']; ?></span>
                                            </div>
                                                    </div>
                                                    </div>
                                        <div class="stats-graph">
                                            <canvas id="earningsLineChart" height="300"></canvas>
                                            </div>
                                        </div>
                                    </div>
                                </div>  
                                <?php } ?> 
                            <?php if ($tab == 'invoice'): ?>
                            <div class="row vh-100">
                                <div class="container-fluid vh-100">
                                    <div class="row vh-100">
                                        <div class="col-12 extra-pd">

                                            
                                            <div class="d-grid d-md-flex col-gap flex-wrap">
                                                <?php
                                                $search = isset($_GET['search']) ? trim($_GET['search']) : '';
                                                $currencyFilter = isset($_GET['currency_filter']) ? $_GET['currency_filter'] : 'all';
                                                
                                                // Build the WHERE clause
                                                $whereConditions = ["p.c_id = $user_id"];
                                                
                                                if (!empty($search)) {
                                                    $searchSql = $database->escapeValue($search);
                                                    $whereConditions[] = "(m.title LIKE '%$searchSql%' OR p.project_title LIKE '%$searchSql%')";
                                                }
                                                
                                                if ($currencyFilter !== 'all') {
                                                    // Clean the currency filter to get just the code
                                                    $cleanCurrencyFilter = explode(',', $currencyFilter)[0];
                                                    $currencyFilterSql = $database->escapeValue($cleanCurrencyFilter);
                                                    // Match both formats: "PKR,Rs" and "PKR"
                                                    $whereConditions[] = "(m.currency LIKE '$currencyFilterSql,%' OR m.currency = '$currencyFilterSql' OR m.currency LIKE '%,$currencyFilterSql')";
                                                }
                                                
                                                $whereClause = implode(' AND ', $whereConditions);
                                                $milestonesQuery = "SELECT m.*, m.currency, p.project_title FROM milestones m INNER JOIN projects p ON m.p_id = p.p_id WHERE $whereClause ORDER BY m.deadline DESC";
                                                $milestonesResult = $database->query($milestonesQuery);
                                                $milestones = [];
                                                while ($row = $database->fetchArray($milestonesResult)) {
                                                    $milestones[] = $row;
                                                }
                                                if (empty($milestones)) {
                                                    echo '<div class="col"><div class="empty-box">' . $lang['No invoices found'] . '</div></div>';
                                                }
                                                foreach ($milestones as $milestone) {
                                                ?>
                                                <div class="cols">
                                                    <div class="card shadow-sm card-style">
                                                        <div class="card-body">
                                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                                <span class="size-12 badge <?php echo $milestone['status']==0 ? 'red-badge' : 'success'; ?>">
                                                                    <?php echo $milestone['status']==0 ? $lang['Unpaid'] : $lang['Paid']; ?>
                                                                </span>
                                                                <div class="dropdown">
                                                                    <button class="btn-dots" type="button" id="dropdownMenu<?php echo $milestone['id']; ?>" data-bs-toggle="dropdown" aria-expanded="false">
                                                                       <?php echo ts_icon('dots-vertical', 'w-6'); ?><?php echo $lang['View Project']; ?>

                                                                            </a>
                                                                        </li>
                                                                        <li>
                                                                            <form method="post" action="#">
                                                                                <input type="hidden" value="<?php echo $milestone['id'];?>" name="edit_id" />
                                                                                <button type="submit" class="dropdown-item" name="edit-mile">
                                                                                    <?php echo ts_icon('edit', 'tasksession-timer-log-menu-ico me-2'); ?> <?php echo $lang['Edit Invoice']; ?>
                                                                                </button>
                                                                            </form>
                                                                        </li>
                                                                        <li>
                                                                            <form method="post" action="#">
                                                                                <input type="hidden" value="<?php echo $milestone['id'];?>" name="delete_id" />
                                                                                <button type="submit" class="dropdown-item text-danger" name="delete-mile" onclick="return confirm('Are you sure you want to delete this milestone?');">
                                                                                    <?php echo ts_icon('delete', 'tasksession-timer-log-menu-ico me-2'); ?> <?php echo $lang['Delete']; ?>
                                                                                </button>
                                                                            </form>
                                                                        </li>
                                                                    </ul>
                                                                </div>
                                                            </div>
                                                            <h5 class="card-title grey"><?php echo htmlspecialchars($milestone['title']); ?></h5>
                                                            <h3 class="big-text"><?php echo getCurrencySymbol($milestone['currency']) . number_format($milestone['budget']); ?></h3>
                                                            <div class="task-date grey">
                                                                <div><b class="dark"><?php echo $lang['Due date']; ?>:</b> <?php echo $milestone['deadline']; ?></div>
                                                                <div><b class="dark"><?php echo $lang['Invoice']; ?>: #</b> <?php echo $milestone['p_id'] . $milestone['id']; ?></div>
                                                            </div>
                                                            <div>
                                                                <form method="post" action="#" class="d-inline">
                                                                    <input type="hidden" value="<?php echo $milestone['budget'];?>" name="edit_id2" />
                                                                    <input type="hidden" value="<?php echo $milestone['id'];?>" name="edit_id1" />
                                                                    <button type="submit" class="btn btn-outline-grey" name="edit-mile1">
                                                                        <?php echo $lang['View invoice']; ?>
                                                                    </button>
                                                                </form>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <?php } ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>
							   <?php if ($tab == 'projects'): ?>
                                    <div class="row" id="project-grid">
                                        <?php
                                        $search = isset($_GET['search']) ? trim($_GET['search']) : '';
                                        if ($user->accountStatus == 3) { // staff
                                            if (!empty($search)) {
                                                $searchSql = $database->escapeValue($search);
                                                $userProjects = projects::findBySql("SELECT * FROM projects WHERE FIND_IN_SET($user_id, s_ids) AND archive = 0 AND trash != 1 AND project_title LIKE '%$searchSql%'");
                                            } else {
                                                $userProjects = projects::findBySql("SELECT * FROM projects WHERE FIND_IN_SET($user_id, s_ids) AND archive = 0 AND trash != 1");
                                            }
                                        } else { // client
                                            if (!empty($search)) {
                                                $searchSql = $database->escapeValue($search);
                                                $userProjects = projects::findBySql("SELECT * FROM projects WHERE c_id = $user_id AND archive = 0 AND trash != 1 AND project_title LIKE '%$searchSql%'");
                                            } else {
                                                $userProjects = projects::findBySql("SELECT * FROM projects WHERE c_id = $user_id AND archive = 0 AND trash != 1");
                                            }
                                        }
                                        if ($userProjects):
                                        ?>
                                            <?php foreach ($userProjects as $recentProject):
                                                // Task counts
                                                $projectId = $recentProject->p_id;
                                                $taskResult = $database->query("SELECT COUNT(*) as task_count FROM tasks WHERE project_id = $projectId");
                                                $taskCount = 0;
                                                if($taskRow = $database->fetchArray($taskResult)) {
                                                    $taskCount = $taskRow['task_count'];
                                                }
                                                // Completed tasks
                                                $completedTaskResult = $database->query("SELECT COUNT(*) as completed_count FROM tasks WHERE project_id = $projectId AND status = 'done'");
                                                $completedTaskCount = 0;
                                                if($completedTaskRow = $database->fetchArray($completedTaskResult)) {
                                                    $completedTaskCount = $completedTaskRow['completed_count'];
                                                }
                                                $percent = ($taskCount > 0) ? round(($completedTaskCount / $taskCount) * 100) : 0;
                                            ?>
                                            <div class="col-md-4 col-lg-4 col-xl-4 mb-3">
                                                <div class="card project-card" style="position: relative;">
                                                    <!-- 3-dots dropdown menu -->
                                                    <div class="dropdown card-action-dropdown" style="position: absolute; top: 12px; right: 16px;">
                                                        <button class="btn btn-link dropdown-toggle" type="button" id="dropdownMenu<?php echo $recentProject->p_id; ?>" data-bs-toggle="dropdown" aria-expanded="false" style="color: #333; font-size: 20px; text-decoration: none;">
                                                            <span class="light-grey" style="font-size: 20px; letter-spacing: &#8226;">&#8226;&#8226;&#8226;</span>
                                                        </button>
                                                        <ul class="dropdown-menu" aria-labelledby="dropdownMenu<?php echo $recentProject->p_id; ?>">
                                                            <li>
                                                                <a class="dropdown-item" href="overview?projectId=<?php echo $recentProject->p_id; ?>">
                                                                    <?php echo ts_icon('clock', 'tasksession-timer-log-menu-ico me-2'); ?><?php echo $lang['Overview']; ?>
                                                                </a>
                                                            </li>
                                                            <li>
                                                                <form action="../discussion?project_id=<?php echo $recentProject->p_id;?>" method="post" style="display:inline;">
                                                                    <input type="hidden" name="user_id" value="<?php echo $recentProject->c_id;?>" />
                                                                    <input type="hidden" name="project_id" value="<?php echo $recentProject->p_id;?>" />
                                                                    <button type="submit" name="chat" class="dropdown-item">
                                                                        <?php echo ts_icon('document-text', 'tasksession-timer-log-menu-ico me-2'); ?><?php echo $lang['Discussion']; ?>
                                                                    </button>
                                                                </form>
                                                            </li>
                                                            <li>
                                                                <a class="dropdown-item" href="task?projectId=<?php echo $recentProject->p_id; ?>">
                                                                    <?php echo ts_icon('tasks', 'tasksession-timer-log-menu-ico me-2'); ?><?php echo $lang['Tasks']; ?>
                                                                </a>
                                                            </li>
                                                            <li>
                                                                <a href="edit-project?id=<?php echo $recentProject->p_id;?>" class="dropdown-item"><?php echo ts_icon('edit', 'tasksession-timer-log-menu-ico me-2'); ?><?php echo $lang['Edit Project']; ?></a>
                                                            </li>
                                                            <li>
                                                                <form method="post" action="#" style="display:inline;">
                                                                    <input type="hidden" value="<?php echo $recentProject->p_id;?>" name="comp_id" />
                                                                    <input type="hidden" value="<?php if($recentProject->status == 0){ echo '1';}else { echo '0';} ?>" name="comp_val" />
                                                                    <button type="submit" name="comp_proj" class="dropdown-item">
                                                                        <?php if($recentProject->status == 0){ ?>
                                                                            <?php echo ts_icon('check-circle', 'tasksession-timer-log-menu-ico me-2'); ?>Mark as complete
                                                                        <?php } else { ?>
                                                                            <?php echo ts_icon('plus', 'tasksession-timer-log-menu-ico me-2'); ?>Re-open
                                                                        <?php } ?>
                                                                    </button>
                                                                </form>
                                                            </li>
                                                            <li>
                                                                <form method="post" action="#" style="display:inline;">
                                                                    <input type="hidden" value="<?php echo $recentProject->p_id;?>" name="arc_id" />
                                                                    <input type="hidden" value="<?php if($recentProject->archive == 0){ echo '1';}else { echo '0';} ?>" name="arc_val" />
                                                                    <button type="submit" name="arc_proj" class="dropdown-item">
                                                                        <?php echo ts_icon('archive', 'tasksession-timer-log-menu-ico me-2'); ?><?php if($recentProject->archive == 0){ echo $lang['Move to Archive']; }else{ echo $lang['Move to Projects']; } ?>
                                                                    </button>
                                                                </form>
                                                            </li>
                                                            <li>
                                                                <form method="post" action="#" style="display:inline;">
                                                                    <input type="hidden" value="<?php echo $recentProject->p_id;?>" name="del_id" />
                                                                    <input type="hidden" value="<?php if($recentProject->trash == 0){ echo '1';}else { echo '0';} ?>" name="del_val" />
                                                                    <button type="submit" name="del_proj" class="dropdown-item">
                                                                        <?php echo ts_icon('delete', 'tasksession-timer-log-menu-ico me-2'); ?><?php echo $lang['Delete Project']; ?>
                                                                    </button>
                                                                </form>
                                                            </li>
                                                        </ul>
                                                    </div>
                                                    <div class="card-body">
                                                        <!-- Due Date -->
                                                        <div class="due-date mb-2">
                                                            <?php
                                                                $due = strtotime($recentProject->end_time);
                                                                $now = strtotime(date('Y-m-d'));
                                                                $daysLeft = ceil(($due - $now) / 86400);
                                                                if ($daysLeft > 1) {
                                                                    echo "<span class='badge success'>DUE: $daysLeft DAY LEFT</span>";
                                                                } elseif ($daysLeft == 1) {
                                                                    echo "<span class='badge red-badge'>DUE: 1 DAY LEFT</span>";
                                                                } elseif ($daysLeft == 0) {
                                                                    echo "<span class='badge red-badge'>DUE: TODAY</span>";
                                                                } else {
                                                                    echo "<span class='badge red-badge'>DUE PASSED</span>";
                                                                }
                                                            ?>
                                                        </div>
                                                        <!-- Project Title -->
                                                        <h5 class="card-title mb-4"><?php echo htmlspecialchars($recentProject->project_title); ?></h5>
                                                        <!-- Assigned Team and Clients -->
                                                        <div class="d-flex col-gap-40 mb-4 flex-wrap" style="text-align: left;">
                                                            <div class="clients-rpt" style="text-align: left;">
                                                                <div class="title-head mb-2"><?php echo $lang['Assigned Team']; ?></div>
                                                                <div class="d-flex align-items-center">
                                                                    <?php 
                                                                    $s_ids = $recentProject->s_ids;
                                                                    $st_ids = explode(',', $s_ids);
                                                                    $counter = 0;
                                                                    foreach($st_ids as $st_id){
                                                                        if($st_id != $recentProject->c_id && $st_id != 0){
                                                                            $counter++;
                                                                            if($counter > 3){} else {
                                                                                $user2 = user::findById($st_id); 
                                                                                echo '<div class="user-box">';
                                                                                echo getUserAvatarHtml($st_id, $user2->firstName, $user2->lastName ?? '', 36, 36, '', $user2->firstName);
                                                                                echo '</div>'; 
                                                                            }
                                                                        }
                                                                    }
                                                                    if($counter > 3){
                                                                        $more = $counter-3;
                                                                        echo '<div class="plus-more shadow-dept">+'. $more .'</div>'; 
                                                                    }
                                                                    ?>
                                                                </div>
                                                            </div>
                                                            <!-- Clients -->
                                                            <div class="clients mb-2">
                                                                <div class="title-head mb-2"><?php echo $lang['Clients']; ?></div>
                                                                <div class="d-flex align-items-center">
                                                                    <?php 
                                                                    // Display main client first
                                                                    $mainClient = user::findById($recentProject->main_client_id ?: $recentProject->c_id);
                                                                    if ($mainClient) {
                                                                        echo '<div class="user-box">';
                                                                        echo getUserAvatarHtml($mainClient->id, $mainClient->firstName, $mainClient->lastName ?? '', 36, 36, '', $mainClient->firstName);
                                                                        echo '</div>';
                                                                    }
                                                                    
                                                                    // Display additional clients if any
                                                                    if (!empty($recentProject->c_ids)) {
                                                                        $allClientIds = array_filter(explode(',', $recentProject->c_ids));
                                                                        $additionalClients = array_filter($allClientIds, function($id) use ($recentProject) {
                                                                            return $id != $recentProject->main_client_id && $id != $recentProject->c_id;
                                                                        });
                                                                        
                                                                        $clientCounter = 0;
                                                                        foreach($additionalClients as $clientId) {
                                                                            $clientCounter++;
                                                                            if($clientCounter > 1) break; // Show max 1 additional client (total 2 clients)
                                                                            
                                                                            $client = user::findById($clientId);
                                                                            if ($client) {
                                                                                echo '<div class="user-box">';
                                                                                echo getUserAvatarHtml($client->id, $client->firstName, $client->lastName ?? '', 36, 36, '', $client->firstName);
                                                                                echo '</div>';
                                                                            }
                                                                        }
                                                                        
                                                                        if(count($additionalClients) > 1) {
                                                                            $more = count($additionalClients) - 1;
                                                                            echo '<div class="plus-more shadow-dept">+'. $more .'</div>';
                                                                        }
                                                                    }
                                                                    ?>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <!-- Progress Bar and Task Completion -->
                                                        <div class="progress mb-2" style="height:8px;">
                                                            <div class="progress-bar" role="progressbar" style="width: <?php echo $percent; ?>%; background: <?php echo $percent < 30 ? '#f66' : ($percent < 70 ? '#f9b233' : '#4caf50'); ?>;" aria-valuenow="<?php echo $percent; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                                        </div>
                                                        <div class="mb-2 d-flex col-gap-5">
                                                            <div class="grey bold"><?php echo $lang['TASK']; ?></div>
                                                            <?php echo $completedTaskCount; ?>/<?php echo $taskCount; ?>
                                                            <span class="text-align-right flex-grow"><?php echo $percent; ?>%</span>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <?php endforeach; ?>
                                        </div>
                                        <?php else: ?>
                                            <div class="empty-box"><?php echo $lang['No projects found.']; ?></div>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            <?php if ($tab == 'activity'): ?>
                                <div class="vh-100">
                                    <div class="table-responsive card scroll-x vh-100">
                                        <table class="table table-new projectspage" data-pagination="true" data-page-size="10">
                                            <thead>
                                                <tr>
                                                    <th class="text-center" width="5%">
                                                        <?php echo $lang['No.']; ?>
                                                    </th>
                                                    <th width="8%">
                                                        <?php echo $lang['Type']; ?>
                                                    </th>
                                                    <th width="15%">
                                                        <?php echo $lang['Event']; ?>
                                                    </th>
                                                    <th width="25%">
                                                        <?php echo $lang['Description']; ?>
                                                    </th>
                                                    <th width="12%">
                                                        <?php echo $lang['IP Address']; ?>
                                                    </th>
                                                    <th width="15%">
                                                        <?php echo $lang['Date & Time']; ?>
                                                    </th>
                                                    <th width="20%">
                                                        <?php echo $lang['Session Duration']; ?>
                                                    </th>
                                                </tr>
                                            </thead>
                                            <tbody id="activity-tbl">
                                                <?php
                                                // Check for inactive sessions and clean them up
                                                require_once('../includes/activity_logger.php');
                                                ActivityLogger::checkInactiveSessions();
                                                
                                                // Get user activity data
                                                $activityData = [];
                                                
                                                // Apply date filtering
                                                $dateFilter = isset($_GET['date_filter']) ? $_GET['date_filter'] : 'all';
                                                $loginDateWhereClause = "";
                                                $securityDateWhereClause = "";
                                                
                                                if ($dateFilter !== 'all') {
                                                    $currentDate = date('Y-m-d');
                                                    
                                                    switch ($dateFilter) {
                                                        case 'today':
                                                            $loginDateWhereClause = " AND DATE(attempt_time) = '$currentDate'";
                                                            $securityDateWhereClause = " AND DATE(created_at) = '$currentDate'";
                                                            break;
                                                        case 'yesterday':
                                                            $yesterday = date('Y-m-d', strtotime('-1 day'));
                                                            $loginDateWhereClause = " AND DATE(attempt_time) = '$yesterday'";
                                                            $securityDateWhereClause = " AND DATE(created_at) = '$yesterday'";
                                                            break;
                                                        case 'this_week':
                                                            $startOfWeek = date('Y-m-d', strtotime('monday this week'));
                                                            $loginDateWhereClause = " AND DATE(attempt_time) >= '$startOfWeek'";
                                                            $securityDateWhereClause = " AND DATE(created_at) >= '$startOfWeek'";
                                                            break;
                                                        case 'last_week':
                                                            $startLastWeek = date('Y-m-d', strtotime('monday last week'));
                                                            $endLastWeek = date('Y-m-d', strtotime('sunday last week'));
                                                            $loginDateWhereClause = " AND DATE(attempt_time) >= '$startLastWeek' AND DATE(attempt_time) <= '$endLastWeek'";
                                                            $securityDateWhereClause = " AND DATE(created_at) >= '$startLastWeek' AND DATE(created_at) <= '$endLastWeek'";
                                                            break;
                                                        case 'this_month':
                                                            $startOfMonth = date('Y-m-01');
                                                            $loginDateWhereClause = " AND DATE(attempt_time) >= '$startOfMonth'";
                                                            $securityDateWhereClause = " AND DATE(created_at) >= '$startOfMonth'";
                                                            break;
                                                        case 'last_month':
                                                            $startLastMonth = date('Y-m-01', strtotime('first day of last month'));
                                                            $endLastMonth = date('Y-m-t', strtotime('last day of last month'));
                                                            $loginDateWhereClause = " AND DATE(attempt_time) >= '$startLastMonth' AND DATE(attempt_time) <= '$endLastMonth'";
                                                            $securityDateWhereClause = " AND DATE(created_at) >= '$startLastMonth' AND DATE(created_at) <= '$endLastMonth'";
                                                            break;
                                                        case 'this_year':
                                                            $startOfYear = date('Y-01-01');
                                                            $loginDateWhereClause = " AND DATE(attempt_time) >= '$startOfYear'";
                                                            $securityDateWhereClause = " AND DATE(created_at) >= '$startOfYear'";
                                                            break;
                                                        case 'custom':
                                                            $fromDate = isset($_GET['from_date']) ? $_GET['from_date'] : '';
                                                            $toDate = isset($_GET['to_date']) ? $_GET['to_date'] : '';
                                                            if ($fromDate && $toDate) {
                                                                $loginDateWhereClause = " AND DATE(attempt_time) >= '$fromDate' AND DATE(attempt_time) <= '$toDate'";
                                                                $securityDateWhereClause = " AND DATE(created_at) >= '$fromDate' AND DATE(created_at) <= '$toDate'";
                                                            }
                                                            break;
                                                    }
                                                }
                                                
                                                // Get login attempts
                                                $loginQuery = "SELECT 'login' as type, 
                                                                    CASE 
                                                                        WHEN type = 'logout' THEN 'logout'
                                                                        WHEN success = 1 THEN 'user.login'
                                                                        ELSE 'user.login_failed'
                                                                    END as event,
                                                                    attempt_time as created_at,
                                                                    ip_address,
                                                                    user_agent,
                                                                    CASE 
                                                                        WHEN type = 'logout' THEN 'User logged out'
                                                                        WHEN success = 1 THEN 'Successful login'
                                                                        ELSE 'Failed login attempt'
                                                                    END as description
                                                               FROM login_attempts 
                                                               WHERE user_id = ? $loginDateWhereClause
                                                               ORDER BY attempt_time DESC 
                                                               LIMIT 50";
                                                $stmt = $connect->prepare($loginQuery);
                                                $stmt->bind_param("i", $user_id);
                                                $stmt->execute();
                                                $loginResult = $stmt->get_result();
                                                
                                                while ($row = $loginResult->fetch_assoc()) {
                                                    $activityData[] = $row;
                                                }
                                                
                                                // Get security logs
                                                $securityQuery = "SELECT 'security' as type,
                                                                      event,
                                                                      created_at,
                                                                      ip_address,
                                                                      user_agent,
                                                                      details as description
                                                                 FROM security_logs 
                                                                 WHERE user_id = ? $securityDateWhereClause
                                                                 ORDER BY created_at DESC 
                                                                 LIMIT 50";
                                                $stmt = $connect->prepare($securityQuery);
                                                $stmt->bind_param("i", $user_id);
                                                $stmt->execute();
                                                $securityResult = $stmt->get_result();
                                                
                                                while ($row = $securityResult->fetch_assoc()) {
                                                    $activityData[] = $row;
                                                }
                                                
                                                // Sort all activities by date
                                                usort($activityData, function($a, $b) {
                                                    return strtotime($b['created_at']) - strtotime($a['created_at']);
                                                });
                                                
                                                // Pagination logic
                                                $page_size = 10;
                                                $current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
                                                $total_records = count($activityData);
                                                $total_pages = ceil($total_records / $page_size);
                                                
                                                // Apply pagination
                                                $start_index = ($current_page - 1) * $page_size;
                                                $paginatedData = array_slice($activityData, $start_index, $page_size);
                                                
                                                if (empty($paginatedData)) {
                                                    echo '<tr><td colspan="7" class="text-center">' . $lang['No activity found for this user.'] . '</td></tr>';
                                                } else {
                                                    $counter = $start_index + 1;
                                                    foreach ($paginatedData as $activity) {
                                                        $badgeClass = '';
                                                        $eventText = '';
                                                        
                                                        switch ($activity['event']) {
                                                            case 'user.login':
                                                                $badgeClass = 'badge-success';
                                                                $eventText = $lang['Login Successful'];
                                                                break;
                                                            case 'user.login_failed':
                                                                $badgeClass = 'badge-danger';
                                                                $eventText = $lang['Login Failed'];
                                                                break;
                                                            case 'password.change':
                                                                $badgeClass = 'badge-warning';
                                                                $eventText = $lang['Password Changed'];
                                                                break;
                                                            case 'logout':
                                                                $badgeClass = 'badge-info';
                                                                $eventText = $lang['Logout'];
                                                                break;
                                                            default:
                                                                $badgeClass = 'badge-info';
                                                                $eventText = $activity['event'];
                                                                break;
                                                        }
                                                        
                                                        // Calculate session duration for login events
                                                        $sessionDuration = '-';
                                                        if ($activity['event'] == 'user.login') {
                                                            // Find the next logout event for this user after this login
                                                            $loginTime = strtotime($activity['created_at']);
                                                            $logoutQuery = "SELECT attempt_time FROM login_attempts 
                                                                          WHERE user_id = ? AND type = 'logout' AND attempt_time > ? 
                                                                          ORDER BY attempt_time ASC LIMIT 1";
                                                            $logoutStmt = $connect->prepare($logoutQuery);
                                                            $logoutStmt->bind_param("is", $user_id, $activity['created_at']);
                                                            $logoutStmt->execute();
                                                            $logoutResult = $logoutStmt->get_result();
                                                            
                                                            if ($logoutRow = $logoutResult->fetch_assoc()) {
                                                                $logoutTime = strtotime($logoutRow['attempt_time']);
                                                                $sessionDuration = function_exists('user_presence_activity_duration_label')
                                                                    ? user_presence_activity_duration_label($loginTime, $logoutTime, 0, 'offline')
                                                                    : user_presence_format_duration($logoutTime - $loginTime);
                                                            } else {
                                                                $seenUser = (isset($profileUser) && is_object($profileUser)) ? $profileUser : User::findById((int)$user_id);
                                                                $sessionDuration = function_exists('user_presence_activity_duration_label')
                                                                    ? user_presence_activity_duration_label(
                                                                        $loginTime,
                                                                        null,
                                                                        $seenUser->last_seen ?? 0,
                                                                        $seenUser->session_status ?? 'offline'
                                                                    )
                                                                    : 'Active';
                                                            }
                                                        } elseif ($activity['event'] == 'logout') {
                                                            // Find the previous login event for this user before this logout
                                                            $logoutTime = strtotime($activity['created_at']);
                                                            $loginQuery = "SELECT attempt_time FROM login_attempts 
                                                                         WHERE user_id = ? AND success = 1 AND type = 'login' AND attempt_time < ? 
                                                                         ORDER BY attempt_time DESC LIMIT 1";
                                                            $loginStmt = $connect->prepare($loginQuery);
                                                            $loginStmt->bind_param("is", $user_id, $activity['created_at']);
                                                            $loginStmt->execute();
                                                            $loginResult = $loginStmt->get_result();
                                                            
                                                            if ($loginRow = $loginResult->fetch_assoc()) {
                                                                $loginTime = strtotime($loginRow['attempt_time']);
                                                                $duration = $logoutTime - $loginTime;
                                                                
                                                                if ($duration < 60) {
                                                                    $sessionDuration = $duration . ' seconds';
                                                                } elseif ($duration < 3600) {
                                                                    $minutes = floor($duration / 60);
                                                                    $seconds = $duration % 60;
                                                                    $sessionDuration = $minutes . 'm ' . $seconds . 's';
                                                                } else {
                                                                    $hours = floor($duration / 3600);
                                                                    $minutes = floor(($duration % 3600) / 60);
                                                                    $sessionDuration = $hours . 'h ' . $minutes . 'm';
                                                                }
                                                            }
                                                        }
                                                        
                                                        echo '<tr>';
                                                        echo '<td class="text-center">' . $counter . '</td>';
                                                        echo '<td><span class="badge ' . $badgeClass . '">' . ucfirst($activity['type']) . '</span></td>';
                                                        echo '<td><div class="tbl-ttl">' . htmlspecialchars($eventText) . '</div></td>';
                                                        echo '<td><div class="tbl-ttl">' . htmlspecialchars($activity['description']) . '</div></td>';
                                                        echo '<td><div class="tbl-ttl">' . htmlspecialchars($activity['ip_address']) . '</div></td>';
                                                        echo '<td><div class="tbl-ttl">' . date('M j, Y g:i A', strtotime($activity['created_at'])) . '</div></td>';
                                                        if ($sessionDuration == 'Active') {
                                                            $durationClass = 'session-duration active';
                                                        } elseif (strpos($sessionDuration, 'Timeout') !== false) {
                                                            $durationClass = 'session-duration timeout';
                                                        } else {
                                                            $durationClass = 'session-duration';
                                                        }
                                                        echo '<td><div class="' . $durationClass . '">' . $sessionDuration . '</div></td>';
                                                        echo '</tr>';
                                                        $counter++;
                                                    }
                                                }
                                                ?>
                                            </tbody>
                                        </table>
                                    </div>
                                    
                                    <!-- Pagination -->
                                    <div class="row pagination-box">
                                        <div class="col-md-6 resilts-txt">
                                            <?php 
                                            $start_from = ($current_page - 1) * $page_size + 1;
                                            $end_at = min($current_page * $page_size, $total_records);
                                            
                                            if ($total_records > 0) {
                                                echo $lang['Showing'] . ' <span class="start_val">' . $start_from . '</span> ' . $lang['to'] . ' <span class="end_val">' . $end_at . '</span> ' . $lang['of'] . ' <span class="total_val">' . $total_records . '</span> ' . $lang['entries'];
                                            }
                                            ?>
                                        </div>
                                        <div class="col-md-6">
                                            <?php
                                            if ($total_records > $page_size) {
                                                $total_pages = ceil($total_records / $page_size);
                                                
                                                // Build pagination parameters
                                                $paginationParams = [];
                                                $paginationParams[] = 'user_id=' . $user_id;
                                                $paginationParams[] = 'tab=activity';
                                                $paginationQueryString = '&' . implode('&', $paginationParams);
                                                
                                                echo '<nav aria-label="Page navigation"><ul class="pagination justify-content-end">';
                                                
                                                // Previous button
                                                if ($current_page > 1) {
                                                    echo '<li class="page-item">
                                                          <a class="page-link" href="?page=' . ($current_page - 1) . $paginationQueryString . '" aria-label="Previous">
                                                            <span aria-hidden="true">&laquo;</span>
                                                            <span class="sr-only">' . $lang['Previous'] . '</span>
                                                          </a>
                                                        </li>';
                                                }
                                                
                                                // Page numbers
                                                $pagLink = "";
                                                for ($i = 1; $i <= $total_pages; $i++) {
                                                    $activeClass = ($i == $current_page) ? ' active' : '';
                                                    $pagLink .= "<li class='page-item$activeClass'><a class='page-link' href='?page=" . $i . $paginationQueryString . "'>" . $i . "</a></li>";
                                                }
                                                echo $pagLink;
                                                
                                                // Next button
                                                if ($current_page < $total_pages) {
                                                    echo '<li class="page-item">
                                                          <a class="page-link" href="?page=' . ($current_page + 1) . $paginationQueryString . '" aria-label="Next">
                                                            <span aria-hidden="true">&raquo;</span>
                                                            <span class="sr-only">' . $lang['Next'] . '</span>
                                                          </a>
                                                        </li>';
                                                }
                                                
                                                echo '</ul></nav>';
                                            }
                                            ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                            <?php if ($tab == 'tasks'): ?>
                                <?php
                                // Load task model
                                require_once("../includes/task.php");
                                
                                // Define status types and labels
                                $statusTypes = ['todo', 'inprogress', 'review', 'done'];
                                $statusLabels = [
                                    'todo' => 'To Do',
                                    'inprogress' => 'In Progress',
                                    'review' => 'In Review',
                                    'done' => 'Completed'
                                ];
                                $statusColors = [
                                    'todo' => 'todo todo-bg-op',
                                    'inprogress' => 'inprogress inprogress-bg-op',
                                    'review' => 'review review-bg-op',
                                    'done' => 'done review done-bg-op'
                                ];
                                
                                // Load custom column names from database
                                $columnNamesQuery = "SELECT column_key, custom_name FROM project_columns WHERE project_id = 0";
                                $columnNamesResult = $database->query($columnNamesQuery);
                                
                                if ($columnNamesResult && $database->numRows($columnNamesResult) > 0) {
                                    while ($columnRow = $database->fetchArray($columnNamesResult)) {
                                        $key = $columnRow['column_key'];
                                        if (isset($statusLabels[$key])) {
                                            $statusLabels[$key] = $columnRow['custom_name'];
                                        }
                                    }
                                }
                                
                                // Get status filter from URL
                                $statusFilter = isset($_GET['status']) && in_array($_GET['status'], $statusTypes) ? $_GET['status'] : null;
                                
                                // Get search query from URL
                                $searchQuery = isset($_GET['search']) ? trim($_GET['search']) : '';
                                
                                // Get date filter from URL
                                $dateFilter = isset($_GET['date_filter']) ? $_GET['date_filter'] : null;
                                $fromDate = isset($_GET['from_date']) ? $_GET['from_date'] : '';
                                $toDate = isset($_GET['to_date']) ? $_GET['to_date'] : '';
                                
                                // Get task counts for statistics
                                $taskCounts = [];
                                $totalUserTasks = 0;
                                
                                foreach($statusTypes as $status) {
                                    if ($profileUser->accountStatus == 3) {
                                        // Staff - tasks assigned to them
                                        $countQuery = "SELECT COUNT(*) as count FROM tasks WHERE FIND_IN_SET($user_id, assigned_to) > 0 AND status = '$status'";
                                    } else {
                                        // Client - tasks from projects where they are main client or additional client
                                        $countQuery = "SELECT COUNT(*) as count FROM tasks t 
                                                     LEFT JOIN projects p ON t.project_id = p.p_id 
                                                     WHERE t.project_id > 0 AND t.status = '$status' AND (
                                                         p.c_id = $user_id OR 
                                                         p.main_client_id = $user_id OR 
                                                         FIND_IN_SET($user_id, p.c_ids) > 0
                                                     )";
                                    }
                                    
                                    // Add date filtering to count queries
                                    if ($dateFilter === 'custom' && $fromDate && $toDate) {
                                        $fromDateSafe = $database->escapeValue($fromDate);
                                        $toDateSafe = $database->escapeValue($toDate);
                                        $countQuery .= " AND DATE(t.created_at) >= '$fromDateSafe' AND DATE(t.created_at) <= '$toDateSafe'";
                                    }
                                    
                                    $countResult = $database->query($countQuery);
                                    $countData = $database->fetchArray($countResult);
                                    $taskCounts[$status] = $countData['count'];
                                    $totalUserTasks += $countData['count'];
                                }
                                
                                // Pagination settings
                                $limit = 12;
                                $page = isset($_GET["page"]) ? max(1, (int)$_GET["page"]) : 1;
                                $start_from = ($page-1) * $limit;
                                
                                // Get tasks for current user with project info
                                // For staff: tasks assigned to them
                                // For clients: tasks from projects where they are main client or additional client
                                if ($profileUser->accountStatus == 3) {
                                    // Staff - tasks assigned to them
                                    $tasksQuery = "SELECT t.*, 
                                        CASE 
                                            WHEN t.project_id > 0 THEN p.project_title 
                                            ELSE 'Internal Task' 
                                        END as project_title,
                                        CASE 
                                            WHEN t.project_id > 0 THEN p.p_id 
                                            ELSE 0 
                                        END as p_id,
                                        p.c_id as project_client_id,
                                        u.firstName as client_first_name,
                                        cp.filename as client_image
                                        FROM tasks t 
                                        LEFT JOIN projects p ON t.project_id = p.p_id
                                        LEFT JOIN users u ON p.c_id = u.id
                                        LEFT JOIN profile_pics cp ON cp.fkUserId = u.id
                                        WHERE FIND_IN_SET($user_id, t.assigned_to) > 0";
                                } else {
                                    // Client - tasks from projects where they are main client or additional client
                                    $tasksQuery = "SELECT t.*, 
                                        CASE 
                                            WHEN t.project_id > 0 THEN p.project_title 
                                            ELSE 'Internal Task' 
                                        END as project_title,
                                        CASE 
                                            WHEN t.project_id > 0 THEN p.p_id 
                                            ELSE 0 
                                        END as p_id,
                                        p.c_id as project_client_id,
                                        u.firstName as client_first_name,
                                        cp.filename as client_image
                                        FROM tasks t 
                                        LEFT JOIN projects p ON t.project_id = p.p_id
                                        LEFT JOIN users u ON p.c_id = u.id
                                        LEFT JOIN profile_pics cp ON cp.fkUserId = u.id
                                        WHERE t.project_id > 0 AND (
                                            p.c_id = $user_id OR 
                                            p.main_client_id = $user_id OR 
                                            FIND_IN_SET($user_id, p.c_ids) > 0
                                        )";
                                }
                                
                                $where = [];
                                if ($statusFilter) {
                                    $where[] = "t.status = '" . $database->escapeValue($statusFilter) . "'";
                                }
                                if ($searchQuery !== '') {
                                    $searchSafe = $database->escapeValue($searchQuery);
                                    $where[] = "(t.title LIKE '%$searchSafe%' OR t.description LIKE '%$searchSafe%')";
                                }
                                
                                // Add date filtering
                                if ($dateFilter === 'custom' && $fromDate && $toDate) {
                                    $fromDateSafe = $database->escapeValue($fromDate);
                                    $toDateSafe = $database->escapeValue($toDate);
                                    $where[] = "DATE(t.created_at) >= '$fromDateSafe' AND DATE(t.created_at) <= '$toDateSafe'";
                                }
                                
                                if (count($where) > 0) {
                                    $tasksQuery .= " AND " . implode(' AND ', $where);
                                }
                                
                                $tasksQuery .= " ORDER BY t.created_at DESC 
                                              LIMIT $start_from, $limit";
                                
                                $result = $database->query($tasksQuery);
                                $tasks = [];
                                
                                while($row = $database->fetchArray($result)) {
                                    $tasks[] = $row;
                                }
                                
                                // Get total count for pagination
                                if ($profileUser->accountStatus == 3) {
                                    // Staff - tasks assigned to them
                                    $countQuery = "SELECT COUNT(*) as total FROM tasks WHERE FIND_IN_SET($user_id, assigned_to) > 0";
                                } else {
                                    // Client - tasks from projects where they are main client or additional client
                                    $countQuery = "SELECT COUNT(*) as total FROM tasks t 
                                                 LEFT JOIN projects p ON t.project_id = p.p_id 
                                                 WHERE t.project_id > 0 AND (
                                                     p.c_id = $user_id OR 
                                                     p.main_client_id = $user_id OR 
                                                     FIND_IN_SET($user_id, p.c_ids) > 0
                                                 )";
                                }
                                if (count($where) > 0) {
                                    $countQuery .= " AND " . implode(' AND ', $where);
                                }
                                $countResult = $database->query($countQuery);
                                $countData = $database->fetchArray($countResult);
                                $total_records = $countData['total'];
                                $total_pages = ceil($total_records / $limit);
                                ?>
                                
                                <!-- Task Statistics -->
								
						  <div class="task-area">

                                <div class="row mb-4">
                                    <div class="col-12">
                                        <h3 class="card-title mb-3"><?php echo $lang['Task Statistics']; ?></h3>
                                        <div class="row">
                                            <?php foreach($statusTypes as $status): ?>
                                            <div class="col-md-3 col-sm-6 mb-3">
                                                <div class="stat-card">
                                                    <div class="stat-icon">
                                                        <?php echo ts_icon('document-text'); ?>
                                                    </div>
                                                    <div class="stat-info">
                                                        <h3><?php echo $taskCounts[$status]; ?></h3>
                                                        <p><?php echo $statusLabels[$status]; ?></p>
                                                    </div>
                                                </div>
                                            </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Task Search -->
                                <div class="row mb-3">
                                    <div class="col-md-12">
                                        <form method="GET" action="" class="search-form">
                                            <input type="hidden" name="user_id" value="<?php echo $user_id; ?>">
                                            <input type="hidden" name="tab" value="tasks">
                                            <?php if($statusFilter): ?>
                                                <input type="hidden" name="status" value="<?php echo htmlspecialchars($statusFilter); ?>">
                                            <?php endif; ?>
                                            <?php if($dateFilter === 'custom' && $fromDate && $toDate): ?>
                                                <input type="hidden" name="date_filter" value="custom">
                                                <input type="hidden" name="from_date" value="<?php echo htmlspecialchars($fromDate); ?>">
                                                <input type="hidden" name="to_date" value="<?php echo htmlspecialchars($toDate); ?>">
                                            <?php endif; ?>
                                            <div class="input-group">
                                                <input type="text" 
                                                       name="search" 
                                                       class="form-control" 
                                                       placeholder="Search tasks..." 
                                                       value="<?php echo htmlspecialchars($searchQuery ?? ''); ?>">
                                                <button type="submit" class="search-icon">
                                                    <?php echo ts_icon('search'); ?>
                                                </button>
                                                <?php if(isset($searchQuery) && !empty($searchQuery)): ?>
                                                    <a href="?user_id=<?php echo $user_id; ?>&tab=tasks<?php echo $statusFilter ? '&status='.htmlspecialchars($statusFilter) : ''; ?><?php echo ($dateFilter === 'custom' && $fromDate && $toDate) ? '&date_filter=custom&from_date='.htmlspecialchars($fromDate).'&to_date='.htmlspecialchars($toDate) : ''; ?>" class="cross">
                                                        <?php echo ts_icon('close'); ?>
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                                
                                <!-- Tasks Table -->
                                <div class="table-responsive vh-100">
                                    <table class="table table-new projectspage" id="tasks-table">
                                        <thead>
                                            <tr>
                                                <th width="30%"><?php echo $lang['Task']; ?></th>
                                                <th><?php echo $lang['Status']; ?></th>
                                                <th><?php echo $lang['Assigned To']; ?></th>
                                                <th><?php echo $lang['Client']; ?></th>
                                                <th><?php echo $lang['Due Date']; ?></th>
                                                <th><?php echo $lang['Task Performance']; ?></th>
                                                <th class="max_w_150"><?php echo $lang['Options']; ?></th>
                                            </tr>
                                        </thead>
                                        <tbody id="tasks-tbl">
                                            <?php if(count($tasks) > 0): ?>
                                                <?php foreach($tasks as $task): ?>
                                                <tr data-status="<?php echo $task['status']; ?>">
                                                    <td>  
                                                        <div class="tbl-ttl">
                                                            <div class="light-colors">
                                                                <div class="p-title">
                                                                    <b><?php echo $lang['Project Title']; ?>:</b> <?php
                                                                        $projectTitle = htmlspecialchars($task['project_title']);
                                                                        echo (mb_strlen($projectTitle) > 30) ? mb_substr($projectTitle, 0, 30) . '...' : $projectTitle;
                                                                    ?>
                                                                </div>
                                                            </div>
                                                            <div class="tbl-ttl"><?php echo htmlspecialchars($task['title']); ?></div>
                                                        </div>
                                                    </td>
                                                    <td class="text-center">
                                                        <span class="badge status color-<?php echo $statusColors[$task['status']]; ?>">
                                                            <?php echo $statusLabels[$task['status']]; ?>
                                                        </span>
                                                    </td>
                                                    <td class="clients-rpt" style="text-align: left;"> 
                                                        <?php 
                                                        if(!empty($task['assigned_to'])):
                                                            $assignedIds = explode(',', $task['assigned_to']);
                                                            foreach($assignedIds as $staffId):
                                                                if(!empty($staffId)):
                                                                    $staffMember = User::findById($staffId);
                                                                    if($staffMember):
                                                                        echo '<div class="user-box d-inline-block me-1" data-bs-toggle="tooltip" data-bs-placement="top" title="' . htmlspecialchars($staffMember->firstName . ' ' . ($staffMember->lastName ?? '')) . '">';
                                                                        echo getUserAvatarHtml($staffMember->id, $staffMember->firstName, $staffMember->lastName ?? '', 36, 36, 'img-fluid profile-img', $staffMember->firstName);
                                                                        echo '</div>';
                                                                    endif;
                                                                endif;
                                                            endforeach;
                                                        else:
                                                            echo '<span class="text-muted">' . $lang['Unassigned'] . '</span>';
                                                        endif; 
                                                        ?>
                                                    </td>
                                                    <td class="clients-rpt" style="text-align: left;"> 
                                                        <div class="user-box d-inline-block me-1" data-bs-toggle="tooltip" data-bs-placement="top" title="<?php echo htmlspecialchars($task['client_first_name'] . ' ' . ($clientUser->lastName ?? '')); ?>">
                                                            <?php
                                                            if (!empty($task['client_first_name'])) {
                                                                // Get client user object to get full name
                                                                $clientUser = user::findById($task['project_client_id']);
                                                                echo getUserAvatarHtml($task['project_client_id'], $task['client_first_name'], $clientUser->lastName ?? '', 36, 36, 'img-fluid profile-img', $task['client_first_name']);
                                                            } else {
                                                                echo '<span class="text-muted badge">' . $lang['Internal Task'] . '</span>';
                                                            }
                                                            ?>
                                                        </div>
                                                    </td>
                                                    <td class="text-center">
                                                        <p>
                                                            <?php if(!empty($task['due_date'])): ?>
                                                                <?php 
                                                                $dueDate = strtotime($task['due_date']);
                                                                $today = strtotime('today');
                                                                $isOverdue = $dueDate < $today;
                                                                $isDueToday = $dueDate == $today;
                                                                ?>
                                                                <span class="<?php echo $isOverdue ? 'text-danger' : ($isDueToday ? 'color-review' : '') ?>">
                                                                    <?php echo date('M j, Y', $dueDate); ?>
                                                                </span>
                                                            <?php else: ?>
                                                                <span class="text-muted"><?php echo $lang['No deadline']; ?></span>
                                                            <?php endif; ?>
                                                        </p>
                                                    </td>
                                                    <td class="text-center">
                                                        <?php if(!empty($task['due_date'])): ?>
                                                            <?php 
                                                            $dueDate = strtotime($task['due_date']);
                                                            $today = strtotime('today');
                                                            $isOverdue = $dueDate < $today;
                                                            $isDueToday = $dueDate == $today;
                                                            $isTaskPro = $task['status'] === 'done' && $dueDate > $today;
                                                            
                                                            // Priority-based badge display
                                                            if ($isOverdue) {
                                                                echo '<span class="todo-bg-op badge">' . $lang['Overdue'] . '</span>';
                                                            } elseif ($isDueToday) {
                                                                echo '<span class="badge color-review review-bg-op">' . $lang['Due Today'] . '</span>';
                                                            } elseif ($isTaskPro) {
                                                                echo '<span class="badge color-done review done-bg-op">' . $lang['Task Pro'] . '</span>';
                                                            } else {
                                                                // Check if task is new (created within last 7 days)
                                                                $taskCreated = strtotime($task['created_at']);
                                                                $sevenDaysAgo = strtotime('-7 days');
                                                                if ($taskCreated > $sevenDaysAgo) {
                                                                    echo '<span class="badge">' . $lang['New Task'] . '</span>';
                                                                } else {
                                                                    echo '<span class="text-muted">-</span>';
                                                                }
                                                            }
                                                            ?>
                                                        <?php else: ?>
                                                            <span class="text-muted">-</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td class="extra-height">
                                                        <div class="action-toggle" data-bs-toggle="collapse" data-bs-target="#actionDropdown<?php echo $task['id']; ?>">
                                                            <?php echo $lang['Action']; ?> <?php echo ts_icon('chevron-down'); ?>
                                                        </div>
                                                        <div id="actionDropdown<?php echo $task['id']; ?>" class="toggle-action collapse shadow-dept">
                                                            <ul>
                                                                <li>
                                                                    <a href="#" onclick="openTaskSidebar(<?php echo $task['id']; ?>); return false;">
                                                                        <?php echo ts_icon('eye', 'tasksession-timer-log-menu-ico me-2'); ?>
                                                                        <?php echo $lang['View Task']; ?>
                                                                    </a>
                                                                </li>
                                                                <li>
                                                                    <a href="edit_task?id=<?php echo $task['id']; ?>">
                                                                        <?php echo ts_icon('edit', 'tasksession-timer-log-menu-ico me-2'); ?>
                                                                        <?php echo $lang['Edit Task']; ?>
                                                                    </a>
                                                                </li>
                                                                <li>
                                                                    <a href="../includes/clone-task.php?id=<?php echo $task['id']; ?>&redirect=<?php echo urlencode($_SERVER['REQUEST_URI']); ?>">
                                                                        <?php echo ts_icon('duplicate', 'tasksession-timer-log-menu-ico me-2'); ?>
                                                                        <?php echo $lang['Clone Task']; ?>
                                                                    </a>
                                                                </li>
                                                                <?php if ($task['project_id'] > 0): ?>
                                                                <li>
                                                                    <a href="overview?projectId=<?php echo $task['project_id']; ?>">
                                                                        <?php echo ts_icon('info', 'tasksession-timer-log-menu-ico me-2'); ?>
                                                                        <?php echo $lang['View Project']; ?>
                                                                    </a>
                                                                </li>
                                                                <li>
                                                                    <a href="task?projectId=<?php echo $task['project_id']; ?>">
                                                                        <?php echo ts_icon('document-text', 'tasksession-timer-log-menu-ico me-2'); ?>
                                                                        <?php echo $lang['Project Board']; ?>
                                                                    </a>
                                                                </li>
                                                                <?php endif; ?>
                                                            </ul>
                                                        </div>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <tr>
                                                    <td colspan="7" class="text-center">
                                                        <div class="empty-box">
                                                            <?php echo $lang['No tasks found']; ?>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                                
								
								
								   </div>
								
								
								
								
								
								
								
								
								
								
								
                                <!-- Pagination -->
                                <?php if($total_pages > 0): ?>
                                <div class="row pagination-box">
                                    <div class="col-md-6 resilts-txt">
                                        <?php
                                            $showing_start = $total_records > 0 ? $start_from + 1 : 0;
                                            $showing_end = min($start_from + $limit, $total_records);
                                        ?>
                                        <?php echo $lang['Showing']; ?> <span class="start_val"><?php echo $showing_start; ?></span>
                                        <?php echo $lang['to']; ?> <span class="end_val"><?php echo $showing_end; ?></span>
                                        <?php echo $lang['of']; ?> <?php echo $total_records; ?> <?php echo $lang['entries']; ?>
                                        <?php if($statusFilter): ?> 
                                            <span class="text-muted">(filtered from <?php echo $totalUserTasks; ?> total entries)</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="col-md-6">
                                        <nav aria-label="Page navigation">
                                            <ul class="pagination justify-content-end">
                                                <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                                                    <a class="page-link" href="?user_id=<?php echo $user_id; ?>&tab=tasks&page=<?php echo max(1, $page - 1); ?><?php echo $statusFilter ? '&status='.$statusFilter : ''; ?><?php echo ($dateFilter === 'custom' && $fromDate && $toDate) ? '&date_filter=custom&from_date='.htmlspecialchars($fromDate).'&to_date='.htmlspecialchars($toDate) : ''; ?>" aria-label="Previous">
                                                        <span aria-hidden="true">«</span>
                                                        <span class="sr-only"><?php echo $lang['Previous']; ?></span>
                                                    </a>
                                                </li>
                                                
                                                <?php 
                                                $startPage = max(1, $page - 2);
                                                $endPage = min($total_pages, $page + 2);
                                                
                                                if($startPage > 1) {
                                                    $dateParams = ($dateFilter === 'custom' && $fromDate && $toDate) ? '&date_filter=custom&from_date='.htmlspecialchars($fromDate).'&to_date='.htmlspecialchars($toDate) : '';
                                                    echo '<li class="page-item"><a class="page-link" href="?user_id=' . $user_id . '&tab=tasks&page=1'.($statusFilter ? '&status='.$statusFilter : '').$dateParams.'">1</a></li>';
                                                    if($startPage > 2) {
                                                        echo '<li class="page-item disabled"><a class="page-link" href="#">...</a></li>';
                                                    }
                                                }
                                                
                                                for($i = $startPage; $i <= $endPage; $i++): 
                                                ?>
                                                    <li class="page-item <?php echo ($page == $i) ? 'active' : ''; ?>">
                                                        <a class="page-link" href="?user_id=<?php echo $user_id; ?>&tab=tasks&page=<?php echo $i; ?><?php echo $statusFilter ? '&status='.$statusFilter : ''; ?><?php echo ($dateFilter === 'custom' && $fromDate && $toDate) ? '&date_filter=custom&from_date='.htmlspecialchars($fromDate).'&to_date='.htmlspecialchars($toDate) : ''; ?>">
                                                            <?php echo $i; ?>
                                                        </a>
                                                    </li>
                                                <?php 
                                                endfor;
                                                
                                                if($endPage < $total_pages) {
                                                    if($endPage < $total_pages - 1) {
                                                        echo '<li class="page-item disabled"><a class="page-link" href="#">...</a></li>';
                                                    }
                                                    $dateParams = ($dateFilter === 'custom' && $fromDate && $toDate) ? '&date_filter=custom&from_date='.htmlspecialchars($fromDate).'&to_date='.htmlspecialchars($toDate) : '';
                                                    echo '<li class="page-item"><a class="page-link" href="?user_id=' . $user_id . '&tab=tasks&page=' . $total_pages . ($statusFilter ? '&status='.$statusFilter : '') . $dateParams . '">' . $total_pages . '</a></li>';
                                                }
                                                ?>
                                                
                                                <li class="page-item <?php echo ($page >= $total_pages) ? 'disabled' : ''; ?>">
                                                    <a class="page-link" href="?user_id=<?php echo $user_id; ?>&tab=tasks&page=<?php echo min($total_pages, $page + 1); ?><?php echo $statusFilter ? '&status='.$statusFilter : ''; ?><?php echo ($dateFilter === 'custom' && $fromDate && $toDate) ? '&date_filter=custom&from_date='.htmlspecialchars($fromDate).'&to_date='.htmlspecialchars($toDate) : ''; ?>" aria-label="Next">
                                                        <span aria-hidden="true">»</span>
                                                        <span class="sr-only"><?php echo $lang['Next']; ?></span>
                                                    </a>
                                                </li>
                                            </ul>
                                        </nav>
                                    </div>
                                </div>
                                <?php endif; ?>
                            <?php endif; ?>
							</div>
						</div>
					</div>
				</div>
			<!-- row -->
		</div>
		
		
		
		
		
		
		
								</div>
								</div>
							</div>
</div>
</div>
</div>


			
			
	<div class="clearfix"></div>
	
</div>
</div>
  </div>
  <script src="../assets/js/Chart.js"></script>
<script src="../assets/js/user-analytics.js"></script>
<?php  include("../templates/payment-footer.php"); 

if(isset($_POST["edit-mile"])){ ?>
<script type="text/javascript">
// Use Bootstrap 5 way to show modal
var myModal = document.getElementById('edit-milestone');
if (myModal) {
  var modal = new bootstrap.Modal(myModal);
  modal.show();
}
</script>
<?php } 
if(isset($_POST["edit-mile1"])){ ?>
<script type="text/javascript">
// Use Bootstrap 5 way to show modal
var myModal = document.getElementById('edit-milestone1');
if (myModal) {
  var modal = new bootstrap.Modal(myModal);
  modal.show();
}
</script>
<?php } ?>
<?php
$message = "";
	if(isset($_POST['edit-milestone-1']))
	{
		$mile = milestone::findById($_POST['editId']); 
		
		$flag=0;
		if($flag==0)
		{
			
			$mile->id        		=  $_POST['editId'];
			$mile->title	=$_POST['title1'];
				$mile->budget		=$_POST['amount1'];
				$mile->deadline		=$_POST['deadline1'];
				$mile->releaseDate		= $_POST['releaseDate'];
				$mile->status	= (int)$_POST['status1'];
				
				$saveMile=$mile->save();
	
				if($saveMile)
				{
					// Send notification if invoice is marked as paid
					if (isset($_POST['status1']) && (int)$_POST['status1'] === 1) {
						require_once('../includes/notification_helper.php');
						NotificationHelper::invoicePaid($mile->id, $mile->title, $session->userId, $mile->p_id);
					}
					header('location:profile?user_id='.$user_id.'&tab=invoice&message=updated'); 
				}
				else
				{
header('location:profile?user_id='.$user_id.'&tab=invoice&message=notupdated'); 
				}
			}
		}

?>

<script>
function showCustomDateRange() {
    document.getElementById('customTaskDateRangeForm').style.display = 'block';
}

function hideCustomTaskDateRange() {
    document.getElementById('customTaskDateRangeForm').style.display = 'none';
}

function showCustomActivityDateRange() {
    document.getElementById('customDateRangeForm').style.display = 'block';
}

function hideCustomActivityDateRange() {
    document.getElementById('customDateRangeForm').style.display = 'none';
}

// User-specific financial data for profile page
<?php
// Calculate user-specific monthly financial data
$present_year = date('Y');
$user_monthly_earnings = [];
$user_monthly_unpaid = [];
$user_month_labels = [];

for ($m = 1; $m <= 12; $m++) {
    $user_month_labels[] = $present_year . ' ' . date('M', mktime(0, 0, 0, $m, 1));
    
    // Build currency filter condition for default currency
    $currency_condition = '';
    if ($defaultCurrency && $defaultCurrency !== 'all') {
        $currency_parts = explode(',', $defaultCurrency);
        $currency_code = trim($currency_parts[0]);
        $currency_condition = " AND (m.currency LIKE '{$currency_code},%' OR m.currency = '{$currency_code}' OR m.currency LIKE '%,{$currency_code}')";
    }
    
    // Get user's paid milestones for this month (filtered by default currency)
    $user_paid_milestones = milestone::findBySql(
        "SELECT budget FROM milestones m 
         INNER JOIN projects p ON m.p_id = p.p_id 
         WHERE m.status = 1 
         AND YEAR(m.releaseDate) = " . (int)$present_year . " 
         AND MONTH(m.releaseDate) = " . (int)$m . "
         AND (p.c_id = $user_id OR p.main_client_id = $user_id OR FIND_IN_SET($user_id, p.c_ids) > 0)
         {$currency_condition}"
    );
    
    // Get user's unpaid milestones for this month (filtered by default currency)
    $user_unpaid_milestones = milestone::findBySql(
        "SELECT budget FROM milestones m 
         INNER JOIN projects p ON m.p_id = p.p_id 
         WHERE m.status = 0 
         AND YEAR(m.deadline) = " . (int)$present_year . " 
         AND MONTH(m.deadline) = " . (int)$m . "
         AND (p.c_id = $user_id OR p.main_client_id = $user_id OR FIND_IN_SET($user_id, p.c_ids) > 0)
         {$currency_condition}"
    );
    
    $user_monthly_earnings[] = array_sum(array_column($user_paid_milestones, 'budget'));
    $user_monthly_unpaid[] = array_sum(array_column($user_unpaid_milestones, 'budget'));
}

// Calculate user totals
$user_total_paid = array_sum($user_monthly_earnings);
$user_total_unpaid = array_sum($user_monthly_unpaid);
?>

window.monthLabels = <?php echo json_encode($user_month_labels); ?>;
window.monthlyEarnings = <?php echo json_encode($user_monthly_earnings); ?>;
window.monthlyUnpaid = <?php echo json_encode($user_monthly_unpaid); ?>;
window.userTotalPaid = <?php echo $user_total_paid; ?>;
window.userTotalUnpaid = <?php echo $user_total_unpaid; ?>;
window.defaultCurrency = <?php echo json_encode($defaultCurrency); ?>;
window.userPreferredCurrency = <?php echo json_encode($userPreferredCurrency); ?>;
</script>
