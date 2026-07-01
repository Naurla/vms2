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

$idTypesList = [
    'National ID'       => ['pattern' => '/^\d{4}-\d{4}-\d{4}-\d{4}$/', 'placeholder' => 'e.g. 1234-5678-9012-3456'],
    'Passport'          => ['pattern' => '/^[A-Z]{1,2}\d{7}[A-Z]?$/i', 'placeholder' => 'e.g. P1234567A'],
    "Driver's License"  => ['pattern' => '/^[A-Z]\d{2}-\d{2}-\d{6}$/i', 'placeholder' => 'e.g. N12-34-567890'],
    'Student ID'        => ['pattern' => '/^\d{4}-\d{5,6}$/', 'placeholder' => 'e.g. 2024-12345'],
    'UMID'              => ['pattern' => '/^\d{4}-\d{7}-\d$/', 'placeholder' => 'e.g. 1234-5678901-2'],
    'SSS ID'            => ['pattern' => '/^\d{2}-\d{7}-\d$/', 'placeholder' => 'e.g. 12-3456789-0'],
    'TIN'               => ['pattern' => '/^\d{3}-\d{3}-\d{3}-\d{3,5}$/', 'placeholder' => 'e.g. 123-456-789-000'],
    'PhilHealth ID'     => ['pattern' => '/^\d{2}-\d{9}-\d$/', 'placeholder' => 'e.g. 12-345678901-2'],
    'Senior Citizen ID' => ['pattern' => '/^[A-Z0-9-]{4,12}$/i', 'placeholder' => 'e.g. 12-3456'],
    'Other'             => ['pattern' => '/^.{4,30}$/', 'placeholder' => 'Enter ID number']
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $fullName = post('full_name');
    $idType   = post('id_type');
    $idNumber = post('id_number');
    $phone    = post('contact_phone');
    $province = post('province');
    $city     = post('city');
    $barangay = post('barangay');
    $address  = post('address');

    // Validate
    if (!$fullName)  $errors[] = 'Full name is required.';
    if (!$idType)    $errors[] = 'ID type is required.';
    if (!$idNumber)  $errors[] = 'ID number is required.';

    if ($idType && !array_key_exists($idType, $idTypesList)) {
        $errors[] = 'Invalid ID Type selected.';
    } elseif ($idType && $idNumber) {
        $pattern = $idTypesList[$idType]['pattern'];
        if (!preg_match($pattern, $idNumber)) {
            $errors[] = "ID Number does not match the expected format for {$idType}.";
        }
    }
    if ($phone !== '' && !preg_match('/^[0-9]+$/', $phone)) {
        $errors[] = 'Contact Phone can only contain digits.';
    }
    if (!$province) $errors[] = 'Please select your province.';
    if (!$city)     $errors[] = 'Please select your city or municipality.';
    if (!$barangay) $errors[] = 'Please select your barangay.';

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
        $address = trim(implode(', ', array_filter([$address, $barangay, $city, $province])));

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
                        <?php foreach (array_keys($idTypesList) as $t): ?>
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
                           placeholder="Enter ID number" autocomplete="off" required>
                    <div id="id-validation-hint" style="font-size: 11px; margin-top: 5px; font-weight: 700; transition: color 0.2s;"></div>
                </div>

                <div class="form-group">
                    <label for="contact_phone">Contact Phone</label>
                    <input type="tel" id="contact_phone" name="contact_phone"
                           value="<?= e(post('contact_phone')) ?>"
                           placeholder="e.g. 09171234567"
                           inputmode="numeric" pattern="[0-9]*" autocomplete="tel"
                           oninput="this.value=this.value.replace(/\D/g,'')"
                           onpaste="this.value=(event.clipboardData||window.clipboardData).getData('text').replace(/\D/g,'')">
                </div>

                <div class="form-group">
                    <label for="province">Province</label>
                    <div class="search-box" style="width:100%">
                        <span class="search-icon">🔍</span>
                        <input type="text" id="province" name="province" autocomplete="off"
                               value="<?= e(post('province')) ?>"
                               placeholder="Search province" required>
                        <div id="province-list" class="autocomplete-list"></div>
                    </div>
                </div>
                <div class="form-group">
                    <label for="city">City / Municipality</label>
                    <div class="search-box" style="width:100%">
                        <span class="search-icon">🔍</span>
                        <input type="text" id="city" name="city" autocomplete="off"
                               value="<?= e(post('city')) ?>"
                               placeholder="Search city / municipality" required>
                        <div id="city-list" class="autocomplete-list"></div>
                    </div>
                </div>
                <div class="form-group">
                    <label for="barangay">Barangay</label>
                    <div class="search-box" style="width:100%">
                        <span class="search-icon">🔍</span>
                        <input type="text" id="barangay" name="barangay" autocomplete="off"
                               value="<?= e(post('barangay')) ?>"
                               placeholder="Search barangay" required>
                        <div id="barangay-list" class="autocomplete-list"></div>
                    </div>
                </div>
                <div class="form-group full">
                    <label for="address">Street / House Number</label>
                    <input type="text" id="address" name="address"
                           value="<?= e(post('address')) ?>"
                           placeholder="Street, Block, Subdivision">
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


const idTypeSelect = document.getElementById('id_type');
const idNumberInput = document.getElementById('id_number');
const validationHint = document.getElementById('id-validation-hint');



if (idTypeSelect && idNumberInput) {
    initIDValidation('id_type', 'id_number', 'id-validation-hint');

    document.getElementById('register-form')?.addEventListener('submit', function(e) {
        if (!validateID()) {
            e.preventDefault();
            alert('Please enter a valid ID Number matching the selected ID Type layout.');
            return;
        }
        document.getElementById('submit-btn').innerHTML = '<span class="spinner"></span> Saving…';
        document.getElementById('submit-btn').disabled = true;
    });
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
