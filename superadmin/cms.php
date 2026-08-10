<?php
require_once __DIR__ . '/../api/db.php';
requireSuperAdmin();

$db = getDB();
$adminName = $_SESSION['superadmin_name'] ?? 'Admin';
$allowedTypes = ['hero', 'announcement', 'content', 'call_to_action', 'general'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        $_SESSION['cms_flash'] = ['error', 'Your session expired. Please try again.'];
        header('Location: cms.php'); exit;
    }

    $action = $_POST['action'] ?? 'save';
    $id = (int)($_POST['id'] ?? 0);

    if ($action === 'delete' && $id) {
        $stmt = $db->prepare('SELECT section_key FROM cms_content WHERE id = ?');
        $stmt->execute([$id]);
        $item = $stmt->fetch();
        if ($item) {
            $db->prepare('DELETE FROM cms_content WHERE id = ?')->execute([$id]);
            logAudit('delete_cms', 'CMS', 'cms_content', $id, "Deleted CMS section {$item['section_key']}");
            $_SESSION['cms_flash'] = ['success', 'Content section deleted.'];
        }
        header('Location: cms.php'); exit;
    }

    if ($action === 'toggle' && $id) {
        $db->prepare('UPDATE cms_content SET is_published = IF(is_published = 1, 0, 1) WHERE id = ?')->execute([$id]);
        logAudit('toggle_cms', 'CMS', 'cms_content', $id, 'Changed CMS publication status');
        $_SESSION['cms_flash'] = ['success', 'Publication status updated.'];
        header('Location: cms.php'); exit;
    }

    if ($action === 'update_hero_order') {
        $source = $_POST['source'] ?? '';
        $sourceId = (int)($_POST['source_id'] ?? 0);
        $sortOrder = max(0, (int)($_POST['sort_order'] ?? 0));
        if ($source === 'cms') {
            $exists = $db->prepare("SELECT COUNT(*) FROM cms_content WHERE id=? AND page_slug='home' AND content_type='hero'");
            $exists->execute([$sourceId]);
            $validTarget = (int)$exists->fetchColumn() > 0;
            $stmt = $db->prepare("UPDATE cms_content SET sort_order=? WHERE id=? AND page_slug='home' AND content_type='hero'");
            $stmt->execute([$sortOrder,$sourceId]);
        } elseif ($source === 'event') {
            $exists = $db->prepare('SELECT COUNT(*) FROM promotion_hero_banners WHERE id=?');
            $exists->execute([$sourceId]);
            $validTarget = (int)$exists->fetchColumn() > 0;
            $stmt = $db->prepare('UPDATE promotion_hero_banners SET sort_order=? WHERE id=?');
            $stmt->execute([$sortOrder,$sourceId]);
        } else {
            $stmt = null;
            $validTarget = false;
        }
        if (!$stmt || !$validTarget) {
            $_SESSION['cms_flash'] = ['error', 'Hero banner order could not be updated.'];
        } else {
            logAudit('order_hero_banner', 'CMS', $source === 'cms' ? 'cms_content' : 'promotion_hero_banner', $sourceId, "Set homepage hero order to $sortOrder");
            $_SESSION['cms_flash'] = ['success', 'Hero banner order updated.'];
        }
        header('Location: cms.php#hero-manager'); exit;
    }

    if ($action === 'toggle_hero') {
        $source = $_POST['source'] ?? '';
        $sourceId = (int)($_POST['source_id'] ?? 0);
        if ($source === 'cms') {
            $stmt = $db->prepare("UPDATE cms_content SET is_published=IF(is_published=1,0,1) WHERE id=? AND page_slug='home' AND content_type='hero'");
            $stmt->execute([$sourceId]);
            $changed = $stmt->rowCount() > 0;
        } elseif ($source === 'event') {
            $bannerStmt = $db->prepare("SELECT b.is_published,e.id event_id,e.status FROM promotion_hero_banners b JOIN event_promotions e ON e.id=b.event_promotion_id WHERE b.id=? LIMIT 1");
            $bannerStmt->execute([$sourceId]);
            $banner = $bannerStmt->fetch();
            $changed = false;
            if ($banner && (int)$banner['is_published'] === 1) {
                $db->prepare('UPDATE promotion_hero_banners SET is_published=0 WHERE id=?')->execute([$sourceId]);
                $changed = true;
            } elseif ($banner && $banner['status'] === 'active' && hasPaidEventForCms($db, (int)$banner['event_id'])) {
                $db->prepare('UPDATE promotion_hero_banners SET is_published=1 WHERE id=?')->execute([$sourceId]);
                $changed = true;
            }
        } else {
            $changed = false;
        }
        if ($changed) {
            logAudit('toggle_hero_banner', 'CMS', $source === 'cms' ? 'cms_content' : 'promotion_hero_banner', $sourceId, 'Changed homepage hero visibility');
            $_SESSION['cms_flash'] = ['success', 'Hero banner visibility updated.'];
        } else {
            $_SESSION['cms_flash'] = ['error', 'Only an active, paid Event Campaign can be shown in the homepage hero.'];
        }
        header('Location: cms.php#hero-manager'); exit;
    }

    $pageSlug = strtolower(trim($_POST['page_slug'] ?? 'home'));
    $sectionKey = strtolower(trim($_POST['section_key'] ?? ''));
    $contentType = $_POST['content_type'] ?? 'general';
    $title = trim($_POST['title'] ?? '');
    $subtitle = trim($_POST['subtitle'] ?? '');
    $content = trim($_POST['content_text'] ?? '');
    $imageUrl = trim($_POST['image_url'] ?? '');
    $buttonText = trim($_POST['button_text'] ?? '');
    $buttonUrl = trim($_POST['button_url'] ?? '');
    $sortOrder = (int)($_POST['sort_order'] ?? 0);
    $published = isset($_POST['is_published']) ? 1 : 0;

    $errors = [];
    if (!preg_match('/^[a-z0-9_-]{2,100}$/', $pageSlug)) $errors[] = 'Page slug may only contain lowercase letters, numbers, hyphens, and underscores.';
    if (!preg_match('/^[a-z0-9_-]{2,100}$/', $sectionKey)) $errors[] = 'Section key may only contain lowercase letters, numbers, hyphens, and underscores.';
    if (!in_array($contentType, $allowedTypes, true)) $errors[] = 'Select a valid content type.';
    if ($title === '' || mb_strlen($title) > 255) $errors[] = 'Title is required and must be 255 characters or fewer.';
    foreach ([['Image URL', $imageUrl], ['Button URL', $buttonUrl]] as [$label, $url]) {
        if ($url !== '' && !preg_match('~^(https?://|/|#|[a-zA-Z0-9][a-zA-Z0-9_./?&=%+-]*$)~', $url)) $errors[] = "$label is not valid.";
    }

    if ($errors) {
        $_SESSION['cms_flash'] = ['error', implode(' ', $errors)];
        $_SESSION['cms_old'] = $_POST;
        header('Location: cms.php' . ($id ? '?edit=' . $id : '?new=1')); exit;
    }

    try {
        if ($id) {
            $stmt = $db->prepare('UPDATE cms_content SET page_slug=?, section_key=?, content_type=?, title=?, subtitle=?, content_text=?, image_url=?, button_text=?, button_url=?, is_published=?, sort_order=? WHERE id=?');
            $stmt->execute([$pageSlug,$sectionKey,$contentType,$title,$subtitle?:null,$content?:null,$imageUrl?:null,$buttonText?:null,$buttonUrl?:null,$published,$sortOrder,$id]);
            $message = 'Content section updated successfully.';
            $auditAction = 'update_cms';
        } else {
            $stmt = $db->prepare('INSERT INTO cms_content (page_slug,section_key,content_type,title,subtitle,content_text,image_url,button_text,button_url,is_published,sort_order) VALUES (?,?,?,?,?,?,?,?,?,?,?)');
            $stmt->execute([$pageSlug,$sectionKey,$contentType,$title,$subtitle?:null,$content?:null,$imageUrl?:null,$buttonText?:null,$buttonUrl?:null,$published,$sortOrder]);
            $id = (int)$db->lastInsertId();
            $message = 'Content section created successfully.';
            $auditAction = 'create_cms';
        }
        logAudit($auditAction, 'CMS', 'cms_content', $id, "$auditAction: $sectionKey");
        $_SESSION['cms_flash'] = ['success', $message];
        unset($_SESSION['cms_old']);
        header('Location: cms.php'); exit;
    } catch (PDOException $e) {
        $_SESSION['cms_flash'] = ['error', $e->getCode() === '23000' ? 'That section key is already in use.' : 'Content could not be saved.'];
        $_SESSION['cms_old'] = $_POST;
        header('Location: cms.php' . ($id ? '?edit=' . $id : '?new=1')); exit;
    }
}

$flash = $_SESSION['cms_flash'] ?? null;
unset($_SESSION['cms_flash']);
$old = $_SESSION['cms_old'] ?? [];
unset($_SESSION['cms_old']);
function hasPaidEventForCms(PDO $db, int $eventId): bool {
    $stmt = $db->prepare("SELECT COUNT(*) FROM promotion_payments WHERE service_type='event_promotion' AND service_id=? AND status='paid' AND payment_method='esewa'");
    $stmt->execute([$eventId]);
    return (int)$stmt->fetchColumn() > 0;
}

$items = $db->query('SELECT * FROM cms_content ORDER BY page_slug, sort_order, id')->fetchAll();
$heroItems = [];
foreach ($items as $item) {
    if ($item['page_slug'] !== 'home' || $item['content_type'] !== 'hero') continue;
    $heroItems[] = [
        'source'=>'cms','source_id'=>(int)$item['id'],'title'=>$item['title'],'subtitle'=>$item['section_key'],
        'image_url'=>$item['image_url'],'sort_order'=>(int)$item['sort_order'],'is_visible'=>(int)$item['is_published'],
        'status'=>(int)$item['is_published'] ? 'Published' : 'Draft','event_id'=>null
    ];
}
$eventHeroItems = $db->query("SELECT b.id source_id,b.image_url,b.sort_order,b.is_published,e.id event_id,e.title,e.status,v.name venue_name
    FROM promotion_hero_banners b JOIN event_promotions e ON e.id=b.event_promotion_id JOIN venues v ON v.id=e.venue_id
    ORDER BY b.sort_order,b.id")->fetchAll();
foreach ($eventHeroItems as $item) {
    $heroItems[] = [
        'source'=>'event','source_id'=>(int)$item['source_id'],'title'=>$item['title'],'subtitle'=>$item['venue_name'],
        'image_url'=>$item['image_url'],'sort_order'=>(int)$item['sort_order'],'is_visible'=>(int)$item['is_published'],
        'status'=>ucwords(str_replace('_',' ',$item['status'])),'event_id'=>(int)$item['event_id'],
        'event_status'=>$item['status']
    ];
}
usort($heroItems, fn(array $a,array $b): int => ($a['sort_order'] <=> $b['sort_order']) ?: strcmp($a['source'].'-'.$a['source_id'],$b['source'].'-'.$b['source_id']));
$contentItems = array_values(array_filter($items, fn(array $item): bool => !($item['page_slug']==='home' && $item['content_type']==='hero')));
$editItem = null;
if (!empty($_GET['edit'])) {
    $stmt = $db->prepare('SELECT * FROM cms_content WHERE id = ?');
    $stmt->execute([(int)$_GET['edit']]);
    $editItem = $stmt->fetch() ?: null;
}
$form = array_merge($editItem ?: ['id'=>0,'page_slug'=>'home','section_key'=>'','content_type'=>'general','title'=>'','subtitle'=>'','content_text'=>'','image_url'=>'','button_text'=>'','button_url'=>'','is_published'=>1,'sort_order'=>0], $old);
$publishedCount = count(array_filter($items, fn($item) => (int)$item['is_published'] === 1));
$visibleHeroCount = count(array_filter($heroItems, fn(array $item): bool => (int)$item['is_visible'] === 1));
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>CMS & Content – MeroMaidan Admin</title>
  <link rel="stylesheet" href="../assets/css/admin.css">
  <style>
    .cms-grid{display:grid;grid-template-columns:minmax(0,1.5fr) minmax(340px,.8fr);gap:22px;align-items:start}.cms-form{padding:22px;position:sticky;top:86px}.cms-form textarea{min-height:105px;resize:vertical}.cms-actions{display:flex;gap:7px;flex-wrap:wrap}.table-scroll{overflow-x:auto}.status-dot{width:8px;height:8px;border-radius:50%;display:inline-block;background:#94a3b8}.status-dot.live{background:#1BB955}.field-row{display:grid;grid-template-columns:1fr 1fr;gap:12px}.check-row{display:flex;align-items:center;gap:9px;font-size:13px;font-weight:700;color:#334155}.empty-state{text-align:center;padding:48px 20px;color:#64748b}.help{font-size:11px;color:#94a3b8;margin-top:5px;line-height:1.5}.alert{padding:13px 16px;border-radius:10px;margin-bottom:18px;font-size:13px;font-weight:700}.alert.success{background:#f0fdf4;border:1px solid #bbf7d0;color:#166534}.alert.error{background:#fef2f2;border:1px solid #fecaca;color:#991b1b}.hero-manager{margin-bottom:22px;overflow:hidden}.hero-manager-intro{display:flex;align-items:center;justify-content:space-between;gap:18px}.hero-count{padding:7px 11px;border-radius:999px;background:#eafaf0;color:#147b3b;font-size:10px;font-weight:900}.hero-admin-list{display:grid}.hero-admin-item{display:grid;grid-template-columns:54px 180px minmax(220px,1fr) 180px auto;gap:16px;align-items:center;padding:16px 20px;border-top:1px solid #e8eef3}.hero-order-number{display:grid;place-items:center;width:38px;height:38px;border-radius:12px;background:#0f2740;color:#fff;font-size:13px;font-weight:900}.hero-thumb{width:180px;aspect-ratio:8/3;object-fit:cover;border-radius:11px;background:#e7edf2}.hero-source{display:inline-flex;margin-bottom:7px;padding:4px 7px;border-radius:999px;background:#edf2f6;color:#52677a;font-size:8px;font-weight:900;text-transform:uppercase;letter-spacing:.07em}.hero-source.event{background:#fff1e8;color:#c2410c}.hero-info h4{margin:0 0 4px;color:#0f2740;font-size:14px}.hero-info p{color:#718497;font-size:10px}.hero-order-form{display:flex;align-items:end;gap:7px}.hero-order-form label{display:grid;gap:5px;color:#64748b;font-size:8px;font-weight:900;text-transform:uppercase}.hero-order-form input{width:76px;padding:8px;border:1px solid #d8e2e9;border-radius:8px}.hero-controls{display:flex;justify-content:flex-end;gap:6px;flex-wrap:wrap}.hero-visibility{display:inline-flex;align-items:center;gap:5px;margin-top:7px;font-size:9px;font-weight:800;color:#64748b}.hero-visibility i{width:7px;height:7px;border-radius:50%;background:#94a3b8}.hero-visibility.live{color:#15803d}.hero-visibility.live i{background:#1bb955}.hero-note{padding:12px 20px;border-top:1px solid #e8eef3;background:#f8fafc;color:#64748b;font-size:10px}@media(max-width:1150px){.hero-admin-item{grid-template-columns:45px 140px 1fr}.hero-thumb{width:140px}.hero-order-form,.hero-controls{grid-column:3}}@media(max-width:1050px){.cms-grid{grid-template-columns:1fr}.cms-form{position:static}}@media(max-width:700px){.field-row{grid-template-columns:1fr}.hero-admin-item{grid-template-columns:40px 1fr}.hero-thumb{grid-column:1/-1;width:100%}.hero-info,.hero-order-form,.hero-controls{grid-column:2}.hero-manager-intro{align-items:flex-start;flex-direction:column}}
  </style>
</head>
<body><div class="admin-layout">
<aside class="admin-sidebar">
  <div class="sidebar-logo"><div><div class="sidebar-logo-text">Mero<span>Maidan</span> <span class="sidebar-badge">ADMIN</span></div><div style="font-size:10px;color:rgba(255,255,255,.4);margin-top:2px">Super Admin Panel</div></div></div>
  <nav class="sidebar-nav">
    <div class="nav-section-label">Overview</div><a href="index.php" class="nav-link"><span class="icon">📊</span> Dashboard</a><a href="reports.php" class="nav-link"><span class="icon">📈</span> Reports</a>
    <div class="nav-section-label">Management</div><a href="venues.php" class="nav-link"><span class="icon">🏟️</span> Venues</a><a href="owners.php" class="nav-link"><span class="icon">👤</span> Owners</a><a href="bookings.php" class="nav-link"><span class="icon">📅</span> Bookings</a><a href="applications.php" class="nav-link"><span class="icon">📋</span> Applications</a><a href="plans.php" class="nav-link"><span class="icon">💳</span> Commercial Services</a><a href="promotions.php" class="nav-link"><span class="icon">📣</span> Promotions</a>
    <div class="nav-section-label">Content & Governance</div><a href="cms.php" class="nav-link active"><span class="icon">📝</span> CMS & Content</a><a href="audit.php" class="nav-link"><span class="icon">🛡️</span> Audit Logs</a><a href="settings.php" class="nav-link"><span class="icon">⚙️</span> Settings</a><a href="../index.php" class="nav-link" target="_blank"><span class="icon">🌐</span> View Site</a>
  </nav>
  <div class="sidebar-footer"><div class="admin-user-row"><div class="admin-avatar"><?=htmlspecialchars(strtoupper(substr($adminName,0,2)))?></div><div class="admin-user-info"><div class="admin-user-name"><?=htmlspecialchars($adminName)?></div><div class="admin-user-role">Super Admin</div></div></div><a href="../auth/logout.php" class="btn-logout">🚪 Sign Out</a></div>
</aside>
<main class="admin-main"><div class="admin-topbar"><div class="topbar-title">Content <span>Management System</span></div><a href="?new=1" class="btn btn-green">＋ New section</a></div>
<div class="admin-content">
  <div class="page-header"><h1>Website content</h1><p>Control homepage copy, calls to action, imagery, visibility, and display order without editing code.</p></div>
  <?php if($flash): ?><div class="alert <?=htmlspecialchars($flash[0])?>"><?=htmlspecialchars($flash[1])?></div><?php endif; ?>
  <section class="data-card hero-manager" id="hero-manager">
    <div class="data-card-header hero-manager-intro"><div><h3>Homepage Hero Banners</h3><span style="font-size:11px;color:#64748b">Every CMS slide and paid Event Campaign banner, managed in one display queue</span></div><div class="hero-count"><?=$visibleHeroCount?> visible · <?=count($heroItems)?> total</div></div>
    <div class="hero-admin-list">
    <?php foreach($heroItems as $position=>$hero):
      $heroImage=(string)$hero['image_url'];
      if($heroImage!==''&&!preg_match('~^https?://~i',$heroImage))$heroImage='../'.ltrim($heroImage,'/');
      if($heroImage==='')$heroImage='https://images.unsplash.com/photo-1546519638-68e109498ffc?auto=format&fit=crop&w=800&q=75';
      $eventCanShow=$hero['source']==='event'&&($hero['event_status']??'')==='active';
    ?>
      <article class="hero-admin-item">
        <div class="hero-order-number"><?=($position+1)?></div>
        <img class="hero-thumb" src="<?=htmlspecialchars($heroImage)?>" alt="<?=htmlspecialchars($hero['title'])?> banner">
        <div class="hero-info"><span class="hero-source <?=$hero['source']==='event'?'event':''?>"><?=$hero['source']==='event'?'Paid Event Campaign':'CMS Hero Slide'?></span><h4><?=htmlspecialchars($hero['title'])?></h4><p><?=htmlspecialchars($hero['subtitle'])?> · Stored order <?=intval($hero['sort_order'])?> · <?=htmlspecialchars($hero['status'])?></p><span class="hero-visibility <?=$hero['is_visible']?'live':''?>"><i></i><?=$hero['is_visible']?'Visible in homepage hero':'Hidden from homepage hero'?></span></div>
        <form method="post" class="hero-order-form"><input type="hidden" name="csrf_token" value="<?=csrfToken()?>"><input type="hidden" name="action" value="update_hero_order"><input type="hidden" name="source" value="<?=htmlspecialchars($hero['source'])?>"><input type="hidden" name="source_id" value="<?=intval($hero['source_id'])?>"><label>Display order<input type="number" min="0" name="sort_order" value="<?=intval($hero['sort_order'])?>" required></label><button class="btn btn-navy btn-sm">Save</button></form>
        <div class="hero-controls">
          <?php if($hero['source']==='cms'):?><a class="btn btn-ghost btn-sm" href="?edit=<?=intval($hero['source_id'])?>">Edit</a><?php else:?><a class="btn btn-ghost btn-sm" href="event-promotion-edit.php?id=<?=intval($hero['event_id'])?>">Manage event</a><?php endif;?>
          <?php if($hero['source']==='cms'||$hero['is_visible']||$eventCanShow):?><form method="post"><input type="hidden" name="csrf_token" value="<?=csrfToken()?>"><input type="hidden" name="action" value="toggle_hero"><input type="hidden" name="source" value="<?=htmlspecialchars($hero['source'])?>"><input type="hidden" name="source_id" value="<?=intval($hero['source_id'])?>"><button class="btn <?=$hero['is_visible']?'btn-red':'btn-green'?> btn-sm"><?=$hero['is_visible']?'Hide':'Show'?></button></form><?php else:?><span class="badge cancelled">Activate event first</span><?php endif;?>
          <?php if($hero['source']==='cms'):?><form method="post" onsubmit="return confirm('Delete this hero slide permanently?')"><input type="hidden" name="csrf_token" value="<?=csrfToken()?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?=intval($hero['source_id'])?>"><button class="btn btn-red btn-sm">Delete</button></form><?php endif;?>
        </div>
      </article>
    <?php endforeach;?>
    <?php if(!$heroItems):?><div class="empty-state">No hero banners yet. Create a CMS Hero section or approve an Event Campaign.</div><?php endif;?>
    </div>
    <div class="hero-note"><strong>Ordering rule:</strong> lower numbers appear first. Event banners can only be shown while their mock-eSewa payment and Super Admin-approved campaign are active.</div>
  </section>
  <div class="stats-row"><div class="stat-card"><div class="stat-icon-wrap green">📝</div><div><div class="stat-num"><?=count($items)?></div><div class="stat-label">Total sections</div></div></div><div class="stat-card"><div class="stat-icon-wrap navy">🌐</div><div><div class="stat-num"><?=$publishedCount?></div><div class="stat-label">Published</div></div></div><div class="stat-card"><div class="stat-icon-wrap orange">✏️</div><div><div class="stat-num"><?=count($items)-$publishedCount?></div><div class="stat-label">Drafts</div></div></div></div>
  <div class="cms-grid">
    <div class="data-card"><div class="data-card-header"><h3>Content inventory</h3><span style="font-size:12px;color:#64748b">Homepage reads published entries instantly</span></div><div class="table-scroll"><table class="data-table"><thead><tr><th>Status</th><th>Section</th><th>Content</th><th>Order</th><th>Updated</th><th>Actions</th></tr></thead><tbody>
    <?php foreach($contentItems as $item): ?><tr><td><span class="status-dot <?=$item['is_published']?'live':''?>"></span> <?=$item['is_published']?'Live':'Draft'?></td><td><strong><?=htmlspecialchars($item['section_key'])?></strong><br><span style="font-size:11px;color:#64748b"><?=htmlspecialchars($item['page_slug'])?> · <?=htmlspecialchars($item['content_type'])?></span></td><td style="max-width:290px"><strong><?=htmlspecialchars($item['title'])?></strong><br><span style="font-size:11px;color:#64748b"><?=htmlspecialchars(mb_strimwidth((string)$item['content_text'],0,85,'…'))?></span></td><td><?=intval($item['sort_order'])?></td><td style="white-space:nowrap"><?=date('d M Y',strtotime($item['updated_at']))?></td><td><div class="cms-actions"><a class="btn btn-ghost btn-sm" href="?edit=<?=$item['id']?>">Edit</a><form method="post"><input type="hidden" name="csrf_token" value="<?=csrfToken()?>"><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?=$item['id']?>"><button class="btn btn-navy btn-sm" type="submit"><?=$item['is_published']?'Unpublish':'Publish'?></button></form><form method="post" onsubmit="return confirm('Delete this content section permanently?')"><input type="hidden" name="csrf_token" value="<?=csrfToken()?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?=$item['id']?>"><button class="btn btn-red btn-sm" type="submit">Delete</button></form></div></td></tr><?php endforeach; ?>
    <?php if(!$contentItems): ?><tr><td colspan="6" class="empty-state">No non-hero CMS sections yet.</td></tr><?php endif; ?></tbody></table></div></div>
    <div class="data-card cms-form"><h3 style="color:#0f2740;margin-bottom:5px"><?=$form['id']?'Edit section':'Create section'?></h3><p style="font-size:12px;color:#64748b;margin-bottom:18px"><?=$form['id']?'Changes appear after saving when published.':'Add a reusable content block to the website.'?></p>
      <form method="post"><input type="hidden" name="csrf_token" value="<?=csrfToken()?>"><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?=intval($form['id'])?>">
      <div class="field-row"><div class="form-group"><label class="form-label">Page</label><input class="form-input" name="page_slug" value="<?=htmlspecialchars($form['page_slug'])?>" required></div><div class="form-group"><label class="form-label">Content type</label><select class="form-select" name="content_type"><?php foreach($allowedTypes as $type): ?><option value="<?=$type?>" <?=$form['content_type']===$type?'selected':''?>><?=ucwords(str_replace('_',' ',$type))?></option><?php endforeach; ?></select></div></div>
      <div class="form-group"><label class="form-label">Section key</label><input class="form-input" name="section_key" value="<?=htmlspecialchars($form['section_key'])?>" placeholder="hero_slide_4" required><div class="help">Every published <b>Hero</b> item becomes a swipeable slide. Use <b>about_section</b>, <b>story_section</b>, or <b>cta_section</b> for the other homepage integrations.</div></div>
      <div class="form-group"><label class="form-label">Title</label><input class="form-input" name="title" maxlength="255" value="<?=htmlspecialchars($form['title'])?>" required></div>
      <div class="form-group"><label class="form-label">Subtitle / highlight</label><input class="form-input" name="subtitle" maxlength="500" value="<?=htmlspecialchars((string)$form['subtitle'])?>"></div>
      <div class="form-group"><label class="form-label">Body content</label><textarea class="form-input" name="content_text"><?=htmlspecialchars((string)$form['content_text'])?></textarea></div>
      <div class="form-group"><label class="form-label">Image URL</label><input class="form-input" name="image_url" value="<?=htmlspecialchars((string)$form['image_url'])?>" placeholder="https://… or assets/images/…"></div>
      <div class="field-row"><div class="form-group"><label class="form-label">Button label</label><input class="form-input" name="button_text" maxlength="100" value="<?=htmlspecialchars((string)$form['button_text'])?>"></div><div class="form-group"><label class="form-label">Button link</label><input class="form-input" name="button_url" value="<?=htmlspecialchars((string)$form['button_url'])?>" placeholder="#services"></div></div>
      <div class="field-row"><div class="form-group"><label class="form-label">Display order</label><input class="form-input" type="number" name="sort_order" value="<?=intval($form['sort_order'])?>"></div><div class="form-group" style="padding-top:27px"><label class="check-row"><input type="checkbox" name="is_published" value="1" <?=$form['is_published']?'checked':''?>> Published on website</label></div></div>
      <div style="display:flex;gap:10px"><button class="btn btn-green" type="submit">💾 <?=$form['id']?'Save changes':'Create section'?></button><?php if($form['id']||isset($_GET['new'])): ?><a class="btn btn-ghost" href="cms.php">Cancel</a><?php endif; ?></div></form>
    </div>
  </div>
</div></main></div></body></html>
