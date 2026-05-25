<?php
// PRC-WebApp/admin-products.php
session_start();
mysqli_report(MYSQLI_REPORT_OFF);

$db_host = 'localhost'; $db_user = 'root'; $db_pass = ''; $db_name = 'prc_db';
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($conn->connect_error) die('<p style="color:#ff6b6b;padding:40px;font-family:monospace;">DB error: '.htmlspecialchars($conn->connect_error).'</p>');

define('IMG_DIR',  __DIR__.'/assets/shop/');
define('IMG_URL',  'assets/shop/');
define('MAX_IMG',  5 * 1024 * 1024);
define('IMG_TYPES',['image/jpeg','image/png','image/gif','image/webp']);
if (!is_dir(IMG_DIR)) mkdir(IMG_DIR, 0755, true);

function sf($t,$m){ $_SESSION['pflash']=['type'=>$t,'msg'=>$m]; }
function gf(){ if(!empty($_SESSION['pflash'])){$f=$_SESSION['pflash'];unset($_SESSION['pflash']);return $f;}return null; }

// ── SLUG GENERATOR ──────────────────────────────────────────
function make_slug($name){
    $s = strtolower(trim($name));
    $s = preg_replace('/[^a-z0-9\s-]/', '', $s);
    $s = preg_replace('/[\s-]+/', '-', $s);
    return substr(trim($s,'-'), 0, 80);
}

// ── IMAGE UPLOAD ─────────────────────────────────────────────
function save_image($key, $prefix = 'prod'){
    if (empty($_FILES[$key]['name'])) return null;
    if ($_FILES[$key]['error'] !== UPLOAD_ERR_OK) return null;
    if ($_FILES[$key]['size'] > MAX_IMG) return null;
    $mime = mime_content_type($_FILES[$key]['tmp_name']);
    if (!in_array($mime, IMG_TYPES)) return null;
    $ext  = strtolower(pathinfo($_FILES[$key]['name'], PATHINFO_EXTENSION));
    $name = $prefix.'_'.time().'_'.rand(1000,9999).'.'.$ext;
    $dest = IMG_DIR.$name;
    if (move_uploaded_file($_FILES[$key]['tmp_name'], $dest)) return IMG_URL.$name;
    return null;
}

// ── HANDLE POST ACTIONS ──────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ── ADD PRODUCT ──
    if ($action === 'add_product') {
        $name   = trim($_POST['product_name'] ?? '');
        $cat    = trim($_POST['category']     ?? 'General');
        $price  = floatval($_POST['price']    ?? 0);
        $desc   = trim($_POST['description'] ?? '');
        $stock  = trim($_POST['stock_status'] ?? 'in-stock');
        $sort   = intval($_POST['sort_order'] ?? 0);
        $active = intval($_POST['is_active']  ?? 1);
        $img    = save_image('product_image', 'prod') ?? trim($_POST['image_url'] ?? '');

        // Generate unique slug
        $base_slug = make_slug($name ?: 'product');
        $slug = $base_slug;
        $i = 1;
        while ($conn->query("SELECT product_id FROM prc_shop_products WHERE product_slug='".addslashes($slug)."'")->num_rows > 0) {
            $slug = $base_slug.'-'.$i++;
        }

        if (!$name) { sf('error','Product name is required.'); }
        else {
            $s = $conn->prepare("INSERT INTO prc_shop_products (product_slug,product_name,category,price,description,image_path,stock_status,is_active,sort_order) VALUES (?,?,?,?,?,?,?,?,?)");
            $s->bind_param('sssdsssii', $slug,$name,$cat,$price,$desc,$img,$stock,$active,$sort);
            if ($s->execute()) {
                $pid = (int)$conn->insert_id;
                save_specs($conn, $pid, $_POST['spec_keys'] ?? [], $_POST['spec_vals'] ?? []);
                sf('success', 'Product "'.$name.'" added successfully.');
            } else {
                sf('error', 'Failed to add product.');
            }
            $s->close();
        }
    }

    // ── EDIT PRODUCT ──
    if ($action === 'edit_product') {
        $pid    = intval($_POST['product_id'] ?? 0);
        $name   = trim($_POST['product_name'] ?? '');
        $cat    = trim($_POST['category']     ?? 'General');
        $price  = floatval($_POST['price']    ?? 0);
        $desc   = trim($_POST['description'] ?? '');
        $stock  = trim($_POST['stock_status'] ?? 'in-stock');
        $sort   = intval($_POST['sort_order'] ?? 0);
        $active = intval($_POST['is_active']  ?? 1);

        $new_img = save_image('product_image', 'prod');
        if (!$new_img) $new_img = trim($_POST['image_url'] ?? '');

        if ($pid && $name) {
            // Regenerate slug only if name changed
            $old = $conn->query("SELECT product_name, product_slug FROM prc_shop_products WHERE product_id=$pid")->fetch_assoc();
            $slug = $old['product_slug'];
            if ($old && strtolower($old['product_name']) !== strtolower($name)) {
                $base = make_slug($name);
                $slug = $base; $i = 1;
                while ($conn->query("SELECT product_id FROM prc_shop_products WHERE product_slug='".addslashes($slug)."' AND product_id<>$pid")->num_rows > 0) {
                    $slug = $base.'-'.$i++;
                }
            }

            $img_clause = $new_img ? ',image_path=?' : '';
            if ($new_img) {
                $s = $conn->prepare("UPDATE prc_shop_products SET product_slug=?,product_name=?,category=?,price=?,description=?,stock_status=?,is_active=?,sort_order=?,image_path=? WHERE product_id=?");
                $s->bind_param('sssdsssisi', $slug,$name,$cat,$price,$desc,$stock,$active,$sort,$new_img,$pid);
            } else {
                $s = $conn->prepare("UPDATE prc_shop_products SET product_slug=?,product_name=?,category=?,price=?,description=?,stock_status=?,is_active=?,sort_order=? WHERE product_id=?");
                $s->bind_param('sssdssiii', $slug,$name,$cat,$price,$desc,$stock,$active,$sort,$pid);
            }
            if ($s->execute()) {
                // Delete old specs then re-insert
                $conn->query("DELETE FROM prc_shop_product_specs WHERE product_id=$pid");
                save_specs($conn, $pid, $_POST['spec_keys'] ?? [], $_POST['spec_vals'] ?? []);
                sf('success', 'Product updated.');
            } else { sf('error','Update failed.'); }
            $s->close();
        }
    }

    // ── TOGGLE ACTIVE ──
    if ($action === 'toggle_active') {
        $pid  = intval($_POST['product_id'] ?? 0);
        $val  = intval($_POST['is_active']  ?? 0);
        if ($pid) {
            $conn->query("UPDATE prc_shop_products SET is_active=$val WHERE product_id=$pid");
            sf('success', 'Visibility updated.');
        }
    }

    // ── UPDATE STOCK STATUS ──
    if ($action === 'update_stock') {
        $pid   = intval($_POST['product_id']  ?? 0);
        $stock = trim($_POST['stock_status'] ?? 'in-stock');
        $valid = ['in-stock','low-stock','out-of-stock'];
        if ($pid && in_array($stock, $valid)) {
            $s = $conn->prepare("UPDATE prc_shop_products SET stock_status=? WHERE product_id=?");
            $s->bind_param('si', $stock, $pid);
            $s->execute(); $s->close();
            sf('success', 'Stock status updated.');
        }
    }

    // ── DELETE PRODUCT ──
    if ($action === 'delete_product') {
        $pid = intval($_POST['product_id'] ?? 0);
        if ($pid) {
            // Remove image file if local
            $row = $conn->query("SELECT image_path FROM prc_shop_products WHERE product_id=$pid")->fetch_assoc();
            if ($row && $row['image_path'] && file_exists(__DIR__.'/'.$row['image_path'])) {
                @unlink(__DIR__.'/'.$row['image_path']);
            }
            $conn->query("DELETE FROM prc_shop_products WHERE product_id=$pid");
            sf('success', 'Product deleted.');
        }
    }

    // ── REMOVE IMAGE ──
    if ($action === 'remove_image') {
        $pid = intval($_POST['product_id'] ?? 0);
        if ($pid) {
            $row = $conn->query("SELECT image_path FROM prc_shop_products WHERE product_id=$pid")->fetch_assoc();
            if ($row && $row['image_path'] && file_exists(__DIR__.'/'.$row['image_path'])) {
                @unlink(__DIR__.'/'.$row['image_path']);
            }
            $conn->query("UPDATE prc_shop_products SET image_path=NULL WHERE product_id=$pid");
            sf('success', 'Image removed.');
        }
    }

    header('Location: admin-products.php'); exit;
}

// ── SAVE SPECS HELPER ────────────────────────────────────────
function save_specs($conn, $pid, $keys, $vals){
    $keys = is_array($keys) ? $keys : [];
    $vals = is_array($vals) ? $vals : [];
    $sort = 0;
    foreach ($keys as $i => $key) {
        $key = trim($key); $val = trim($vals[$i] ?? '');
        if ($key === '' && $val === '') continue;
        $s = $conn->prepare("INSERT INTO prc_shop_product_specs (product_id,spec_key,spec_value,sort_order) VALUES (?,?,?,?)");
        $s->bind_param('issi', $pid, $key, $val, $sort);
        $s->execute(); $s->close();
        $sort++;
    }
}

// ── FETCH DATA ───────────────────────────────────────────────
$products = [];
$pr = $conn->query("SELECT * FROM prc_shop_products ORDER BY sort_order ASC, product_id ASC");
if ($pr) while ($r = $pr->fetch_assoc()) $products[] = $r;

$specs_by_product = [];
$sr = $conn->query("SELECT * FROM prc_shop_product_specs ORDER BY sort_order ASC");
if ($sr) while ($r = $sr->fetch_assoc()) $specs_by_product[$r['product_id']][] = $r;

// Order counts per product
$order_counts = [];
$oc = $conn->query("SELECT product_id, COUNT(*) as cnt, SUM(subtotal) as total FROM prc_shop_order_items WHERE product_id IS NOT NULL GROUP BY product_id");
if ($oc) while ($r = $oc->fetch_assoc()) { $order_counts[$r['product_id']] = $r; }

// Stats
$total     = count($products);
$in_stock  = count(array_filter($products, fn($p) => $p['stock_status'] === 'in-stock'));
$low_stock = count(array_filter($products, fn($p) => $p['stock_status'] === 'low-stock'));
$out_stock = count(array_filter($products, fn($p) => $p['stock_status'] === 'out-of-stock'));
$hidden    = count(array_filter($products, fn($p) => !$p['is_active']));
$cats      = count(array_unique(array_column($products, 'category')));
$flash     = gf();
$conn->close();

// Distinct categories for add/edit selects
$all_cats = array_unique(array_merge(
    array_column($products, 'category'),
    ['Robot Kit','Sensor Package','Drone Soccer','MakeX','Spare Parts','Other']
));
sort($all_cats);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1.0"/>
  <meta name="robots" content="noindex,nofollow"/>
  <title>Products — PRC Admin</title>
  <link rel="icon" type="image/png" href="assets/favicon.png"/>
  <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;600;700;800;900&family=Exo+2:wght@300;400;500;600;700&display=swap" rel="stylesheet"/>
  <link rel='stylesheet' href='https://cdn-uicons.flaticon.com/uicons-regular-rounded/css/uicons-regular-rounded.css'>
  <link rel='stylesheet' href='https://cdn-uicons.flaticon.com/uicons-solid-rounded/css/uicons-solid-rounded.css'>
  <style>
    :root{
      --sb-width:248px;--sb-collapsed:68px;--topbar-h:60px;
      --bg-void:#03020D;--bg-deep:#06051A;--bg-card:rgba(10,8,30,0.80);
      --prc-violet:#8B7EFF;--prc-ice:#C4EEFF;
      --creo-amber:#FFA030;--creo-volt:#FFE930;--creo-sky:#44D9FF;
      --creo-green:#00CC88;--admin-red:#FF4D6A;--admin-green:#44FF88;
      --border-neon:rgba(139,126,255,0.18);
      --text-high:#F2EEFF;--text-mid:#C8C0F0;--text-soft:#9A90CC;--text-dim:#6058A0;
      --font-hud:'Orbitron',monospace;--font-body:'Exo 2',sans-serif;
    }
    *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
    html{scroll-behavior:smooth}
    body{font-family:var(--font-body);background:var(--bg-void);color:var(--text-high);overflow-x:hidden;line-height:1.6;min-height:100vh;cursor:none}
    a{text-decoration:none;color:inherit}ul{list-style:none}
    button{font-family:inherit;border:none;background:none;cursor:none}
    img{max-width:100%;display:block}
    body::before{content:'';position:fixed;inset:0;z-index:0;pointer-events:none;
      background-image:linear-gradient(rgba(139,126,255,0.03) 1px,transparent 1px),linear-gradient(90deg,rgba(139,126,255,0.03) 1px,transparent 1px);
      background-size:44px 44px}
    body::after{content:'';position:fixed;inset:0;z-index:0;pointer-events:none;
      background:repeating-linear-gradient(to bottom,transparent,transparent 2px,rgba(0,0,0,0.025) 2px,rgba(0,0,0,0.025) 4px)}

    /* CURSOR */
    .cursor-dot{position:fixed;width:8px;height:8px;border-radius:50%;background:var(--prc-violet);pointer-events:none;z-index:99999;transform:translate(-50%,-50%);box-shadow:0 0 18px rgba(139,126,255,.80)}
    .cursor-ring{position:fixed;width:36px;height:36px;border-radius:50%;border:1px solid rgba(139,126,255,.60);pointer-events:none;z-index:99998;transform:translate(-50%,-50%);transition:width .25s,height .25s}
    .cursor-ring.hovered{width:52px;height:52px;border-color:var(--creo-amber)}

    /* SHELL */
    .admin-shell{display:grid;grid-template-columns:var(--sb-width) 1fr;grid-template-rows:var(--topbar-h) 1fr;min-height:100vh;position:relative;z-index:1;transition:grid-template-columns .30s cubic-bezier(.77,0,.175,1)}
    .admin-shell.sb-collapsed{grid-template-columns:var(--sb-collapsed) 1fr}
    .admin-sidebar-slot{grid-row:1/-1;grid-column:1}

    /* TOPBAR */
    .admin-topbar{grid-column:2;grid-row:1;height:var(--topbar-h);background:rgba(3,2,13,0.92);backdrop-filter:blur(20px);border-bottom:1px solid var(--border-neon);display:flex;align-items:center;padding:0 28px;gap:16px;position:sticky;top:0;z-index:800}
    .topbar-breadcrumb{display:flex;align-items:center;gap:8px;font-family:var(--font-hud);font-size:0.60rem;color:var(--text-dim);letter-spacing:0.10em;text-transform:uppercase}
    .topbar-breadcrumb a{color:var(--text-dim);transition:color 0.2s}
    .topbar-breadcrumb a:hover{color:var(--prc-violet)}
    .topbar-breadcrumb span{color:var(--creo-amber)}
    .topbar-search{display:flex;align-items:center;gap:8px;background:rgba(139,126,255,0.06);border:1px solid var(--border-neon);padding:6px 14px;transition:all .25s;margin-left:auto}
    .topbar-search:focus-within{border-color:var(--prc-violet);box-shadow:0 0 14px rgba(139,126,255,0.22)}
    .topbar-search input{background:none;border:none;outline:none;font-family:var(--font-body);font-size:0.80rem;color:var(--text-mid);width:200px}
    .topbar-search input::placeholder{color:var(--text-dim)}
    .topbar-search i{color:var(--text-dim)}
    .topbar-icon-btn{width:36px;height:36px;background:rgba(139,126,255,0.05);border:1px solid var(--border-neon)!important;border-radius:3px;display:flex;align-items:center;justify-content:center;color:var(--text-soft);transition:all .25s;cursor:none;flex-shrink:0}
    .topbar-icon-btn:hover{background:rgba(139,126,255,0.14);color:var(--prc-violet)}
    .topbar-date{font-family:var(--font-hud);font-size:0.52rem;color:var(--text-dim);letter-spacing:0.10em;white-space:nowrap}
    .topbar-date span{color:var(--creo-volt)}

    /* MAIN */
    .admin-main{grid-column:2;grid-row:2;padding:28px 28px 80px;overflow-y:auto;min-height:calc(100vh - var(--topbar-h))}

    /* PAGE HEADER */
    .page-header{display:flex;align-items:flex-start;justify-content:space-between;gap:20px;flex-wrap:wrap;margin-bottom:24px}
    .page-eyebrow{font-family:var(--font-hud);font-size:0.52rem;letter-spacing:0.20em;text-transform:uppercase;color:var(--creo-amber);margin-bottom:6px;display:flex;align-items:center;gap:8px}
    .page-eyebrow::before{content:'//';color:rgba(255,160,48,0.38)}
    .dot-live{width:7px;height:7px;background:var(--creo-amber);border-radius:50%;box-shadow:0 0 8px rgba(255,160,48,0.90);animation:pulse 1s ease-in-out infinite}
    @keyframes pulse{0%,100%{opacity:1}50%{opacity:.7}}
    .page-title{font-family:var(--font-hud);font-size:clamp(1.3rem,3vw,2rem);font-weight:900;color:#fff;letter-spacing:-0.01em}
    .page-title .accent{color:var(--creo-amber);text-shadow:0 0 22px rgba(255,160,48,0.70)}
    .page-actions{display:flex;gap:10px;flex-wrap:wrap;align-items:center}

    /* KPI STRIP */
    .kpi-strip{display:grid;grid-template-columns:repeat(6,1fr);gap:12px;margin-bottom:24px}
    .kpi-card{background:var(--bg-card);border:1px solid var(--border-neon);padding:14px 16px;position:relative;overflow:hidden;cursor:none;transition:all .22s;clip-path:polygon(0 0,calc(100% - 8px) 0,100% 8px,100% 100%,8px 100%,0 calc(100% - 8px))}
    .kpi-card::before{content:'';position:absolute;top:0;left:0;right:0;height:1px}
    .kpi-card[data-filter]::after{content:'FILTER';position:absolute;bottom:5px;right:8px;font-family:var(--font-hud);font-size:0.36rem;color:var(--text-dim);letter-spacing:0.10em;opacity:0;transition:opacity .2s}
    .kpi-card[data-filter]:hover::after{opacity:1}
    .kpi-card[data-filter]{cursor:none}
    .kpi-card[data-filter]:hover{transform:translateY(-2px)}
    .kpi-card.active-f,.kpi-card[data-filter]:hover{border-color:rgba(255,160,48,0.45);box-shadow:0 0 20px rgba(255,160,48,0.15)}
    .kpi-card.ka::before{background:linear-gradient(90deg,transparent,var(--creo-amber),transparent)}
    .kpi-card.kg::before{background:linear-gradient(90deg,transparent,var(--creo-green),transparent)}
    .kpi-card.ky::before{background:linear-gradient(90deg,transparent,var(--creo-volt),transparent)}
    .kpi-card.kr::before{background:linear-gradient(90deg,transparent,var(--admin-red),transparent)}
    .kpi-card.kv::before{background:linear-gradient(90deg,transparent,var(--prc-violet),transparent)}
    .kpi-card.kd::before{background:linear-gradient(90deg,transparent,var(--text-dim),transparent)}
    .kpi-num{font-family:var(--font-hud);font-size:1.55rem;font-weight:800;line-height:1;display:block;margin-bottom:5px}
    .kpi-card.ka .kpi-num{color:var(--creo-amber);text-shadow:0 0 16px rgba(255,160,48,0.55)}
    .kpi-card.kg .kpi-num{color:var(--creo-green);text-shadow:0 0 16px rgba(0,204,136,0.55)}
    .kpi-card.ky .kpi-num{color:var(--creo-volt);text-shadow:0 0 16px rgba(255,233,48,0.55)}
    .kpi-card.kr .kpi-num{color:var(--admin-red);text-shadow:0 0 16px rgba(255,77,106,0.55)}
    .kpi-card.kv .kpi-num{color:var(--prc-violet);text-shadow:0 0 16px rgba(139,126,255,0.55)}
    .kpi-card.kd .kpi-num{color:var(--text-soft)}
    .kpi-lbl{font-family:var(--font-hud);font-size:0.46rem;color:var(--text-soft);letter-spacing:0.10em;text-transform:uppercase}

    /* FLASH */
    .flash-wrap{margin-bottom:20px}
    .flash-inner{padding:13px 20px;display:flex;align-items:center;gap:10px;font-family:var(--font-hud);font-size:0.62rem;font-weight:600;letter-spacing:0.08em;border:1px solid}
    .flash-inner.success{color:var(--admin-green);border-color:rgba(68,255,136,0.35);background:rgba(68,255,136,0.06)}
    .flash-inner.error{color:var(--admin-red);border-color:rgba(255,77,106,0.35);background:rgba(255,77,106,0.06)}

    /* BUTTONS */
    .btn{display:inline-flex;align-items:center;gap:8px;font-family:var(--font-hud);font-size:0.60rem;font-weight:700;letter-spacing:0.10em;text-transform:uppercase;padding:9px 20px;transition:all .25s;cursor:none;white-space:nowrap}
    .btn-amber{background:rgba(255,160,48,0.10);border:1px solid var(--creo-amber)!important;color:var(--creo-amber);clip-path:polygon(8px 0%,100% 0%,calc(100% - 8px) 100%,0% 100%);box-shadow:0 0 14px rgba(255,160,48,0.18)}
    .btn-amber:hover{background:rgba(255,160,48,0.22);color:#fff;box-shadow:0 0 28px rgba(255,160,48,0.40)}
    .btn-violet{background:rgba(139,126,255,0.08);border:1px solid var(--prc-violet)!important;color:var(--prc-violet);clip-path:polygon(8px 0%,100% 0%,calc(100% - 8px) 100%,0% 100%)}
    .btn-violet:hover{background:rgba(139,126,255,0.18);color:#fff}
    .btn-ghost{background:rgba(139,126,255,0.04);border:1px solid var(--border-neon)!important;color:var(--text-soft)}
    .btn-ghost:hover{background:rgba(139,126,255,0.10);color:var(--text-mid)}
    .btn-red{background:rgba(255,77,106,0.06);border:1px solid rgba(255,77,106,0.35)!important;color:var(--admin-red);clip-path:polygon(8px 0%,100% 0%,calc(100% - 8px) 100%,0% 100%)}
    .btn-red:hover{background:rgba(255,77,106,0.18);color:#fff}
    .btn-sm{padding:6px 14px;font-size:0.54rem;clip-path:polygon(5px 0%,100% 0%,calc(100% - 5px) 100%,0% 100%)}

    /* FILTER BAR */
    .filter-bar{display:flex;align-items:center;gap:10px;flex-wrap:wrap;padding:12px 18px;margin-bottom:16px;background:var(--bg-card);border:1px solid var(--border-neon);position:relative;overflow:hidden}
    .filter-bar::before{content:'';position:absolute;top:0;left:0;right:0;height:1px;background:linear-gradient(90deg,transparent,var(--creo-amber),transparent);opacity:0.35}
    .filter-lbl{font-family:var(--font-hud);font-size:0.50rem;font-weight:700;letter-spacing:0.16em;text-transform:uppercase;color:var(--text-dim);white-space:nowrap}
    .filter-lbl::before{content:'//';margin-right:6px;color:rgba(255,160,48,0.30)}
    .filter-group{display:flex;gap:6px;flex-wrap:wrap}
    .filter-btn{font-family:var(--font-hud);font-size:0.54rem;font-weight:700;letter-spacing:0.08em;text-transform:uppercase;padding:6px 14px;border:1px solid rgba(139,126,255,0.20);color:var(--text-soft);background:transparent;clip-path:polygon(4px 0%,100% 0%,calc(100% - 4px) 100%,0% 100%);transition:all .22s;cursor:none}
    .filter-btn:hover,.filter-btn.active{border-color:var(--creo-amber);color:var(--creo-amber);background:rgba(255,160,48,0.08);box-shadow:0 0 10px rgba(255,160,48,0.15)}
    .filter-right{margin-left:auto;display:flex;gap:8px;align-items:center}
    .filter-select{background:rgba(139,126,255,0.06);border:1px solid rgba(139,126,255,0.22);color:var(--text-mid);font-family:var(--font-hud);font-size:0.52rem;padding:6px 12px;outline:none;cursor:none}
    .filter-select:focus{border-color:var(--prc-violet)}
    .filter-select option{background:var(--bg-deep);color:var(--text-high)}
    .filter-count{font-family:var(--font-hud);font-size:0.50rem;color:var(--text-dim);letter-spacing:0.08em;white-space:nowrap}
    .filter-count span{color:var(--creo-amber)}

    /* ADD FORM PANEL */
    .add-form-toggle{display:flex;align-items:center;gap:14px;margin-bottom:18px}
    .add-form-line{flex:1;height:1px;background:linear-gradient(90deg,rgba(139,126,255,0.18),transparent)}
    .add-form-line.r{background:linear-gradient(90deg,transparent,rgba(139,126,255,0.18))}
    .add-form-wrap{overflow:hidden;max-height:0;opacity:0;transition:max-height 0.45s cubic-bezier(0.4,0,0.2,1),opacity 0.3s ease;margin-bottom:0}
    .add-form-wrap.open{max-height:1000px;opacity:1;margin-bottom:22px}

    /* FORM CARDS (Add + Edit Modal share) */
    .form-card{background:var(--bg-card);border:1px solid var(--border-neon);position:relative;overflow:hidden}
    .form-card::before{content:'';position:absolute;top:0;left:0;right:0;height:1px;background:linear-gradient(90deg,transparent,var(--creo-amber),transparent)}
    .form-hdr{padding:14px 22px 12px;background:rgba(255,160,48,0.04);border-bottom:1px solid rgba(255,160,48,0.14);display:flex;align-items:center;gap:10px}
    .form-hdr-title{font-family:var(--font-hud);font-size:0.64rem;font-weight:700;letter-spacing:0.10em;text-transform:uppercase;color:var(--creo-amber);display:flex;align-items:center;gap:9px}
    .form-body{padding:22px 24px}
    .form-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:14px 18px}
    .form-grid.two{grid-template-columns:1fr 1fr}
    .f-field{display:flex;flex-direction:column;gap:6px}
    .f-field.full{grid-column:1/-1}
    .f-field.two{grid-column:span 2}
    .f-lbl{font-family:var(--font-hud);font-size:0.48rem;font-weight:700;letter-spacing:0.14em;text-transform:uppercase;color:var(--text-soft)}
    .f-req{color:var(--creo-amber)}
    .f-hint{font-size:0.72rem;color:var(--text-dim);margin-top:3px}
    .f-input,.f-select,.f-textarea{background:rgba(255,160,48,0.04);border:1px solid rgba(255,160,48,0.20);color:var(--text-high);font-family:var(--font-body);font-size:0.875rem;padding:10px 13px;width:100%;outline:none;transition:border-color .22s,box-shadow .22s}
    .f-input::placeholder,.f-textarea::placeholder{color:var(--text-dim)}
    .f-input:focus,.f-select:focus,.f-textarea:focus{border-color:var(--creo-amber);box-shadow:0 0 0 2px rgba(255,160,48,0.10)}
    .f-select option{background:var(--bg-deep);color:var(--text-high)}
    .f-textarea{resize:vertical;min-height:88px}
    .form-actions{display:flex;gap:10px;margin-top:18px;padding-top:18px;border-top:1px solid rgba(255,160,48,0.10);flex-wrap:wrap}

    /* IMAGE DROPZONE */
    .img-zone{border:2px dashed rgba(255,160,48,0.28);background:rgba(255,160,48,0.03);min-height:100px;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:8px;text-align:center;padding:18px;transition:all .22s;position:relative;overflow:hidden;cursor:none}
    .img-zone:hover,.img-zone.drag{border-color:var(--creo-amber);background:rgba(255,160,48,0.08);box-shadow:0 0 18px rgba(255,160,48,0.14)}
    .img-zone input[type=file]{position:absolute;inset:0;opacity:0;cursor:none;width:100%;height:100%}
    .img-zone-icon{font-size:1.6rem;opacity:0.40}
    .img-zone-lbl{font-family:var(--font-hud);font-size:0.46rem;font-weight:700;letter-spacing:0.12em;text-transform:uppercase;color:var(--text-dim)}
    .img-zone-sub{font-size:0.72rem;color:var(--text-dim);opacity:0.7}
    .img-preview-wrap{position:relative;width:100%;min-height:100px}
    .img-preview{width:100%;min-height:100px;object-fit:cover;display:block}
    .img-remove-btn{position:absolute;top:6px;right:6px;width:24px;height:24px;background:rgba(255,77,106,0.85);border:none;color:#fff;font-size:0.65rem;cursor:none;display:flex;align-items:center;justify-content:center;transition:background .2s;z-index:2}
    .img-remove-btn:hover{background:var(--admin-red)}
    .img-current{display:flex;align-items:center;gap:10px;padding:10px 12px;background:rgba(139,126,255,0.05);border:1px solid rgba(139,126,255,0.14);margin-bottom:8px}
    .img-current img{width:44px;height:44px;object-fit:cover;border:1px solid rgba(139,126,255,0.18)}
    .img-current-info{flex:1;min-width:0}
    .img-current-name{font-family:var(--font-hud);font-size:0.50rem;color:var(--text-mid);letter-spacing:0.06em;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
    .img-current-sub{font-size:0.72rem;color:var(--text-dim)}

    /* SPECS BUILDER */
    .specs-builder{display:flex;flex-direction:column;gap:6px}
    .spec-row{display:grid;grid-template-columns:1fr 1fr auto;gap:8px;align-items:center}
    .spec-row .f-input{font-size:0.80rem;padding:8px 10px}
    .spec-del{width:28px;height:28px;background:rgba(255,77,106,0.06);border:1px solid rgba(255,77,106,0.24);color:var(--admin-red);display:flex;align-items:center;justify-content:center;cursor:none;font-size:0.75rem;transition:all .2s;flex-shrink:0}
    .spec-del:hover{background:rgba(255,77,106,0.16)}
    .btn-add-spec{font-family:var(--font-hud);font-size:0.48rem;font-weight:700;letter-spacing:0.10em;text-transform:uppercase;padding:7px 14px;border:1px solid rgba(255,160,48,0.25);color:var(--creo-amber);background:transparent;cursor:none;transition:all .2s;display:inline-flex;align-items:center;gap:6px;margin-top:4px}
    .btn-add-spec:hover{background:rgba(255,160,48,0.08)}

    /* PRODUCT LIST */
    .prod-list{display:flex;flex-direction:column;gap:10px}
    .prod-card{background:var(--bg-card);border:1px solid rgba(139,126,255,0.13);position:relative;overflow:hidden;transition:border-color .25s,box-shadow .25s}
    .prod-card::before{content:'';position:absolute;top:0;left:0;bottom:0;width:3px;background:var(--creo-amber);opacity:0.25;transition:opacity .25s}
    .prod-card:hover{border-color:rgba(255,160,48,0.28);box-shadow:0 0 24px rgba(255,160,48,0.07)}
    .prod-card:hover::before{opacity:1}
    .prod-card.hidden-prod{opacity:0.50}
    .prod-card.hidden-prod::before{background:var(--text-dim)}
    .prod-inner{display:grid;grid-template-columns:100px 1fr auto;align-items:stretch}
    .prod-thumb{width:100px;height:100%;min-height:100px;object-fit:cover;border-right:1px solid rgba(139,126,255,0.10);align-self:stretch}
    .prod-thumb-ph{width:100px;min-height:100px;display:flex;align-items:center;justify-content:center;background:rgba(139,126,255,0.04);border-right:1px solid rgba(139,126,255,0.08);flex-shrink:0;align-self:stretch;font-size:1.8rem;opacity:0.18}
    .prod-body{padding:16px 20px;display:flex;flex-direction:column;gap:6px;min-width:0}
    .prod-badges{display:flex;align-items:center;gap:6px;flex-wrap:wrap}
    .badge{font-family:var(--font-hud);font-size:0.44rem;font-weight:700;letter-spacing:0.08em;text-transform:uppercase;padding:2px 9px;border:1px solid;display:inline-flex;align-items:center;gap:4px}
    .bdot{width:5px;height:5px;border-radius:50%;background:currentColor;flex-shrink:0}
    .badge-cat{color:var(--prc-violet);border-color:rgba(139,126,255,0.28);background:rgba(139,126,255,0.06)}
    .badge-in{color:var(--creo-green);border-color:rgba(0,204,136,0.28);background:rgba(0,204,136,0.05)}
    .badge-low{color:var(--creo-amber);border-color:rgba(255,160,48,0.28);background:rgba(255,160,48,0.05)}
    .badge-out{color:var(--admin-red);border-color:rgba(255,77,106,0.28);background:rgba(255,77,106,0.05)}
    .badge-hidden{color:var(--text-dim);border-color:rgba(139,126,255,0.14);background:rgba(139,126,255,0.03)}
    .badge-orders{color:var(--creo-volt);border-color:rgba(255,233,48,0.24);background:rgba(255,233,48,0.04)}
    .prod-name-row{display:flex;align-items:baseline;gap:12px;flex-wrap:wrap}
    .prod-name{font-family:var(--font-hud);font-size:0.90rem;font-weight:800;color:var(--text-high);line-height:1.2}
    .prod-price{font-family:var(--font-hud);font-size:1.05rem;font-weight:900;color:var(--creo-amber);text-shadow:0 0 12px rgba(255,160,48,0.40)}
    .prod-desc{font-size:0.80rem;color:var(--text-dim);line-height:1.65;max-width:580px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
    .prod-specs{display:flex;gap:4px;flex-wrap:wrap}
    .spec-chip{font-family:var(--font-hud);font-size:0.42rem;font-weight:600;letter-spacing:0.06em;text-transform:uppercase;padding:2px 7px;border:1px solid rgba(139,126,255,0.14);color:var(--text-soft);background:rgba(139,126,255,0.04)}
    .prod-meta{font-size:0.70rem;color:var(--text-dim);display:flex;align-items:center;gap:10px}
    .prod-ctrl{border-left:1px solid rgba(139,126,255,0.10);padding:14px 16px;display:flex;flex-direction:column;gap:7px;justify-content:center;align-items:stretch;min-width:120px;background:rgba(139,126,255,0.01)}
    .ctrl-btn{font-family:var(--font-hud);font-size:0.48rem;font-weight:700;letter-spacing:0.08em;text-transform:uppercase;padding:8px 12px;border:1px solid;background:transparent;cursor:none;transition:all .20s;display:flex;align-items:center;justify-content:center;gap:5px;width:100%}
    .ctrl-edit{color:var(--prc-violet);border-color:rgba(139,126,255,0.28)}
    .ctrl-edit:hover{background:rgba(139,126,255,0.10);box-shadow:0 0 12px rgba(139,126,255,0.22)}
    .ctrl-toggle{color:var(--text-soft);border-color:rgba(139,126,255,0.16)}
    .ctrl-toggle:hover{border-color:var(--creo-volt);color:var(--creo-volt);background:rgba(255,233,48,0.06)}
    .ctrl-toggle.active-prod{color:var(--creo-green);border-color:rgba(0,204,136,0.28)}
    .ctrl-del{color:rgba(255,77,106,0.50);border-color:rgba(255,77,106,0.18)}
    .ctrl-del:hover{color:var(--admin-red);border-color:var(--admin-red);background:rgba(255,77,106,0.07)}

    /* CAT GROUP */
    .cat-group-hdr{font-family:var(--font-hud);font-size:0.52rem;font-weight:700;letter-spacing:0.16em;text-transform:uppercase;color:var(--prc-violet);padding:10px 0 8px;border-bottom:1px solid rgba(139,126,255,0.14);margin-bottom:10px;display:flex;align-items:center;gap:10px}
    .cat-group-hdr span{color:var(--text-dim);font-size:0.44rem}
    .cat-group{margin-bottom:22px}

    /* MODALS */
    .modal-overlay{display:none;position:fixed;inset:0;z-index:9000;background:rgba(3,2,13,0.90);backdrop-filter:blur(10px);align-items:center;justify-content:center;padding:20px;overflow-y:auto}
    .modal-overlay.open{display:flex}
    .modal-box{background:var(--bg-deep);border:1px solid var(--border-neon);width:100%;position:relative;max-height:90vh;overflow-y:auto;margin:auto}
    .modal-box::before{content:'';position:absolute;top:0;left:0;right:0;height:1px;background:linear-gradient(90deg,transparent,var(--prc-violet),transparent)}
    .modal-box.sm{max-width:420px}
    .modal-box.md{max-width:680px}
    .modal-hdr{padding:16px 22px 13px;border-bottom:1px solid rgba(139,126,255,0.15);background:rgba(139,126,255,0.06);display:flex;align-items:center;justify-content:space-between;gap:12px}
    .modal-hdr h3{font-family:var(--font-hud);font-size:0.72rem;font-weight:700;letter-spacing:0.08em;color:var(--prc-violet)}
    .modal-close{width:28px;height:28px;background:rgba(139,126,255,0.06);border:1px solid rgba(139,126,255,0.20)!important;color:var(--text-soft);display:flex;align-items:center;justify-content:center;cursor:none;font-size:0.72rem;transition:all .2s}
    .modal-close:hover{background:rgba(139,126,255,0.14);color:var(--prc-violet)}
    .modal-body{padding:22px}
    .modal-footer{padding:13px 22px;border-top:1px solid rgba(139,126,255,0.12);display:flex;gap:10px;justify-content:flex-end;flex-wrap:wrap}

    /* CONFIRM */
    .confirm-icon{font-size:2rem;color:var(--admin-red);display:block;margin-bottom:12px}
    .confirm-title{font-family:var(--font-hud);font-size:0.88rem;font-weight:800;color:#fff;margin-bottom:8px}
    .confirm-text{font-size:0.875rem;color:var(--text-mid);line-height:1.70}

    /* EMPTY / LOADING */
    .state-box{text-align:center;padding:60px 24px;background:var(--bg-card);border:1px solid var(--border-neon)}
    .state-icon{font-size:2.4rem;display:block;margin-bottom:14px;opacity:0.22}
    .state-txt{font-family:var(--font-hud);font-size:0.54rem;color:var(--text-dim);letter-spacing:0.10em}

    /* TOAST */
    .toast-wrap{position:fixed;bottom:28px;right:28px;display:flex;flex-direction:column;gap:10px;z-index:99999}
    .toast{background:var(--bg-deep);border:1px solid var(--border-neon);padding:12px 20px;font-family:var(--font-hud);font-size:0.58rem;font-weight:700;letter-spacing:0.08em;display:flex;align-items:center;gap:10px;min-width:260px;animation:toastIn .3s ease}
    .toast.success{border-color:rgba(68,255,136,0.40);color:var(--admin-green)}
    .toast.error{border-color:rgba(255,77,106,0.40);color:var(--admin-red)}
    @keyframes toastIn{from{opacity:0;transform:translateX(16px)}to{opacity:1;transform:translateX(0)}}

    /* ANIM */
    @keyframes fadeInUp{from{opacity:0;transform:translateY(14px)}to{opacity:1;transform:translateY(0)}}
    .a1{animation:fadeInUp .40s ease .04s both}
    .a2{animation:fadeInUp .40s ease .10s both}
    .a3{animation:fadeInUp .40s ease .16s both}
    .a4{animation:fadeInUp .40s ease .22s both}

    /* SCROLL */
    ::-webkit-scrollbar{width:4px;height:4px}::-webkit-scrollbar-track{background:var(--bg-void)}::-webkit-scrollbar-thumb{background:var(--creo-amber);opacity:.5;border-radius:2px}

    /* RESPONSIVE */
    @media(max-width:1280px){.kpi-strip{grid-template-columns:repeat(3,1fr)}}
    @media(max-width:900px){.admin-shell{grid-template-columns:0 1fr}.admin-sidebar-slot{display:none}.admin-main{padding:18px 16px 60px}.topbar-search{display:none}body{cursor:auto}button{cursor:pointer}.cursor-dot,.cursor-ring{display:none}}
    @media(max-width:768px){.form-grid{grid-template-columns:1fr 1fr}.form-grid.two{grid-template-columns:1fr}.prod-inner{grid-template-columns:1fr}.prod-thumb,.prod-thumb-ph{width:100%;min-height:160px;border-right:none;border-bottom:1px solid rgba(139,126,255,0.10)}.prod-ctrl{border-left:none;border-top:1px solid rgba(139,126,255,0.10);flex-direction:row;min-width:auto}}
    @media(max-width:520px){.form-grid{grid-template-columns:1fr}.f-field.two{grid-column:span 1}}
  </style>
</head>
<body>
<div class="cursor-dot" id="cursorDot"></div>
<div class="cursor-ring" id="cursorRing"></div>

<div class="admin-shell" id="adminShell">
  <div class="admin-sidebar-slot" id="sidebarSlot"></div>

  <!-- TOPBAR -->
  <header class="admin-topbar">
    <div class="topbar-breadcrumb">
      <a href="admin-dashboard.html">PRC Admin</a>
      <i class="fi fi-rr-angle-right" style="font-size:.55rem"></i>
      <span>Products</span>
    </div>
    <div class="topbar-search">
      <i class="fi fi-rr-search"></i>
      <input type="text" id="search-input" placeholder="Search products…" oninput="searchProducts(this.value)"/>
    </div>
    <button class="topbar-icon-btn" title="Refresh" onclick="location.reload()"><i class="fi fi-rr-refresh"></i></button>
    <a href="shop.php" target="_blank" class="topbar-icon-btn" title="View Shop"><i class="fi fi-rr-eye"></i></a>
    <div class="topbar-date"><span id="topbar-date"></span></div>
  </header>

  <!-- MAIN -->
  <main class="admin-main">

    <!-- PAGE HEADER -->
    <div class="page-header a1">
      <div>
        <div class="page-eyebrow"><span class="dot-live"></span> Shop Management // Products</div>
        <h1 class="page-title">Product <span class="accent">Manager</span></h1>
      </div>
      <div class="page-actions">
        <a href="admin-orders.php" class="btn btn-ghost btn-sm"><i class="fi fi-rr-receipt"></i> View Orders</a>
        <button class="btn btn-amber btn-sm" onclick="toggleAddForm()">
          <i class="fi fi-rr-plus" id="toggle-icon"></i>
          <span id="toggle-label">Add Product</span>
        </button>
      </div>
    </div>

    <!-- FLASH -->
    <?php if ($flash): ?>
    <div class="flash-wrap a1">
      <div class="flash-inner <?= $flash['type'] ?>">
        <i class="fi fi-rr-<?= $flash['type']==='success'?'check':'exclamation' ?>"></i>
        <?= htmlspecialchars($flash['msg']) ?>
      </div>
    </div>
    <?php endif; ?>

    <!-- KPI STRIP -->
    <div class="kpi-strip a2">
      <div class="kpi-card ka" data-filter="all" onclick="setFilter('all',this)">
        <span class="kpi-num"><?= $total ?></span>
        <div class="kpi-lbl">Total Products</div>
      </div>
      <div class="kpi-card kg" data-filter="in-stock" onclick="setFilter('in-stock',this)">
        <span class="kpi-num"><?= $in_stock ?></span>
        <div class="kpi-lbl">In Stock</div>
      </div>
      <div class="kpi-card ky" data-filter="low-stock" onclick="setFilter('low-stock',this)">
        <span class="kpi-num"><?= $low_stock ?></span>
        <div class="kpi-lbl">Low Stock</div>
      </div>
      <div class="kpi-card kr" data-filter="out-of-stock" onclick="setFilter('out-of-stock',this)">
        <span class="kpi-num"><?= $out_stock ?></span>
        <div class="kpi-lbl">Out of Stock</div>
      </div>
      <div class="kpi-card kd" data-filter="hidden" onclick="setFilter('hidden',this)">
        <span class="kpi-num"><?= $hidden ?></span>
        <div class="kpi-lbl">Hidden</div>
      </div>
      <div class="kpi-card kv">
        <span class="kpi-num"><?= $cats ?></span>
        <div class="kpi-lbl">Categories</div>
      </div>
    </div>

    <!-- ADD PRODUCT TOGGLE DIVIDER -->
    <div class="add-form-toggle a3">
      <div class="add-form-line"></div>
      <button class="btn btn-amber btn-sm" onclick="toggleAddForm()" id="toggle-btn-2">
        <i class="fi fi-rr-plus"></i> New Product
      </button>
      <div class="add-form-line r"></div>
    </div>

    <!-- ADD PRODUCT FORM -->
    <div class="add-form-wrap" id="add-form-wrap">
      <div class="form-card">
        <div class="form-hdr">
          <div class="form-hdr-title"><i class="fi fi-rr-plus-small"></i> Add New Product</div>
        </div>
        <div class="form-body">
          <form method="POST" action="admin-products.php" enctype="multipart/form-data" id="add-form">
            <input type="hidden" name="action" value="add_product"/>
            <div class="form-grid">
              <div class="f-field two">
                <label class="f-lbl">Product Name <span class="f-req">*</span></label>
                <input class="f-input" type="text" name="product_name" placeholder="e.g. Basic Robotics Kit" required/>
              </div>
              <div class="f-field">
                <label class="f-lbl">Price (₱) <span class="f-req">*</span></label>
                <input class="f-input" type="number" name="price" placeholder="1200" min="0" step="0.01" required/>
              </div>
              <div class="f-field">
                <label class="f-lbl">Category <span class="f-req">*</span></label>
                <select class="f-select" name="category">
                  <?php foreach ($all_cats as $c): ?>
                  <option value="<?= htmlspecialchars($c) ?>"><?= htmlspecialchars($c) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="f-field">
                <label class="f-lbl">Stock Status</label>
                <select class="f-select" name="stock_status">
                  <option value="in-stock">In Stock</option>
                  <option value="low-stock">Low Stock</option>
                  <option value="out-of-stock">Out of Stock</option>
                </select>
              </div>
              <div class="f-field">
                <label class="f-lbl">Sort Order</label>
                <input class="f-input" type="number" name="sort_order" value="<?= $total ?>" min="0"/>
                <span class="f-hint">Lower = shown first in shop</span>
              </div>
              <div class="f-field">
                <label class="f-lbl">Visibility</label>
                <select class="f-select" name="is_active">
                  <option value="1">Visible (Active)</option>
                  <option value="0">Hidden</option>
                </select>
              </div>
              <div class="f-field full">
                <label class="f-lbl">Description <span class="f-req">*</span></label>
                <textarea class="f-textarea" name="description" placeholder="Brief product description for the shop listing…" required></textarea>
              </div>
              <div class="f-field full">
                <label class="f-lbl">Product Image</label>
                <div class="img-zone" id="n-img-zone">
                  <input type="file" name="product_image" id="n-img-file" accept="image/*"/>
                  <span class="img-zone-icon"><i class="fi fi-rr-picture"></i></span>
                  <span class="img-zone-lbl">Drop image or click to browse</span>
                  <span class="img-zone-sub">PNG, JPG, WEBP · max 5MB</span>
                </div>
                <div class="img-preview-wrap" id="n-img-preview-wrap" style="display:none">
                  <img id="n-img-preview" class="img-preview" src="" alt="Preview"/>
                  <button type="button" class="img-remove-btn" id="n-img-remove"><i class="fi fi-rr-cross"></i></button>
                </div>
                <div class="f-field" style="margin-top:8px">
                  <label class="f-lbl">— or paste image URL / path</label>
                  <input class="f-input" type="text" name="image_url" id="n-img-url" placeholder="assets/shop/my-product.jpg"/>
                </div>
              </div>
              <div class="f-field full">
                <label class="f-lbl">Specifications</label>
                <div class="specs-builder" id="n-specs-builder">
                  <div class="spec-row">
                    <input class="f-input" type="text" name="spec_keys[]" placeholder="Key (e.g. Includes)"/>
                    <input class="f-input" type="text" name="spec_vals[]" placeholder="Value (e.g. 2 motors + sensors)"/>
                    <button type="button" class="spec-del" onclick="removeSpec(this)"><i class="fi fi-rr-cross"></i></button>
                  </div>
                </div>
                <button type="button" class="btn-add-spec" onclick="addSpec('n-specs-builder')"><i class="fi fi-rr-plus"></i> Add Spec Row</button>
              </div>
            </div>
            <div class="form-actions">
              <button type="submit" class="btn btn-amber"><i class="fi fi-rr-plus"></i> Add Product</button>
              <button type="button" class="btn btn-ghost btn-sm" onclick="document.getElementById('add-form').reset();resetImgZone('n');">
                <i class="fi fi-rr-refresh"></i> Clear
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <!-- FILTER BAR -->
    <div class="filter-bar a3">
      <span class="filter-lbl">Filter</span>
      <div class="filter-group">
        <button class="filter-btn active" data-fval="all"        onclick="setFilter('all',this)">All</button>
        <button class="filter-btn" data-fval="in-stock"          onclick="setFilter('in-stock',this)">In Stock</button>
        <button class="filter-btn" data-fval="low-stock"         onclick="setFilter('low-stock',this)">Low Stock</button>
        <button class="filter-btn" data-fval="out-of-stock"      onclick="setFilter('out-of-stock',this)">Out of Stock</button>
        <button class="filter-btn" data-fval="hidden"            onclick="setFilter('hidden',this)">Hidden</button>
      </div>
      <div class="filter-right">
        <select class="filter-select" id="cat-filter" onchange="setCatFilter(this.value)">
          <option value="all">All Categories</option>
          <?php foreach ($all_cats as $c): ?>
          <option value="<?= htmlspecialchars($c) ?>"><?= htmlspecialchars($c) ?></option>
          <?php endforeach; ?>
        </select>
        <select class="filter-select" id="view-mode" onchange="setViewMode(this.value)">
          <option value="list">List View</option>
          <option value="cats">By Category</option>
        </select>
        <span class="filter-count">Showing <span id="showing-count"><?= $total ?></span> of <span><?= $total ?></span></span>
      </div>
    </div>

    <!-- PRODUCT LIST -->
    <div id="prod-list" class="a4">
      <?php if (empty($products)): ?>
      <div class="state-box">
        <span class="state-icon"><i class="fi fi-rr-box-open-full"></i></span>
        <div class="state-txt">No products yet. Use the form above to add your first product.</div>
      </div>
      <?php else: ?>
      <div class="prod-list" id="prod-list-inner">
        <?php foreach ($products as $p):
          $pid    = $p['product_id'];
          $specs  = $specs_by_product[$pid] ?? [];
          $oc     = $order_counts[$pid] ?? null;
          $scls   = $p['stock_status'] === 'in-stock' ? 'badge-in' : ($p['stock_status'] === 'low-stock' ? 'badge-low' : 'badge-out');
          $slbl   = $p['stock_status'] === 'in-stock' ? 'In Stock' : ($p['stock_status'] === 'low-stock' ? 'Low Stock' : 'Out of Stock');
          $date   = $p['created_at'] ? date('M d, Y', strtotime($p['created_at'])) : '—';
          // build JSON for edit modal
          $editData = json_encode([
            'product_id'   => $pid,
            'product_name' => $p['product_name'],
            'category'     => $p['category'],
            'price'        => (float)$p['price'],
            'description'  => $p['description'] ?? '',
            'image_path'   => $p['image_path'] ?? '',
            'stock_status' => $p['stock_status'],
            'is_active'    => (int)$p['is_active'],
            'sort_order'   => (int)$p['sort_order'],
            'specs'        => array_map(fn($s)=>['key'=>$s['spec_key'],'val'=>$s['spec_value']], $specs),
          ]);
        ?>
        <div class="prod-card <?= !$p['is_active'] ? 'hidden-prod' : '' ?>"
             data-stock="<?= $p['stock_status'] ?>"
             data-active="<?= $p['is_active'] ?>"
             data-cat="<?= htmlspecialchars($p['category']) ?>"
             data-search="<?= strtolower(htmlspecialchars($p['product_name'].' '.$p['category'].' '.($p['description']??''))) ?>">
          <div class="prod-inner">
            <?php if ($p['image_path']): ?>
            <img class="prod-thumb" src="<?= htmlspecialchars($p['image_path']) ?>" alt="<?= htmlspecialchars($p['product_name']) ?>"/>
            <?php else: ?>
            <div class="prod-thumb-ph"><i class="fi fi-rr-box-open-full"></i></div>
            <?php endif; ?>
            <div class="prod-body">
              <div class="prod-badges">
                <span class="badge badge-cat"><?= htmlspecialchars($p['category']) ?></span>
                <span class="badge <?= $scls ?>"><span class="bdot"></span><?= $slbl ?></span>
                <?php if (!$p['is_active']): ?><span class="badge badge-hidden">Hidden</span><?php endif; ?>
                <?php if ($oc): ?>
                <span class="badge badge-orders"><i class="fi fi-rr-receipt"></i> <?= $oc['cnt'] ?> order<?= $oc['cnt']!=1?'s':'' ?></span>
                <?php endif; ?>
              </div>
              <div class="prod-name-row">
                <span class="prod-name"><?= htmlspecialchars($p['product_name']) ?></span>
                <span class="prod-price">&#8369;<?= number_format((float)$p['price'], 0) ?></span>
              </div>
              <div class="prod-desc" title="<?= htmlspecialchars($p['description'] ?? '') ?>"><?= htmlspecialchars($p['description'] ?? '') ?></div>
              <?php if ($specs): ?>
              <div class="prod-specs">
                <?php foreach (array_slice($specs, 0, 4) as $s): ?>
                <span class="spec-chip"><?= htmlspecialchars($s['spec_key']) ?>: <?= htmlspecialchars($s['spec_value']) ?></span>
                <?php endforeach; ?>
                <?php if (count($specs) > 4): ?>
                <span class="spec-chip">+<?= count($specs)-4 ?> more</span>
                <?php endif; ?>
              </div>
              <?php endif; ?>
              <div class="prod-meta">
                <span><i class="fi fi-rr-sort-alt" style="font-size:.65rem;margin-right:3px"></i>Sort: <?= $p['sort_order'] ?></span>
                <span><i class="fi fi-rr-calendar" style="font-size:.65rem;margin-right:3px"></i><?= $date ?></span>
                <span style="font-family:var(--font-hud);font-size:.44rem;color:rgba(139,126,255,0.40)"><?= htmlspecialchars($p['product_slug']) ?></span>
              </div>
            </div>
            <div class="prod-ctrl">
              <button class="ctrl-btn ctrl-edit" onclick="openEdit(<?= htmlspecialchars($editData) ?>)">
                <i class="fi fi-rr-edit"></i> Edit
              </button>
              <!-- Quick stock change -->
              <form method="POST" action="admin-products.php" style="width:100%">
                <input type="hidden" name="action" value="update_stock"/>
                <input type="hidden" name="product_id" value="<?= $pid ?>"/>
                <select name="stock_status" class="f-select" style="width:100%;font-size:.46rem;padding:6px 8px" onchange="this.form.submit()">
                  <option value="in-stock"     <?= $p['stock_status']==='in-stock'?'selected':'' ?>>In Stock</option>
                  <option value="low-stock"    <?= $p['stock_status']==='low-stock'?'selected':'' ?>>Low Stock</option>
                  <option value="out-of-stock" <?= $p['stock_status']==='out-of-stock'?'selected':'' ?>>Out of Stock</option>
                </select>
              </form>
              <!-- Toggle visibility -->
              <form method="POST" action="admin-products.php" style="width:100%">
                <input type="hidden" name="action" value="toggle_active"/>
                <input type="hidden" name="product_id" value="<?= $pid ?>"/>
                <input type="hidden" name="is_active" value="<?= $p['is_active'] ? 0 : 1 ?>"/>
                <button type="submit" class="ctrl-btn ctrl-toggle <?= $p['is_active'] ? 'active-prod' : '' ?>">
                  <i class="fi fi-rr-<?= $p['is_active'] ? 'eye' : 'eye-crossed' ?>"></i>
                  <?= $p['is_active'] ? 'Visible' : 'Hidden' ?>
                </button>
              </form>
              <button class="ctrl-btn ctrl-del" onclick="openConfirm(<?= $pid ?>,'<?= htmlspecialchars(addslashes($p['product_name'])) ?>')">
                <i class="fi fi-rr-trash"></i> Delete
              </button>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>

  </main>
</div>

<!-- ── EDIT PRODUCT MODAL ── -->
<div class="modal-overlay" id="modal-edit" onclick="closeModalOutside(event,'modal-edit')">
  <div class="modal-box md">
    <div class="modal-hdr">
      <h3><i class="fi fi-rr-edit" style="margin-right:8px"></i>Edit Product</h3>
      <button class="modal-close" onclick="closeModal('modal-edit')"><i class="fi fi-rr-cross"></i></button>
    </div>
    <form method="POST" action="admin-products.php" enctype="multipart/form-data" id="edit-form">
      <input type="hidden" name="action" value="edit_product"/>
      <input type="hidden" name="product_id" id="e-id"/>
      <div class="modal-body">
        <div class="form-grid">
          <div class="f-field two">
            <label class="f-lbl">Product Name <span class="f-req">*</span></label>
            <input class="f-input" type="text" name="product_name" id="e-name" required/>
          </div>
          <div class="f-field">
            <label class="f-lbl">Price (₱) <span class="f-req">*</span></label>
            <input class="f-input" type="number" name="price" id="e-price" min="0" step="0.01" required/>
          </div>
          <div class="f-field">
            <label class="f-lbl">Category</label>
            <select class="f-select" name="category" id="e-cat">
              <?php foreach ($all_cats as $c): ?>
              <option value="<?= htmlspecialchars($c) ?>"><?= htmlspecialchars($c) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="f-field">
            <label class="f-lbl">Stock Status</label>
            <select class="f-select" name="stock_status" id="e-stock">
              <option value="in-stock">In Stock</option>
              <option value="low-stock">Low Stock</option>
              <option value="out-of-stock">Out of Stock</option>
            </select>
          </div>
          <div class="f-field">
            <label class="f-lbl">Sort Order</label>
            <input class="f-input" type="number" name="sort_order" id="e-sort" min="0"/>
          </div>
          <div class="f-field">
            <label class="f-lbl">Visibility</label>
            <select class="f-select" name="is_active" id="e-active">
              <option value="1">Visible (Active)</option>
              <option value="0">Hidden</option>
            </select>
          </div>
          <div class="f-field full">
            <label class="f-lbl">Description</label>
            <textarea class="f-textarea" name="description" id="e-desc"></textarea>
          </div>
          <div class="f-field full">
            <label class="f-lbl">Product Image</label>
            <div class="img-current" id="e-img-current" style="display:none">
              <img id="e-img-current-thumb" src="" alt=""/>
              <div class="img-current-info">
                <div class="img-current-name" id="e-img-current-name">current image</div>
                <div class="img-current-sub">Current — upload below to replace</div>
              </div>
              <form method="POST" action="admin-products.php" style="flex-shrink:0" id="e-img-rm-form">
                <input type="hidden" name="action" value="remove_image"/>
                <input type="hidden" name="product_id" id="e-img-rm-pid"/>
                <button type="submit" class="btn btn-red btn-sm" style="clip-path:none" title="Remove image"><i class="fi fi-rr-trash"></i></button>
              </form>
            </div>
            <div class="img-zone" id="e-img-zone">
              <input type="file" name="product_image" id="e-img-file" accept="image/*"/>
              <span class="img-zone-icon"><i class="fi fi-rr-picture"></i></span>
              <span class="img-zone-lbl">Drop new image or click to browse</span>
              <span class="img-zone-sub">PNG, JPG, WEBP · max 5MB</span>
            </div>
            <div class="img-preview-wrap" id="e-img-preview-wrap" style="display:none">
              <img id="e-img-preview" class="img-preview" src="" alt="Preview"/>
              <button type="button" class="img-remove-btn" id="e-img-remove"><i class="fi fi-rr-cross"></i></button>
            </div>
            <div class="f-field" style="margin-top:8px">
              <label class="f-lbl">— or paste image URL / path</label>
              <input class="f-input" type="text" name="image_url" id="e-img-url" placeholder="assets/shop/my-product.jpg"/>
            </div>
          </div>
          <div class="f-field full">
            <label class="f-lbl">Specifications</label>
            <div class="specs-builder" id="e-specs-builder"></div>
            <button type="button" class="btn-add-spec" onclick="addSpec('e-specs-builder')"><i class="fi fi-rr-plus"></i> Add Spec Row</button>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost btn-sm" onclick="closeModal('modal-edit')">Cancel</button>
        <button type="submit" class="btn btn-violet btn-sm"><i class="fi fi-rr-check"></i> Save Changes</button>
      </div>
    </form>
  </div>
</div>

<!-- ── CONFIRM DELETE MODAL ── -->
<div class="modal-overlay" id="modal-confirm" onclick="closeModalOutside(event,'modal-confirm')">
  <div class="modal-box sm" style="text-align:center">
    <div class="modal-hdr" style="justify-content:center;border-color:rgba(255,77,106,0.22);background:rgba(255,77,106,0.05)">
      <h3 style="color:var(--admin-red)">Confirm Delete</h3>
    </div>
    <div class="modal-body">
      <i class="fi fi-rr-trash confirm-icon"></i>
      <div class="confirm-title" id="confirm-title">Delete Product?</div>
      <div class="confirm-text">This will permanently remove the product and all its specs. Orders referencing it will retain the product name. <strong>This cannot be undone.</strong></div>
    </div>
    <div class="modal-footer" style="justify-content:center">
      <button type="button" class="btn btn-ghost btn-sm" onclick="closeModal('modal-confirm')">Cancel</button>
      <form method="POST" action="admin-products.php" style="display:inline">
        <input type="hidden" name="action" value="delete_product"/>
        <input type="hidden" name="product_id" id="confirm-pid"/>
        <button type="submit" class="btn btn-red btn-sm"><i class="fi fi-rr-trash"></i> Yes, Delete</button>
      </form>
    </div>
  </div>
</div>

<!-- TOAST -->
<div class="toast-wrap" id="toast-wrap"></div>

<!-- SIDEBAR LOADER -->
<script>
(function(){
  var slot = document.getElementById('sidebarSlot');
  if (!slot) return;
  fetch('admin-sidebar.html').then(function(r){return r.text();})
    .then(function(html){
      slot.innerHTML = html;
      slot.querySelectorAll('script').forEach(function(old){
        var s = document.createElement('script'); s.textContent = old.textContent; document.body.appendChild(s);
      });
    }).catch(function(){
      slot.innerHTML = '<aside style="position:fixed;top:0;left:0;height:100vh;width:248px;background:#04031A;border-right:1px solid rgba(139,126,255,0.18);display:flex;align-items:center;justify-content:center;font-family:Orbitron,monospace;font-size:0.55rem;color:rgba(139,126,255,0.40);letter-spacing:0.12em;text-align:center;padding:20px;">SIDEBAR</aside>';
    });
})();
document.addEventListener('prc-sidebar-toggle', function(e){
  document.getElementById('adminShell').classList.toggle('sb-collapsed', e.detail.collapsed);
});
if (localStorage.getItem('prc_sidebar_collapsed') === '1')
  document.getElementById('adminShell').classList.add('sb-collapsed');
</script>

<script>
/* ══════════════════════════════════
   CURSOR
══════════════════════════════════ */
(function(){
  var dot=document.getElementById('cursorDot'), ring=document.getElementById('cursorRing');
  if (!dot||!ring) return;
  var mx=0,my=0,rx=0,ry=0;
  document.addEventListener('mousemove',function(e){mx=e.clientX;my=e.clientY;dot.style.left=mx+'px';dot.style.top=my+'px';});
  (function l(){rx+=(mx-rx)*.12;ry+=(my-ry)*.12;ring.style.left=rx+'px';ring.style.top=ry+'px';requestAnimationFrame(l);})();
  document.addEventListener('mouseover',function(e){if(e.target.closest('a,button,input,select,textarea'))ring.classList.add('hovered');});
  document.addEventListener('mouseout',function(e){if(e.target.closest('a,button,input,select,textarea'))ring.classList.remove('hovered');});
})();

/* ══════════════════════════════════
   TOPBAR DATE
══════════════════════════════════ */
(function(){
  var el=document.getElementById('topbar-date'),M=['JAN','FEB','MAR','APR','MAY','JUN','JUL','AUG','SEP','OCT','NOV','DEC'];
  function upd(){var d=new Date(),h=d.getHours(),m=d.getMinutes(),ap=h>=12?'PM':'AM';h=h%12||12;
    el.innerHTML=M[d.getMonth()]+' '+String(d.getDate()).padStart(2,'0')+', '+d.getFullYear()+'&nbsp;<span>'+String(h).padStart(2,'0')+':'+String(m).padStart(2,'0')+' '+ap+'</span>';}
  upd();setInterval(upd,30000);
})();

/* ══════════════════════════════════
   ADD FORM TOGGLE
══════════════════════════════════ */
var addFormOpen = false;
function toggleAddForm(){
  addFormOpen = !addFormOpen;
  var wrap = document.getElementById('add-form-wrap');
  wrap.classList.toggle('open', addFormOpen);
  document.getElementById('toggle-icon').className  = addFormOpen ? 'fi fi-rr-cross' : 'fi fi-rr-plus';
  document.getElementById('toggle-label').textContent = addFormOpen ? 'Cancel' : 'Add Product';
  if (addFormOpen) wrap.scrollIntoView({behavior:'smooth', block:'nearest'});
}

/* ══════════════════════════════════
   IMAGE DROPZONE
══════════════════════════════════ */
var _imgData = {};

function wireImgZone(prefix){
  var zone   = document.getElementById(prefix+'-img-zone');
  var fileIn = document.getElementById(prefix+'-img-file');
  var prevWr = document.getElementById(prefix+'-img-preview-wrap');
  var prev   = document.getElementById(prefix+'-img-preview');
  var rmBtn  = document.getElementById(prefix+'-img-remove');
  if (!zone) return;

  fileIn.addEventListener('change',function(){
    if (fileIn.files && fileIn.files[0]) handleFile(prefix, fileIn.files[0]);
  });
  zone.addEventListener('dragover',function(e){e.preventDefault();zone.classList.add('drag');});
  zone.addEventListener('dragleave',function(){zone.classList.remove('drag');});
  zone.addEventListener('drop',function(e){
    e.preventDefault();zone.classList.remove('drag');
    var f = e.dataTransfer.files && e.dataTransfer.files[0];
    if (f) handleFile(prefix, f);
  });
  if (rmBtn) rmBtn.addEventListener('click',function(e){e.stopPropagation();resetImgZone(prefix);});
}

function handleFile(prefix, file){
  if (!file.type.startsWith('image/')) { showToast('Please select an image file.','error'); return; }
  if (file.size > 5*1024*1024) { showToast('Image must be under 5MB.','error'); return; }
  var r = new FileReader();
  r.onload = function(ev){
    _imgData[prefix] = ev.target.result;
    showImgPreview(prefix, ev.target.result);
  };
  r.readAsDataURL(file);
}

function showImgPreview(prefix, src){
  document.getElementById(prefix+'-img-zone').style.display = 'none';
  var wr = document.getElementById(prefix+'-img-preview-wrap');
  var im = document.getElementById(prefix+'-img-preview');
  im.src = src;
  wr.style.display = 'block';
}

function resetImgZone(prefix){
  _imgData[prefix] = null;
  var zone = document.getElementById(prefix+'-img-zone');
  var wr   = document.getElementById(prefix+'-img-preview-wrap');
  var fi   = document.getElementById(prefix+'-img-file');
  if (zone) zone.style.display = 'flex';
  if (wr)   wr.style.display   = 'none';
  if (fi)   fi.value = '';
  var prev = document.getElementById(prefix+'-img-preview');
  if (prev) prev.src = '';
}

wireImgZone('n');
wireImgZone('e');

/* ══════════════════════════════════
   SPECS BUILDER
══════════════════════════════════ */
function addSpec(builderId){
  var builder = document.getElementById(builderId);
  var row = document.createElement('div');
  row.className = 'spec-row';
  row.innerHTML =
    '<input class="f-input" type="text" name="spec_keys[]" placeholder="Key (e.g. Warranty)"/>'+
    '<input class="f-input" type="text" name="spec_vals[]" placeholder="Value (e.g. 6 months)"/>'+
    '<button type="button" class="spec-del" onclick="removeSpec(this)"><i class="fi fi-rr-cross"></i></button>';
  builder.appendChild(row);
}

function removeSpec(btn){ btn.closest('.spec-row').remove(); }

/* ══════════════════════════════════
   FILTER & SEARCH
══════════════════════════════════ */
var activeFilter  = 'all';
var activeCat     = 'all';
var activeSearch  = '';
var viewMode      = 'list';

var allCards = Array.from(document.querySelectorAll('.prod-card'));

function setFilter(val, btn){
  activeFilter = val;
  // Update filter buttons
  document.querySelectorAll('.filter-btn').forEach(function(b){ b.classList.remove('active'); });
  if (btn && btn.classList.contains('filter-btn')) btn.classList.add('active');
  // Update KPI cards
  document.querySelectorAll('.kpi-card[data-filter]').forEach(function(k){ k.classList.remove('active-f'); });
  var kpiMatch = document.querySelector('.kpi-card[data-filter="'+val+'"]');
  if (kpiMatch) kpiMatch.classList.add('active-f');
  applyFilters();
}

function setCatFilter(val){ activeCat = val; applyFilters(); }
function searchProducts(val){ activeSearch = val.toLowerCase().trim(); applyFilters(); }
function setViewMode(val){ viewMode = val; applyFilters(); }

function applyFilters(){
  var visible = allCards.filter(function(c){
    var stockOk   = activeFilter === 'all'
                    || (activeFilter === 'hidden' && c.dataset.active === '0')
                    || (activeFilter !== 'hidden' && c.dataset.stock === activeFilter);
    var catOk     = activeCat === 'all' || c.dataset.cat === activeCat;
    var searchOk  = !activeSearch || c.dataset.search.includes(activeSearch);
    return stockOk && catOk && searchOk;
  });

  allCards.forEach(function(c){ c.style.display = 'none'; });

  var listInner = document.getElementById('prod-list-inner');
  if (!listInner) return;

  if (viewMode === 'cats') {
    // Rebuild by category grouping
    var catGroups = {};
    visible.forEach(function(c){
      var cat = c.dataset.cat || 'Other';
      if (!catGroups[cat]) catGroups[cat] = [];
      catGroups[cat].push(c);
    });
    listInner.innerHTML = '';
    Object.keys(catGroups).sort().forEach(function(cat){
      var grp = document.createElement('div');
      grp.className = 'cat-group';
      grp.innerHTML = '<div class="cat-group-hdr">'+escHtml(cat)+'<span>('+catGroups[cat].length+')</span></div>';
      catGroups[cat].forEach(function(c){ c.style.display=''; grp.appendChild(c); });
      listInner.appendChild(grp);
    });
  } else {
    // Flatten list
    listInner.innerHTML = '';
    visible.forEach(function(c){ c.style.display = ''; listInner.appendChild(c); });
  }

  document.getElementById('showing-count').textContent = visible.length;

  // Empty state
  var existing = document.querySelector('.state-box');
  if (!visible.length) {
    if (!existing) {
      var sb = document.createElement('div');
      sb.className = 'state-box'; sb.id = 'empty-state';
      sb.innerHTML = '<span class="state-icon"><i class="fi fi-rr-search"></i></span><div class="state-txt">No products match your filters.</div>';
      listInner.appendChild(sb);
    }
  } else {
    var emp = document.getElementById('empty-state');
    if (emp) emp.remove();
  }
}

/* ══════════════════════════════════
   MODALS
══════════════════════════════════ */
function openModal(id){ document.getElementById(id).classList.add('open'); document.body.style.overflow='hidden'; }
function closeModal(id){ document.getElementById(id).classList.remove('open'); document.body.style.overflow=''; }
function closeModalOutside(e,id){ if(e.target===document.getElementById(id)) closeModal(id); }
document.addEventListener('keydown',function(e){
  if(e.key==='Escape') document.querySelectorAll('.modal-overlay.open').forEach(function(m){closeModal(m.id);});
});

/* EDIT MODAL */
function openEdit(data){
  document.getElementById('e-id').value    = data.product_id;
  document.getElementById('e-name').value  = data.product_name;
  document.getElementById('e-price').value = data.price;
  document.getElementById('e-cat').value   = data.category;
  document.getElementById('e-stock').value = data.stock_status;
  document.getElementById('e-sort').value  = data.sort_order;
  document.getElementById('e-active').value= data.is_active;
  document.getElementById('e-desc').value  = data.description;
  document.getElementById('e-img-url').value = '';
  document.getElementById('e-img-rm-pid').value = data.product_id;

  // Current image display
  var curWr   = document.getElementById('e-img-current');
  var curThumb = document.getElementById('e-img-current-thumb');
  var curName  = document.getElementById('e-img-current-name');
  if (data.image_path) {
    curWr.style.display = 'flex';
    curThumb.src = data.image_path;
    curName.textContent = data.image_path.split('/').pop();
  } else {
    curWr.style.display = 'none';
    curThumb.src = '';
  }

  // Reset new image zone
  resetImgZone('e');

  // Build specs
  var builder = document.getElementById('e-specs-builder');
  builder.innerHTML = '';
  (data.specs || []).forEach(function(s){
    var row = document.createElement('div');
    row.className = 'spec-row';
    row.innerHTML =
      '<input class="f-input" type="text" name="spec_keys[]" value="'+escHtml(s.key)+'" placeholder="Key"/>'+
      '<input class="f-input" type="text" name="spec_vals[]" value="'+escHtml(s.val)+'" placeholder="Value"/>'+
      '<button type="button" class="spec-del" onclick="removeSpec(this)"><i class="fi fi-rr-cross"></i></button>';
    builder.appendChild(row);
  });
  if (!data.specs || !data.specs.length) addSpec('e-specs-builder');

  wireImgZone('e');
  openModal('modal-edit');
}

/* CONFIRM DELETE */
function openConfirm(pid, name){
  document.getElementById('confirm-pid').value   = pid;
  document.getElementById('confirm-title').textContent = 'Delete "'+name+'"?';
  openModal('modal-confirm');
}

/* ══════════════════════════════════
   TOAST
══════════════════════════════════ */
function showToast(msg, type){
  var wrap = document.getElementById('toast-wrap');
  var t = document.createElement('div');
  t.className = 'toast '+(type||'');
  t.innerHTML = '<i class="fi fi-rr-'+(type==='success'?'check':'exclamation')+'"></i> '+msg;
  wrap.appendChild(t);
  setTimeout(function(){t.style.opacity='0';t.style.transition='opacity .4s';setTimeout(function(){t.remove();},400);},3500);
}

/* ══════════════════════════════════
   UTILS
══════════════════════════════════ */
function escHtml(s){ return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }

/* ══════════════════════════════════
   INIT
══════════════════════════════════ */
// Mark first KPI card as active
document.querySelector('.kpi-card[data-filter="all"]').classList.add('active-f');
// PHP flash already rendered — no extra toast needed
</script>
</body>
</html>