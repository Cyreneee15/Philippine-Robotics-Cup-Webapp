<?php
// ══════════════════════════════════════════════════════════════════════════════
// sync_to_sheets.php  —  PRC 2026 · Bulk DB → Google Sheets Sync
//
// Run this once (or anytime) to push ALL existing database records to Sheets.
// Access it in your browser or run via CLI:
//   php sync_to_sheets.php
//
// It reads every registration + player from prc_db and calls the
// Google Apps Script webhook in batches so you never hit timeout limits.
// ══════════════════════════════════════════════════════════════════════════════

// ─── Prevent running in production without auth ───────────────────────────────
// Remove or change this token once you're done syncing.
define('SYNC_TOKEN', 'prc2026_bulk_sync');

$providedToken = $_GET['token'] ?? (php_sapi_name() === 'cli' ? SYNC_TOKEN : '');
if ($providedToken !== SYNC_TOKEN) {
    http_response_code(403);
    die(json_encode(['error' => 'Unauthorized. Append ?token=' . SYNC_TOKEN . ' to the URL.']));
}

// ─── DB CONFIG (same as register.php) ────────────────────────────────────────
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'prc_db');

// ─── Sheets webhook (same as sheets_sync.php) ────────────────────────────────
define('SHEETS_WEBHOOK_URL', 'https://script.google.com/macros/s/AKfycbxFtSUi5OhJrRpvpDzUx0WMaLOTqOn917KxDs_RDvB5WCK_ltYtiv_xQFIai3h8yIer/exec');
define('SHEETS_SECRET',      'prc2026_sync_secret');

// ─── How many registrations to push per batch ────────────────────────────────
// Apps Script has a 6-minute execution limit. 10 per batch is safe.
define('BATCH_SIZE', 10);

// ══════════════════════════════════════════════════════════════════════════════
$isCli      = (php_sapi_name() === 'cli');
$jsonStream = (!$isCli && ($_GET['format'] ?? '') === 'json');
$nl         = $isCli ? "\n" : '<br>';

// Streaming JSON mode (called by the admin modal via XHR)
if ($jsonStream) {
    header('Content-Type: text/plain; charset=utf-8');
    header('X-Accel-Buffering: no'); // disable nginx buffering
    ini_set('output_buffering', 'off');
    ini_set('zlib.output_compression', 'off');
} else {
    header('Content-Type: text/html; charset=utf-8');
}

function out($msg, $type = 'info') {
    global $isCli, $jsonStream, $nl;
    if ($jsonStream) {
        echo json_encode(['type' => 'log', 'msg' => $msg, 'logType' => $type]) . "\n";
        ob_flush(); flush();
        return;
    }
    $icons = ['info' => '·', 'ok' => '✅', 'warn' => '⚠️', 'err' => '❌', 'head' => '══'];
    $icon  = $icons[$type] ?? '·';
    if (!$isCli) {
        $colors = ['info' => '#555', 'ok' => '#2E7D32', 'warn' => '#E65100', 'err' => '#C62828', 'head' => '#3949AB'];
        $color  = $colors[$type] ?? '#555';
        echo '<span style="color:' . $color . ';font-family:monospace;">' . $icon . ' ' . htmlspecialchars($msg) . '</span>' . $nl;
    } else {
        echo $icon . ' ' . $msg . $nl;
    }
    ob_flush(); flush();
}

function outStat($total, $done, $fail) {
    global $jsonStream;
    if (!$jsonStream) return;
    echo json_encode(['type' => 'stat', 'total' => $total, 'done' => $done, 'fail' => $fail]) . "\n";
    ob_flush(); flush();
}

// ── Print page shell (browser only, not JSON stream) ─────────────────────────
if (!$isCli && !$jsonStream) {
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>PRC 2026 — Bulk Sync to Google Sheets</title>
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
  body {
    font-family: "Google Sans", "Segoe UI", Arial, sans-serif;
    background: #F8F9FA;
    color: #202124;
    min-height: 100vh;
    display: flex;
    align-items: flex-start;
    justify-content: center;
    padding: 40px 16px 80px;
  }
  .card {
    background: #fff;
    border: 1px solid #E0E0E0;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.06);
    max-width: 780px;
    width: 100%;
    padding: 36px 40px;
  }
  .logo-row {
    display: flex;
    align-items: center;
    gap: 14px;
    margin-bottom: 28px;
    padding-bottom: 20px;
    border-bottom: 1px solid #E0E0E0;
  }
  .logo-icon {
    width: 44px; height: 44px;
    background: #3949AB;
    border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    font-size: 1.4rem; color: #fff;
  }
  .logo-title { font-size: 1.15rem; font-weight: 700; color: #202124; line-height: 1.2; }
  .logo-sub   { font-size: 0.78rem; color: #9E9E9E; margin-top: 2px; }
  .section-label {
    font-size: 0.68rem;
    font-weight: 700;
    letter-spacing: 0.12em;
    text-transform: uppercase;
    color: #3949AB;
    margin: 24px 0 10px;
    display: flex; align-items: center; gap: 6px;
  }
  .section-label::before { content: "//"; color: #C5CAE9; }
  .log-box {
    background: #FAFAFA;
    border: 1px solid #E0E0E0;
    border-radius: 8px;
    padding: 18px 20px;
    font-family: "Courier New", monospace;
    font-size: 0.82rem;
    line-height: 1.9;
    max-height: 480px;
    overflow-y: auto;
    margin-bottom: 20px;
  }
  .stat-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(130px, 1fr));
    gap: 12px;
    margin-bottom: 24px;
  }
  .stat-box {
    border: 1px solid #E0E0E0;
    border-radius: 8px;
    padding: 14px 16px;
    text-align: center;
  }
  .stat-num {
    font-size: 1.80rem;
    font-weight: 800;
    color: #3949AB;
    line-height: 1;
    display: block;
  }
  .stat-label {
    font-size: 0.68rem;
    font-weight: 600;
    color: #9E9E9E;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    margin-top: 4px;
    display: block;
  }
  .notice {
    background: #E8F5E9;
    border: 1px solid #A5D6A7;
    border-radius: 8px;
    padding: 14px 18px;
    font-size: 0.85rem;
    color: #1B5E20;
    margin-top: 20px;
    display: flex; align-items: flex-start; gap: 10px;
  }
  .notice-warn {
    background: #FFF8E1;
    border-color: #FFE082;
    color: #E65100;
  }
  footer {
    margin-top: 28px;
    padding-top: 16px;
    border-top: 1px solid #E0E0E0;
    font-size: 0.72rem;
    color: #BDBDBD;
    text-align: center;
  }
</style>
</head><body><div class="card">
<div class="logo-row">
  <div class="logo-icon">📋</div>
  <div>
    <div class="logo-title">PRC 2026 — Bulk DB → Google Sheets Sync</div>
    <div class="logo-sub">Reads all registrations from prc_db and pushes them to your connected spreadsheet</div>
  </div>
</div>';
}

// ─── 1. CONNECT TO DATABASE ───────────────────────────────────────────────────
if (!$isCli && !$jsonStream) echo '<div class="section-label">Database</div><div class="log-box" id="log">';

out('Connecting to ' . DB_NAME . ' on ' . DB_HOST . '...');
$db = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($db->connect_error) {
    out('DB connection failed: ' . $db->connect_error, 'err');
    die();
}
$db->set_charset('utf8mb4');
out('Connected.', 'ok');

// ─── 2. FETCH ALL REGISTRATIONS + PLAYERS ────────────────────────────────────
$sql = "
    SELECT
        r.reg_id, r.ref_no, r.submitted_at,
        r.contact_name, r.contact_email, r.contact_phone, r.contact_role,
        r.package_name, r.package_amount, r.member_count,
        r.payment_method, r.payment_status, r.reg_status,
        r.proof_link, r.notes,
        s.school_name, s.school_region,
        t.team_id, t.team_name, t.category, t.package
    FROM prc_registrations r
    JOIN prc_reg_schools s ON s.school_id = r.school_id
    JOIN prc_reg_teams   t ON t.team_id   = r.team_id
    ORDER BY r.submitted_at ASC
";
$result = $db->query($sql);
$registrations = [];
while ($row = $result->fetch_assoc()) { $registrations[] = $row; }

// Fetch all players indexed by team_id
$plResult   = $db->query("SELECT * FROM prc_reg_players ORDER BY player_id ASC");
$playersMap = [];
while ($p = $plResult->fetch_assoc()) {
    $playersMap[$p['team_id']][] = $p;
}
$db->close();

$totalRegs    = count($registrations);
$totalPlayers = array_sum(array_map('count', $playersMap));

out("Found {$totalRegs} registration(s) and {$totalPlayers} player(s) in the database.", 'ok');
outStat($totalRegs, 0, 0);

if (!$isCli && !$jsonStream) {
    echo '</div>'; // close log-box

    // Stat grid
    echo '<div class="stat-grid">
      <div class="stat-box"><span class="stat-num">' . $totalRegs . '</span><span class="stat-label">Registrations</span></div>
      <div class="stat-box"><span class="stat-num">' . $totalPlayers . '</span><span class="stat-label">Players</span></div>
      <div class="stat-box"><span class="stat-num">' . ceil($totalRegs / BATCH_SIZE) . '</span><span class="stat-label">Batches</span></div>
    </div>';

    echo '<div class="section-label">Sync Progress</div><div class="log-box">';
}

if ($totalRegs === 0) {
    out('No registrations to sync. Database is empty.', 'warn');
    if (!$isCli && !$jsonStream) { echo '</div>'; }
} else {

    // ─── 3. PUSH IN BATCHES ───────────────────────────────────────────────────
    $batches   = array_chunk($registrations, BATCH_SIZE);
    $succeeded = 0;
    $failed    = 0;
    $batchNum  = 0;

    foreach ($batches as $batch) {
        $batchNum++;
        out("Batch {$batchNum}/" . count($batches) . " — pushing " . count($batch) . " record(s)...");

        foreach ($batch as $r) {
            $players = $playersMap[$r['team_id']] ?? [];
            $playerList = array_map(function($p) {
                return [
                    'name'      => $p['player_name']      ?? '',
                    'grade'     => $p['player_grade']     ?? '',
                    'birthdate' => $p['player_birthdate'] ?? '',
                ];
            }, $players);

            $payload = [
                'action'         => 'upsert_registration',
                '_secret'        => SHEETS_SECRET,
                'ref'            => $r['ref_no']         ?? '',
                'submitted_at'   => $r['submitted_at']   ? date('M d, Y h:i A', strtotime($r['submitted_at'])) : '',
                'contact_name'   => $r['contact_name']   ?? '',
                'contact_email'  => $r['contact_email']  ?? '',
                'contact_phone'  => $r['contact_phone']  ?? '',
                'contact_role'   => $r['contact_role']   ?? '',
                'school'         => $r['school_name']    ?? '',
                'school_region'  => $r['school_region']  ?? '',
                'team_name'      => $r['team_name']      ?? '',
                'category'       => $r['category']       ?? '',
                'package'        => $r['package']        ?? $r['package_name'] ?? '',
                'amount'         => (string)($r['package_amount'] ?? 0),
                'member_count'   => (int)($r['member_count'] ?? count($playerList)),
                'payment_method' => $r['payment_method'] ?? 'Pending',
                'payment_status' => $r['payment_status'] ?? 'Pending Payment',
                'reg_status'     => $r['reg_status']     ?? 'Active',
                'notes'          => $r['notes']          ?? '',
                'proof_link'     => $r['proof_link']     ?? '',
                'players'        => $playerList,
            ];

            $response = _sheets_post_sync($payload);

            if ($response && ($response['ok'] ?? false)) {
                $succeeded++;
                out('  ✔ ' . ($r['ref_no'] ?? '?') . ' — ' . ($r['team_name'] ?? '?') . ' (' . count($playerList) . ' player(s))', 'ok');
            } else {
                $failed++;
                $errMsg = $response['error'] ?? 'No response / timeout';
                out('  ✘ ' . ($r['ref_no'] ?? '?') . ' — ' . $errMsg, 'err');
            }
            outStat($totalRegs, $succeeded, $failed);

            // Small pause between records to avoid hammering Apps Script
            usleep(300000); // 0.3 s
        }

        // Slightly longer pause between batches
        if ($batchNum < count($batches)) {
            out("  Pausing 2s before next batch...");
            sleep(2);
        }
    }

    // ─── 4. SUMMARY ──────────────────────────────────────────────────────────
    if (!$isCli && !$jsonStream) echo '</div>'; // close log-box

    out('');
    out('══════════════════════════════════', 'head');
    out("SYNC COMPLETE — {$succeeded} succeeded, {$failed} failed out of {$totalRegs} total.", $failed > 0 ? 'warn' : 'ok');
    out('══════════════════════════════════', 'head');

    if (!$isCli && !$jsonStream) {
        $noticeClass = $failed > 0 ? 'notice notice-warn' : 'notice';
        $icon        = $failed > 0 ? '⚠️' : '✅';
        $msg         = $failed > 0
            ? "{$succeeded} of {$totalRegs} records synced. {$failed} failed — check the Sync Log sheet for details."
            : "All {$succeeded} registration(s) and their players have been synced to Google Sheets successfully.";
        echo '<div class="' . $noticeClass . '"><span>' . $icon . '</span><span>' . htmlspecialchars($msg) . '</span></div>';
    }
}

if (!$isCli && !$jsonStream) {
    echo '<footer>PRC 2026 · Creotec Philippines Inc. · sync_to_sheets.php</footer></div></body></html>';
}

// ─── HTTP POST HELPER (standalone — no dependency on sheets_sync.php) ─────────
function _sheets_post_sync(array $data): ?array {
    $json = json_encode($data, JSON_UNESCAPED_UNICODE);
    $ch   = curl_init(SHEETS_WEBHOOK_URL);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $json,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Content-Length: ' . strlen($json),
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 5,
        CURLOPT_TIMEOUT        => 15,      // longer timeout for bulk
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr  = curl_error($ch);
    curl_close($ch);

    if ($curlErr || $httpCode < 200 || $httpCode >= 300) {
        return ['ok' => false, 'error' => $curlErr ?: "HTTP {$httpCode}"];
    }
    $decoded = json_decode($response, true);
    return is_array($decoded) ? $decoded : ['ok' => false, 'error' => 'Bad JSON'];
}