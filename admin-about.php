<?php
// PRC-WebApp/admin-about.php
// ── DB ─────────────────────────────────────────────────────────
$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'prc_db';

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($conn->connect_error) {
    die('<p style="color:#ff6b6b;padding:40px;font-family:monospace;">DB error: ' . htmlspecialchars($conn->connect_error) . '</p>');
}

// ── AUTO-INSTALL TABLES ────────────────────────────────────────
$conn->multi_query("
CREATE TABLE IF NOT EXISTS `prc_about_meta` (
  `meta_id`    int(11) NOT NULL AUTO_INCREMENT,
  `meta_key`   varchar(120) NOT NULL,
  `meta_value` text DEFAULT NULL,
  PRIMARY KEY (`meta_id`),
  UNIQUE KEY `meta_key` (`meta_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `prc_about_stats` (
  `stat_id`    int(11) NOT NULL AUTO_INCREMENT,
  `stat_icon`  varchar(100) NOT NULL DEFAULT 'fi-rr-info',
  `stat_num`   varchar(30)  NOT NULL,
  `stat_label` varchar(120) NOT NULL,
  `stat_sort`  int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`stat_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `prc_about_values` (
  `val_id`    int(11) NOT NULL AUTO_INCREMENT,
  `val_num`   varchar(5)   NOT NULL,
  `val_icon`  varchar(100) NOT NULL DEFAULT 'fi-rr-star',
  `val_title` varchar(150) NOT NULL,
  `val_desc`  text         NOT NULL,
  `val_sort`  int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`val_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `prc_about_highlights` (
  `hl_id`    int(11) NOT NULL AUTO_INCREMENT,
  `hl_icon`  varchar(100) NOT NULL DEFAULT 'fi-rr-info',
  `hl_title` varchar(150) NOT NULL,
  `hl_desc`  varchar(300) NOT NULL,
  `hl_sort`  int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`hl_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `prc_about_programs` (
  `prog_id`    int(11) NOT NULL AUTO_INCREMENT,
  `prog_num`   varchar(20)  NOT NULL,
  `prog_title` varchar(200) NOT NULL,
  `prog_desc`  text         NOT NULL,
  `prog_img`   varchar(500) NOT NULL DEFAULT '',
  `prog_type`  varchar(30)  NOT NULL DEFAULT 'default',
  `prog_tags`  varchar(500) NOT NULL DEFAULT '',
  `prog_sort`  int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`prog_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `prc_about_partners_gov` (
  `pg_id`    int(11) NOT NULL AUTO_INCREMENT,
  `pg_name`  varchar(150) NOT NULL,
  `pg_short` varchar(30)  NOT NULL,
  `pg_logo`  varchar(500) NOT NULL DEFAULT '',
  `pg_sort`  int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`pg_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `prc_about_partners_acad` (
  `pa_id`   int(11) NOT NULL AUTO_INCREMENT,
  `pa_name` varchar(200) NOT NULL,
  `pa_sort` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`pa_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `prc_about_refs` (
  `ref_id`       int(11) NOT NULL AUTO_INCREMENT,
  `ref_type`     varchar(30)  NOT NULL DEFAULT 'fb',
  `ref_source`   varchar(100) NOT NULL DEFAULT 'Facebook Video',
  `ref_platform` varchar(100) NOT NULL DEFAULT 'Creotec Philippines Inc.',
  `ref_title`    varchar(300) NOT NULL,
  `ref_desc`     text         NOT NULL,
  `ref_tag`      varchar(100) NOT NULL DEFAULT '',
  `ref_url`      varchar(1000) NOT NULL DEFAULT '#',
  `ref_sort`     int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`ref_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");
// Flush multi_query results
while ($conn->more_results()) { $conn->next_result(); }

// ── SEED DEFAULT DATA (only if tables are empty) ───────────────
function seed_if_empty($conn, $table, $check_sql, $insert_sql) {
    $r = $conn->query($check_sql);
    if ($r && $r->num_rows === 0) {
        $conn->multi_query($insert_sql);
        while ($conn->more_results()) $conn->next_result();
    }
}

seed_if_empty($conn, 'prc_about_meta',
    "SELECT meta_id FROM prc_about_meta LIMIT 1",
    "INSERT INTO prc_about_meta (meta_key, meta_value) VALUES
     ('hero_eyebrow','About the Organizer'),
     ('hero_title','About <span class=\"accent\">Creotec</span>'),
     ('hero_desc','The company behind the Philippine Robotics Cup — an industry-based training and learning company providing innovative technology-based solutions to the academe, professionals, and individuals.'),
     ('who_p1','Creotec Philippines Inc. is an industry-based training and learning company providing innovative technology-based curriculum and solutions to the academe, professionals, and individuals across the Philippines.'),
     ('who_p2','Established on October 20, 2015 (SEC # CS201521140) and situated at 117 Technology Ave., SEPZ, LTI, Biñan, Laguna — Creotec operates as a locator in the processing zone for the provision of learning, immersion, OJT services, and technology learning equipment for the industry and the academe.'),
     ('who_p3','As a proud member of the <strong style=\"color:var(--prc-ice)\">EMS Group of Companies (EMSG)</strong> — known for its contribution to the electronics manufacturing services industry with six facilities within Biñan Technopark — Creotec brings deep industry expertise directly into the classroom.'),
     ('who_badge_year','2015'),
     ('who_badge_sub','Est. Biñan, Laguna'),
     ('who_img','assets/about-creotec.png'),
     ('vision_text','To provide competitive sustainable solutions to our partners and equitable value to our stakeholders — building a future where industry and education work seamlessly together, and every Filipino learner has access to relevant, world-class skills training.'),
     ('mission_text','We passionately and persistently create shared value through accessible, industry-relevant, and distinctive learning innovations — bridging the gap between academic theory and real-world industry demands to prepare graduates for the workforce of today and tomorrow.'),
     ('bg_p1','Creotec draws its strength from the EMS Group of Companies — a powerhouse in Philippine electronics manufacturing services with six facilities inside Biñan Technopark. This industrial foundation means our curricula aren\\'t theoretical: they reflect actual production floors, live equipment, and real industry standards.'),
     ('bg_p2','EMSG is committed to supporting the United Nations\\' Sustainable Development Goal on Quality Education (SDG #4). Through Creotec, EMSG reaches schools and universities nationwide — providing relevant learning services that reduce the skills mismatch challenge facing the country.'),
     ('bg_img_main','assets/about-history.jpg'),
     ('bg_img_secondary','assets/about-history2.jpg'),
     ('contact_phone1','+63 968 305 6459'),
     ('contact_phone2','+63 917 828 2736'),
     ('contact_email','info@creotec.com.ph'),
     ('contact_website','ems.com.ph'),
     ('contact_fb_label','Creotec Philippines Inc.'),
     ('contact_fb_url','https://facebook.com/CreotecPhilippinesInc'),
     ('contact_address','117 Technology Ave., SEPZ, LTI, Biñan, Laguna'),
     ('partners_heading','// Key Partnerships'),
     ('partners_sub','Creotec is proud to partner with leading government agencies and academic institutions across the Philippines.')"
);

seed_if_empty($conn, 'prc_about_stats',
    "SELECT stat_id FROM prc_about_stats LIMIT 1",
    "INSERT INTO prc_about_stats (stat_icon, stat_num, stat_label, stat_sort) VALUES
     ('fi-rr-calendar','2015','Year Established',0),
     ('fi-rr-graduation-cap','6+','Training Programs',1),
     ('fi-rr-handshake','20+','Academic Partners',2),
     ('fi-rr-trophy','1st','Philippine Robotics Cup',3)"
);

seed_if_empty($conn, 'prc_about_values',
    "SELECT val_id FROM prc_about_values LIMIT 1",
    "INSERT INTO prc_about_values (val_num, val_icon, val_title, val_desc, val_sort) VALUES
     ('01','fi-rr-smile','Customer Satisfaction','We put our partners and learners first — delivering quality that exceeds expectations at every touchpoint.',0),
     ('02','fi-rr-target','Accountability & Ownership','We take full responsibility for our commitments, results, and the impact of our work on the communities we serve.',1),
     ('03','fi-rr-star','Excellence & Innovation','We continuously push boundaries in curriculum design, technology integration, and learning experiences.',2),
     ('04','fi-rr-leaf','Sustainability','We build lasting value aligned with the UN\\'s SDG #4 on Quality Education — for learners, partners, and the nation.',3)"
);

seed_if_empty($conn, 'prc_about_highlights',
    "SELECT hl_id FROM prc_about_highlights LIMIT 1",
    "INSERT INTO prc_about_highlights (hl_icon, hl_title, hl_desc, hl_sort) VALUES
     ('fi-rr-building','EMSG Member Company','Six facilities in Biñan Technopark, Laguna',0),
     ('fi-rr-globe','UN SDG #4 Aligned','Quality Education for all Filipinos',1),
     ('fi-rr-microchip','Industry-Driven Curriculum','Semiconductor & electronics manufacturing expertise',2)"
);

seed_if_empty($conn, 'prc_about_programs',
    "SELECT prog_id FROM prc_about_programs LIMIT 1",
    "INSERT INTO prc_about_programs (prog_num, prog_title, prog_desc, prog_img, prog_type, prog_tags, prog_sort) VALUES
     ('Program 01','Strengthening Job-Readiness & College-Preparedness','A ladderized approach moving from manufacturing process familiarization to applied operations — covering Simulating Manufacturing Processes, Support Services, Digital Solutions Capability, and Advancing Industry Readiness. Ranges from 80 to 640 hours per module.','https://images.unsplash.com/photo-1581092918056-0c4c3acd3789?w=700&auto=format&fit=crop&q=80','default','80–640 hrs,Manufacturing,Digital Skills,OJT',0),
     ('Program 02','Setting the Foundations for Digital Transformation','Short-form professional courses covering digital transformation fundamentals (16 hrs), workplace productivity tools (24 hrs), data literacy & digital mindset (24 hrs), cybersecurity practices (8 hrs), and Industry 4.0 mechatronics (200 hrs).','https://images.unsplash.com/photo-1518770660439-4636190af475?w=700&auto=format&fit=crop&q=80','default','8–200 hrs,Industry 4.0,Cybersecurity,Data Literacy',1),
     ('Program 03','Enhancing Classroom Instruction through Robotics','A comprehensive program integrating robotics and AI into Science, Mathematics, and ICT instruction for K–12 and tertiary levels. Includes curriculum-aligned guides, robotics & AI kits, gamified mobile modules, and teacher capacity-building workshops.','https://images.unsplash.com/photo-1535378917042-10a22c95931a?w=700&auto=format&fit=crop&q=80','default','K–12 & Tertiary,Robotics Kits,AI Integration,Teacher Training',2),
     ('Program 04','Empowering Basic Education through CreoApps','An innovative suite of mobile gamified learning applications that makes education more interactive and accessible. CreoApps integrates game-based elements, interactive lessons, and formative assessment tools to strengthen learner understanding in key subject areas.','https://images.unsplash.com/photo-1498050108023-c5249f4df085?w=700&auto=format&fit=crop&q=80','default','Mobile Learning,Gamified,Formative Assessment,Remediation',3),
     ('Program 05','Igniting Innovation through Robotics, AI & Drone Challenges','The Philippine Robotics Cup — an annual national competition featuring mission-based robotics contests, AI problem-solving scenarios, and drone soccer matches. Students showcase skills in robotics assembly, coding, and drone technology on a national stage.','https://images.unsplash.com/photo-1485827404703-89b55fcc595e?w=700&auto=format&fit=crop&q=80','competition','Annual National,Robotics Cup,Drone Soccer,MakeX Qualifier',4),
     ('Program 06','Equipping Teachers for Technology-Integrated Instruction','A professional development initiative strengthening educators\\' capability to integrate technology into teaching, learning, and research. Teachers gain hands-on experience in instructional design, student research development, and prototype creation.','https://images.unsplash.com/photo-1524178232363-1fb2b075b655?w=700&auto=format&fit=crop&q=80','default','Professional Dev,EdTech,Research,Innovation',5),
     ('TESDA 07.1','Mechatronics Servicing NC II','A 158-hour TESDA-accredited program equipping learners with competencies to install, configure, maintain, and troubleshoot mechatronic devices. Covers PLCs, sensors, actuators, pneumatics, and industrial networking for Industry 4.0. Available in Biñan, Laguna and Marikina City.','https://images.unsplash.com/photo-1581092580497-e0d23cbdf1dc?w=700&auto=format&fit=crop&q=80','tesda','158 hrs,TESDA NC II,PLC Programming,Biñan & Marikina',6),
     ('TESDA 07.2','Electronics Back-End Operation NC II','An 80-hour TESDA course training individuals in electronics manufacturing back-end operations — covering production workspace preparation, process monitoring, quality compliance, and troubleshooting. Prepares learners for roles in electronics and semiconductor companies. Available in Biñan, Laguna.','https://images.unsplash.com/photo-1518770660439-4636190af475?w=700&auto=format&fit=crop&q=80','tesda','80 hrs,TESDA NC II,Electronics Mfg.,Biñan, Laguna',7)"
);

seed_if_empty($conn, 'prc_about_partners_gov',
    "SELECT pg_id FROM prc_about_partners_gov LIMIT 1",
    "INSERT INTO prc_about_partners_gov (pg_name, pg_short, pg_logo, pg_sort) VALUES
     ('Dept. of Education','DepEd','assets/DepED-Logo.png',0),
     ('Dept. of Science & Tech.','DOST','assets/DOST-Logo.png',1),
     ('Dept. of ICT','DICT','assets/DICT-Logo.png',2),
     ('Tech. Education & Skills Dev. Authority','TESDA','',3),
     ('Rizal Memorial State University','RMTU','',4),
     ('Batangas State University','BSU','',5)"
);

seed_if_empty($conn, 'prc_about_partners_acad',
    "SELECT pa_id FROM prc_about_partners_acad LIMIT 1",
    "INSERT INTO prc_about_partners_acad (pa_name, pa_sort) VALUES
     ('Bethel Academy',0),('Adventist University of the Philippines',1),
     ('Statefields School',2),('Woodridge College',3),
     ('Divine Light Academy',4),('La Salle Green Hills',5),
     ('St. Anne College Lucena',6),('University of Batangas',7),
     ('San Beda College Alabang',8),('Miriam College',9),
     ('St. Dominic College of Asia',10),('St. Scholastica\\'s College',11),
     ('Westmead International School',12),('Canossa Academy Lipa',13),
     ('St. Therese School of Southville',14),('University of Baguio',15),
     ('Saint Louis University',16)"
);

seed_if_empty($conn, 'prc_about_refs',
    "SELECT ref_id FROM prc_about_refs LIMIT 1",
    "INSERT INTO prc_about_refs (ref_type, ref_source, ref_platform, ref_title, ref_desc, ref_tag, ref_url, ref_sort) VALUES
     ('fb','Facebook Video','Creotec Philippines Inc.','Level Up Your School Events with Robotics & AI','See how Roboventure transforms Science Fairs, STEM Days, and District Meets into future-ready competitions — with SDG-themed missions, automated scoring, and affordable packages starting at ₱5,000.','Roboventure','https://www.facebook.com/share/v/18aZaiPMnc/',0),
     ('fb','Facebook Video','Creotec Philippines Inc.','Bring Robotics & AI Competition to Your Campus','Host your own school robotics competition with the Roboventure Package — robot kits, exclusive scoring software, competition guides, and technical support from Creotec. Winners advance to the Philippine Robotics Cup.','Roboventure','https://www.facebook.com/share/v/1Cs8s59ay1/',1),
     ('fb','Facebook Video','Creotec Philippines Inc.','Is Your School Ready to Launch Its Own Robotics Competition?','The Roboventure Competition Package gives schools ready-to-run modules that build coding, problem-solving, and teamwork skills — positioning your school as a leader in 21st-century STEM education.','School Competition','https://www.facebook.com/share/v/1Ben5X8BwD/',2),
     ('award','Global Achievement','MakeX 2025 — Wuxi, China','🇵🇭 Philippines on the Global Robotics Stage — 3rd Runner Up, MakeX 2025','From 22 countries, Team Philippines — Luis Palad Integrated HS — earned 3rd Runner Up and a Finalist spot at the MakeX Robotics Competition 2025 Global Competition in Wuxi, China.','🏆 Global Finalist','https://www.facebook.com/share/v/1FKy5hHaH2/',3)"
);

// ── HANDLE AJAX / POST SAVES ───────────────────────────────────
header('Content-Type: text/html; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    // Helper: save a meta key
    function save_meta($conn, $key, $value) {
        $stmt = $conn->prepare("INSERT INTO prc_about_meta (meta_key, meta_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE meta_value = VALUES(meta_value)");
        $stmt->bind_param('ss', $key, $value);
        $stmt->execute();
        $stmt->close();
    }

    // Save GENERAL / HERO / WHO / MISSION / BACKGROUND / CONTACT
    if ($action === 'save_meta') {
        $allowed = ['hero_eyebrow','hero_title','hero_desc',
                    'who_p1','who_p2','who_p3','who_badge_year','who_badge_sub','who_img',
                    'vision_text','mission_text',
                    'bg_p1','bg_p2','bg_img_main','bg_img_secondary',
                    'contact_phone1','contact_phone2','contact_email',
                    'contact_website','contact_fb_label','contact_fb_url','contact_address',
                    'partners_heading','partners_sub'];
        foreach ($allowed as $k) {
            if (isset($_POST[$k])) save_meta($conn, $k, $_POST[$k]);
        }
        echo json_encode(['ok'=>true]);
        exit;
    }

    // ── STATS ──
    if ($action === 'save_stat') {
        $id = (int)($_POST['stat_id'] ?? 0);
        $icon = $conn->real_escape_string($_POST['stat_icon'] ?? '');
        $num  = $conn->real_escape_string($_POST['stat_num']  ?? '');
        $lbl  = $conn->real_escape_string($_POST['stat_label']?? '');
        $sort = (int)($_POST['stat_sort'] ?? 0);
        if ($id) {
            $conn->query("UPDATE prc_about_stats SET stat_icon='$icon',stat_num='$num',stat_label='$lbl',stat_sort=$sort WHERE stat_id=$id");
        } else {
            $conn->query("INSERT INTO prc_about_stats (stat_icon,stat_num,stat_label,stat_sort) VALUES('$icon','$num','$lbl',$sort)");
            $id = $conn->insert_id;
        }
        echo json_encode(['ok'=>true,'id'=>$id]); exit;
    }
    if ($action === 'delete_stat') { $id=(int)$_POST['stat_id']; $conn->query("DELETE FROM prc_about_stats WHERE stat_id=$id"); echo json_encode(['ok'=>true]); exit; }

    // ── VALUES ──
    if ($action === 'save_value') {
        $id   = (int)($_POST['val_id'] ?? 0);
        $num  = $conn->real_escape_string($_POST['val_num']  ?? '');
        $icon = $conn->real_escape_string($_POST['val_icon'] ?? '');
        $tit  = $conn->real_escape_string($_POST['val_title']?? '');
        $desc = $conn->real_escape_string($_POST['val_desc'] ?? '');
        $sort = (int)($_POST['val_sort'] ?? 0);
        if ($id) {
            $conn->query("UPDATE prc_about_values SET val_num='$num',val_icon='$icon',val_title='$tit',val_desc='$desc',val_sort=$sort WHERE val_id=$id");
        } else {
            $conn->query("INSERT INTO prc_about_values (val_num,val_icon,val_title,val_desc,val_sort) VALUES('$num','$icon','$tit','$desc',$sort)");
            $id = $conn->insert_id;
        }
        echo json_encode(['ok'=>true,'id'=>$id]); exit;
    }
    if ($action === 'delete_value') { $id=(int)$_POST['val_id']; $conn->query("DELETE FROM prc_about_values WHERE val_id=$id"); echo json_encode(['ok'=>true]); exit; }

    // ── HIGHLIGHTS ──
    if ($action === 'save_highlight') {
        $id   = (int)($_POST['hl_id'] ?? 0);
        $icon = $conn->real_escape_string($_POST['hl_icon'] ?? '');
        $tit  = $conn->real_escape_string($_POST['hl_title']?? '');
        $desc = $conn->real_escape_string($_POST['hl_desc'] ?? '');
        $sort = (int)($_POST['hl_sort'] ?? 0);
        if ($id) {
            $conn->query("UPDATE prc_about_highlights SET hl_icon='$icon',hl_title='$tit',hl_desc='$desc',hl_sort=$sort WHERE hl_id=$id");
        } else {
            $conn->query("INSERT INTO prc_about_highlights (hl_icon,hl_title,hl_desc,hl_sort) VALUES('$icon','$tit','$desc',$sort)");
            $id = $conn->insert_id;
        }
        echo json_encode(['ok'=>true,'id'=>$id]); exit;
    }
    if ($action === 'delete_highlight') { $id=(int)$_POST['hl_id']; $conn->query("DELETE FROM prc_about_highlights WHERE hl_id=$id"); echo json_encode(['ok'=>true]); exit; }

    // ── PROGRAMS ──
    if ($action === 'save_program') {
        $id   = (int)($_POST['prog_id'] ?? 0);
        $num  = $conn->real_escape_string($_POST['prog_num']  ?? '');
        $tit  = $conn->real_escape_string($_POST['prog_title']?? '');
        $desc = $conn->real_escape_string($_POST['prog_desc'] ?? '');
        $img  = $conn->real_escape_string($_POST['prog_img']  ?? '');
        $type = $conn->real_escape_string($_POST['prog_type'] ?? 'default');
        $tags = $conn->real_escape_string($_POST['prog_tags'] ?? '');
        $sort = (int)($_POST['prog_sort'] ?? 0);
        if ($id) {
            $conn->query("UPDATE prc_about_programs SET prog_num='$num',prog_title='$tit',prog_desc='$desc',prog_img='$img',prog_type='$type',prog_tags='$tags',prog_sort=$sort WHERE prog_id=$id");
        } else {
            $conn->query("INSERT INTO prc_about_programs (prog_num,prog_title,prog_desc,prog_img,prog_type,prog_tags,prog_sort) VALUES('$num','$tit','$desc','$img','$type','$tags',$sort)");
            $id = $conn->insert_id;
        }
        echo json_encode(['ok'=>true,'id'=>$id]); exit;
    }
    if ($action === 'delete_program') { $id=(int)$_POST['prog_id']; $conn->query("DELETE FROM prc_about_programs WHERE prog_id=$id"); echo json_encode(['ok'=>true]); exit; }

    // ── GOV PARTNERS ──
    if ($action === 'save_partner_gov') {
        $id   = (int)($_POST['pg_id'] ?? 0);
        $name = $conn->real_escape_string($_POST['pg_name']  ?? '');
        $sh   = $conn->real_escape_string($_POST['pg_short'] ?? '');
        $logo = $conn->real_escape_string($_POST['pg_logo']  ?? '');
        $sort = (int)($_POST['pg_sort'] ?? 0);
        if ($id) {
            $conn->query("UPDATE prc_about_partners_gov SET pg_name='$name',pg_short='$sh',pg_logo='$logo',pg_sort=$sort WHERE pg_id=$id");
        } else {
            $conn->query("INSERT INTO prc_about_partners_gov (pg_name,pg_short,pg_logo,pg_sort) VALUES('$name','$sh','$logo',$sort)");
            $id = $conn->insert_id;
        }
        echo json_encode(['ok'=>true,'id'=>$id]); exit;
    }
    if ($action === 'delete_partner_gov') { $id=(int)$_POST['pg_id']; $conn->query("DELETE FROM prc_about_partners_gov WHERE pg_id=$id"); echo json_encode(['ok'=>true]); exit; }

    // ── ACAD PARTNERS ──
    if ($action === 'save_partner_acad') {
        $id   = (int)($_POST['pa_id'] ?? 0);
        $name = $conn->real_escape_string($_POST['pa_name'] ?? '');
        $sort = (int)($_POST['pa_sort'] ?? 0);
        if ($id) {
            $conn->query("UPDATE prc_about_partners_acad SET pa_name='$name',pa_sort=$sort WHERE pa_id=$id");
        } else {
            $conn->query("INSERT INTO prc_about_partners_acad (pa_name,pa_sort) VALUES('$name',$sort)");
            $id = $conn->insert_id;
        }
        echo json_encode(['ok'=>true,'id'=>$id]); exit;
    }
    if ($action === 'delete_partner_acad') { $id=(int)$_POST['pa_id']; $conn->query("DELETE FROM prc_about_partners_acad WHERE pa_id=$id"); echo json_encode(['ok'=>true]); exit; }

    // ── REFERENCES ──
    if ($action === 'save_ref') {
        $id   = (int)($_POST['ref_id'] ?? 0);
        $type = $conn->real_escape_string($_POST['ref_type']    ?? 'fb');
        $src  = $conn->real_escape_string($_POST['ref_source']  ?? '');
        $plat = $conn->real_escape_string($_POST['ref_platform']?? '');
        $tit  = $conn->real_escape_string($_POST['ref_title']   ?? '');
        $desc = $conn->real_escape_string($_POST['ref_desc']    ?? '');
        $tag  = $conn->real_escape_string($_POST['ref_tag']     ?? '');
        $url  = $conn->real_escape_string($_POST['ref_url']     ?? '#');
        $sort = (int)($_POST['ref_sort'] ?? 0);
        if ($id) {
            $conn->query("UPDATE prc_about_refs SET ref_type='$type',ref_source='$src',ref_platform='$plat',ref_title='$tit',ref_desc='$desc',ref_tag='$tag',ref_url='$url',ref_sort=$sort WHERE ref_id=$id");
        } else {
            $conn->query("INSERT INTO prc_about_refs (ref_type,ref_source,ref_platform,ref_title,ref_desc,ref_tag,ref_url,ref_sort) VALUES('$type','$src','$plat','$tit','$desc','$tag','$url',$sort)");
            $id = $conn->insert_id;
        }
        echo json_encode(['ok'=>true,'id'=>$id]); exit;
    }
    if ($action === 'delete_ref') { $id=(int)$_POST['ref_id']; $conn->query("DELETE FROM prc_about_refs WHERE ref_id=$id"); echo json_encode(['ok'=>true]); exit; }
}

// ── FETCH ALL DATA ─────────────────────────────────────────────
function get_meta($conn) {
    $out = [];
    $r = $conn->query("SELECT meta_key, meta_value FROM prc_about_meta");
    if ($r) while ($row = $r->fetch_assoc()) $out[$row['meta_key']] = $row['meta_value'];
    return $out;
}
function fetch_all($conn, $sql) {
    $out = []; $r = $conn->query($sql);
    if ($r) while ($row = $r->fetch_assoc()) $out[] = $row;
    return $out;
}

$meta       = get_meta($conn);
$stats      = fetch_all($conn, "SELECT * FROM prc_about_stats      ORDER BY stat_sort ASC");
$values     = fetch_all($conn, "SELECT * FROM prc_about_values     ORDER BY val_sort ASC");
$highlights = fetch_all($conn, "SELECT * FROM prc_about_highlights ORDER BY hl_sort ASC");
$programs   = fetch_all($conn, "SELECT * FROM prc_about_programs   ORDER BY prog_sort ASC");
$partners_g = fetch_all($conn, "SELECT * FROM prc_about_partners_gov ORDER BY pg_sort ASC");
$partners_a = fetch_all($conn, "SELECT * FROM prc_about_partners_acad ORDER BY pa_sort ASC");
$refs       = fetch_all($conn, "SELECT * FROM prc_about_refs       ORDER BY ref_sort ASC");

$conn->close();

function m($meta, $key, $default = '') { return htmlspecialchars($meta[$key] ?? $default); }
function mv($meta, $key, $default = '') { return $meta[$key] ?? $default; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>About Page Manager — PRC Admin</title>
  <link rel="icon" type="image/png" href="assets/favicon.png"/>
  <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;600;700;800;900&family=Exo+2:wght@300;400;500;600;700&display=swap" rel="stylesheet"/>
  <link rel='stylesheet' href='https://cdn-uicons.flaticon.com/uicons-regular-rounded/css/uicons-regular-rounded.css'>
  <link rel='stylesheet' href='https://cdn-uicons.flaticon.com/uicons-solid-rounded/css/uicons-solid-rounded.css'>

  <style>
    :root {
      --prc-violet:  #8B7EFF;
      --prc-ice:     #C4EEFF;
      --creo-amber:  #FFA030;
      --creo-volt:   #FFE930;
      --bg-void:     #03020D;
      --bg-deep:     #06051A;
      --bg-card:     rgba(139,126,255,0.04);
      --border-neon: rgba(139,126,255,0.20);
      --text-high:   #F2EEFF;
      --text-mid:    #C8C0F0;
      --text-soft:   #9A90CC;
      --text-dim:    #7068A8;
      --sb-width:    248px;
      --font-hud:    'Orbitron', monospace;
      --font-body:   'Exo 2', sans-serif;
      --danger:      #FF5050;
      --success:     #44FF88;
    }
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    html { scroll-behavior: smooth; }
    body { font-family: var(--font-body); background: var(--bg-void); color: var(--text-high); overflow-x: hidden; min-height: 100vh; }
    a { text-decoration: none; color: inherit; }
    button { font-family: inherit; cursor: pointer; border: none; background: none; }
    textarea, input, select { font-family: var(--font-body); }

    /* ── LAYOUT ── */
    .admin-layout { display: flex; min-height: 100vh; }
    .admin-content {
      margin-left: var(--sb-width);
      flex: 1; padding: 36px 40px 80px;
      transition: margin-left 0.30s;
      max-width: calc(100vw - var(--sb-width));
    }

    /* ── TOP BAR ── */
    .admin-topbar {
      display: flex; align-items: center; justify-content: space-between;
      margin-bottom: 32px; flex-wrap: wrap; gap: 16px;
    }
    .admin-page-title { font-family: var(--font-hud); font-size: 1.1rem; font-weight: 800; color: #fff; letter-spacing: 0.04em; }
    .admin-page-title span { color: var(--prc-violet); text-shadow: 0 0 12px rgba(139,126,255,0.55); }
    .admin-breadcrumb { font-family: var(--font-hud); font-size: 0.52rem; color: var(--text-dim); letter-spacing: 0.12em; margin-top: 4px; }
    .topbar-actions { display: flex; gap: 10px; }
    .btn { display: inline-flex; align-items: center; gap: 8px; padding: 10px 22px; font-family: var(--font-hud); font-size: 0.62rem; font-weight: 700; letter-spacing: 0.10em; text-transform: uppercase; border: 1px solid; cursor: pointer; transition: all 0.22s; }
    .btn-primary { color: var(--prc-violet); border-color: rgba(139,126,255,0.50); background: rgba(139,126,255,0.08); }
    .btn-primary:hover { background: rgba(139,126,255,0.18); box-shadow: 0 0 18px rgba(139,126,255,0.30); color: #fff; }
    .btn-amber { color: var(--creo-amber); border-color: rgba(255,160,48,0.45); background: rgba(255,160,48,0.07); }
    .btn-amber:hover { background: rgba(255,160,48,0.16); box-shadow: 0 0 18px rgba(255,160,48,0.28); color: #fff; }
    .btn-danger { color: var(--danger); border-color: rgba(255,80,80,0.35); background: rgba(255,80,80,0.06); padding: 7px 14px; font-size: 0.58rem; }
    .btn-danger:hover { background: rgba(255,80,80,0.16); border-color: rgba(255,80,80,0.60); }
    .btn-sm { padding: 7px 14px; font-size: 0.56rem; }
    .btn-success { color: var(--success); border-color: rgba(68,255,136,0.35); background: rgba(68,255,136,0.06); }
    .btn-success:hover { background: rgba(68,255,136,0.14); }

    /* ── TABS ── */
    .tabs-bar { display: flex; gap: 0; border-bottom: 1px solid var(--border-neon); margin-bottom: 36px; overflow-x: auto; flex-wrap: nowrap; }
    .tab-btn { font-family: var(--font-hud); font-size: 0.60rem; font-weight: 700; letter-spacing: 0.10em; text-transform: uppercase; color: var(--text-dim); padding: 12px 22px; border: none; background: none; cursor: pointer; border-bottom: 2px solid transparent; transition: all 0.2s; white-space: nowrap; }
    .tab-btn:hover { color: var(--text-soft); }
    .tab-btn.active { color: var(--prc-violet); border-bottom-color: var(--prc-violet); text-shadow: 0 0 10px rgba(139,126,255,0.50); }
    .tab-panel { display: none; }
    .tab-panel.active { display: block; }

    /* ── CARDS / SECTIONS ── */
    .admin-card {
      background: var(--bg-card); border: 1px solid var(--border-neon);
      padding: 28px 32px; margin-bottom: 24px; position: relative;
    }
    .admin-card::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 2px; background: linear-gradient(90deg, transparent, var(--prc-violet), transparent); opacity: 0.55; }
    .card-title { font-family: var(--font-hud); font-size: 0.68rem; font-weight: 700; color: var(--prc-ice); letter-spacing: 0.10em; text-transform: uppercase; margin-bottom: 20px; display: flex; align-items: center; justify-content: space-between; gap: 12px; }
    .card-title-left { display: flex; align-items: center; gap: 10px; }
    .card-title-dot { width: 7px; height: 7px; border-radius: 50%; background: var(--prc-violet); box-shadow: 0 0 8px rgba(139,126,255,0.70); }

    /* ── FORM ROWS ── */
    .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 18px; margin-bottom: 16px; }
    .form-row.col-3 { grid-template-columns: 1fr 1fr 1fr; }
    .form-row.col-1 { grid-template-columns: 1fr; }
    .form-group { display: flex; flex-direction: column; gap: 6px; }
    .form-label { font-family: var(--font-hud); font-size: 0.52rem; font-weight: 700; letter-spacing: 0.14em; text-transform: uppercase; color: var(--text-soft); }
    .form-control {
      background: rgba(0,0,8,0.60); border: 1px solid rgba(139,126,255,0.22);
      color: var(--text-high); padding: 10px 14px; font-size: 0.875rem;
      outline: none; transition: border-color 0.2s, box-shadow 0.2s; width: 100%;
    }
    .form-control:focus { border-color: var(--prc-violet); box-shadow: 0 0 12px rgba(139,126,255,0.22); }
    textarea.form-control { resize: vertical; min-height: 90px; }
    select.form-control { cursor: pointer; }
    .form-hint { font-size: 0.76rem; color: var(--text-dim); margin-top: 2px; }

    /* ── TABLE ── */
    .admin-table { width: 100%; border-collapse: collapse; font-size: 0.875rem; }
    .admin-table th { font-family: var(--font-hud); font-size: 0.52rem; font-weight: 700; letter-spacing: 0.14em; text-transform: uppercase; color: var(--text-dim); padding: 10px 14px; border-bottom: 1px solid var(--border-neon); text-align: left; }
    .admin-table td { padding: 12px 14px; border-bottom: 1px solid rgba(139,126,255,0.08); color: var(--text-mid); vertical-align: top; }
    .admin-table tr:last-child td { border-bottom: none; }
    .admin-table tr:hover td { background: rgba(139,126,255,0.04); }
    .table-wrap { overflow-x: auto; }

    /* ── INLINE EDIT ROW ── */
    .edit-row { background: rgba(139,126,255,0.07); border-top: 1px solid rgba(139,126,255,0.22); }
    .edit-row td { padding: 18px 14px; }
    .edit-form-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 12px; margin-bottom: 12px; }
    .edit-actions { display: flex; gap: 8px; margin-top: 4px; }

    /* ── TOAST ── */
    #toast {
      position: fixed; bottom: 32px; right: 32px; z-index: 9999;
      background: rgba(4,3,26,0.96); border: 1px solid var(--prc-violet);
      color: var(--text-high); padding: 14px 22px;
      font-family: var(--font-hud); font-size: 0.60rem; font-weight: 700; letter-spacing: 0.10em;
      box-shadow: 0 0 28px rgba(139,126,255,0.30);
      transform: translateY(20px); opacity: 0; transition: all 0.3s; pointer-events: none;
      display: flex; align-items: center; gap: 10px;
    }
    #toast.show { transform: translateY(0); opacity: 1; }
    #toast.toast-success { border-color: var(--success); box-shadow: 0 0 20px rgba(68,255,136,0.22); }
    #toast.toast-error   { border-color: var(--danger);  box-shadow: 0 0 20px rgba(255,80,80,0.22); }
    .toast-dot { width: 6px; height: 6px; border-radius: 50%; background: var(--success); }
    #toast.toast-error .toast-dot { background: var(--danger); }

    /* ── DIVIDER ── */
    .section-divider { height: 1px; background: var(--border-neon); margin: 28px 0; }

    /* ── TYPE BADGE ── */
    .type-badge { display: inline-block; font-family: var(--font-hud); font-size: 0.48rem; font-weight: 700; letter-spacing: 0.10em; text-transform: uppercase; padding: 3px 9px; border: 1px solid; }
    .type-default    { color: var(--prc-violet); border-color: rgba(139,126,255,0.35); background: rgba(139,126,255,0.07); }
    .type-tesda      { color: var(--creo-volt);  border-color: rgba(255,233,48,0.35);  background: rgba(255,233,48,0.06); }
    .type-competition{ color: var(--creo-amber); border-color: rgba(255,160,48,0.35);  background: rgba(255,160,48,0.07); }

    /* ── PREVIEW IMG ── */
    .img-preview { width: 64px; height: 44px; object-fit: cover; border: 1px solid var(--border-neon); display: block; margin-top: 6px; }

    /* ── RESPONSIVE ── */
    @media (max-width: 900px) {
      .admin-content { margin-left: 0; max-width: 100%; padding: 20px 16px 60px; }
      .form-row { grid-template-columns: 1fr; }
      .form-row.col-3 { grid-template-columns: 1fr; }
    }

    ::-webkit-scrollbar { width: 4px; height: 4px; }
    ::-webkit-scrollbar-track { background: var(--bg-void); }
    ::-webkit-scrollbar-thumb { background: var(--prc-violet); border-radius: 2px; }
  </style>
</head>
<body>
<div class="admin-layout">

  <!-- SIDEBAR -->
  <div id="prc-sidebar-mount"></div>

  <!-- MAIN CONTENT -->
  <main class="admin-content">

    <!-- TOP BAR -->
    <div class="admin-topbar">
      <div>
        <div class="admin-page-title">About Page <span>Manager</span></div>
        <div class="admin-breadcrumb">// PRC ADMIN &nbsp;›&nbsp; CONTENT &nbsp;›&nbsp; ABOUT</div>
      </div>
      <div class="topbar-actions">
        <a href="about.php" target="_blank" class="btn btn-primary"><i class="fi fi-rr-eye"></i> Preview Page</a>
      </div>
    </div>

    <!-- TABS -->
    <div class="tabs-bar">
      <button class="tab-btn active" data-tab="hero">Hero / Who</button>
      <button class="tab-btn" data-tab="stats">Stats Strip</button>
      <button class="tab-btn" data-tab="mission">Mission &amp; Vision</button>
      <button class="tab-btn" data-tab="values">Our Values</button>
      <button class="tab-btn" data-tab="background">Background</button>
      <button class="tab-btn" data-tab="programs">Programs</button>
      <button class="tab-btn" data-tab="partners">Partners</button>
      <button class="tab-btn" data-tab="contact">Contact</button>
      <button class="tab-btn" data-tab="refs">References</button>
    </div>

    <!-- ══════════════ TAB: HERO / WHO ══════════════ -->
    <div class="tab-panel active" id="tab-hero">

      <!-- HERO -->
      <div class="admin-card">
        <div class="card-title">
          <div class="card-title-left"><span class="card-title-dot"></span> Page Hero</div>
          <button class="btn btn-primary btn-sm" onclick="saveMeta('hero_form')"><i class="fi fi-rr-disk"></i> Save</button>
        </div>
        <form id="hero_form">
          <div class="form-row">
            <div class="form-group">
              <label class="form-label">Eyebrow Text</label>
              <input type="text" name="hero_eyebrow" class="form-control" value="<?= m($meta,'hero_eyebrow') ?>"/>
            </div>
            <div class="form-group">
              <label class="form-label">Hero Title (HTML allowed)</label>
              <input type="text" name="hero_title" class="form-control" value="<?= m($meta,'hero_title') ?>"/>
            </div>
          </div>
          <div class="form-row col-1">
            <div class="form-group">
              <label class="form-label">Hero Description</label>
              <textarea name="hero_desc" class="form-control"><?= m($meta,'hero_desc') ?></textarea>
            </div>
          </div>
        </form>
      </div>

      <!-- WHO IS CREOTEC -->
      <div class="admin-card">
        <div class="card-title">
          <div class="card-title-left"><span class="card-title-dot"></span> Who Is Creotec — Text</div>
          <button class="btn btn-primary btn-sm" onclick="saveMeta('who_form')"><i class="fi fi-rr-disk"></i> Save</button>
        </div>
        <form id="who_form">
          <div class="form-row col-1">
            <div class="form-group">
              <label class="form-label">Paragraph 1</label>
              <textarea name="who_p1" class="form-control"><?= m($meta,'who_p1') ?></textarea>
            </div>
          </div>
          <div class="form-row col-1">
            <div class="form-group">
              <label class="form-label">Paragraph 2</label>
              <textarea name="who_p2" class="form-control"><?= m($meta,'who_p2') ?></textarea>
            </div>
          </div>
          <div class="form-row col-1">
            <div class="form-group">
              <label class="form-label">Paragraph 3 (HTML allowed for bold/links)</label>
              <textarea name="who_p3" class="form-control"><?= m($meta,'who_p3') ?></textarea>
            </div>
          </div>
          <div class="section-divider"></div>
          <div class="card-title" style="margin-bottom:14px;"><div class="card-title-left"><span class="card-title-dot"></span> Image &amp; Badge</div></div>
          <div class="form-row col-3">
            <div class="form-group">
              <label class="form-label">Image Path / URL</label>
              <input type="text" name="who_img" class="form-control" value="<?= m($meta,'who_img') ?>"/>
              <?php if (!empty($meta['who_img'])): ?>
              <img src="<?= htmlspecialchars($meta['who_img']) ?>" class="img-preview" alt="" onerror="this.style.display='none'"/>
              <?php endif; ?>
            </div>
            <div class="form-group">
              <label class="form-label">Badge Year</label>
              <input type="text" name="who_badge_year" class="form-control" value="<?= m($meta,'who_badge_year') ?>"/>
            </div>
            <div class="form-group">
              <label class="form-label">Badge Sub-label</label>
              <input type="text" name="who_badge_sub" class="form-control" value="<?= m($meta,'who_badge_sub') ?>"/>
            </div>
          </div>
        </form>
      </div>

    </div><!-- /tab-hero -->

    <!-- ══════════════ TAB: STATS STRIP ══════════════ -->
    <div class="tab-panel" id="tab-stats">
      <div class="admin-card">
        <div class="card-title">
          <div class="card-title-left"><span class="card-title-dot"></span> Stats Strip</div>
          <button class="btn btn-amber btn-sm" onclick="openAddRow('stats')"><i class="fi fi-rr-plus"></i> Add Stat</button>
        </div>
        <div class="table-wrap">
          <table class="admin-table" id="stats-table">
            <thead><tr><th>#</th><th>Icon Class</th><th>Number</th><th>Label</th><th>Sort</th><th>Actions</th></tr></thead>
            <tbody>
            <?php foreach ($stats as $s): ?>
            <tr id="stat-row-<?= $s['stat_id'] ?>">
              <td><?= $s['stat_id'] ?></td>
              <td><code style="font-size:0.78rem;color:var(--prc-violet);"><?= htmlspecialchars($s['stat_icon']) ?></code></td>
              <td style="font-family:var(--font-hud);color:var(--prc-violet);font-weight:700;"><?= htmlspecialchars($s['stat_num']) ?></td>
              <td><?= htmlspecialchars($s['stat_label']) ?></td>
              <td><?= $s['stat_sort'] ?></td>
              <td>
                <div style="display:flex;gap:6px;">
                  <button class="btn btn-primary btn-sm" onclick="editStat(<?= $s['stat_id'] ?>, <?= htmlspecialchars(json_encode($s)) ?>)"><i class="fi fi-rr-edit"></i></button>
                  <button class="btn btn-danger" onclick="deleteStat(<?= $s['stat_id'] ?>)"><i class="fi fi-rr-trash"></i></button>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <!-- Add/Edit form -->
        <div id="stat-edit-box" style="display:none;margin-top:20px;padding:20px;background:rgba(139,126,255,0.06);border:1px solid rgba(139,126,255,0.22);">
          <div class="card-title" style="margin-bottom:14px;"><div class="card-title-left"><span class="card-title-dot"></span> <span id="stat-edit-title">Edit Stat</span></div></div>
          <input type="hidden" id="stat_id" value="0"/>
          <div class="edit-form-grid">
            <div class="form-group"><label class="form-label">Icon Class</label><input type="text" id="stat_icon" class="form-control" placeholder="fi-rr-calendar"/><div class="form-hint">e.g. fi-rr-calendar, fi-rr-trophy</div></div>
            <div class="form-group"><label class="form-label">Number / Value</label><input type="text" id="stat_num" class="form-control" placeholder="2015"/></div>
            <div class="form-group"><label class="form-label">Label</label><input type="text" id="stat_label" class="form-control" placeholder="Year Established"/></div>
            <div class="form-group"><label class="form-label">Sort Order</label><input type="number" id="stat_sort" class="form-control" value="0"/></div>
          </div>
          <div class="edit-actions">
            <button class="btn btn-primary" onclick="saveStat()"><i class="fi fi-rr-disk"></i> Save Stat</button>
            <button class="btn btn-sm" style="color:var(--text-soft);border-color:rgba(139,126,255,0.22);" onclick="document.getElementById('stat-edit-box').style.display='none'">Cancel</button>
          </div>
        </div>
      </div>
    </div>

    <!-- ══════════════ TAB: MISSION & VISION ══════════════ -->
    <div class="tab-panel" id="tab-mission">
      <div class="admin-card">
        <div class="card-title">
          <div class="card-title-left"><span class="card-title-dot"></span> Mission &amp; Vision</div>
          <button class="btn btn-primary btn-sm" onclick="saveMeta('mv_form')"><i class="fi fi-rr-disk"></i> Save</button>
        </div>
        <form id="mv_form">
          <div class="form-row col-1">
            <div class="form-group">
              <label class="form-label">Vision Text</label>
              <textarea name="vision_text" class="form-control" style="min-height:110px;"><?= m($meta,'vision_text') ?></textarea>
            </div>
          </div>
          <div class="form-row col-1">
            <div class="form-group">
              <label class="form-label">Mission Text</label>
              <textarea name="mission_text" class="form-control" style="min-height:110px;"><?= m($meta,'mission_text') ?></textarea>
            </div>
          </div>
        </form>
      </div>
    </div>

    <!-- ══════════════ TAB: VALUES ══════════════ -->
    <div class="tab-panel" id="tab-values">
      <div class="admin-card">
        <div class="card-title">
          <div class="card-title-left"><span class="card-title-dot"></span> Our Values</div>
          <button class="btn btn-amber btn-sm" onclick="openAddValue()"><i class="fi fi-rr-plus"></i> Add Value</button>
        </div>
        <div class="table-wrap">
          <table class="admin-table">
            <thead><tr><th>#</th><th>Num</th><th>Icon</th><th>Title</th><th>Description</th><th>Sort</th><th>Actions</th></tr></thead>
            <tbody id="values-tbody">
            <?php foreach ($values as $v): ?>
            <tr id="val-row-<?= $v['val_id'] ?>">
              <td><?= $v['val_id'] ?></td>
              <td style="font-family:var(--font-hud);color:var(--prc-violet);font-size:0.85rem;font-weight:700;"><?= htmlspecialchars($v['val_num']) ?></td>
              <td><code style="font-size:0.75rem;color:var(--creo-amber);"><?= htmlspecialchars($v['val_icon']) ?></code></td>
              <td style="font-weight:600;"><?= htmlspecialchars($v['val_title']) ?></td>
              <td style="max-width:320px;font-size:0.83rem;"><?= htmlspecialchars(substr($v['val_desc'],0,90)) ?>...</td>
              <td><?= $v['val_sort'] ?></td>
              <td>
                <div style="display:flex;gap:6px;">
                  <button class="btn btn-primary btn-sm" onclick="editValue(<?= $v['val_id'] ?>, <?= htmlspecialchars(json_encode($v)) ?>)"><i class="fi fi-rr-edit"></i></button>
                  <button class="btn btn-danger" onclick="deleteValue(<?= $v['val_id'] ?>)"><i class="fi fi-rr-trash"></i></button>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <div id="value-edit-box" style="display:none;margin-top:20px;padding:20px;background:rgba(139,126,255,0.06);border:1px solid rgba(139,126,255,0.22);">
          <div class="card-title" style="margin-bottom:14px;"><div class="card-title-left"><span class="card-title-dot"></span> <span id="value-edit-title">Edit Value</span></div></div>
          <input type="hidden" id="val_id" value="0"/>
          <div class="form-row col-3">
            <div class="form-group"><label class="form-label">Number Label</label><input type="text" id="val_num" class="form-control" placeholder="01"/></div>
            <div class="form-group"><label class="form-label">Icon Class</label><input type="text" id="val_icon" class="form-control" placeholder="fi-rr-star"/></div>
            <div class="form-group"><label class="form-label">Sort Order</label><input type="number" id="val_sort" class="form-control" value="0"/></div>
          </div>
          <div class="form-row col-1">
            <div class="form-group"><label class="form-label">Title</label><input type="text" id="val_title" class="form-control"/></div>
          </div>
          <div class="form-row col-1">
            <div class="form-group"><label class="form-label">Description</label><textarea id="val_desc" class="form-control"></textarea></div>
          </div>
          <div class="edit-actions">
            <button class="btn btn-primary" onclick="saveValue()"><i class="fi fi-rr-disk"></i> Save Value</button>
            <button class="btn btn-sm" style="color:var(--text-soft);border-color:rgba(139,126,255,0.22);" onclick="document.getElementById('value-edit-box').style.display='none'">Cancel</button>
          </div>
        </div>
      </div>
    </div>

    <!-- ══════════════ TAB: BACKGROUND ══════════════ -->
    <div class="tab-panel" id="tab-background">

      <div class="admin-card">
        <div class="card-title">
          <div class="card-title-left"><span class="card-title-dot"></span> Background — Text &amp; Images</div>
          <button class="btn btn-primary btn-sm" onclick="saveMeta('bg_form')"><i class="fi fi-rr-disk"></i> Save</button>
        </div>
        <form id="bg_form">
          <div class="form-row col-1"><div class="form-group"><label class="form-label">Paragraph 1</label><textarea name="bg_p1" class="form-control"><?= m($meta,'bg_p1') ?></textarea></div></div>
          <div class="form-row col-1"><div class="form-group"><label class="form-label">Paragraph 2</label><textarea name="bg_p2" class="form-control"><?= m($meta,'bg_p2') ?></textarea></div></div>
          <div class="form-row">
            <div class="form-group">
              <label class="form-label">Main Image Path / URL</label>
              <input type="text" name="bg_img_main" class="form-control" value="<?= m($meta,'bg_img_main') ?>"/>
              <?php if (!empty($meta['bg_img_main'])): ?><img src="<?= htmlspecialchars($meta['bg_img_main']) ?>" class="img-preview" onerror="this.style.display='none'"/><?php endif; ?>
            </div>
            <div class="form-group">
              <label class="form-label">Secondary Image Path / URL</label>
              <input type="text" name="bg_img_secondary" class="form-control" value="<?= m($meta,'bg_img_secondary') ?>"/>
              <?php if (!empty($meta['bg_img_secondary'])): ?><img src="<?= htmlspecialchars($meta['bg_img_secondary']) ?>" class="img-preview" onerror="this.style.display='none'"/><?php endif; ?>
            </div>
          </div>
        </form>
      </div>

      <!-- HIGHLIGHTS -->
      <div class="admin-card">
        <div class="card-title">
          <div class="card-title-left"><span class="card-title-dot"></span> Highlights (Background Section)</div>
          <button class="btn btn-amber btn-sm" onclick="openAddHighlight()"><i class="fi fi-rr-plus"></i> Add</button>
        </div>
        <div class="table-wrap">
          <table class="admin-table">
            <thead><tr><th>#</th><th>Icon</th><th>Title</th><th>Description</th><th>Sort</th><th>Actions</th></tr></thead>
            <tbody id="hl-tbody">
            <?php foreach ($highlights as $h): ?>
            <tr id="hl-row-<?= $h['hl_id'] ?>">
              <td><?= $h['hl_id'] ?></td>
              <td><code style="font-size:0.75rem;color:var(--prc-violet);"><?= htmlspecialchars($h['hl_icon']) ?></code></td>
              <td style="font-weight:600;"><?= htmlspecialchars($h['hl_title']) ?></td>
              <td style="font-size:0.83rem;"><?= htmlspecialchars($h['hl_desc']) ?></td>
              <td><?= $h['hl_sort'] ?></td>
              <td>
                <div style="display:flex;gap:6px;">
                  <button class="btn btn-primary btn-sm" onclick="editHighlight(<?= $h['hl_id'] ?>, <?= htmlspecialchars(json_encode($h)) ?>)"><i class="fi fi-rr-edit"></i></button>
                  <button class="btn btn-danger" onclick="deleteHighlight(<?= $h['hl_id'] ?>)"><i class="fi fi-rr-trash"></i></button>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <div id="hl-edit-box" style="display:none;margin-top:20px;padding:20px;background:rgba(139,126,255,0.06);border:1px solid rgba(139,126,255,0.22);">
          <input type="hidden" id="hl_id" value="0"/>
          <div class="form-row col-3">
            <div class="form-group"><label class="form-label">Icon</label><input type="text" id="hl_icon" class="form-control"/></div>
            <div class="form-group"><label class="form-label">Title</label><input type="text" id="hl_title" class="form-control"/></div>
            <div class="form-group"><label class="form-label">Sort</label><input type="number" id="hl_sort" class="form-control" value="0"/></div>
          </div>
          <div class="form-row col-1"><div class="form-group"><label class="form-label">Description</label><input type="text" id="hl_desc" class="form-control"/></div></div>
          <div class="edit-actions">
            <button class="btn btn-primary" onclick="saveHighlight()"><i class="fi fi-rr-disk"></i> Save</button>
            <button class="btn btn-sm" style="color:var(--text-soft);border-color:rgba(139,126,255,0.22);" onclick="document.getElementById('hl-edit-box').style.display='none'">Cancel</button>
          </div>
        </div>
      </div>
    </div>

    <!-- ══════════════ TAB: PROGRAMS ══════════════ -->
    <div class="tab-panel" id="tab-programs">
      <div class="admin-card">
        <div class="card-title">
          <div class="card-title-left"><span class="card-title-dot"></span> Training Programs</div>
          <button class="btn btn-amber btn-sm" onclick="openAddProgram()"><i class="fi fi-rr-plus"></i> Add Program</button>
        </div>
        <div class="table-wrap">
          <table class="admin-table">
            <thead><tr><th>#</th><th>Num</th><th>Title</th><th>Type</th><th>Tags</th><th>Sort</th><th>Actions</th></tr></thead>
            <tbody id="prog-tbody">
            <?php foreach ($programs as $p): ?>
            <tr id="prog-row-<?= $p['prog_id'] ?>">
              <td><?= $p['prog_id'] ?></td>
              <td style="font-family:var(--font-hud);font-size:0.72rem;color:var(--prc-violet);white-space:nowrap;"><?= htmlspecialchars($p['prog_num']) ?></td>
              <td style="font-weight:600;max-width:260px;"><?= htmlspecialchars($p['prog_title']) ?></td>
              <td><span class="type-badge type-<?= htmlspecialchars($p['prog_type']) ?>"><?= htmlspecialchars($p['prog_type']) ?></span></td>
              <td style="font-size:0.78rem;color:var(--text-soft);"><?= htmlspecialchars($p['prog_tags']) ?></td>
              <td><?= $p['prog_sort'] ?></td>
              <td>
                <div style="display:flex;gap:6px;">
                  <button class="btn btn-primary btn-sm" onclick="editProgram(<?= $p['prog_id'] ?>, <?= htmlspecialchars(json_encode($p)) ?>)"><i class="fi fi-rr-edit"></i></button>
                  <button class="btn btn-danger" onclick="deleteProgram(<?= $p['prog_id'] ?>)"><i class="fi fi-rr-trash"></i></button>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <div id="prog-edit-box" style="display:none;margin-top:20px;padding:20px;background:rgba(139,126,255,0.06);border:1px solid rgba(139,126,255,0.22);">
          <input type="hidden" id="prog_id" value="0"/>
          <div class="form-row col-3">
            <div class="form-group"><label class="form-label">Program Number</label><input type="text" id="prog_num" class="form-control" placeholder="Program 01"/></div>
            <div class="form-group">
              <label class="form-label">Card Type</label>
              <select id="prog_type" class="form-control">
                <option value="default">Default (violet)</option>
                <option value="tesda">TESDA (volt)</option>
                <option value="competition">Competition (amber)</option>
              </select>
            </div>
            <div class="form-group"><label class="form-label">Sort Order</label><input type="number" id="prog_sort" class="form-control" value="0"/></div>
          </div>
          <div class="form-row col-1"><div class="form-group"><label class="form-label">Title</label><input type="text" id="prog_title" class="form-control"/></div></div>
          <div class="form-row col-1"><div class="form-group"><label class="form-label">Description</label><textarea id="prog_desc" class="form-control" style="min-height:100px;"></textarea></div></div>
          <div class="form-row">
            <div class="form-group">
              <label class="form-label">Image URL / Path</label>
              <input type="text" id="prog_img" class="form-control" placeholder="https://... or assets/..."/>
            </div>
            <div class="form-group">
              <label class="form-label">Tags (comma-separated)</label>
              <input type="text" id="prog_tags" class="form-control" placeholder="80 hrs, TESDA NC II, PLC"/>
              <div class="form-hint">Displayed as small tag pills on the card.</div>
            </div>
          </div>
          <div class="edit-actions">
            <button class="btn btn-primary" onclick="saveProgram()"><i class="fi fi-rr-disk"></i> Save Program</button>
            <button class="btn btn-sm" style="color:var(--text-soft);border-color:rgba(139,126,255,0.22);" onclick="document.getElementById('prog-edit-box').style.display='none'">Cancel</button>
          </div>
        </div>
      </div>
    </div>

    <!-- ══════════════ TAB: PARTNERS ══════════════ -->
    <div class="tab-panel" id="tab-partners">

      <!-- Section heading meta -->
      <div class="admin-card">
        <div class="card-title">
          <div class="card-title-left"><span class="card-title-dot"></span> Partners Section Header</div>
          <button class="btn btn-primary btn-sm" onclick="saveMeta('partners_meta_form')"><i class="fi fi-rr-disk"></i> Save</button>
        </div>
        <form id="partners_meta_form">
          <div class="form-row">
            <div class="form-group"><label class="form-label">Section Heading</label><input type="text" name="partners_heading" class="form-control" value="<?= m($meta,'partners_heading') ?>"/></div>
            <div class="form-group"><label class="form-label">Sub-text</label><input type="text" name="partners_sub" class="form-control" value="<?= m($meta,'partners_sub') ?>"/></div>
          </div>
        </form>
      </div>

      <!-- Government Partners -->
      <div class="admin-card">
        <div class="card-title">
          <div class="card-title-left"><span class="card-title-dot"></span> Government / Institutional Partners</div>
          <button class="btn btn-amber btn-sm" onclick="openAddGovPartner()"><i class="fi fi-rr-plus"></i> Add</button>
        </div>
        <div class="table-wrap">
          <table class="admin-table">
            <thead><tr><th>#</th><th>Name</th><th>Short</th><th>Logo Path</th><th>Sort</th><th>Actions</th></tr></thead>
            <tbody id="gov-tbody">
            <?php foreach ($partners_g as $pg): ?>
            <tr id="pg-row-<?= $pg['pg_id'] ?>">
              <td><?= $pg['pg_id'] ?></td>
              <td><?= htmlspecialchars($pg['pg_name']) ?></td>
              <td style="font-family:var(--font-hud);font-size:0.75rem;color:var(--prc-violet);font-weight:700;"><?= htmlspecialchars($pg['pg_short']) ?></td>
              <td style="font-size:0.78rem;color:var(--text-soft);"><?= htmlspecialchars($pg['pg_logo']) ?: '—' ?></td>
              <td><?= $pg['pg_sort'] ?></td>
              <td>
                <div style="display:flex;gap:6px;">
                  <button class="btn btn-primary btn-sm" onclick="editGovPartner(<?= $pg['pg_id'] ?>, <?= htmlspecialchars(json_encode($pg)) ?>)"><i class="fi fi-rr-edit"></i></button>
                  <button class="btn btn-danger" onclick="deleteGovPartner(<?= $pg['pg_id'] ?>)"><i class="fi fi-rr-trash"></i></button>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <div id="gov-edit-box" style="display:none;margin-top:20px;padding:20px;background:rgba(139,126,255,0.06);border:1px solid rgba(139,126,255,0.22);">
          <input type="hidden" id="pg_id" value="0"/>
          <div class="form-row" style="grid-template-columns:2fr 1fr 2fr 1fr;">
            <div class="form-group"><label class="form-label">Full Name</label><input type="text" id="pg_name" class="form-control"/></div>
            <div class="form-group"><label class="form-label">Short / Acronym</label><input type="text" id="pg_short" class="form-control"/></div>
            <div class="form-group"><label class="form-label">Logo Path (optional)</label><input type="text" id="pg_logo" class="form-control" placeholder="assets/DepED-Logo.png"/></div>
            <div class="form-group"><label class="form-label">Sort</label><input type="number" id="pg_sort" class="form-control" value="0"/></div>
          </div>
          <div class="edit-actions">
            <button class="btn btn-primary" onclick="saveGovPartner()"><i class="fi fi-rr-disk"></i> Save</button>
            <button class="btn btn-sm" style="color:var(--text-soft);border-color:rgba(139,126,255,0.22);" onclick="document.getElementById('gov-edit-box').style.display='none'">Cancel</button>
          </div>
        </div>
      </div>

      <!-- Academic Partners -->
      <div class="admin-card">
        <div class="card-title">
          <div class="card-title-left"><span class="card-title-dot"></span> Academic Partner Institutions</div>
          <button class="btn btn-amber btn-sm" onclick="openAddAcadPartner()"><i class="fi fi-rr-plus"></i> Add</button>
        </div>
        <div class="table-wrap">
          <table class="admin-table">
            <thead><tr><th>#</th><th>Institution Name</th><th>Sort</th><th>Actions</th></tr></thead>
            <tbody id="acad-tbody">
            <?php foreach ($partners_a as $pa): ?>
            <tr id="pa-row-<?= $pa['pa_id'] ?>">
              <td><?= $pa['pa_id'] ?></td>
              <td><?= htmlspecialchars($pa['pa_name']) ?></td>
              <td><?= $pa['pa_sort'] ?></td>
              <td>
                <div style="display:flex;gap:6px;align-items:center;">
                  <input type="text" value="<?= htmlspecialchars($pa['pa_name']) ?>" id="pa-inline-<?= $pa['pa_id'] ?>" class="form-control" style="max-width:260px;padding:6px 10px;font-size:0.82rem;"/>
                  <button class="btn btn-success btn-sm" onclick="saveAcadInline(<?= $pa['pa_id'] ?>)"><i class="fi fi-rr-disk"></i></button>
                  <button class="btn btn-danger" onclick="deleteAcadPartner(<?= $pa['pa_id'] ?>)"><i class="fi fi-rr-trash"></i></button>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <!-- Add new academic partner -->
        <div id="acad-add-box" style="display:none;margin-top:16px;padding:16px;background:rgba(139,126,255,0.06);border:1px solid rgba(139,126,255,0.22);">
          <div class="form-row" style="grid-template-columns:3fr 1fr;">
            <div class="form-group"><label class="form-label">Institution Name</label><input type="text" id="pa_name_new" class="form-control" placeholder="e.g. Ateneo de Manila University"/></div>
            <div class="form-group"><label class="form-label">Sort</label><input type="number" id="pa_sort_new" class="form-control" value="<?= count($partners_a) ?>"/></div>
          </div>
          <div class="edit-actions">
            <button class="btn btn-primary" onclick="saveAcadNew()"><i class="fi fi-rr-plus"></i> Add Institution</button>
            <button class="btn btn-sm" style="color:var(--text-soft);border-color:rgba(139,126,255,0.22);" onclick="document.getElementById('acad-add-box').style.display='none'">Cancel</button>
          </div>
        </div>
      </div>

    </div><!-- /tab-partners -->

    <!-- ══════════════ TAB: CONTACT ══════════════ -->
    <div class="tab-panel" id="tab-contact">
      <div class="admin-card">
        <div class="card-title">
          <div class="card-title-left"><span class="card-title-dot"></span> Contact Details</div>
          <button class="btn btn-primary btn-sm" onclick="saveMeta('contact_form')"><i class="fi fi-rr-disk"></i> Save</button>
        </div>
        <form id="contact_form">
          <div class="form-row col-1"><div class="form-group"><label class="form-label">Address</label><input type="text" name="contact_address" class="form-control" value="<?= m($meta,'contact_address') ?>"/></div></div>
          <div class="form-row">
            <div class="form-group"><label class="form-label">Phone 1</label><input type="text" name="contact_phone1" class="form-control" value="<?= m($meta,'contact_phone1') ?>"/></div>
            <div class="form-group"><label class="form-label">Phone 2</label><input type="text" name="contact_phone2" class="form-control" value="<?= m($meta,'contact_phone2') ?>"/></div>
          </div>
          <div class="form-row">
            <div class="form-group"><label class="form-label">Email</label><input type="email" name="contact_email" class="form-control" value="<?= m($meta,'contact_email') ?>"/></div>
            <div class="form-group"><label class="form-label">Website</label><input type="text" name="contact_website" class="form-control" value="<?= m($meta,'contact_website') ?>"/></div>
          </div>
          <div class="form-row">
            <div class="form-group"><label class="form-label">Facebook Label</label><input type="text" name="contact_fb_label" class="form-control" value="<?= m($meta,'contact_fb_label') ?>"/></div>
            <div class="form-group"><label class="form-label">Facebook URL</label><input type="url" name="contact_fb_url" class="form-control" value="<?= m($meta,'contact_fb_url') ?>"/></div>
          </div>
        </form>
      </div>
    </div>

    <!-- ══════════════ TAB: REFERENCES ══════════════ -->
    <div class="tab-panel" id="tab-refs">
      <div class="admin-card">
        <div class="card-title">
          <div class="card-title-left"><span class="card-title-dot"></span> Reference Links / Cards</div>
          <button class="btn btn-amber btn-sm" onclick="openAddRef()"><i class="fi fi-rr-plus"></i> Add Reference</button>
        </div>
        <div class="table-wrap">
          <table class="admin-table">
            <thead><tr><th>#</th><th>Type</th><th>Title</th><th>Platform</th><th>Tag</th><th>Sort</th><th>Actions</th></tr></thead>
            <tbody id="refs-tbody">
            <?php foreach ($refs as $ref): ?>
            <tr id="ref-row-<?= $ref['ref_id'] ?>">
              <td><?= $ref['ref_id'] ?></td>
              <td><span class="type-badge type-<?= $ref['ref_type'] === 'award' ? 'competition' : 'default' ?>"><?= htmlspecialchars($ref['ref_type']) ?></span></td>
              <td style="max-width:240px;font-weight:600;font-size:0.83rem;"><?= htmlspecialchars($ref['ref_title']) ?></td>
              <td style="font-size:0.78rem;color:var(--text-soft);"><?= htmlspecialchars($ref['ref_platform']) ?></td>
              <td style="font-size:0.78rem;"><?= htmlspecialchars($ref['ref_tag']) ?></td>
              <td><?= $ref['ref_sort'] ?></td>
              <td>
                <div style="display:flex;gap:6px;">
                  <a href="<?= htmlspecialchars($ref['ref_url']) ?>" target="_blank" class="btn btn-sm" style="color:var(--creo-volt);border-color:rgba(255,233,48,0.30);background:rgba(255,233,48,0.05);"><i class="fi fi-rr-link"></i></a>
                  <button class="btn btn-primary btn-sm" onclick="editRef(<?= $ref['ref_id'] ?>, <?= htmlspecialchars(json_encode($ref)) ?>)"><i class="fi fi-rr-edit"></i></button>
                  <button class="btn btn-danger" onclick="deleteRef(<?= $ref['ref_id'] ?>)"><i class="fi fi-rr-trash"></i></button>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <div id="ref-edit-box" style="display:none;margin-top:20px;padding:20px;background:rgba(139,126,255,0.06);border:1px solid rgba(139,126,255,0.22);">
          <input type="hidden" id="ref_id" value="0"/>
          <div class="form-row col-3">
            <div class="form-group">
              <label class="form-label">Card Type</label>
              <select id="ref_type" class="form-control">
                <option value="fb">Facebook (blue)</option>
                <option value="award">Award / Achievement (volt)</option>
              </select>
            </div>
            <div class="form-group"><label class="form-label">Source Label</label><input type="text" id="ref_source" class="form-control" placeholder="Facebook Video"/></div>
            <div class="form-group"><label class="form-label">Platform</label><input type="text" id="ref_platform" class="form-control" placeholder="Creotec Philippines Inc."/></div>
          </div>
          <div class="form-row col-1"><div class="form-group"><label class="form-label">Title</label><input type="text" id="ref_title" class="form-control"/></div></div>
          <div class="form-row col-1"><div class="form-group"><label class="form-label">Description</label><textarea id="ref_desc" class="form-control"></textarea></div></div>
          <div class="form-row col-3">
            <div class="form-group"><label class="form-label">Tag Label</label><input type="text" id="ref_tag" class="form-control" placeholder="Roboventure"/></div>
            <div class="form-group"><label class="form-label">Sort Order</label><input type="number" id="ref_sort" class="form-control" value="0"/></div>
          </div>
          <div class="form-row col-1"><div class="form-group"><label class="form-label">URL</label><input type="url" id="ref_url" class="form-control" placeholder="https://"/></div></div>
          <div class="edit-actions">
            <button class="btn btn-primary" onclick="saveRef()"><i class="fi fi-rr-disk"></i> Save Reference</button>
            <button class="btn btn-sm" style="color:var(--text-soft);border-color:rgba(139,126,255,0.22);" onclick="document.getElementById('ref-edit-box').style.display='none'">Cancel</button>
          </div>
        </div>
      </div>
    </div>

  </main><!-- /admin-content -->
</div><!-- /admin-layout -->

<!-- TOAST -->
<div id="toast"><span class="toast-dot"></span><span id="toast-msg">Saved!</span></div>

<!-- SIDEBAR INCLUDE -->
<script>
fetch('admin-sidebar.html').then(r => r.text()).then(html => {
  document.getElementById('prc-sidebar-mount').innerHTML = html;
  // Mark gallery as active
  setTimeout(() => {
    document.querySelectorAll('.sb-nav-item').forEach(a => {
      a.classList.remove('active');
      if (a.getAttribute('href') === 'admin-about.php') a.classList.add('active');
    });
  }, 100);
});

// ── Sidebar collapse sync ──────────────────────────────
document.addEventListener('prc-sidebar-toggle', function(e) {
  var content = document.querySelector('.admin-content');
  if (content) content.style.marginLeft = e.detail.collapsed ? '68px' : '248px';
});

// ── TABS ──────────────────────────────────────────────
document.querySelectorAll('.tab-btn').forEach(function(btn) {
  btn.addEventListener('click', function() {
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
    btn.classList.add('active');
    document.getElementById('tab-' + btn.dataset.tab).classList.add('active');
  });
});

// ── TOAST ─────────────────────────────────────────────
function showToast(msg, type) {
  var t = document.getElementById('toast');
  var m = document.getElementById('toast-msg');
  t.className = 'show toast-' + (type || 'success');
  m.textContent = msg;
  clearTimeout(t._timer);
  t._timer = setTimeout(function() { t.classList.remove('show'); }, 2800);
}

// ── GENERIC POST ──────────────────────────────────────
function post(data, onSuccess) {
  var fd = new FormData();
  Object.keys(data).forEach(k => fd.append(k, data[k]));
  fetch('admin-about.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(res => {
      if (res.ok) { showToast('Saved!', 'success'); if (onSuccess) onSuccess(res); }
      else showToast('Error saving.', 'error');
    }).catch(() => showToast('Network error.', 'error'));
}

// ── SAVE META FORM ────────────────────────────────────
function saveMeta(formId) {
  var form = document.getElementById(formId);
  var data = { action: 'save_meta' };
  new FormData(form).forEach((v, k) => data[k] = v);
  post(data);
}

// ═══════════════════════════════════
// STATS
// ═══════════════════════════════════
function openAddRow(type) {
  if (type === 'stats') {
    document.getElementById('stat_id').value = '0';
    document.getElementById('stat_icon').value = '';
    document.getElementById('stat_num').value = '';
    document.getElementById('stat_label').value = '';
    document.getElementById('stat_sort').value = document.querySelectorAll('#stats-table tbody tr').length;
    document.getElementById('stat-edit-title').textContent = 'Add New Stat';
    document.getElementById('stat-edit-box').style.display = '';
    document.getElementById('stat-edit-box').scrollIntoView({ behavior: 'smooth', block: 'nearest' });
  }
}
function editStat(id, data) {
  document.getElementById('stat_id').value   = id;
  document.getElementById('stat_icon').value = data.stat_icon;
  document.getElementById('stat_num').value  = data.stat_num;
  document.getElementById('stat_label').value= data.stat_label;
  document.getElementById('stat_sort').value = data.stat_sort;
  document.getElementById('stat-edit-title').textContent = 'Edit Stat';
  document.getElementById('stat-edit-box').style.display = '';
  document.getElementById('stat-edit-box').scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}
function saveStat() {
  post({
    action: 'save_stat',
    stat_id:    document.getElementById('stat_id').value,
    stat_icon:  document.getElementById('stat_icon').value,
    stat_num:   document.getElementById('stat_num').value,
    stat_label: document.getElementById('stat_label').value,
    stat_sort:  document.getElementById('stat_sort').value,
  }, () => location.reload());
}
function deleteStat(id) {
  if (!confirm('Delete this stat?')) return;
  post({ action: 'delete_stat', stat_id: id }, () => {
    var row = document.getElementById('stat-row-' + id);
    if (row) row.remove();
  });
}

// ═══════════════════════════════════
// VALUES
// ═══════════════════════════════════
function openAddValue() {
  ['val_id','val_num','val_icon','val_title','val_desc'].forEach(id => document.getElementById(id).value = '');
  document.getElementById('val_sort').value = document.querySelectorAll('#values-tbody tr').length;
  document.getElementById('value-edit-title').textContent = 'Add New Value';
  document.getElementById('value-edit-box').style.display = '';
  document.getElementById('value-edit-box').scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}
function editValue(id, data) {
  document.getElementById('val_id').value    = id;
  document.getElementById('val_num').value   = data.val_num;
  document.getElementById('val_icon').value  = data.val_icon;
  document.getElementById('val_title').value = data.val_title;
  document.getElementById('val_desc').value  = data.val_desc;
  document.getElementById('val_sort').value  = data.val_sort;
  document.getElementById('value-edit-title').textContent = 'Edit Value';
  document.getElementById('value-edit-box').style.display = '';
  document.getElementById('value-edit-box').scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}
function saveValue() {
  post({
    action:'save_value',
    val_id:    document.getElementById('val_id').value,
    val_num:   document.getElementById('val_num').value,
    val_icon:  document.getElementById('val_icon').value,
    val_title: document.getElementById('val_title').value,
    val_desc:  document.getElementById('val_desc').value,
    val_sort:  document.getElementById('val_sort').value,
  }, () => location.reload());
}
function deleteValue(id) {
  if (!confirm('Delete this value card?')) return;
  post({ action:'delete_value', val_id: id }, () => { var r = document.getElementById('val-row-'+id); if(r) r.remove(); });
}

// ═══════════════════════════════════
// HIGHLIGHTS
// ═══════════════════════════════════
function openAddHighlight() {
  ['hl_id','hl_icon','hl_title','hl_desc'].forEach(id => document.getElementById(id).value = '');
  document.getElementById('hl_sort').value = document.querySelectorAll('#hl-tbody tr').length;
  document.getElementById('hl-edit-box').style.display = '';
  document.getElementById('hl-edit-box').scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}
function editHighlight(id, data) {
  document.getElementById('hl_id').value    = id;
  document.getElementById('hl_icon').value  = data.hl_icon;
  document.getElementById('hl_title').value = data.hl_title;
  document.getElementById('hl_desc').value  = data.hl_desc;
  document.getElementById('hl_sort').value  = data.hl_sort;
  document.getElementById('hl-edit-box').style.display = '';
  document.getElementById('hl-edit-box').scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}
function saveHighlight() {
  post({
    action:'save_highlight',
    hl_id:    document.getElementById('hl_id').value,
    hl_icon:  document.getElementById('hl_icon').value,
    hl_title: document.getElementById('hl_title').value,
    hl_desc:  document.getElementById('hl_desc').value,
    hl_sort:  document.getElementById('hl_sort').value,
  }, () => location.reload());
}
function deleteHighlight(id) {
  if (!confirm('Delete this highlight?')) return;
  post({ action:'delete_highlight', hl_id: id }, () => { var r = document.getElementById('hl-row-'+id); if(r) r.remove(); });
}

// ═══════════════════════════════════
// PROGRAMS
// ═══════════════════════════════════
function openAddProgram() {
  ['prog_id','prog_num','prog_title','prog_desc','prog_img','prog_tags'].forEach(id => document.getElementById(id).value = '');
  document.getElementById('prog_sort').value = document.querySelectorAll('#prog-tbody tr').length;
  document.getElementById('prog_type').value = 'default';
  document.getElementById('prog-edit-box').style.display = '';
  document.getElementById('prog-edit-box').scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}
function editProgram(id, data) {
  document.getElementById('prog_id').value    = id;
  document.getElementById('prog_num').value   = data.prog_num;
  document.getElementById('prog_title').value = data.prog_title;
  document.getElementById('prog_desc').value  = data.prog_desc;
  document.getElementById('prog_img').value   = data.prog_img;
  document.getElementById('prog_type').value  = data.prog_type;
  document.getElementById('prog_tags').value  = data.prog_tags;
  document.getElementById('prog_sort').value  = data.prog_sort;
  document.getElementById('prog-edit-box').style.display = '';
  document.getElementById('prog-edit-box').scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}
function saveProgram() {
  post({
    action:'save_program',
    prog_id:    document.getElementById('prog_id').value,
    prog_num:   document.getElementById('prog_num').value,
    prog_title: document.getElementById('prog_title').value,
    prog_desc:  document.getElementById('prog_desc').value,
    prog_img:   document.getElementById('prog_img').value,
    prog_type:  document.getElementById('prog_type').value,
    prog_tags:  document.getElementById('prog_tags').value,
    prog_sort:  document.getElementById('prog_sort').value,
  }, () => location.reload());
}
function deleteProgram(id) {
  if (!confirm('Delete this program?')) return;
  post({ action:'delete_program', prog_id: id }, () => { var r = document.getElementById('prog-row-'+id); if(r) r.remove(); });
}

// ═══════════════════════════════════
// GOV PARTNERS
// ═══════════════════════════════════
function openAddGovPartner() {
  ['pg_id','pg_name','pg_short','pg_logo'].forEach(id => document.getElementById(id).value = '');
  document.getElementById('pg_sort').value = document.querySelectorAll('#gov-tbody tr').length;
  document.getElementById('gov-edit-box').style.display = '';
  document.getElementById('gov-edit-box').scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}
function editGovPartner(id, data) {
  document.getElementById('pg_id').value    = id;
  document.getElementById('pg_name').value  = data.pg_name;
  document.getElementById('pg_short').value = data.pg_short;
  document.getElementById('pg_logo').value  = data.pg_logo;
  document.getElementById('pg_sort').value  = data.pg_sort;
  document.getElementById('gov-edit-box').style.display = '';
  document.getElementById('gov-edit-box').scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}
function saveGovPartner() {
  post({
    action:'save_partner_gov',
    pg_id:    document.getElementById('pg_id').value,
    pg_name:  document.getElementById('pg_name').value,
    pg_short: document.getElementById('pg_short').value,
    pg_logo:  document.getElementById('pg_logo').value,
    pg_sort:  document.getElementById('pg_sort').value,
  }, () => location.reload());
}
function deleteGovPartner(id) {
  if (!confirm('Delete this partner?')) return;
  post({ action:'delete_partner_gov', pg_id: id }, () => { var r = document.getElementById('pg-row-'+id); if(r) r.remove(); });
}

// ═══════════════════════════════════
// ACAD PARTNERS
// ═══════════════════════════════════
function openAddAcadPartner() {
  document.getElementById('acad-add-box').style.display = '';
  document.getElementById('pa_name_new').focus();
}
function saveAcadInline(id) {
  var name = document.getElementById('pa-inline-' + id).value;
  post({ action:'save_partner_acad', pa_id: id, pa_name: name, pa_sort: 0 });
}
function saveAcadNew() {
  var name = document.getElementById('pa_name_new').value.trim();
  var sort = document.getElementById('pa_sort_new').value;
  if (!name) { showToast('Enter an institution name.', 'error'); return; }
  post({ action:'save_partner_acad', pa_id: 0, pa_name: name, pa_sort: sort }, () => location.reload());
}
function deleteAcadPartner(id) {
  if (!confirm('Remove this academic partner?')) return;
  post({ action:'delete_partner_acad', pa_id: id }, () => { var r = document.getElementById('pa-row-'+id); if(r) r.remove(); });
}

// ═══════════════════════════════════
// REFERENCES
// ═══════════════════════════════════
function openAddRef() {
  ['ref_id','ref_source','ref_platform','ref_title','ref_desc','ref_tag','ref_url'].forEach(id => document.getElementById(id).value = '');
  document.getElementById('ref_sort').value = document.querySelectorAll('#refs-tbody tr').length;
  document.getElementById('ref_type').value = 'fb';
  document.getElementById('ref-edit-box').style.display = '';
  document.getElementById('ref-edit-box').scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}
function editRef(id, data) {
  document.getElementById('ref_id').value       = id;
  document.getElementById('ref_type').value     = data.ref_type;
  document.getElementById('ref_source').value   = data.ref_source;
  document.getElementById('ref_platform').value = data.ref_platform;
  document.getElementById('ref_title').value    = data.ref_title;
  document.getElementById('ref_desc').value     = data.ref_desc;
  document.getElementById('ref_tag').value      = data.ref_tag;
  document.getElementById('ref_url').value      = data.ref_url;
  document.getElementById('ref_sort').value     = data.ref_sort;
  document.getElementById('ref-edit-box').style.display = '';
  document.getElementById('ref-edit-box').scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}
function saveRef() {
  post({
    action:'save_ref',
    ref_id:       document.getElementById('ref_id').value,
    ref_type:     document.getElementById('ref_type').value,
    ref_source:   document.getElementById('ref_source').value,
    ref_platform: document.getElementById('ref_platform').value,
    ref_title:    document.getElementById('ref_title').value,
    ref_desc:     document.getElementById('ref_desc').value,
    ref_tag:      document.getElementById('ref_tag').value,
    ref_url:      document.getElementById('ref_url').value,
    ref_sort:     document.getElementById('ref_sort').value,
  }, () => location.reload());
}
function deleteRef(id) {
  if (!confirm('Delete this reference?')) return;
  post({ action:'delete_ref', ref_id: id }, () => { var r = document.getElementById('ref-row-'+id); if(r) r.remove(); });
}
</script>

</body>
</html>