<?php
// PRC-WebApp/shop.php
// ── DB ──────────────────────────────────────────────────────────
$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'prc_db';

// ── GOOGLE SHEETS WEB APP URL ────────────────────────────────────
// Replace this with YOUR deployed Apps Script Web App URL
define('GSHEET_WEBHOOK_URL', 'https://script.google.com/macros/s/AKfycbz8KBnsnWYLAflfa7bsAEhQXN9UusyWPJsIJ9KqdrE3W_KFHhrNboagfaEz_YlK-xna/exec');

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($conn->connect_error) {
    die('<p style="color:#ff6b6b;padding:40px;font-family:monospace;">DB error: ' . htmlspecialchars($conn->connect_error) . '</p>');
}

// ── Google Sheets Sync Helper ────────────────────────────────────
function syncToSheets(string $action, array $payload): void {
    $payload['action'] = $action;
    $ch = curl_init(GSHEET_WEBHOOK_URL);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query($payload),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    curl_exec($ch); // fire-and-forget; errors are non-fatal
    curl_close($ch);
}

// ── Handle AJAX order submission ─────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'place_order') {
    header('Content-Type: application/json');

    $name    = trim($_POST['customer_name']  ?? '');
    $email   = trim($_POST['customer_email'] ?? '');
    $phone   = trim($_POST['customer_phone'] ?? '');
    $school  = trim($_POST['school_org']     ?? '');
    $notes   = trim($_POST['notes']          ?? '');
    $method  = trim($_POST['payment_method'] ?? 'GCash');
    $total   = floatval($_POST['total_amount'] ?? 0);
    $itemsJson = $_POST['items'] ?? '[]';
    $items   = json_decode($itemsJson, true);

    if (!$name || !$email || !$phone || !$school || !$items) {
        echo json_encode(['success' => false, 'message' => 'Missing required fields.']);
        exit;
    }

    // Handle proof upload
    $proofData = null;
    $proofMime = null;
    $proofName = null;
    if (!empty($_FILES['proof']['tmp_name'])) {
        $proofData = file_get_contents($_FILES['proof']['tmp_name']);
        $proofMime = $_FILES['proof']['type'];
        $proofName = basename($_FILES['proof']['name']);
        if (strlen($proofData) > 5 * 1024 * 1024) {
            echo json_encode(['success' => false, 'message' => 'Proof file too large (max 5MB).']);
            exit;
        }
    }

    $ref = 'ORD-' . strtoupper(substr(md5(uniqid()), 0, 8));

    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare(
            "INSERT INTO prc_shop_orders
             (order_ref,customer_name,customer_email,customer_phone,school_org,notes,total_amount,payment_method,proof_filename,proof_data,proof_mimetype)
             VALUES (?,?,?,?,?,?,?,?,?,?,?)"
        );
        $stmt->bind_param('ssssssdssbs',
            $ref, $name, $email, $phone, $school, $notes,
            $total, $method, $proofName, $proofData, $proofMime
        );
        $stmt->execute();
        $orderId = $conn->insert_id;
        $stmt->close();

        // Insert order items
        $si = $conn->prepare(
            "INSERT INTO prc_shop_order_items (order_id,product_id,product_name,unit_price,quantity,subtotal)
             VALUES (?,?,?,?,?,?)"
        );
        foreach ($items as $item) {
            $pid    = intval($item['product_id'] ?? 0) ?: null;
            $pname  = $item['product_name'] ?? '';
            $uprice = floatval($item['unit_price'] ?? 0);
            $qty    = intval($item['quantity'] ?? 1);
            $sub    = $uprice * $qty;
            $si->bind_param('iisddd', $orderId, $pid, $pname, $uprice, $qty, $sub);
            $si->execute();
        }
        $si->close();

        $conn->commit();

        // ── Sync to Google Sheets (non-blocking) ──────────────────
        // Build items summary string for the sheet
        $itemsSummary = array_map(
            fn($i) => ($i['product_name'] ?? '') . ' x' . ($i['quantity'] ?? 1) . ' = ₱' . number_format(($i['subtotal'] ?? 0), 2),
            $items
        );
        syncToSheets('new_order', [
            'order_id'       => $orderId,
            'order_ref'      => $ref,
            'customer_name'  => $name,
            'customer_email' => $email,
            'customer_phone' => $phone,
            'school_org'     => $school,
            'notes'          => $notes,
            'payment_method' => $method,
            'total_amount'   => $total,
            'order_status'   => 'pending',
            'created_at'     => date('Y-m-d H:i:s'),
            'items'          => json_encode(array_map(fn($i) => [
                'product_name' => $i['product_name'] ?? '',
                'unit_price'   => $i['unit_price']   ?? 0,
                'quantity'     => $i['quantity']      ?? 1,
                'subtotal'     => ($i['unit_price'] ?? 0) * ($i['quantity'] ?? 1),
            ], $items)),
        ]);
        // ── End Sheets sync ──────────────────────────────────────

        echo json_encode(['success' => true, 'ref' => $ref, 'email' => $email]);

    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => 'Server error. Please try again.']);
    }
    $conn->close();
    exit;
}

// ── Fetch all active products ────────────────────────────────────
$products = [];
$pr = $conn->query("SELECT * FROM prc_shop_products WHERE is_active = 1 ORDER BY sort_order ASC, product_id ASC");
if ($pr) while ($row = $pr->fetch_assoc()) $products[] = $row;

// ── Fetch specs keyed by product_id ─────────────────────────────
$specs_by_product = [];
$sr = $conn->query("SELECT * FROM prc_shop_product_specs ORDER BY sort_order ASC");
if ($sr) while ($row = $sr->fetch_assoc()) $specs_by_product[$row['product_id']][] = $row;

// ── Build distinct category list ────────────────────────────────
$categories = [];
foreach ($products as $p) {
    if (!in_array($p['category'], $categories)) $categories[] = $p['category'];
}

$conn->close();

// ── The rest of shop.php (HTML/JS) is UNCHANGED from your original ──
// Include your existing shop.php HTML below this line.
// Nothing else needs to change in the front-end.
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Shop — Philippine Robotics Cup 2026</title>
  <link rel="icon" type="image/png" href="assets/favicon.png" />
  <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;600;700;800;900&family=Exo+2:wght@300;400;500;600;700&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="https://cdn-uicons.flaticon.com/uicons-regular-rounded/css/uicons-regular-rounded.css">
  <link rel="stylesheet" href="https://cdn-uicons.flaticon.com/uicons-solid-rounded/css/uicons-solid-rounded.css">
  <link rel="stylesheet" href="https://cdn-uicons.flaticon.com/uicons-brands/css/uicons-brands.css">
  <style>
    :root{
      --prc-violet:#8B7EFF;--prc-ice:#C4EEFF;
      --creo-purple:#7733FF;--creo-amber:#FFA030;--creo-volt:#FFE930;--creo-sky:#44D9FF;
      --neon-primary:var(--prc-violet);
      --bg-void:#03020D;--bg-deep:#06051A;
      --border-neon:rgba(139,126,255,0.22);
      --glow-primary:0 0 18px rgba(139,126,255,0.60),0 0 55px rgba(139,126,255,0.20);
      --text-high:#F2EEFF;--text-mid:#C8C0F0;--text-soft:#9A90CC;--text-dim:#7068A8;
      --nav-height:72px;--font-hud:'Orbitron',monospace;--font-body:'Exo 2',sans-serif;
    }
    *,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
    html{scroll-behavior:smooth;}
    body{font-family:var(--font-body);background:var(--bg-void);color:var(--text-high);overflow-x:hidden;line-height:1.6;cursor:none;}
    img{max-width:100%;display:block;}
    a{text-decoration:none;color:inherit;}
    ul{list-style:none;}
    button{font-family:inherit;cursor:none;border:none;background:none;}

    /* CURSOR */
    .cursor-dot{position:fixed;width:8px;height:8px;border-radius:50%;background:var(--neon-primary);pointer-events:none;z-index:99999;transform:translate(-50%,-50%);box-shadow:var(--glow-primary);transition:transform 0.1s,background 0.2s;}
    .cursor-ring{position:fixed;width:36px;height:36px;border-radius:50%;border:1px solid rgba(139,126,255,0.65);pointer-events:none;z-index:99998;transform:translate(-50%,-50%);transition:width 0.25s,height 0.25s,border-color 0.25s,transform 0.08s;}
    .cursor-ring.hovered{width:56px;height:56px;border-color:var(--creo-amber);border-width:1.5px;}

    body::after{content:'';position:fixed;inset:0;z-index:9998;pointer-events:none;background:repeating-linear-gradient(to bottom,transparent,transparent 2px,rgba(0,0,0,0.04) 2px,rgba(0,0,0,0.04) 4px);}
    .hex-grid{position:fixed;inset:0;z-index:0;pointer-events:none;background-image:linear-gradient(rgba(139,126,255,0.04) 1px,transparent 1px),linear-gradient(90deg,rgba(139,126,255,0.04) 1px,transparent 1px);background-size:50px 50px;}
    .hex-grid::before{content:'';position:absolute;inset:0;background:radial-gradient(ellipse 80% 60% at 50% 0%,rgba(119,51,255,0.14) 0%,transparent 70%);}

    @keyframes neonPulse{0%,100%{opacity:1;}50%{opacity:0.7;}}
    @keyframes scanDown{from{transform:translateY(-100%);}to{transform:translateY(100vh);}}

    /* NAV */
    #main-nav{position:fixed;top:0;left:0;right:0;height:var(--nav-height);z-index:1000;background:rgba(3,2,13,0.94);backdrop-filter:blur(20px);border-bottom:1px solid var(--border-neon);box-shadow:0 0 30px rgba(139,126,255,0.10);}
    .nav-inner{max-width:1340px;margin:0 auto;height:100%;padding:0 36px;display:flex;align-items:center;justify-content:space-between;gap:16px;}
    .nav-logo{display:flex;align-items:center;gap:12px;flex-shrink:0;}
    .nav-logo img{height:38px;width:auto;transition:filter 0.3s;}
    .nav-logo:hover img{filter:drop-shadow(0 0 14px rgba(139,126,255,0.75));}
    .nav-brand{font-family:var(--font-hud);font-weight:700;font-size:0.72rem;letter-spacing:0.06em;line-height:1.3;color:var(--prc-violet);text-shadow:0 0 12px rgba(139,126,255,0.65);}
    .nav-brand span{color:var(--text-soft);display:block;font-size:0.58rem;font-weight:400;letter-spacing:0.10em;text-transform:uppercase;margin-top:1px;}
    .nav-links{display:flex;align-items:center;gap:2px;}
    .nav-links a{font-family:var(--font-hud);font-size:0.65rem;font-weight:600;color:var(--text-mid);padding:8px 14px;letter-spacing:0.08em;text-transform:uppercase;border-radius:4px;transition:all 0.2s;white-space:nowrap;}
    .nav-links a:hover{color:var(--prc-violet);text-shadow:0 0 12px rgba(139,126,255,0.85);}
    .nav-cta{background:transparent!important;border:1px solid var(--prc-violet)!important;color:var(--prc-violet)!important;padding:8px 20px!important;border-radius:3px!important;box-shadow:0 0 15px rgba(139,126,255,0.28),inset 0 0 15px rgba(139,126,255,0.06)!important;transition:all 0.25s!important;margin-left:8px;clip-path:polygon(6px 0%,100% 0%,calc(100% - 6px) 100%,0% 100%);}
    .nav-cta:hover{background:rgba(139,126,255,0.12)!important;color:#fff!important;}
    .nav-hamburger{display:none;flex-direction:column;justify-content:center;align-items:center;gap:5px;width:44px;height:44px;padding:0;cursor:none;background:rgba(139,126,255,0.06);border:1px solid var(--border-neon);border-radius:4px;flex-shrink:0;z-index:1002;-webkit-tap-highlight-color:transparent;touch-action:manipulation;transition:all 0.2s;}
    .nav-hamburger:hover{background:rgba(139,126,255,0.14);box-shadow:0 0 14px rgba(139,126,255,0.28);}
    .nav-hamburger span{width:20px;height:1.5px;background:var(--prc-violet);border-radius:2px;transition:transform 0.28s,opacity 0.28s;display:block;pointer-events:none;}
    .nav-hamburger.open span:nth-child(1){transform:rotate(45deg) translate(5px,5px);}
    .nav-hamburger.open span:nth-child(2){opacity:0;}
    .nav-hamburger.open span:nth-child(3){transform:rotate(-45deg) translate(5px,-5px);}
    .nav-mobile{display:none;position:fixed;top:var(--nav-height);left:0;right:0;background:rgba(3,2,13,0.98);backdrop-filter:blur(20px);border-bottom:1px solid var(--border-neon);padding:12px 18px 24px;z-index:1000;flex-direction:column;gap:2px;box-shadow:0 20px 60px rgba(139,126,255,0.09);}
    .nav-mobile.open{display:flex;}
    .nav-mobile a{font-family:var(--font-hud);font-size:0.70rem;font-weight:600;color:var(--text-mid);padding:13px 14px;border-radius:3px;letter-spacing:0.08em;text-transform:uppercase;transition:all 0.2s;display:flex;align-items:center;gap:12px;}
    .nav-mobile a i{font-size:1rem;color:var(--prc-violet);}
    .nav-mobile a:hover{color:var(--prc-violet);background:rgba(139,126,255,0.07);text-shadow:0 0 10px rgba(139,126,255,0.55);}
    .nav-mobile .nav-cta{border:1px solid var(--prc-violet)!important;color:var(--prc-violet)!important;margin-top:10px;justify-content:center;border-radius:3px!important;clip-path:none!important;}

    /* BREADCRUMB */
    .breadcrumb-bar{margin-top:var(--nav-height);padding:14px 0;border-bottom:1px solid var(--border-neon);background:rgba(139,126,255,0.02);}
    .breadcrumb-inner{max-width:1340px;margin:0 auto;padding:0 36px;display:flex;align-items:center;gap:10px;font-family:var(--font-hud);font-size:0.58rem;letter-spacing:0.10em;text-transform:uppercase;}
    .breadcrumb-inner a{color:var(--text-dim);transition:color 0.2s;}
    .breadcrumb-inner a:hover{color:var(--prc-violet);}
    .breadcrumb-sep{color:var(--text-dim);font-size:0.52rem;}
    .breadcrumb-current{color:var(--creo-amber);}

    /* SHOP WRAP */
    .shop-wrap{max-width:1100px;margin:0 auto;padding:48px 36px 90px;position:relative;z-index:1;}
    .shop-topbar{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:16px;margin-bottom:44px;}
    .section-eyebrow{display:inline-flex;align-items:center;gap:8px;font-family:var(--font-hud);color:var(--prc-ice);font-size:0.60rem;font-weight:700;letter-spacing:0.20em;text-transform:uppercase;margin-bottom:16px;}
    .section-eyebrow::before{content:'//';color:rgba(139,126,255,0.40);font-size:0.70rem;}
    .section-title{font-family:var(--font-hud);font-size:clamp(1.8rem,3.8vw,2.8rem);font-weight:800;letter-spacing:-0.01em;line-height:1.08;margin-bottom:14px;color:#fff;}
    .section-title .accent{color:var(--prc-violet);text-shadow:0 0 18px rgba(139,126,255,0.65);}
    .shop-count{font-family:var(--font-hud);font-size:0.54rem;color:var(--text-dim);letter-spacing:0.10em;}

    /* FILTERS */
    .shop-filters{display:flex;gap:6px;flex-wrap:wrap;}
    .shop-filter-btn{font-family:var(--font-hud);font-size:0.58rem;font-weight:700;letter-spacing:0.10em;text-transform:uppercase;padding:7px 16px;border:1px solid rgba(139,126,255,0.24);color:var(--text-soft);background:transparent;clip-path:polygon(5px 0%,100% 0%,calc(100% - 5px) 100%,0% 100%);cursor:pointer;transition:all 0.2s;}
    .shop-filter-btn:hover,.shop-filter-btn.active{border-color:var(--prc-violet);color:var(--prc-violet);background:rgba(139,126,255,0.08);box-shadow:0 0 12px rgba(139,126,255,0.22);}

    /* CART BUTTON */
    .cart-btn{display:inline-flex;align-items:center;gap:9px;font-family:var(--font-hud);font-size:0.58rem;font-weight:700;letter-spacing:0.10em;text-transform:uppercase;padding:9px 20px;border:1px solid rgba(255,160,48,0.40);color:var(--creo-amber);background:rgba(255,160,48,0.05);clip-path:polygon(6px 0%,100% 0%,calc(100% - 6px) 100%,0% 100%);cursor:pointer;transition:all 0.22s;position:relative;}
    .cart-btn:hover{background:rgba(255,160,48,0.12);box-shadow:0 0 18px rgba(255,160,48,0.30);border-color:var(--creo-amber);}
    .cart-badge{position:absolute;top:-6px;right:-6px;width:18px;height:18px;border-radius:50%;background:var(--creo-amber);color:var(--bg-void);font-family:var(--font-hud);font-size:0.48rem;font-weight:900;display:flex;align-items:center;justify-content:center;transition:transform 0.2s;}
    .cart-badge.bump{transform:scale(1.4);}

    /* PRODUCT LIST */
    .shop-list{display:flex;flex-direction:column;gap:14px;}
    .product-row{background:rgba(0,0,8,0.55);border:1px solid rgba(139,126,255,0.13);position:relative;overflow:hidden;transition:border-color 0.3s,box-shadow 0.3s;display:grid;grid-template-columns:190px 1fr auto;align-items:stretch;}
    .product-row::before{content:"";position:absolute;top:0;left:0;bottom:0;width:3px;background:var(--prc-violet);opacity:0.45;transition:opacity 0.3s;}
    .product-row:hover{border-color:rgba(139,126,255,0.36);box-shadow:0 0 32px rgba(139,126,255,0.10);}
    .product-row:hover::before{opacity:1;}

    /* IMAGE */
    .product-img-wrap{position:relative;overflow:hidden;background:rgba(139,126,255,0.05);border-right:1px solid rgba(139,126,255,0.10);display:flex;align-items:center;justify-content:center;min-height:160px;}
    .product-img-wrap img{width:100%;height:100%;object-fit:cover;filter:brightness(0.85) saturate(0.75);transition:transform 0.45s,filter 0.35s;}
    .product-row:hover .product-img-wrap img{transform:scale(1.05);filter:brightness(1.0) saturate(1.1);}
    .product-img-placeholder{display:flex;flex-direction:column;align-items:center;justify-content:center;width:100%;height:100%;min-height:160px;font-family:var(--font-hud);font-size:0.52rem;letter-spacing:0.14em;text-transform:uppercase;color:rgba(139,126,255,0.30);gap:10px;}
    .product-img-placeholder i{font-size:1.8rem;color:rgba(139,126,255,0.20);}
    .product-tag-img{position:absolute;top:10px;left:10px;font-family:var(--font-hud);font-size:0.46rem;font-weight:700;letter-spacing:0.12em;text-transform:uppercase;padding:3px 9px;background:rgba(3,2,13,0.80);border:1px solid rgba(139,126,255,0.30);color:var(--prc-violet);clip-path:polygon(4px 0%,100% 0%,calc(100% - 4px) 100%,0% 100%);}

    /* PRODUCT BODY */
    .product-body{padding:24px 24px 22px;display:flex;flex-direction:column;}
    .product-eyebrow{font-family:var(--font-hud);font-size:0.48rem;font-weight:700;letter-spacing:0.16em;text-transform:uppercase;color:var(--text-dim);margin-bottom:6px;}
    .product-eyebrow::before{content:"// ";color:rgba(139,126,255,0.30);}
    .product-name{font-family:var(--font-hud);font-size:1.02rem;font-weight:800;letter-spacing:0.03em;color:var(--text-high);margin-bottom:4px;text-shadow:0 0 14px rgba(139,126,255,0.22);line-height:1.2;}
    .product-price{font-family:var(--font-hud);font-size:1.18rem;font-weight:900;color:var(--prc-violet);text-shadow:0 0 14px rgba(139,126,255,0.50);margin-bottom:10px;display:inline-flex;align-items:baseline;gap:5px;}
    .product-desc{font-size:0.858rem;color:var(--text-mid);line-height:1.70;margin-bottom:16px;flex:1;}
    .product-specs{display:flex;flex-wrap:wrap;gap:5px;margin-bottom:16px;}
    .product-spec{font-family:var(--font-hud);font-size:0.48rem;font-weight:600;letter-spacing:0.07em;text-transform:uppercase;padding:2px 8px;border:1px solid rgba(139,126,255,0.18);color:var(--text-soft);background:rgba(139,126,255,0.04);}
    .product-actions{display:flex;gap:8px;align-items:center;flex-wrap:wrap;}

    /* PRODUCT SIDE (qty) */
    .product-side{padding:22px 22px 22px 18px;border-left:1px solid rgba(139,126,255,0.09);display:flex;flex-direction:column;align-items:flex-end;justify-content:space-between;min-width:130px;gap:12px;background:rgba(139,126,255,0.015);}
    .qty-wrap{display:flex;align-items:center;gap:0;border:1px solid rgba(139,126,255,0.20);}
    .qty-btn{width:28px;height:28px;display:flex;align-items:center;justify-content:center;font-family:var(--font-hud);font-size:0.80rem;color:var(--prc-violet);background:rgba(139,126,255,0.06);cursor:pointer;border:none;transition:all 0.18s;user-select:none;}
    .qty-btn:hover{background:rgba(139,126,255,0.16);}
    .qty-val{width:36px;text-align:center;font-family:var(--font-hud);font-size:0.70rem;font-weight:700;color:var(--text-high);background:transparent;border:none;border-left:1px solid rgba(139,126,255,0.18);border-right:1px solid rgba(139,126,255,0.18);padding:0;height:28px;line-height:28px;}

    /* STOCK BADGES */
    .stock-badge{font-family:var(--font-hud);font-size:0.46rem;font-weight:700;letter-spacing:0.10em;text-transform:uppercase;padding:2px 9px;border:1px solid;display:inline-flex;align-items:center;gap:5px;}
    .stock-badge.in-stock{color:var(--creo-volt);border-color:rgba(255,233,48,0.30);background:rgba(255,233,48,0.04);}
    .stock-badge.low-stock{color:var(--creo-amber);border-color:rgba(255,160,48,0.30);background:rgba(255,160,48,0.04);}
    .stock-badge.out-of-stock{color:#FF6B6B;border-color:rgba(255,107,107,0.30);background:rgba(255,107,107,0.04);}
    .stock-dot{width:5px;height:5px;border-radius:50%;background:currentColor;flex-shrink:0;animation:neonPulse 1.4s ease-in-out infinite;}

    /* ACTION BUTTONS */
    .btn-view-item{font-family:var(--font-hud);font-size:0.54rem;font-weight:700;letter-spacing:0.10em;text-transform:uppercase;padding:8px 16px;border:1px solid rgba(139,126,255,0.26);color:var(--text-soft);background:transparent;clip-path:polygon(5px 0%,100% 0%,calc(100% - 5px) 100%,0% 100%);cursor:pointer;transition:all 0.22s;}
    .btn-view-item:hover{border-color:var(--prc-violet);color:var(--prc-violet);background:rgba(139,126,255,0.07);}
    .btn-add-cart{font-family:var(--font-hud);font-size:0.54rem;font-weight:700;letter-spacing:0.10em;text-transform:uppercase;padding:8px 20px;border:1px solid var(--creo-amber);color:var(--creo-amber);background:transparent;clip-path:polygon(5px 0%,100% 0%,calc(100% - 5px) 100%,0% 100%);box-shadow:0 0 10px rgba(255,160,48,0.18);cursor:pointer;transition:all 0.22s;position:relative;overflow:hidden;}
    .btn-add-cart::before{content:"";position:absolute;inset:0;background:linear-gradient(90deg,transparent,rgba(255,160,48,0.14),transparent);transform:translateX(-100%);transition:transform 0.4s;}
    .btn-add-cart:hover{background:rgba(255,160,48,0.10);box-shadow:0 0 22px rgba(255,160,48,0.38);color:#fff;transform:translateY(-1px);}
    .btn-add-cart:hover::before{transform:translateX(100%);}
    .btn-add-cart.added{border-color:var(--creo-volt);color:var(--creo-volt);box-shadow:0 0 14px rgba(255,233,48,0.30);}
    .btn-order-now{font-family:var(--font-hud);font-size:0.54rem;font-weight:700;letter-spacing:0.10em;text-transform:uppercase;padding:8px 20px;border:1px solid var(--prc-violet);color:var(--prc-violet);background:transparent;clip-path:polygon(5px 0%,100% 0%,calc(100% - 5px) 100%,0% 100%);box-shadow:0 0 10px rgba(139,126,255,0.18);cursor:pointer;transition:all 0.22s;}
    .btn-order-now:hover{background:rgba(139,126,255,0.10);box-shadow:0 0 22px rgba(139,126,255,0.38);color:#fff;transform:translateY(-1px);}
    .btn-order-now:disabled,.btn-add-cart:disabled{opacity:0.38;pointer-events:none;}

    /* PRODUCT DETAIL MODAL */
    .prod-modal-overlay{position:fixed;inset:0;z-index:9000;background:rgba(3,2,13,0.90);backdrop-filter:blur(10px);display:none;align-items:center;justify-content:center;padding:24px;}
    .prod-modal-overlay.open{display:flex;}
    .prod-modal{background:var(--bg-deep);border:1px solid var(--border-neon);max-width:780px;width:100%;max-height:88vh;overflow-y:auto;position:relative;}
    .prod-modal::before{content:"";position:absolute;top:0;left:0;right:0;height:1px;background:linear-gradient(90deg,transparent,var(--prc-violet),transparent);}
    .prod-modal-hdr{padding:20px 26px 16px;border-bottom:1px solid var(--border-neon);background:rgba(139,126,255,0.06);display:flex;align-items:center;justify-content:space-between;gap:12px;}
    .prod-modal-title{font-family:var(--font-hud);font-size:0.78rem;font-weight:700;letter-spacing:0.08em;color:var(--prc-violet);text-shadow:0 0 10px rgba(139,126,255,0.50);}
    .prod-modal-close{width:30px;height:30px;background:rgba(139,126,255,0.06);border:1px solid rgba(139,126,255,0.20);color:var(--text-soft);display:flex;align-items:center;justify-content:center;cursor:pointer;font-size:0.75rem;transition:all 0.2s;}
    .prod-modal-close:hover{background:rgba(139,126,255,0.14);color:var(--prc-violet);}
    .prod-modal-body{padding:26px;display:grid;grid-template-columns:1fr 1fr;gap:28px;}
    .prod-modal-img{background:rgba(139,126,255,0.05);border:1px solid rgba(139,126,255,0.12);display:flex;align-items:center;justify-content:center;min-height:240px;overflow:hidden;}
    .prod-modal-img img{width:100%;object-fit:cover;}
    .prod-modal-img-ph{display:flex;flex-direction:column;align-items:center;gap:10px;color:rgba(139,126,255,0.25);font-family:var(--font-hud);font-size:0.52rem;letter-spacing:0.12em;}
    .prod-modal-img-ph i{font-size:2.5rem;}
    .prod-modal-price{font-family:var(--font-hud);font-size:1.65rem;font-weight:900;color:var(--prc-violet);text-shadow:0 0 16px rgba(139,126,255,0.50);margin-bottom:4px;}
    .prod-modal-price-sub{font-family:var(--font-hud);font-size:0.50rem;color:var(--text-dim);letter-spacing:0.10em;margin-bottom:16px;}
    .prod-modal-desc{font-size:0.875rem;color:var(--text-mid);line-height:1.75;margin-bottom:18px;}
    .prod-modal-specs-title{font-family:var(--font-hud);font-size:0.52rem;font-weight:700;letter-spacing:0.14em;text-transform:uppercase;color:var(--text-soft);margin-bottom:10px;}
    .prod-modal-specs-title::before{content:"// ";color:rgba(139,126,255,0.30);}
    .prod-modal-spec-list{display:flex;flex-direction:column;gap:7px;margin-bottom:20px;}
    .prod-modal-spec-row{display:flex;align-items:baseline;gap:10px;font-size:0.845rem;}
    .prod-modal-spec-key{font-family:var(--font-hud);font-size:0.50rem;color:var(--text-soft);letter-spacing:0.10em;text-transform:uppercase;flex-shrink:0;min-width:90px;}
    .prod-modal-spec-val{color:var(--text-mid);}
    .prod-modal-footer{padding:18px 26px;border-top:1px solid rgba(139,126,255,0.10);display:flex;gap:10px;flex-wrap:wrap;align-items:center;justify-content:space-between;background:rgba(0,0,0,0.18);}

    /* CART DRAWER */
    .cart-drawer{position:fixed;top:0;right:0;bottom:0;width:360px;z-index:9500;background:rgba(6,5,26,0.98);border-left:1px solid var(--border-neon);transform:translateX(100%);transition:transform 0.32s cubic-bezier(0.23,1,0.32,1);overflow-y:auto;box-shadow:-20px 0 60px rgba(139,126,255,0.10);}
    .cart-drawer.open{transform:translateX(0);}
    .cart-drawer-hdr{padding:20px 22px 16px;border-bottom:1px solid var(--border-neon);background:rgba(139,126,255,0.05);display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:1;}
    .cart-drawer-title{font-family:var(--font-hud);font-size:0.70rem;font-weight:700;letter-spacing:0.10em;color:var(--prc-violet);text-shadow:0 0 10px rgba(139,126,255,0.45);display:flex;align-items:center;gap:9px;}
    .cart-close{width:30px;height:30px;background:rgba(139,126,255,0.06);border:1px solid rgba(139,126,255,0.18);color:var(--text-soft);display:flex;align-items:center;justify-content:center;cursor:pointer;font-size:0.75rem;transition:all 0.2s;}
    .cart-close:hover{background:rgba(139,126,255,0.14);color:var(--prc-violet);}
    .cart-items{padding:16px 22px;display:flex;flex-direction:column;gap:10px;min-height:200px;}
    .cart-item{display:grid;grid-template-columns:52px 1fr auto;gap:12px;align-items:center;padding:12px;background:rgba(139,126,255,0.04);border:1px solid rgba(139,126,255,0.10);}
    .cart-item-img{width:52px;height:52px;object-fit:cover;background:rgba(139,126,255,0.08);display:flex;align-items:center;justify-content:center;color:rgba(139,126,255,0.25);font-size:1.2rem;overflow:hidden;}
    .cart-item-img img{width:100%;height:100%;object-fit:cover;}
    .cart-item-name{font-family:var(--font-hud);font-size:0.60rem;font-weight:700;color:var(--text-high);letter-spacing:0.04em;margin-bottom:3px;}
    .cart-item-price{font-family:var(--font-hud);font-size:0.58rem;color:var(--prc-violet);}
    .cart-item-qty-label{font-family:var(--font-hud);font-size:0.50rem;color:var(--text-dim);}
    .cart-item-remove{background:none;border:none;color:rgba(139,126,255,0.30);font-size:0.70rem;cursor:pointer;padding:4px;transition:color 0.2s;}
    .cart-item-remove:hover{color:#FF4444;}
    .cart-empty{padding:48px 22px;text-align:center;}
    .cart-empty i{font-size:2rem;color:rgba(139,126,255,0.18);display:block;margin-bottom:12px;}
    .cart-empty p{font-family:var(--font-hud);font-size:0.58rem;color:var(--text-dim);letter-spacing:0.08em;}
    .cart-footer{padding:18px 22px;border-top:1px solid var(--border-neon);background:rgba(0,0,0,0.25);position:sticky;bottom:0;}
    .cart-total-row{display:flex;justify-content:space-between;align-items:baseline;margin-bottom:14px;}
    .cart-total-label{font-family:var(--font-hud);font-size:0.55rem;color:var(--text-soft);letter-spacing:0.10em;text-transform:uppercase;}
    .cart-total-val{font-family:var(--font-hud);font-size:1.05rem;font-weight:900;color:var(--prc-violet);text-shadow:0 0 12px rgba(139,126,255,0.50);}
    .btn-checkout{width:100%;font-family:var(--font-hud);font-size:0.62rem;font-weight:700;letter-spacing:0.12em;text-transform:uppercase;padding:12px;border:1px solid var(--creo-amber);color:var(--creo-amber);background:transparent;cursor:pointer;transition:all 0.24s;box-shadow:0 0 14px rgba(255,160,48,0.22);}
    .btn-checkout:hover{background:rgba(255,160,48,0.12);box-shadow:0 0 30px rgba(255,160,48,0.45);color:#fff;}

    /* CHECKOUT MODAL */
    .co-overlay{position:fixed;inset:0;z-index:9600;background:rgba(3,2,13,0.92);backdrop-filter:blur(12px);display:none;align-items:center;justify-content:center;padding:20px;}
    .co-overlay.open{display:flex;}
    .co-modal{background:var(--bg-deep);border:1px solid rgba(255,160,48,0.30);width:100%;max-width:580px;max-height:90vh;overflow-y:auto;position:relative;}
    .co-modal::before{content:"";position:absolute;top:0;left:0;right:0;height:1px;background:linear-gradient(90deg,transparent,var(--creo-amber),transparent);}
    .co-scene{display:none;}
    .co-scene.active{display:block;}
    .co-hdr{padding:18px 24px 14px;border-bottom:1px solid rgba(255,160,48,0.15);background:rgba(255,160,48,0.05);display:flex;align-items:center;justify-content:space-between;}
    .co-hdr-title{font-family:var(--font-hud);font-size:0.68rem;font-weight:700;letter-spacing:0.10em;text-transform:uppercase;color:var(--creo-amber);display:flex;align-items:center;gap:9px;}
    .co-x{width:28px;height:28px;background:rgba(255,160,48,0.06);border:1px solid rgba(255,160,48,0.22);color:var(--text-soft);display:flex;align-items:center;justify-content:center;cursor:pointer;font-size:0.72rem;transition:all 0.2s;}
    .co-x:hover{background:rgba(255,160,48,0.16);color:var(--creo-amber);}
    .co-body{padding:22px 24px;}
    .co-lbl{font-family:var(--font-hud);font-size:0.50rem;font-weight:700;letter-spacing:0.16em;text-transform:uppercase;color:var(--creo-amber);margin-bottom:14px;}
    .co-lbl::before{content:"// ";color:rgba(255,160,48,0.35);}

    /* Scene 1 */
    .co-item{display:grid;grid-template-columns:1fr auto auto;gap:12px;align-items:center;padding:10px 0;border-bottom:1px solid rgba(255,160,48,0.08);}
    .co-item:last-child{border-bottom:none;}
    .co-item-name{font-family:var(--font-hud);font-size:0.62rem;font-weight:700;color:var(--text-high);}
    .co-item-cat{font-size:0.72rem;color:var(--text-dim);margin-top:2px;}
    .co-item-qty{display:flex;align-items:center;border:1px solid rgba(255,160,48,0.22);}
    .co-qbtn{width:26px;height:26px;display:flex;align-items:center;justify-content:center;font-size:0.85rem;color:var(--creo-amber);background:rgba(255,160,48,0.06);cursor:pointer;border:none;transition:background 0.18s;}
    .co-qbtn:hover{background:rgba(255,160,48,0.16);}
    .co-qnum{width:32px;text-align:center;font-family:var(--font-hud);font-size:0.68rem;font-weight:700;color:var(--text-high);border-left:1px solid rgba(255,160,48,0.18);border-right:1px solid rgba(255,160,48,0.18);line-height:26px;}
    .co-item-price{font-family:var(--font-hud);font-size:0.72rem;font-weight:800;color:var(--creo-amber);text-align:right;min-width:70px;}
    .co-total-row{display:flex;justify-content:space-between;align-items:baseline;padding:14px 0 0;border-top:1px solid rgba(255,160,48,0.20);margin-top:8px;}
    .co-total-lbl{font-family:var(--font-hud);font-size:0.52rem;color:var(--text-soft);letter-spacing:0.12em;text-transform:uppercase;}
    .co-total-amt{font-family:var(--font-hud);font-size:1.20rem;font-weight:900;color:var(--creo-amber);text-shadow:0 0 14px rgba(255,160,48,0.55);}

    /* Scene 2 */
    .co-form{display:grid;grid-template-columns:1fr 1fr;gap:14px 18px;}
    .co-field{display:flex;flex-direction:column;gap:6px;}
    .co-field.full{grid-column:1/-1;}
    .co-field-lbl{font-family:var(--font-hud);font-size:0.48rem;font-weight:700;letter-spacing:0.14em;text-transform:uppercase;color:var(--text-soft);}
    .co-req{color:var(--creo-amber);}
    .co-input{background:rgba(255,160,48,0.04);border:1px solid rgba(255,160,48,0.22);color:var(--text-high);font-family:var(--font-body);font-size:0.875rem;padding:10px 13px;width:100%;outline:none;transition:border-color 0.22s,box-shadow 0.22s;}
    .co-input::placeholder{color:var(--text-dim);}
    .co-input:focus{border-color:var(--creo-amber);box-shadow:0 0 16px rgba(255,160,48,0.18);}

    /* Scene 3 */
    .co-pay-tabs{display:flex;border-bottom:1px solid rgba(255,160,48,0.15);margin-bottom:16px;}
    .co-pay-tab{flex:1;padding:10px;font-family:var(--font-hud);font-size:0.58rem;font-weight:700;letter-spacing:0.10em;text-transform:uppercase;color:var(--text-soft);background:transparent;border:none;border-bottom:2px solid transparent;cursor:pointer;transition:all 0.2s;display:flex;align-items:center;justify-content:center;gap:7px;margin-bottom:-1px;}
    .co-pay-tab:hover,.co-pay-tab.active{color:var(--creo-amber);border-bottom-color:var(--creo-amber);background:rgba(255,160,48,0.04);}
    .co-gcash-details{display:flex;gap:16px;align-items:flex-start;flex-wrap:wrap;margin-bottom:14px;}
    .co-gcash-qr{width:90px;height:90px;background:#fff;border:2px solid rgba(0,124,255,0.28);display:flex;align-items:center;justify-content:center;flex-shrink:0;}
    .co-gcash-info{display:flex;flex-direction:column;gap:3px;}
    .co-gcash-num-lbl{font-family:var(--font-hud);font-size:0.46rem;font-weight:700;letter-spacing:0.14em;text-transform:uppercase;color:var(--text-dim);}
    .co-gcash-num{font-family:var(--font-hud);font-size:1.02rem;font-weight:900;color:#007CFF;text-shadow:0 0 12px rgba(0,124,255,0.40);letter-spacing:0.06em;}
    .co-gcash-name{font-size:0.78rem;color:var(--text-soft);margin-bottom:5px;}
    .co-bank-box{padding:14px 18px;background:rgba(255,160,48,0.04);border:1px solid rgba(255,160,48,0.20);display:flex;flex-direction:column;gap:0;margin-bottom:14px;}
    .co-bank-row{display:flex;align-items:baseline;justify-content:space-between;padding:6px 0;border-bottom:1px solid rgba(255,160,48,0.08);}
    .co-bank-row:last-of-type{border-bottom:none;}
    .co-bank-key{font-family:var(--font-hud);font-size:0.46rem;color:var(--text-dim);letter-spacing:0.10em;text-transform:uppercase;}
    .co-bank-val{font-family:var(--font-hud);font-size:0.62rem;color:var(--text-high);font-weight:700;text-align:right;}
    .co-bank-hl{color:var(--creo-amber);text-shadow:0 0 10px rgba(255,160,48,0.45);font-size:0.80rem;letter-spacing:0.08em;}
    .co-copy-btn{display:inline-flex;align-items:center;gap:7px;font-family:var(--font-hud);font-size:0.48rem;font-weight:700;letter-spacing:0.10em;text-transform:uppercase;padding:5px 12px;border:1px solid rgba(255,160,48,0.28);color:var(--creo-amber);background:rgba(255,160,48,0.05);cursor:pointer;transition:all 0.2s;clip-path:polygon(4px 0%,100% 0%,calc(100% - 4px) 100%,0% 100%);}
    .co-copy-btn:hover{background:rgba(255,160,48,0.14);}
    .co-copy-btn.blue{border-color:rgba(0,124,255,0.30);color:#007CFF;background:rgba(0,124,255,0.05);}
    .co-copy-btn.blue:hover{background:rgba(0,124,255,0.14);}
    .co-upload-wrap{margin-top:12px;border:1.5px dashed rgba(255,160,48,0.28);background:rgba(255,160,48,0.02);cursor:pointer;transition:all 0.22s;min-height:82px;display:flex;align-items:center;justify-content:center;text-align:center;position:relative;overflow:hidden;}
    .co-upload-wrap:hover{border-color:var(--creo-amber);background:rgba(255,160,48,0.06);}
    .co-upload-wrap.has-file{border-style:solid;border-color:rgba(255,233,48,0.35);}
    .co-upload-idle{padding:18px 20px;}
    .co-upload-lbl{font-family:var(--font-hud);font-size:0.56rem;font-weight:700;letter-spacing:0.10em;text-transform:uppercase;color:var(--creo-amber);margin-bottom:3px;}
    .co-upload-sub{font-size:0.72rem;color:var(--text-dim);}
    .co-upload-preview{width:100%;padding:12px 14px;}
    .co-upload-preview img{max-height:100px;max-width:100%;margin:0 auto 8px;display:block;}
    .co-upload-file-info{display:flex;align-items:center;gap:8px;}
    .co-upload-fname{font-family:var(--font-hud);font-size:0.56rem;font-weight:700;color:var(--creo-volt);letter-spacing:0.04em;}
    .co-upload-fsize{font-size:0.68rem;color:var(--text-dim);}
    .co-upload-remove{margin-left:auto;background:rgba(255,68,68,0.08);border:1px solid rgba(255,68,68,0.25);color:#FF6B6B;width:24px;height:24px;display:flex;align-items:center;justify-content:center;cursor:pointer;font-size:0.60rem;flex-shrink:0;transition:all 0.2s;}
    .co-upload-remove:hover{background:rgba(255,68,68,0.18);}
    .co-note-box{display:flex;gap:10px;align-items:flex-start;margin-bottom:12px;padding:11px 14px;background:rgba(255,160,48,0.05);border:1px solid rgba(255,160,48,0.22);font-size:0.828rem;color:var(--text-mid);line-height:1.65;}
    .co-confirm-box{background:rgba(255,160,48,0.04);border:1px solid rgba(255,160,48,0.20);padding:14px 18px;font-size:0.845rem;color:var(--text-mid);line-height:1.90;}
    .co-ftr{padding:14px 24px 18px;border-top:1px solid rgba(255,160,48,0.10);background:rgba(0,0,0,0.18);display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap;}
    .co-btn-back{font-family:var(--font-hud);font-size:0.56rem;font-weight:700;letter-spacing:0.10em;text-transform:uppercase;padding:9px 18px;border:1px solid rgba(255,160,48,0.22);color:var(--text-soft);background:transparent;cursor:pointer;transition:all 0.22s;display:inline-flex;align-items:center;gap:7px;}
    .co-btn-back:hover{border-color:var(--creo-amber);color:var(--creo-amber);}
    .co-btn-next{font-family:var(--font-hud);font-size:0.58rem;font-weight:700;letter-spacing:0.10em;text-transform:uppercase;padding:10px 24px;border:1px solid var(--creo-amber);color:var(--creo-amber);background:transparent;cursor:pointer;transition:all 0.22s;box-shadow:0 0 14px rgba(255,160,48,0.22);display:inline-flex;align-items:center;gap:8px;position:relative;overflow:hidden;}
    .co-btn-next::before{content:"";position:absolute;inset:0;background:linear-gradient(90deg,transparent,rgba(255,160,48,0.14),transparent);transform:translateX(-100%);transition:transform 0.4s;}
    .co-btn-next:hover{background:rgba(255,160,48,0.12);box-shadow:0 0 26px rgba(255,160,48,0.42);color:#fff;}
    .co-btn-next:hover::before{transform:translateX(100%);}
    .co-btn-next:disabled{opacity:0.50;pointer-events:none;}

    /* FOOTER */
    footer{background:rgba(0,0,6,0.95);border-top:1px solid var(--border-neon);padding:70px 0 32px;position:relative;z-index:1;}
    .footer-inner{max-width:1340px;margin:0 auto;padding:0 36px;}
    .footer-top{display:grid;grid-template-columns:2fr 1fr 1fr 1fr;gap:52px;margin-bottom:52px;}
    .footer-brand img{height:36px;width:auto;margin-bottom:16px;}
    .footer-brand p{font-size:0.875rem;color:var(--text-mid);line-height:1.80;margin-bottom:22px;}
    .footer-contact-list{display:flex;flex-direction:column;gap:11px;margin-bottom:24px;}
    .footer-contact-item{display:flex;align-items:center;gap:11px;font-size:0.875rem;color:var(--text-mid);}
    .footer-contact-item i{color:var(--prc-violet);font-size:0.95rem;flex-shrink:0;}
    .footer-contact-item a{color:var(--text-mid);transition:color 0.2s;}
    .footer-contact-item a:hover{color:var(--prc-violet);}
    .social-links{display:flex;gap:8px;}
    .social-link{width:40px;height:40px;background:rgba(139,126,255,0.04);border:1px solid rgba(139,126,255,0.18);display:flex;align-items:center;justify-content:center;font-size:1rem;color:rgba(139,126,255,0.50);transition:all 0.25s;}
    .social-link:hover{background:rgba(139,126,255,0.12);color:var(--prc-violet);box-shadow:0 0 14px rgba(139,126,255,0.35);border-color:var(--prc-violet);}
    .footer-col h4{font-family:var(--font-hud);font-size:0.65rem;font-weight:700;letter-spacing:0.10em;text-transform:uppercase;margin-bottom:20px;color:var(--prc-ice);text-shadow:0 0 10px rgba(196,238,255,0.45);}
    .footer-col ul{display:flex;flex-direction:column;gap:10px;}
    .footer-col ul li a{font-size:0.875rem;color:var(--text-mid);transition:all 0.2s;display:flex;align-items:center;gap:8px;}
    .footer-col ul li a i{font-size:0.65rem;color:rgba(139,126,255,0.38);transition:color 0.2s;}
    .footer-col ul li a:hover{color:var(--prc-violet);padding-left:4px;}
    .footer-col ul li a:hover i{color:var(--prc-violet);}
    .footer-bottom{padding-top:24px;border-top:1px solid rgba(139,126,255,0.12);display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:14px;}
    .footer-bottom p{font-family:var(--font-hud);font-size:0.58rem;color:var(--text-soft);letter-spacing:0.06em;}
    .footer-bottom-links{display:flex;gap:22px;}
    .footer-bottom-links a{font-family:var(--font-hud);font-size:0.58rem;color:var(--text-soft);transition:color 0.2s;letter-spacing:0.06em;}
    .footer-bottom-links a:hover{color:var(--prc-violet);}

    /* REVEAL */
    .reveal{opacity:0;transform:translateY(30px);transition:opacity 0.65s ease,transform 0.65s ease;}
    .reveal.visible{opacity:1;transform:translateY(0);}

    /* RESPONSIVE */
    @media(max-width:900px){.product-row{grid-template-columns:150px 1fr;}.product-side{display:none;}}
    @media(max-width:768px){
      :root{--nav-height:62px;}body{cursor:auto;}button{cursor:pointer;}
      .cursor-dot,.cursor-ring{display:none;}.nav-links{display:none;}.nav-hamburger{display:flex;}
      .product-row{grid-template-columns:1fr;}.product-img-wrap{min-height:180px;border-right:none;border-bottom:1px solid rgba(139,126,255,0.10);}
      .shop-wrap{padding:24px 14px 70px;}.cart-drawer{width:100%;}.prod-modal-body{grid-template-columns:1fr;}
      .co-form{grid-template-columns:1fr;}.footer-top{grid-template-columns:1fr;gap:32px;}.footer-bottom{flex-direction:column;text-align:center;}
      .co-gcash-details{flex-direction:column;}
    }
    @media(max-width:520px){:root{--nav-height:58px;}.nav-inner{padding:0 14px;}.footer-inner,.breadcrumb-inner{padding:0 16px;}}
    ::-webkit-scrollbar{width:4px;}::-webkit-scrollbar-track{background:var(--bg-void);}::-webkit-scrollbar-thumb{background:var(--prc-violet);box-shadow:0 0 8px rgba(139,126,255,0.70);border-radius:2px;}
  </style>
</head>
<body>
  <div class="cursor-dot" id="cursorDot"></div>
  <div class="cursor-ring" id="cursorRing"></div>
  <div class="hex-grid" aria-hidden="true"></div>

  <!-- NAV -->
  <?php $activePage = 'shop'; include 'nav.php'; ?>

  <!-- BREADCRUMB -->
  <div class="breadcrumb-bar">
    <div class="breadcrumb-inner">
      <a href="index.php">Home</a>
      <span class="breadcrumb-sep">&#8250;</span>
      <span class="breadcrumb-current">Materials / Kits Shop</span>
    </div>
  </div>

  <!-- SHOP -->
  <div class="shop-wrap">
    <div class="shop-topbar">
      <div>
        <div class="section-eyebrow" style="display:inline-flex;margin-bottom:8px;">PRC 2026 // Materials &amp; Kits</div>
        <h1 class="section-title" style="margin-bottom:4px;">Shop <span class="accent">Materials</span></h1>
        <div class="shop-count" id="shop-count">Showing <?= count($products) ?> items</div>
      </div>
      <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
        <div class="shop-filters">
          <button class="shop-filter-btn active" onclick="filterShop('all',this)">All</button>
          <?php foreach ($categories as $cat): ?>
          <button class="shop-filter-btn" onclick="filterShop(<?= json_encode($cat) ?>,this)"><?= htmlspecialchars($cat) ?></button>
          <?php endforeach; ?>
        </div>
        <button class="cart-btn" onclick="toggleCart()">
          <i class="fi fi-rr-shopping-cart"></i> Cart
          <div class="cart-badge" id="cart-badge">0</div>
        </button>
      </div>
    </div>

    <div class="shop-list" id="shop-list">

      <?php foreach ($products as $p):
        $pid   = $p['product_id'];
        $slug  = htmlspecialchars($p['product_slug']);
        $name  = htmlspecialchars($p['product_name']);
        $cat   = htmlspecialchars($p['category']);
        $price = number_format($p['price'], 0);
        $priceRaw = (float)$p['price'];
        $desc  = htmlspecialchars($p['description'] ?? '');
        $img   = $p['image_path'] ? htmlspecialchars($p['image_path']) : null;
        $stock = $p['stock_status'];  // in-stock | low-stock | out-of-stock
        $isUnavailable = ($stock === 'out-of-stock') ? 'disabled' : '';
        $stockLabel = match($stock) {
            'low-stock'     => '<span class="stock-badge low-stock"><span class="stock-dot"></span>Low Stock</span>',
            'out-of-stock'  => '<span class="stock-badge out-of-stock"><span class="stock-dot"></span>Out of Stock</span>',
            default         => '<span class="stock-badge in-stock"><span class="stock-dot"></span>In Stock</span>',
        };
        $specs = $specs_by_product[$pid] ?? [];
      ?>
      <div class="product-row reveal" id="prod-<?= $slug ?>" data-cat="<?= $cat ?>">
        <div class="product-img-wrap">
          <?php if ($img): ?>
            <img src="<?= $img ?>" alt="<?= $name ?>" loading="lazy" />
          <?php else: ?>
            <div class="product-img-placeholder"><i class="fi fi-rr-box-open-full"></i>Photo</div>
          <?php endif; ?>
          <span class="product-tag-img"><?= $cat ?></span>
        </div>
        <div class="product-body">
          <div class="product-eyebrow"><?= $cat ?></div>
          <div class="product-name"><?= $name ?></div>
          <div class="product-price">&#8369;<?= $price ?></div>
          <div class="product-desc"><?= $desc ?></div>
          <?php if ($specs): ?>
          <div class="product-specs">
            <?php foreach (array_slice($specs, 0, 3) as $s): ?>
            <span class="product-spec"><?= htmlspecialchars($s['spec_value']) ?></span>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>
          <div class="product-actions">
            <?= $stockLabel ?>
            <button class="btn-view-item" onclick="openProdModal(<?= $pid ?>)"><i class="fi fi-rr-info"></i> View Details</button>
            <button class="btn-add-cart" id="cart-btn-<?= $slug ?>" onclick="addToCart(<?= $pid ?>)" <?= $isUnavailable ?>><i class="fi fi-rr-shopping-cart"></i> Add to Cart</button>
            <button class="btn-order-now" onclick="orderNow(<?= $pid ?>)" <?= $isUnavailable ?>><i class="fi fi-rr-bolt"></i> Order Now</button>
          </div>
        </div>
        <div class="product-side">
          <div class="product-price" style="font-size:1.05rem;margin-bottom:0">&#8369;<?= $price ?></div>
          <div>
            <div style="font-family:var(--font-hud);font-size:0.48rem;color:var(--text-dim);letter-spacing:0.10em;margin-bottom:7px;text-align:right;">QTY</div>
            <div class="qty-wrap">
              <button class="qty-btn" onclick="changeQty(<?= $pid ?>,-1)">&#8722;</button>
              <div class="qty-val" id="qty-<?= $pid ?>">1</div>
              <button class="qty-btn" onclick="changeQty(<?= $pid ?>,1)">&#43;</button>
            </div>
          </div>
        </div>
      </div>
      <?php endforeach; ?>

      <?php if (empty($products)): ?>
      <div style="padding:60px 30px;text-align:center;border:1px solid var(--border-neon);color:var(--text-dim);font-family:var(--font-hud);font-size:0.72rem;letter-spacing:0.10em;">
        No products available at this time.
      </div>
      <?php endif; ?>

    </div><!-- /shop-list -->

    <div style="margin-top:30px;padding:18px 22px;border:1px solid rgba(139,126,255,0.13);background:rgba(139,126,255,0.025);display:flex;align-items:flex-start;gap:14px;" class="reveal">
      <i class="fi fi-rr-info" style="color:var(--prc-violet);font-size:1rem;flex-shrink:0;margin-top:3px;"></i>
      <p style="font-size:0.858rem;color:var(--text-mid);line-height:1.65;margin:0;">
        Need help choosing? Contact us at <a href="mailto:philippineroboticscup@gmail.com" style="color:var(--prc-violet);">philippineroboticscup@gmail.com</a> or
        <a href="https://www.facebook.com/profile.php?id=61579706372017" target="_blank" rel="noopener" style="color:var(--prc-violet);">message us on Facebook</a>.
      </p>
    </div>
  </div><!-- /shop-wrap -->


  <!-- PRODUCT DETAIL MODAL -->
  <div class="prod-modal-overlay" id="prod-modal-overlay" onclick="closeProdModalOutside(event)">
    <div class="prod-modal">
      <div class="prod-modal-hdr">
        <div class="prod-modal-title" id="prod-modal-title">Product Details</div>
        <button class="prod-modal-close" onclick="closeProdModal()"><i class="fi fi-rr-cross"></i></button>
      </div>
      <div class="prod-modal-body">
        <div class="prod-modal-img" id="prod-modal-img">
          <div class="prod-modal-img-ph"><i class="fi fi-rr-box-open-full"></i>Product Photo</div>
        </div>
        <div class="prod-modal-info">
          <div style="font-family:var(--font-hud);font-size:0.50rem;color:var(--text-dim);letter-spacing:0.14em;text-transform:uppercase;margin-bottom:6px;" id="prod-modal-cat"></div>
          <div class="prod-modal-price" id="prod-modal-price"></div>
          <div class="prod-modal-price-sub">per unit</div>
          <div class="prod-modal-desc" id="prod-modal-desc"></div>
          <div class="prod-modal-specs-title">Specifications</div>
          <div class="prod-modal-spec-list" id="prod-modal-specs"></div>
        </div>
      </div>
      <div class="prod-modal-footer">
        <div id="prod-modal-stock"></div>
        <div style="display:flex;gap:10px;flex-wrap:wrap;">
          <button class="btn-view-item" onclick="closeProdModal()">Close</button>
          <button class="btn-add-cart" id="prod-modal-cart-btn" onclick="addToCartFromModal()"><i class="fi fi-rr-shopping-cart"></i> Add to Cart</button>
          <button class="btn-order-now" onclick="orderNowFromModal()"><i class="fi fi-rr-bolt"></i> Order Now</button>
        </div>
      </div>
    </div>
  </div>


  <!-- CART DRAWER -->
  <div class="cart-drawer" id="cart-drawer">
    <div class="cart-drawer-hdr">
      <div class="cart-drawer-title"><i class="fi fi-rr-shopping-cart"></i> Your Cart</div>
      <button class="cart-close" onclick="toggleCart()"><i class="fi fi-rr-cross"></i></button>
    </div>
    <div class="cart-items" id="cart-items">
      <div class="cart-empty"><i class="fi fi-rr-shopping-cart"></i><p>Your cart is empty</p></div>
    </div>
    <div class="cart-footer">
      <div class="cart-total-row">
        <span class="cart-total-label">Total</span>
        <span class="cart-total-val" id="cart-total">&#8369;0</span>
      </div>
      <button class="btn-checkout" onclick="openCheckout()"><i class="fi fi-rr-credit-card"></i> Proceed to Checkout</button>
    </div>
  </div>


  <!-- CHECKOUT MODAL -->
  <div class="co-overlay" id="co-overlay" onclick="closeCoOutside(event)">
    <div class="co-modal" onclick="event.stopPropagation()">

      <!-- Scene 1: Order Summary -->
      <div class="co-scene active" id="co1">
        <div class="co-hdr">
          <div class="co-hdr-title"><i class="fi fi-rr-receipt"></i> Order Summary</div>
          <button class="co-x" onclick="closeCheckout()"><i class="fi fi-rr-cross"></i></button>
        </div>
        <div class="co-body">
          <div class="co-lbl">Items in Cart</div>
          <div id="co-items"></div>
          <div class="co-total-row">
            <span class="co-total-lbl">Total</span>
            <span class="co-total-amt" id="co-total">&#8369;0</span>
          </div>
        </div>
        <div class="co-ftr">
          <button class="co-btn-back" onclick="closeCheckout()"><i class="fi fi-rr-arrow-left"></i> Back to Shop</button>
          <button class="co-btn-next" onclick="goScene(2)">Continue <i class="fi fi-rr-arrow-right"></i></button>
        </div>
      </div>

      <!-- Scene 2: Contact Details -->
      <div class="co-scene" id="co2">
        <div class="co-hdr">
          <div class="co-hdr-title"><i class="fi fi-rr-user"></i> Your Details</div>
          <button class="co-x" onclick="closeCheckout()"><i class="fi fi-rr-cross"></i></button>
        </div>
        <div class="co-body">
          <div class="co-lbl">Contact Information</div>
          <div class="co-form">
            <div class="co-field">
              <label class="co-field-lbl">Full Name <span class="co-req">*</span></label>
              <input class="co-input" id="co-name" type="text" placeholder="Your full name"/>
            </div>
            <div class="co-field">
              <label class="co-field-lbl">Email <span class="co-req">*</span></label>
              <input class="co-input" id="co-email" type="email" placeholder="your@email.com"/>
            </div>
            <div class="co-field">
              <label class="co-field-lbl">Contact Number <span class="co-req">*</span></label>
              <input class="co-input" id="co-phone" type="tel" placeholder="+63 9XX XXX XXXX"/>
            </div>
            <div class="co-field">
              <label class="co-field-lbl">School / Organization <span class="co-req">*</span></label>
              <input class="co-input" id="co-school" type="text" placeholder="Full school name"/>
            </div>
            <div class="co-field full">
              <label class="co-field-lbl">Notes / Address</label>
              <input class="co-input" id="co-notes" type="text" placeholder="Any special instructions (optional)"/>
            </div>
          </div>
        </div>
        <div class="co-ftr">
          <button class="co-btn-back" onclick="goScene(1)"><i class="fi fi-rr-arrow-left"></i> Back</button>
          <button class="co-btn-next" onclick="validateDetailsAndProceed()">Continue <i class="fi fi-rr-arrow-right"></i></button>
        </div>
      </div>

      <!-- Scene 3: Payment -->
      <div class="co-scene" id="co3">
        <div class="co-hdr">
          <div class="co-hdr-title"><i class="fi fi-rr-smartphone"></i> Payment</div>
          <button class="co-x" onclick="closeCheckout()"><i class="fi fi-rr-cross"></i></button>
        </div>
        <div class="co-body">
          <div class="co-lbl">Payment Method</div>
          <div class="co-pay-tabs">
            <button class="co-pay-tab active" id="co-tab-gcash" onclick="coSwitchTab('gcash')"><i class="fi fi-rr-smartphone"></i> GCash</button>
            <button class="co-pay-tab" id="co-tab-bank" onclick="coSwitchTab('bank')"><i class="fi fi-rr-bank"></i> Bank Transfer</button>
          </div>

          <!-- GCash Panel -->
          <div id="co-panel-gcash">
            <div class="co-gcash-details">
              <div class="co-gcash-qr"><i class="fi fi-rr-qr-scan" style="font-size:2.4rem;color:rgba(0,124,255,0.30);"></i></div>
              <div class="co-gcash-info">
                <div class="co-gcash-num-lbl">GCash Number</div>
                <div class="co-gcash-num">+63 917 771 3961</div>
                <div class="co-gcash-name">Creotec Philippines Inc.</div>
                <div style="font-family:var(--font-hud);font-size:0.50rem;color:var(--text-dim);letter-spacing:0.10em;margin-bottom:6px;">Amount: <span style="color:var(--creo-amber);" id="co-gcash-amount">&#8369;0</span></div>
                <button class="co-copy-btn blue" onclick="copyCoGcash()">
                  <i class="fi fi-rr-copy" id="co-copy-icon"></i>
                  <span id="co-copy-lbl">Copy Number</span>
                </button>
              </div>
            </div>
            <div class="co-note-box"><i class="fi fi-rr-info" style="color:var(--creo-amber);flex-shrink:0;margin-top:2px;"></i>Send the exact amount via GCash then attach your receipt below.</div>
            <div style="font-family:var(--font-hud);font-size:0.50rem;font-weight:700;letter-spacing:0.14em;text-transform:uppercase;color:var(--text-soft);margin-bottom:8px;">// GCash Receipt</div>
            <div class="co-upload-wrap" id="upload-gcash" onclick="document.getElementById('co-file-gcash').click()">
              <input type="file" id="co-file-gcash" accept="image/*,.pdf" style="display:none" onchange="coHandleUpload(event,'gcash')"/>
              <div class="co-upload-idle" id="co-idle-gcash">
                <i class="fi fi-rr-cloud-upload" style="font-size:1.6rem;color:rgba(255,160,48,0.35);display:block;margin-bottom:6px;"></i>
                <div class="co-upload-lbl">Attach GCash Receipt</div>
                <div class="co-upload-sub">JPG, PNG or PDF — max 5MB</div>
              </div>
              <div class="co-upload-preview" id="co-preview-gcash" style="display:none;">
                <img id="co-thumb-gcash" src="" style="display:none;" alt="Receipt"/>
                <div class="co-upload-file-info">
                  <i class="fi fi-rr-check-circle" style="color:var(--creo-volt);font-size:1rem;"></i>
                  <div><div class="co-upload-fname" id="co-fname-gcash">—</div><div class="co-upload-fsize" id="co-fsize-gcash">—</div></div>
                  <button class="co-upload-remove" onclick="coRemoveUpload('gcash',event)"><i class="fi fi-rr-cross"></i></button>
                </div>
              </div>
            </div>
          </div>

          <!-- Bank Panel -->
          <div id="co-panel-bank" style="display:none;">
            <div class="co-bank-box">
              <div class="co-bank-row"><span class="co-bank-key">Bank</span><span class="co-bank-val">BDO Unibank</span></div>
              <div class="co-bank-row"><span class="co-bank-key">Account Name</span><span class="co-bank-val">Creotec Philippines Inc.</span></div>
              <div class="co-bank-row"><span class="co-bank-key">Account Number</span><span class="co-bank-val co-bank-hl">000-123-4567-8</span></div>
              <div class="co-bank-row"><span class="co-bank-key">Amount</span><span class="co-bank-val co-bank-hl" id="co-bank-amount">&#8369;0</span></div>
              <button class="co-copy-btn" onclick="copyCoBank()" style="margin-top:8px;">
                <i class="fi fi-rr-copy" id="co-copy-bank-icon"></i>
                <span id="co-copy-bank-lbl">Copy Account Number</span>
              </button>
            </div>
            <div class="co-note-box"><i class="fi fi-rr-info" style="color:var(--creo-amber);flex-shrink:0;margin-top:2px;"></i>Use your order reference as the transfer notes. Attach your bank receipt below.</div>
            <div style="font-family:var(--font-hud);font-size:0.50rem;font-weight:700;letter-spacing:0.14em;text-transform:uppercase;color:var(--text-soft);margin-bottom:8px;">// Bank Transfer Receipt</div>
            <div class="co-upload-wrap" id="upload-bank" onclick="document.getElementById('co-file-bank').click()">
              <input type="file" id="co-file-bank" accept="image/*,.pdf" style="display:none" onchange="coHandleUpload(event,'bank')"/>
              <div class="co-upload-idle" id="co-idle-bank">
                <i class="fi fi-rr-cloud-upload" style="font-size:1.6rem;color:rgba(255,160,48,0.35);display:block;margin-bottom:6px;"></i>
                <div class="co-upload-lbl">Attach Bank Receipt</div>
                <div class="co-upload-sub">JPG, PNG or PDF — max 5MB</div>
              </div>
              <div class="co-upload-preview" id="co-preview-bank" style="display:none;">
                <img id="co-thumb-bank" src="" style="display:none;" alt="Receipt"/>
                <div class="co-upload-file-info">
                  <i class="fi fi-rr-check-circle" style="color:var(--creo-volt);font-size:1rem;"></i>
                  <div><div class="co-upload-fname" id="co-fname-bank">—</div><div class="co-upload-fsize" id="co-fsize-bank">—</div></div>
                  <button class="co-upload-remove" onclick="coRemoveUpload('bank',event)"><i class="fi fi-rr-cross"></i></button>
                </div>
              </div>
            </div>
          </div>
        </div>
        <div class="co-ftr">
          <button class="co-btn-back" onclick="goScene(2)"><i class="fi fi-rr-arrow-left"></i> Back</button>
          <button class="co-btn-next" id="co-confirm-btn" onclick="confirmShopOrder()">Confirm Order <i class="fi fi-rr-check"></i></button>
        </div>
      </div>

      <!-- Scene 4: Done -->
      <div class="co-scene" id="co4">
        <div class="co-hdr">
          <div class="co-hdr-title"><i class="fi fi-rr-check-circle"></i> Order Sent!</div>
          <button class="co-x" onclick="closeCheckout();clearCart();"><i class="fi fi-rr-cross"></i></button>
        </div>
        <div class="co-body" style="text-align:center;padding:32px 24px;">
          <div style="font-size:2.8rem;margin-bottom:14px;">&#127873;</div>
          <div style="font-family:var(--font-hud);font-size:0.88rem;font-weight:800;color:var(--text-high);margin-bottom:10px;letter-spacing:0.04em;">Thank You!</div>
          <p style="font-size:0.875rem;color:var(--text-mid);line-height:1.72;margin-bottom:20px;">Your order has been saved. We will contact you at <strong id="co-confirm-email">your email</strong> within 24 hours.</p>
          <div class="co-confirm-box" id="co-confirm-box"></div>
        </div>
        <div class="co-ftr" style="justify-content:center;">
          <button class="co-btn-next" onclick="closeCheckout();clearCart();"><i class="fi fi-rr-shopping-cart"></i> Back to Shop</button>
        </div>
      </div>

    </div>
  </div>


  <footer role="contentinfo">
    <div class="footer-inner">
      <div class="footer-top">
        <div class="footer-brand">
          <img src="assets/PRC White Logo.png" alt="Philippine Robotics Cup"/>
          <p>The Philippine Robotics Cup is a premier national robotics competition promoting STEM education.</p>
          <div class="footer-contact-list">
            <div class="footer-contact-item"><i class="fi fi-brands-facebook"></i><a href="https://www.facebook.com/profile.php?id=61579706372017" target="_blank" rel="noopener">Philippine Robotics Cup</a></div>
            <div class="footer-contact-item"><i class="fi fi-rr-phone-call"></i><a href="tel:+639177713961">+63 917 771 3961</a></div>
            <div class="footer-contact-item"><i class="fi fi-rr-envelope"></i><a href="mailto:philippineroboticscup@gmail.com">philippineroboticscup@gmail.com</a></div>
          </div>
        </div>
        <nav class="footer-col"><h4>Competition</h4><ul><li><a href="categories.php"><i class="fi fi-rr-angle-right"></i>Categories</a></li><li><a href="rankings.php"><i class="fi fi-rr-angle-right"></i>Rankings</a></li></ul></nav>
        <nav class="footer-col"><h4>Register</h4><ul><li><a href="register.php"><i class="fi fi-rr-angle-right"></i>Register Now</a></li><li><a href="contact.php"><i class="fi fi-rr-angle-right"></i>FAQ</a></li></ul></nav>
        <nav class="footer-col"><h4>Shop</h4><ul><li><a href="shop.php"><i class="fi fi-rr-angle-right"></i>All Products</a></li><li><a href="contact.php"><i class="fi fi-rr-angle-right"></i>Order Support</a></li></ul></nav>
      </div>
      <div class="footer-bottom">
        <p>&copy; 2026 Philippine Robotics Cup // Creotec Philippines Inc. All rights reserved.</p>
        <div class="footer-bottom-links"><a href="#">Privacy Policy</a><a href="#">Terms of Use</a></div>
      </div>
    </div>
  </footer>


  <script>
    /* ══════════════════════════════
       GOOGLE SCRIPT ENDPOINT
    ══════════════════════════════ */
    var SHEET_URL = 'https://script.google.com/macros/s/AKfycbyw_3nQVGQIVvSyyAR8RZjG0yLSEgK7poXoBZOJi8vwh6bt5OYOUdMBXnJSMB1HfJ-UrQ/exec';

    /* ═══════════════════════════════════
       PHP → JS: product catalogue
    ═══════════════════════════════════ */
    var PRODUCTS = <?php
      $js = [];
      foreach ($products as $p) {
          $pid = $p['product_id'];
          $js[$pid] = [
              'product_id'  => $pid,
              'slug'        => $p['product_slug'],
              'name'        => $p['product_name'],
              'category'    => $p['category'],
              'priceRaw'    => (float)$p['price'],
              'priceLabel'  => '₱' . number_format($p['price'], 0),
              'description' => $p['description'] ?? '',
              'image_path'  => $p['image_path'],
              'stock'       => $p['stock_status'],
              'specs'       => array_map(fn($s) => [$s['spec_key'], $s['spec_value']], $specs_by_product[$pid] ?? []),
          ];
      }
      echo json_encode($js, JSON_UNESCAPED_UNICODE);
    ?>;

    /* ═══════════════════════════════════
       CURSOR
    ═══════════════════════════════════ */
    var dot=document.getElementById('cursorDot'),ring=document.getElementById('cursorRing'),mx=0,my=0,rx=0,ry=0;
    document.addEventListener('mousemove',function(e){mx=e.clientX;my=e.clientY;dot.style.left=mx+'px';dot.style.top=my+'px';});
    (function loop(){rx+=(mx-rx)*.12;ry+=(my-ry)*.12;ring.style.left=rx+'px';ring.style.top=ry+'px';requestAnimationFrame(loop);})();
    document.addEventListener('mouseover',function(e){if(e.target.closest('a,button,.product-row')){ring.classList.add('hovered');dot.style.background='var(--creo-amber)';}});
    document.addEventListener('mouseout',function(e){if(e.target.closest('a,button,.product-row')){ring.classList.remove('hovered');dot.style.background='var(--neon-primary)';}});

    /* ═══════════════════════════════════
       SCROLL REVEAL
    ═══════════════════════════════════ */
    var ro=new IntersectionObserver(function(entries){
      entries.forEach(function(e){if(e.isIntersecting){e.target.classList.add('visible');ro.unobserve(e.target);}});
    },{threshold:0.06});
    document.querySelectorAll('.reveal').forEach(function(el){ro.observe(el);});

    /* ═══════════════════════════════════
       QTY CONTROLS
    ═══════════════════════════════════ */
    function changeQty(pid,delta){
      var el=document.getElementById('qty-'+pid);if(!el)return;
      el.textContent=Math.max(1,parseInt(el.textContent)+delta);
    }
    function getQty(pid){var el=document.getElementById('qty-'+pid);return el?parseInt(el.textContent)||1:1;}

    /* ═══════════════════════════════════
       CART
    ═══════════════════════════════════ */
    var cart={};

    function addToCart(pid,qty){
      qty=qty||getQty(pid);
      if(!cart[pid])cart[pid]=0;
      cart[pid]+=qty;
      updateCartUI();
      var p=PRODUCTS[pid];
      if(p){
        var btn=document.getElementById('cart-btn-'+p.slug);
        if(btn){btn.classList.add('added');btn.innerHTML='<i class="fi fi-rr-check"></i> Added!';setTimeout(function(){btn.classList.remove('added');btn.innerHTML='<i class="fi fi-rr-shopping-cart"></i> Add to Cart';},1600);}
      }
      var badge=document.getElementById('cart-badge');badge.classList.add('bump');setTimeout(function(){badge.classList.remove('bump');},300);
    }

    function removeFromCart(pid){delete cart[pid];updateCartUI();}

    function updateCartUI(){
      var total=0,count=0,html='';
      var keys=Object.keys(cart);
      if(!keys.length){
        document.getElementById('cart-items').innerHTML='<div class="cart-empty"><i class="fi fi-rr-shopping-cart"></i><p>Your cart is empty</p></div>';
        document.getElementById('cart-total').textContent='\u20B10';
        document.getElementById('cart-badge').textContent='0';
        return;
      }
      keys.forEach(function(pid){
        var p=PRODUCTS[pid];if(!p)return;
        count+=cart[pid];total+=p.priceRaw*cart[pid];
        html+='<div class="cart-item">'+
          '<div class="cart-item-img"><i class="fi fi-rr-box-open-full"></i></div>'+
          '<div>'+
            '<div class="cart-item-name">'+p.name+'</div>'+
            '<div class="cart-item-price">'+p.priceLabel+' &times; '+cart[pid]+'</div>'+
            '<div class="cart-item-qty-label">Subtotal: \u20B1'+(p.priceRaw*cart[pid]).toLocaleString()+'</div>'+
          '</div>'+
          '<button class="cart-item-remove" onclick="removeFromCart('+pid+')"><i class="fi fi-rr-trash"></i></button>'+
        '</div>';
      });
      document.getElementById('cart-items').innerHTML=html;
      document.getElementById('cart-total').textContent='\u20B1'+total.toLocaleString();
      document.getElementById('cart-badge').textContent=count;
    }

    function toggleCart(){document.getElementById('cart-drawer').classList.toggle('open');}
    function clearCart(){cart={};updateCartUI();document.getElementById('cart-drawer').classList.remove('open');}

    function orderNow(pid){
      var qty=getQty(pid);
      if(!cart[pid])cart[pid]=0;
      cart[pid]+=qty;
      updateCartUI();
      openCheckout();
    }

    /* ═══════════════════════════════════
       FILTER
    ═══════════════════════════════════ */
    function filterShop(cat,btn){
      document.querySelectorAll('.shop-filter-btn').forEach(function(b){b.classList.remove('active');});
      btn.classList.add('active');
      var shown=0;
      document.querySelectorAll('.product-row').forEach(function(row){
        var match=cat==='all'||row.dataset.cat===cat;
        row.style.display=match?'':'none';if(match)shown++;
      });
      document.getElementById('shop-count').textContent='Showing '+shown+' item'+(shown!==1?'s':'');
    }

    /* ═══════════════════════════════════
       PRODUCT DETAIL MODAL
    ═══════════════════════════════════ */
    var currentModalPid=null;

    function openProdModal(pid){
      currentModalPid=pid;var p=PRODUCTS[pid];if(!p)return;
      document.getElementById('prod-modal-title').textContent=p.name;
      document.getElementById('prod-modal-cat').textContent=p.category;
      document.getElementById('prod-modal-price').textContent=p.priceLabel;
      document.getElementById('prod-modal-desc').textContent=p.description;
      document.getElementById('prod-modal-specs').innerHTML=p.specs.map(function(s){
        return '<div class="prod-modal-spec-row"><span class="prod-modal-spec-key">'+s[0]+'</span><span class="prod-modal-spec-val">'+s[1]+'</span></div>';
      }).join('');
      var imgEl=document.getElementById('prod-modal-img');
      if(p.image_path){
        imgEl.innerHTML='<img src="'+p.image_path+'" alt="'+p.name+'" />';
      } else {
        imgEl.innerHTML='<div class="prod-modal-img-ph"><i class="fi fi-rr-box-open-full"></i>Product Photo</div>';
      }
      var stockMap={'in-stock':'<span class="stock-badge in-stock"><span class="stock-dot"></span>In Stock</span>','low-stock':'<span class="stock-badge low-stock"><span class="stock-dot"></span>Low Stock</span>','out-of-stock':'<span class="stock-badge out-of-stock"><span class="stock-dot"></span>Out of Stock</span>'};
      document.getElementById('prod-modal-stock').innerHTML=stockMap[p.stock]||stockMap['in-stock'];
      var unavail=p.stock==='out-of-stock';
      document.getElementById('prod-modal-cart-btn').disabled=unavail;
      document.querySelector('.prod-modal-footer .btn-order-now').disabled=unavail;
      document.getElementById('prod-modal-overlay').classList.add('open');
      document.body.style.overflow='hidden';
    }
    function closeProdModal(){document.getElementById('prod-modal-overlay').classList.remove('open');document.body.style.overflow='';}
    function closeProdModalOutside(e){if(e.target===document.getElementById('prod-modal-overlay'))closeProdModal();}
    function addToCartFromModal(){if(currentModalPid){addToCart(currentModalPid,1);closeProdModal();}}
    function orderNowFromModal(){if(currentModalPid){closeProdModal();orderNow(currentModalPid);}}

    /* ═══════════════════════════════════
       CHECKOUT — SCENE CONTROL
    ═══════════════════════════════════ */
    var coCart={};

    function openCheckout(){
      if(!Object.keys(cart).length){alert('Your cart is empty.');return;}
      coCart=JSON.parse(JSON.stringify(cart));
      renderCoItems();
      document.getElementById('cart-drawer').classList.remove('open');
      document.getElementById('co-overlay').classList.add('open');
      document.body.style.overflow='hidden';
      goScene(1);
    }
    function closeCheckout(){document.getElementById('co-overlay').classList.remove('open');document.body.style.overflow='';}
    function closeCoOutside(e){if(e.target===document.getElementById('co-overlay'))closeCheckout();}
    function goScene(n){
      document.querySelectorAll('.co-scene').forEach(function(s){s.classList.remove('active');});
      document.getElementById('co'+n).classList.add('active');
    }

    /* Scene 1 */
    function renderCoItems(){
      var html='',total=0;
      Object.keys(coCart).forEach(function(pid){
        var p=PRODUCTS[pid];if(!p)return;
        var qty=coCart[pid],sub=p.priceRaw*qty;total+=sub;
        html+='<div class="co-item">'+
          '<div><div class="co-item-name">'+p.name+'</div><div class="co-item-cat">'+p.category+'</div></div>'+
          '<div class="co-item-qty">'+
            '<button class="co-qbtn" onclick="coQty('+pid+',-1)">&#8722;</button>'+
            '<div class="co-qnum" id="cq-'+pid+'">'+qty+'</div>'+
            '<button class="co-qbtn" onclick="coQty('+pid+',1)">&#43;</button>'+
          '</div>'+
          '<div class="co-item-price">\u20B1'+sub.toLocaleString()+'</div>'+
        '</div>';
      });
      document.getElementById('co-items').innerHTML=html||'<p style="color:var(--text-dim)">No items.</p>';
      updateCoTotal();
    }

    function coQty(pid,d){
      coCart[pid]=Math.max(1,(coCart[pid]||1)+d);
      var el=document.getElementById('cq-'+pid);if(el)el.textContent=coCart[pid];
      updateCoTotal();
    }

    function updateCoTotal(){
      var total=0;
      Object.keys(coCart).forEach(function(pid){var p=PRODUCTS[pid];if(p)total+=p.priceRaw*coCart[pid];});
      document.getElementById('co-total').innerHTML='\u20B1'+total.toLocaleString();
    }

    function getCoTotal(){
      var t=0;Object.keys(coCart).forEach(function(pid){var p=PRODUCTS[pid];if(p)t+=p.priceRaw*coCart[pid];});return t;
    }

    /* Scene 2 */
    function validateDetailsAndProceed(){
      var name=document.getElementById('co-name').value.trim();
      var email=document.getElementById('co-email').value.trim();
      var phone=document.getElementById('co-phone').value.trim();
      var school=document.getElementById('co-school').value.trim();
      if(!name||!email||!phone||!school){alert('Please fill in all required fields.');return;}
      var total=getCoTotal();
      var fmt='\u20B1'+total.toLocaleString();
      document.getElementById('co-gcash-amount').textContent=fmt;
      document.getElementById('co-bank-amount').innerHTML=fmt;
      goScene(3);
    }

    /* Scene 3 */
    function coSwitchTab(tab){
      document.getElementById('co-panel-gcash').style.display=tab==='gcash'?'block':'none';
      document.getElementById('co-panel-bank').style.display=tab==='bank'?'block':'none';
      document.getElementById('co-tab-gcash').classList.toggle('active',tab==='gcash');
      document.getElementById('co-tab-bank').classList.toggle('active',tab==='bank');
    }
    function copyCoGcash(){
      navigator.clipboard.writeText('+639177713961').then(function(){
        document.getElementById('co-copy-lbl').textContent='Copied!';document.getElementById('co-copy-icon').className='fi fi-rr-check';
        setTimeout(function(){document.getElementById('co-copy-lbl').textContent='Copy Number';document.getElementById('co-copy-icon').className='fi fi-rr-copy';},2000);
      }).catch(function(){alert('+63 917 771 3961');});
    }
    function copyCoBank(){
      navigator.clipboard.writeText('000-123-4567-8').then(function(){
        document.getElementById('co-copy-bank-lbl').textContent='Copied!';document.getElementById('co-copy-bank-icon').className='fi fi-rr-check';
        setTimeout(function(){document.getElementById('co-copy-bank-lbl').textContent='Copy Account Number';document.getElementById('co-copy-bank-icon').className='fi fi-rr-copy';},2000);
      }).catch(function(){alert('000-123-4567-8');});
    }

    /* File upload */
    function coHandleUpload(e,type){
      var file=e.target.files[0];if(!file)return;
      if(file.size>5*1024*1024){alert('File too large. Maximum 5MB.');return;}
      document.getElementById('upload-'+type).classList.add('has-file');
      document.getElementById('co-idle-'+type).style.display='none';
      document.getElementById('co-preview-'+type).style.display='block';
      document.getElementById('co-fname-'+type).textContent=file.name;
      document.getElementById('co-fsize-'+type).textContent=(file.size/1024).toFixed(1)+' KB';
      if(file.type.startsWith('image/')){
        var r=new FileReader();r.onload=function(ev){var t=document.getElementById('co-thumb-'+type);t.src=ev.target.result;t.style.display='block';};r.readAsDataURL(file);
      }
    }
    function coRemoveUpload(type,e){
      e.stopPropagation();
      document.getElementById('co-file-'+type).value='';
      document.getElementById('upload-'+type).classList.remove('has-file');
      document.getElementById('co-idle-'+type).style.display='block';
      document.getElementById('co-preview-'+type).style.display='none';
      document.getElementById('co-thumb-'+type).src='';
      document.getElementById('co-thumb-'+type).style.display='none';
    }

    /* Scene 3 — Confirm & POST to PHP */
    function confirmShopOrder(){
      var isGcash=document.getElementById('co-tab-gcash').classList.contains('active');
      var file=isGcash?document.getElementById('co-file-gcash').files[0]:document.getElementById('co-file-bank').files[0];
      if(!file){alert('Please attach your '+(isGcash?'GCash':'bank transfer')+' receipt.');return;}

      var btn=document.getElementById('co-confirm-btn');
      btn.disabled=true;btn.textContent='Sending…';

      var items=[];
      Object.keys(coCart).forEach(function(pid){
        var p=PRODUCTS[pid];if(!p)return;
        items.push({product_id:parseInt(pid),product_name:p.name,unit_price:p.priceRaw,quantity:coCart[pid]});
      });

      var fd=new FormData();
      fd.append('action','place_order');
      fd.append('customer_name', document.getElementById('co-name').value.trim());
      fd.append('customer_email',document.getElementById('co-email').value.trim());
      fd.append('customer_phone',document.getElementById('co-phone').value.trim());
      fd.append('school_org',    document.getElementById('co-school').value.trim());
      fd.append('notes',         document.getElementById('co-notes').value.trim());
      fd.append('payment_method',isGcash?'GCash':'Bank Transfer');
      fd.append('total_amount',  getCoTotal());
      fd.append('items',         JSON.stringify(items));
      fd.append('proof',         file, file.name);

      fetch('shop.php',{method:'POST',body:fd})
        .then(function(r){return r.json();})
        .then(function(data){
          btn.disabled=false;btn.innerHTML='Confirm Order <i class="fi fi-rr-check"></i>';
          if(data.success){
            document.getElementById('co-confirm-email').textContent=data.email||'your email';
            var lines=[];
            Object.keys(coCart).forEach(function(pid){var p=PRODUCTS[pid];if(p)lines.push(p.name+' x'+coCart[pid]+' = \u20B1'+(p.priceRaw*coCart[pid]).toLocaleString());});
            document.getElementById('co-confirm-box').innerHTML=
              '<strong>Ref:</strong> '+data.ref+'<br>'+
              '<strong>Name:</strong> '+document.getElementById('co-name').value.trim()+'<br>'+
              '<strong>School:</strong> '+document.getElementById('co-school').value.trim()+'<br><br>'+
              lines.join('<br>')+'<br><br>'+
              '<strong>Total: \u20B1'+getCoTotal().toLocaleString()+'</strong>';
            goScene(4);
          } else {
            alert('Error: '+(data.message||'Something went wrong. Please try again.'));
          }
        })
        .catch(function(){
          btn.disabled=false;btn.innerHTML='Confirm Order <i class="fi fi-rr-check"></i>';
          alert('Network error. Please check your connection and try again.');
        });
    }

    /* ESC key */
    document.addEventListener('keydown',function(e){
      if(e.key==='Escape'){closeProdModal();closeCheckout();document.getElementById('cart-drawer').classList.remove('open');document.body.style.overflow='';}
    });
  </script>
</body>
</html>