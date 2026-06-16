<?php
/**
 * visitors/checkout.php – Check Out a Visitor
 */
define('BASE_PATH', '../');
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireAuth();
$db   = getDB();
$user = currentUser();

// Handle checkout POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['log_id'])) {
    verifyCsrf();
    $logId = (int)$_POST['log_id'];

    // Fetch the log entry
    $log = $db->prepare("SELECT * FROM visit_logs WHERE id = ? AND status = 'Checked In'");
    $log->execute([$logId]);
    $log = $log->fetch();

    if ($log) {
        $checkInTime  = new DateTime($log['check_in_time']);
        $checkOutTime = new DateTime();
        $duration     = (int)round(($checkOutTime->getTimestamp() - $checkInTime->getTimestamp()) / 60);

        $db->prepare("
            UPDATE visit_logs
            SET status='Checked Out', check_out_time=NOW(),
                checked_out_by=?, duration_minutes=?
            WHERE id=?
        ")->execute([$user['id'], $duration, $logId]);

        // Get visitor name for flash
        $vname = $db->prepare("SELECT v.full_name FROM visitors v JOIN visit_logs vl ON vl.visitor_id=v.id WHERE vl.id=?");
        $vname->execute([$logId]);
        $vname = $vname->fetchColumn();

        setFlash('success', "✅ {$vname} has been checked out. Duration: " . fmtDuration($duration));
    }
    redirect('../visitors/checkout.php');
}

// Fetch all currently checked-in visitors
$checkedIn = $db->query("
    SELECT vl.id, vl.check_in_time, vl.relationship, vl.purpose,
           v.full_name AS visitor_name, v.contact_phone,
           r.full_name AS resident_name, r.room_number,
           u.full_name AS staff_name,
           TIMESTAMPDIFF(MINUTE, vl.check_in_time, NOW()) AS elapsed
    FROM visit_logs vl
    JOIN visitors v  ON v.id = vl.visitor_id
    JOIN residents r ON r.id = vl.resident_id
    JOIN users u     ON u.id = vl.checked_in_by
    WHERE vl.status = 'Checked In'
    ORDER BY vl.check_in_time ASC
")->fetchAll();

$pageTitle   = 'Check Out Visitor';
$activeNav   = 'checkout';
$breadcrumbs = [['label' => 'Visitors'], ['label' => 'Check Out']];
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-actions">
    <div class="page-actions-left">
        <h2 style="font-size:16px;font-weight:700;color:var(--text-secondary)">
            <?= plural(count($checkedIn), 'visitor') ?> currently inside
        </h2>
    </div>
    <div class="page-actions-right">
        <div class="search-box">
            <span class="search-icon">🔍</span>
            <input type="text" id="search-checkout" placeholder="Search visitor or resident…">
        </div>
    </div>
</div>

<?php if ($checkedIn): ?>

<div class="card">
    <div class="table-wrapper">
        <table id="checkout-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Visitor</th>
                    <th>Visiting Resident</th>
                    <th>Purpose / Relationship</th>
                    <th>Check-In Time</th>
                    <th>Time Inside</th>
                    <th>Checked In By</th>
                    <th style="text-align:center">Action</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($checkedIn as $i => $ci): ?>
            <tr>
                <td style="color:var(--text-muted);font-size:13px"><?= $i + 1 ?></td>
                <td>
                    <div class="td-name"><?= e($ci['visitor_name']) ?></div>
                    <?php if ($ci['contact_phone']): ?>
                    <div class="td-sub">📞 <?= e($ci['contact_phone']) ?></div>
                    <?php endif; ?>
                </td>
                <td>
                    <div class="td-name"><?= e($ci['resident_name']) ?></div>
                    <div class="td-sub">Room <?= e($ci['room_number']) ?></div>
                </td>
                <td>
                    <div><?= e($ci['purpose'] ?: '—') ?></div>
                    <?php if ($ci['relationship']): ?>
                    <div class="td-sub"><?= e($ci['relationship']) ?></div>
                    <?php endif; ?>
                </td>
                <td style="white-space:nowrap;font-size:13px">
                    <?= fmtDateTime($ci['check_in_time']) ?>
                </td>
                <td>
                    <?php
                    $el = $ci['elapsed'];
                    $badge = $el > 120 ? 'badge-warning' : 'badge-success';
                    echo '<span class="badge ' . $badge . '">' . fmtDuration($el) . '</span>';
                    ?>
                </td>
                <td style="font-size:13px;color:var(--text-muted)">
                    <?= e($ci['staff_name']) ?>
                </td>
                <td style="text-align:center">
                    <form method="POST" style="display:inline">
                        <?= csrfField() ?>
                        <input type="hidden" name="log_id" value="<?= $ci['id'] ?>">
                        <button type="submit" class="btn btn-sm btn-danger"
                                data-confirm="Check out <?= e($ci['visitor_name']) ?>?">
                            🚪 Check Out
                        </button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php else: ?>
<div class="card">
    <div class="empty-state">
        <div class="empty-icon">🏡</div>
        <h3>All Clear!</h3>
        <p>There are no visitors currently inside the premises.</p>
        <a href="../visitors/checkin.php" class="btn btn-primary mt-16">✅ Check In a Visitor</a>
    </div>
</div>
<?php endif; ?>

<script>
initTableSearch('search-checkout', 'checkout-table');
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
