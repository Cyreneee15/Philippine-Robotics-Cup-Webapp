<?php
// ══════════════════════════════════════════════════════════════════════════════
// sheets_sync.php  —  PRC 2026 · Google Sheets Sync Helper
//
// USAGE (include this file at the top of register.php, admin-registrations.php):
//   require_once 'sheets_sync.php';
//
// Then call after any DB write:
//   prc_sync_registration($payload);   // new/updated registration
//   prc_sync_status($ref, $payStatus, $regStatus); // status change only
//
// SETUP:
//   1. Deploy sheets_sync.gs as a Google Apps Script Web App.
//   2. Copy the "Web App URL" it gives you.
//   3. Paste it below as SHEETS_WEBHOOK_URL.
//   4. Replace SHEETS_SECRET with any random string — put the same value in
//      the .gs file's doPost() if you want basic auth (optional but recommended).
// ══════════════════════════════════════════════════════════════════════════════

// ─── CONFIGURE THESE TWO LINES ────────────────────────────────────────────────
define('SHEETS_WEBHOOK_URL', 'https://script.google.com/macros/s/AKfycbxFtSUi5OhJrRpvpDzUx0WMaLOTqOn917KxDs_RDvB5WCK_ltYtiv_xQFIai3h8yIer/exec');
define('SHEETS_SECRET',      'prc2026_sync_secret');   // optional shared secret
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Push a full registration record to Google Sheets.
 *
 * $reg array keys (all optional except 'ref'):
 *   ref, submitted_at, contact_name, contact_email, contact_phone,
 *   contact_role, school, school_region, team_name, category, package,
 *   amount, member_count, payment_method, payment_status, reg_status,
 *   notes, proof_link, players[]  (array of {name, grade, birthdate})
 *
 * Returns decoded JSON response array, or null on failure.
 */
function prc_sync_registration(array $reg): ?array {
    $payload = array_merge($reg, [
        'action' => 'upsert_registration',
        '_secret' => SHEETS_SECRET,
    ]);
    return _sheets_post($payload);
}

/**
 * Push a status-only update for an existing registration.
 *
 * @param string $ref          e.g. "PRC-123456"
 * @param string $payStatus    e.g. "Confirmed", "Rejected"
 * @param string $regStatus    e.g. "Active", "Cancelled"  (pass '' to skip)
 */
function prc_sync_status(string $ref, string $payStatus = '', string $regStatus = ''): ?array {
    return _sheets_post([
        'action'         => 'update_status',
        'ref'            => $ref,
        'payment_status' => $payStatus,
        'reg_status'     => $regStatus,
        '_secret'        => SHEETS_SECRET,
    ]);
}

/**
 * Internal: fire-and-forget POST to the Apps Script web app.
 * Uses a 6-second timeout so it never blocks the user's page load.
 */
function _sheets_post(array $data): ?array {
    $json = json_encode($data, JSON_UNESCAPED_UNICODE);

    $ch = curl_init(SHEETS_WEBHOOK_URL);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $json,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Content-Length: ' . strlen($json),
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,   // Apps Script redirects once
        CURLOPT_MAXREDIRS      => 5,
        CURLOPT_TIMEOUT        => 6,      // never stall the user
        CURLOPT_CONNECTTIMEOUT => 4,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr  = curl_error($ch);
    curl_close($ch);

    if ($curlErr) {
        error_log('[PRC Sheets Sync] cURL error: ' . $curlErr);
        return null;
    }
    if ($httpCode < 200 || $httpCode >= 300) {
        error_log('[PRC Sheets Sync] HTTP ' . $httpCode . ' — ' . substr($response, 0, 200));
        return null;
    }

    $decoded = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log('[PRC Sheets Sync] Bad JSON response: ' . substr($response, 0, 200));
        return null;
    }
    return $decoded;
}

// ═════════════════════════════════════════════════════════════════════════════
// INTEGRATION GUIDE
// ═════════════════════════════════════════════════════════════════════════════
//
// ── In register.php ───────────────────────────────────────────────────────────
//
// 1. At the very top of the POST handler, add:
//      require_once 'sheets_sync.php';
//
// 2. After the final $db->close(), call:
//
//    // On new registration (first submission, Pending Payment)
//    prc_sync_registration([
//        'ref'            => $ref,
//        'submitted_at'   => date('M d, Y h:i A'),
//        'contact_name'   => $cname,
//        'contact_email'  => $cemail,
//        'contact_phone'  => $cphone,
//        'contact_role'   => $crole,
//        'school'         => $school,
//        'school_region'  => $schoolReg,
//        'team_name'      => $team,
//        'category'       => $category,
//        'package'        => $package,
//        'amount'         => (string)$pkgAmount,
//        'member_count'   => $members_n,
//        'payment_method' => $payMethod,
//        'payment_status' => $payStatus,
//        'reg_status'     => 'Active',
//        'notes'          => $notes,
//        'proof_link'     => $proofLink,
//        'players'        => $data['members_list'] ?? [],
//    ]);
//
// ── In admin-registrations.php ────────────────────────────────────────────────
//
// 1. At the top, add:
//      require_once 'sheets_sync.php';
//
// 2. Inside the POST action handler, after the DB UPDATE, add:
//
//    if ($action === 'confirm') {
//        prc_sync_status($ref, 'Confirmed', 'Active');
//    } elseif ($action === 'reject') {
//        prc_sync_status($ref, 'Rejected', 'Cancelled');
//    } elseif ($action === 'cancel') {
//        prc_sync_status($ref, '', 'Cancelled');
//    } elseif ($action === 'reactivate') {
//        prc_sync_status($ref, '', 'Active');
//    }
//
// ═════════════════════════════════════════════════════════════════════════════