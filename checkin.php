<?php
/**
 * index.php – Public Visitor Self-Service Check-In Kiosk
 * No authentication required.
 */
define('BASE_PATH', './');
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

if (session_status() === PHP_SESSION_NONE) session_start();

$db = getDB();

// Auto-create a system kiosk user for the FK constraint (is_active=0 so it can never login)
$kioskUserId = $db->query("SELECT id FROM users WHERE email = 'kiosk@system.local' LIMIT 1")->fetchColumn();
if (!$kioskUserId) {
    $db->prepare("INSERT INTO users (email, password, full_name, role, is_active) VALUES ('kiosk@system.local', ?, 'Visitor Self Check-In', 'receptionist', 0)")
       ->execute([password_hash('KIOSK_NO_ACCESS_' . uniqid(), PASSWORD_BCRYPT)]);
    $kioskUserId = (int)$db->lastInsertId();
}
$kioskUserId = (int)$kioskUserId;



$errors       = [];
$success      = false;
$confirmation = null;

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
    $fullName      = post('full_name');
    $idType        = post('id_type');
    $idNumber      = post('id_number');
    $phone         = post('contact_phone');
    $province      = post('province');
    $city          = post('city');
    $barangay      = post('barangay');
    $address       = post('address');
    $residentName  = trim(post('resident_name'));
    $residentId    = 0;
    $relationship  = post('relationship');
    $relationshipCustom = trim(post('relationship_custom'));
    if ($relationship === 'Other') {
        if ($relationshipCustom === '') {
            $errors[] = 'Please specify your relationship.';
        } else {
            $relationship = $relationshipCustom;
        }
    }

    $purpose       = post('purpose');
    $purposeCustom = trim(post('purpose_custom'));
    if ($purpose === 'Other') {
        if ($purposeCustom === '') {
            $errors[] = 'Please specify your purpose of visit.';
        } else {
            $purpose = $purposeCustom;
        }
    }
    $numCompanions = max(0, (int)post('num_companions', '0'));
    $notes         = post('notes');

    if (!$fullName)   $errors[] = 'Your full name is required.';
    if (!$idType)     $errors[] = 'Please select your ID type.';
    if (!$idNumber)   $errors[] = 'Your ID number is required.';

    if ($idType && !array_key_exists($idType, $idTypesList)) {
        $errors[] = 'Invalid ID Type selected.';
    } elseif ($idType && $idNumber) {
        $pattern = $idTypesList[$idType]['pattern'];
        if (!preg_match($pattern, $idNumber)) {
            $errors[] = "ID Number does not match the expected format for {$idType}.";
        }
    }
    if ($phone !== '' && !preg_match('/^[0-9]+$/', $phone)) {
        $errors[] = 'Contact Number must contain digits only.';
    }
    if (!$province) $errors[] = 'Please select your province.';
    if (!$city)     $errors[] = 'Please select your city or municipality.';
    if (!$barangay) $errors[] = 'Please select your barangay.';
    
    if (!$residentName) {
        $errors[] = 'Please enter the name of the resident you are visiting.';
    } else {
        $resStmt = $db->prepare("SELECT id, full_name FROM residents WHERE status='Active' AND LOWER(full_name) = ? LIMIT 1");
        $resStmt->execute([strtolower($residentName)]);
        $resMatch = $resStmt->fetch();
        if (!$resMatch) {
            $errors[] = 'No active resident exists with that name. Please check spelling.';
        } else {
            $residentId = (int)$resMatch['id'];
        }
    }

    if (!$purpose)    $errors[] = 'Please select your purpose of visit.';

    if (!$errors) {
        $address = trim(implode(', ', array_filter([$address, $barangay, $city, $province])));

        // Find or auto-register visitor by ID
        $stmt = $db->prepare("SELECT id FROM visitors WHERE id_type = ? AND id_number = ?");
        $stmt->execute([$idType, $idNumber]);
        $visitorId = $stmt->fetchColumn();

        if (!$visitorId) {
            $db->prepare("INSERT INTO visitors (full_name, id_type, id_number, contact_phone, address) VALUES (?,?,?,?,?)")
               ->execute([$fullName, $idType, $idNumber, $phone, $address]);
            $visitorId = (int)$db->lastInsertId();
        } else {
            // Update info in case anything changed
            $db->prepare("UPDATE visitors SET full_name=?, contact_phone=?, address=? WHERE id=?")
               ->execute([$fullName, $phone, $address, $visitorId]);
        }

        // Block if already checked in
        $already = $db->prepare("SELECT id FROM visit_logs WHERE visitor_id=? AND status='Checked In'");
        $already->execute([$visitorId]);
        if ($already->fetchColumn()) {
            $errors[] = 'You are already checked in. Please use the Check-Out terminal first.';
        }
    }

    if (!$errors) {
        $visitCode = generateVisitCode($db);
        $db->prepare("
            INSERT INTO visit_logs
                (visitor_id, resident_id, relationship, purpose, num_companions, check_in_time, checked_in_by, notes, visit_code)
            VALUES (?, ?, ?, ?, ?, NOW(), ?, ?, ?)
        ")->execute([$visitorId, $residentId, $relationship, $purpose, $numCompanions, $kioskUserId, $notes, $visitCode]);

        $res = $db->prepare("SELECT full_name, room_number FROM residents WHERE id=?");
        $res->execute([$residentId]);
        $res = $res->fetch();

        $confirmation = [
            'name'      => $fullName,
            'resident'  => $res['full_name'],
            'room'      => $res['room_number'],
            'time'      => date('h:i A'),
            'date'      => date('F d, Y'),
            'purpose'   => $purpose,
            'companions'=> $numCompanions,
            'visit_code'=> $visitCode,
        ];
        $success = true;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Visitor Check-In – Care Home for the Aged">
    <title>Visitor Check-In – Care Home VMS</title>
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
        body{
            font-family:'Nunito',sans-serif;
            min-height:100vh;
            background:linear-gradient(160deg,#0d3d4f 0%,#1a5f7a 55%,#c8a45e 100%);
            background-size:400% 400%;
            animation:bgShift 18s ease infinite;
            display:flex;flex-direction:column;align-items:center;justify-content:flex-start;
            padding:24px 16px;
        }
        @keyframes bgShift{0%{background-position:0% 50%}50%{background-position:100% 50%}100%{background-position:0% 50%}}

        /* Top bar */
        .topbar{
            width:100%;max-width:780px;
            display:flex;align-items:center;justify-content:space-between;
            margin-bottom:24px;padding:0 4px;
        }
        .topbar-brand{display:flex;align-items:center;gap:12px;color:#fff}
        .topbar-icon{
            width:48px;height:48px;background:rgba(255,255,255,.2);
            border-radius:14px;display:flex;align-items:center;justify-content:center;
            font-size:24px;border:2px solid rgba(255,255,255,.3)
        }
        .topbar-name{font-size:18px;font-weight:900;line-height:1}
        .topbar-sub{font-size:12px;opacity:.75;font-weight:600}
        .topbar-clock{color:#fff;text-align:right;font-weight:800;font-size:15px;opacity:.9}
        .topbar-date{font-size:11px;opacity:.65;font-weight:600}

        /* Card */
        .card{
            background:#fff;border-radius:24px;
            width:100%;max-width:780px;
            box-shadow:0 24px 70px rgba(0,0,0,.28);
            overflow:hidden;
            animation:cardIn .5s ease;
        }
        @keyframes cardIn{from{opacity:0;transform:translateY(24px)}to{opacity:1;transform:translateY(0)}}

        .card-head{
            background:linear-gradient(135deg,#0d3d4f,#1a5f7a);
            padding:28px 36px;color:#fff;
            display:flex;align-items:center;gap:16px;
        }
        .card-head-icon{font-size:36px}
        .card-head h1{font-size:24px;font-weight:900;margin-bottom:4px}
        .card-head p{font-size:13px;opacity:.8;font-weight:600}

        .card-body{padding:36px}

        /* Form */
        .section-label{
            font-size:11px;font-weight:800;text-transform:uppercase;
            letter-spacing:1.2px;color:#7f8c8d;
            margin-bottom:14px;padding-bottom:8px;
            border-bottom:2px solid #e2eaf0;
        }
        .form-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:16px;margin-bottom:24px}
        .form-group{display:flex;flex-direction:column;gap:6px}
        .form-group.full{grid-column:1/-1}
        label{font-size:13px;font-weight:700;color:#5a6a77}
        .req{color:#ef4444}
        input,select,textarea{
            padding:11px 15px;border:2px solid #e2eaf0;border-radius:10px;
            font-family:inherit;font-size:15px;color:#1e2c38;background:#fff;
            transition:border-color .2s,box-shadow .2s;outline:none;
        }
        input:focus,select:focus,textarea:focus{
            border-color:#1a5f7a;box-shadow:0 0 0 3px rgba(26,95,122,.12)
        }
        textarea{resize:vertical;min-height:80px}
        select{cursor:pointer}

        /* Alert */
        .alert{
            padding:14px 18px;border-radius:12px;margin-bottom:20px;
            font-size:14px;font-weight:700;border-left:4px solid;
        }
        .alert-error{background:#fee2e2;color:#991b1b;border-color:#ef4444}
        .alert ul{margin-left:18px;margin-top:6px}
        .alert li{margin-bottom:4px}

        /* Submit */
        .btn-submit{
            width:100%;padding:16px;
            background:linear-gradient(135deg,#1a5f7a,#0d3d4f);
            color:#fff;border:none;border-radius:14px;
            font-family:inherit;font-size:17px;font-weight:900;
            cursor:pointer;
            box-shadow:0 6px 20px rgba(26,95,122,.4);
            transition:transform .15s,box-shadow .15s;
            display:flex;align-items:center;justify-content:center;gap:10px;
        }
        .btn-submit:hover{transform:translateY(-2px);box-shadow:0 8px 28px rgba(26,95,122,.5)}
        #cancel-btn:hover{background:#dce4ec !important; color:#1e2c38 !important; box-shadow:0 6px 15px rgba(0,0,0,0.1) !important;}
        .btn-submit:disabled{opacity:.6;cursor:not-allowed;transform:none}

        /* Success */
        .success-card{text-align:center;padding:20px 0 10px}
        .success-icon{font-size:72px;margin-bottom:16px;animation:popIn .5s ease}
        @keyframes popIn{from{transform:scale(0)}to{transform:scale(1)}}
        .success-title{font-size:26px;font-weight:900;color:#0d3d4f;margin-bottom:6px}
        .success-sub{font-size:15px;color:#7f8c8d;font-weight:600;margin-bottom:28px}
        .confirm-box{
            background:linear-gradient(135deg,#f0fdf4,#d1fae5);
            border-radius:16px;padding:24px 28px;
            border:2px solid #a7f3d0;margin-bottom:24px;text-align:left;
        }
        .confirm-row{
            display:flex;align-items:flex-start;gap:12px;
            padding:10px 0;border-bottom:1px solid rgba(16,185,129,.2);
        }
        .confirm-row:last-child{border-bottom:none;padding-bottom:0}
        .confirm-icon{font-size:20px;flex-shrink:0;margin-top:1px}
        .confirm-label{font-size:12px;font-weight:700;color:#065f46;text-transform:uppercase;letter-spacing:.5px}
        .confirm-value{font-size:15px;font-weight:800;color:#0d3d4f}
        .btn-new{
            display:inline-flex;align-items:center;gap:8px;
            background:#1a5f7a;color:#fff;
            padding:13px 28px;border-radius:12px;
            font-family:inherit;font-size:15px;font-weight:800;
            border:none;cursor:pointer;text-decoration:none;
            transition:all .2s;
        }
        .btn-new:hover{background:#0d3d4f;transform:translateY(-2px)}
        .btn-checkout{
            display:inline-flex;align-items:center;gap:8px;
            background:transparent;color:#1a5f7a;
            padding:13px 28px;border-radius:12px;
            font-family:inherit;font-size:15px;font-weight:800;
            border:2px solid #1a5f7a;cursor:pointer;text-decoration:none;
            transition:all .2s;margin-left:10px;
        }
        .btn-checkout:hover{background:#1a5f7a;color:#fff}

        .spinner{
            width:20px;height:20px;border:3px solid rgba(255,255,255,.4);
            border-top-color:#fff;border-radius:50%;animation:spin .6s linear infinite;display:inline-block;
        }
        @keyframes spin{to{transform:rotate(360deg)}}

        /* Modal Styles */
        .modal-overlay {
            position: fixed; inset: 0; background: rgba(0,0,0,.6); z-index: 10000;
            display: none; align-items: center; justify-content: center; padding: 20px;
        }
        .modal-overlay.open { display: flex; }
        .modal {
            background: #fff; border-radius: 20px; padding: 32px; max-width: 500px; width: 100%;
            box-shadow: 0 20px 60px rgba(0,0,0,.25); animation: mIn .25s ease;
        }
        @keyframes mIn { from { opacity: 0; transform: scale(.95); } to { opacity: 1; transform: scale(1); } }
        .modal-icon { font-size: 40px; text-align: center; margin-bottom: 12px; }
        .modal h2 { font-size: 22px; font-weight: 900; color: #0d3d4f; text-align: center; margin-bottom: 8px; }
        .modal-sub { font-size: 14px; color: #7f8c8d; font-weight: 600; text-align: center; margin-bottom: 20px; }
        
        .confirm-details-list {
            background: #f8fafc; border: 2px solid #e2eaf0; border-radius: 12px;
            padding: 16px; margin-bottom: 24px; max-height: 300px; overflow-y: auto;
        }
        .confirm-detail-item {
            display: flex; justify-content: space-between; gap: 12px; padding: 8px 0;
            border-bottom: 1px solid #e2eaf0; font-size: 14px;
        }
        .confirm-detail-item:last-child { border-bottom: none; }
        .confirm-detail-label { font-weight: 700; color: #5a6a77; flex-shrink: 0; }
        .confirm-detail-value { font-weight: 800; color: #1e2c38; text-align: right; word-break: break-word; }

        .modal-btns { display: flex; gap: 12px; }
        .btn-modal-confirm {
            flex: 2; padding: 14px; background: linear-gradient(135deg,#1a5f7a,#0d3d4f);
            color: #fff; border: none; border-radius: 12px; font-family: inherit;
            font-size: 15px; font-weight: 800; cursor: pointer; text-align: center;
        }
        .btn-modal-confirm:hover { box-shadow: 0 4px 12px rgba(26,95,122,.3); transform: translateY(-1px); }
        .btn-modal-cancel {
            flex: 1; padding: 14px; background: #e8edf2; color: #5a6a77;
            border: none; border-radius: 12px; font-family: inherit;
            font-size: 15px; font-weight: 700; cursor: pointer; text-align: center;
        }
        .btn-modal-cancel:hover { background: #dce4ec; }

        .footer-note{color:rgba(255,255,255,.55);font-size:12px;font-weight:600;margin-top:20px;text-align:center}
        a.staff-link{color:rgba(255,255,255,.5);font-size:11px;text-decoration:none;border-bottom:1px dashed rgba(255,255,255,.3)}
        a.staff-link:hover{color:#fff}

        /* Search Box and Autocomplete Styles */
        .search-box {
            position: relative;
            display: flex;
            align-items: center;
            width: 100%;
        }
        .search-box input {
            padding-left: 38px;
            width: 100%;
            border-radius: 10px; /* matches normal input border-radius in index.php */
        }
        .search-box .search-icon {
            position: absolute;
            left: 14px;
            color: #7f8c8d;
            font-size: 15px;
            pointer-events: none;
        }
        .autocomplete-list {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: #fff;
            border: 2px solid #e2eaf0;
            border-top: none;
            border-radius: 0 0 10px 10px;
            max-height: 200px;
            overflow-y: auto;
            z-index: 1000;
            box-shadow: 0 10px 30px rgba(0,0,0,0.15);
            display: none;
        }
        .autocomplete-list.open {
            display: block;
        }
        .autocomplete-item {
            padding: 10px 15px;
            cursor: pointer;
            font-size: 14px;
            border-bottom: 1px solid #f0f4f7;
            color: #1e2c38;
            transition: background 0.2s, color 0.2s;
            text-align: left;
        }
        .autocomplete-item:last-child {
            border-bottom: none;
        }
        .autocomplete-item:hover, .autocomplete-item.selected {
            background: #e8f4f8;
            color: #1a5f7a;
        }
        .autocomplete-item strong {
            font-weight: 700;
        }
        .autocomplete-item small {
            font-size: 11.5px;
            color: #7f8c8d;
            display: block;
            margin-top: 2px;
        }

        @media(max-width:600px){.card-body{padding:24px 20px}.card-head{padding:22px 24px}}
    </style>
</head>
<body>

<!-- Top Bar -->
<div class="topbar">
    <div class="topbar-brand">
        <div class="topbar-icon">🏠</div>
        <div>
            <div class="topbar-name">Care Home VMS</div>
            <div class="topbar-sub">Home for the Aged</div>
        </div>
    </div>
    <div class="topbar-clock">
        <div id="clock">--:--:--</div>
        <div class="topbar-date" id="clock-date"></div>
    </div>
</div>

<div class="card">
    <div class="card-head">
        <div class="card-head-icon">✅</div>
        <div>
            <h1>Visitor Check-In</h1>
            <p>Please fill in the form below to register your visit.</p>
        </div>
    </div>

    <div class="card-body">

    <?php if ($success && $confirmation): ?>
        <!-- ── SUCCESS ── -->
        <div class="success-card">
            <div class="success-icon">🎉</div>
            <div class="success-title">Check-In Successful!</div>
            <div class="success-sub">Welcome, <?= e($confirmation['name']) ?>. Your visit has been recorded.</div>

            <!-- QR Code Receipt Panel -->
            <div style="background:linear-gradient(135deg,#0d3d4f,#1a5f7a);color:#fff;border-radius:18px;padding:24px;margin:0 auto 24px;text-align:center;box-shadow:0 8px 24px rgba(26,95,122,0.25);max-width:320px">
                <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:1px;opacity:0.8;margin-bottom:12px">Your Check-Out Code &amp; QR</div>
                <img src="https://api.qrserver.com/v1/create-qr-code/?size=150x150&amp;data=<?= urlencode($confirmation['visit_code']) ?>" alt="QR Code" style="margin-bottom:12px; border:4px solid #fff; border-radius:8px; box-shadow:0 4px 12px rgba(0,0,0,0.15)">
                <div style="font-size:28px;font-weight:900;letter-spacing:1.5px;color:#c8a45e"><?= e($confirmation['visit_code']) ?></div>
                <div style="font-size:11px;opacity:0.75;margin-top:8px;font-weight:600">📸 Please take a photo of this QR / Code to check out later!</div>
            </div>

            <div class="confirm-box">
                <div class="confirm-row">
                    <span class="confirm-icon">🔑</span>
                    <div><div class="confirm-label">Visit Code</div><div class="confirm-value" style="color:var(--primary);font-size:16px"><?= e($confirmation['visit_code']) ?></div></div>
                </div>
                <div class="confirm-row">
                    <span class="confirm-icon">👤</span>
                    <div><div class="confirm-label">Visitor Name</div><div class="confirm-value"><?= e($confirmation['name']) ?></div></div>
                </div>
                <div class="confirm-row">
                    <span class="confirm-icon">👵</span>
                    <div><div class="confirm-label">Visiting Resident</div><div class="confirm-value"><?= e($confirmation['resident']) ?> &nbsp;·&nbsp; Room <?= e($confirmation['room']) ?></div></div>
                </div>
                <div class="confirm-row">
                    <span class="confirm-icon">🎯</span>
                    <div><div class="confirm-label">Purpose</div><div class="confirm-value"><?= e($confirmation['purpose']) ?></div></div>
                </div>
                <div class="confirm-row">
                    <span class="confirm-icon">🕐</span>
                    <div><div class="confirm-label">Check-In Time</div><div class="confirm-value"><?= e($confirmation['time']) ?> · <?= e($confirmation['date']) ?></div></div>
                </div>
                <?php if ($confirmation['companions'] > 0): ?>
                <div class="confirm-row">
                    <span class="confirm-icon">👥</span>
                    <div><div class="confirm-label">Companions</div><div class="confirm-value"><?= $confirmation['companions'] ?> person(s)</div></div>
                </div>
                <?php endif; ?>
            </div>

            <div style="display:flex;justify-content:center;flex-wrap:wrap;gap:10px">
                <button onclick="location.href='checkin.php'" class="btn-new">✅ New Check-In</button>
                <button onclick="printCheckinReceipt('<?= e(addslashes($confirmation['visit_code'])) ?>', '<?= e(addslashes($confirmation['name'])) ?>', '<?= e(addslashes($confirmation['resident'])) ?>', '<?= e(addslashes($confirmation['room'])) ?>', '<?= e(addslashes($confirmation['purpose'])) ?>', '<?= e(addslashes($confirmation['time'] . ' · ' . $confirmation['date'])) ?>', <?= (int)$confirmation['companions'] ?>)" class="btn-new" style="background:#c8a45e;color:#fff;">🖨️ Print Receipt</button>
                <a href="checkout.php" class="btn-checkout">🚪 Go to Check-Out</a>
            </div>

            <p style="margin-top:20px;font-size:13px;color:#7f8c8d;font-weight:600">
                ⚠️ Please remember to check out before leaving.
            </p>
        </div>

    <?php else: ?>
        <!-- ── FORM ── -->

        <?php if ($errors): ?>
        <div class="alert alert-error">
            ❌ Please fix the following:
            <ul><?php foreach($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul>
        </div>
        <?php endif; ?>

        <form method="POST" id="checkin-form" novalidate>

            <p class="section-label">👤 Your Information</p>
            <div class="form-grid">
                <div class="form-group full">
                    <label for="full_name">Full Name <span class="req">*</span></label>
                    <input type="text" id="full_name" name="full_name"
                           value="<?= e(post('full_name')) ?>"
                           placeholder="e.g. Juan dela Cruz" required autofocus>
                </div>
                <div class="form-group">
                    <label for="id_type">ID Type <span class="req">*</span></label>
                    <select id="id_type" name="id_type" required>
                        <option value="">— Select ID Type —</option>
                        <?php foreach (array_keys($idTypesList) as $t): ?>
                        <option value="<?= e($t) ?>" <?= post('id_type') === $t ? 'selected' : '' ?>><?= e($t) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="id_number">ID Number <span class="req">*</span></label>
                    <input type="text" id="id_number" name="id_number"
                           value="<?= e(post('id_number')) ?>"
                           placeholder="Enter ID number" autocomplete="off" required>
                    <div id="id-validation-hint" style="font-size: 11px; margin-top: 5px; font-weight: 700; transition: color 0.2s;"></div>
                </div>
                <div class="form-group">
                    <label for="contact_phone">Contact Number</label>
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

            <p class="section-label">👵 Who Are You Visiting?</p>
            <div class="form-grid">
                <div class="form-group full">
                    <label for="resident_name">Resident's Full Name <span class="req">*</span></label>
                    <div style="display:flex; gap:10px;">
                        <input type="text" id="resident_name" name="resident_name"
                               value="<?= e(post('resident_name')) ?>"
                               placeholder="Enter the resident's full name (e.g. Roberto Santos)" 
                               required style="flex:1;">
                        <button type="button" id="btn-search-resident" 
                                style="padding:11px 20px; border-radius:10px; font-family:inherit; font-size:15px; font-weight:700; background:#1a5f7a; color:#fff; border:none; cursor:pointer; box-shadow:0 4px 10px rgba(26,95,122,0.2); transition: all 0.2s;">
                            🔍 Search
                        </button>
                    </div>
                    <input type="hidden" id="resident_id" name="resident_id" value="<?= e(post('resident_id')) ?>">
                    <div id="resident-validation-hint" style="font-size:12px; margin-top:6px; font-weight:700; display:none;"></div>
                    
                    <!-- Multi-match dropdown selection container -->
                    <div id="multiple-matches-container" style="display:none; margin-top:12px;">
                        <label for="resident_match_select" style="font-size:13px; font-weight:700; color:#5a6a77;">Multiple matches found. Please choose the correct resident: <span class="req">*</span></label>
                        <select id="resident_match_select" style="width:100%; margin-top:6px;">
                            <option value="">— Choose Resident —</option>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label for="relationship">Your Relationship</label>
                    <select id="relationship" name="relationship">
                        <option value="">— Select —</option>
                        <?php foreach (['Son','Daughter','Spouse','Sibling','Grandchild','Nephew/Niece','Friend','Medical Staff','Volunteer','Other'] as $r): ?>
                        <option value="<?= e($r) ?>" <?= post('relationship') === $r ? 'selected' : '' ?>><?= e($r) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div id="relationship_custom_container" style="display: <?= post('relationship') === 'Other' ? 'block' : 'none' ?>; margin-top: 8px;">
                        <label for="relationship_custom" style="font-size:12px; font-weight:700;">Specify Relationship <span class="req">*</span></label>
                        <input type="text" id="relationship_custom" name="relationship_custom" 
                               value="<?= e(post('relationship_custom')) ?>"
                               placeholder="Specify your relationship" 
                               style="width: 100%; margin-top: 4px;">
                    </div>
                </div>
                <div class="form-group">
                    <label for="num_companions">No. of Companions</label>
                    <input type="number" id="num_companions" name="num_companions"
                           min="0" max="20" value="<?= e(post('num_companions', '0')) ?>">
                </div>
            </div>

            <p class="section-label">🎯 Visit Details</p>
            <div class="form-grid">
                <div class="form-group full">
                    <label for="purpose">Purpose of Visit <span class="req">*</span></label>
                    <select id="purpose" name="purpose" required>
                        <option value="">— Select Purpose —</option>
                        <?php foreach (['Regular Family Visit','Medical Checkup','Birthday / Special Occasion','Social/Recreational','Bring Personal Items','Medical Assistance','Official Business','Other'] as $p): ?>
                        <option value="<?= e($p) ?>" <?= post('purpose') === $p ? 'selected' : '' ?>><?= e($p) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div id="purpose_custom_container" style="display: <?= post('purpose') === 'Other' ? 'block' : 'none' ?>; margin-top: 8px;">
                        <label for="purpose_custom" style="font-size:12px; font-weight:700;">Specify Purpose <span class="req">*</span></label>
                        <input type="text" id="purpose_custom" name="purpose_custom" 
                               value="<?= e(post('purpose_custom')) ?>"
                               placeholder="Specify your purpose of visit" 
                               style="width: 100%; margin-top: 4px;">
                    </div>
                </div>
                <div class="form-group full">
                    <label for="notes">Additional Notes <span style="font-weight:400;color:#96a5b0">(optional)</span></label>
                    <textarea id="notes" name="notes" rows="2"
                              placeholder="Any special notes for staff…"><?= e(post('notes')) ?></textarea>
                </div>
            </div>

            <div style="display:flex;gap:12px;margin-top:24px;flex-wrap:wrap">
                <a href="index.php" class="btn-submit" id="cancel-btn" style="flex:1;min-width:180px;background:#e8edf2;color:#5a6a77;box-shadow:none;text-decoration:none;border:2px solid #e2eaf0;font-weight:700">
                    🏠 Back to Home
                </a>
                <button type="submit" class="btn-submit" id="submit-btn" style="flex:2;min-width:240px;margin:0">
                    ✅ Complete Check-In
                </button>
            </div>
        </form>

        <!-- Check-In Confirmation Modal -->
        <div class="modal-overlay" id="checkin-confirm-modal">
            <div class="modal">
                <div class="modal-icon">📋</div>
                <h2>Confirm Your Information</h2>
                <p class="modal-sub">Please review your check-in details before submitting.</p>
                
                <div class="confirm-details-list" id="confirm-modal-details">
                    <!-- Javascript will populate this dynamically -->
                </div>

                <div class="modal-btns">
                    <button type="button" class="btn-modal-cancel" id="btn-modal-cancel">✕ Edit</button>
                    <button type="button" class="btn-modal-confirm" id="btn-modal-confirm">✅ Yes, Submit</button>
                </div>
            </div>
        </div>
    <?php endif; ?>

    </div><!-- /card-body -->
</div><!-- /card -->

<div class="footer-note">
    Need to leave? <a href="checkout.php" class="staff-link">🚪 Visitor Check-Out</a>
    &nbsp;·&nbsp;
    <a href="index.php" class="staff-link">🏠 Kiosk Home</a>
    &nbsp;·&nbsp;
    <a href="login.php" class="staff-link">Staff Login</a>
</div>

<script src="assets/js/main.js"></script>
<script>
function updateClock() {
    const now = new Date();
    const t = now.toLocaleTimeString('en-PH', {hour:'2-digit',minute:'2-digit',second:'2-digit'});
    const d = now.toLocaleDateString('en-PH', {weekday:'long',month:'long',day:'numeric',year:'numeric'});
    document.getElementById('clock').textContent = t;
    document.getElementById('clock-date').textContent = d;
}
setInterval(updateClock, 1000);
updateClock();

const idTypesConfig = {
    'National ID': {
        placeholder: 'e.g. 1234-5678-9012-3456',
        hint: '16-digit PhilID card number (XXXX-XXXX-XXXX-XXXX)',
        regex: /^\d{4}-\d{4}-\d{4}-\d{4}$/,
        format: (val) => {
            const digits = val.replace(/\D/g, '').slice(0, 16);
            const chunks = [];
            for (let i = 0; i < digits.length; i += 4) {
                chunks.push(digits.substring(i, i + 4));
            }
            return chunks.join('-');
        }
    },
    'Passport': {
        placeholder: 'e.g. P1234567A',
        hint: 'Passport (e.g. P/EB followed by 7 digits and optional letter)',
        regex: /^[A-Z]{1,2}\d{7}[A-Z]?$/i,
        format: (val) => {
            return val.toUpperCase().replace(/[^A-Z0-9]/g, '').slice(0, 10);
        }
    },
    "Driver's License": {
        placeholder: 'e.g. N12-34-567890',
        hint: 'Driver\'s License: L00-00-000000',
        regex: /^[A-Z]\d{2}-\d{2}-\d{6}$/i,
        format: (val) => {
            const clean = val.replace(/[^A-Za-z0-9]/g, '').slice(0, 9);
            let formatted = '';
            if (clean.length > 0) {
                formatted += clean[0].toUpperCase();
            }
            if (clean.length > 1) {
                formatted += clean.substring(1, 3).replace(/\D/g, '');
            }
            if (clean.length > 3) {
                formatted += '-' + clean.substring(3, 5).replace(/\D/g, '');
            }
            if (clean.length > 5) {
                formatted += '-' + clean.substring(5, 11).replace(/\D/g, '');
            }
            return formatted;
        }
    },
    'Student ID': {
        placeholder: 'e.g. 2024-12345',
        hint: 'Student ID: YYYY-NNNNN',
        regex: /^\d{4}-\d{5,6}$/,
        format: (val) => {
            const digits = val.replace(/\D/g, '').slice(0, 10);
            if (digits.length > 4) {
                return digits.substring(0, 4) + '-' + digits.substring(4);
            }
            return digits;
        }
    },
    'UMID': {
        placeholder: 'e.g. 1234-5678901-2',
        hint: 'UMID: 0000-0000000-0',
        regex: /^\d{4}-\d{7}-\d$/,
        format: (val) => {
            const digits = val.replace(/\D/g, '').slice(0, 12);
            let formatted = '';
            if (digits.length > 0) {
                formatted += digits.substring(0, 4);
            }
            if (digits.length > 4) {
                formatted += '-' + digits.substring(4, 11);
            }
            if (digits.length > 11) {
                formatted += '-' + digits.substring(11, 12);
            }
            return formatted;
        }
    },
    'SSS ID': {
        placeholder: 'e.g. 12-3456789-0',
        hint: 'SSS: 00-0000000-0',
        regex: /^\d{2}-\d{7}-\d$/,
        format: (val) => {
            const digits = val.replace(/\D/g, '').slice(0, 10);
            let formatted = '';
            if (digits.length > 0) {
                formatted += digits.substring(0, 2);
            }
            if (digits.length > 2) {
                formatted += '-' + digits.substring(2, 9);
            }
            if (digits.length > 9) {
                formatted += '-' + digits.substring(9, 10);
            }
            return formatted;
        }
    },
    'TIN': {
        placeholder: 'e.g. 123-456-789-000',
        hint: 'TIN: 000-000-000-000',
        regex: /^\d{3}-\d{3}-\d{3}-\d{3,5}$/,
        format: (val) => {
            const digits = val.replace(/\D/g, '').slice(0, 14);
            let formatted = '';
            if (digits.length > 0) {
                formatted += digits.substring(0, 3);
            }
            if (digits.length > 3) {
                formatted += '-' + digits.substring(3, 6);
            }
            if (digits.length > 6) {
                formatted += '-' + digits.substring(6, 9);
            }
            if (digits.length > 9) {
                formatted += '-' + digits.substring(9);
            }
            return formatted;
        }
    },
    'PhilHealth ID': {
        placeholder: 'e.g. 12-345678901-2',
        hint: 'PhilHealth: 00-000000000-0',
        regex: /^\d{2}-\d{9}-\d$/,
        format: (val) => {
            const digits = val.replace(/\D/g, '').slice(0, 12);
            let formatted = '';
            if (digits.length > 0) {
                formatted += digits.substring(0, 2);
            }
            if (digits.length > 2) {
                formatted += '-' + digits.substring(2, 11);
            }
            if (digits.length > 11) {
                formatted += '-' + digits.substring(11, 12);
            }
            return formatted;
        }
    },
    'Senior Citizen ID': {
        placeholder: 'e.g. 12-3456',
        hint: 'Senior Citizen ID (4-12 alphanumeric/dashes)',
        regex: /^[A-Z0-9-]{4,12}$/i,
        format: (val) => {
            return val.toUpperCase().replace(/[^A-Z0-9-]/g, '').slice(0, 12);
        }
    },
    'Other': {
        placeholder: 'Enter ID number',
        hint: 'Min 4 characters',
        regex: /^.{4,30}$/,
        format: (val) => val.slice(0, 30)
    }
};

const idTypeSelect = document.getElementById('id_type');
const idNumberInput = document.getElementById('id_number');
const validationHint = document.getElementById('id-validation-hint');
const submitBtn = document.getElementById('submit-btn');

function validateID() {
    if (!idTypeSelect || !idNumberInput || !validationHint) return true;
    const selectedType = idTypeSelect.value;
    const value = idNumberInput.value.trim();

    if (!selectedType) {
        idNumberInput.placeholder = 'Enter ID number';
        validationHint.textContent = '';
        idNumberInput.style.borderColor = '';
        return true;
    }

    const config = idTypesConfig[selectedType];
    if (!config) return true;

    idNumberInput.placeholder = config.placeholder;

    if (!value) {
        validationHint.textContent = `Format: ${config.hint}`;
        validationHint.style.color = 'rgba(255, 255, 255, 0.7)';
        idNumberInput.style.borderColor = '';
        return false;
    }

    const isValid = config.regex.test(value);
    if (isValid) {
        validationHint.textContent = `✓ Valid ${selectedType} format`;
        validationHint.style.color = '#a7f3d0';
        idNumberInput.style.borderColor = '#10b981';
    } else {
        validationHint.textContent = `⚠️ Invalid format. Expected: ${config.hint}`;
        validationHint.style.color = '#fca5a5';
        idNumberInput.style.borderColor = '#ef4444';
    }
    return isValid;
}

if (idTypeSelect && idNumberInput) {
    idTypeSelect.addEventListener('change', () => {
        idNumberInput.value = '';
        validateID();
    });

    idNumberInput.addEventListener('input', (e) => {
        const selectedType = idTypeSelect.value;
        const config = idTypesConfig[selectedType];
        if (config && config.format) {
            const start = e.target.selectionStart;
            const prevLen = e.target.value.length;
            e.target.value = config.format(e.target.value);
            const postLen = e.target.value.length;
            
            if (start !== null) {
                e.target.setSelectionRange(start + (postLen - prevLen), start + (postLen - prevLen));
            }
        }
        validateID();
    });

    validateID();

    const btnSearchResident = document.getElementById('btn-search-resident');
    const residentNameInput = document.getElementById('resident_name');
    const residentIdInput = document.getElementById('resident_id');
    const residentValidationHint = document.getElementById('resident-validation-hint');
    const multiMatchContainer = document.getElementById('multiple-matches-container');
    const multiMatchSelect = document.getElementById('resident_match_select');

    async function searchResident() {
        const name = residentNameInput ? residentNameInput.value.trim() : '';
        if (!name || name.length < 3) {
            showResidentValidation('Please enter at least 3 characters to search.', 'error');
            resetMultiMatches();
            return false;
        }
        
        showResidentValidation('🔍 Searching...', 'info');
        resetMultiMatches();
        
        try {
            const response = await fetch(`api/verify_resident.php?name=${encodeURIComponent(name)}`);
            const data = await response.json();
            
            if (data && data.exists) {
                if (data.match_type === 'single') {
                    residentIdInput.value = data.id;
                    residentNameInput.value = data.full_name;
                    residentNameInput.dataset.fullName = data.full_name;
                    residentNameInput.dataset.roomNumber = data.room_number;
                    showResidentValidation(`✓ Resident found: ${data.full_name} (Room ${data.room_number})`, 'success');
                    return true;
                } else if (data.match_type === 'multiple') {
                    // Populate multi-match select
                    multiMatchSelect.innerHTML = '<option value="">— Choose Resident —</option>';
                    data.matches.forEach(m => {
                        const opt = document.createElement('option');
                        opt.value = m.id;
                        opt.textContent = `${m.full_name} (Room ${m.room_number})`;
                        opt.dataset.fullName = m.full_name;
                        opt.dataset.roomNumber = m.room_number;
                        multiMatchSelect.appendChild(opt);
                    });
                    
                    residentIdInput.value = '';
                    multiMatchContainer.style.display = 'block';
                    showResidentValidation('⚠️ Multiple matching residents found. Please choose from the options.', 'info');
                    return false;
                }
            } else {
                residentIdInput.value = '';
                residentNameInput.removeAttribute('data-full-name');
                residentNameInput.removeAttribute('data-room-number');
                showResidentValidation(data.message || '❌ No active resident exists with that name. Please check spelling.', 'error');
                return false;
            }
        } catch (e) {
            showResidentValidation('⚠️ Error connecting to verification service.', 'error');
            return false;
        }
    }

    function resetMultiMatches() {
        if (multiMatchContainer) multiMatchContainer.style.display = 'none';
        if (multiMatchSelect) multiMatchSelect.innerHTML = '<option value="">— Choose Resident —</option>';
    }

    function showResidentValidation(msg, type) {
        if (!residentValidationHint) return;
        residentValidationHint.textContent = msg;
        residentValidationHint.style.display = 'block';
        if (type === 'success') {
            residentValidationHint.style.color = '#10b981';
        } else if (type === 'error') {
            residentValidationHint.style.color = '#ef4444';
        } else {
            residentValidationHint.style.color = '#1a5f7a';
        }
    }

    if (btnSearchResident) {
        btnSearchResident.addEventListener('click', searchResident);
    }

    if (residentNameInput) {
        residentNameInput.addEventListener('input', () => {
            if (residentIdInput) residentIdInput.value = '';
            residentNameInput.removeAttribute('data-full-name');
            residentNameInput.removeAttribute('data-room-number');
            resetMultiMatches();
            if (residentValidationHint) {
                residentValidationHint.style.display = 'none';
            }
        });
    }

    if (multiMatchSelect) {
        multiMatchSelect.addEventListener('change', () => {
            const opt = multiMatchSelect.options[multiMatchSelect.selectedIndex];
            if (opt && opt.value) {
                residentIdInput.value = opt.value;
                residentNameInput.value = opt.dataset.fullName;
                residentNameInput.dataset.fullName = opt.dataset.fullName;
                residentNameInput.dataset.roomNumber = opt.dataset.roomNumber;
                showResidentValidation(`✓ Selected: ${opt.dataset.fullName} (Room ${opt.dataset.roomNumber})`, 'success');
            } else {
                residentIdInput.value = '';
                residentNameInput.removeAttribute('data-full-name');
                residentNameInput.removeAttribute('data-room-number');
                showResidentValidation('⚠️ Please choose a resident from the matching list.', 'info');
            }
        });
    }

    let isConfirmed = false;
    const form = document.getElementById('checkin-form');
    form?.addEventListener('submit', async function(e) {
        if (isConfirmed) {
            return; // let form submit go through
        }
        
        e.preventDefault(); // Intercept initial submit

        // First validate ID
        if (!validateID()) {
            alert('Please enter a valid ID Number matching the selected ID Type layout.');
            return;
        }

        // Validate resident lookup
        const residentId = residentIdInput ? residentIdInput.value : '';
        if (!residentId) {
            // Check if there are options they haven't selected yet
            if (multiMatchSelect && multiMatchSelect.options.length > 1 && !multiMatchSelect.value) {
                alert('Please select a resident from the matching options dropdown.');
                multiMatchSelect.focus();
                return;
            }
            
            // Run search dynamically first
            const verified = await searchResident();
            if (!verified) {
                // If it opened options, don't submit yet
                if (multiMatchSelect && multiMatchSelect.options.length > 1) {
                    return;
                }
                alert('Please enter a valid active resident name before completing check-in.');
                return;
            }
        }

        // Check overall form validity (required fields, etc.)
        if (!form.checkValidity()) {
            form.reportValidity();
            return;
        }

        // Populate and open confirmation modal
        const detailsContainer = document.getElementById('confirm-modal-details');
        if (detailsContainer) {
            // Gather values
            const name = document.getElementById('full_name').value.trim();
            const idType = document.getElementById('id_type').value;
            const idNo = document.getElementById('id_number').value.trim();
            const phone = document.getElementById('contact_phone').value.trim() || '—';
            
            const province = document.getElementById('province').value.trim();
            const city = document.getElementById('city').value.trim();
            const barangay = document.getElementById('barangay').value.trim();
            const street = document.getElementById('address').value.trim();
            const address = [street, barangay, city, province].filter(Boolean).join(', ') || '—';
            
            const rName = residentNameInput.dataset.fullName || residentNameInput.value.trim();
            const rRoom = residentNameInput.dataset.roomNumber || '—';
            const resident = `${rName} — Room ${rRoom}`;
            
            const relSelect = document.getElementById('relationship');
            let relationship = relSelect.value;
            if (relationship === 'Other') {
                relationship = document.getElementById('relationship_custom').value.trim() || 'Other';
            }
            relationship = relationship || '—';
            
            const companions = document.getElementById('num_companions').value || '0';
            
            const purpSelect = document.getElementById('purpose');
            let purpose = purpSelect.value;
            if (purpose === 'Other') {
                purpose = document.getElementById('purpose_custom').value.trim() || 'Other';
            }
            purpose = purpose || '—';
            
            const notes = document.getElementById('notes').value.trim() || '—';

            // Build html
            detailsContainer.innerHTML = `
                <div class="confirm-detail-item">
                    <span class="confirm-detail-label">Full Name</span>
                    <span class="confirm-detail-value">${escapeHtml(name)}</span>
                </div>
                <div class="confirm-detail-item">
                    <span class="confirm-detail-label">ID Type</span>
                    <span class="confirm-detail-value">${escapeHtml(idType)}</span>
                </div>
                <div class="confirm-detail-item">
                    <span class="confirm-detail-label">ID Number</span>
                    <span class="confirm-detail-value">${escapeHtml(idNo)}</span>
                </div>
                <div class="confirm-detail-item">
                    <span class="confirm-detail-label">Contact Phone</span>
                    <span class="confirm-detail-value">${escapeHtml(phone)}</span>
                </div>
                <div class="confirm-detail-item">
                    <span class="confirm-detail-label">Address</span>
                    <span class="confirm-detail-value">${escapeHtml(address)}</span>
                </div>
                <div class="confirm-detail-item" style="border-top: 2px solid #e2eaf0; margin-top: 8px; padding-top: 12px;">
                    <span class="confirm-detail-label">Visiting Resident</span>
                    <span class="confirm-detail-value">${escapeHtml(resident)}</span>
                </div>
                <div class="confirm-detail-item">
                    <span class="confirm-detail-label">Relationship</span>
                    <span class="confirm-detail-value">${escapeHtml(relationship)}</span>
                </div>
                <div class="confirm-detail-item">
                    <span class="confirm-detail-label">Companions</span>
                    <span class="confirm-detail-value">${escapeHtml(companions)}</span>
                </div>
                <div class="confirm-detail-item">
                    <span class="confirm-detail-label">Purpose of Visit</span>
                    <span class="confirm-detail-value">${escapeHtml(purpose)}</span>
                </div>
                <div class="confirm-detail-item">
                    <span class="confirm-detail-label">Notes</span>
                    <span class="confirm-detail-value">${escapeHtml(notes)}</span>
                </div>
            `;
        }

        // Open modal
        const modal = document.getElementById('checkin-confirm-modal');
        if (modal) {
            modal.classList.add('open');
            document.body.style.overflow = 'hidden';
        }
    });

    function escapeHtml(string) {
        return String(string).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    // Modal buttons wiring
    document.getElementById('btn-modal-cancel')?.addEventListener('click', () => {
        const modal = document.getElementById('checkin-confirm-modal');
        if (modal) {
            modal.classList.remove('open');
            document.body.style.overflow = '';
        }
    });

    document.getElementById('btn-modal-confirm')?.addEventListener('click', () => {
        isConfirmed = true;
        const modal = document.getElementById('checkin-confirm-modal');
        if (modal) {
            modal.classList.remove('open');
            document.body.style.overflow = '';
        }
        
        // Show processing state on main submit button
        const btn = document.getElementById('submit-btn');
        if (btn) {
            btn.innerHTML = '<span class="spinner"></span> Processing…';
            btn.disabled = true;
        }
        
        form.submit();
    });
}

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

function printCheckinReceipt(visitCode, visitorName, residentName, residentRoom, purpose, dateTime, companions) {
    const printWindow = window.open('', '_blank', 'width=400,height=600');
    if (!printWindow) {
        alert('Please allow popups to print the receipt.');
        return;
    }
    printWindow.document.write(`
        <html>
        <head>
            <title>Check-In Receipt - ${visitCode}</title>
            <style>
                body {
                    font-family: 'Nunito', 'Segoe UI', Arial, sans-serif;
                    padding: 20px;
                    color: #1e2c38;
                    max-width: 280px;
                    margin: 0 auto;
                    text-align: center;
                }
                .logo { font-size: 28px; margin-bottom: 4px; }
                .title { font-size: 16px; font-weight: 900; margin-bottom: 2px; color: #0d3d4f; }
                .subtitle { font-size: 11px; color: #5a6a77; margin-bottom: 12px; font-weight: 600; }
                .divider { border-top: 1px dashed #cbd5e1; margin: 12px 0; }
                .qr-container { margin: 16px 0; }
                .qr-code-img { border: 1px solid #e2eaf0; padding: 4px; border-radius: 6px; }
                .visit-code { font-size: 22px; font-weight: 900; color: #1a5f7a; letter-spacing: 1px; margin-top: 8px; }
                .details { text-align: left; font-size: 12px; line-height: 1.5; color: #1e2c38; }
                .detail-row { display: flex; justify-content: space-between; margin-bottom: 5px; }
                .detail-label { font-weight: 700; color: #5a6a77; }
                .detail-value { font-weight: 800; text-align: right; }
                .footer { font-size: 10px; color: #7f8c8d; margin-top: 20px; line-height: 1.4; font-weight: 600; }
            </style>
        </head>
        <body>
            <div class="logo">🏠</div>
            <div class="title">CARE HOME VMS</div>
            <div class="subtitle">Home for the Aged</div>
            <div class="divider"></div>
            <div class="title" style="font-size: 14px; color: #10b981; margin-bottom: 8px;">VISITOR CHECK-IN SLIP</div>
            <div class="qr-container">
                <img src="https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=${encodeURIComponent(visitCode)}" class="qr-code-img">
                <div class="visit-code">${visitCode}</div>
            </div>
            <div class="divider"></div>
            <div class="details">
                <div class="detail-row">
                    <span class="detail-label">Visitor Name:</span>
                    <span class="detail-value">${visitorName}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Resident:</span>
                    <span class="detail-value">${residentName}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Room Number:</span>
                    <span class="detail-value">Room ${residentRoom}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Purpose:</span>
                    <span class="detail-value">${purpose}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Date / Time:</span>
                    <span class="detail-value">${dateTime}</span>
                </div>
                ${companions > 0 ? `
                <div class="detail-row">
                    <span class="detail-label">Companions:</span>
                    <span class="detail-value">${companions} person(s)</span>
                </div>` : ''}
            </div>
            <div class="divider"></div>
            <div class="footer">
                Please take a photo of this slip.<br>
                Scan the QR code or enter the Visit Code at the checkout terminal when leaving.<br>
                Thank you!
            </div>
            <script>
                window.onload = function() {
                    window.print();
                    setTimeout(function() { window.close(); }, 500);
                };
            <\/script>
        </body>
        </html>
    `);
    printWindow.document.close();
}
</script>
</body>
</html>
