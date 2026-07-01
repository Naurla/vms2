<?php
define('BASE_PATH', '../');
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireAuth();
requireAdmin();

$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['id'])) {
    $id = (int)$_POST['id'];
    $action = $_POST['action'];
    if (in_array($action, ['Approve', 'Reject'])) {
        $newStatus = $action === 'Approve' ? 'Approved' : 'Rejected';
        $db->prepare("UPDATE visitor_restrictions SET status = ? WHERE id = ?")->execute([$newStatus, $id]);
        setFlash('success', "Restriction request $newStatus.");
        redirect('list.php');
    }
}

$stmt = $db->query("
    SELECT vr.*, r.full_name as resident_name, r.room_number 
    FROM visitor_restrictions vr 
    JOIN residents r ON vr.resident_id = r.id 
    ORDER BY vr.created_at DESC
");
$restrictions = $stmt->fetchAll();

$pageTitle = 'Visitor Restrictions';
$activeNav = 'restrictions';
$breadcrumbs = [['label' => 'Restrictions', 'url' => 'list.php']];
require_once __DIR__ . '/../includes/header.php';
?>

<div class="card">
    <div class="card-header">
        <div class="card-title">🛡️ Visitor Restriction Requests</div>
    </div>
    <?php if ($restrictions): ?>
    <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th>Date Requested</th>
                    <th>Resident</th>
                    <th>Requested By</th>
                    <th>Restriction Date</th>
                    <th>Allowed Visitors</th>
                    <th>Reason</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($restrictions as $req): ?>
            <tr>
                <td style="font-size:13px;"><?= fmtDate($req['created_at']) ?></td>
                <td>
                    <strong><?= e($req['resident_name']) ?></strong>
                    <div class="td-sub">Room <?= e($req['room_number']) ?></div>
                </td>
                <td>
                    <?= e($req['requested_by_name']) ?>
                    <div class="td-sub"><?= e($req['requested_by_relation']) ?></div>
                    <?php if ($req['contact_info']): ?>
                    <div class="td-sub">📞 <?= e($req['contact_info']) ?></div>
                    <?php endif; ?>
                </td>
                <td style="font-weight:700;color:var(--danger);"><?= fmtDate($req['restriction_date']) ?></td>
                <td><?= e($req['allowed_visitors'] ?: 'None') ?></td>
                <td><small><?= e($req['reason']) ?></small></td>
                <td>
                    <?php
                    $badge = 'secondary';
                    if ($req['status'] === 'Approved') $badge = 'success';
                    if ($req['status'] === 'Rejected') $badge = 'danger';
                    ?>
                    <span class="badge badge-<?= $badge ?>"><?= $req['status'] ?></span>
                </td>
                <td>
                    <?php if ($req['status'] === 'Pending'): ?>
                    <form method="POST" style="display:flex;gap:4px;">
                        <input type="hidden" name="id" value="<?= $req['id'] ?>">
                        <button type="submit" name="action" value="Approve" class="btn btn-sm btn-success">✅ Approve</button>
                        <button type="submit" name="action" value="Reject" class="btn btn-sm btn-danger">❌ Reject</button>
                    </form>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
    <div class="empty-state">
        <div class="empty-icon">🛡️</div>
        <h3>No restrictions requested</h3>
    </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
