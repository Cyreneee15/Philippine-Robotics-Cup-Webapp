<?php
// ══════════════════════════════════════════════════════════════════
// register.php — PRC 2026 Registration
// ══════════════════════════════════════════════════════════════════

// ── DB CONFIG ──────────────────────────────────────────────────────
$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'prc_db';

// ── GOOGLE SHEETS WEBHOOK (Apps Script Web App URL) ────────────────
// Replace with your deployed Apps Script Web App URL
define('GSHEET_WEBHOOK_URL', 'https://script.google.com/macros/s/AKfycbxFtSUi5OhJrRpvpDzUx0WMaLOTqOn917KxDs_RDvB5WCK_ltYtiv_xQFIai3h8yIer/exec');

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($conn->connect_error) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'DB connection error.']);
        exit;
    }
    die('<p style="color:#ff6b6b;padding:40px;font-family:monospace;">DB error: ' . htmlspecialchars($conn->connect_error) . '</p>');
}
$conn->set_charset('utf8mb4');

// ── Google Sheets Sync Helper ──────────────────────────────────────
function syncToSheets(string $action, array $payload): void {
    $payload['action'] = $action;
    $ch = curl_init(GSHEET_WEBHOOK_URL);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    curl_exec($ch); // fire-and-forget; errors are non-fatal
    curl_close($ch);
}

// ══════════════════════════════════════════════════════════════════
// HANDLE AJAX POST — place_registration
// ══════════════════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'place_registration') {
    header('Content-Type: application/json');

    // ── Collect fields ─────────────────────────────────────────────
    $ref        = trim($_POST['ref']            ?? '');
    $name       = trim($_POST['contact_name']   ?? '');
    $email      = trim($_POST['contact_email']  ?? '');
    $phone      = trim($_POST['contact_phone']  ?? '');
    $role       = trim($_POST['contact_role']   ?? '');
    $school     = trim($_POST['school_name']    ?? '');
    $region     = trim($_POST['school_region']  ?? '');
    $team       = trim($_POST['team_name']      ?? '');
    $category   = trim($_POST['category']       ?? '');
    $pkgName    = trim($_POST['package_name']   ?? '');
    $pkgAmount  = floatval($_POST['package_amount'] ?? 0);
    $notes      = trim($_POST['notes']          ?? '');
    $payMethod  = in_array($_POST['payment_method'] ?? '', ['GCash','Bank Transfer']) ? $_POST['payment_method'] : 'GCash';
    $membersJson = $_POST['members_list'] ?? '[]';
    $members    = json_decode($membersJson, true) ?: [];
    $memberCount = count($members);

    if (!$ref || !$name || !$email || !$phone || !$school || !$team || !$category || !$pkgName) {
        echo json_encode(['success' => false, 'message' => 'Missing required fields.']);
        exit;
    }

    // ── Handle proof upload ────────────────────────────────────────
    $proofLink = '';
    if (!empty($_FILES['proof']['tmp_name'])) {
        if ($_FILES['proof']['size'] > 5 * 1024 * 1024) {
            echo json_encode(['success' => false, 'message' => 'Proof file too large (max 5MB).']);
            exit;
        }
        $uploadDir = __DIR__ . '/uploads/payment_proofs/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
        $ext  = strtolower(pathinfo($_FILES['proof']['name'], PATHINFO_EXTENSION));
        $ext  = in_array($ext, ['jpg','jpeg','png','pdf']) ? $ext : 'jpg';
        $fname = 'proof_' . $ref . '_' . time() . '.' . $ext;
        if (move_uploaded_file($_FILES['proof']['tmp_name'], $uploadDir . $fname)) {
            $proofLink = 'uploads/payment_proofs/' . $fname;
        }
    }

    $payStatus = $proofLink ? 'Proof Submitted' : 'Pending Payment';

    $conn->begin_transaction();
    try {
        // ── 1. Upsert school ───────────────────────────────────────
        $stmt = $conn->prepare(
            "INSERT INTO prc_reg_schools (school_name, school_region, school_email, school_phone)
             VALUES (?,?,?,?)
             ON DUPLICATE KEY UPDATE school_region = VALUES(school_region)"
        );
        $stmt->bind_param('ssss', $school, $region, $email, $phone);
        $stmt->execute();
        $stmt->close();

        $sr = $conn->prepare("SELECT school_id FROM prc_reg_schools WHERE school_name = ? LIMIT 1");
        $sr->bind_param('s', $school);
        $sr->execute();
        $schoolId = $sr->get_result()->fetch_assoc()['school_id'];
        $sr->close();

        // ── 2. Check for existing ref (update path) ────────────────
        $ec = $conn->prepare("SELECT reg_id, team_id FROM prc_registrations WHERE ref_no = ? LIMIT 1");
        $ec->bind_param('s', $ref);
        $ec->execute();
        $existing = $ec->get_result()->fetch_assoc();
        $ec->close();

        if ($existing) {
            // Update proof + status only
            $up = $conn->prepare(
                "UPDATE prc_registrations
                 SET payment_method=?, payment_status=?, proof_link=?, updated_at=NOW()
                 WHERE ref_no=?"
            );
            $up->bind_param('ssss', $payMethod, $payStatus, $proofLink, $ref);
            $up->execute();
            $up->close();
            $teamId = $existing['team_id'];
        } else {
            // ── 3. Insert team ─────────────────────────────────────
            $ti = $conn->prepare(
                "INSERT INTO prc_reg_teams (school_id, team_name, category, package)
                 VALUES (?,?,?,?)"
            );
            $ti->bind_param('isss', $schoolId, $team, $category, $pkgName);
            $ti->execute();
            $teamId = $conn->insert_id;
            $ti->close();

            // ── 4. Insert registration ─────────────────────────────
            $ri = $conn->prepare(
                "INSERT INTO prc_registrations
                 (ref_no, school_id, team_id, contact_name, contact_email, contact_phone,
                  contact_role, notes, package_name, package_amount, member_count,
                  payment_method, payment_status, proof_link, reg_status, submitted_at)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,'Active',NOW())"
            );
            $ri->bind_param(
                'siissssssdiiss',
                $ref, $schoolId, $teamId, $name, $email, $phone,
                $role, $notes, $pkgName, $pkgAmount, $memberCount,
                $payMethod, $payStatus, $proofLink
            );
            $ri->execute();
            $ri->close();

            // ── 5. Insert players ──────────────────────────────────
            if ($members) {
                $pi = $conn->prepare(
                    "INSERT INTO prc_reg_players (team_id, player_name, player_birthdate, player_grade)
                     VALUES (?,?,?,?)"
                );
                foreach ($members as $m) {
                    $pname  = trim($m['name'] ?? '');
                    if (!$pname) continue;
                    $pbdate = $m['birthdate'] ?: null;
                    $pgrade = trim($m['grade'] ?? '');
                    $pi->bind_param('isss', $teamId, $pname, $pbdate, $pgrade);
                    $pi->execute();
                }
                $pi->close();
            }
        }

        $conn->commit();

        // ── Sync to Google Sheets (fire-and-forget) ────────────────
        syncToSheets('upsert_registration', [
            'ref'            => $ref,
            'submitted_at'   => date('M d, Y h:i A'),
            'contact_name'   => $name,
            'contact_email'  => $email,
            'contact_phone'  => $phone,
            'contact_role'   => $role,
            'school'         => $school,
            'school_region'  => $region,
            'team_name'      => $team,
            'category'       => $category,
            'package'        => $pkgName,
            'amount'         => $pkgAmount,
            'member_count'   => $memberCount,
            'payment_method' => $payMethod,
            'payment_status' => $payStatus,
            'reg_status'     => 'Active',
            'notes'          => $notes,
            'proof_link'     => $proofLink,
            'players'        => array_map(fn($m) => [
                'name'      => $m['name']      ?? '',
                'grade'     => $m['grade']     ?? '',
                'birthdate' => $m['birthdate'] ?? '',
            ], $members),
        ]);

        echo json_encode(['success' => true, 'ref' => $ref, 'email' => $email]);

    } catch (Exception $e) {
        $conn->rollback();
        error_log('PRC register error: ' . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Server error. Please try again.']);
    }

    $conn->close();
    exit;
}

// ══════════════════════════════════════════════════════════════════
// GET — render the page
// ══════════════════════════════════════════════════════════════════
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Register — Philippine Robotics Cup 2026</title>
  <link rel="icon" type="image/png" href="assets/favicon.png" />
  <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;600;700;800;900&family=Exo+2:wght@300;400;500;600;700&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="https://cdn-uicons.flaticon.com/uicons-regular-rounded/css/uicons-regular-rounded.css">
  <link rel="stylesheet" href="https://cdn-uicons.flaticon.com/uicons-solid-rounded/css/uicons-solid-rounded.css">
  <link rel="stylesheet" href="https://cdn-uicons.flaticon.com/uicons-brands/css/uicons-brands.css">
  <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
  <style>
    :root {
      --prc-violet:   #8B7EFF;
      --prc-ice:      #C4EEFF;
      --creo-purple:  #7733FF;
      --creo-amber:   #FFA030;
      --creo-volt:    #FFE930;
      --creo-sky:     #44D9FF;
      --gcash-blue:   #007CFF;
      --neon-primary: var(--prc-violet);
      --bg-void:      #03020D;
      --bg-deep:      #06051A;
      --border-neon:  rgba(139,126,255,0.22);
      --text-high:    #F2EEFF;
      --text-mid:     #C8C0F0;
      --text-soft:    #9A90CC;
      --text-dim:     #7068A8;
      --nav-height:   72px;
      --font-hud:     'Orbitron', monospace;
      --font-body:    'Exo 2', sans-serif;
      --glow-primary: 0 0 18px rgba(139,126,255,0.60), 0 0 55px rgba(139,126,255,0.20);
    }
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    html { scroll-behavior: smooth; }
    body { font-family: var(--font-body); background: var(--bg-void); color: var(--text-high); overflow-x: hidden; line-height: 1.6; cursor: none; }
    a { text-decoration: none; color: inherit; }
    ul { list-style: none; }
    button { font-family: inherit; cursor: none; border: none; background: none; }

    .cursor-dot  { position:fixed;width:8px;height:8px;border-radius:50%;background:var(--prc-violet);pointer-events:none;z-index:99999;transform:translate(-50%,-50%);box-shadow:var(--glow-primary);transition:transform .1s,background .2s; }
    .cursor-ring { position:fixed;width:36px;height:36px;border-radius:50%;border:1px solid rgba(139,126,255,0.65);pointer-events:none;z-index:99998;transform:translate(-50%,-50%);transition:width .25s,height .25s,border-color .25s,transform .08s; }
    .cursor-ring.hovered { width:56px;height:56px;border-color:var(--creo-amber);border-width:1.5px; }

    body::after { content:'';position:fixed;inset:0;z-index:9998;pointer-events:none;background:repeating-linear-gradient(to bottom,transparent,transparent 2px,rgba(0,0,0,0.04) 2px,rgba(0,0,0,0.04) 4px); }
    .hex-grid { position:fixed;inset:0;z-index:0;pointer-events:none;background-image:linear-gradient(rgba(139,126,255,0.04) 1px,transparent 1px),linear-gradient(90deg,rgba(139,126,255,0.04) 1px,transparent 1px);background-size:50px 50px; }

    @keyframes fadeUp { from{opacity:0;transform:translateY(10px)} to{opacity:1;transform:translateY(0)} }
    @keyframes shake { 0%,100%{transform:translateX(0)} 20%,60%{transform:translateX(-5px)} 40%,80%{transform:translateX(5px)} }

    .page-wrapper { position:relative;z-index:1; }

    /* ── NAV ── */
    #main-nav { position:fixed;top:0;left:0;right:0;height:var(--nav-height);z-index:1000;background:rgba(3,2,13,0.94);backdrop-filter:blur(20px);border-bottom:1px solid var(--border-neon); }
    .nav-inner { max-width:1340px;margin:0 auto;height:100%;padding:0 36px;display:flex;align-items:center;justify-content:space-between;gap:16px; }
    .nav-logo { display:flex;align-items:center;gap:12px;flex-shrink:0; }
    .nav-logo img { height:38px;width:auto;transition:filter .3s; }
    .nav-logo:hover img { filter:drop-shadow(0 0 14px rgba(139,126,255,0.75)); }
    .nav-brand { font-family:var(--font-hud);font-weight:700;font-size:.72rem;letter-spacing:.06em;line-height:1.3;color:var(--prc-violet);text-shadow:0 0 12px rgba(139,126,255,0.65); }
    .nav-brand span { color:var(--text-soft);display:block;font-size:.58rem;font-weight:400;letter-spacing:.10em;text-transform:uppercase;margin-top:1px; }
    .nav-links { display:flex;align-items:center;gap:2px; }
    .nav-links a { font-family:var(--font-hud);font-size:.65rem;font-weight:600;color:var(--text-mid);padding:8px 14px;letter-spacing:.08em;text-transform:uppercase;border-radius:4px;transition:all .2s;white-space:nowrap; }
    .nav-links a:hover,.nav-links a.active { color:var(--prc-violet);text-shadow:0 0 12px rgba(139,126,255,0.85); }
    .nav-cta { background:transparent!important;border:1px solid var(--prc-violet)!important;color:var(--prc-violet)!important;padding:8px 20px!important;border-radius:3px!important;box-shadow:0 0 15px rgba(139,126,255,0.28),inset 0 0 15px rgba(139,126,255,0.06)!important;margin-left:8px;clip-path:polygon(6px 0%,100% 0%,calc(100% - 6px) 100%,0% 100%); }
    .nav-cta:hover { background:rgba(139,126,255,0.12)!important;color:#fff!important; }
    .nav-hamburger { display:none;flex-direction:column;justify-content:center;align-items:center;gap:5px;width:44px;height:44px;padding:0;background:rgba(139,126,255,0.06);border:1px solid var(--border-neon);border-radius:4px;flex-shrink:0;z-index:1002; }
    .nav-hamburger span { width:20px;height:1.5px;background:var(--prc-violet);border-radius:2px;transition:transform .28s,opacity .28s;display:block;pointer-events:none; }
    .nav-hamburger.open span:nth-child(1) { transform:rotate(45deg) translate(5px,5px); }
    .nav-hamburger.open span:nth-child(2) { opacity:0; }
    .nav-hamburger.open span:nth-child(3) { transform:rotate(-45deg) translate(5px,-5px); }
    .nav-mobile { display:none;position:fixed;top:var(--nav-height);left:0;right:0;background:rgba(3,2,13,0.98);backdrop-filter:blur(20px);border-bottom:1px solid var(--border-neon);padding:12px 18px 24px;z-index:1000;flex-direction:column;gap:2px; }
    .nav-mobile.open { display:flex; }
    .nav-mobile a { font-family:var(--font-hud);font-size:.70rem;font-weight:600;color:var(--text-mid);padding:13px 14px;border-radius:3px;letter-spacing:.08em;text-transform:uppercase;transition:all .2s;display:flex;align-items:center;gap:12px; }
    .nav-mobile a i { font-size:1rem;color:var(--prc-violet); }
    .nav-mobile a:hover { color:var(--prc-violet);background:rgba(139,126,255,0.07); }

    /* ── MAIN WRAP ── */
    .reg-wrap { max-width:1060px;margin:0 auto;padding:calc(var(--nav-height) + 48px) 36px 90px;position:relative;z-index:1; }

    /* ── STEP INDICATOR ── */
    .reg-steps { display:flex;align-items:center;justify-content:center;gap:0;margin-bottom:52px; }
    .step { display:flex;flex-direction:column;align-items:center;gap:8px; }
    .step-num { width:40px;height:40px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-family:var(--font-hud);font-size:.70rem;font-weight:900;border:2px solid rgba(139,126,255,0.25);color:var(--text-dim);background:rgba(139,126,255,0.04);transition:all .3s;position:relative;z-index:1; }
    .step.active .step-num { border-color:var(--prc-violet);color:var(--prc-violet);background:rgba(139,126,255,0.14);box-shadow:0 0 18px rgba(139,126,255,0.40); }
    .step.done   .step-num { border-color:var(--creo-volt);color:var(--bg-void);background:var(--creo-volt);box-shadow:0 0 14px rgba(255,233,48,0.55); }
    .step-label { font-family:var(--font-hud);font-size:.48rem;letter-spacing:.12em;text-transform:uppercase;color:var(--text-dim);white-space:nowrap; }
    .step.active .step-label { color:var(--prc-violet); }
    .step.done   .step-label { color:var(--creo-volt); }
    .step-line { width:72px;height:1px;background:rgba(139,126,255,0.16);margin-bottom:22px;flex-shrink:0;transition:background .4s; }
    .step-line.done { background:var(--creo-volt);box-shadow:0 0 8px rgba(255,233,48,0.35); }

    /* ── SCENES ── */
    .reg-scene { display:none;animation:fadeUp .32s ease forwards; }
    .reg-scene.active { display:block; }

    /* ── SECTION EYEBROW ── */
    .section-eyebrow { display:inline-flex;align-items:center;gap:8px;font-family:var(--font-hud);color:var(--prc-ice);font-size:.60rem;font-weight:700;letter-spacing:.20em;text-transform:uppercase;margin-bottom:16px; }
    .section-eyebrow::before { content:'//';color:rgba(139,126,255,0.40);font-size:.70rem; }
    .section-title { font-family:var(--font-hud);font-size:clamp(1.8rem,3.8vw,2.8rem);font-weight:800;letter-spacing:-.01em;line-height:1.08;margin-bottom:14px;color:#fff; }
    .section-title .accent { color:var(--prc-violet);text-shadow:0 0 18px rgba(139,126,255,0.65); }

    /* ── PACKAGE LIST ── */
    .pkg-list { display:flex;flex-direction:column;gap:14px; }
    .pkg-row { background:rgba(0,0,8,0.55);border:1px solid rgba(139,126,255,0.13);position:relative;overflow:hidden;transition:border-color .3s,box-shadow .3s; }
    .pkg-row::before { content:"";position:absolute;top:0;left:0;bottom:0;width:3px;background:var(--prc-violet);opacity:.45;transition:opacity .3s; }
    .pkg-row:hover { border-color:rgba(139,126,255,0.36);box-shadow:0 0 32px rgba(139,126,255,0.10); }
    .pkg-row:hover::before { opacity:1; }
    .pkg-row.popular { border-color:rgba(255,233,48,0.22); }
    .pkg-row.popular::before { background:var(--creo-volt); }
    .pkg-row-badge { position:absolute;top:0;right:0;font-family:var(--font-hud);font-size:.48rem;font-weight:700;letter-spacing:.12em;text-transform:uppercase;padding:5px 14px;background:var(--creo-volt);color:var(--bg-void);clip-path:polygon(8px 0%,100% 0%,100% 100%,0% 100%); }
    .pkg-row-inner { padding:24px 26px 22px 30px;display:grid;grid-template-columns:1fr auto;gap:20px;align-items:center; }
    .pkg-row-eyebrow { font-family:var(--font-hud);font-size:.50rem;font-weight:700;letter-spacing:.18em;text-transform:uppercase;color:var(--text-dim);margin-bottom:5px; }
    .pkg-row-eyebrow::before { content:"// ";color:rgba(139,126,255,0.35); }
    .pkg-row-name { font-family:var(--font-hud);font-size:1.05rem;font-weight:800;letter-spacing:.04em;color:var(--text-high);margin-bottom:9px;text-shadow:0 0 14px rgba(139,126,255,0.25); }
    .pkg-row-desc { font-size:.875rem;color:var(--text-mid);line-height:1.72;margin-bottom:14px;max-width:560px; }
    .pkg-row-tags { display:flex;flex-wrap:wrap;gap:5px; }
    .pkg-row-tag { font-family:var(--font-hud);font-size:.50rem;font-weight:600;letter-spacing:.07em;text-transform:uppercase;padding:3px 9px;border:1px solid rgba(139,126,255,0.18);color:var(--prc-violet);background:rgba(139,126,255,0.04); }
    .pkg-row.popular .pkg-row-tag { border-color:rgba(255,233,48,0.20);color:var(--creo-volt);background:rgba(255,233,48,0.04); }
    .pkg-row-right { display:flex;flex-direction:column;align-items:flex-end;gap:14px;flex-shrink:0; }
    .pkg-row-price-amt { font-family:var(--font-hud);font-size:1.60rem;font-weight:900;color:var(--prc-violet);text-shadow:0 0 16px rgba(139,126,255,0.52);line-height:1; }
    .pkg-row.popular .pkg-row-price-amt { color:var(--creo-volt);text-shadow:0 0 16px rgba(255,233,48,0.58); }
    .pkg-row-price-sub { font-family:var(--font-hud);font-size:.50rem;color:var(--text-dim);letter-spacing:.08em;margin-top:3px;text-align:right; }
    .pkg-row-actions { display:flex;gap:8px;align-items:center; }
    .btn-view { font-family:var(--font-hud);font-size:.56rem;font-weight:700;letter-spacing:.10em;text-transform:uppercase;padding:9px 16px;border:1px solid rgba(139,126,255,0.26);color:var(--text-soft);background:transparent;clip-path:polygon(5px 0%,100% 0%,calc(100% - 5px) 100%,0% 100%);cursor:pointer;transition:all .22s; }
    .btn-view:hover { border-color:var(--prc-violet);color:var(--prc-violet);background:rgba(139,126,255,0.07); }
    .btn-select { font-family:var(--font-hud);font-size:.56rem;font-weight:700;letter-spacing:.10em;text-transform:uppercase;padding:9px 20px;border:1px solid var(--prc-violet);color:var(--prc-violet);background:transparent;clip-path:polygon(5px 0%,100% 0%,calc(100% - 5px) 100%,0% 100%);box-shadow:0 0 12px rgba(139,126,255,0.20);cursor:pointer;transition:all .22s;position:relative;overflow:hidden; }
    .btn-select:hover { background:rgba(139,126,255,0.11);box-shadow:0 0 26px rgba(139,126,255,0.42);color:#fff;transform:translateY(-1px); }
    .pkg-row.popular .btn-select { border-color:var(--creo-volt);color:var(--creo-volt);box-shadow:0 0 12px rgba(255,233,48,0.20); }
    .pkg-row.popular .btn-select:hover { background:rgba(255,233,48,0.11);box-shadow:0 0 26px rgba(255,233,48,0.42);color:var(--bg-void); }

    /* ── FORM CARD ── */
    .reg-form-card { background:rgba(139,126,255,0.025);border:1px solid var(--border-neon);position:relative;overflow:hidden; }
    .reg-form-card::before { content:"";position:absolute;top:0;left:0;right:0;height:1px;background:linear-gradient(90deg,transparent,var(--prc-violet),transparent); }
    .reg-form-hdr { padding:20px 30px 16px;border-bottom:1px solid var(--border-neon);background:rgba(139,126,255,0.06);display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px; }
    .reg-form-hdr-title { font-family:var(--font-hud);font-size:.68rem;font-weight:700;letter-spacing:.10em;text-transform:uppercase;color:var(--prc-violet);text-shadow:0 0 10px rgba(139,126,255,0.50);display:flex;align-items:center;gap:9px; }
    .reg-pkg-summary { display:flex;align-items:center;gap:12px;font-family:var(--font-hud);font-size:.56rem;color:var(--text-soft);letter-spacing:.07em; }
    .reg-pkg-name  { color:var(--text-high);font-weight:700;font-size:.62rem; }
    .reg-pkg-price { color:var(--prc-violet);font-weight:900;font-size:.78rem;text-shadow:0 0 10px rgba(139,126,255,0.50); }
    .reg-form-body { padding:28px 30px; }
    .reg-form-grid { display:grid;grid-template-columns:1fr 1fr;gap:18px 24px;margin-bottom:24px; }
    .reg-field { display:flex;flex-direction:column;gap:7px; }
    .reg-field.full { grid-column:1/-1; }
    .reg-label { font-family:var(--font-hud);font-size:.50rem;font-weight:700;letter-spacing:.14em;text-transform:uppercase;color:var(--text-soft);display:flex;align-items:center;gap:5px; }
    .reg-label .req { color:var(--creo-amber);font-size:.65rem; }
    .reg-input,.reg-select,.reg-textarea { background:rgba(139,126,255,0.04);border:1px solid rgba(139,126,255,0.20);color:var(--text-high);font-family:var(--font-body);font-size:.888rem;padding:11px 14px;width:100%;outline:none;transition:border-color .22s,box-shadow .22s,background .22s;clip-path:polygon(5px 0%,100% 0%,calc(100% - 5px) 100%,0% 100%); }
    .reg-input::placeholder { color:var(--text-dim); }
    .reg-input:focus,.reg-select:focus,.reg-textarea:focus { border-color:var(--prc-violet);background:rgba(139,126,255,0.07);box-shadow:0 0 18px rgba(139,126,255,0.18); }
    .reg-input.error { border-color:rgba(255,80,80,0.70);animation:shake .3s; }
    .reg-select { cursor:pointer; }
    .reg-select option { background:var(--bg-deep);color:var(--text-high); }
    .reg-textarea { resize:vertical;min-height:80px;clip-path:none; }
    .reg-divider { display:flex;align-items:center;gap:12px;margin:4px 0 20px; }
    .reg-divider-line { flex:1;height:1px;background:rgba(139,126,255,0.12); }
    .reg-divider-label { font-family:var(--font-hud);font-size:.50rem;font-weight:700;letter-spacing:.16em;text-transform:uppercase;color:var(--text-dim);white-space:nowrap; }
    .reg-divider-label::before { content:"// ";color:rgba(139,126,255,0.30); }

    /* ── MEMBER ROWS ── */
    .members-wrap { display:flex;flex-direction:column;gap:10px;margin-bottom:8px; }
    .member-row { display:grid;grid-template-columns:1fr 1fr 1fr auto;gap:10px;align-items:end;background:rgba(139,126,255,0.03);border:1px solid rgba(139,126,255,0.10);padding:14px 16px;position:relative; }
    .member-row::before { content:'';position:absolute;left:0;top:0;bottom:0;width:2px;background:var(--prc-violet);opacity:.4; }
    .member-num { position:absolute;top:-8px;left:12px;font-family:var(--font-hud);font-size:.44rem;font-weight:700;letter-spacing:.12em;color:var(--text-dim);background:var(--bg-deep);padding:0 6px;text-transform:uppercase; }
    .btn-remove-member { width:34px;height:34px;background:rgba(255,80,80,0.07);border:1px solid rgba(255,80,80,0.22);color:rgba(255,120,120,0.70);display:flex;align-items:center;justify-content:center;cursor:pointer;font-size:.70rem;transition:all .2s;flex-shrink:0; }
    .btn-remove-member:hover { background:rgba(255,80,80,0.15);color:#FF6B6B;border-color:rgba(255,80,80,0.45); }
    .btn-add-member { display:inline-flex;align-items:center;gap:8px;font-family:var(--font-hud);font-size:.58rem;font-weight:700;letter-spacing:.10em;text-transform:uppercase;color:var(--prc-violet);background:transparent;border:1px dashed rgba(139,126,255,0.35);padding:9px 18px;cursor:pointer;transition:all .22s;margin-top:4px; }
    .btn-add-member:hover { border-color:var(--prc-violet);background:rgba(139,126,255,0.07); }
    .member-count-note { font-family:var(--font-hud);font-size:.48rem;color:var(--text-dim);letter-spacing:.08em;margin-top:6px; }

    /* ── TERMS ── */
    .reg-terms-wrap { display:flex;align-items:flex-start;gap:12px;padding:14px 16px;background:rgba(139,126,255,0.03);border:1px solid rgba(139,126,255,0.13);margin-bottom:26px;cursor:pointer;transition:border-color .2s; }
    .reg-terms-wrap:hover { border-color:rgba(139,126,255,0.26); }
    .reg-checkbox { width:18px;height:18px;flex-shrink:0;border:1.5px solid rgba(139,126,255,0.38);background:transparent;display:flex;align-items:center;justify-content:center;font-size:.62rem;color:transparent;transition:all .2s;margin-top:2px; }
    .reg-checkbox.checked { background:var(--prc-violet);border-color:var(--prc-violet);color:#fff;box-shadow:0 0 10px rgba(139,126,255,0.48); }
    .reg-terms-label { font-size:.845rem;color:var(--text-mid);line-height:1.62; }
    .reg-terms-label a { color:var(--prc-violet);text-decoration:underline; }

    /* ── FORM FOOTER ── */
    .reg-form-footer { padding:18px 30px 22px;border-top:1px solid rgba(139,126,255,0.11);background:rgba(0,0,0,0.18);display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px; }
    .btn-back { display:inline-flex;align-items:center;gap:7px;font-family:var(--font-hud);font-size:.58rem;font-weight:700;letter-spacing:.10em;text-transform:uppercase;color:var(--text-soft);background:transparent;border:1px solid rgba(139,126,255,0.18);padding:9px 20px;clip-path:polygon(5px 0%,100% 0%,calc(100% - 5px) 100%,0% 100%);cursor:pointer;transition:all .22s; }
    .btn-back:hover { border-color:var(--prc-violet);color:var(--prc-violet); }
    .btn-submit { display:inline-flex;align-items:center;gap:9px;font-family:var(--font-hud);font-size:.62rem;font-weight:700;letter-spacing:.12em;text-transform:uppercase;color:var(--prc-violet);background:transparent;border:1px solid var(--prc-violet);padding:11px 34px;clip-path:polygon(9px 0%,100% 0%,calc(100% - 9px) 100%,0% 100%);box-shadow:0 0 16px rgba(139,126,255,0.28),inset 0 0 16px rgba(139,126,255,0.05);cursor:pointer;transition:all .24s;position:relative;overflow:hidden; }
    .btn-submit:hover { background:rgba(139,126,255,0.11);box-shadow:0 0 36px rgba(139,126,255,0.52);color:#fff;transform:translateY(-2px); }
    .btn-submit:disabled { opacity:.5;cursor:not-allowed;transform:none; }

    /* ── PAYMENT SCENE ── */
    .pay-layout { display:grid;grid-template-columns:320px 1fr;gap:18px;align-items:start; }
    .pay-summary-card,.pay-gcash-card { background:rgba(139,126,255,0.025);border:1px solid var(--border-neon);position:relative;overflow:hidden; }
    .pay-gcash-card { border-color:rgba(0,124,255,0.22); }
    .pay-summary-card::before { content:"";position:absolute;top:0;left:0;right:0;height:1px;background:linear-gradient(90deg,transparent,var(--prc-violet),transparent); }
    .pay-gcash-card::before   { content:"";position:absolute;top:0;left:0;right:0;height:1px;background:linear-gradient(90deg,transparent,var(--gcash-blue),transparent); }
    .pay-card-hdr { padding:16px 22px 14px;border-bottom:1px solid rgba(139,126,255,0.12);background:rgba(139,126,255,0.05);font-family:var(--font-hud);font-size:.65rem;font-weight:700;letter-spacing:.10em;text-transform:uppercase;color:var(--prc-violet);display:flex;align-items:center;gap:9px; }
    .pay-gcash-card .pay-card-hdr { background:rgba(0,124,255,0.05);border-color:rgba(0,124,255,0.12); }
    .pay-card-body { padding:20px 22px; }
    .reg-order-row { display:flex;justify-content:space-between;align-items:baseline;padding:7px 0;border-bottom:1px solid rgba(139,126,255,0.07); }
    .reg-order-row:last-child { border-bottom:none; }
    .reg-order-key { font-family:var(--font-hud);font-size:.50rem;color:var(--text-soft);letter-spacing:.10em;text-transform:uppercase; }
    .reg-order-val { font-family:var(--font-hud);font-size:.60rem;color:var(--text-high);font-weight:700;text-align:right;max-width:180px; }
    .reg-order-row.total .reg-order-key { color:var(--prc-violet); }
    .reg-order-row.total .reg-order-val { color:var(--prc-violet);font-size:.85rem;text-shadow:0 0 10px rgba(139,126,255,0.50); }
    .pay-ref-box { margin:16px 22px 20px;padding:14px 18px;background:rgba(255,233,48,0.04);border:1px solid rgba(255,233,48,0.22);text-align:center; }
    .pay-ref-label { font-family:var(--font-hud);font-size:.48rem;font-weight:700;letter-spacing:.14em;text-transform:uppercase;color:var(--creo-volt);margin-bottom:6px; }
    .pay-ref-val { font-family:var(--font-hud);font-size:1.20rem;font-weight:900;color:var(--creo-volt);text-shadow:0 0 14px rgba(255,233,48,0.60);letter-spacing:.10em;margin-bottom:5px; }
    .pay-ref-note { font-size:.72rem;color:var(--text-dim); }
    .pay-tabs { display:flex;border-bottom:1px solid rgba(139,126,255,0.14);background:rgba(0,0,0,0.15); }
    .pay-tab { flex:1;padding:13px 16px;font-family:var(--font-hud);font-size:.60rem;font-weight:700;letter-spacing:.10em;text-transform:uppercase;color:var(--text-soft);background:transparent;border:none;cursor:pointer;transition:all .22s;display:flex;align-items:center;justify-content:center;gap:8px;border-bottom:2px solid transparent;margin-bottom:-1px; }
    .pay-tab:hover { color:var(--prc-violet);background:rgba(139,126,255,0.05); }
    .pay-tab.active { color:var(--prc-violet);border-bottom-color:var(--prc-violet);background:rgba(139,126,255,0.06); }
    .pay-tab:nth-child(2).active { color:var(--creo-amber);border-bottom-color:var(--creo-amber);background:rgba(255,160,48,0.05); }
    .pay-step { display:flex;gap:14px;align-items:flex-start;padding:14px 0;border-bottom:1px solid rgba(0,124,255,0.08); }
    .pay-step:last-child { border-bottom:none; }
    .pay-step-num { width:26px;height:26px;border-radius:50%;flex-shrink:0;background:rgba(0,124,255,0.12);border:1px solid rgba(0,124,255,0.30);color:var(--gcash-blue);font-family:var(--font-hud);font-size:.60rem;font-weight:900;display:flex;align-items:center;justify-content:center; }
    .pay-step-title { font-family:var(--font-hud);font-size:.62rem;font-weight:700;color:var(--text-high);letter-spacing:.06em;margin-bottom:5px; }
    .pay-step-desc { font-size:.845rem;color:var(--text-mid);line-height:1.68; }
    .pay-qr-wrap { display:flex;gap:16px;align-items:flex-start;margin-top:14px;flex-wrap:wrap; }
    .pay-qr-box { text-align:center; }
    .pay-qr-inner { width:120px;height:120px;background:#fff;border:3px solid rgba(0,124,255,0.30);display:flex;align-items:center;justify-content:center;margin-bottom:6px; }
    .pay-qr-label { font-family:var(--font-hud);font-size:.46rem;color:var(--text-dim);letter-spacing:.08em; }
    .pay-gcash-num-wrap { display:flex;flex-direction:column;gap:4px; }
    .pay-gcash-num-label { font-family:var(--font-hud);font-size:.46rem;font-weight:700;letter-spacing:.14em;text-transform:uppercase;color:var(--text-dim); }
    .pay-gcash-num { font-family:var(--font-hud);font-size:1.05rem;font-weight:900;color:var(--gcash-blue);letter-spacing:.06em;text-shadow:0 0 12px rgba(0,124,255,0.40); }
    .pay-gcash-name { font-size:.78rem;color:var(--text-soft);margin-bottom:8px; }
    .pay-copy-btn { display:inline-flex;align-items:center;gap:7px;font-family:var(--font-hud);font-size:.50rem;font-weight:700;letter-spacing:.10em;text-transform:uppercase;padding:6px 14px;border:1px solid rgba(0,124,255,0.30);color:var(--gcash-blue);background:rgba(0,124,255,0.06);cursor:pointer;transition:all .2s;clip-path:polygon(4px 0%,100% 0%,calc(100% - 4px) 100%,0% 100%); }
    .pay-copy-btn:hover { background:rgba(0,124,255,0.14);box-shadow:0 0 10px rgba(0,124,255,0.25); }
    .pay-amount-pill { display:inline-flex;align-items:center;margin-top:10px;padding:8px 20px;font-family:var(--font-hud);font-size:1.10rem;font-weight:900;color:var(--prc-violet);text-shadow:0 0 14px rgba(139,126,255,0.55);border:1px solid rgba(139,126,255,0.30);background:rgba(139,126,255,0.07); }
    .pay-note-box { display:flex;gap:10px;align-items:flex-start;margin-top:12px;padding:12px 14px;background:rgba(255,160,48,0.05);border:1px solid rgba(255,160,48,0.22);font-size:.830rem;color:var(--text-mid);line-height:1.65; }
    .pay-upload-wrap { margin-top:12px;border:1.5px dashed rgba(139,126,255,0.30);background:rgba(139,126,255,0.03);cursor:pointer;transition:all .22s;min-height:100px;display:flex;align-items:center;justify-content:center;position:relative;overflow:hidden; }
    .pay-upload-wrap:hover { border-color:var(--prc-violet);background:rgba(139,126,255,0.07);box-shadow:0 0 18px rgba(139,126,255,0.14); }
    .pay-upload-idle { text-align:center;padding:22px 20px; }
    .pay-upload-label { font-family:var(--font-hud);font-size:.60rem;font-weight:700;letter-spacing:.10em;text-transform:uppercase;color:var(--prc-violet);margin-bottom:4px; }
    .pay-upload-sub { font-size:.75rem;color:var(--text-dim); }
    .pay-upload-preview { width:100%;padding:14px 16px; }
    .pay-upload-preview img { width:100%;max-height:180px;object-fit:contain;border:1px solid rgba(139,126,255,0.18);margin-bottom:10px;background:rgba(0,0,0,0.30); }
    .pay-upload-file-info { display:flex;align-items:center;gap:10px; }
    .pay-upload-file-name { font-family:var(--font-hud);font-size:.58rem;font-weight:700;color:var(--text-high);letter-spacing:.04em; }
    .pay-upload-file-size { font-size:.68rem;color:var(--text-dim); }
    .pay-upload-remove { margin-left:auto;background:rgba(255,68,68,0.08);border:1px solid rgba(255,68,68,0.25);color:#FF6B6B;width:26px;height:26px;display:flex;align-items:center;justify-content:center;cursor:pointer;font-size:.65rem;flex-shrink:0;transition:all .2s; }
    .pay-upload-remove:hover { background:rgba(255,68,68,0.18); }
    .pay-upload-wrap.has-file { border-style:solid;border-color:rgba(255,233,48,0.35); }
    .bank-details-box { margin-top:12px;padding:16px 18px;background:rgba(255,160,48,0.04);border:1px solid rgba(255,160,48,0.20);display:flex;flex-direction:column;gap:0; }
    .bank-row { display:flex;align-items:baseline;justify-content:space-between;padding:7px 0;border-bottom:1px solid rgba(255,160,48,0.08); }
    .bank-row:last-of-type { border-bottom:none; }
    .bank-key { font-family:var(--font-hud);font-size:.48rem;color:var(--text-dim);letter-spacing:.10em;text-transform:uppercase; }
    .bank-val { font-family:var(--font-hud);font-size:.62rem;color:var(--text-high);font-weight:700;text-align:right; }
    .bank-acct { color:var(--creo-amber);text-shadow:0 0 10px rgba(255,160,48,0.45);font-size:.85rem;letter-spacing:.08em; }
    .pay-card-footer { padding:16px 22px 20px;border-top:1px solid rgba(0,124,255,0.10);background:rgba(0,0,0,0.15);display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap; }

    /* ── DONE SCENE ── */
    .receipt-top-msg { display:flex;align-items:center;gap:16px;padding:20px 24px;background:rgba(255,233,48,0.05);border:1px solid rgba(255,233,48,0.28);margin-bottom:28px; }
    .receipt-check { font-size:2rem;color:var(--creo-volt);text-shadow:0 0 14px rgba(255,233,48,0.60);flex-shrink:0; }
    .receipt-top-title { font-family:var(--font-hud);font-size:.88rem;font-weight:800;color:var(--text-high);margin-bottom:4px;letter-spacing:.04em; }
    .receipt-top-sub { font-size:.858rem;color:var(--text-mid);line-height:1.60; }
    .receipt-card { max-width:680px;margin:0 auto;background:rgba(139,126,255,0.03);border:1px solid var(--border-neon);position:relative;overflow:hidden; }
    .receipt-card::before { content:"";position:absolute;top:0;left:0;right:0;height:2px;background:linear-gradient(90deg,transparent,var(--prc-violet),transparent); }
    .receipt-body { padding:18px 28px; }
    .receipt-ref-row { display:flex;justify-content:space-between;align-items:baseline;padding:7px 0;border-bottom:1px solid rgba(139,126,255,0.07); }
    .receipt-ref-row:last-child { border-bottom:none; }
    .receipt-ref-label { font-family:var(--font-hud);font-size:.50rem;color:var(--text-soft);letter-spacing:.10em;text-transform:uppercase; }
    .receipt-ref-val { font-family:var(--font-hud);font-size:.68rem;color:var(--text-high);font-weight:700;text-align:right; }
    .receipt-status { color:var(--creo-amber);font-size:.60rem; }
    .receipt-divider { height:1px;background:rgba(139,126,255,0.14);margin:0 28px; }
    .receipt-section-label { padding:10px 28px 6px;font-family:var(--font-hud);font-size:.52rem;font-weight:700;letter-spacing:.16em;text-transform:uppercase;color:var(--prc-violet);background:rgba(139,126,255,0.05); }
    .receipt-row { display:flex;justify-content:space-between;align-items:baseline;padding:6px 0;border-bottom:1px solid rgba(139,126,255,0.06); }
    .receipt-row:last-child { border-bottom:none; }
    .receipt-key { font-family:var(--font-hud);font-size:.48rem;color:var(--text-dim);letter-spacing:.10em;text-transform:uppercase;flex-shrink:0; }
    .receipt-val { font-size:.845rem;color:var(--text-mid);text-align:right;max-width:340px; }
    .receipt-total-row .receipt-key { color:var(--prc-violet);font-size:.52rem; }
    .receipt-amount { color:var(--prc-violet);font-family:var(--font-hud);font-size:1.10rem;font-weight:900;text-shadow:0 0 12px rgba(139,126,255,0.50); }
    .receipt-footer-note { padding:16px 28px;font-size:.798rem;color:var(--text-dim);line-height:1.70;border-top:1px solid rgba(139,126,255,0.10);text-align:center; }
    .receipt-org-row { display:flex;justify-content:space-between;padding:10px 28px 14px;font-family:var(--font-hud);font-size:.46rem;color:var(--text-dim);letter-spacing:.08em; }
    .receipt-actions { display:flex;gap:12px;justify-content:center;flex-wrap:wrap;margin-top:24px;padding-bottom:8px; }
    .receipt-members-list { font-size:.830rem;color:var(--text-mid);line-height:1.70; }

    /* ── BUTTONS ── */
    .btn-neon-primary { display:inline-flex;align-items:center;gap:10px;background:transparent;color:var(--prc-violet);padding:12px 28px;font-family:var(--font-hud);font-size:.66rem;font-weight:700;letter-spacing:.12em;text-transform:uppercase;border:1px solid var(--prc-violet);clip-path:polygon(10px 0%,100% 0%,calc(100% - 10px) 100%,0% 100%);box-shadow:0 0 18px rgba(139,126,255,0.32),inset 0 0 18px rgba(139,126,255,0.07);transition:all .25s;position:relative;overflow:hidden;cursor:pointer; }
    .btn-neon-primary:hover { background:rgba(139,126,255,0.12);box-shadow:0 0 38px rgba(139,126,255,0.60),inset 0 0 28px rgba(139,126,255,0.12);color:#fff;transform:translateY(-2px); }
    .btn-neon-secondary { display:inline-flex;align-items:center;gap:10px;background:transparent;color:var(--creo-sky);padding:12px 28px;font-family:var(--font-hud);font-size:.66rem;font-weight:700;letter-spacing:.12em;text-transform:uppercase;border:1px solid var(--creo-sky);clip-path:polygon(10px 0%,100% 0%,calc(100% - 10px) 100%,0% 100%);box-shadow:0 0 18px rgba(68,217,255,0.28),inset 0 0 18px rgba(68,217,255,0.06);transition:all .25s;cursor:pointer; }
    .btn-neon-secondary:hover { background:rgba(68,217,255,0.10);box-shadow:0 0 38px rgba(68,217,255,0.55),inset 0 0 28px rgba(68,217,255,0.10);color:#fff;transform:translateY(-2px); }
    .btn-download { display:inline-flex;align-items:center;gap:10px;background:transparent;color:var(--creo-volt);padding:12px 28px;font-family:var(--font-hud);font-size:.66rem;font-weight:700;letter-spacing:.12em;text-transform:uppercase;border:1px solid var(--creo-volt);clip-path:polygon(10px 0%,100% 0%,calc(100% - 10px) 100%,0% 100%);box-shadow:0 0 18px rgba(255,233,48,0.28),inset 0 0 18px rgba(255,233,48,0.06);transition:all .25s;cursor:pointer; }
    .btn-download:hover { background:rgba(255,233,48,0.10);box-shadow:0 0 38px rgba(255,233,48,0.55);color:var(--bg-void);transform:translateY(-2px); }
    .btn-download:disabled { opacity:.5;cursor:not-allowed;transform:none; }

    /* ── MODAL ── */
    .pkg-modal-overlay { position:fixed;inset:0;z-index:9000;background:rgba(3,2,13,0.88);backdrop-filter:blur(8px);display:none;align-items:center;justify-content:center;padding:24px; }
    .pkg-modal-overlay.open { display:flex; }
    .pkg-modal { background:var(--bg-deep);border:1px solid var(--border-neon);max-width:580px;width:100%;max-height:85vh;overflow-y:auto;position:relative; }
    .pkg-modal::before { content:"";position:absolute;top:0;left:0;right:0;height:1px;background:linear-gradient(90deg,transparent,var(--prc-violet),transparent); }
    .pkg-modal-hdr { padding:20px 26px 16px;border-bottom:1px solid var(--border-neon);background:rgba(139,126,255,0.06);display:flex;align-items:center;justify-content:space-between; }
    .pkg-modal-title { font-family:var(--font-hud);font-size:.75rem;font-weight:700;letter-spacing:.08em;color:var(--prc-violet);text-shadow:0 0 10px rgba(139,126,255,0.50); }
    .pkg-modal-close { width:30px;height:30px;background:rgba(139,126,255,0.06);border:1px solid rgba(139,126,255,0.20);color:var(--text-soft);display:flex;align-items:center;justify-content:center;cursor:pointer;font-size:.75rem;transition:all .2s; }
    .pkg-modal-close:hover { background:rgba(139,126,255,0.14);color:var(--prc-violet); }
    .pkg-modal-body { padding:22px 26px 26px; }
    .pkg-modal-price { font-family:var(--font-hud);font-size:1.85rem;font-weight:900;color:var(--prc-violet);text-shadow:0 0 16px rgba(139,126,255,0.52);margin-bottom:3px; }
    .pkg-modal-price-sub { font-family:var(--font-hud);font-size:.50rem;color:var(--text-dim);letter-spacing:.10em;margin-bottom:18px; }
    .pkg-modal-desc { font-size:.888rem;color:var(--text-mid);line-height:1.75;margin-bottom:20px; }
    .pkg-modal-ftitle { font-family:var(--font-hud);font-size:.54rem;font-weight:700;letter-spacing:.14em;text-transform:uppercase;color:var(--text-soft);margin-bottom:11px; }
    .pkg-modal-ftitle::before { content:"// ";color:rgba(139,126,255,0.35); }
    .pkg-modal-flist { display:flex;flex-direction:column;gap:9px;margin-bottom:22px; }
    .pkg-modal-feat { display:flex;align-items:flex-start;gap:11px;font-size:.862rem;color:var(--text-mid);line-height:1.50; }
    .pkg-modal-feat i { color:var(--prc-violet);font-size:.72rem;margin-top:3px;flex-shrink:0; }
    .pkg-modal-footer { display:flex;gap:10px;flex-wrap:wrap; }

    /* ── FOOTER ── */
    footer { background:rgba(0,0,6,0.95);border-top:1px solid var(--border-neon);padding:70px 0 32px; }
    .footer-inner { max-width:1340px;margin:0 auto;padding:0 36px; }
    .footer-top { display:grid;grid-template-columns:2fr 1fr 1fr 1fr;gap:52px;margin-bottom:52px; }
    .footer-brand img { height:36px;width:auto;margin-bottom:16px; }
    .footer-brand p { font-size:.875rem;color:var(--text-mid);line-height:1.80;margin-bottom:22px; }
    .footer-contact-list { display:flex;flex-direction:column;gap:11px;margin-bottom:24px; }
    .footer-contact-item { display:flex;align-items:center;gap:11px;font-size:.875rem;color:var(--text-mid); }
    .footer-contact-item i { color:var(--prc-violet);font-size:.95rem;flex-shrink:0; }
    .footer-contact-item a { color:var(--text-mid);transition:color .2s; }
    .footer-contact-item a:hover { color:var(--prc-violet); }
    .footer-col h4 { font-family:var(--font-hud);font-size:.65rem;font-weight:700;letter-spacing:.10em;text-transform:uppercase;margin-bottom:20px;color:var(--prc-ice);text-shadow:0 0 10px rgba(196,238,255,0.45); }
    .footer-col ul { display:flex;flex-direction:column;gap:10px; }
    .footer-col ul li a { font-size:.875rem;color:var(--text-mid);transition:all .2s;display:flex;align-items:center;gap:8px; }
    .footer-col ul li a:hover { color:var(--prc-violet);padding-left:4px; }
    .footer-bottom { padding-top:24px;border-top:1px solid rgba(139,126,255,0.12);display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:14px; }
    .footer-bottom p { font-family:var(--font-hud);font-size:.58rem;color:var(--text-soft);letter-spacing:.06em; }
    .footer-bottom-links { display:flex;gap:22px; }
    .footer-bottom-links a { font-family:var(--font-hud);font-size:.58rem;color:var(--text-soft);transition:color .2s;letter-spacing:.06em; }
    .footer-bottom-links a:hover { color:var(--prc-violet); }

    ::-webkit-scrollbar { width:4px; }
    ::-webkit-scrollbar-track { background:var(--bg-void); }
    ::-webkit-scrollbar-thumb { background:var(--prc-violet);box-shadow:0 0 8px rgba(139,126,255,0.70);border-radius:2px; }

    @media (max-width:900px) { .pay-layout { grid-template-columns:1fr; } }
    @media (max-width:768px) {
      :root { --nav-height:62px; }
      body { cursor:auto; } button { cursor:pointer; }
      .cursor-dot,.cursor-ring { display:none; }
      .nav-links { display:none; } .nav-hamburger { display:flex; }
      .reg-form-grid { grid-template-columns:1fr; }
      .reg-field.full { grid-column:auto; }
      .member-row { grid-template-columns:1fr 1fr; }
      .reg-wrap { padding:calc(var(--nav-height) + 28px) 16px 70px; }
      .footer-top { grid-template-columns:1fr 1fr;gap:36px; }
    }
    @media (max-width:520px) {
      .step-line { width:36px; }
      .pkg-row-inner { grid-template-columns:1fr; }
      .member-row { grid-template-columns:1fr; }
    }
  </style>
</head>
<body>
  <div class="cursor-dot" id="cursorDot"></div>
  <div class="cursor-ring" id="cursorRing"></div>
  <div class="hex-grid" aria-hidden="true"></div>

  <?php $activePage = 'register'; include 'nav.php'; ?>

  <div class="reg-wrap page-wrapper">

    <!-- STEP INDICATOR -->
    <div class="reg-steps">
      <div class="step active" id="step1"><div class="step-num">1</div><div class="step-label">Package</div></div>
      <div class="step-line" id="line1"></div>
      <div class="step" id="step2"><div class="step-num">2</div><div class="step-label">Your Info</div></div>
      <div class="step-line" id="line2"></div>
      <div class="step" id="step3"><div class="step-num">3</div><div class="step-label">Payment</div></div>
      <div class="step-line" id="line3"></div>
      <div class="step" id="step4"><div class="step-num">4</div><div class="step-label">Done</div></div>
    </div>

    <!-- ══ SCENE 1 — PACKAGES ══ -->
    <div class="reg-scene active" id="scene-packages">
      <div style="text-align:center;margin-bottom:44px;">
        <div class="section-eyebrow" style="display:inline-flex;margin-bottom:13px;">PRC 2026 // Registration</div>
        <h1 class="section-title" style="margin-bottom:10px;">Registration <span class="accent">Packages</span></h1>
        <p style="font-size:.900rem;color:var(--text-mid);max-width:480px;margin:0 auto;line-height:1.75;">Compare options and view full details before selecting your package.</p>
      </div>
      <div class="pkg-list">
        <div class="pkg-row">
          <div class="pkg-row-inner">
            <div class="pkg-row-left">
              <div class="pkg-row-eyebrow">Package 01</div>
              <div class="pkg-row-name">Starter</div>
              <div class="pkg-row-desc">Perfect for first-time participants and beginner-level teams. Includes everything you need to compete in one category and experience the national competition.</div>
              <div class="pkg-row-tags"><span class="pkg-row-tag">1 Category</span><span class="pkg-row-tag">Up to 3 Members</span><span class="pkg-row-tag">Basic Kit</span><span class="pkg-row-tag">Certificate</span></div>
            </div>
            <div class="pkg-row-right">
              <div><div class="pkg-row-price-amt">&#8369;2,500</div><div class="pkg-row-price-sub">per team</div></div>
              <div class="pkg-row-actions">
                <button class="btn-view" onclick="openModal(1)"><i class="fi fi-rr-info"></i> Details</button>
                <button class="btn-select" onclick="choosePkg(1)">Select <i class="fi fi-rr-arrow-right"></i></button>
              </div>
            </div>
          </div>
        </div>
        <div class="pkg-row popular">
          <div class="pkg-row-badge">Most Popular</div>
          <div class="pkg-row-inner">
            <div class="pkg-row-left">
              <div class="pkg-row-eyebrow">Package 02</div>
              <div class="pkg-row-name">Competitor</div>
              <div class="pkg-row-desc">The most popular choice for experienced teams. Compete in two categories, get an advanced kit, lunch vouchers, and trophy recognition for top-3 finishers.</div>
              <div class="pkg-row-tags"><span class="pkg-row-tag">2 Categories</span><span class="pkg-row-tag">Up to 5 Members</span><span class="pkg-row-tag">Advanced Kit</span><span class="pkg-row-tag">Trophy (Top 3)</span><span class="pkg-row-tag">Lunch Voucher</span></div>
            </div>
            <div class="pkg-row-right">
              <div><div class="pkg-row-price-amt">&#8369;4,500</div><div class="pkg-row-price-sub">per team</div></div>
              <div class="pkg-row-actions">
                <button class="btn-view" onclick="openModal(2)"><i class="fi fi-rr-info"></i> Details</button>
                <button class="btn-select" onclick="choosePkg(2)">Select <i class="fi fi-rr-arrow-right"></i></button>
              </div>
            </div>
          </div>
        </div>
        <div class="pkg-row">
          <div class="pkg-row-inner">
            <div class="pkg-row-left">
              <div class="pkg-row-eyebrow">Package 03</div>
              <div class="pkg-row-name">Champion</div>
              <div class="pkg-row-desc">The ultimate package for elite teams. Compete across all categories with priority seeding, professional kit, full catering, merch pack, and MakeX international qualifier access.</div>
              <div class="pkg-row-tags"><span class="pkg-row-tag">All Categories</span><span class="pkg-row-tag">Up to 8 Members</span><span class="pkg-row-tag">Pro Kit</span><span class="pkg-row-tag">Priority Seeding</span><span class="pkg-row-tag">Full Catering</span><span class="pkg-row-tag">MakeX Access</span></div>
            </div>
            <div class="pkg-row-right">
              <div><div class="pkg-row-price-amt">&#8369;7,000</div><div class="pkg-row-price-sub">per team</div></div>
              <div class="pkg-row-actions">
                <button class="btn-view" onclick="openModal(3)"><i class="fi fi-rr-info"></i> Details</button>
                <button class="btn-select" onclick="choosePkg(3)">Select <i class="fi fi-rr-arrow-right"></i></button>
              </div>
            </div>
          </div>
        </div>
      </div>
      <div style="margin-top:30px;padding:18px 22px;border:1px solid rgba(139,126,255,0.13);background:rgba(139,126,255,0.025);display:flex;align-items:flex-start;gap:14px;">
        <i class="fi fi-rr-info" style="color:var(--prc-violet);font-size:1rem;flex-shrink:0;margin-top:3px;"></i>
        <p style="font-size:.858rem;color:var(--text-mid);line-height:1.65;margin:0;">Need help choosing? Contact us at <a href="mailto:philippineroboticscup@gmail.com" style="color:var(--prc-violet);">philippineroboticscup@gmail.com</a> or <a href="https://www.facebook.com/profile.php?id=61579706372017" target="_blank" rel="noopener" style="color:var(--prc-violet);">message us on Facebook</a>.</p>
      </div>
    </div>

    <!-- ══ SCENE 2 — FORM ══ -->
    <div class="reg-scene" id="scene-form">
      <div class="reg-form-card">
        <div class="reg-form-hdr">
          <div class="reg-form-hdr-title"><i class="fi fi-rr-pen-field"></i> Registration Form</div>
          <div class="reg-pkg-summary">
            <span>Package:</span>
            <span class="reg-pkg-name" id="form-pkg-name">—</span>
            <span class="reg-pkg-price" id="form-pkg-price">—</span>
          </div>
        </div>
        <div class="reg-form-body">
          <div class="reg-divider"><div class="reg-divider-line"></div><div class="reg-divider-label">Contact Person</div><div class="reg-divider-line"></div></div>
          <div class="reg-form-grid">
            <div class="reg-field">
              <label class="reg-label">Full Name <span class="req">*</span></label>
              <input class="reg-input" id="f-name" type="text" placeholder="Full name of main contact" />
            </div>
            <div class="reg-field">
              <label class="reg-label">Email Address <span class="req">*</span></label>
              <input class="reg-input" id="f-email" type="email" placeholder="your@email.com" />
            </div>
            <div class="reg-field">
              <label class="reg-label">Contact Number <span class="req">*</span></label>
              <input class="reg-input" id="f-phone" type="tel" placeholder="+63 9XX XXX XXXX" />
            </div>
            <div class="reg-field">
              <label class="reg-label">Role / Position</label>
              <input class="reg-input" id="f-role" type="text" placeholder="e.g. Teacher, Coach, Team Lead" />
            </div>
          </div>
          <div class="reg-divider"><div class="reg-divider-line"></div><div class="reg-divider-label">School &amp; Team Information</div><div class="reg-divider-line"></div></div>
          <div class="reg-form-grid">
            <div class="reg-field">
              <label class="reg-label">School / Organization <span class="req">*</span></label>
              <input class="reg-input" id="f-school" type="text" placeholder="Full school or organization name" />
            </div>
            <div class="reg-field">
              <label class="reg-label">School Region <span class="req">*</span></label>
              <select class="reg-input reg-select" id="f-region">
                <option value="" disabled selected>Select region</option>
                <option>NCR - National Capital Region</option>
                <option>CAR - Cordillera Administrative Region</option>
                <option>Region I - Ilocos Region</option>
                <option>Region II - Cagayan Valley</option>
                <option>Region III - Central Luzon</option>
                <option>Region IV-A - CALABARZON</option>
                <option>Region IV-B - MIMAROPA</option>
                <option>Region V - Bicol Region</option>
                <option>Region VI - Western Visayas</option>
                <option>Region VII - Central Visayas</option>
                <option>Region VIII - Eastern Visayas</option>
                <option>Region IX - Zamboanga Peninsula</option>
                <option>Region X - Northern Mindanao</option>
                <option>Region XI - Davao Region</option>
                <option>Region XII - SOCCSKSARGEN</option>
                <option>Region XIII - Caraga</option>
                <option>BARMM - Bangsamoro Region</option>
              </select>
            </div>
            <div class="reg-field">
              <label class="reg-label">Team Name <span class="req">*</span></label>
              <input class="reg-input" id="f-team" type="text" placeholder="Your team name" />
            </div>
            <div class="reg-field">
              <label class="reg-label">Competition Category <span class="req">*</span></label>
              <select class="reg-input reg-select" id="f-category">
                <option value="" disabled selected>Select a category</option>
                <optgroup label="RoboVenture">
                  <option>Aspiring Makers</option>
                  <option>Robot Soccer</option>
                  <option>Emerging Innovators</option>
                  <option>Navigation – Autonomous</option>
                  <option>Navigation – Manual</option>
                  <option>Line Tracing</option>
                  <option>Sumobot</option>
                  <option>Innovation Builders</option>
                </optgroup>
                <optgroup label="MakeX">
                  <option>MakeX Starter</option>
                  <option>MakeX Explorer</option>
                </optgroup>
                <optgroup label="Drone Soccer">
                  <option>Drone Soccer</option>
                </optgroup>
              </select>
            </div>
          </div>
          <div class="reg-divider"><div class="reg-divider-line"></div><div class="reg-divider-label">Team Members</div><div class="reg-divider-line"></div></div>
          <div class="members-wrap" id="members-wrap"></div>
          <button class="btn-add-member" id="btn-add-member" onclick="addMemberRow()">
            <i class="fi fi-rr-plus"></i> Add Member
          </button>
          <div class="member-count-note" id="member-count-note">0 member(s) added — max <span id="max-members-label">3</span> for this package</div>
          <div class="reg-divider" style="margin-top:24px;"><div class="reg-divider-line"></div><div class="reg-divider-label">Additional Notes</div><div class="reg-divider-line"></div></div>
          <div class="reg-field full" style="margin-bottom:20px;">
            <label class="reg-label">Special Requests / Remarks</label>
            <textarea class="reg-input reg-textarea" id="f-notes" placeholder="Any special requests or additional information (optional)"></textarea>
          </div>
          <div class="reg-terms-wrap" onclick="toggleTerms()">
            <div class="reg-checkbox" id="terms-check"><i class="fi fi-rr-check"></i></div>
            <div class="reg-terms-label">I agree to the <a href="#">Terms &amp; Conditions</a> and <a href="#">Competition Rules</a> of PRC 2026. I confirm all information provided is accurate and that all listed members are eligible participants.</div>
          </div>
        </div>
        <div class="reg-form-footer">
          <button class="btn-back" onclick="goToScene('scene-packages',1)"><i class="fi fi-rr-arrow-left"></i> Back to Packages</button>
          <button class="btn-submit" id="btn-submit-form" onclick="submitForm()">Continue to Payment <i class="fi fi-rr-arrow-right"></i></button>
        </div>
      </div>
    </div>

    <!-- ══ SCENE 3 — PAYMENT ══ -->
    <div class="reg-scene" id="scene-confirm">
      <div class="pay-layout">
        <div class="pay-summary-card">
          <div class="pay-card-hdr"><i class="fi fi-rr-receipt"></i> Order Summary</div>
          <div class="pay-card-body">
            <div class="reg-order-row"><span class="reg-order-key">Team</span><span class="reg-order-val" id="pay-order-team">—</span></div>
            <div class="reg-order-row"><span class="reg-order-key">School</span><span class="reg-order-val" id="pay-order-school">—</span></div>
            <div class="reg-order-row"><span class="reg-order-key">Category</span><span class="reg-order-val" id="pay-order-cat">—</span></div>
            <div class="reg-order-row"><span class="reg-order-key">Members</span><span class="reg-order-val" id="pay-order-members">—</span></div>
            <div class="reg-order-row"><span class="reg-order-key">Package</span><span class="reg-order-val" id="pay-order-pkg">—</span></div>
            <div class="reg-order-row total"><span class="reg-order-key">Total Due</span><span class="reg-order-val" id="pay-order-price">—</span></div>
          </div>
          <div class="pay-ref-box">
            <div class="pay-ref-label">Reference No.</div>
            <div class="pay-ref-val" id="pay-ref-num">—</div>
            <div class="pay-ref-note">Keep this for your records</div>
          </div>
        </div>
        <div class="pay-gcash-card">
          <div class="pay-tabs">
            <button class="pay-tab active" id="tab-gcash" onclick="switchPayTab('gcash')"><i class="fi fi-rr-smartphone"></i> GCash</button>
            <button class="pay-tab" id="tab-bank" onclick="switchPayTab('bank')"><i class="fi fi-rr-bank"></i> Bank Transfer</button>
          </div>
          <!-- GCash Panel -->
          <div id="panel-gcash">
            <div class="pay-card-body">
              <div class="pay-step">
                <div class="pay-step-num">1</div>
                <div class="pay-step-content"><div class="pay-step-title">Open GCash App</div><div class="pay-step-desc">Open GCash on your phone and tap <strong>Send Money</strong> or <strong>Pay QR</strong>.</div></div>
              </div>
              <div class="pay-step">
                <div class="pay-step-num">2</div>
                <div class="pay-step-content">
                  <div class="pay-step-title">Scan QR or Enter Number</div>
                  <div class="pay-step-desc">Scan the QR code or send to the GCash number manually.</div>
                  <div class="pay-qr-wrap">
                    <div class="pay-qr-box">
                      <div class="pay-qr-inner"><i class="fi fi-rr-qr-scan" style="font-size:3rem;color:rgba(0,100,255,0.25);"></i></div>
                      <div class="pay-qr-label">Philippine Robotics Cup</div>
                    </div>
                    <div class="pay-gcash-num-wrap">
                      <div class="pay-gcash-num-label">GCash Number</div>
                      <div class="pay-gcash-num">+63 917 771 3961</div>
                      <div class="pay-gcash-name">Creotec Philippines Inc.</div>
                      <button class="pay-copy-btn" onclick="copyGcash()"><i class="fi fi-rr-copy" id="copy-icon"></i><span id="copy-label">Copy Number</span></button>
                    </div>
                  </div>
                </div>
              </div>
              <div class="pay-step">
                <div class="pay-step-num">3</div>
                <div class="pay-step-content"><div class="pay-step-title">Enter Exact Amount</div><div class="pay-step-desc">Send the exact amount and add your <strong>Reference No.</strong> in the notes field.</div><div class="pay-amount-pill" id="pay-amount-display">—</div></div>
              </div>
              <div class="pay-step">
                <div class="pay-step-num">4</div>
                <div class="pay-step-content">
                  <div class="pay-step-title">Upload GCash Receipt</div>
                  <div class="pay-step-desc">Screenshot your GCash confirmation and attach it below.</div>
                  <div class="pay-upload-wrap" id="pay-upload-wrap" onclick="document.getElementById('proof-file').click()">
                    <input type="file" id="proof-file" accept="image/jpeg,image/png,image/jpg,.pdf" style="display:none" onchange="handleProofUpload(event,'gcash')" />
                    <div class="pay-upload-idle" id="upload-idle"><i class="fi fi-rr-cloud-upload" style="font-size:1.8rem;color:rgba(139,126,255,0.35);display:block;margin-bottom:8px;"></i><div class="pay-upload-label">Click to attach receipt</div><div class="pay-upload-sub">JPG, PNG or PDF — max 5MB</div></div>
                    <div class="pay-upload-preview" id="upload-preview" style="display:none;"><img id="proof-thumb" src="" alt="Receipt" /><div class="pay-upload-file-info"><i class="fi fi-rr-check-circle" style="color:var(--creo-volt);font-size:1rem;"></i><div><div class="pay-upload-file-name" id="proof-filename">—</div><div class="pay-upload-file-size" id="proof-filesize">—</div></div><button class="pay-upload-remove" onclick="removeProof(event,'gcash')"><i class="fi fi-rr-cross"></i></button></div></div>
                  </div>
                  <div class="pay-note-box" style="margin-top:12px;"><i class="fi fi-rr-info" style="color:var(--creo-amber);flex-shrink:0;margin-top:2px;"></i>Your Reference No. is included automatically. Slot confirmed within 24 hours.</div>
                </div>
              </div>
            </div>
          </div>
          <!-- Bank Panel -->
          <div id="panel-bank" style="display:none;">
            <div class="pay-card-body">
              <div class="pay-step">
                <div class="pay-step-num" style="background:rgba(255,160,48,0.12);border-color:rgba(255,160,48,0.35);color:var(--creo-amber);">1</div>
                <div class="pay-step-content">
                  <div class="pay-step-title">Bank Transfer Details</div>
                  <div class="pay-step-desc">Transfer the exact amount to:</div>
                  <div class="bank-details-box">
                    <div class="bank-row"><span class="bank-key">Bank</span><span class="bank-val">BDO Unibank</span></div>
                    <div class="bank-row"><span class="bank-key">Account Name</span><span class="bank-val">Creotec Philippines Inc.</span></div>
                    <div class="bank-row"><span class="bank-key">Account Number</span><span class="bank-val bank-acct">000-123-4567-8</span></div>
                    <button class="pay-copy-btn" style="margin-top:10px;border-color:rgba(255,160,48,0.30);color:var(--creo-amber);background:rgba(255,160,48,0.05);" onclick="copyBank()"><i class="fi fi-rr-copy" id="bank-copy-icon"></i><span id="bank-copy-label">Copy Account Number</span></button>
                  </div>
                </div>
              </div>
              <div class="pay-step">
                <div class="pay-step-num" style="background:rgba(255,160,48,0.12);border-color:rgba(255,160,48,0.35);color:var(--creo-amber);">2</div>
                <div class="pay-step-content"><div class="pay-step-title">Enter Exact Amount</div><div class="pay-step-desc">Include your <strong>Reference No.</strong> in the transaction notes.</div><div class="pay-amount-pill" id="pay-amount-display-bank">—</div></div>
              </div>
              <div class="pay-step">
                <div class="pay-step-num" style="background:rgba(255,160,48,0.12);border-color:rgba(255,160,48,0.35);color:var(--creo-amber);">3</div>
                <div class="pay-step-content">
                  <div class="pay-step-title">Upload Bank Receipt</div>
                  <div class="pay-step-desc">Attach your bank transfer confirmation screenshot.</div>
                  <div class="pay-upload-wrap" id="pay-upload-wrap-bank" onclick="document.getElementById('proof-file-bank').click()">
                    <input type="file" id="proof-file-bank" accept="image/jpeg,image/png,image/jpg,.pdf" style="display:none" onchange="handleProofUpload(event,'bank')" />
                    <div class="pay-upload-idle" id="upload-idle-bank"><i class="fi fi-rr-cloud-upload" style="font-size:1.8rem;color:rgba(255,160,48,0.35);display:block;margin-bottom:8px;"></i><div class="pay-upload-label" style="color:var(--creo-amber);">Click to attach receipt</div><div class="pay-upload-sub">JPG, PNG or PDF — max 5MB</div></div>
                    <div class="pay-upload-preview" id="upload-preview-bank" style="display:none;"><img id="proof-thumb-bank" src="" alt="Receipt" /><div class="pay-upload-file-info"><i class="fi fi-rr-check-circle" style="color:var(--creo-volt);font-size:1rem;"></i><div><div class="pay-upload-file-name" id="proof-filename-bank">—</div><div class="pay-upload-file-size" id="proof-filesize-bank">—</div></div><button class="pay-upload-remove" onclick="removeProof(event,'bank')"><i class="fi fi-rr-cross"></i></button></div></div>
                  </div>
                  <div class="pay-note-box" style="margin-top:12px;border-color:rgba(255,160,48,0.22);background:rgba(255,160,48,0.04);"><i class="fi fi-rr-info" style="color:var(--creo-amber);flex-shrink:0;margin-top:2px;"></i>Slot confirmed within 24 hours after payment verification.</div>
                </div>
              </div>
            </div>
          </div>
          <div class="pay-card-footer">
            <button class="btn-back" onclick="goToScene('scene-form',2)"><i class="fi fi-rr-arrow-left"></i> Back</button>
            <button class="btn-submit" id="btn-confirm-pay" onclick="confirmPayment()">I've Sent Payment <i class="fi fi-rr-check"></i></button>
          </div>
        </div>
      </div>
    </div>

    <!-- ══ SCENE 4 — DONE ══ -->
    <div class="reg-scene" id="scene-done">
      <div style="max-width:680px;margin:0 auto 24px;">
        <div class="receipt-top-msg">
          <span class="receipt-check"><i class="fi fi-rr-check-circle"></i></span>
          <div>
            <div class="receipt-top-title">Payment Submitted!</div>
            <div class="receipt-top-sub">We'll verify your payment and confirm your slot within <strong>24 hours</strong>. Download your receipt below.</div>
          </div>
        </div>
      </div>
      <div class="receipt-card" id="receipt-card">
        <div class="receipt-body">
          <div class="receipt-ref-row"><div class="receipt-ref-label">Reference No.</div><div class="receipt-ref-val" id="receipt-ref">—</div></div>
          <div class="receipt-ref-row"><div class="receipt-ref-label">Date &amp; Time</div><div class="receipt-ref-val" id="receipt-date">—</div></div>
          <div class="receipt-ref-row"><div class="receipt-ref-label">Status</div><div class="receipt-ref-val receipt-status">Proof Submitted — Pending Verification</div></div>
        </div>
        <div class="receipt-divider"></div>
        <div class="receipt-section-label">Registrant Details</div>
        <div class="receipt-body">
          <div class="receipt-row"><span class="receipt-key">Name</span><span class="receipt-val" id="receipt-name">—</span></div>
          <div class="receipt-row"><span class="receipt-key">Email</span><span class="receipt-val" id="receipt-email">—</span></div>
          <div class="receipt-row"><span class="receipt-key">Contact</span><span class="receipt-val" id="receipt-phone">—</span></div>
          <div class="receipt-row"><span class="receipt-key">School</span><span class="receipt-val" id="receipt-school">—</span></div>
          <div class="receipt-row"><span class="receipt-key">Region</span><span class="receipt-val" id="receipt-region">—</span></div>
          <div class="receipt-row"><span class="receipt-key">Team Name</span><span class="receipt-val" id="receipt-team">—</span></div>
          <div class="receipt-row"><span class="receipt-key">Category</span><span class="receipt-val" id="receipt-cat">—</span></div>
          <div class="receipt-row"><span class="receipt-key">Members</span><span class="receipt-val receipt-members-list" id="receipt-members">—</span></div>
        </div>
        <div class="receipt-divider"></div>
        <div class="receipt-section-label">Payment Details</div>
        <div class="receipt-body">
          <div class="receipt-row"><span class="receipt-key">Package</span><span class="receipt-val" id="receipt-pkg">—</span></div>
          <div class="receipt-row"><span class="receipt-key">Payment Method</span><span class="receipt-val" id="receipt-method">—</span></div>
          <div class="receipt-row receipt-total-row"><span class="receipt-key">Amount Paid</span><span class="receipt-val receipt-amount" id="receipt-amount">—</span></div>
        </div>
        <div class="receipt-divider"></div>
        <div class="receipt-footer-note">This receipt confirms that your registration and proof of payment have been received. Your slot will be officially confirmed within 24 hours via email. For inquiries: philippineroboticscup@gmail.com</div>
        <div class="receipt-org-row"><span>Organized by Creotec Philippines Inc.</span><span>philippineroboticscup@gmail.com</span></div>
      </div>
      <div class="receipt-actions">
        <button class="btn-download" id="btn-download-receipt" onclick="downloadReceiptPDF()">
          <i class="fi fi-rr-download"></i> Download Receipt (PDF)
        </button>
        <button class="btn-neon-secondary" onclick="window.location.href='index.php'">
          <i class="fi fi-rr-home"></i> Back to Home
        </button>
      </div>
    </div>

  </div><!-- /reg-wrap -->

  <!-- PACKAGE DETAIL MODAL -->
  <div class="pkg-modal-overlay" id="modal-overlay" onclick="closeModalOutside(event)">
    <div class="pkg-modal">
      <div class="pkg-modal-hdr">
        <div class="pkg-modal-title" id="modal-title">Package Details</div>
        <button class="pkg-modal-close" onclick="closeModal()"><i class="fi fi-rr-cross"></i></button>
      </div>
      <div class="pkg-modal-body">
        <div class="pkg-modal-price" id="modal-price"></div>
        <div class="pkg-modal-price-sub">per team registration</div>
        <div class="pkg-modal-desc" id="modal-desc"></div>
        <div class="pkg-modal-ftitle">What's Included</div>
        <div class="pkg-modal-flist" id="modal-features"></div>
        <div class="pkg-modal-footer">
          <button class="btn-neon-primary" onclick="choosePkg(currentModal)"><i class="fi fi-rr-pen-field"></i> Register with This Package</button>
          <button class="btn-view" onclick="closeModal()">Close</button>
        </div>
      </div>
    </div>
  </div>

  <footer role="contentinfo">
    <div class="footer-inner">
      <div class="footer-top">
        <div class="footer-brand">
          <img src="assets/PRC White Logo.png" alt="Philippine Robotics Cup" />
          <p>The Philippine Robotics Cup is a premier national robotics competition promoting STEM education.</p>
          <div class="footer-contact-list">
            <div class="footer-contact-item"><i class="fi fi-brands-facebook"></i><a href="https://www.facebook.com/profile.php?id=61579706372017" target="_blank" rel="noopener">Philippine Robotics Cup</a></div>
            <div class="footer-contact-item"><i class="fi fi-rr-phone-call"></i><a href="tel:+639177713961">+63 917 771 3961</a></div>
            <div class="footer-contact-item"><i class="fi fi-rr-envelope"></i><a href="mailto:philippineroboticscup@gmail.com">philippineroboticscup@gmail.com</a></div>
          </div>
        </div>
        <nav class="footer-col"><h4>Competition</h4><ul><li><a href="index.php#categories"><i class="fi fi-rr-angle-right"></i>Categories</a></li><li><a href="rankings.php"><i class="fi fi-rr-angle-right"></i>Rankings</a></li></ul></nav>
        <nav class="footer-col"><h4>Register</h4><ul><li><a href="register.php"><i class="fi fi-rr-angle-right"></i>Register Now</a></li><li><a href="#"><i class="fi fi-rr-angle-right"></i>FAQ</a></li></ul></nav>
        <nav class="footer-col"><h4>Resources</h4><ul><li><a href="shop.php"><i class="fi fi-rr-angle-right"></i>Shop</a></li><li><a href="contact.php"><i class="fi fi-rr-angle-right"></i>Contact Us</a></li></ul></nav>
      </div>
      <div class="footer-bottom">
        <p>&copy; 2026 Philippine Robotics Cup // Creotec Philippines Inc. All rights reserved.</p>
        <div class="footer-bottom-links"><a href="#">Privacy Policy</a><a href="#">Terms of Use</a></div>
      </div>
    </div>
  </footer>

  <script>
    /* ══ CURSOR ══════════════════════════════════════════════════ */
    var dot=document.getElementById('cursorDot'),ring=document.getElementById('cursorRing'),mx=0,my=0,rx=0,ry=0;
    document.addEventListener('mousemove',function(e){mx=e.clientX;my=e.clientY;dot.style.left=mx+'px';dot.style.top=my+'px';});
    (function a(){rx+=(mx-rx)*.12;ry+=(my-ry)*.12;ring.style.left=rx+'px';ring.style.top=ry+'px';requestAnimationFrame(a);})();
    document.querySelectorAll('a,button,.pkg-row').forEach(function(el){
      el.addEventListener('mouseenter',function(){ring.classList.add('hovered');dot.style.background='var(--creo-amber)';});
      el.addEventListener('mouseleave',function(){ring.classList.remove('hovered');dot.style.background='var(--prc-violet)';});
    });

    /* ══ PACKAGE DATA ════════════════════════════════════════════ */
    var pkgs = {
      1: { name:'Package 1 — Starter',    price:'₱2,500', amount: 2500, maxMembers:3,
           desc:'Perfect for first-time participants and beginner-level school teams.',
           features:['1 competition category entry','Basic robot kit and materials','Certificate of Participation','Up to 3 team members','Event day access passes','Access to practice area'] },
      2: { name:'Package 2 — Competitor', price:'₱4,500', amount: 4500, maxMembers:5,
           desc:'The most popular choice for experienced teams. Compete in two categories with full support.',
           features:['2 competition category entries','Advanced robot kit and parts','Certificate of Participation','Trophy for top 3 finishers','Up to 5 team members','Lunch voucher for entire team','Event day access passes','Priority registration support'] },
      3: { name:'Package 3 — Champion',   price:'₱7,000', amount: 7000, maxMembers:8,
           desc:'The ultimate package for elite teams competing across all categories.',
           features:['Entry to all competition categories','Professional robot kit and premium parts','Priority judging lane and seeding','Up to 8 team members','Full catering for team','Exclusive PRC 2026 merchandise pack','MakeX qualifier access','Dedicated coach liaison support'] }
    };

    var selectedPkg  = 0;
    var termsChecked = false;
    var currentModal = 1;
    var currentRef   = '';
    var formData     = {};
    var memberCount  = 0;

    /* ══ SCENE FLOW ══════════════════════════════════════════════ */
    function goToScene(sceneId, stepNum) {
      document.querySelectorAll('.reg-scene').forEach(function(s){ s.classList.remove('active'); });
      document.getElementById(sceneId).classList.add('active');
      setStep(stepNum);
      window.scrollTo({top:0,behavior:'smooth'});
    }

    function setStep(n) {
      for (var i=1; i<=4; i++) {
        var s = document.getElementById('step'+i);
        if (!s) continue;
        s.classList.remove('active','done');
        if (i < n)  s.classList.add('done');
        if (i === n) s.classList.add('active');
        if (i <= 3) { var l=document.getElementById('line'+i); if(l) l.classList.toggle('done',i<n); }
      }
      document.querySelectorAll('.step').forEach(function(s,idx){
        var num=s.querySelector('.step-num'); if(!num) return;
        num.innerHTML = s.classList.contains('done') ? '<i class="fi fi-rr-check"></i>' : (idx+1)+'';
      });
    }

    /* ══ PACKAGE SELECT ══════════════════════════════════════════ */
    function choosePkg(n) {
      selectedPkg = n;
      closeModal();
      document.getElementById('form-pkg-name').textContent  = pkgs[n].name;
      document.getElementById('form-pkg-price').textContent = pkgs[n].price;
      document.getElementById('max-members-label').textContent = pkgs[n].maxMembers;
      document.getElementById('members-wrap').innerHTML = '';
      memberCount = 0;
      addMemberRow();
      goToScene('scene-form',2);
    }

    /* ══ MEMBER ROWS ══════════════════════════════════════════════ */
    function addMemberRow() {
      var max = selectedPkg ? pkgs[selectedPkg].maxMembers : 8;
      if (memberCount >= max) { alert('Maximum '+max+' members allowed for this package.'); return; }
      memberCount++;
      var wrap = document.getElementById('members-wrap');
      var row = document.createElement('div');
      row.className = 'member-row';
      row.id = 'member-row-'+memberCount;
      row.innerHTML =
        '<div class="member-num">Member ' + memberCount + '</div>' +
        '<div class="reg-field"><label class="reg-label">Full Name <span class="req">*</span></label><input class="reg-input" type="text" placeholder="Player full name" id="m-name-'+memberCount+'" /></div>' +
        '<div class="reg-field"><label class="reg-label">Birthdate</label><input class="reg-input" type="date" id="m-bdate-'+memberCount+'" /></div>' +
        '<div class="reg-field"><label class="reg-label">Grade Level</label><input class="reg-input" type="text" placeholder="e.g. Grade 8" id="m-grade-'+memberCount+'" /></div>' +
        (memberCount > 1 ? '<button class="btn-remove-member" onclick="removeMemberRow('+memberCount+')"><i class="fi fi-rr-trash"></i></button>' : '<div></div>');
      wrap.appendChild(row);
      updateMemberNote();
    }

    function removeMemberRow(n) {
      var row = document.getElementById('member-row-'+n);
      if (row) { row.remove(); memberCount--; updateMemberNote(); renumberMembers(); }
    }

    function renumberMembers() {
      document.querySelectorAll('.member-row').forEach(function(r,i){
        var num = r.querySelector('.member-num');
        if (num) num.textContent = 'Member '+(i+1);
      });
    }

    function updateMemberNote() {
      var max  = selectedPkg ? pkgs[selectedPkg].maxMembers : 8;
      var rows = document.querySelectorAll('.member-row').length;
      document.getElementById('member-count-note').innerHTML = rows+' member(s) added — max <span id="max-members-label">'+max+'</span> for this package';
    }

    function getMembersList() {
      var members = [];
      document.querySelectorAll('.member-row').forEach(function(row,idx) {
        var inputs = row.querySelectorAll('input');
        var name   = inputs[0] ? inputs[0].value.trim() : '';
        var bdate  = inputs[1] ? inputs[1].value : '';
        var grade  = inputs[2] ? inputs[2].value.trim() : '';
        if (name) members.push({ name:name, birthdate:bdate, grade:grade });
      });
      return members;
    }

    /* ══ MODAL ═══════════════════════════════════════════════════ */
    function openModal(n) {
      currentModal = n;
      var d = pkgs[n];
      document.getElementById('modal-title').textContent = d.name;
      document.getElementById('modal-price').textContent = d.price;
      document.getElementById('modal-desc').textContent  = d.desc;
      document.getElementById('modal-features').innerHTML = d.features.map(function(f){
        return '<div class="pkg-modal-feat"><i class="fi fi-rr-check"></i>'+f+'</div>';
      }).join('');
      document.getElementById('modal-overlay').classList.add('open');
      document.body.style.overflow='hidden';
    }
    function closeModal() { document.getElementById('modal-overlay').classList.remove('open'); document.body.style.overflow=''; }
    function closeModalOutside(e) { if(e.target===document.getElementById('modal-overlay')) closeModal(); }

    /* ══ TERMS ════════════════════════════════════════════════════ */
    function toggleTerms() {
      termsChecked = !termsChecked;
      document.getElementById('terms-check').classList.toggle('checked',termsChecked);
    }

    /* ══ FORM SUBMIT — saves initial "Pending Payment" row ═══════ */
    function submitForm() {
      var name   = document.getElementById('f-name').value.trim();
      var email  = document.getElementById('f-email').value.trim();
      var phone  = document.getElementById('f-phone').value.trim();
      var school = document.getElementById('f-school').value.trim();
      var region = document.getElementById('f-region').value;
      var team   = document.getElementById('f-team').value.trim();
      var cat    = document.getElementById('f-category').value;
      var notes  = document.getElementById('f-notes').value.trim();
      var role   = document.getElementById('f-role').value.trim();

      var errors = [];
      if (!name)   errors.push('f-name');
      if (!email)  errors.push('f-email');
      if (!phone)  errors.push('f-phone');
      if (!school) errors.push('f-school');
      if (!region) errors.push('f-region');
      if (!team)   errors.push('f-team');
      if (!cat)    errors.push('f-category');
      errors.forEach(function(id){
        var el=document.getElementById(id);
        if(el){ el.classList.add('error'); setTimeout(function(){ el.classList.remove('error'); },600); }
      });
      if (errors.length) { alert('Please fill in all required fields.'); return; }
      if (!termsChecked) { alert('Please agree to the Terms & Conditions to proceed.'); return; }

      var members_list = getMembersList();
      if (members_list.length === 0) { alert('Please add at least one team member.'); return; }

      currentRef = 'PRC-' + Date.now().toString().slice(-6);
      formData = { name:name, email:email, phone:phone, role:role, school:school, region:region, team:team, category:cat, notes:notes, members_list:members_list };

      // Save initial "Pending Payment" record to MySQL via FormData POST
      var fd = new FormData();
      fd.append('action',           'place_registration');
      fd.append('ref',              currentRef);
      fd.append('contact_name',     name);
      fd.append('contact_email',    email);
      fd.append('contact_phone',    phone);
      fd.append('contact_role',     role);
      fd.append('school_name',      school);
      fd.append('school_region',    region);
      fd.append('team_name',        team);
      fd.append('category',         cat);
      fd.append('package_name',     pkgs[selectedPkg].name);
      fd.append('package_amount',   pkgs[selectedPkg].amount);
      fd.append('notes',            notes);
      fd.append('payment_method',   'Pending');
      fd.append('members_list',     JSON.stringify(members_list));

      fetch('register.php', { method:'POST', body:fd })
        .then(function(r){ return r.json(); })
        .catch(function(){ return { success:true }; }); // non-blocking; proceed regardless

      // Populate payment scene
      var s = function(id,v){ var el=document.getElementById(id); if(el) el.textContent=v; };
      s('pay-ref-num',         currentRef);
      s('pay-amount-display',  pkgs[selectedPkg].price);
      s('pay-amount-display-bank', pkgs[selectedPkg].price);
      s('pay-order-team',      team);
      s('pay-order-school',    school);
      s('pay-order-cat',       cat);
      s('pay-order-members',   members_list.length+' member(s)');
      s('pay-order-pkg',       pkgs[selectedPkg].name);
      s('pay-order-price',     pkgs[selectedPkg].price);

      goToScene('scene-confirm',3);
    }

    /* ══ PAYMENT TAB ═════════════════════════════════════════════ */
    function switchPayTab(tab) {
      document.getElementById('panel-gcash').style.display = tab==='gcash' ? 'block':'none';
      document.getElementById('panel-bank').style.display  = tab==='bank'  ? 'block':'none';
      document.getElementById('tab-gcash').classList.toggle('active', tab==='gcash');
      document.getElementById('tab-bank').classList.toggle('active',  tab==='bank');
    }

    /* ══ UPLOAD HELPERS ══════════════════════════════════════════ */
    function handleProofUpload(e, which) {
      var file = e.target.files[0]; if (!file) return;
      if (file.size > 5*1024*1024) { alert('File too large. Max 5MB.'); return; }
      var suffix = which==='bank' ? '-bank' : '';
      document.getElementById('pay-upload-wrap'+suffix).classList.add('has-file');
      document.getElementById('upload-idle'+suffix).style.display    = 'none';
      document.getElementById('upload-preview'+suffix).style.display = 'block';
      document.getElementById('proof-filename'+suffix).textContent   = file.name;
      document.getElementById('proof-filesize'+suffix).textContent   = (file.size/1024).toFixed(1)+' KB';
      if (file.type.startsWith('image/')) {
        var r=new FileReader(); r.onload=function(ev){
          var t=document.getElementById('proof-thumb'+suffix);
          t.src=ev.target.result; t.style.display='block';
        }; r.readAsDataURL(file);
      }
    }

    function removeProof(e, which) {
      e.stopPropagation();
      var suffix = which==='bank' ? '-bank' : '';
      document.getElementById('proof-file'+suffix).value='';
      document.getElementById('pay-upload-wrap'+suffix).classList.remove('has-file');
      document.getElementById('upload-idle'+suffix).style.display='block';
      document.getElementById('upload-preview'+suffix).style.display='none';
      var t=document.getElementById('proof-thumb'+suffix); if(t){t.src='';t.style.display='none';}
    }

    /* ══ CONFIRM PAYMENT — POST with file to PHP ═════════════════ */
    function confirmPayment() {
      var isGcash = document.getElementById('tab-gcash').classList.contains('active');
      var fileEl  = document.getElementById(isGcash ? 'proof-file' : 'proof-file-bank');
      var file    = fileEl.files[0];
      if (!file) { alert('Please attach your '+(isGcash?'GCash':'bank transfer')+' receipt before proceeding.'); return; }

      var method = isGcash ? 'GCash' : 'Bank Transfer';
      var btn    = document.getElementById('btn-confirm-pay');
      btn.disabled = true;
      btn.textContent = 'Submitting…';

      var fd = new FormData();
      fd.append('action',          'place_registration');
      fd.append('ref',             currentRef);
      fd.append('contact_name',    formData.name);
      fd.append('contact_email',   formData.email);
      fd.append('contact_phone',   formData.phone);
      fd.append('contact_role',    formData.role);
      fd.append('school_name',     formData.school);
      fd.append('school_region',   formData.region);
      fd.append('team_name',       formData.team);
      fd.append('category',        formData.category);
      fd.append('package_name',    pkgs[selectedPkg].name);
      fd.append('package_amount',  pkgs[selectedPkg].amount);
      fd.append('notes',           formData.notes);
      fd.append('payment_method',  method);
      fd.append('members_list',    JSON.stringify(formData.members_list));
      fd.append('proof',           file, file.name);

      fetch('register.php', { method:'POST', body:fd })
        .then(function(r){ return r.json(); })
        .then(function(data) {
          btn.disabled = false;
          btn.innerHTML = 'I\'ve Sent Payment <i class="fi fi-rr-check"></i>';
          if (data.success) {
            var now = new Date().toLocaleString('en-PH',{timeZone:'Asia/Manila'});
            var memberText = formData.members_list.map(function(m,i){
              return (i+1)+'. '+m.name+(m.grade?' ('+m.grade+')':'');
            }).join('\n');
            var s2=function(id,v){var el=document.getElementById(id);if(el)el.textContent=v;};
            s2('receipt-ref',    currentRef);
            s2('receipt-date',   now);
            s2('receipt-name',   formData.name);
            s2('receipt-email',  formData.email);
            s2('receipt-phone',  formData.phone);
            s2('receipt-school', formData.school);
            s2('receipt-region', formData.region);
            s2('receipt-team',   formData.team);
            s2('receipt-cat',    formData.category);
            s2('receipt-members',memberText);
            s2('receipt-pkg',    pkgs[selectedPkg].name);
            s2('receipt-method', method);
            s2('receipt-amount', pkgs[selectedPkg].price);

            window._receiptData = {
              ref:currentRef, date:now,
              name:formData.name, email:formData.email, phone:formData.phone,
              school:formData.school, region:formData.region, team:formData.team,
              category:formData.category, members_list:formData.members_list,
              pkg:pkgs[selectedPkg].name, method:method, amount:pkgs[selectedPkg].price
            };
            goToScene('scene-done',4);
          } else {
            alert('Error: '+(data.message||'Something went wrong. Please try again.'));
          }
        })
        .catch(function(){
          btn.disabled = false;
          btn.innerHTML = 'I\'ve Sent Payment <i class="fi fi-rr-check"></i>';
          alert('Network error. Check your connection and try again.');
        });
    }

    /* ══ COPY HELPERS ════════════════════════════════════════════ */
    function copyGcash() {
      navigator.clipboard.writeText('+639177713961').then(function(){
        document.getElementById('copy-label').textContent='Copied!';
        document.getElementById('copy-icon').className='fi fi-rr-check';
        setTimeout(function(){ document.getElementById('copy-label').textContent='Copy Number'; document.getElementById('copy-icon').className='fi fi-rr-copy'; },2000);
      }).catch(function(){ alert('+63 917 771 3961'); });
    }
    function copyBank() {
      navigator.clipboard.writeText('000-123-4567-8').then(function(){
        document.getElementById('bank-copy-label').textContent='Copied!';
        document.getElementById('bank-copy-icon').className='fi fi-rr-check';
        setTimeout(function(){ document.getElementById('bank-copy-label').textContent='Copy Account Number'; document.getElementById('bank-copy-icon').className='fi fi-rr-copy'; },2000);
      }).catch(function(){ alert('000-123-4567-8'); });
    }

    /* ══ PDF RECEIPT — Light Mode ════════════════════════════════ */
    function downloadReceiptPDF() {
      var btn = document.getElementById('btn-download-receipt');
      btn.disabled = true;
      btn.innerHTML = '<i class="fi fi-rr-loading"></i> Generating...';
      var d = window._receiptData || {};

      try {
        var jsPDF = window.jspdf.jsPDF;
        var doc   = new jsPDF({ orientation:'portrait', unit:'mm', format:'a4' });
        var pageW  = doc.internal.pageSize.getWidth();
        var pageH  = doc.internal.pageSize.getHeight();
        var margin = 18;
        var colL   = margin;
        var colR   = pageW - margin;
        var y      = 0;

        // ── Helpers ──────────────────────────────────────────────
        function rgb(r,g,b) { doc.setTextColor(r,g,b); }
        function fill(r,g,b) { doc.setFillColor(r,g,b); }
        function draw(r,g,b) { doc.setDrawColor(r,g,b); }
        function bold()   { doc.setFont('helvetica','bold'); }
        function normal() { doc.setFont('helvetica','normal'); }
        function size(n)  { doc.setFontSize(n); }
        function line(x1,y1,x2,y2) { doc.line(x1,y1,x2,y2); }
        function hrule(yPos, r,g,b, lw) {
          draw(r||220,g||220,b||220); doc.setLineWidth(lw||0.3);
          line(margin, yPos, colR, yPos);
        }

        // ── HEADER BAND ──────────────────────────────────────────
        fill(37,37,60); doc.rect(0, 0, pageW, 34, 'F');

        // Logo text left
        bold(); size(15); rgb(255,255,255);
        doc.text('Philippine Robotics Cup', margin, 14);
        bold(); size(8); rgb(180,170,255);
        doc.text('2026 — NATIONAL COMPETITION', margin, 21);
        normal(); size(7.5); rgb(140,130,200);
        doc.text('Organized by Creotec Philippines Inc.', margin, 27.5);

        // "RECEIPT" label right
        bold(); size(9); rgb(255,255,255);
        doc.text('REGISTRATION', colR, 14, {align:'right'});
        bold(); size(22); rgb(255,255,255);
        doc.text('RECEIPT', colR, 26, {align:'right'});

        y = 44;

        // ── REF + STATUS ROW ─────────────────────────────────────
        // Left: ref box
        fill(248,247,255); doc.roundedRect(margin, y-5, 74, 20, 2, 2, 'F');
        draw(210,205,255); doc.setLineWidth(0.3); doc.roundedRect(margin, y-5, 74, 20, 2, 2, 'S');
        normal(); size(7); rgb(120,110,180);
        doc.text('REFERENCE NUMBER', margin+4, y+1);
        bold(); size(13); rgb(60,50,140);
        doc.text(d.ref || '—', margin+4, y+10);

        // Right: date box
        fill(248,247,255); doc.roundedRect(colR-74, y-5, 74, 20, 2, 2, 'F');
        draw(210,205,255); doc.setLineWidth(0.3); doc.roundedRect(colR-74, y-5, 74, 20, 2, 2, 'S');
        normal(); size(7); rgb(120,110,180);
        doc.text('DATE & TIME', colR-70, y+1);
        bold(); size(8.5); rgb(60,50,140);
        var dateLines = doc.splitTextToSize(d.date || '—', 68);
        doc.text(dateLines, colR-70, y+8);

        y += 25;

        // Status pill
        fill(235,255,240); doc.roundedRect(margin, y-4, 90, 11, 2, 2, 'F');
        draw(150,210,170); doc.setLineWidth(0.3); doc.roundedRect(margin, y-4, 90, 11, 2, 2, 'S');
        bold(); size(7.5); rgb(20,120,60);
        doc.text('⬤  PROOF SUBMITTED — PENDING VERIFICATION', margin+4, y+3.5);

        y += 17;
        hrule(y, 220,220,235, 0.5); y += 8;

        // ── SECTION HEADER helper ─────────────────────────────────
        function sectionHeader(title) {
          fill(245,244,255); doc.rect(margin, y-3, colR-margin, 10, 'F');
          draw(210,205,245); doc.setLineWidth(0.25); doc.rect(margin, y-3, colR-margin, 10, 'S');
          bold(); size(7.5); rgb(80,65,180);
          doc.text(title, margin+3, y+4);
          y += 14;
        }

        // ── DATA ROW helper ───────────────────────────────────────
        // Alternating row bg
        var rowAlt = false;
        function dataRow(label, value) {
          if (!value) return;
          var valLines = doc.splitTextToSize(String(value), (colR - margin) * 0.58);
          var rowH     = Math.max(8, valLines.length * 5.5);
          if (rowAlt) { fill(251,250,255); doc.rect(margin, y-3, colR-margin, rowH+2, 'F'); }
          rowAlt = !rowAlt;
          normal(); size(7.5); rgb(120,115,155);
          doc.text(label.toUpperCase(), colR - margin - (colR-margin)*0.58 - 3, y+3, {align:'right'});
          bold(); size(8.5); rgb(40,35,80);
          doc.text(valLines, colR - (colR-margin)*0.58, y+3);
          y += rowH + 2;
          // light row separator
          draw(230,228,245); doc.setLineWidth(0.2);
          line(margin, y-1, colR, y-1);
        }

        // ── REGISTRANT DETAILS ────────────────────────────────────
        sectionHeader('REGISTRANT DETAILS');
        rowAlt = false;
        dataRow('Full Name',    d.name);
        dataRow('Email',        d.email);
        dataRow('Contact No.',  d.phone);
        dataRow('School',       d.school);
        dataRow('Region',       d.region);
        dataRow('Team Name',    d.team);
        dataRow('Category',     d.category);

        // Team members block
        if (d.members_list && d.members_list.length) {
          var memStr = d.members_list.map(function(m,i){
            return (i+1)+'. '+m.name+(m.grade?' — '+m.grade:'')+(m.birthdate?'  ('+m.birthdate+')':'');
          }).join('\n');
          dataRow('Team Members', memStr);
        }

        y += 4;
        hrule(y, 220,218,240, 0.4); y += 8;

        // ── PAYMENT DETAILS ───────────────────────────────────────
        sectionHeader('PAYMENT DETAILS');
        rowAlt = false;
        dataRow('Package',         d.pkg);
        dataRow('Payment Method',  d.method);

        y += 4;

        // Total amount highlight box
        fill(245,243,255); doc.roundedRect(margin, y-2, colR-margin, 18, 2, 2, 'F');
        draw(180,170,240); doc.setLineWidth(0.5); doc.roundedRect(margin, y-2, colR-margin, 18, 2, 2, 'S');
        normal(); size(8); rgb(100,90,180);
        doc.text('TOTAL AMOUNT PAID', margin+5, y+7);
        bold(); size(18); rgb(55,40,160);
        doc.text(d.amount || '—', colR-5, y+12, {align:'right'});

        y += 26;
        hrule(y, 220,218,240, 0.4); y += 8;

        // ── FOOTER NOTE ───────────────────────────────────────────
        normal(); size(7.5); rgb(140,135,175);
        var noteText = 'This receipt confirms that your registration and proof of payment have been received by the Philippine Robotics Cup 2026 organizing team. Your slot will be officially confirmed within 24 hours via email after payment verification. For inquiries, contact us at philippineroboticscup@gmail.com or +63 917 771 3961.';
        var noteLines = doc.splitTextToSize(noteText, colR - margin);
        doc.text(noteLines, margin, y);
        y += noteLines.length * 4.5 + 6;

        // ── PAGE FOOTER BAND ──────────────────────────────────────
        fill(245,244,252); doc.rect(0, pageH-16, pageW, 16, 'F');
        draw(210,207,240); doc.setLineWidth(0.4); line(0, pageH-16, pageW, pageH-16);

        normal(); size(7); rgb(120,115,160);
        doc.text('philippineroboticscup@gmail.com  ·  +63 917 771 3961', margin, pageH-7.5);
        bold(); size(7); rgb(80,70,160);
        doc.text('PRC 2026  ·  Creotec Philippines Inc.', colR, pageH-7.5, {align:'right'});

        // ── WATERMARK diagonal ───────────────────────────────────
        doc.saveGraphicsState();
        doc.setGState(new doc.GState({opacity: 0.04}));
        bold(); size(52); rgb(80,60,200);
        doc.text('PRC 2026', pageW/2, pageH/2, {align:'center', angle:45});
        doc.restoreGraphicsState();

        doc.save('PRC2026_Receipt_' + (d.ref || 'receipt') + '.pdf');

      } catch(err) {
        console.error('PDF error:', err);
        alert('Could not generate PDF. Please try again.');
      }

      btn.disabled = false;
      btn.innerHTML = '<i class="fi fi-rr-download"></i> Download Receipt (PDF)';
    }

    document.addEventListener('keydown', function(e){ if(e.key==='Escape') closeModal(); });
  </script>
</body>
</html>