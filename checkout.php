<?php
/**
 * checkout.php – Public Visitor Self-Service Check-Out Kiosk
 * No authentication required.
 */
define('BASE_PATH', './');
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

if (session_status() === PHP_SESSION_NONE) session_start();

$db = getDB();

// Get kiosk system user ID (same as check-in)
$kioskUserId = (int)$db->query("SELECT id FROM users WHERE email = 'kiosk@system.local' LIMIT 1")->fetchColumn();
if (!$kioskUserId) {
    $db->prepare("INSERT INTO users (email, password, full_name, role, is_active) VALUES ('kiosk@system.local', ?, 'Visitor Self Check-In', 'receptionist', 0)")
       ->execute([password_hash('KIOSK_' . uniqid(), PASSWORD_BCRYPT)]);
    $kioskUserId = (int)$db->lastInsertId();
}

$success      = false;
$farewell     = null;

// Handle checkout POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $checkoutSuccess = false;
    $log = null;
    $logId = null;

    if (isset($_POST['log_id'])) {
        $logId    = (int)$_POST['log_id'];
        $verifyId = trim($_POST['verify_id'] ?? '');

        // Fetch log entry and verify by ID number or visit code
        $log = $db->prepare("
            SELECT vl.*, v.full_name AS visitor_name, v.id_number,
                   r.full_name AS resident_name, r.room_number
            FROM visit_logs vl
            JOIN visitors v ON v.id = vl.visitor_id
            JOIN residents r ON r.id = vl.resident_id
            WHERE vl.id = ? AND vl.status = 'Checked In'
        ");
        $log->execute([$logId]);
        $log = $log->fetch();

        if ($log) {
            $dbCode = strtolower(trim($log['visit_code'] ?? ''));
            $dbId = strtolower(trim($log['id_number'] ?? ''));
            $inputCode = strtolower($verifyId);

            if (($dbCode !== '' && $dbCode === $inputCode) || $dbId === $inputCode) {
                $checkoutSuccess = true;
            } else {
                $_SESSION['co_error'] = 'Check-Out Code or ID number does not match. Please try again.';
                header('Location: checkout.php');
                exit;
            }
        } else {
            $_SESSION['co_error'] = 'Active check-in not found. Please try again.';
            header('Location: checkout.php');
            exit;
        }
    } elseif (isset($_POST['visit_code'])) {
        $verifyId = trim($_POST['visit_code'] ?? '');

        // Fetch log entry by visit code or ID number
        $log = $db->prepare("
            SELECT vl.*, v.full_name AS visitor_name, v.id_number,
                   r.full_name AS resident_name, r.room_number
            FROM visit_logs vl
            JOIN visitors v ON v.id = vl.visitor_id
            JOIN residents r ON r.id = vl.resident_id
            WHERE (vl.visit_code = ? OR v.id_number = ?) AND vl.status = 'Checked In'
            LIMIT 1
        ");
        $log->execute([$verifyId, $verifyId]);
        $log = $log->fetch();

        if ($log) {
            $checkoutSuccess = true;
            $logId = $log['id'];
        } else {
            $_SESSION['co_error'] = 'Active check-in not found for the code: ' . htmlspecialchars($verifyId);
            header('Location: checkout.php');
            exit;
        }
    }

    if ($checkoutSuccess && $log && $logId) {
        $checkIn   = new DateTime($log['check_in_time']);
        $checkOut  = new DateTime();
        $duration  = (int)round(($checkOut->getTimestamp() - $checkIn->getTimestamp()) / 60);

        $db->prepare("
            UPDATE visit_logs
            SET status='Checked Out', check_out_time=NOW(),
                checked_out_by=?, duration_minutes=?
            WHERE id=?
        ")->execute([$kioskUserId, $duration, $logId]);

        $farewell = [
            'name'     => $log['visitor_name'],
            'resident' => $log['resident_name'],
            'duration' => $duration,
            'time'     => date('h:i A'),
        ];
        $success = true;
    }
}

$coError = $_SESSION['co_error'] ?? null;
unset($_SESSION['co_error']);

// Fetch all currently checked-in visitors
$checkedIn = $db->query("
    SELECT vl.id, vl.check_in_time, vl.visit_code,
           v.full_name AS visitor_name,
           TIMESTAMPDIFF(MINUTE, vl.check_in_time, NOW()) AS elapsed,
           r.full_name AS resident_name, r.room_number
    FROM visit_logs vl
    JOIN visitors v ON v.id = vl.visitor_id
    JOIN residents r ON r.id = vl.resident_id
    WHERE vl.status = 'Checked In'
    ORDER BY vl.check_in_time ASC
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Visitor Check-Out – Care Home for the Aged">
    <title>Visitor Check-Out – Care Home VMS</title>
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>
    <style>
        *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
        body{
            font-family:'Nunito',sans-serif;min-height:100vh;
            background:linear-gradient(160deg,#065f46 0%,#10b981 55%,#c8a45e 100%);
            background-size:400% 400%;animation:bgShift 18s ease infinite;
            display:flex;flex-direction:column;align-items:center;padding:24px 16px;
        }
        @keyframes bgShift{0%{background-position:0% 50%}50%{background-position:100% 50%}100%{background-position:0% 50%}}

        .topbar{width:100%;max-width:860px;display:flex;align-items:center;justify-content:space-between;margin-bottom:24px;padding:0 4px}
        .topbar-brand{display:flex;align-items:center;gap:12px;color:#fff}
        .topbar-icon{width:48px;height:48px;background:rgba(255,255,255,.2);border-radius:14px;display:flex;align-items:center;justify-content:center;font-size:24px;border:2px solid rgba(255,255,255,.3)}
        .topbar-name{font-size:18px;font-weight:900;line-height:1}
        .topbar-sub{font-size:12px;opacity:.75;font-weight:600}
        .topbar-clock{color:#fff;text-align:right;font-weight:800;font-size:15px;opacity:.9}
        .topbar-date{font-size:11px;opacity:.65;font-weight:600}

        .card{background:#fff;border-radius:24px;width:100%;max-width:860px;box-shadow:0 24px 70px rgba(0,0,0,.28);overflow:hidden;animation:cardIn .5s ease}
        @keyframes cardIn{from{opacity:0;transform:translateY(24px)}to{opacity:1;transform:translateY(0)}}

        .card-head{background:linear-gradient(135deg,#065f46,#10b981);padding:28px 36px;color:#fff;display:flex;align-items:center;gap:16px}
        .card-head-icon{font-size:36px}
        .card-head h1{font-size:24px;font-weight:900;margin-bottom:4px}
        .card-head p{font-size:13px;opacity:.8;font-weight:600}

        .card-body{padding:36px}

        .alert{padding:14px 18px;border-radius:12px;margin-bottom:20px;font-size:14px;font-weight:700;border-left:4px solid}
        .alert-error{background:#fee2e2;color:#991b1b;border-color:#ef4444}

        /* Visitor list */
        .visitors-list{display:flex;flex-direction:column;gap:14px}
        .visitor-row{
            background:#f7f9fb;border:2px solid #e2eaf0;border-radius:16px;
            padding:18px 22px;display:flex;align-items:center;gap:18px;
            transition:border-color .2s,box-shadow .2s;
        }
        .visitor-row:hover{border-color:#10b981;box-shadow:0 4px 16px rgba(16,185,129,.15)}
        .visitor-avatar{
            width:50px;height:50px;border-radius:50%;
            background:linear-gradient(135deg,#d1fae5,#10b981);
            display:flex;align-items:center;justify-content:center;
            font-size:20px;font-weight:900;color:#065f46;flex-shrink:0;
        }
        .visitor-info{flex:1;min-width:0}
        .visitor-name{font-size:17px;font-weight:800;color:#1e2c38}
        .visitor-sub{font-size:13px;color:#7f8c8d;font-weight:600;margin-top:3px}
        .visitor-time{
            text-align:right;flex-shrink:0;
        }
        .time-badge{
            display:inline-block;padding:5px 12px;border-radius:20px;
            font-size:13px;font-weight:800;margin-bottom:8px;
        }
        .time-ok{background:#d1fae5;color:#065f46}
        .time-long{background:#fef3c7;color:#92400e}
        .time-very-long{background:#fee2e2;color:#991b1b}
        .btn-checkout{
            background:linear-gradient(135deg,#065f46,#10b981);
            color:#fff;border:none;border-radius:10px;
            padding:10px 18px;font-family:inherit;font-size:14px;font-weight:800;
            cursor:pointer;transition:transform .15s,box-shadow .15s;
            box-shadow:0 4px 12px rgba(16,185,129,.35);
        }
        .btn-checkout:hover{transform:translateY(-2px);box-shadow:0 6px 18px rgba(16,185,129,.5)}

        /* Modal */
        .modal-overlay{position:fixed;inset:0;background:rgba(0,0,0,.6);z-index:100;display:none;align-items:center;justify-content:center;padding:20px}
        .modal-overlay.open{display:flex}
        .modal{background:#fff;border-radius:20px;padding:36px;max-width:440px;width:100%;box-shadow:0 20px 60px rgba(0,0,0,.25);animation:mIn .25s ease}
        @keyframes mIn{from{opacity:0;transform:scale(.95)}to{opacity:1;transform:scale(1)}}
        .modal-icon{font-size:48px;text-align:center;margin-bottom:14px}
        .modal h2{font-size:20px;font-weight:900;color:#1e2c38;text-align:center;margin-bottom:6px}
        .modal-sub{font-size:14px;color:#7f8c8d;font-weight:600;text-align:center;margin-bottom:22px}
        .modal label{font-size:13px;font-weight:700;color:#5a6a77;display:block;margin-bottom:6px}
        .modal input{width:100%;padding:12px 15px;border:2px solid #e2eaf0;border-radius:10px;font-family:inherit;font-size:15px;outline:none}
        .modal input:focus{border-color:#10b981;box-shadow:0 0 0 3px rgba(16,185,129,.15)}
        .modal-btns{display:flex;gap:10px;margin-top:22px}
        .btn-confirm{flex:1;padding:12px;background:linear-gradient(135deg,#065f46,#10b981);color:#fff;border:none;border-radius:10px;font-family:inherit;font-size:15px;font-weight:800;cursor:pointer}
        .btn-cancel{flex:1;padding:12px;background:#e8edf2;color:#5a6a77;border:none;border-radius:10px;font-family:inherit;font-size:15px;font-weight:700;cursor:pointer}
        .btn-cancel:hover{background:#dce4ec}

        /* Success */
        .success-card{text-align:center;padding:20px 0 10px}
        .success-icon{font-size:72px;margin-bottom:16px;animation:popIn .5s ease}
        @keyframes popIn{from{transform:scale(0)}to{transform:scale(1)}}
        .success-title{font-size:26px;font-weight:900;color:#065f46;margin-bottom:6px}
        .success-sub{font-size:15px;color:#7f8c8d;font-weight:600;margin-bottom:28px}
        .farewell-box{background:linear-gradient(135deg,#f0fdf4,#d1fae5);border-radius:16px;padding:24px 28px;border:2px solid #a7f3d0;margin-bottom:24px;text-align:left}
        .farewell-row{display:flex;align-items:flex-start;gap:12px;padding:10px 0;border-bottom:1px solid rgba(16,185,129,.2)}
        .farewell-row:last-child{border-bottom:none;padding-bottom:0}
        .farewell-label{font-size:12px;font-weight:700;color:#065f46;text-transform:uppercase;letter-spacing:.5px}
        .farewell-value{font-size:15px;font-weight:800;color:#0d3d4f}
        .btn-new{display:inline-flex;align-items:center;gap:8px;background:#10b981;color:#fff;padding:13px 28px;border-radius:12px;font-family:inherit;font-size:15px;font-weight:800;border:none;cursor:pointer;text-decoration:none;transition:all .2s}
        .btn-new:hover{background:#059669;transform:translateY(-2px)}
        .btn-checkin{display:inline-flex;align-items:center;gap:8px;background:transparent;color:#1a5f7a;padding:13px 28px;border-radius:12px;font-family:inherit;font-size:15px;font-weight:800;border:2px solid #1a5f7a;text-decoration:none;transition:all .2s;margin-left:10px}
        .btn-checkin:hover{background:#1a5f7a;color:#fff}

        .empty{text-align:center;padding:60px 20px;color:#96a5b0}
        .empty-icon{font-size:60px;opacity:.4;margin-bottom:14px}
        .empty h3{font-size:18px;font-weight:800;color:#5a6a77;margin-bottom:8px}

        .footer-note{color:rgba(255,255,255,.55);font-size:12px;font-weight:600;margin-top:20px;text-align:center}
        a.staff-link{color:rgba(255,255,255,.5);font-size:11px;text-decoration:none;border-bottom:1px dashed rgba(255,255,255,.3)}
        a.staff-link:hover{color:#fff}

        @media(max-width:600px){.card-body{padding:24px 20px}.visitor-row{flex-wrap:wrap}.visitor-time{text-align:left}}
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
        <div class="card-head-icon">🚪</div>
        <div>
            <h1>Visitor Check-Out</h1>
            <p>Find your name below and click <strong>Check Out</strong> to complete your visit.</p>
        </div>
    </div>

    <div class="card-body">

    <?php if ($success && $farewell): ?>
        <!-- ── SUCCESS ── -->
        <div class="success-card">
            <div class="success-icon">👋</div>
            <div class="success-title">Thank You for Visiting!</div>
            <div class="success-sub">We hope it was a wonderful visit. Please come again!</div>
            <div class="farewell-box">
                <div class="farewell-row">
                    <span style="font-size:20px;flex-shrink:0">👤</span>
                    <div><div class="farewell-label">Visitor</div><div class="farewell-value"><?= e($farewell['name']) ?></div></div>
                </div>
                <div class="farewell-row">
                    <span style="font-size:20px;flex-shrink:0">👵</span>
                    <div><div class="farewell-label">Visited Resident</div><div class="farewell-value"><?= e($farewell['resident']) ?></div></div>
                </div>
                <div class="farewell-row">
                    <span style="font-size:20px;flex-shrink:0">⏱️</span>
                    <div><div class="farewell-label">Visit Duration</div><div class="farewell-value"><?php
                        $d = $farewell['duration'];
                        echo $d < 60 ? $d . ' minutes' : floor($d/60) . 'h ' . ($d%60) . 'm';
                    ?></div></div>
                </div>
                <div class="farewell-row">
                    <span style="font-size:20px;flex-shrink:0">🕐</span>
                    <div><div class="farewell-label">Check-Out Time</div><div class="farewell-value"><?= e($farewell['time']) ?></div></div>
                </div>
            </div>
            <div style="display:flex;justify-content:center;flex-wrap:wrap;gap:10px">
                <button onclick="location.href='checkout.php'" class="btn-new">🚪 Another Check-Out</button>
                <a href="index.php" class="btn-checkin">🏠 Kiosk Home</a>
                <a href="checkin.php" class="btn-checkin" style="background:#10b981;color:#fff;border-color:#10b981">✅ Go to Check-In</a>
            </div>
        </div>

    <?php else: ?>
        <!-- ── LIST ── -->
        
        <!-- ⚡ Quick Check-Out & QR Code Scanner Panel -->
        <div style="background:#f4fbf7;border:2px solid #a7f3d0;border-radius:18px;padding:24px;margin-bottom:28px">
            <h2 style="font-size:16px;font-weight:800;color:#065f46;margin-bottom:6px;display:flex;align-items:center;gap:6px">
                ⚡ Quick Check-Out / QR Scan
            </h2>
            <p style="font-size:13px;color:#047857;font-weight:600;margin-bottom:18px">
                Type in your Check-Out Code or scan your QR Code with your camera!
            </p>
            
            <form method="POST" id="quick-checkout-form" style="display:flex;gap:12px;flex-wrap:wrap;align-items:center">
                <div style="flex:1;min-width:240px;position:relative">
                    <input type="text" name="visit_code" id="quick-code-input" 
                           placeholder="Enter Check-Out Code (e.g. VMS-A9B4)" 
                           style="width:100%;padding:12px 15px;border:2px solid #a7f3d0;border-radius:10px;font-size:15px;font-weight:700;color:#065f46" 
                           required autocomplete="off">
                </div>
                <button type="submit" class="btn-checkout" style="padding:13px 24px;font-size:15px">
                    🚪 Check Out
                </button>
                <button type="button" id="btn-scan-qr" class="btn-checkout" style="padding:13px 24px;font-size:15px;background:linear-gradient(135deg,#1e3a8a,#3b82f6);box-shadow:0 4px 12px rgba(59,130,246,0.35);display:inline-flex;align-items:center;gap:8px">
                    📷 Scan QR Code
                </button>
            </form>

            <!-- Camera Scan Container -->
            <div id="scanner-container" style="display:none;margin-top:20px;text-align:center">
                <div style="max-width:360px;margin:0 auto;border:3px solid #10b981;border-radius:12px;overflow:hidden;background:#000;position:relative">
                    <div id="qr-reader" style="width:100%"></div>
                    <button type="button" id="btn-stop-scan" style="position:absolute;top:10px;right:10px;background:rgba(239,68,68,0.85);color:#fff;border:none;border-radius:6px;padding:6px 12px;cursor:pointer;font-weight:700;font-size:12px;z-index:10">✕ Stop Camera</button>
                </div>
                <div id="scanner-status" style="margin-top:10px;font-size:13px;color:#047857;font-weight:700">Initializing camera...</div>
            </div>
        </div>
        <?php if ($coError): ?>
        <div class="alert alert-error">❌ <?= htmlspecialchars($coError) ?></div>
        <?php endif; ?>

        <?php if ($checkedIn): ?>
        <p style="font-size:14px;color:#7f8c8d;font-weight:600;margin-bottom:20px">
            <?= count($checkedIn) ?> visitor(s) currently inside. Click <strong>Check Out</strong> next to your name.
        </p>
        <div class="visitors-list">
        <?php foreach ($checkedIn as $ci):
            $el = (int)$ci['elapsed'];
            $timeClass = $el > 180 ? 'time-very-long' : ($el > 90 ? 'time-long' : 'time-ok');
            $timeLabel = $el < 60 ? $el . ' min' : floor($el/60) . 'h ' . ($el%60) . 'm';
            $initials = strtoupper(substr($ci['visitor_name'], 0, 1));
        ?>
        <div class="visitor-row">
            <div class="visitor-avatar"><?= $initials ?></div>
            <div class="visitor-info">
                <div class="visitor-name">
                    <?= e($ci['visitor_name']) ?>
                </div>
                <div class="visitor-sub">
                    Visiting: <?= e($ci['resident_name']) ?> — Room <?= e($ci['room_number']) ?>
                    &nbsp;·&nbsp; In since <?= date('h:i A', strtotime($ci['check_in_time'])) ?>
                </div>
            </div>
            <div class="visitor-time">
                <div class="time-badge <?= $timeClass ?>"><?= $timeLabel ?> inside</div><br>
                <button class="btn-checkout"
                        onclick="openCheckout(<?= $ci['id'] ?>, '<?= e(addslashes($ci['visitor_name'])) ?>')">
                    🚪 Check Out
                </button>
            </div>
        </div>
        <?php endforeach; ?>
        </div>

        <?php else: ?>
        <div class="empty">
            <div class="empty-icon">🏡</div>
            <h3>No Visitors Currently Inside</h3>
            <p style="font-size:14px">The premises are clear. <a href="checkin.php" style="color:#10b981;font-weight:700">Check in a visitor?</a></p>
        </div>
        <?php endif; ?>

    <?php endif; ?>

    </div>
</div>

<div class="footer-note">
    Want to visit? <a href="checkin.php" class="staff-link">✅ Visitor Check-In</a>
    &nbsp;·&nbsp;
    <a href="index.php" class="staff-link">🏠 Kiosk Home</a>
    &nbsp;·&nbsp;
    <a href="login.php" class="staff-link">Staff Login</a>
</div>

<!-- Verify ID Modal -->
<div class="modal-overlay" id="co-modal">
    <div class="modal">
        <div class="modal-icon">🔐</div>
        <h2>Confirm Your Identity</h2>
        <p class="modal-sub" id="modal-name-label">Please enter your Check-Out Code or ID number to confirm check-out.</p>
        <form method="POST" id="checkout-form">
            <input type="hidden" name="log_id" id="modal-log-id" value="">
            <label for="verify_id">Your Check-Out Code / ID Number</label>
            <input type="text" id="verify_id" name="verify_id"
                   placeholder="Enter Code (e.g. VMS-A9B4) or ID number" autofocus>
            <div class="modal-btns">
                <button type="button" class="btn-cancel" onclick="closeModal()">✕ Cancel</button>
                <button type="submit" class="btn-confirm">✅ Confirm Check-Out</button>
            </div>
        </form>
    </div>
</div>

<script>
function updateClock() {
    const now = new Date();
    document.getElementById('clock').textContent = now.toLocaleTimeString('en-PH',{hour:'2-digit',minute:'2-digit',second:'2-digit'});
    document.getElementById('clock-date').textContent = now.toLocaleDateString('en-PH',{weekday:'long',month:'long',day:'numeric',year:'numeric'});
}
setInterval(updateClock, 1000);
updateClock();

function openCheckout(logId, name) {
    document.getElementById('modal-log-id').value = logId;
    document.getElementById('modal-name-label').textContent = 'Checking out: ' + name + '. Enter your Check-Out Code or ID number to confirm.';
    document.getElementById('verify_id').value = '';
    document.getElementById('co-modal').classList.add('open');
    setTimeout(() => document.getElementById('verify_id').focus(), 100);
}
function closeModal() {
    document.getElementById('co-modal').classList.remove('open');
}
document.getElementById('co-modal').addEventListener('click', function(e) {
    if (e.target === this) closeModal();
});
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeModal(); });

// ── QR CODE CAMERA SCANNER LOGIC ──
let html5QrCode = null;

const btnScanQr = document.getElementById('btn-scan-qr');
if (btnScanQr) {
    btnScanQr.addEventListener('click', function() {
        const container = document.getElementById('scanner-container');
        const status = document.getElementById('scanner-status');
        container.style.display = 'block';
        status.textContent = 'Requesting camera access...';
        status.style.color = '#047857';

        container.scrollIntoView({ behavior: 'smooth', block: 'nearest' });

        if (!html5QrCode) {
            html5QrCode = new Html5Qrcode("qr-reader");
        }

        const qrCodeSuccessCallback = (decodedText, decodedResult) => {
            document.getElementById('quick-code-input').value = decodedText;
            status.textContent = '✓ QR Code scanned successfully! Processing check-out...';
            status.style.color = '#10b981';
            
            html5QrCode.stop().then(() => {
                container.style.display = 'none';
                document.getElementById('quick-checkout-form').submit();
            }).catch(err => {
                document.getElementById('quick-checkout-form').submit();
            });
        };

        const config = { fps: 10, qrbox: { width: 250, height: 250 } };

        html5QrCode.start(
            { facingMode: "environment" },
            config,
            qrCodeSuccessCallback
        ).then(() => {
            status.textContent = '🎥 Camera active. Place QR code inside the box.';
        }).catch(err => {
            console.error("Error starting scanner:", err);
            status.textContent = '❌ Camera access error: ' + err;
            status.style.color = '#ef4444';
        });
    });
}

const btnStopScan = document.getElementById('btn-stop-scan');
if (btnStopScan) {
    btnStopScan.addEventListener('click', function() {
        if (html5QrCode && html5QrCode.isScanning) {
            html5QrCode.stop().then(() => {
                document.getElementById('scanner-container').style.display = 'none';
            }).catch(err => {
                console.error(err);
            });
        } else {
            document.getElementById('scanner-container').style.display = 'none';
        }
    });
}
</script>
</body>
</html>
