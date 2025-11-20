<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

$auth = new Auth();
$auth->requireRole('administrator');

$db = Database::getInstance();
$conn = $db->getConnection();

// Handle filters
$filter_user = isset($_GET['user']) ? sanitize($_GET['user']) : '';
$filter_action = isset($_GET['action']) ? sanitize($_GET['action']) : '';
$filter_date_from = isset($_GET['date_from']) ? sanitize($_GET['date_from']) : '';
$filter_date_to = isset($_GET['date_to']) ? sanitize($_GET['date_to']) : '';
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 50;
$offset = ($page - 1) * $per_page;

// Build query
$where_conditions = [];
$params = [];
$types = '';

if (!empty($filter_user)) {
    $where_conditions[] = "(username LIKE ? OR user_id = ?)";
    $params[] = "%{$filter_user}%";
    $params[] = is_numeric($filter_user) ? intval($filter_user) : 0;
    $types .= 'si';
}

if (!empty($filter_action)) {
    $where_conditions[] = "action = ?";
    $params[] = $filter_action;
    $types .= 's';
}

if (!empty($filter_date_from)) {
    $where_conditions[] = "DATE(created_at) >= ?";
    $params[] = $filter_date_from;
    $types .= 's';
}

if (!empty($filter_date_to)) {
    $where_conditions[] = "DATE(created_at) <= ?";
    $params[] = $filter_date_to;
    $types .= 's';
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get total count (use same params as filter, without limit/offset)
$count_sql = "SELECT COUNT(*) as total FROM user_logs $where_clause";
$count_stmt = $conn->prepare($count_sql);
if (!empty($params)) {
    $count_stmt->bind_param($types, ...array_values($params));
}
$count_stmt->execute();
$total_logs = $count_stmt->get_result()->fetch_assoc()['total'];
$count_stmt->close();

$total_pages = ceil($total_logs / $per_page);

// Get logs
$sql = "SELECT * FROM user_logs $where_clause ORDER BY created_at DESC LIMIT ? OFFSET ?";
$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $params[] = $per_page;
    $params[] = $offset;
    $types .= 'ii';
    $stmt->bind_param($types, ...array_values($params));
} else {
    $stmt->bind_param('ii', $per_page, $offset);
}
$stmt->execute();
$logs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get unique actions for filter dropdown
$actions_result = $conn->query("SELECT DISTINCT action FROM user_logs ORDER BY action");
$actions = [];
while ($row = $actions_result->fetch_assoc()) {
    $actions[] = $row['action'];
}

$page_title = 'User Logs';
include __DIR__ . '/../includes/header.php';
?>

<div class="container">
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">User Activity Logs</h2>
        </div>
        
        <!-- Filters -->
        <form method="GET" action="" style="margin-bottom: 20px; padding: 20px; background: #f8fafc; border-radius: 8px;">
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                <div class="form-group" style="margin-bottom: 0;">
                    <label>User (Username or ID)</label>
                    <input type="text" name="user" class="form-control" value="<?php echo htmlspecialchars($filter_user); ?>" placeholder="Search user...">
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label>Action</label>
                    <select name="action" class="form-control">
                        <option value="">All Actions</option>
                        <?php foreach ($actions as $action): ?>
                            <option value="<?php echo htmlspecialchars($action); ?>" <?php echo $filter_action === $action ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($action); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label>Date From</label>
                    <input type="date" name="date_from" class="form-control" value="<?php echo htmlspecialchars($filter_date_from); ?>">
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label>Date To</label>
                    <input type="date" name="date_to" class="form-control" value="<?php echo htmlspecialchars($filter_date_to); ?>">
                </div>
            </div>
            <div style="margin-top: 15px; display: flex; gap: 10px;">
                <button type="submit" class="btn btn-primary">Filter</button>
                <a href="logs.php" class="btn btn-secondary">Clear</a>
            </div>
        </form>
        
        <!-- Summary Stats -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px; margin-bottom: 20px;">
            <div style="background: #f8fafc; padding: 15px; border-radius: 8px; text-align: center;">
                <div style="font-size: 24px; font-weight: 700; color: var(--primary-color);"><?php echo number_format($total_logs); ?></div>
                <div style="font-size: 12px; color: var(--text-secondary); margin-top: 5px;">Total Logs</div>
            </div>
            <div style="background: #f8fafc; padding: 15px; border-radius: 8px; text-align: center;">
                <div style="font-size: 24px; font-weight: 700; color: var(--success-color);">
                    <?php 
                    $today_count = $conn->query("SELECT COUNT(*) as count FROM user_logs WHERE DATE(created_at) = CURDATE()")->fetch_assoc()['count'];
                    echo number_format($today_count);
                    ?>
                </div>
                <div style="font-size: 12px; color: var(--text-secondary); margin-top: 5px;">Today's Logs</div>
            </div>
            <div style="background: #f8fafc; padding: 15px; border-radius: 8px; text-align: center;">
                <div style="font-size: 24px; font-weight: 700; color: var(--info-color);">
                    <?php 
                    $unique_users = $conn->query("SELECT COUNT(DISTINCT user_id) as count FROM user_logs WHERE user_id IS NOT NULL")->fetch_assoc()['count'];
                    echo number_format($unique_users);
                    ?>
                </div>
                <div style="font-size: 12px; color: var(--text-secondary); margin-top: 5px;">Unique Users</div>
            </div>
        </div>
        
        <!-- Logs Table -->
        <?php if (empty($logs)): ?>
            <div class="empty-state">
                <p>No logs found.</p>
            </div>
        <?php else: ?>
            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Date & Time</th>
                            <th>User</th>
                            <th>Action</th>
                            <th>Description</th>
                            <th>IP Address</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($logs as $log): ?>
                            <tr>
                                <td><?php echo date('M d, Y h:i A', strtotime($log['created_at'])); ?></td>
                                <td>
                                    <?php if ($log['user_id']): ?>
                                        <a href="users.php?edit=<?php echo $log['user_id']; ?>" style="color: var(--primary-color); text-decoration: none;">
                                            <?php echo htmlspecialchars($log['username'] ?? 'User #' . $log['user_id']); ?>
                                        </a>
                                    <?php else: ?>
                                        <span style="color: var(--text-secondary);"><?php echo htmlspecialchars($log['username'] ?? 'System'); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php
                                    $badge_color = 'var(--primary-color)';
                                    switch($log['action']) {
                                        case 'LOGIN':
                                            $badge_color = 'var(--success-color)';
                                            break;
                                        case 'LOGOUT':
                                            $badge_color = 'var(--secondary-color)';
                                            break;
                                        case 'CREATE':
                                        case 'UPDATE':
                                        case 'DELETE':
                                            $badge_color = 'var(--info-color)';
                                            break;
                                        case 'ERROR':
                                        case 'FAILED':
                                            $badge_color = 'var(--danger-color)';
                                            break;
                                    }
                                    ?>
                                    <span class="badge" style="background: <?php echo $badge_color; ?>; color: white; padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: 600;">
                                        <?php echo htmlspecialchars($log['action']); ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($log['description'] ?? '-'); ?></td>
                                <td style="font-family: monospace; font-size: 12px;"><?php echo htmlspecialchars($log['ip_address'] ?? '-'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <div style="display: flex; justify-content: center; align-items: center; gap: 10px; margin-top: 20px;">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?php echo $page - 1; ?><?php echo !empty($filter_user) ? '&user=' . urlencode($filter_user) : ''; ?><?php echo !empty($filter_action) ? '&action=' . urlencode($filter_action) : ''; ?><?php echo !empty($filter_date_from) ? '&date_from=' . urlencode($filter_date_from) : ''; ?><?php echo !empty($filter_date_to) ? '&date_to=' . urlencode($filter_date_to) : ''; ?>" class="btn btn-secondary">Previous</a>
                    <?php endif; ?>
                    
                    <span style="padding: 8px 16px; background: #f8fafc; border-radius: 6px;">
                        Page <?php echo $page; ?> of <?php echo $total_pages; ?>
                    </span>
                    
                    <?php if ($page < $total_pages): ?>
                        <a href="?page=<?php echo $page + 1; ?><?php echo !empty($filter_user) ? '&user=' . urlencode($filter_user) : ''; ?><?php echo !empty($filter_action) ? '&action=' . urlencode($filter_action) : ''; ?><?php echo !empty($filter_date_from) ? '&date_from=' . urlencode($filter_date_from) : ''; ?><?php echo !empty($filter_date_to) ? '&date_to=' . urlencode($filter_date_to) : ''; ?>" class="btn btn-secondary">Next</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>

