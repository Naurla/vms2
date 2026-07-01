<?php
define('BASE_PATH', './');
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

if (session_status() === PHP_SESSION_NONE) session_start();

$db = getDB();

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $residentId = (int)post('resident_id');
    $reqName = trim(post('requested_by_name'));
    $reqRelation = post('requested_by_relation');
    if ($reqRelation === 'Other') {
        $reqRelation = trim(post('requested_by_relation_custom'));
    }
    $contact = trim(post('contact_info'));
    $date = post('restriction_date');
    $reason = trim(post('reason'));
    $allowed = trim(post('allowed_visitors'));
    $allowedRels = isset($_POST['allowed_relationships']) ? implode(',', $_POST['allowed_relationships']) : null;
    $bypassCode = trim(post('bypass_code'));

    if (!$residentId) $errors[] = 'Please select a valid resident.';
    if (!$reqName) $errors[] = 'Your full name is required.';
    if (!$reqRelation) $errors[] = 'Please specify your relationship.';
    if (!$date) $errors[] = 'Please select a date for the restriction.';
    if (!$reason) $errors[] = 'Please provide a reason.';

    if (!$errors) {
        $db->prepare("
            INSERT INTO visitor_restrictions 
            (resident_id, requested_by_name, requested_by_relation, contact_info, restriction_date, reason, allowed_visitors, allowed_relationships, bypass_code)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ")->execute([$residentId, $reqName, $reqRelation, $contact, $date, $reason, $allowed, $allowedRels, $bypassCode]);
        $success = true;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Request Visitor Restriction – Care Home VMS</title>
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
        body{
            font-family:'Nunito',sans-serif;
            min-height:100vh;
            background:linear-gradient(160deg,#0d3d4f 0%,#1a5f7a 55%,#c8a45e 100%);
            display:flex;flex-direction:column;align-items:center;justify-content:flex-start;
            padding:24px 16px;
        }
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

        .alert{
            padding:14px 18px;border-radius:12px;margin-bottom:20px;
            font-size:14px;font-weight:700;border-left:4px solid;
        }
        .alert-error{background:#fee2e2;color:#991b1b;border-color:#ef4444}
        .alert-success{background:#f0fdf4;color:#065f46;border-color:#10b981}

        .btn-submit{
            width:100%;padding:16px;
            background:linear-gradient(135deg,#1a5f7a,#0d3d4f);
            color:#fff;border:none;border-radius:14px;
            font-family:inherit;font-size:17px;font-weight:900;
            cursor:pointer;
            box-shadow:0 6px 20px rgba(26,95,122,.4);
            display:flex;align-items:center;justify-content:center;gap:10px;
        }
        .btn-submit:hover{transform:translateY(-2px);box-shadow:0 8px 28px rgba(26,95,122,.5)}
        .footer-note{color:rgba(255,255,255,.55);font-size:12px;font-weight:600;margin-top:20px;text-align:center}
        a.staff-link{color:rgba(255,255,255,.5);font-size:11px;text-decoration:none;border-bottom:1px dashed rgba(255,255,255,.3)}
        a.staff-link:hover{color:#fff}
    </style>
</head>
<body>

<div class="card">
    <div class="card-head">
        <div class="card-head-icon">🛡️</div>
        <div>
            <h1>Request Visitor Restriction</h1>
            <p>For Legal Guardians and Medical Personnel</p>
        </div>
    </div>
    <div class="card-body">
        <?php if ($success): ?>
            <div class="alert alert-success">
                ✅ Your restriction request has been submitted. An administrator will review it shortly.
            </div>
            <div style="text-align:center;margin-top:20px;">
                <a href="index.php" class="btn-submit" style="text-decoration:none;display:inline-block;width:auto;padding:12px 24px;">Return Home</a>
            </div>
        <?php else: ?>
            <?php if ($errors): ?>
            <div class="alert alert-error">
                ❌ Please fix the following:
                <ul style="margin-left:20px;margin-top:5px;"><?php foreach($errors as $e) echo "<li>" . e($e) . "</li>"; ?></ul>
            </div>
            <?php endif; ?>

            <form method="POST">
                <p class="section-label">👵 Resident Information</p>
                <div class="form-grid">
                    <div class="form-group full">
                        <label for="resident_name">Resident's Full Name <span class="req">*</span></label>
                        <div style="display:flex; gap:10px;">
                            <input type="text" id="resident_name" name="resident_name"
                                   placeholder="Enter the resident's full name" required style="flex:1;">
                            <button type="button" id="btn-search-resident" 
                                    style="padding:11px 20px; border-radius:10px; font-family:inherit; font-size:15px; font-weight:700; background:#1a5f7a; color:#fff; border:none; cursor:pointer;">
                                🔍 Search
                            </button>
                        </div>
                        <input type="hidden" id="resident_id" name="resident_id" value="<?= e(post('resident_id')) ?>">
                        <div id="resident-validation-hint" style="font-size:12px; margin-top:6px; font-weight:700; display:none;"></div>
                        <div id="multiple-matches-container" style="display:none; margin-top:12px;">
                            <label for="resident_match_select" style="font-size:13px; font-weight:700; color:#5a6a77;">Multiple matches found. Choose resident: <span class="req">*</span></label>
                            <select id="resident_match_select" style="width:100%; margin-top:6px;">
                                <option value="">— Choose Resident —</option>
                            </select>
                        </div>
                    </div>
                </div>

                <p class="section-label">👤 Your Details</p>
                <div class="form-grid">
                    <div class="form-group full">
                        <label>Your Full Name <span class="req">*</span></label>
                        <input type="text" name="requested_by_name" value="<?= e(post('requested_by_name')) ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Relationship <span class="req">*</span></label>
                        <select name="requested_by_relation" id="requested_by_relation" required>
                            <option value="">— Select —</option>
                            <?php foreach (['Legal Guardian', 'Medical Staff', 'Physician', 'Family Member', 'Other'] as $r): ?>
                                <option value="<?= $r ?>" <?= post('requested_by_relation') === $r ? 'selected' : '' ?>><?= $r ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div id="relation_custom_container" style="display: <?= post('requested_by_relation') === 'Other' ? 'block' : 'none' ?>; margin-top: 8px;">
                            <label style="font-size:12px; font-weight:700;">Specify <span class="req">*</span></label>
                            <input type="text" name="requested_by_relation_custom" id="requested_by_relation_custom" value="<?= e(post('requested_by_relation_custom')) ?>" style="width: 100%; margin-top: 4px;">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Contact Info (Phone/Email)</label>
                        <input type="text" name="contact_info" value="<?= e(post('contact_info')) ?>">
                    </div>
                </div>

                <p class="section-label">🛡️ Restriction Details</p>
                <div class="form-grid">
                    <div class="form-group">
                        <label>Restriction Date <span class="req">*</span></label>
                        <input type="date" name="restriction_date" required min="<?= date('Y-m-d') ?>" value="<?= e(post('restriction_date')) ?>">
                    </div>
                    <div class="form-group">
                        <label>Allowed Visitors <span style="font-weight:400;color:#96a5b0">(optional)</span></label>
                        <input type="text" name="allowed_visitors" placeholder="e.g. John Doe, Mary" value="<?= e(post('allowed_visitors')) ?>">
                        <div style="font-size:11px;color:#7f8c8d;margin-top:4px;">Comma separated names of people still allowed to visit</div>
                    </div>
                    <div class="form-group">
                        <label>Bypass Passcode <span style="font-weight:400;color:#96a5b0">(optional)</span></label>
                        <input type="text" name="bypass_code" placeholder="e.g. 1234" value="<?= e(post('bypass_code')) ?>">
                        <div style="font-size:11px;color:#7f8c8d;margin-top:4px;">Secret PIN that allows check-in regardless of name</div>
                    </div>
                    <div class="form-group full">
                        <label>Allowed Relationships <span style="font-weight:400;color:#96a5b0">(optional)</span></label>
                        <div style="display:flex; flex-wrap:wrap; gap:12px; margin-top:4px;">
                            <?php 
                            $relOptions = ['Son','Daughter','Spouse','Sibling','Grandchild','Nephew/Niece','Friend','Medical Staff','Volunteer'];
                            $postedRels = isset($_POST['allowed_relationships']) ? (array)$_POST['allowed_relationships'] : [];
                            foreach ($relOptions as $rel):
                                $checked = in_array($rel, $postedRels) ? 'checked' : '';
                            ?>
                            <label style="display:flex; align-items:center; gap:6px; font-weight:600; cursor:pointer;">
                                <input type="checkbox" name="allowed_relationships[]" value="<?= e($rel) ?>" <?= $checked ?>> <?= e($rel) ?>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="form-group full">
                        <label>Reason for Restriction <span class="req">*</span></label>
                        <textarea name="reason" required placeholder="Provide medical or legal reason..."><?= e(post('reason')) ?></textarea>
                    </div>
                </div>

                <div style="display:flex;gap:12px;margin-top:24px;">
                    <a href="index.php" class="btn-submit" style="flex:1;background:#e8edf2;color:#5a6a77;text-decoration:none;font-weight:700;border:2px solid #e2eaf0;box-shadow:none;">
                        🏠 Cancel
                    </a>
                    <button type="submit" class="btn-submit" style="flex:2;">
                        ✅ Submit Request
                    </button>
                </div>
            </form>
        <?php endif; ?>
    </div>
</div>

<div class="footer-note">
    <a href="index.php" class="staff-link">🏠 Kiosk Home</a>
</div>

<script>
    const relationSelect = document.getElementById('requested_by_relation');
    const relationCustomContainer = document.getElementById('relation_custom_container');
    const relationCustomInput = document.getElementById('requested_by_relation_custom');

    if (relationSelect) {
        relationSelect.addEventListener('change', () => {
            if (relationSelect.value === 'Other') {
                relationCustomContainer.style.display = 'block';
                relationCustomInput.required = true;
            } else {
                relationCustomContainer.style.display = 'none';
                relationCustomInput.required = false;
            }
        });
    }

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
                    showResidentValidation(`✓ Resident found: ${data.full_name}`, 'success');
                    return true;
                } else if (data.match_type === 'multiple') {
                    multiMatchSelect.innerHTML = '<option value="">— Choose Resident —</option>';
                    data.matches.forEach(m => {
                        const opt = document.createElement('option');
                        opt.value = m.id;
                        opt.textContent = `${m.full_name} (Room ${m.room_number})`;
                        opt.dataset.fullName = m.full_name;
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
                showResidentValidation(data.message || '❌ No active resident exists with that name.', 'error');
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
        if (type === 'success') residentValidationHint.style.color = '#10b981';
        else if (type === 'error') residentValidationHint.style.color = '#ef4444';
        else residentValidationHint.style.color = '#1a5f7a';
    }

    if (btnSearchResident) btnSearchResident.addEventListener('click', searchResident);

    if (residentNameInput) {
        residentNameInput.addEventListener('input', () => {
            if (residentIdInput) residentIdInput.value = '';
            residentNameInput.removeAttribute('data-full-name');
            resetMultiMatches();
            if (residentValidationHint) residentValidationHint.style.display = 'none';
        });
    }

    if (multiMatchSelect) {
        multiMatchSelect.addEventListener('change', () => {
            const opt = multiMatchSelect.options[multiMatchSelect.selectedIndex];
            if (opt && opt.value) {
                residentIdInput.value = opt.value;
                residentNameInput.value = opt.dataset.fullName;
                residentNameInput.dataset.fullName = opt.dataset.fullName;
                showResidentValidation(`✓ Selected: ${opt.dataset.fullName}`, 'success');
            } else {
                residentIdInput.value = '';
                residentNameInput.removeAttribute('data-full-name');
                showResidentValidation('⚠️ Please choose a resident from the matching list.', 'info');
            }
        });
    }

    document.querySelector('form')?.addEventListener('submit', function(e) {
        if (!residentIdInput.value) {
            e.preventDefault();
            alert('Please search and select a valid resident.');
        }
    });
</script>
</body>
</html>
