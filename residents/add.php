<?php
/**
 * residents/add.php – Add New Resident (Admin only)
 */
define('BASE_PATH', '../');
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireAuth();
requireAdmin();
$db = getDB();

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $fullName     = post('full_name');
    $roomNumber   = post('room_number');
    $dob          = post('date_of_birth');
    $gender       = post('gender');
    $ecName       = post('emergency_contact_name');
    $ecPhone      = post('emergency_contact_phone');
    $ecRelation   = post('emergency_contact_relation');
    $medNotes     = post('medical_notes');
    $admDate      = post('admission_date') ?: null;

    if (!$fullName)   $errors[] = 'Full name is required.';
    if (!$roomNumber) $errors[] = 'Room number is required.';

    // Check room uniqueness (active residents)
    if (!$errors) {
        $dup = $db->prepare("SELECT id FROM residents WHERE room_number = ? AND status = 'Active'");
        $dup->execute([$roomNumber]);
        if ($dup->fetchColumn()) {
            $errors[] = "Room {$roomNumber} is already assigned to an active resident.";
        }
    }

    if (!$errors) {
        $db->prepare("
            INSERT INTO residents
                (full_name, room_number, date_of_birth, gender,
                 emergency_contact_name, emergency_contact_phone, emergency_contact_relation,
                 medical_notes, admission_date)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ")->execute([$fullName, $roomNumber, $dob ?: null, $gender, $ecName, $ecPhone, $ecRelation, $medNotes, $admDate]);

        setFlash('success', "Resident \"{$fullName}\" (Room {$roomNumber}) added successfully.");
        redirect('../residents/list.php');
    }
}

$pageTitle   = 'Add Resident';
$activeNav   = 'resident-add';
$breadcrumbs = [['label' => 'Residents', 'url' => '../residents/list.php'], ['label' => 'Add Resident']];
require_once __DIR__ . '/../includes/header.php';
?>

<div class="card" style="max-width:760px">
    <div class="card-header">
        <div class="card-title">👵 Add New Resident</div>
    </div>
    <div class="card-body">

        <?php if ($errors): ?>
        <div class="alert alert-danger" data-auto-dismiss>
            <span class="alert-icon">❌</span>
            <div><?= implode('<br>', array_map('e', $errors)) ?></div>
        </div>
        <?php endif; ?>

        <form method="POST" id="add-form" novalidate>
            <?= csrfField() ?>

            <p class="section-label">Personal Details</p>
            <div class="form-grid mb-20">
                <div class="form-group full">
                    <label for="full_name">Full Name <span class="required-star">*</span></label>
                    <input type="text" id="full_name" name="full_name"
                           value="<?= e(post('full_name')) ?>"
                           placeholder="e.g. Maria Dela Cruz" required autofocus>
                </div>
                <div class="form-group">
                    <label for="room_number">Room Number <span class="required-star">*</span></label>
                    <input type="text" id="room_number" name="room_number"
                           value="<?= e(post('room_number')) ?>"
                           placeholder="e.g. 101" required>
                </div>
                <div class="form-group">
                    <label for="gender">Gender</label>
                    <select id="gender" name="gender">
                        <option value="">— Select —</option>
                        <option value="Male"   <?= post('gender') === 'Male'   ? 'selected' : '' ?>>Male</option>
                        <option value="Female" <?= post('gender') === 'Female' ? 'selected' : '' ?>>Female</option>
                        <option value="Other"  <?= post('gender') === 'Other'  ? 'selected' : '' ?>>Other</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="date_of_birth">Date of Birth</label>
                    <input type="date" id="date_of_birth" name="date_of_birth"
                           value="<?= e(post('date_of_birth')) ?>">
                </div>
                <div class="form-group">
                    <label for="admission_date">Admission Date</label>
                    <input type="date" id="admission_date" name="admission_date"
                           value="<?= e(post('admission_date') ?: date('Y-m-d')) ?>">
                </div>
            </div>

            <p class="section-label">Emergency Contact</p>
            <div class="form-grid mb-20">
                <div class="form-group">
                    <label for="emergency_contact_name">Contact Name</label>
                    <input type="text" id="emergency_contact_name" name="emergency_contact_name"
                           value="<?= e(post('emergency_contact_name')) ?>"
                           placeholder="e.g. Jose Dela Cruz">
                </div>
                <div class="form-group">
                    <label for="emergency_contact_phone">Contact Phone</label>
                    <input type="tel" id="emergency_contact_phone" name="emergency_contact_phone"
                           value="<?= e(post('emergency_contact_phone')) ?>"
                           placeholder="e.g. 09171234567">
                </div>
                <div class="form-group">
                    <label for="emergency_contact_relation">Relationship</label>
                    <select id="emergency_contact_relation" name="emergency_contact_relation">
                        <option value="">— Select —</option>
                        <?php foreach (['Son','Daughter','Spouse','Sibling','Nephew','Niece','Grandchild','Friend','Other'] as $r): ?>
                        <option value="<?= e($r) ?>" <?= post('emergency_contact_relation') === $r ? 'selected' : '' ?>>
                            <?= e($r) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <p class="section-label">Medical Notes</p>
            <div class="form-group mb-24">
                <label for="medical_notes">Notes / Conditions</label>
                <textarea id="medical_notes" name="medical_notes" rows="3"
                          placeholder="Any medical conditions, allergies, or special care requirements…"><?= e(post('medical_notes')) ?></textarea>
            </div>

            <div style="display:flex;gap:12px;flex-wrap:wrap">
                <button type="submit" class="btn btn-accent" id="submit-btn">
                    💾 Save Resident
                </button>
                <a href="../residents/list.php" class="btn btn-secondary">✕ Cancel</a>
            </div>
        </form>
    </div>
</div>

<script>
document.getElementById('add-form').addEventListener('submit', function() {
    document.getElementById('submit-btn').innerHTML = '<span class="spinner"></span> Saving…';
    document.getElementById('submit-btn').disabled = true;
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
