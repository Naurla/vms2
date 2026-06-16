<?php
/**
 * public/checkin.php – Public Visitor Self-Service Check-In Kiosk
 * No authentication required.
 */
define('BASE_PATH', '../');
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

if (session_status() === PHP_SESSION_NONE) session_start();

$db = getDB();

// Auto-create a system kiosk user for the FK constraint (is_active=0 so it can never login)
$kioskUserId = $db->query("SELECT id FROM users WHERE username = '__kiosk__' LIMIT 1")->fetchColumn();
if (!$kioskUserId) {
    $db->prepare("INSERT INTO users (username, password, full_name, role, is_active) VALUES ('__kiosk__', ?, 'Visitor Self Check-In', 'receptionist', 0)")
       ->execute([password_hash('KIOSK_NO_ACCESS_' . uniqid(), PASSWORD_BCRYPT)]);
    $kioskUserId = (int)$db->lastInsertId();
}
$kioskUserId = (int)$kioskUserId;

// Active residents for dropdown
$residents = $db->query("SELECT id, full_name, room_number FROM residents WHERE status='Active' ORDER BY full_name")->fetchAll();

$errors       = [];
$success      = false;
$confirmation = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName      = post('full_name');
    $idType        = post('id_type');
    $idNumber      = post('id_number');
    $phone         = post('contact_phone');
    $address       = post('address');
    $residentId    = (int)post('resident_id');
    $relationship  = post('relationship');
    $purpose       = post('purpose');
    $numCompanions = max(0, (int)post('num_companions', '0'));
    $notes         = post('notes');

    if (!$fullName)   $errors[] = 'Your full name is required.';
    if (!$idType)     $errors[] = 'Please select your ID type.';
    if (!$idNumber)   $errors[] = 'Your ID number is required.';
    if (!$residentId) $errors[] = 'Please select the resident you are visiting.';
    if (!$purpose)    $errors[] = 'Please select your purpose of visit.';

    if (!$errors) {
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
        $db->prepare("
            INSERT INTO visit_logs
                (visitor_id, resident_id, relationship, purpose, num_companions, check_in_time, checked_in_by, notes)
            VALUES (?, ?, ?, ?, ?, NOW(), ?, ?)
        ")->execute([$visitorId, $residentId, $relationship, $purpose, $numCompanions, $kioskUserId, $notes]);

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

        .footer-note{color:rgba(255,255,255,.55);font-size:12px;font-weight:600;margin-top:20px;text-align:center}
        a.staff-link{color:rgba(255,255,255,.5);font-size:11px;text-decoration:none;border-bottom:1px dashed rgba(255,255,255,.3)}
        a.staff-link:hover{color:#fff}

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

            <div class="confirm-box">
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
                <button onclick="location.reload()" class="btn-new">✅ New Check-In</button>
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
                        <?php foreach (['National ID', 'Passport', "Driver's License", 'Senior Citizen ID', 'Other'] as $t): ?>
                        <option value="<?= e($t) ?>" <?= post('id_type') === $t ? 'selected' : '' ?>><?= e($t) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="id_number">ID Number <span class="req">*</span></label>
                    <input type="text" id="id_number" name="id_number"
                           value="<?= e(post('id_number')) ?>"
                           placeholder="e.g. 1234-5678-90">
                </div>
                <div class="form-group">
                    <label for="contact_phone">Contact Number</label>
                    <input type="tel" id="contact_phone" name="contact_phone"
                           value="<?= e(post('contact_phone')) ?>"
                           placeholder="e.g. 09171234567">
                </div>
                <div class="form-group">
                    <label for="address">Home Address</label>
                    <input type="text" id="address" name="address"
                           value="<?= e(post('address')) ?>"
                           placeholder="Barangay, City">
                </div>
            </div>

            <p class="section-label">👵 Who Are You Visiting?</p>
            <div class="form-grid">
                <div class="form-group full">
                    <label for="resident_id">Resident Name &amp; Room <span class="req">*</span></label>
                    <select id="resident_id" name="resident_id" required>
                        <option value="">— Select Resident —</option>
                        <?php foreach ($residents as $res): ?>
                        <option value="<?= $res['id'] ?>" <?= (int)post('resident_id') === (int)$res['id'] ? 'selected' : '' ?>>
                            <?= e($res['full_name']) ?> — Room <?= e($res['room_number']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="relationship">Your Relationship</label>
                    <select id="relationship" name="relationship">
                        <option value="">— Select —</option>
                        <?php foreach (['Son','Daughter','Spouse','Sibling','Grandchild','Nephew/Niece','Friend','Medical Staff','Volunteer','Other'] as $r): ?>
                        <option value="<?= e($r) ?>" <?= post('relationship') === $r ? 'selected' : '' ?>><?= e($r) ?></option>
                        <?php endforeach; ?>
                    </select>
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
                </div>
                <div class="form-group full">
                    <label for="notes">Additional Notes <span style="font-weight:400;color:#96a5b0">(optional)</span></label>
                    <textarea id="notes" name="notes" rows="2"
                              placeholder="Any special notes for staff…"><?= e(post('notes')) ?></textarea>
                </div>
            </div>

            <button type="submit" class="btn-submit" id="submit-btn">
                ✅ Complete Check-In
            </button>
        </form>
    <?php endif; ?>

    </div><!-- /card-body -->
</div><!-- /card -->

<div class="footer-note">
    Need to leave? <a href="checkout.php" class="staff-link">🚪 Visitor Check-Out</a>
    &nbsp;·&nbsp;
    <a href="../index.php" class="staff-link">Staff Login</a>
</div>

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

document.getElementById('checkin-form')?.addEventListener('submit', function() {
    const btn = document.getElementById('submit-btn');
    btn.innerHTML = '<span class="spinner"></span> Processing…';
    btn.disabled = true;
});
</script>
</body>
</html>
