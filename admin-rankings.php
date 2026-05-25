<?php
// PRC-WebApp/admin-rankings.php
session_start();
mysqli_report(MYSQLI_REPORT_OFF);

$db_host='localhost';$db_user='root';$db_pass='';$db_name='prc_db';
$conn=new mysqli($db_host,$db_user,$db_pass,$db_name);
if($conn->connect_error) die('<p style="color:#ff6b6b;padding:40px;font-family:monospace;">DB error: '.htmlspecialchars($conn->connect_error).'</p>');

define('RANKING_UPLOAD_DIR',__DIR__.'/assets/rankings/admin/');
define('RANKING_UPLOAD_URL','assets/rankings/admin/');
define('MAX_FILE_SIZE',50*1024*1024);
define('ALLOWED_IMG',['image/jpeg','image/png','image/gif','image/webp']);
if(!is_dir(RANKING_UPLOAD_DIR)) mkdir(RANKING_UPLOAD_DIR,0755,true);

function set_flash($t,$m){$_SESSION['rflash']=['type'=>$t,'msg'=>$m];}
function get_flash(){if(!empty($_SESSION['rflash'])){$f=$_SESSION['rflash'];unset($_SESSION['rflash']);return $f;}return null;}

function upload_image($file_key, $prefix='img') {
    if(empty($_FILES[$file_key]['name'])) return null;
    if($_FILES[$file_key]['error']!==UPLOAD_ERR_OK) return null;
    if($_FILES[$file_key]['size']>MAX_FILE_SIZE) return null;
    $mime=mime_content_type($_FILES[$file_key]['tmp_name']);
    if(!in_array($mime,ALLOWED_IMG)) return null;
    $ext=strtolower(pathinfo($_FILES[$file_key]['name'],PATHINFO_EXTENSION));
    $safe=$prefix.'_'.time().'_'.rand(1000,9999).'.'.$ext;
    $dest=RANKING_UPLOAD_DIR.$safe;
    if(move_uploaded_file($_FILES[$file_key]['tmp_name'],$dest))
        return RANKING_UPLOAD_URL.$safe;
    return null;
}

// ── HANDLE POST ACTIONS ──────────────────────────────────────
if($_SERVER['REQUEST_METHOD']==='POST'){
    $action=$_POST['action']??'';

    // ── META: hero description ──
    if($action==='save_meta'){
        $desc=trim($_POST['hero_desc']??'');
        $s=$conn->prepare("INSERT INTO prc_rankings_meta(meta_key,meta_value) VALUES('hero_desc',?) ON DUPLICATE KEY UPDATE meta_value=?");
        $s->bind_param('ss',$desc,$desc);
        $s->execute() ? set_flash('success','Page description updated.') : set_flash('error','Failed to update description.');
        $s->close();
    }

    // ── YEARS: add ──
    if($action==='add_year'){
        $label=trim($_POST['year_label']??'');
        $edition=trim($_POST['edition']??'National Finals');
        $date=trim($_POST['event_date']??'');
        $venue=trim($_POST['venue']??'');
        $sort=(int)($_POST['year_sort']??0);
        if($label===''){set_flash('error','Year label is required.');}
        else{
            $s=$conn->prepare("INSERT INTO prc_rankings_years(year_label,edition,event_date,venue,year_sort) VALUES(?,?,?,?,?)");
            $s->bind_param('ssssi',$label,$edition,$date,$venue,$sort);
            $s->execute() ? set_flash('success','Year "'.$label.'" added.') : set_flash('error','That year label already exists.');
            $s->close();
        }
    }

    // ── YEARS: edit ──
    if($action==='edit_year'){
        $yid=(int)($_POST['year_id']??0);
        $label=trim($_POST['year_label']??'');
        $edition=trim($_POST['edition']??'');
        $date=trim($_POST['event_date']??'');
        $venue=trim($_POST['venue']??'');
        $sort=(int)($_POST['year_sort']??0);
        $active=(int)($_POST['is_active']??1);
        if($yid&&$label){
            $s=$conn->prepare("UPDATE prc_rankings_years SET year_label=?,edition=?,event_date=?,venue=?,year_sort=?,is_active=? WHERE year_id=?");
            $s->bind_param('ssssiii',$label,$edition,$date,$venue,$sort,$active,$yid);
            $s->execute() ? set_flash('success','Year updated.') : set_flash('error','Update failed.');
            $s->close();
        }
    }

    // ── YEARS: delete ──
    if($action==='delete_year'){
        $yid=(int)($_POST['year_id']??0);
        if($yid){$conn->query("DELETE FROM prc_rankings_years WHERE year_id=$yid");set_flash('success','Year deleted.');}
    }

    // ── CATEGORIES: add ──
    if($action==='add_cat'){
        $slug=trim($_POST['cat_slug']??'');
        $name=trim($_POST['cat_name']??'');
        $color=trim($_POST['cat_color']??'rv');
        $sort=(int)($_POST['cat_sort']??0);
        $logo=upload_image('cat_logo','cat_logo') ?? trim($_POST['cat_logo_url']??'');
        if(!$slug||!$name){set_flash('error','Slug and name are required.');}
        else{
            $s=$conn->prepare("INSERT INTO prc_rankings_categories(cat_slug,cat_name,cat_logo,cat_color,cat_sort) VALUES(?,?,?,?,?)");
            $s->bind_param('ssssi',$slug,$name,$logo,$color,$sort);
            $s->execute() ? set_flash('success','Category added.') : set_flash('error','Slug already exists.');
            $s->close();
        }
    }

    // ── CATEGORIES: edit ──
    if($action==='edit_cat'){
        $cid=(int)($_POST['cat_id']??0);
        $slug=trim($_POST['cat_slug']??'');
        $name=trim($_POST['cat_name']??'');
        $color=trim($_POST['cat_color']??'rv');
        $sort=(int)($_POST['cat_sort']??0);
        $active=(int)($_POST['is_active']??1);
        $logo=upload_image('cat_logo','cat_logo');
        if(!$logo) $logo=trim($_POST['cat_logo_url']??'');
        if($cid&&$slug&&$name){
            $s=$conn->prepare("UPDATE prc_rankings_categories SET cat_slug=?,cat_name=?,cat_logo=?,cat_color=?,cat_sort=?,is_active=? WHERE cat_id=?");
            $s->bind_param('ssssiii',$slug,$name,$logo,$color,$sort,$active,$cid);
            $s->execute() ? set_flash('success','Category updated.') : set_flash('error','Update failed.');
            $s->close();
        }
    }

    // ── CATEGORIES: delete ──
    if($action==='delete_cat'){
        $cid=(int)($_POST['cat_id']??0);
        if($cid){$conn->query("DELETE FROM prc_rankings_categories WHERE cat_id=$cid");set_flash('success','Category and all its subcategories deleted.');}
    }

    // ── SUBCATEGORIES: add ──
    if($action==='add_sub'){
        $cid=(int)($_POST['cat_id']??0);
        $slug=trim($_POST['sub_slug']??'');
        $name=trim($_POST['sub_name']??'');
        $sort=(int)($_POST['sub_sort']??0);
        if(!$cid||!$slug||!$name){set_flash('error','All fields required.');}
        else{
            $s=$conn->prepare("INSERT INTO prc_rankings_subcategories(cat_id,sub_slug,sub_name,sub_sort) VALUES(?,?,?,?)");
            $s->bind_param('issi',$cid,$slug,$name,$sort);
            $s->execute() ? set_flash('success','Subcategory added.') : set_flash('error','Slug already exists in this category.');
            $s->close();
        }
    }

    // ── SUBCATEGORIES: edit ──
    if($action==='edit_sub'){
        $sid=(int)($_POST['sub_id']??0);
        $slug=trim($_POST['sub_slug']??'');
        $name=trim($_POST['sub_name']??'');
        $sort=(int)($_POST['sub_sort']??0);
        $active=(int)($_POST['is_active']??1);
        if($sid&&$slug&&$name){
            $s=$conn->prepare("UPDATE prc_rankings_subcategories SET sub_slug=?,sub_name=?,sub_sort=?,is_active=? WHERE sub_id=?");
            $s->bind_param('ssiii',$slug,$name,$sort,$active,$sid);
            $s->execute() ? set_flash('success','Subcategory updated.') : set_flash('error','Update failed.');
            $s->close();
        }
    }

    // ── SUBCATEGORIES: delete ──
    if($action==='delete_sub'){
        $sid=(int)($_POST['sub_id']??0);
        if($sid){$conn->query("DELETE FROM prc_rankings_subcategories WHERE sub_id=$sid");set_flash('success','Subcategory deleted.');}
    }

    // ── ENTRIES: add ──
    if($action==='add_entry'){
        $yid=(int)($_POST['year_id']??0);
        $sid=(int)($_POST['sub_id']??0);
        $rank=(int)($_POST['rank_pos']??4);
        $school=trim($_POST['school_name']??'');
        $team=trim($_POST['team_name']??'');
        $members=trim($_POST['members']??'');
        $status=trim($_POST['status_label']??'');
        $sort=(int)($_POST['entry_sort']??0);
        $logo_path=upload_image('school_logo','slogo') ?? trim($_POST['school_logo_url']??'');
        $photo_path=upload_image('podium_photo','pphoto') ?? trim($_POST['podium_photo_url']??'');
        $award_photo_path=upload_image('award_photo','aphoto') ?? trim($_POST['award_photo_url']??'');
        $award_detail=trim($_POST['award_detail']??'');
        if(!$yid||!$sid||!$school){set_flash('error','Year, subcategory, and school name are required.');}
        else{
            $s=$conn->prepare("INSERT INTO prc_rankings_entries(year_id,sub_id,rank_pos,school_name,team_name,members,school_logo,podium_photo,status_label,entry_sort) VALUES(?,?,?,?,?,?,?,?,?,?)");
            $s->bind_param('iiisssssssi',$yid,$sid,$rank,$school,$team,$members,$logo_path,$photo_path,$status,$sort);
            // fix: 10 params
            $stmt=$conn->prepare("INSERT INTO prc_rankings_entries(year_id,sub_id,rank_pos,school_name,team_name,members,school_logo,podium_photo,status_label,entry_sort) VALUES(?,?,?,?,?,?,?,?,?,?)");
            $stmt->bind_param('iiissssssi',$yid,$sid,$rank,$school,$team,$members,$logo_path,$photo_path,$status,$sort);
            if($stmt->execute()){
                $eid=(int)$conn->insert_id;
                if($award_photo_path||$award_detail){
                    $s2=$conn->prepare("INSERT INTO prc_rankings_award_extras(entry_id,award_detail,award_photo) VALUES(?,?,?) ON DUPLICATE KEY UPDATE award_detail=?,award_photo=?");
                    $s2->bind_param('issss',$eid,$award_detail,$award_photo_path,$award_detail,$award_photo_path);
                    $s2->execute();$s2->close();
                }
                set_flash('success','Entry added.');
            } else {
                set_flash('error','Failed to add entry.');
            }
            $stmt->close();
        }
    }

    // ── ENTRIES: edit ──
    if($action==='edit_entry'){
        $eid=(int)($_POST['entry_id']??0);
        $yid=(int)($_POST['year_id']??0);
        $sid=(int)($_POST['sub_id']??0);
        $rank=(int)($_POST['rank_pos']??4);
        $school=trim($_POST['school_name']??'');
        $team=trim($_POST['team_name']??'');
        $members=trim($_POST['members']??'');
        $status=trim($_POST['status_label']??'');
        $sort=(int)($_POST['entry_sort']??0);
        $active=(int)($_POST['is_active']??1);
        $logo_path=upload_image('school_logo','slogo');
        if(!$logo_path) $logo_path=trim($_POST['school_logo_url']??'');
        $photo_path=upload_image('podium_photo','pphoto');
        if(!$photo_path) $photo_path=trim($_POST['podium_photo_url']??'');
        $award_photo_path=upload_image('award_photo','aphoto');
        if(!$award_photo_path) $award_photo_path=trim($_POST['award_photo_url']??'');
        $award_detail=trim($_POST['award_detail']??'');
        if($eid&&$school){
            $s=$conn->prepare("UPDATE prc_rankings_entries SET year_id=?,sub_id=?,rank_pos=?,school_name=?,team_name=?,members=?,school_logo=?,podium_photo=?,status_label=?,entry_sort=?,is_active=? WHERE entry_id=?");
            $s->bind_param('iiissssssiii',$yid,$sid,$rank,$school,$team,$members,$logo_path,$photo_path,$status,$sort,$active,$eid);
            if($s->execute()){
                $s2=$conn->prepare("INSERT INTO prc_rankings_award_extras(entry_id,award_detail,award_photo) VALUES(?,?,?) ON DUPLICATE KEY UPDATE award_detail=?,award_photo=?");
                $s2->bind_param('issss',$eid,$award_detail,$award_photo_path,$award_detail,$award_photo_path);
                $s2->execute();$s2->close();
                set_flash('success','Entry updated.');
            } else {set_flash('error','Update failed.');}
            $s->close();
        }
    }

    // ── ENTRIES: delete ──
    if($action==='delete_entry'){
        $eid=(int)($_POST['entry_id']??0);
        if($eid){$conn->query("DELETE FROM prc_rankings_entries WHERE entry_id=$eid");set_flash('success','Entry deleted.');}
    }

    header('Location: admin-rankings.php');exit;
}

// ── FETCH DATA ───────────────────────────────────────────────
$meta_row=$conn->query("SELECT meta_value FROM prc_rankings_meta WHERE meta_key='hero_desc'")->fetch_row();
$hero_desc=$meta_row[0]??'';

$years=[];
$yr=$conn->query("SELECT * FROM prc_rankings_years ORDER BY year_sort ASC, year_label DESC");
if($yr) while($r=$yr->fetch_assoc()) $years[]=$r;

$cats=[];
$cr=$conn->query("SELECT * FROM prc_rankings_categories ORDER BY cat_sort ASC");
if($cr) while($r=$cr->fetch_assoc()) $cats[]=$r;

$all_subs=[];
$sr=$conn->query("SELECT s.*,c.cat_name,c.cat_slug FROM prc_rankings_subcategories s JOIN prc_rankings_categories c ON s.cat_id=c.cat_id ORDER BY s.cat_id ASC,s.sub_sort ASC");
if($sr) while($r=$sr->fetch_assoc()) $all_subs[]=$r;

$subs_by_cat=[];
foreach($all_subs as $s) $subs_by_cat[$s['cat_id']][]=$s;

$all_entries=[];
$er=$conn->query("SELECT e.*,ae.award_detail,ae.award_photo,s.sub_name,s.sub_slug,c.cat_name,c.cat_slug,c.cat_color,y.year_label FROM prc_rankings_entries e JOIN prc_rankings_subcategories s ON e.sub_id=s.sub_id JOIN prc_rankings_categories c ON s.cat_id=c.cat_id JOIN prc_rankings_years y ON e.year_id=y.year_id LEFT JOIN prc_rankings_award_extras ae ON ae.entry_id=e.entry_id ORDER BY y.year_label DESC,c.cat_sort ASC,s.sub_sort ASC,e.rank_pos ASC,e.entry_sort ASC");
if($er) while($r=$er->fetch_assoc()) $all_entries[]=$r;

$total_entries=count($all_entries);
$flash=get_flash();
$conn->close();

function status_default($pos){return match((int)$pos){1=>'Champion',2=>'1st Runner-up',3=>'2nd Runner-up',default=>'Finalist'};}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1.0"/>
  <meta name="robots" content="noindex,nofollow"/>
  <title>Rankings — PRC Admin</title>
  <link rel="icon" type="image/png" href="assets/favicon.png"/>
  <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;600;700;800;900&family=Exo+2:wght@300;400;500;600;700&display=swap" rel="stylesheet"/>
  <link rel='stylesheet' href='https://cdn-uicons.flaticon.com/uicons-regular-rounded/css/uicons-regular-rounded.css'>
  <link rel='stylesheet' href='https://cdn-uicons.flaticon.com/uicons-solid-rounded/css/uicons-solid-rounded.css'>
  <style>
    :root{--sb-width:248px;--sb-collapsed:68px;--topbar-h:60px;--bg-void:#03020D;--bg-card:rgba(10,8,30,0.80);--prc-violet:#8B7EFF;--prc-ice:#C4EEFF;--creo-amber:#FFA030;--creo-volt:#FFE930;--creo-sky:#44D9FF;--admin-red:#FF4D6A;--admin-green:#44FF88;--border-neon:rgba(139,126,255,0.18);--text-high:#F2EEFF;--text-mid:#C8C0F0;--text-soft:#9A90CC;--text-dim:#6058A0;--font-hud:'Orbitron',monospace;--font-body:'Exo 2',sans-serif}
    *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
    html{scroll-behavior:smooth}
    body{font-family:var(--font-body);background:var(--bg-void);color:var(--text-high);overflow-x:hidden;line-height:1.6;min-height:100vh;cursor:none}
    img{max-width:100%;display:block} a{text-decoration:none;color:inherit} ul{list-style:none}
    button{font-family:inherit;border:none;background:none;cursor:none}
    body::before{content:'';position:fixed;inset:0;z-index:0;pointer-events:none;background-image:linear-gradient(rgba(139,126,255,0.03) 1px,transparent 1px),linear-gradient(90deg,rgba(139,126,255,0.03) 1px,transparent 1px);background-size:44px 44px}
    body::after{content:'';position:fixed;inset:0;z-index:0;pointer-events:none;background:repeating-linear-gradient(to bottom,transparent,transparent 2px,rgba(0,0,0,0.025) 2px,rgba(0,0,0,0.025) 4px)}
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
    .topbar-breadcrumb span{color:var(--prc-violet)}
    .topbar-right{margin-left:auto;display:flex;align-items:center;gap:10px}
    .topbar-icon-btn{width:36px;height:36px;background:rgba(139,126,255,0.05);border:1px solid var(--border-neon)!important;border-radius:3px;display:flex;align-items:center;justify-content:center;color:var(--text-soft);transition:all .25s;cursor:none}
    .topbar-icon-btn:hover{background:rgba(139,126,255,0.14);color:var(--prc-violet)}
    .topbar-public-btn{display:inline-flex;align-items:center;gap:7px;font-family:var(--font-hud);font-size:0.56rem;font-weight:700;letter-spacing:0.10em;text-transform:uppercase;color:var(--prc-violet);padding:7px 14px;border:1px solid rgba(139,126,255,0.35)!important;background:rgba(139,126,255,0.06);clip-path:polygon(6px 0%,100% 0%,calc(100% - 6px) 100%,0% 100%);transition:all .25s}
    .topbar-public-btn:hover{background:rgba(139,126,255,0.16);color:#fff}
    .topbar-date{font-family:var(--font-hud);font-size:0.52rem;color:var(--text-dim);letter-spacing:0.10em;white-space:nowrap}
    .topbar-date span{color:var(--creo-volt)}
    /* MAIN */
    .admin-main{grid-column:2;grid-row:2;padding:28px 28px 60px;overflow-y:auto;min-height:calc(100vh - var(--topbar-h))}
    /* PAGE HEADER */
    .page-header{display:flex;align-items:flex-start;justify-content:space-between;gap:20px;flex-wrap:wrap;margin-bottom:24px}
    .page-eyebrow{font-family:var(--font-hud);font-size:0.52rem;letter-spacing:0.20em;text-transform:uppercase;color:var(--admin-red);margin-bottom:6px;display:flex;align-items:center;gap:8px}
    .dot-live{width:7px;height:7px;background:var(--admin-red);border-radius:50%;box-shadow:0 0 8px rgba(255,77,106,0.90);animation:sbPulse 1s ease-in-out infinite}
    @keyframes sbPulse{0%,100%{opacity:1}50%{opacity:.7}}
    .page-title{font-family:var(--font-hud);font-size:clamp(1.3rem,3vw,2rem);font-weight:900;color:#fff;letter-spacing:-0.01em}
    .page-title .accent{color:var(--admin-red);text-shadow:0 0 22px rgba(255,77,106,0.70)}
    .page-stats{display:flex;gap:12px;flex-wrap:wrap;align-items:center}
    .stat-chip{background:rgba(255,77,106,0.06);border:1px solid rgba(255,77,106,0.20);padding:10px 18px;text-align:center;clip-path:polygon(6px 0%,100% 0%,calc(100% - 6px) 100%,0% 100%)}
    .stat-chip-num{font-family:var(--font-hud);font-size:1.4rem;font-weight:800;color:var(--admin-red);display:block;line-height:1;text-shadow:0 0 14px rgba(255,77,106,0.70)}
    .stat-chip-lbl{font-family:var(--font-hud);font-size:0.48rem;color:var(--text-soft);text-transform:uppercase;letter-spacing:0.12em;display:block;margin-top:3px}
    /* TABS */
    .admin-tabs{display:flex;gap:2px;margin-bottom:24px;border-bottom:1px solid var(--border-neon)}
    .admin-tab{font-family:var(--font-hud);font-size:0.60rem;font-weight:700;letter-spacing:0.12em;text-transform:uppercase;padding:11px 22px;color:var(--text-soft);cursor:pointer!important;border:none;background:transparent;transition:all .22s;border-bottom:2px solid transparent;position:relative;top:1px}
    .admin-tab:hover{color:var(--text-mid)}
    .admin-tab.active{color:var(--prc-violet);border-bottom-color:var(--prc-violet);text-shadow:0 0 10px rgba(139,126,255,0.55)}
    .tab-pane{display:none}.tab-pane.active{display:block}
    /* FLASH */
    .flash-wrap{margin-bottom:20px}
    .flash-inner{padding:13px 20px;display:flex;align-items:center;gap:10px;font-family:var(--font-hud);font-size:0.63rem;font-weight:600;letter-spacing:0.08em;border:1px solid}
    .flash-inner.success{color:var(--admin-green);border-color:rgba(68,255,136,0.35);background:rgba(68,255,136,0.06)}
    .flash-inner.error{color:var(--admin-red);border-color:rgba(255,77,106,0.35);background:rgba(255,77,106,0.06)}
    /* CARDS */
    .panel-card{background:var(--bg-card);border:1px solid rgba(139,126,255,0.18);position:relative;margin-bottom:20px}
    .panel-card::before{content:'';position:absolute;top:0;left:0;right:0;height:1px;background:linear-gradient(90deg,transparent,var(--prc-violet),transparent)}
    .panel-card.red-card::before{background:linear-gradient(90deg,transparent,var(--admin-red),transparent)}
    .panel-card.green-card::before{background:linear-gradient(90deg,transparent,var(--admin-green),transparent)}
    .panel-hdr{background:rgba(139,126,255,0.05);padding:14px 18px;border-bottom:1px solid rgba(139,126,255,0.12);display:flex;align-items:center;gap:10px}
    .panel-hdr.red{background:rgba(255,77,106,0.05);border-color:rgba(255,77,106,0.15)}
    .panel-hdr i{color:var(--prc-violet);font-size:0.95rem}
    .panel-hdr.red i{color:var(--admin-red)}
    .panel-hdr-text h3{font-family:var(--font-hud);font-size:0.70rem;font-weight:700;letter-spacing:0.06em;color:var(--text-high)}
    .panel-hdr-text p{font-size:0.76rem;color:var(--text-soft);margin-top:2px}
    .panel-body{padding:18px 20px}
    /* GRID LAYOUT */
    .two-col{display:grid;grid-template-columns:1fr 1fr;gap:20px}
    .three-col{display:grid;grid-template-columns:1fr 1fr 1fr;gap:16px}
    /* FORM */
    .field{margin-bottom:14px}
    .field:last-of-type{margin-bottom:0}
    .field-label{font-family:var(--font-hud);font-size:0.50rem;font-weight:700;letter-spacing:0.14em;text-transform:uppercase;color:var(--text-soft);display:flex;align-items:center;gap:6px;margin-bottom:7px}
    .field-label .req{color:var(--admin-red)}
    .field-input,.field-select,.field-textarea{width:100%;padding:10px 13px;background:rgba(139,126,255,0.04);border:1px solid rgba(139,126,255,0.22);color:var(--text-high);font-family:var(--font-body);font-size:0.88rem;outline:none;transition:border-color .22s,box-shadow .22s;appearance:none}
    .field-input::placeholder,.field-textarea::placeholder{color:var(--text-dim)}
    .field-input:focus,.field-select:focus,.field-textarea:focus{border-color:var(--prc-violet);box-shadow:0 0 0 2px rgba(139,126,255,0.14)}
    .field-textarea{resize:vertical;min-height:80px}
    .field-hint{font-size:0.74rem;color:var(--text-dim);margin-top:5px}
    .field-row{display:flex;gap:12px}
    .field-row .field{flex:1}
    /* UPLOAD ZONE */
    .upload-zone{border:1.5px dashed rgba(139,126,255,0.28);background:rgba(139,126,255,0.03);padding:16px;text-align:center;cursor:pointer!important;transition:all .25s;position:relative;overflow:hidden}
    .upload-zone:hover{border-color:var(--prc-violet);background:rgba(139,126,255,0.08)}
    .upload-zone input[type="file"]{position:absolute;inset:0;opacity:0;cursor:pointer!important;width:100%;height:100%}
    .upload-zone-label{font-family:var(--font-hud);font-size:0.56rem;font-weight:700;color:var(--text-soft);letter-spacing:0.08em;display:block;margin-bottom:2px}
    .upload-zone-sub{font-size:0.72rem;color:var(--text-dim)}
    /* BUTTONS */
    .btn-primary{display:inline-flex;align-items:center;justify-content:center;gap:8px;padding:10px 22px;font-family:var(--font-hud);font-size:0.60rem;font-weight:700;letter-spacing:0.12em;text-transform:uppercase;color:var(--prc-violet);border:1px solid var(--prc-violet)!important;box-shadow:0 0 14px rgba(139,126,255,0.22);cursor:pointer!important;transition:all .25s;background:transparent;clip-path:polygon(8px 0%,100% 0%,calc(100% - 8px) 100%,0% 100%)}
    .btn-primary:hover{background:rgba(139,126,255,0.12);box-shadow:0 0 28px rgba(139,126,255,0.48);color:#fff;transform:translateY(-1px)}
    .btn-red{color:var(--admin-red);border-color:rgba(255,77,106,0.40)!important;box-shadow:0 0 14px rgba(255,77,106,0.18)}
    .btn-red:hover{background:rgba(255,77,106,0.12);box-shadow:0 0 28px rgba(255,77,106,0.45)}
    .btn-sm{padding:7px 14px;font-size:0.52rem;clip-path:polygon(5px 0%,100% 0%,calc(100% - 5px) 100%,0% 100%)}
    .btn-full{width:100%}
    /* SECTION LABEL */
    .section-label{font-family:var(--font-hud);font-size:0.64rem;font-weight:700;letter-spacing:0.12em;text-transform:uppercase;color:var(--text-mid);margin-bottom:14px;display:flex;align-items:center;gap:10px;padding-bottom:8px;border-bottom:1px solid rgba(139,126,255,0.14)}
    .section-label::before{content:'//';color:rgba(139,126,255,0.35);font-size:0.70rem}
    /* DATA TABLE */
    .data-table{width:100%;border-collapse:collapse}
    .data-table th{font-family:var(--font-hud);font-size:0.50rem;font-weight:700;letter-spacing:0.14em;text-transform:uppercase;color:var(--text-soft);padding:10px 14px;background:rgba(139,126,255,0.07);border-bottom:1px solid rgba(139,126,255,0.18);text-align:left;white-space:nowrap}
    .data-table td{padding:11px 14px;border-bottom:1px solid rgba(139,126,255,0.07);font-size:0.86rem;color:var(--text-mid);vertical-align:middle}
    .data-table tr:last-child td{border-bottom:none}
    .data-table tr:hover td{background:rgba(139,126,255,0.04)}
    .data-table-wrap{border:1px solid rgba(139,126,255,0.18);overflow:hidden;overflow-x:auto}
    .data-table-wrap::before{content:'';display:block;height:1px;background:linear-gradient(90deg,transparent,var(--prc-violet),transparent)}
    /* ICON BUTTONS */
    .icon-btn{display:inline-flex;align-items:center;justify-content:center;width:30px;height:30px;border:1px solid;border-radius:2px;cursor:pointer!important;transition:all .20s;background:transparent;font-size:0.80rem}
    .icon-btn.edit{color:var(--creo-sky);border-color:rgba(68,217,255,0.30);background:rgba(68,217,255,0.04)}
    .icon-btn.edit:hover{background:rgba(68,217,255,0.14);box-shadow:0 0 10px rgba(68,217,255,0.28)}
    .icon-btn.del{color:var(--admin-red);border-color:rgba(255,77,106,0.30);background:rgba(255,77,106,0.04)}
    .icon-btn.del:hover{background:rgba(255,77,106,0.14);box-shadow:0 0 10px rgba(255,77,106,0.28)}
    /* BADGE */
    .badge{font-family:var(--font-hud);font-size:0.46rem;font-weight:700;padding:2px 8px;border:1px solid;clip-path:polygon(3px 0%,100% 0%,calc(100% - 3px) 100%,0% 100%);letter-spacing:0.08em;text-transform:uppercase;white-space:nowrap}
    .badge-rv{color:#8B7EFF;border-color:rgba(139,126,255,0.40);background:rgba(139,126,255,0.08)}
    .badge-mx{color:#44D9FF;border-color:rgba(68,217,255,0.40);background:rgba(68,217,255,0.08)}
    .badge-drone{color:#FFA030;border-color:rgba(255,160,48,0.40);background:rgba(255,160,48,0.08)}
    .badge-award{color:#FFD700;border-color:rgba(255,215,0,0.40);background:rgba(255,215,0,0.08)}
    .badge-rank{color:var(--creo-volt);border-color:rgba(255,233,48,0.35);background:rgba(255,233,48,0.06)}
    /* RANK INDICATOR */
    .rank-ind{font-family:var(--font-hud);font-size:0.72rem;font-weight:900}
    .rank-ind.r1{color:var(--creo-volt);text-shadow:0 0 8px rgba(255,233,48,.70)}
    .rank-ind.r2{color:#C4EEFF;text-shadow:0 0 8px rgba(196,238,255,.70)}
    .rank-ind.r3{color:var(--creo-amber);text-shadow:0 0 8px rgba(255,160,48,.70)}
    .rank-ind.rn{color:var(--text-dim)}
    /* THUMB */
    .thumb{width:42px;height:42px;object-fit:cover;border:1px solid rgba(139,126,255,0.25)}
    /* MODALS */
    .modal-overlay{display:none;position:fixed;inset:0;z-index:9000;background:rgba(3,2,13,0.88);backdrop-filter:blur(10px);align-items:center;justify-content:center;padding:20px;overflow-y:auto}
    .modal-overlay.open{display:flex}
    .modal-box{background:#0A0918;border:1px solid var(--border-neon);max-width:580px;width:100%;position:relative;max-height:90vh;overflow-y:auto;margin:auto}
    .modal-box.wide{max-width:760px}
    .modal-box::before{content:'';position:absolute;top:0;left:0;right:0;height:1px;background:linear-gradient(90deg,transparent,var(--prc-violet),transparent)}
    .modal-hdr{padding:16px 20px 13px;border-bottom:1px solid rgba(139,126,255,0.14);background:rgba(139,126,255,0.06);display:flex;align-items:center;justify-content:space-between;gap:12px}
    .modal-hdr h3{font-family:var(--font-hud);font-size:0.73rem;font-weight:700;letter-spacing:0.06em;color:var(--prc-violet)}
    .modal-close{width:28px;height:28px;background:rgba(139,126,255,0.06);border:1px solid rgba(139,126,255,0.20)!important;color:var(--text-soft);display:flex;align-items:center;justify-content:center;cursor:pointer!important;font-size:0.72rem;transition:all .2s}
    .modal-close:hover{background:rgba(139,126,255,0.14);color:var(--prc-violet)}
    .modal-body{padding:20px}
    .modal-footer{padding:13px 20px;border-top:1px solid rgba(139,126,255,0.12);display:flex;gap:10px;justify-content:flex-end}
    /* CONFIRM */
    .confirm-icon{font-size:2rem;color:var(--admin-red);display:block;margin-bottom:12px}
    .confirm-title{font-family:var(--font-hud);font-size:0.92rem;font-weight:800;color:#fff;margin-bottom:8px}
    .confirm-text{font-size:0.88rem;color:var(--text-mid);line-height:1.70}
    /* EMPTY */
    .empty-row td{text-align:center;padding:32px;font-family:var(--font-hud);font-size:0.58rem;color:var(--text-dim);letter-spacing:0.10em}
    /* SCROLL */
    ::-webkit-scrollbar{width:4px}::-webkit-scrollbar-track{background:var(--bg-void)}::-webkit-scrollbar-thumb{background:var(--prc-violet);border-radius:2px}
    /* RESPONSIVE */
    @media(max-width:900px){.admin-shell{grid-template-columns:0 1fr}.admin-sidebar-slot{display:none}.admin-main{padding:18px 16px 48px}body{cursor:auto}button{cursor:pointer}.cursor-dot,.cursor-ring{display:none}}
    @media(max-width:768px){.two-col,.three-col{grid-template-columns:1fr}.field-row{flex-direction:column}}
  </style>
</head>
<body>
<div class="cursor-dot" id="cursorDot"></div>
<div class="cursor-ring" id="cursorRing"></div>

<div class="admin-shell" id="adminShell">
  <div class="admin-sidebar-slot" id="sidebarSlot"></div>

  <!-- TOP BAR -->
  <header class="admin-topbar">
    <div class="topbar-breadcrumb">PRC Admin <i class="fi fi-rr-angle-right" style="font-size:.55rem"></i> <span>Rankings</span></div>
    <div class="topbar-right">
      <button class="topbar-icon-btn" onclick="location.reload()" title="Refresh"><i class="fi fi-rr-refresh"></i></button>
      <a href="rankings.php" target="_blank" class="topbar-public-btn"><i class="fi fi-rr-eye"></i> Public View</a>
      <div class="topbar-date"><span id="topbar-date-display"></span></div>
    </div>
  </header>

  <!-- MAIN -->
  <main class="admin-main">
    <div class="page-header">
      <div>
        <div class="page-eyebrow"><span class="dot-live"></span> Content Management // Rankings</div>
        <h1 class="page-title"><span class="accent">Rankings</span> Manager</h1>
      </div>
      <div class="page-stats">
        <div class="stat-chip"><span class="stat-chip-num"><?= count($years) ?></span><span class="stat-chip-lbl">Years</span></div>
        <div class="stat-chip"><span class="stat-chip-num"><?= count($cats) ?></span><span class="stat-chip-lbl">Categories</span></div>
        <div class="stat-chip"><span class="stat-chip-num"><?= count($all_subs) ?></span><span class="stat-chip-lbl">Subcats</span></div>
        <div class="stat-chip"><span class="stat-chip-num"><?= $total_entries ?></span><span class="stat-chip-lbl">Entries</span></div>
      </div>
    </div>

    <?php if($flash): ?>
    <div class="flash-wrap">
      <div class="flash-inner <?= $flash['type'] ?>">
        <i class="fi fi-<?= $flash['type']==='success'?'rr-check':'rr-cross' ?>"></i>
        <?= htmlspecialchars($flash['msg']) ?>
      </div>
    </div>
    <?php endif; ?>

    <!-- TABS -->
    <div class="admin-tabs">
      <button class="admin-tab active" onclick="switchTab('meta',this)">Page Settings</button>
      <button class="admin-tab" onclick="switchTab('years',this)">Years</button>
      <button class="admin-tab" onclick="switchTab('cats',this)">Categories</button>
      <button class="admin-tab" onclick="switchTab('entries',this)">Entries</button>
    </div>

    <!-- ══════════════════════════════════════════
         TAB 1: PAGE META
    ══════════════════════════════════════════ -->
    <div class="tab-pane active" id="tab-meta">
      <div class="panel-card">
        <div class="panel-hdr"><i class="fi fi-rr-settings"></i><div class="panel-hdr-text"><h3>Page Settings</h3><p>Hero description shown on the public rankings page</p></div></div>
        <div class="panel-body">
          <form method="POST" action="admin-rankings.php">
            <input type="hidden" name="action" value="save_meta"/>
            <div class="field">
              <label class="field-label">Hero Description <span class="req">*</span></label>
              <textarea class="field-textarea" name="hero_desc" rows="4" required><?= htmlspecialchars($hero_desc) ?></textarea>
              <div class="field-hint">This text appears below the "Rankings &amp; Results" heading on the public page.</div>
            </div>
            <button type="submit" class="btn-primary"><i class="fi fi-rr-check"></i> Save Description</button>
          </form>
        </div>
      </div>
    </div>

    <!-- ══════════════════════════════════════════
         TAB 2: YEARS
    ══════════════════════════════════════════ -->
    <div class="tab-pane" id="tab-years">
      <div class="two-col">
        <!-- Add Year -->
        <div class="panel-card">
          <div class="panel-hdr"><i class="fi fi-rr-calendar-plus"></i><div class="panel-hdr-text"><h3>Add Year</h3><p>Add a new competition year</p></div></div>
          <div class="panel-body">
            <form method="POST" action="admin-rankings.php">
              <input type="hidden" name="action" value="add_year"/>
              <div class="field"><label class="field-label">Year Label <span class="req">*</span></label><input class="field-input" type="text" name="year_label" placeholder="e.g. 2026" maxlength="10" required/></div>
              <div class="field"><label class="field-label">Edition</label><input class="field-input" type="text" name="edition" placeholder="National Finals" maxlength="100"/></div>
              <div class="field"><label class="field-label">Event Date</label><input class="field-input" type="text" name="event_date" placeholder="October 18–19, 2026" maxlength="100"/></div>
              <div class="field"><label class="field-label">Venue</label><input class="field-input" type="text" name="venue" placeholder="Vista Mall Las Piñas" maxlength="200"/></div>
              <div class="field"><label class="field-label">Sort Order</label><input class="field-input" type="number" name="year_sort" value="0" min="0"/><div class="field-hint">Lower = leftmost button on public page.</div></div>
              <button type="submit" class="btn-primary btn-full"><i class="fi fi-rr-plus"></i> Add Year</button>
            </form>
          </div>
        </div>
        <!-- Year List -->
        <div>
          <div class="section-label">All Competition Years</div>
          <div class="data-table-wrap">
            <table class="data-table">
              <thead><tr><th>Year</th><th>Edition</th><th>Date / Venue</th><th>Actions</th></tr></thead>
              <tbody>
                <?php if(empty($years)): ?>
                <tr class="empty-row"><td colspan="4">No years yet.</td></tr>
                <?php else: foreach($years as $y): ?>
                <tr>
                  <td><strong style="font-family:var(--font-hud);font-size:1rem;color:var(--prc-violet)"><?= htmlspecialchars($y['year_label']) ?></strong><?php if(!$y['is_active']): ?><br><span style="font-family:var(--font-hud);font-size:0.44rem;color:var(--text-dim)">HIDDEN</span><?php endif; ?></td>
                  <td><?= htmlspecialchars($y['edition']) ?></td>
                  <td style="font-size:.80rem;color:var(--text-soft)"><?= htmlspecialchars($y['event_date']) ?><br><?= htmlspecialchars($y['venue']) ?></td>
                  <td style="white-space:nowrap">
                    <button class="icon-btn edit" title="Edit" onclick="openEditYear(<?= $y['year_id'] ?>,'<?= addslashes($y['year_label']) ?>','<?= addslashes($y['edition']) ?>','<?= addslashes($y['event_date']) ?>','<?= addslashes($y['venue']) ?>',<?= $y['year_sort'] ?>,<?= $y['is_active'] ?>)"><i class="fi fi-rr-edit"></i></button>
                    <button class="icon-btn del" title="Delete" onclick="openConfirm('year',<?= $y['year_id'] ?>,'<?= addslashes($y['year_label']) ?>')"><i class="fi fi-rr-trash"></i></button>
                  </td>
                </tr>
                <?php endforeach; endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

    <!-- ══════════════════════════════════════════
         TAB 3: CATEGORIES & SUBCATEGORIES
    ══════════════════════════════════════════ -->
    <div class="tab-pane" id="tab-cats">

      <!-- Add Category -->
      <div class="panel-card" style="margin-bottom:28px">
        <div class="panel-hdr"><i class="fi fi-rr-folder-add"></i><div class="panel-hdr-text"><h3>Add Category</h3><p>Top-level sidebar section (e.g. RoboVenture, MakeX)</p></div></div>
        <div class="panel-body">
          <form method="POST" action="admin-rankings.php" enctype="multipart/form-data">
            <input type="hidden" name="action" value="add_cat"/>
            <div class="three-col">
              <div class="field"><label class="field-label">Slug <span class="req">*</span></label><input class="field-input" type="text" name="cat_slug" placeholder="rv" maxlength="50" required/><div class="field-hint">Short unique ID: rv, mx, drone, award, etc.</div></div>
              <div class="field"><label class="field-label">Display Name <span class="req">*</span></label><input class="field-input" type="text" name="cat_name" placeholder="RoboVenture" maxlength="100" required/></div>
              <div class="field"><label class="field-label">Color Theme</label>
                <select class="field-select" name="cat_color">
                  <option value="rv">RoboVenture (violet)</option>
                  <option value="mx">MakeX (sky)</option>
                  <option value="drone">Drone Soccer (amber)</option>
                  <option value="award">Special Awards (gold)</option>
                </select>
              </div>
            </div>
            <div class="field-row">
              <div class="field"><label class="field-label">Logo File</label><div class="upload-zone"><input type="file" name="cat_logo" accept="image/*"/><span class="upload-zone-label">Upload logo image</span><span class="upload-zone-sub">PNG/JPG recommended</span></div></div>
              <div class="field"><label class="field-label">— or Logo URL</label><input class="field-input" type="text" name="cat_logo_url" placeholder="assets/Roboventure Logo.png"/></div>
              <div class="field"><label class="field-label">Sort Order</label><input class="field-input" type="number" name="cat_sort" value="0" min="0"/></div>
            </div>
            <button type="submit" class="btn-primary"><i class="fi fi-rr-plus"></i> Add Category</button>
          </form>
        </div>
      </div>

      <!-- Categories list + Sub management -->
      <?php foreach($cats as $cat):
        $csubs=$subs_by_cat[$cat['cat_id']]??[];
        $colorMap=['rv'=>'badge-rv','mx'=>'badge-mx','drone'=>'badge-drone','award'=>'badge-award'];
        $badgeCls=$colorMap[$cat['cat_color']]??'badge-rv';
      ?>
      <div class="panel-card" style="margin-bottom:20px">
        <div class="panel-hdr" style="justify-content:space-between">
          <div style="display:flex;align-items:center;gap:12px">
            <?php if($cat['cat_logo']): ?><img src="<?= htmlspecialchars($cat['cat_logo']) ?>" style="height:28px;width:auto;opacity:.8;" alt=""/><?php endif; ?>
            <div><strong style="font-family:var(--font-hud);font-size:0.72rem;color:var(--text-high)"><?= htmlspecialchars($cat['cat_name']) ?></strong> <span class="badge <?= $badgeCls ?>"><?= htmlspecialchars($cat['cat_color']) ?></span></div>
          </div>
          <div style="display:flex;gap:6px">
            <button class="icon-btn edit" title="Edit category" onclick="openEditCat(<?= $cat['cat_id'] ?>,'<?= addslashes($cat['cat_slug']) ?>','<?= addslashes($cat['cat_name']) ?>','<?= addslashes($cat['cat_logo']??'') ?>','<?= $cat['cat_color'] ?>',<?= $cat['cat_sort'] ?>,<?= $cat['is_active'] ?>)"><i class="fi fi-rr-edit"></i></button>
            <button class="icon-btn del" title="Delete category" onclick="openConfirm('cat',<?= $cat['cat_id'] ?>,'<?= addslashes($cat['cat_name']) ?>')"><i class="fi fi-rr-trash"></i></button>
          </div>
        </div>
        <div class="panel-body">
          <!-- Subcategory table -->
          <div class="section-label">Subcategories</div>
          <div class="data-table-wrap" style="margin-bottom:16px">
            <table class="data-table">
              <thead><tr><th>#</th><th>Slug</th><th>Name</th><th>Sort</th><th>Actions</th></tr></thead>
              <tbody>
                <?php if(empty($csubs)): ?>
                <tr class="empty-row"><td colspan="5">No subcategories yet.</td></tr>
                <?php else: foreach($csubs as $si=>$sub): ?>
                <tr>
                  <td style="font-family:var(--font-hud);font-size:0.60rem;color:var(--text-dim)"><?= str_pad($si+1,2,'0',STR_PAD_LEFT) ?></td>
                  <td><code style="font-family:var(--font-hud);font-size:0.70rem;color:var(--prc-violet);background:rgba(139,126,255,0.08);padding:2px 7px"><?= htmlspecialchars($sub['sub_slug']) ?></code></td>
                  <td><?= htmlspecialchars($sub['sub_name']) ?></td>
                  <td style="font-size:.80rem;color:var(--text-dim)"><?= $sub['sub_sort'] ?></td>
                  <td style="white-space:nowrap">
                    <button class="icon-btn edit" title="Edit" onclick="openEditSub(<?= $sub['sub_id'] ?>,'<?= addslashes($sub['sub_slug']) ?>','<?= addslashes($sub['sub_name']) ?>',<?= $sub['sub_sort'] ?>,<?= $sub['is_active'] ?>)"><i class="fi fi-rr-edit"></i></button>
                    <button class="icon-btn del" title="Delete" onclick="openConfirm('sub',<?= $sub['sub_id'] ?>,'<?= addslashes($sub['sub_name']) ?>')"><i class="fi fi-rr-trash"></i></button>
                  </td>
                </tr>
                <?php endforeach; endif; ?>
              </tbody>
            </table>
          </div>
          <!-- Add Subcategory inline -->
          <details>
            <summary style="font-family:var(--font-hud);font-size:0.58rem;font-weight:700;letter-spacing:0.10em;text-transform:uppercase;color:var(--prc-violet);cursor:pointer;margin-bottom:12px;list-style:none;display:flex;align-items:center;gap:8px"><i class="fi fi-rr-plus"></i> Add Subcategory to <?= htmlspecialchars($cat['cat_name']) ?></summary>
            <form method="POST" action="admin-rankings.php" style="margin-top:12px">
              <input type="hidden" name="action" value="add_sub"/>
              <input type="hidden" name="cat_id" value="<?= $cat['cat_id'] ?>"/>
              <div class="field-row">
                <div class="field"><label class="field-label">Slug <span class="req">*</span></label><input class="field-input" type="text" name="sub_slug" placeholder="ei" maxlength="50" required/></div>
                <div class="field"><label class="field-label">Name <span class="req">*</span></label><input class="field-input" type="text" name="sub_name" placeholder="Emerging Innovators" maxlength="150" required/></div>
                <div class="field"><label class="field-label">Sort</label><input class="field-input" type="number" name="sub_sort" value="<?= count($csubs) ?>" min="0"/></div>
              </div>
              <button type="submit" class="btn-primary btn-sm"><i class="fi fi-rr-plus"></i> Add Sub</button>
            </form>
          </details>
        </div>
      </div>
      <?php endforeach; ?>
    </div>

    <!-- ══════════════════════════════════════════
         TAB 4: RANKING ENTRIES
    ══════════════════════════════════════════ -->
    <div class="tab-pane" id="tab-entries">

      <!-- Add Entry -->
      <div class="panel-card red-card" style="margin-bottom:28px">
        <div class="panel-hdr red"><i class="fi fi-rr-trophy"></i><div class="panel-hdr-text"><h3>Add Ranking Entry</h3><p>Champion, runners-up, finalists, or special award recipients</p></div></div>
        <div class="panel-body">
          <form method="POST" action="admin-rankings.php" enctype="multipart/form-data">
            <input type="hidden" name="action" value="add_entry"/>
            <div class="three-col">
              <div class="field"><label class="field-label">Competition Year <span class="req">*</span></label>
                <select class="field-select" name="year_id" required>
                  <option value="" disabled selected>— Select year —</option>
                  <?php foreach($years as $y): ?><option value="<?= $y['year_id'] ?>"><?= htmlspecialchars($y['year_label']) ?></option><?php endforeach; ?>
                </select>
              </div>
              <div class="field"><label class="field-label">Subcategory <span class="req">*</span></label>
                <select class="field-select" name="sub_id" required>
                  <option value="" disabled selected>— Select subcategory —</option>
                  <?php foreach($cats as $cat): $csubs=$subs_by_cat[$cat['cat_id']]??[]; ?>
                  <optgroup label="<?= htmlspecialchars($cat['cat_name']) ?>">
                    <?php foreach($csubs as $sub): ?>
                    <option value="<?= $sub['sub_id'] ?>"><?= htmlspecialchars($sub['sub_name']) ?></option>
                    <?php endforeach; ?>
                  </optgroup>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="field"><label class="field-label">Rank Position <span class="req">*</span></label>
                <select class="field-select" name="rank_pos" required>
                  <option value="1">1 — Champion</option>
                  <option value="2">2 — 1st Runner-up</option>
                  <option value="3">3 — 2nd Runner-up</option>
                  <option value="4" selected>4+ — Finalist / Participant</option>
                  <option value="5">5</option><option value="6">6</option><option value="7">7</option>
                  <option value="8">8</option><option value="9">9</option><option value="10">10</option>
                  <option value="11">11</option><option value="12">12</option><option value="13">13</option>
                  <option value="14">14</option><option value="15">15</option><option value="16">16</option>
                </select>
              </div>
            </div>
            <div class="field-row">
              <div class="field"><label class="field-label">School Name <span class="req">*</span></label><input class="field-input" type="text" name="school_name" placeholder="School / Organization Name" maxlength="300" required/></div>
              <div class="field"><label class="field-label">Team Name</label><input class="field-input" type="text" name="team_name" placeholder="Team Codename" maxlength="200"/></div>
            </div>
            <div class="field"><label class="field-label">Members / Coach</label><input class="field-input" type="text" name="members" placeholder="John Doe, Jane Doe (comma-separated)" maxlength="500"/></div>
            <div class="field"><label class="field-label">Status Label Override</label><input class="field-input" type="text" name="status_label" placeholder="Leave blank to auto-generate from rank"/></div>

            <div class="section-label" style="margin-top:16px">Images</div>
            <div class="three-col">
              <div class="field">
                <label class="field-label">School Logo</label>
                <div class="upload-zone"><input type="file" name="school_logo" accept="image/*"/><span class="upload-zone-label">Upload logo</span><span class="upload-zone-sub">PNG preferred</span></div>
                <input class="field-input" type="text" name="school_logo_url" placeholder="or paste URL/path" style="margin-top:8px"/>
              </div>
              <div class="field">
                <label class="field-label">Podium Photo</label>
                <div class="upload-zone"><input type="file" name="podium_photo" accept="image/*"/><span class="upload-zone-label">Upload podium photo</span><span class="upload-zone-sub">JPG/PNG</span></div>
                <input class="field-input" type="text" name="podium_photo_url" placeholder="or paste URL/path" style="margin-top:8px"/>
              </div>
              <div class="field">
                <label class="field-label">Award Photo <span style="color:var(--text-dim);font-size:.90em">(Special Awards only)</span></label>
                <div class="upload-zone"><input type="file" name="award_photo" accept="image/*"/><span class="upload-zone-label">Upload award photo</span><span class="upload-zone-sub">JPG/PNG</span></div>
                <input class="field-input" type="text" name="award_photo_url" placeholder="or paste URL/path" style="margin-top:8px"/>
              </div>
            </div>
            <div class="field-row">
              <div class="field"><label class="field-label">Award Detail <span style="color:var(--text-dim);font-size:.90em">(Special Awards only)</span></label><input class="field-input" type="text" name="award_detail" placeholder="e.g. RoboBusters 2 — Coach: Roscel Marc A. Lugo" maxlength="500"/></div>
              <div class="field"><label class="field-label">Sort Order</label><input class="field-input" type="number" name="entry_sort" value="0" min="0"/><div class="field-hint">Controls row order within same rank tier.</div></div>
            </div>
            <button type="submit" class="btn-primary btn-red"><i class="fi fi-rr-plus"></i> Add Entry</button>
          </form>
        </div>
      </div>

      <!-- Entries Table -->
      <div class="section-label">All Entries (<?= $total_entries ?>)</div>

      <!-- Filter Bar -->
      <div style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:14px;align-items:center">
        <select class="field-select" id="filterYear" onchange="filterEntries()" style="width:auto;padding:8px 12px;font-size:.78rem">
          <option value="">All Years</option>
          <?php foreach($years as $y): ?><option value="<?= $y['year_id'] ?>"><?= htmlspecialchars($y['year_label']) ?></option><?php endforeach; ?>
        </select>
        <select class="field-select" id="filterCat" onchange="filterEntries()" style="width:auto;padding:8px 12px;font-size:.78rem">
          <option value="">All Categories</option>
          <?php foreach($cats as $cat): ?><option value="<?= $cat['cat_id'] ?>"><?= htmlspecialchars($cat['cat_name']) ?></option><?php endforeach; ?>
        </select>
        <input class="field-input" type="text" id="filterSearch" oninput="filterEntries()" placeholder="Search school / team…" style="width:240px;padding:8px 12px;font-size:.78rem"/>
        <button class="btn-primary btn-sm" onclick="clearFilters()"><i class="fi fi-rr-cross-small"></i> Clear</button>
      </div>

      <div class="data-table-wrap">
        <table class="data-table" id="entriesTable">
          <thead><tr><th>Year</th><th>Category / Sub</th><th>Rank</th><th>School</th><th>Team</th><th>Photos</th><th>Status</th><th>Actions</th></tr></thead>
          <tbody>
            <?php if(empty($all_entries)): ?>
            <tr class="empty-row"><td colspan="8">No entries yet. Use the form above to add some.</td></tr>
            <?php else: foreach($all_entries as $e):
              $rpos=(int)$e['rank_pos'];
              $rankCls=$rpos===1?'r1':($rpos===2?'r2':($rpos===3?'r3':'rn'));
              $bCls=['rv'=>'badge-rv','mx'=>'badge-mx','drone'=>'badge-drone','award'=>'badge-award'][$e['cat_color']]??'badge-rv';
              $statusLbl=$e['status_label']??status_default($rpos);
            ?>
            <tr data-year="<?= $e['year_id'] ?>" data-cat="<?= $e['cat_id']??'' ?>" data-search="<?= strtolower(htmlspecialchars($e['school_name'].' '.$e['team_name'])) ?>">
              <td><span style="font-family:var(--font-hud);font-size:0.72rem;color:var(--prc-violet)"><?= htmlspecialchars($e['year_label']) ?></span></td>
              <td>
                <span class="badge <?= $bCls ?>" style="display:inline-block;margin-bottom:4px"><?= htmlspecialchars($e['cat_name']) ?></span><br>
                <span style="font-size:.78rem;color:var(--text-soft)"><?= htmlspecialchars($e['sub_name']) ?></span>
              </td>
              <td><span class="rank-ind <?= $rankCls ?>"><?= $rpos ?></span></td>
              <td>
                <div style="font-family:var(--font-hud);font-size:.66rem;font-weight:700;color:var(--text-high);line-height:1.3"><?= htmlspecialchars($e['school_name']) ?></div>
                <?php if($e['members']): ?><div style="font-size:.74rem;color:var(--text-dim);margin-top:2px"><?= htmlspecialchars(mb_strimwidth($e['members'],0,50,'…')) ?></div><?php endif; ?>
              </td>
              <td style="font-size:.80rem;color:var(--text-soft)"><?= htmlspecialchars($e['team_name']??'—') ?></td>
              <td>
                <?php if($e['school_logo']): ?><img src="<?= htmlspecialchars($e['school_logo']) ?>" class="thumb" style="width:30px;height:30px;border-radius:50%;margin-bottom:3px" alt=""/><?php endif; ?>
                <?php if($e['podium_photo']||$e['award_photo']): ?><img src="<?= htmlspecialchars($e['podium_photo']??$e['award_photo']) ?>" class="thumb" alt=""/><?php else: ?><span style="font-family:var(--font-hud);font-size:.44rem;color:var(--text-dim)">No photo</span><?php endif; ?>
              </td>
              <td><span style="font-family:var(--font-hud);font-size:.56rem;color:var(--text-soft)"><?= htmlspecialchars($statusLbl) ?></span></td>
              <td style="white-space:nowrap">
                <button class="icon-btn edit" title="Edit" onclick="openEditEntry(<?= htmlspecialchars(json_encode($e)) ?>)"><i class="fi fi-rr-edit"></i></button>
                <button class="icon-btn del" title="Delete" onclick="openConfirm('entry',<?= $e['entry_id'] ?>,'<?= addslashes($e['school_name']) ?>')"><i class="fi fi-rr-trash"></i></button>
              </td>
            </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div><!-- /tab-entries -->

  </main><!-- /admin-main -->
</div><!-- /admin-shell -->

<!-- ══ EDIT YEAR MODAL ══ -->
<div class="modal-overlay" id="modal-edit-year">
  <div class="modal-box">
    <div class="modal-hdr"><h3>Edit Year</h3><button class="modal-close" onclick="closeModal('modal-edit-year')"><i class="fi fi-rr-cross"></i></button></div>
    <form method="POST" action="admin-rankings.php">
      <input type="hidden" name="action" value="edit_year"/>
      <input type="hidden" name="year_id" id="ey-id"/>
      <div class="modal-body">
        <div class="field"><label class="field-label">Year Label <span class="req">*</span></label><input class="field-input" type="text" name="year_label" id="ey-label" maxlength="10" required/></div>
        <div class="field"><label class="field-label">Edition</label><input class="field-input" type="text" name="edition" id="ey-edition" maxlength="100"/></div>
        <div class="field"><label class="field-label">Event Date</label><input class="field-input" type="text" name="event_date" id="ey-date" maxlength="100"/></div>
        <div class="field"><label class="field-label">Venue</label><input class="field-input" type="text" name="venue" id="ey-venue" maxlength="200"/></div>
        <div class="field-row">
          <div class="field"><label class="field-label">Sort Order</label><input class="field-input" type="number" name="year_sort" id="ey-sort" min="0"/></div>
          <div class="field"><label class="field-label">Visible</label><select class="field-select" name="is_active" id="ey-active"><option value="1">Yes</option><option value="0">Hidden</option></select></div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn-primary btn-sm" onclick="closeModal('modal-edit-year')">Cancel</button>
        <button type="submit" class="btn-primary btn-sm"><i class="fi fi-rr-check"></i> Save</button>
      </div>
    </form>
  </div>
</div>

<!-- ══ EDIT CATEGORY MODAL ══ -->
<div class="modal-overlay" id="modal-edit-cat">
  <div class="modal-box">
    <div class="modal-hdr"><h3>Edit Category</h3><button class="modal-close" onclick="closeModal('modal-edit-cat')"><i class="fi fi-rr-cross"></i></button></div>
    <form method="POST" action="admin-rankings.php" enctype="multipart/form-data">
      <input type="hidden" name="action" value="edit_cat"/>
      <input type="hidden" name="cat_id" id="ec-id"/>
      <div class="modal-body">
        <div class="field-row">
          <div class="field"><label class="field-label">Slug <span class="req">*</span></label><input class="field-input" type="text" name="cat_slug" id="ec-slug" maxlength="50" required/></div>
          <div class="field"><label class="field-label">Name <span class="req">*</span></label><input class="field-input" type="text" name="cat_name" id="ec-name" maxlength="100" required/></div>
        </div>
        <div class="field-row">
          <div class="field"><label class="field-label">Color Theme</label>
            <select class="field-select" name="cat_color" id="ec-color">
              <option value="rv">RoboVenture (violet)</option><option value="mx">MakeX (sky)</option>
              <option value="drone">Drone Soccer (amber)</option><option value="award">Special Awards (gold)</option>
            </select>
          </div>
          <div class="field"><label class="field-label">Sort</label><input class="field-input" type="number" name="cat_sort" id="ec-sort" min="0"/></div>
          <div class="field"><label class="field-label">Visible</label><select class="field-select" name="is_active" id="ec-active"><option value="1">Yes</option><option value="0">Hidden</option></select></div>
        </div>
        <div class="field"><label class="field-label">New Logo File</label><div class="upload-zone"><input type="file" name="cat_logo" accept="image/*"/><span class="upload-zone-label">Replace logo image</span><span class="upload-zone-sub">Leave empty to keep current</span></div></div>
        <div class="field"><label class="field-label">Logo URL / Path</label><input class="field-input" type="text" name="cat_logo_url" id="ec-logo" placeholder="current path"/></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn-primary btn-sm" onclick="closeModal('modal-edit-cat')">Cancel</button>
        <button type="submit" class="btn-primary btn-sm"><i class="fi fi-rr-check"></i> Save</button>
      </div>
    </form>
  </div>
</div>

<!-- ══ EDIT SUBCATEGORY MODAL ══ -->
<div class="modal-overlay" id="modal-edit-sub">
  <div class="modal-box">
    <div class="modal-hdr"><h3>Edit Subcategory</h3><button class="modal-close" onclick="closeModal('modal-edit-sub')"><i class="fi fi-rr-cross"></i></button></div>
    <form method="POST" action="admin-rankings.php">
      <input type="hidden" name="action" value="edit_sub"/>
      <input type="hidden" name="sub_id" id="es-id"/>
      <div class="modal-body">
        <div class="field-row">
          <div class="field"><label class="field-label">Slug <span class="req">*</span></label><input class="field-input" type="text" name="sub_slug" id="es-slug" maxlength="50" required/></div>
          <div class="field"><label class="field-label">Name <span class="req">*</span></label><input class="field-input" type="text" name="sub_name" id="es-name" maxlength="150" required/></div>
        </div>
        <div class="field-row">
          <div class="field"><label class="field-label">Sort</label><input class="field-input" type="number" name="sub_sort" id="es-sort" min="0"/></div>
          <div class="field"><label class="field-label">Visible</label><select class="field-select" name="is_active" id="es-active"><option value="1">Yes</option><option value="0">Hidden</option></select></div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn-primary btn-sm" onclick="closeModal('modal-edit-sub')">Cancel</button>
        <button type="submit" class="btn-primary btn-sm"><i class="fi fi-rr-check"></i> Save</button>
      </div>
    </form>
  </div>
</div>

<!-- ══ EDIT ENTRY MODAL ══ -->
<div class="modal-overlay" id="modal-edit-entry">
  <div class="modal-box wide">
    <div class="modal-hdr"><h3>Edit Entry</h3><button class="modal-close" onclick="closeModal('modal-edit-entry')"><i class="fi fi-rr-cross"></i></button></div>
    <form method="POST" action="admin-rankings.php" enctype="multipart/form-data">
      <input type="hidden" name="action" value="edit_entry"/>
      <input type="hidden" name="entry_id" id="ee-id"/>
      <div class="modal-body">
        <div class="three-col">
          <div class="field"><label class="field-label">Year <span class="req">*</span></label>
            <select class="field-select" name="year_id" id="ee-year">
              <?php foreach($years as $y): ?><option value="<?= $y['year_id'] ?>"><?= htmlspecialchars($y['year_label']) ?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="field"><label class="field-label">Subcategory <span class="req">*</span></label>
            <select class="field-select" name="sub_id" id="ee-sub">
              <?php foreach($cats as $cat): $csubs=$subs_by_cat[$cat['cat_id']]??[]; ?>
              <optgroup label="<?= htmlspecialchars($cat['cat_name']) ?>">
                <?php foreach($csubs as $sub): ?><option value="<?= $sub['sub_id'] ?>"><?= htmlspecialchars($sub['sub_name']) ?></option><?php endforeach; ?>
              </optgroup>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="field"><label class="field-label">Rank</label>
            <select class="field-select" name="rank_pos" id="ee-rank">
              <?php for($r=1;$r<=16;$r++): ?><option value="<?= $r ?>"><?= $r ?><?= $r===1?' — Champion':($r===2?' — 1st Runner-up':($r===3?' — 2nd Runner-up':'')) ?></option><?php endfor; ?>
            </select>
          </div>
        </div>
        <div class="field-row">
          <div class="field"><label class="field-label">School Name <span class="req">*</span></label><input class="field-input" type="text" name="school_name" id="ee-school" maxlength="300" required/></div>
          <div class="field"><label class="field-label">Team Name</label><input class="field-input" type="text" name="team_name" id="ee-team" maxlength="200"/></div>
        </div>
        <div class="field"><label class="field-label">Members / Coach</label><input class="field-input" type="text" name="members" id="ee-members" maxlength="500"/></div>
        <div class="field"><label class="field-label">Status Label</label><input class="field-input" type="text" name="status_label" id="ee-status" maxlength="100"/></div>
        <div class="field-row">
          <div class="field"><label class="field-label">School Logo URL</label><input class="field-input" type="text" name="school_logo_url" id="ee-logo" placeholder="leave blank to keep current"/></div>
          <div class="field"><label class="field-label">Upload New Logo</label><div class="upload-zone"><input type="file" name="school_logo" accept="image/*"/><span class="upload-zone-label">Upload</span><span class="upload-zone-sub">Overrides URL above</span></div></div>
        </div>
        <div class="field-row">
          <div class="field"><label class="field-label">Podium Photo URL</label><input class="field-input" type="text" name="podium_photo_url" id="ee-photo" placeholder="leave blank to keep current"/></div>
          <div class="field"><label class="field-label">Upload New Podium Photo</label><div class="upload-zone"><input type="file" name="podium_photo" accept="image/*"/><span class="upload-zone-label">Upload</span><span class="upload-zone-sub">Overrides URL above</span></div></div>
        </div>
        <div class="field-row">
          <div class="field"><label class="field-label">Award Photo URL</label><input class="field-input" type="text" name="award_photo_url" id="ee-aphoto" placeholder="leave blank to keep current"/></div>
          <div class="field"><label class="field-label">Upload New Award Photo</label><div class="upload-zone"><input type="file" name="award_photo" accept="image/*"/><span class="upload-zone-label">Upload</span><span class="upload-zone-sub">Overrides URL above</span></div></div>
        </div>
        <div class="field-row">
          <div class="field"><label class="field-label">Award Detail</label><input class="field-input" type="text" name="award_detail" id="ee-adetail" maxlength="500"/></div>
          <div class="field"><label class="field-label">Sort Order</label><input class="field-input" type="number" name="entry_sort" id="ee-sort" min="0"/></div>
          <div class="field"><label class="field-label">Visible</label><select class="field-select" name="is_active" id="ee-active"><option value="1">Yes</option><option value="0">Hidden</option></select></div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn-primary btn-sm" onclick="closeModal('modal-edit-entry')">Cancel</button>
        <button type="submit" class="btn-primary btn-sm btn-red"><i class="fi fi-rr-check"></i> Save Changes</button>
      </div>
    </form>
  </div>
</div>

<!-- ══ CONFIRM DELETE MODAL ══ -->
<div class="modal-overlay" id="modal-confirm">
  <div class="modal-box" style="max-width:400px;text-align:center">
    <div class="modal-hdr" style="justify-content:center;border-color:rgba(255,77,106,0.25);background:rgba(255,77,106,0.05)">
      <h3 style="color:var(--admin-red)">Confirm Delete</h3>
    </div>
    <div class="modal-body">
      <i class="fi fi-rr-trash confirm-icon"></i>
      <div class="confirm-title" id="confirm-title">Delete?</div>
      <div class="confirm-text" id="confirm-text">This cannot be undone.</div>
    </div>
    <div class="modal-footer" style="justify-content:center">
      <button type="button" class="btn-primary btn-sm" onclick="closeModal('modal-confirm')">Cancel</button>
      <form method="POST" action="admin-rankings.php" style="display:inline">
        <input type="hidden" name="action" id="confirm-action"/>
        <input type="hidden" name="year_id" id="confirm-year-id"/>
        <input type="hidden" name="cat_id" id="confirm-cat-id"/>
        <input type="hidden" name="sub_id" id="confirm-sub-id"/>
        <input type="hidden" name="entry_id" id="confirm-entry-id"/>
        <button type="submit" class="btn-primary btn-sm btn-red"><i class="fi fi-rr-trash"></i> Yes, Delete</button>
      </form>
    </div>
  </div>
</div>

<!-- ══ SIDEBAR ══ -->
<script>
(function(){
  var slot=document.getElementById('sidebarSlot');
  if(!slot)return;
  fetch('admin-sidebar.html').then(function(r){return r.text();}).then(function(html){
    slot.innerHTML=html;
    slot.querySelectorAll('script').forEach(function(old){var s=document.createElement('script');s.textContent=old.textContent;document.body.appendChild(s);});
  }).catch(function(){
    slot.innerHTML='<aside style="position:fixed;top:0;left:0;height:100vh;width:248px;background:#04031A;border-right:1px solid rgba(139,126,255,0.18);display:flex;align-items:center;justify-content:center;font-family:Orbitron,monospace;font-size:0.55rem;color:rgba(139,126,255,0.40);letter-spacing:0.12em;text-align:center;padding:20px;">SIDEBAR<br/>COMPONENT<br/><span style="margin-top:8px;display:block;font-size:0.44rem">admin-sidebar.html</span></aside>';
  });
})();
document.addEventListener('prc-sidebar-toggle',function(e){document.getElementById('adminShell').classList.toggle('sb-collapsed',e.detail.collapsed);});
(function(){if(localStorage.getItem('prc_sidebar_collapsed')==='1')document.getElementById('adminShell').classList.add('sb-collapsed');})();
</script>

<script>
// ── TOPBAR DATE ──
(function(){
  var el=document.getElementById('topbar-date-display');
  function upd(){var d=new Date(),M=['JAN','FEB','MAR','APR','MAY','JUN','JUL','AUG','SEP','OCT','NOV','DEC'],h=d.getHours(),m=d.getMinutes(),ap=h>=12?'PM':'AM';h=h%12||12;el.innerHTML=M[d.getMonth()]+' '+String(d.getDate()).padStart(2,'0')+', '+d.getFullYear()+'&nbsp;<span>'+String(h).padStart(2,'0')+':'+String(m).padStart(2,'0')+' '+ap+'</span>';}
  upd();setInterval(upd,30000);
})();

// ── CURSOR ──
(function(){
  var dot=document.getElementById('cursorDot'),ring=document.getElementById('cursorRing');
  if(!dot||!ring)return;
  var mx=0,my=0,rx=0,ry=0;
  document.addEventListener('mousemove',function(e){mx=e.clientX;my=e.clientY;dot.style.left=mx+'px';dot.style.top=my+'px';});
  (function l(){rx+=(mx-rx)*.12;ry+=(my-ry)*.12;ring.style.left=rx+'px';ring.style.top=ry+'px';requestAnimationFrame(l);})();
  document.querySelectorAll('a,button').forEach(function(el){el.addEventListener('mouseenter',function(){ring.classList.add('hovered');});el.addEventListener('mouseleave',function(){ring.classList.remove('hovered');});});
})();

// ── TABS ──
function switchTab(id, btn) {
  document.querySelectorAll('.tab-pane').forEach(function(p){p.classList.remove('active');});
  document.querySelectorAll('.admin-tab').forEach(function(b){b.classList.remove('active');});
  document.getElementById('tab-'+id).classList.add('active');
  btn.classList.add('active');
}

// ── MODALS ──
function openModal(id){document.getElementById(id).classList.add('open');document.body.style.overflow='hidden';}
function closeModal(id){document.getElementById(id).classList.remove('open');document.body.style.overflow='';}
document.querySelectorAll('.modal-overlay').forEach(function(el){el.addEventListener('click',function(e){if(e.target===el)closeModal(el.id);});});
document.addEventListener('keydown',function(e){if(e.key==='Escape')document.querySelectorAll('.modal-overlay.open').forEach(function(m){closeModal(m.id);});});

// ── OPEN EDIT MODALS ──
function openEditYear(id,label,edition,date,venue,sort,active){
  document.getElementById('ey-id').value=id;
  document.getElementById('ey-label').value=label;
  document.getElementById('ey-edition').value=edition;
  document.getElementById('ey-date').value=date;
  document.getElementById('ey-venue').value=venue;
  document.getElementById('ey-sort').value=sort;
  document.getElementById('ey-active').value=active;
  openModal('modal-edit-year');
}
function openEditCat(id,slug,name,logo,color,sort,active){
  document.getElementById('ec-id').value=id;
  document.getElementById('ec-slug').value=slug;
  document.getElementById('ec-name').value=name;
  document.getElementById('ec-logo').value=logo;
  document.getElementById('ec-color').value=color;
  document.getElementById('ec-sort').value=sort;
  document.getElementById('ec-active').value=active;
  openModal('modal-edit-cat');
}
function openEditSub(id,slug,name,sort,active){
  document.getElementById('es-id').value=id;
  document.getElementById('es-slug').value=slug;
  document.getElementById('es-name').value=name;
  document.getElementById('es-sort').value=sort;
  document.getElementById('es-active').value=active;
  openModal('modal-edit-sub');
}
function openEditEntry(e){
  document.getElementById('ee-id').value=e.entry_id;
  document.getElementById('ee-year').value=e.year_id;
  document.getElementById('ee-sub').value=e.sub_id;
  document.getElementById('ee-rank').value=e.rank_pos;
  document.getElementById('ee-school').value=e.school_name;
  document.getElementById('ee-team').value=e.team_name||'';
  document.getElementById('ee-members').value=e.members||'';
  document.getElementById('ee-status').value=e.status_label||'';
  document.getElementById('ee-logo').value=e.school_logo||'';
  document.getElementById('ee-photo').value=e.podium_photo||'';
  document.getElementById('ee-aphoto').value=e.award_photo||'';
  document.getElementById('ee-adetail').value=e.award_detail||'';
  document.getElementById('ee-sort').value=e.entry_sort;
  document.getElementById('ee-active').value=e.is_active;
  openModal('modal-edit-entry');
}

// ── CONFIRM DELETE ──
function openConfirm(type,id,name){
  var actions={year:'delete_year',cat:'delete_cat',sub:'delete_sub',entry:'delete_entry'};
  var labels={year:'year_id',cat:'cat_id',sub:'sub_id',entry:'entry_id'};
  document.getElementById('confirm-title').textContent='Delete "'+name+'"?';
  document.getElementById('confirm-text').textContent='This will permanently delete the item'+(type==='cat'?' and all its subcategories and entries':type==='year'?' and all ranking entries for that year':'')+'. This cannot be undone.';
  document.getElementById('confirm-action').value=actions[type];
  ['year_id','cat_id','sub_id','entry_id'].forEach(function(f){document.getElementById('confirm-'+f.replace('_','-')).value='';});
  document.getElementById('confirm-'+labels[type].replace('_','-')).value=id;
  openModal('modal-confirm');
}

// ── ENTRY FILTER ──
function filterEntries(){
  var yf=document.getElementById('filterYear').value;
  var cf=document.getElementById('filterCat').value;
  var sf=document.getElementById('filterSearch').value.toLowerCase();
  document.querySelectorAll('#entriesTable tbody tr:not(.empty-row)').forEach(function(tr){
    var show=true;
    if(yf&&tr.dataset.year!==yf) show=false;
    if(cf&&tr.dataset.cat!==cf) show=false;
    if(sf&&!tr.dataset.search.includes(sf)) show=false;
    tr.style.display=show?'':'none';
  });
}
function clearFilters(){
  document.getElementById('filterYear').value='';
  document.getElementById('filterCat').value='';
  document.getElementById('filterSearch').value='';
  filterEntries();
}
</script>
</body>
</html>