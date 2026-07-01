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
    $relationshipCustom = trim(post('relationship_custom'));
    if ($relationship === 'Other') {
        if ($relationshipCustom === '') {
            $errors[] = 'Please specify the relationship.';
        } else {
            $relationship = $relationshipCustom;
        }
    }

    $purpose       = post('purpose');
    $purposeCustom = trim(post('purpose_custom'));
    if ($purpose === 'Other') {
        if ($purposeCustom === '') {
            $errors[] = 'Please specify the purpose of visit.';
        } else {
            $purpose = $purposeCustom;
        }
    }

    $numCompanions = max(0, (int)post('num_companions', '0'));
    $notes         = post('notes');

    $companionNames = $_POST['companion_name'] ?? [];
    $companionRels  = $_POST['companion_relationship'] ?? [];
    $validatedCompanions = [];

    if ($numCompanions > 0) {
        for ($i = 0; $i < $numCompanions; $i++) {
            $cName = isset($companionNames[$i]) ? trim($companionNames[$i]) : '';
            $cRel  = isset($companionRels[$i]) ? trim($companionRels[$i]) : '';
            if ($cName === '') {
                $errors[] = "Please provide the name of Companion #" . ($i + 1) . ".";
            }
            if ($cRel === '') {
                $errors[] = "Please select the relationship for Companion #" . ($i + 1) . ".";
            }
            $validatedCompanions[] = [
                'name' => $cName,
                'relationship' => $cRel
            ];
        }
    }

    if (!$visitorId)  $errors[] = 'Please select or search for a visitor.';
    if (!$residentId) $errors[] = 'Please select a resident to visit.';
    if (!$purpose)    $errors[] = 'Purpose of visit is required.';

    // Check for restrictions
    if (!$errors && $residentId && $visitorId) {
        $vis = $db->prepare("SELECT full_name FROM visitors WHERE id = ?");
        $vis->execute([$visitorId]);
        $visitorName = $vis->fetchColumn();

        $stmtRestr = $db->prepare("SELECT reason, allowed_visitors FROM visitor_restrictions WHERE resident_id = ? AND restriction_date = CURRENT_DATE() AND status = 'Approved'");
        $stmtRestr->execute([$residentId]);
        $restriction = $stmtRestr->fetch();
        if ($restriction) {
            $isAllowed = false;
            if (!empty($restriction['allowed_visitors'])) {
                $allowedList = array_map('trim', explode(',', strtolower($restriction['allowed_visitors'])));
                $searchName = strtolower($visitorName);
                foreach ($allowedList as $allowedName) {
                    if ($allowedName && strpos($searchName, $allowedName) !== false) {
                        $isAllowed = true;
                        break;
                    }
                }
            }
            if (!$isAllowed) {
                $errors[] = "Check-in is currently restricted for this resident today. Reason: " . $restriction['reason'];
            }
        }
    }

    // Check visitor isn't already checked in
    if (!$errors) {
        $alreadyIn = $db->prepare("SELECT id FROM visit_logs WHERE visitor_id = ? AND status = 'Checked In'");
        $alreadyIn->execute([$visitorId]);
        if ($alreadyIn->fetchColumn()) {
            $errors[] = 'This visitor is already checked in. Please check them out first.';
        }
    }

    if (!$errors) {
        $visitCode = generateVisitCode($db);
        $stmt = $db->prepare("
            INSERT INTO visit_logs
                (visitor_id, resident_id, relationship, purpose, num_companions, check_in_time, checked_in_by, visit_code)
            VALUES (?, ?, ?, ?, ?, NOW(), ?, ?)
        ");
        $stmt->execute([$visitorId, $residentId, $relationship, $purpose, $numCompanions, $user['id'], $visitCode]);

        $visitLogId = (int)$db->lastInsertId();
        if ($numCompanions > 0 && !empty($validatedCompanions)) {
            $compStmt = $db->prepare("INSERT INTO visit_companions (visit_log_id, full_name, relationship) VALUES (?, ?, ?)");
            foreach ($validatedCompanions as $c) {
                $compStmt->execute([$visitLogId, $c['name'], $c['relationship']]);
            }
        }

        // Get names for confirmation
        $vis = $db->prepare("SELECT full_name FROM visitors WHERE id = ?");
        $vis->execute([$visitorId]);
        $visName = $vis->fetchColumn();

        $res = $db->prepare("SELECT full_name FROM residents WHERE id = ?");
        $res->execute([$residentId]);
        $resName = $res->fetchColumn();

        setFlash('success', "✅ {$visName} has been checked in to visit {$resName}. Check-Out Code: <strong>{$visitCode}</strong>");
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
                        <option value="<?= e($r) ?>" <?= post('relationship') === $r ? 'selected' : '' ?>><?= e($r) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div id="relationship_custom_container" style="display: <?= post('relationship') === 'Other' ? 'block' : 'none' ?>; margin-top: 8px;">
                        <label for="relationship_custom" style="font-size:12px; font-weight:700;">Specify Relationship <span class="required-star">*</span></label>
                        <input type="text" id="relationship_custom" name="relationship_custom" 
                               value="<?= e(post('relationship_custom')) ?>"
                               placeholder="Specify relationship" 
                               style="width: 100%; margin-top: 4px; border-radius:var(--radius-sm)">
                    </div>
                </div>
                <div class="form-group">
                    <label for="num_companions">No. of Companions</label>
                    <input type="number" id="num_companions" name="num_companions"
                           min="0" max="20" value="0">
                </div>
            </div>

            <!-- Companion Details Container -->
            <div id="companions_container" style="display:none; margin-bottom:16px; background:var(--primary-light); border-radius:var(--radius-sm); padding:16px;">
                <h4 style="margin-top:0; margin-bottom:12px; color:var(--primary); font-size: 14px; font-weight:800;">👥 Companion Details</h4>
                <div id="companions_fields_list" style="display: flex; flex-direction: column; gap: 12px;"></div>
            </div>


            <div class="form-group mb-16">
                <label for="purpose">Purpose of Visit <span class="required-star">*</span></label>
                <select id="purpose" name="purpose" required>
                    <option value="">— Select purpose —</option>
                    <?php foreach (['Regular Family Visit','Medical Checkup','Birthday / Special Occasion','Social/Recreational','Bring Personal Items','Medical Assistance','Official Business','Other'] as $p): ?>
                    <option value="<?= e($p) ?>" <?= post('purpose') === $p ? 'selected' : '' ?>><?= e($p) ?></option>
                    <?php endforeach; ?>
                </select>
                <div id="purpose_custom_container" style="display: <?= post('purpose') === 'Other' ? 'block' : 'none' ?>; margin-top: 8px;">
                    <label for="purpose_custom" style="font-size:12px; font-weight:700;">Specify Purpose <span class="required-star">*</span></label>
                    <input type="text" id="purpose_custom" name="purpose_custom" 
                           value="<?= e(post('purpose_custom')) ?>"
                           placeholder="Specify purpose of visit" 
                           style="width: 100%; margin-top: 4px; border-radius:var(--radius-sm)">
                </div>
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

// Relationship and Purpose "Other" toggles
(function() {
    const relationshipSelect = document.getElementById('relationship');
    const relationshipCustomContainer = document.getElementById('relationship_custom_container');
    const relationshipCustomInput = document.getElementById('relationship_custom');

    const purposeSelect = document.getElementById('purpose');
    const purposeCustomContainer = document.getElementById('purpose_custom_container');
    const purposeCustomInput = document.getElementById('purpose_custom');

    function toggleCustomFields() {
        if (relationshipSelect && relationshipCustomContainer && relationshipCustomInput) {
            if (relationshipSelect.value === 'Other') {
                relationshipCustomContainer.style.display = 'block';
                relationshipCustomInput.required = true;
            } else {
                relationshipCustomContainer.style.display = 'none';
                relationshipCustomInput.required = false;
            }
        }
        if (purposeSelect && purposeCustomContainer && purposeCustomInput) {
            if (purposeSelect.value === 'Other') {
                purposeCustomContainer.style.display = 'block';
                purposeCustomInput.required = true;
            } else {
                purposeCustomContainer.style.display = 'none';
                purposeCustomInput.required = false;
            }
        }
    }

    if (relationshipSelect) {
        relationshipSelect.addEventListener('change', toggleCustomFields);
    }
    if (purposeSelect) {
        purposeSelect.addEventListener('change', toggleCustomFields);
    }
    
    // Run initially
    toggleCustomFields();
})();

// Dynamic Companion Fields Generation
(function() {
    const numCompanionsInput = document.getElementById('num_companions');
    const companionsContainer = document.getElementById('companions_container');
    const companionsFieldsList = document.getElementById('companions_fields_list');

    function escapeHtml(string) {
        return String(string).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    function updateCompanionFields() {
        if (!numCompanionsInput || !companionsContainer || !companionsFieldsList) return;
        
        const count = parseInt(numCompanionsInput.value) || 0;
        if (count <= 0) {
            companionsContainer.style.display = 'none';
            companionsFieldsList.innerHTML = '';
            return;
        }
        
        // Save existing values if any
        const existingNames = Array.from(companionsFieldsList.querySelectorAll('input[name="companion_name[]"]')).map(input => input.value);
        const existingRelationships = Array.from(companionsFieldsList.querySelectorAll('select[name="companion_relationship[]"]')).map(select => select.value);
        
        companionsFieldsList.innerHTML = '';
        companionsContainer.style.display = 'block';
        
        for (let i = 0; i < count; i++) {
            const row = document.createElement('div');
            row.className = 'companion-row';
            row.style.cssText = 'display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px; padding: 14px; background: #fff; border: 2px solid #e2eaf0; border-radius: var(--radius-sm); margin-bottom: 8px; animation: slideDown 0.2s ease;';
            
            const nameVal = existingNames[i] || '';
            const relVal = existingRelationships[i] || '';
            
            row.innerHTML = `
                <div class="form-group" style="margin-bottom: 0;">
                    <label style="font-size: 12px; font-weight: 700; color: var(--text-secondary);">Companion #${i + 1} Full Name <span class="required-star">*</span></label>
                    <input type="text" name="companion_name[]" value="${escapeHtml(nameVal)}" placeholder="Enter full name" required style="padding: 8px 12px; font-size: 14px; border-radius: var(--radius-sm);">
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label style="font-size: 12px; font-weight: 700; color: var(--text-secondary);">Relationship to Resident <span class="required-star">*</span></label>
                    <select name="companion_relationship[]" required style="padding: 8px 12px; font-size: 14px; height: 41px; border-radius: var(--radius-sm);">
                        <option value="">— Select —</option>
                        ${['Son','Daughter','Spouse','Sibling','Grandchild','Nephew/Niece','Friend','Medical Staff','Volunteer','Other'].map(r => 
                            `<option value="${r}" ${relVal === r ? 'selected' : ''}>${r}</option>`
                        ).join('')}
                    </select>
                </div>
            `;
            companionsFieldsList.appendChild(row);
        }
    }

    if (numCompanionsInput) {
        numCompanionsInput.addEventListener('input', updateCompanionFields);
        numCompanionsInput.addEventListener('change', updateCompanionFields);
        updateCompanionFields();
    }
})();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
