<?php
require_once("../includes/lib-initialize.php");

if(!($session->isLoggedIn())){
    die("Unauthorized");
}

// Only allow admin
if($_SESSION['accountStatus'] != 1){
    die("Unauthorized");
}

if (!isset($_GET['table'])) {
    die("Table name required");
}

$tableName = $database->escapeValue($_GET['table']);

// Get table structure
$sql = "SHOW CREATE TABLE `{$tableName}`";
$result = $database->query($sql);

if (!$result) {
    echo '<div class="alert alert-danger">Table not found or error occurred.</div>';
    exit;
}

$row = $database->fetchArray($result);
$createTable = $row[1];

// Get table columns
$sql = "SHOW COLUMNS FROM `{$tableName}`";
$result = $database->query($sql);
$columns = [];
if ($result) {
    while ($col = $database->fetchArray($result)) {
        $columns[] = $col;
    }
}

// Get row count
$sql = "SELECT COUNT(*) as count FROM `{$tableName}`";
$result = $database->query($sql);
$rowCount = 0;
if ($result) {
    $row = $database->fetchArray($result);
    $rowCount = $row['count'];
}

// Get table size
$sql = "SELECT 
    ROUND(((data_length + index_length) / 1024 / 1024), 2) AS 'size_mb'
    FROM information_schema.TABLES 
    WHERE table_schema = DATABASE()
    AND table_name = '{$tableName}'";
$result = $database->query($sql);
$tableSize = 'N/A';
if ($result) {
    $row = $database->fetchArray($result);
    if ($row) {
        $tableSize = $row['size_mb'] . ' MB';
    }
}

?>
<div class="table-info">
    <div class="row mb-3">
        <div class="col-md-4">
            <div class="card">
                <div class="card-body text-center">
                    <h5 class="card-title">Rows</h5>
                    <h3 class="text-primary"><?php echo number_format($rowCount); ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card">
                <div class="card-body text-center">
                    <h5 class="card-title">Size</h5>
                    <h3 class="text-info"><?php echo $tableSize; ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card">
                <div class="card-body text-center">
                    <h5 class="card-title">Columns</h5>
                    <h3 class="text-success"><?php echo count($columns); ?></h3>
                </div>
            </div>
        </div>
    </div>
    
    <h5>Columns</h5>
    <div class="table-responsive">
        <table class="table table-sm table-bordered">
            <thead>
                <tr>
                    <th>Field</th>
                    <th>Type</th>
                    <th>Null</th>
                    <th>Key</th>
                    <th>Default</th>
                    <th>Extra</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($columns as $col): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($col['Field']); ?></strong></td>
                        <td><?php echo htmlspecialchars($col['Type']); ?></td>
                        <td><?php echo $col['Null'] == 'YES' ? '<span class="badge bg-warning">YES</span>' : '<span class="badge bg-success">NO</span>'; ?></td>
                        <td><?php echo $col['Key'] ? '<span class="badge bg-info">' . htmlspecialchars($col['Key']) . '</span>' : '-'; ?></td>
                        <td><?php echo $col['Default'] !== null ? htmlspecialchars($col['Default']) : '<span class="text-muted">NULL</span>'; ?></td>
                        <td><?php echo $col['Extra'] ? htmlspecialchars($col['Extra']) : '-'; ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    
    <h5 class="mt-4">CREATE TABLE Statement</h5>
    <div class="bg-light p-3 rounded">
        <pre class="mb-0" style="max-height: 300px; overflow-y: auto;"><code><?php echo htmlspecialchars($createTable); ?></code></pre>
    </div>
</div>

