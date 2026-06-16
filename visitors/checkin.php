<?php
/**
 * visitors/checkin.php – Check In a Visitor
 */
define('BASE_PATH', '../');
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireAuth();
$db   = getDB();
$user = currentUser();

$errors  = [];
$success = false;

// Pre-fill visitor if redirected from register
$prefillVisitorId = (int)get('prefill');
$prefillVisitor   = null;
if ($prefillVisitorId) {
    $prefillVisitor = $db->prepare("SELECT * FROM visitors WHERE id = ?");
    $prefillVisitor->execute([$prefillVisitorId]);
    $prefillVisitor = $prefillVisitor->fetch();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $visitorId     = (int)post('visitor_id');
    $residentId    = (int)post('resident_id');
    $relationship  = post('relationship');
    $purpose       = post('purpose');
    $numCompanions = max(0, (int)post('num_companions', '0'));
    $notes         = post('notes');

    if (!$visitorId)  $errors[] = 'Please select or search for a visitor.';
    if (!$residentId) $errors[] = 'Please select a resident to visit.';
    if (!$purpose)    $errors[] = 'Purpose of visit is required.';

    // Check visitor isn't already checked in
    if (!$errors) {
        $alreadyIn = $db->prepare("SELECT id FROM visit_logs WHERE visitor_id = ? AND status = 'Checked In'");
        $alreadyIn->execute([$visitorId]);
        if ($alreadyIn->fetchColumn()) {
            $errors[] = 'This visitor is already checked in. Please check them out first.';
        }
    }

    if (!$errors) {
        $stmt = $db->prepare("
            INSERT INTO visit_logs
                (visitor_id, resident_id, relationship, purpose, num_companions, check_in_time, checked_in_by)
            VALUES (?, ?, ?, ?, ?, NOW(), ?)
        ");
        $stmt->execute([$visitorId, $residentId, $relationship, $purpose, $numCompanions, $user['id']]);

        // Get names for confirmation
        $vis = $db->prepare("SELECT full_name FROM visitors WHERE id = ?");
        $vis->execute([$visitorId]);
        $visName = $vis->fetchColumn();

        $res = $db->prepare("SELECT full_name FROM residents WHERE id = ?");
        $res->execute([$residentId]);
        $resName = $res->fetchColumn();

        setFlash('success', "✅ {$visName} has been checked in to visit {$resName}.");
        redirect('../dashboard.php');
    }
}

// Get all active residents for dropdown
$residents = $db->query("SELECT id, full_name, room_number FROM residents WHERE status='Active' ORDER BY full_name")->fetchAll();

$pageTitle   = 'Check In Visitor';
$activeNav   = 'checkin';
$breadcrumbs = [['label' => 'Visitors'], ['label' => 'Check In']];
require_once __DIR__ . '/../includes/header.php';
?>

<div style="display:grid;grid-template-columns:1fr 340px;gap:22px;align-items:start;flex-wrap:wrap">

<!-- Check-In Form -->
<div class="card">
    <div class="card-header">
        <div class="card-title">✅ Visitor Check In</div>
        <a href="../visitors/register.php" class="btn btn-sm btn-accent">+ Register New Visitor</a>
    </div>
    <div class="card-body">

        <?php if ($errors): ?>
        <div class="alert alert-danger" data-auto-dismiss>
            <span class="alert-icon">❌</span>
            <div><?= implode('<br>', array_map('e', $errors)) ?></div>
        </div>
        <?php endif; ?>

        <form method="POST" id="checkin-form" novalidate>
            <?= csrfField() ?>

            <!-- Visitor Search -->
            <div class="form-group mb-16">
                <label for="visitor-search">
                    Visitor <span class="required-star">*</span>
                </label>
                <div style="position:relative">
                    <div class="search-box" style="width:100%">
                        <span class="search-icon">🔍</span>
                        <input type="text"
                               id="visitor-search"
                               placeholder="Search visitor by name or ID…"
                               value="<?= $prefillVisitor ? e($prefillVisitor['full_name']) : '' ?>"
                               style="width:100%;border-radius:var(--radius-sm)">
                    </div>
                    <div id="visitor-list" class="autocomplete-list"></div>
                </div>
                <input type="hidden" id="visitor_id" name="visitor_id"
                       value="<?= $prefillVisitor ? $prefillVisitor['id'] : '' ?>">
                <div class="form-hint">Start typing a name or ID number. Not found? <a href="../visitors/register.php">Register visitor</a>.</div>
            </div>

            <!-- Visitor Detail Preview -->
            <div id="visitor-preview" style="display:<?= $prefillVisitor ? 'block' : 'none' ?>;
                background:var(--primary-light);border-radius:var(--radius-sm);
                padding:12px 16px;margin-bottom:16px;font-size:13px">
                <?php if ($prefillVisitor): ?>
                <strong><?= e($prefillVisitor['full_name']) ?></strong><br>
                <?= e($prefillVisitor['id_type']) ?>: <?= e($prefillVisitor['id_number']) ?>
                <?php if ($prefillVisitor['contact_phone']): ?>
                &nbsp;·&nbsp; 📞 <?= e($prefillVisitor['contact_phone']) ?>
                <?php endif; ?>
                <?php endif; ?>
            </div>

            <!-- Resident Selection -->
            <div class="form-group mb-16">
                <label for="resident_id">Resident to Visit <span class="required-star">*</span></label>
                <select id="resident_id" name="resident_id" required>
                    <option value="">— Select resident —</option>
                    <?php foreach ($residents as $res): ?>
                    <option value="<?= $res['id'] ?>">
                        <?= e($res['full_name']) ?> (Room <?= e($res['room_number']) ?>)
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-grid mb-16">
                <div class="form-group">
                    <label for="relationship">Relationship to Resident <span class="required-star"></span></label>
                    <select id="relationship" name="relationship">
                        <option value="">— Select —</option>
                        <?php foreach (['Son','Daughter','Spouse','Sibling','Grandchild','Nephew/Niece','Friend','Medical Staff','Volunteer','Other'] as $r): ?>
                        <option value="<?= e($r) ?>"><?= e($r) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="num_companions">No. of Companions</label>
                    <input type="number" id="num_companions" name="num_companions"
                           min="0" max="20" value="0">
                </div>
            </div>

            <div class="form-group mb-16">
                <label for="purpose">Purpose of Visit <span class="required-star">*</span></label>
                <select id="purpose" name="purpose" required>
                    <option value="">— Select purpose —</option>
                    <?php foreach (['Regular Family Visit','Medical Checkup','Birthday / Special Occasion','Social/Recreational','Bring Personal Items','Medical Assistance','Official Business','Other'] as $p): ?>
                    <option value="<?= e($p) ?>"><?= e($p) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group mb-20">
                <label for="notes">Additional Notes</label>
                <textarea id="notes" name="notes" rows="2" placeholder="Optional notes…"></textarea>
            </div>

            <button type="submit" class="btn btn-success btn-lg" id="submit-btn" style="width:100%;justify-content:center">
                ✅ Confirm Check In
            </button>
        </form>
    </div>
</div>

<!-- Currently Inside Panel -->
<div class="card">
    <div class="card-header">
        <div class="card-title">🟢 Currently Inside</div>
        <a href="../visitors/checkout.php" class="btn btn-sm btn-danger">Check Out →</a>
    </div>
    <?php
    $inside = $db->query("
        SELECT vl.id, v.full_name AS vname, r.full_name AS rname,
               r.room_number, vl.check_in_time, vl.relationship
        FROM visit_logs vl
        JOIN visitors v  ON v.id = vl.visitor_id
        JOIN residents r ON r.id = vl.resident_id
        WHERE vl.status = 'Checked In'
        ORDER BY vl.check_in_time DESC
    ")->fetchAll();
    ?>
    <?php if ($inside): ?>
    <div class="table-wrapper">
        <table>
            <thead><tr><th>Visitor</th><th>Resident/Room</th><th>Since</th></tr></thead>
            <tbody>
            <?php foreach ($inside as $ci): ?>
            <tr>
                <td class="td-name"><?= e($ci['vname']) ?></td>
                <td>
                    <?= e($ci['rname']) ?>
                    <div class="td-sub">Room <?= e($ci['room_number']) ?></div>
                </td>
                <td style="font-size:11px;color:var(--text-muted)">
                    <?= date('h:i A', strtotime($ci['check_in_time'])) ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
    <div class="empty-state" style="padding:24px">
        <div class="empty-icon" style="font-size:36px">🏡</div>
        <p>No visitors currently inside.</p>
    </div>
    <?php endif; ?>
</div>

</div>

<script>
initVisitorSearch({
    inputId:   'visitor-search',
    listId:    'visitor-list',
    hiddenId:  'visitor_id',
    clearBtnId: null,
    endpoint:  '../api/search_visitors.php'
});

// Show visitor preview on selection
document.getElementById('visitor-list').addEventListener('click', function(e) {
    const item = e.target.closest('.autocomplete-item');
    if (!item) return;
    // Preview will be populated by the autocomplete handler
    setTimeout(() => {
        const vid = document.getElementById('visitor_id').value;
        if (vid) {
            fetch('../api/search_visitors.php?id=' + vid)
                .then(r => r.json())
                .then(data => {
                    if (data.length) {
                        const v = data[0];
                        document.getElementById('visitor-preview').style.display = 'block';
                        document.getElementById('visitor-preview').innerHTML =
                            `<strong>${v.full_name}</strong><br>
                             ${v.id_type}: ${v.id_number}
                             ${v.contact_phone ? ' · 📞 ' + v.contact_phone : ''}`;
                    }
                });
        }
    }, 100);
});

document.getElementById('checkin-form').addEventListener('submit', function() {
    const btn = document.getElementById('submit-btn');
    btn.innerHTML = '<span class="spinner"></span> Processing…';
    btn.disabled = true;
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
