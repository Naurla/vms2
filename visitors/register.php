<?php
/**
 * visitors/register.php – Register a New Visitor
 */
define('BASE_PATH', '../');
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireAuth();
$db = getDB();

$errors  = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $fullName = post('full_name');
    $idType   = post('id_type');
    $idNumber = post('id_number');
    $phone    = post('contact_phone');
    $address  = post('address');

    // Validate
    if (!$fullName)  $errors[] = 'Full name is required.';
    if (!$idType)    $errors[] = 'ID type is required.';
    if (!$idNumber)  $errors[] = 'ID number is required.';

    if (!$errors) {
        // Check for duplicate (same id_type + id_number)
        $dup = $db->prepare("SELECT id FROM visitors WHERE id_type = ? AND id_number = ?");
        $dup->execute([$idType, $idNumber]);
        if ($dup->fetchColumn()) {
            $errors[] = 'A visitor with this ID type and number already exists.';
        }
    }

    if (!$errors) {
        $stmt = $db->prepare("
            INSERT INTO visitors (full_name, id_type, id_number, contact_phone, address)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([$fullName, $idType, $idNumber, $phone, $address]);
        $newId = $db->lastInsertId();
        setFlash('success', "Visitor \"{$fullName}\" registered successfully.");
        redirect('../visitors/checkin.php?prefill=' . $newId);
    }
}

$pageTitle   = 'Register Visitor';
$activeNav   = 'register';
$breadcrumbs = [['label' => 'Visitors'], ['label' => 'Register']];
require_once __DIR__ . '/../includes/header.php';
?>

<div class="card" style="max-width:700px">
    <div class="card-header">
        <div class="card-title">📝 Register New Visitor</div>
    </div>
    <div class="card-body">

        <?php if ($errors): ?>
        <div class="alert alert-danger" data-auto-dismiss>
            <span class="alert-icon">❌</span>
            <div><?= implode('<br>', array_map('e', $errors)) ?></div>
            <button class="alert-close">×</button>
        </div>
        <?php endif; ?>

        <form method="POST" id="register-form" novalidate>
            <?= csrfField() ?>

            <p class="section-label">Personal Information</p>

            <div class="form-grid mb-20">
                <div class="form-group full">
                    <label for="full_name">Full Name <span class="required-star">*</span></label>
                    <input type="text" id="full_name" name="full_name"
                           value="<?= e(post('full_name')) ?>"
                           placeholder="e.g. Juan dela Cruz"
                           required autofocus>
                </div>

                <div class="form-group">
                    <label for="id_type">ID Type <span class="required-star">*</span></label>
                    <select id="id_type" name="id_type" required>
                        <option value="">— Select ID type —</option>
                        <?php foreach (['National ID', 'Passport', "Driver's License", 'Senior Citizen ID', 'Other'] as $t): ?>
                        <option value="<?= e($t) ?>" <?= post('id_type') === $t ? 'selected' : '' ?>>
                            <?= e($t) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="id_number">ID Number <span class="required-star">*</span></label>
                    <input type="text" id="id_number" name="id_number"
                           value="<?= e(post('id_number')) ?>"
                           placeholder="e.g. 1234-5678-90">
                </div>

                <div class="form-group">
                    <label for="contact_phone">Contact Phone</label>
                    <input type="tel" id="contact_phone" name="contact_phone"
                           value="<?= e(post('contact_phone')) ?>"
                           placeholder="e.g. 09171234567">
                </div>

                <div class="form-group full">
                    <label for="address">Home Address</label>
                    <textarea id="address" name="address" rows="2"
                              placeholder="Street, Barangay, City/Municipality"><?= e(post('address')) ?></textarea>
                </div>
            </div>

            <div style="display:flex;gap:12px;flex-wrap:wrap">
                <button type="submit" class="btn btn-primary" id="submit-btn">
                    ✅ Register &amp; Proceed to Check In
                </button>
                <a href="../dashboard.php" class="btn btn-secondary">✕ Cancel</a>
            </div>
        </form>
    </div>
</div>

<script>
document.getElementById('register-form').addEventListener('submit', function() {
    document.getElementById('submit-btn').innerHTML = '<span class="spinner"></span> Saving…';
    document.getElementById('submit-btn').disabled = true;
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
