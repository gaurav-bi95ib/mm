<?php
require_once __DIR__ . '/../api/db.php';
requireSuperAdmin();
$db        = getDB();
$adminName = $_SESSION['superadmin_name'] ?? 'Admin';

$msg = '';
$error = '';
$config = [];
foreach($db->query("SELECT config_key,config_value FROM platform_commercial_config")->fetchAll() as $row){$config[$row['config_key']]=$row['config_value'];}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if(!verifyCsrfToken($_POST['csrf_token']??'')){http_response_code(403);die('Your session expired.');}
    $platformName=trim($_POST['platform_name']??'');$supportEmail=trim($_POST['support_email']??'');
    $cancelHours=(int)($_POST['cancellation_window_hours']??6);
    if($platformName===''||!filter_var($supportEmail,FILTER_VALIDATE_EMAIL)||!in_array($cancelHours,[2,6,24],true)){$error='Enter a valid platform name, support email and cancellation window.';}
    else{
        $values=['platform_name'=>[$platformName,'text','Public platform name'],'support_email'=>[$supportEmail,'text','Support contact email'],'mock_esewa_enabled'=>[isset($_POST['mock_esewa_enabled'])?'1':'0','boolean','Enable mock eSewa gateway'],'mock_khalti_enabled'=>[isset($_POST['mock_khalti_enabled'])?'1':'0','boolean','Enable mock Khalti gateway'],'cancellation_window_hours'=>[(string)$cancelHours,'integer','Default booking cancellation window']];
        $upsert=$db->prepare("INSERT INTO platform_commercial_config (config_key,config_value,value_type,description,updated_by) VALUES (?,?,?,?,?) ON DUPLICATE KEY UPDATE config_value=VALUES(config_value),value_type=VALUES(value_type),description=VALUES(description),updated_by=VALUES(updated_by)");
        foreach($values as $key=>$value){$upsert->execute([$key,$value[0],$value[1],$value[2],(int)$_SESSION['superadmin_id']]);$config[$key]=$value[0];}
        logAudit('update_platform_settings', 'SuperAdmin', 'system', 0, "Updated platform configuration");
        $msg = '✅ Platform configuration updated successfully!';
    }
}

$pendingCount = $db->query("SELECT COUNT(*) FROM venues WHERE status='pending'")->fetchColumn();
$appsCount    = $db->query("SELECT COUNT(*) FROM owner_applications WHERE status='new'")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Platform Settings – MeroMaidan Admin</title>
  <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body>
<div class="admin-layout">

  <aside class="admin-sidebar">
    <div class="sidebar-logo">
      <div>
        <div class="sidebar-logo-text">Mero<span>Maidan</span> <span class="sidebar-badge">ADMIN</span></div>
        <div style="font-size:10px;color:rgba(255,255,255,.4);margin-top:2px;">Super Admin Panel</div>
      </div>
    </div>
    <nav class="sidebar-nav">
      <div class="nav-section-label">Overview</div>
      <a href="index.php" class="nav-link"><span class="icon">📊</span> Dashboard</a>
      <a href="reports.php" class="nav-link"><span class="icon">📈</span> Reports</a>

      <div class="nav-section-label">Management</div>
      <a href="venues.php" class="nav-link"><span class="icon">🏟️</span> Venues <?php if($pendingCount>0): ?><span class="badge orange"><?=$pendingCount?></span><?php endif; ?></a>
      <a href="owners.php" class="nav-link"><span class="icon">👤</span> Owners</a>
      <a href="bookings.php" class="nav-link"><span class="icon">📅</span> Bookings</a>
      <a href="applications.php" class="nav-link"><span class="icon">📋</span> Applications <?php if($appsCount>0): ?><span class="badge orange"><?=$appsCount?></span><?php endif; ?></a>
      <a href="plans.php" class="nav-link"><span class="icon">💳</span> Commercial Services</a>
      <a href="recommended-promotions.php" class="nav-link"><span class="icon">📍</span> Recommended Venue</a>
      <a href="event-promotions.php" class="nav-link"><span class="icon">📣</span> Event Campaigns</a>
      <a href="cms.php" class="nav-link"><span class="icon">📝</span> CMS & Content</a>

      <div class="nav-section-label">System Governance</div>
      <a href="audit.php" class="nav-link"><span class="icon">🛡️</span> Audit Logs</a>
      <a href="settings.php" class="nav-link active"><span class="icon">⚙️</span> Settings</a>
      <a href="../index.php" class="nav-link" target="_blank"><span class="icon">🌐</span> View Site</a>
    </nav>
    <div class="sidebar-footer">
      <div class="admin-user-row">
        <div class="admin-avatar"><?= strtoupper(substr($adminName,0,2)) ?></div>
        <div class="admin-user-info">
          <div class="admin-user-name"><?= htmlspecialchars($adminName) ?></div>
          <div class="admin-user-role">Super Admin</div>
        </div>
      </div>
      <a href="../auth/logout.php" class="btn-logout">🚪 Sign Out</a>
    </div>
  </aside>

  <main class="admin-main">
    <div class="admin-topbar">
      <div class="topbar-title">Platform <span>Settings</span></div>
    </div>

    <div class="admin-content">
      <div class="page-header">
        <h1>Global Platform Configuration</h1>
        <p>Manage ecosystem defaults, payment options, and feature policies.</p>
      </div>

      <?php if ($msg): ?><div class="alert success" style="background:#f0fdf4;color:#16a34a;padding:12px;border-radius:8px;margin-bottom:16px;font-weight:700;"><?= $msg ?></div><?php endif; ?>
      <?php if ($error): ?><div class="alert error" style="background:#fef2f2;color:#b91c1c;padding:12px;border-radius:8px;margin-bottom:16px;font-weight:700;"><?=htmlspecialchars($error)?></div><?php endif; ?>

      <div class="data-card" style="max-width:640px;">
        <div class="data-card-header">
          <h3>⚙️ Ecosystem Parameters</h3>
        </div>
        <form method="POST" style="padding:24px;">
          <input type="hidden" name="csrf_token" value="<?=csrfToken()?>">
          <div class="form-group" style="margin-bottom:16px;">
            <label style="display:block;font-size:12px;font-weight:800;color:#64748b;margin-bottom:6px;">Platform Name</label>
            <input type="text" name="platform_name" class="form-input" value="<?=htmlspecialchars($config['platform_name']??'MeroMaidan')?>" required style="width:100%;padding:10px;border:1px solid #cbd5e1;border-radius:8px;">
          </div>

          <div class="form-group" style="margin-bottom:16px;">
            <label style="display:block;font-size:12px;font-weight:800;color:#64748b;margin-bottom:6px;">Support Contact Email</label>
            <input type="email" name="support_email" class="form-input" value="<?=htmlspecialchars($config['support_email']??'support@meromaidan.com')?>" required style="width:100%;padding:10px;border:1px solid #cbd5e1;border-radius:8px;">
          </div>

          <div class="form-group" style="margin-bottom:16px;">
            <label style="display:block;font-size:12px;font-weight:800;color:#64748b;margin-bottom:6px;">Supported Payment Gateways</label>
            <div style="display:flex;gap:12px;align-items:center;margin-top:6px;">
              <label><input type="checkbox" checked disabled> 💵 Cash (Pay at Venue)</label>
              <label><input type="checkbox" name="mock_esewa_enabled" value="1" <?=($config['mock_esewa_enabled']??'1')==='1'?'checked':''?>> 🟢 eSewa (Mock/Sandbox)</label>
              <label><input type="checkbox" name="mock_khalti_enabled" value="1" <?=($config['mock_khalti_enabled']??'1')==='1'?'checked':''?>> 🟣 Khalti (Mock/Sandbox)</label>
            </div>
          </div>

          <div class="form-group" style="margin-bottom:24px;">
            <label style="display:block;font-size:12px;font-weight:800;color:#64748b;margin-bottom:6px;">Default Booking Cancellation Window</label>
            <select name="cancellation_window_hours" class="form-input" style="width:100%;padding:10px;border:1px solid #cbd5e1;border-radius:8px;">
              <option value="2" <?=($config['cancellation_window_hours']??'6')==='2'?'selected':''?>>2 Hours before match</option>
              <option value="6" <?=($config['cancellation_window_hours']??'6')==='6'?'selected':''?>>6 Hours before match</option>
              <option value="24" <?=($config['cancellation_window_hours']??'6')==='24'?'selected':''?>>24 Hours before match</option>
            </select>
          </div>

          <button type="submit" class="btn btn-green" style="padding:12px 24px;font-weight:800;">💾 Save Configuration</button>
        </form>
      </div>

    </div>
  </main>
</div>
</body>
</html>
