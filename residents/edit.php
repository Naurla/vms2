<?php
/**
 * residents/edit.php – Edit Resident (Admin only)
 */
define('BASE_PATH', '../');
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireAuth();
requireAdmin();
$db = getDB();

$id = (int)get('id');
if (!$id) redirect('../residents/list.php');

$resident = $db->prepare("SELECT * FROM residents WHERE id = ?");
$resident->execute([$id]);
$resident = $resident->fetch();
if (!$resident) { setFlash('error', 'Resident not found.'); redirect('../residents/list.php'); }

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $fullName   = post('full_name');
    $roomNumber = post('room_number');
    $dob        = post('date_of_birth');
    $gender     = post('gender');
    $ecName     = post('emergency_contact_name');
    $ecPhone    = post('emergency_contact_phone');
    $ecRelation = post('emergency_contact_relation');
    $ecRelationCustom = trim(post('emergency_contact_relation_custom'));
    if ($ecRelation === 'Other') {
        if ($ecRelationCustom === '') {
            $errors[] = 'Please specify the emergency contact relationship.';
        } else {
            $ecRelation = $ecRelationCustom;
        }
    }
    $medNotes   = post('medical_notes');
    $admDate    = post('admission_date') ?: null;
    $status     = post('status', 'Active');

    if (!$fullName)   $errors[] = 'Full name is required.';
    if (!$roomNumber) $errors[] = 'Room number is required.';

    // Room uniqueness check (exclude self)
    if (!$errors) {
        $dup = $db->prepare("SELECT id FROM residents WHERE room_number = ? AND status = 'Active' AND id != ?");
        $dup->execute([$roomNumber, $id]);
        if ($dup->fetchColumn()) {
            $errors[] = "Room {$roomNumber} is already assigned to another active resident.";
        }
    }

    if (!$errors) {
        $db->prepare("
            UPDATE residents
            SET full_name=?, room_number=?, date_of_birth=?, gender=?,
                emergency_contact_name=?, emergency_contact_phone=?,
                emergency_contact_relation=?, medical_notes=?,
                admission_date=?, status=?
            WHERE id=?
        ")->execute([$fullName, $roomNumber, $dob ?: null, $gender, $ecName, $ecPhone, $ecRelation, $medNotes, $admDate, $status, $id]);

        setFlash('success', "Resident \"{$fullName}\" updated successfully.");
        redirect('../residents/list.php');
    }

    // Keep POSTed values on error
    $resident = array_merge($resident, $_POST);
}

// Convenience aliases for form values
$v = fn($k) => e($resident[$k] ?? '');

$pageTitle   = 'Edit Resident';
$activeNav   = 'residents';
$breadcrumbs = [['label' => 'Residents', 'url' => '../residents/list.php'], ['label' => 'Edit']];
require_once __DIR__ . '/../includes/header.php';
?>

<div class="card" style="max-width:760px">
    <div class="card-header">
        <div class="card-title">✏️ Edit Resident – <?= e($resident['full_name']) ?></div>
        <a href="../residents/view.php?id=<?= $id ?>" class="btn btn-sm btn-secondary">👁 View Profile</a>
    </div>
    <div class="card-body">

        <?php if ($errors): ?>
        <div class="alert alert-danger" data-auto-dismiss>
            <span class="alert-icon">❌</span>
            <div><?= implode('<br>', array_map('e', $errors)) ?></div>
        </div>
        <?php endif; ?>

        <form method="POST" id="edit-form" novalidate>
            <?= csrfField() ?>

            <p class="section-label">Personal Details</p>
            <div class="form-grid mb-20">
                <div class="form-group full">
                    <label for="full_name">Full Name <span class="required-star">*</span></label>
                    <input type="text" id="full_name" name="full_name" value="<?= $v('full_name') ?>" required autofocus>
                </div>
                <div class="form-group">
                    <label for="room_number">Room Number <span class="required-star">*</span></label>
                    <input type="text" id="room_number" name="room_number" value="<?= $v('room_number') ?>" required>
                </div>
                <div class="form-group">
                    <label for="gender">Gender</label>
                    <select id="gender" name="gender">
                        <option value="">— Select —</option>
                        <?php foreach (['Male','Female','Other'] as $g): ?>
                        <option value="<?= e($g) ?>" <?= ($resident['gender'] ?? '') === $g ? 'selected' : '' ?>><?= e($g) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="date_of_birth">Date of Birth</label>
                    <input type="date" id="date_of_birth" name="date_of_birth" value="<?= $v('date_of_birth') ?>">
                </div>
                <div class="form-group">
                    <label for="admission_date">Admission Date</label>
                    <input type="date" id="admission_date" name="admission_date" value="<?= $v('admission_date') ?>">
                </div>
                <div class="form-group">
                    <label for="status">Status</label>
                    <select id="status" name="status">
                        <option value="Active"   <?= ($resident['status'] ?? '') === 'Active'   ? 'selected' : '' ?>>Active</option>
                        <option value="Inactive" <?= ($resident['status'] ?? '') === 'Inactive' ? 'selected' : '' ?>>Inactive</option>
                    </select>
                </div>
            </div>

            <p class="section-label">Emergency Contact</p>
            <div class="form-grid mb-20">
                <div class="form-group">
                    <label for="emergency_contact_name">Contact Name</label>
                    <input type="text" id="emergency_contact_name" name="emergency_contact_name" value="<?= $v('emergency_contact_name') ?>">
                </div>
                <div class="form-group">
                    <label for="emergency_contact_phone">Contact Phone</label>
                    <input type="tel" id="emergency_contact_phone" name="emergency_contact_phone" value="<?= $v('emergency_contact_phone') ?>">
                </div>
                <div class="form-group">
                    <label for="emergency_contact_relation">Relationship</label>
                    <?php
                    $predefinedRelations = ['Son','Daughter','Spouse','Sibling','Nephew','Niece','Grandchild','Friend'];
                    $selectedRelation = post('emergency_contact_relation', $resident['emergency_contact_relation'] ?? '');
                    $isOtherSelected = ($selectedRelation === 'Other') || ($selectedRelation !== '' && !in_array($selectedRelation, $predefinedRelations));
                    $relationCustomValue = '';
                    if ($isOtherSelected) {
                        if ($selectedRelation === 'Other') {
                            $relationCustomValue = post('emergency_contact_relation_custom');
                        } else {
                            $relationCustomValue = $selectedRelation;
                        }
                    }
                    ?>
                    <select id="emergency_contact_relation" name="emergency_contact_relation">
                        <option value="">— Select —</option>
                        <?php foreach ($predefinedRelations as $r): ?>
                        <option value="<?= e($r) ?>" <?= $selectedRelation === $r ? 'selected' : '' ?>><?= e($r) ?></option>
                        <?php endforeach; ?>
                        <option value="Other" <?= $isOtherSelected ? 'selected' : '' ?>>Other</option>
                    </select>
                    <div id="emergency_contact_relation_custom_container" style="display: <?= $isOtherSelected ? 'block' : 'none' ?>; margin-top: 8px;">
                        <label for="emergency_contact_relation_custom" style="font-size:12px; font-weight:700;">Specify Relationship <span class="required-star">*</span></label>
                        <input type="text" id="emergency_contact_relation_custom" name="emergency_contact_relation_custom" 
                               value="<?= e($relationCustomValue) ?>"
                               placeholder="Specify relationship" 
                               style="width: 100%; margin-top: 4px;">
                    </div>
                </div>
            </div>

            <p class="section-label">Medical Notes</p>
            <div class="form-group mb-24">
                <label for="medical_notes">Notes / Conditions</label>
                <textarea id="medical_notes" name="medical_notes" rows="3"><?= $v('medical_notes') ?></textarea>
            </div>

            <div style="display:flex;gap:12px;flex-wrap:wrap">
                <button type="submit" class="btn btn-accent" id="submit-btn">💾 Save Changes</button>
                <a href="../residents/list.php" class="btn btn-secondary">✕ Cancel</a>
            </div>
        </form>
    </div>
</div>

<script>
document.getElementById('edit-form').addEventListener('submit', function() {
    document.getElementById('submit-btn').innerHTML = '<span class="spinner"></span> Saving…';
    document.getElementById('submit-btn').disabled = true;
});

// Emergency Contact Relation "Other" toggle
(function() {
    const relationSelect = document.getElementById('emergency_contact_relation');
    const relationCustomContainer = document.getElementById('emergency_contact_relation_custom_container');
    const relationCustomInput = document.getElementById('emergency_contact_relation_custom');

    function toggleCustomFields() {
        if (relationSelect && relationCustomContainer && relationCustomInput) {
            if (relationSelect.value === 'Other') {
                relationCustomContainer.style.display = 'block';
                relationCustomInput.required = true;
            } else {
                relationCustomContainer.style.display = 'none';
                relationCustomInput.required = false;
            }
        }
    }

    if (relationSelect) {
        relationSelect.addEventListener('change', toggleCustomFields);
    }
    
    toggleCustomFields();
})();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
