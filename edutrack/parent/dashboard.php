<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

$auth = new Auth();
$auth->requireRole('parent');

$db = Database::getInstance();
$conn = $db->getConnection();
$parent_user_id = $auth->getUserId();

// Get linked children
$children = getParentStudents($parent_user_id);

// Get performance summaries for each child
$children_summaries = [];
foreach ($children as $child) {
    $children_summaries[$child['id']] = getStudentPerformanceSummary($child['id']);
}

$page_title = 'Parent Dashboard';
include __DIR__ . '/../includes/header.php';
?>

<div class="container">
    <div class="nav" style="display: none;">
        <ul class="nav-list">
            <li class="nav-item"><a href="dashboard.php" class="active">Dashboard</a></li>
            <li class="nav-item"><a href="reports.php">View Reports</a></li>
            <li class="nav-item"><a href="add_child.php">Add Child</a></li>
        </ul>
    </div>
    
    <?php if (empty($children)): ?>
        <div class="card">
            <div class="empty-state">
                <p>No children linked to your account.</p>
                <p>Please contact the administrator to link your children's accounts.</p>
            </div>
        </div>
    <?php else: ?>
        <!-- Performance Overview Cards -->
        <div class="grid grid-<?php echo min(count($children), 3); ?>">
            <?php foreach ($children as $child): 
                $summary = $children_summaries[$child['id']];
            ?>
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title"><?php echo htmlspecialchars($child['first_name'] . ' ' . $child['last_name']); ?></h2>
                    </div>
                    <div class="stats-grid" style="grid-template-columns: 1fr 1fr;">
                        <div class="stat-card">
                            <div class="stat-value" style="font-size: 2em; color: <?php echo $summary['overall_average'] >= 75 ? '#10b981' : ($summary['overall_average'] >= 60 ? '#f59e0b' : '#ef4444'); ?>">
                                <?php echo number_format($summary['overall_average'], 1); ?>
                            </div>
                            <div class="stat-label">Overall Average</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-value" style="font-size: 2em; color: <?php echo $summary['attendance_rate'] >= 90 ? '#10b981' : ($summary['attendance_rate'] >= 75 ? '#f59e0b' : '#ef4444'); ?>">
                                <?php echo number_format($summary['attendance_rate'], 1); ?>%
                            </div>
                            <div class="stat-label">Attendance Rate</div>
                        </div>
                    </div>
                    <div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #e5e7eb;">
                        <p><strong>LRN:</strong> <?php echo htmlspecialchars($child['student_number']); ?></p>
                        <p><strong>Section:</strong> <?php echo htmlspecialchars($child['section_name'] ?? 'Not assigned'); ?></p>
                        <p><strong>Grade Level:</strong> <?php echo htmlspecialchars($child['grade_level'] ?? 'N/A'); ?></p>
                        <?php if ($summary['last_grade_update']): ?>
                            <p style="font-size: 0.9em; color: #6b7280;">
                                <strong>Last Grade Update:</strong> <?php echo formatDate($summary['last_grade_update'], 'M d, Y h:i A'); ?>
                            </p>
                        <?php endif; ?>
                        <?php if ($summary['last_attendance_update']): ?>
                            <p style="font-size: 0.9em; color: #6b7280;">
                                <strong>Last Attendance Update:</strong> <?php echo formatDate($summary['last_attendance_update'], 'M d, Y h:i A'); ?>
                            </p>
                        <?php endif; ?>
                    </div>
                    <div style="margin-top: 15px;">
                        <a href="reports.php?student_id=<?php echo $child['id']; ?>" class="btn btn-primary btn-block">View Full Report</a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        
        <!-- Recent Updates Section -->
        <?php if (count($children) > 0): ?>
            <div class="card" style="margin-top: 20px;">
                <div class="card-header">
                    <h2 class="card-title">Recent Updates from Teachers</h2>
                </div>
                <?php 
                $all_recent_updates = [];
                foreach ($children as $child) {
                    $updates = getRecentStudentUpdates($child['id'], 5);
                    foreach ($updates as $update) {
                        $update['child_name'] = $child['first_name'] . ' ' . $child['last_name'];
                        $update['child_id'] = $child['id'];
                        $all_recent_updates[] = $update;
                    }
                }
                // Sort by date
                usort($all_recent_updates, function($a, $b) {
                    $dateA = strtotime($a['updated_at']);
                    $dateB = strtotime($b['updated_at']);
                    return $dateB - $dateA;
                });
                $all_recent_updates = array_slice($all_recent_updates, 0, 10);
                ?>
                <?php if (empty($all_recent_updates)): ?>
                    <div class="empty-state">
                        <p>No recent updates available.</p>
                    </div>
                <?php else: ?>
                    <div class="table-container">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Child</th>
                                    <th>Update Type</th>
                                    <th>Details</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($all_recent_updates as $update): ?>
                                    <tr>
                                        <td><?php echo formatDate($update['updated_at'], 'M d, Y h:i A'); ?></td>
                                        <td><?php echo htmlspecialchars($update['child_name']); ?></td>
                                        <td>
                                            <span class="badge badge-<?php 
                                                echo $update['update_type'] == 'grade' ? 'primary' : 
                                                    ($update['update_type'] == 'attendance' ? 'info' : 'success'); 
                                            ?>">
                                                <?php 
                                                    echo ucfirst($update['update_type']);
                                                ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($update['update_type'] == 'grade'): ?>
                                                <strong><?php echo htmlspecialchars($update['subject_name']); ?></strong> - 
                                                <?php echo getGradingPeriodName($update['grading_period']); ?>: 
                                                <strong><?php echo number_format($update['grade_value'], 2); ?></strong>
                                            <?php elseif ($update['update_type'] == 'attendance'): ?>
                                                <strong><?php echo htmlspecialchars($update['status']); ?></strong>
                                                <?php if (!empty($update['remarks'])): ?>
                                                    - <?php echo htmlspecialchars($update['remarks']); ?>
                                                <?php endif; ?>
                                                (Date: <?php echo formatDate($update['attendance_date'] ?? $update['updated_at'], 'M d, Y'); ?>)
                                            <?php elseif ($update['update_type'] == 'remark'): ?>
                                                <strong><?php echo getGradingPeriodName($update['grading_period']); ?></strong> - 
                                                <?php echo htmlspecialchars(($update['first_name'] ?? '') . ' ' . ($update['last_name'] ?? '')); ?>: 
                                                <?php echo htmlspecialchars(substr($update['remark_text'], 0, 100)); ?>
                                                <?php if (strlen($update['remark_text']) > 100): ?>...<?php endif; ?>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        
        <!-- Quick Actions -->
        <div class="card" style="margin-top: 20px;">
            <div class="card-header">
                <h2 class="card-title">Quick Actions</h2>
            </div>
            <div class="grid grid-<?php echo min(count($children), 3); ?>">
                <?php foreach ($children as $child): 
                    $summary = $children_summaries[$child['id']];
                ?>
                    <div>
                        <h3><?php echo htmlspecialchars($child['first_name']); ?></h3>
                        <div style="margin-bottom: 10px;">
                            <p style="font-size: 0.9em;">
                                <strong>Recent Updates (7 days):</strong><br>
                                <?php echo $summary['recent_grades']; ?> grade(s), 
                                <?php echo $summary['recent_attendance']; ?> attendance record(s), 
                                <?php echo $summary['recent_remarks']; ?> remark(s)
                            </p>
                        </div>
                        <a href="reports.php?student_id=<?php echo $child['id']; ?>" class="btn btn-primary btn-block">View Full Report</a>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>

