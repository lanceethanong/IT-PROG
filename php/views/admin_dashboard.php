<?php
// ============================================================
//  admin_dashboard.php  —  EZLabs Admin Control Panel
//  Requires: repository.php (which includes bootstrap.php)
//  The events table + cancel_reason column are auto-created
//  on first load via events_table_ensure() inside db().
// ============================================================

require_once __DIR__ . '/../lib/repository.php';

// ── Auth guard ────────────────────────────────────────────────
require_role('Admin');
$sessionUser = $_SESSION['user'] ?? [];
$sessionName = (string) ($sessionUser['username'] ?? '');
$sessionId   = (string) ($sessionUser['_id']      ?? '');

// ── Active tab ────────────────────────────────────────────────
$tab          = $_GET['tab'] ?? 'overview';
$allowedTabs  = ['overview', 'users', 'labs', 'events', 'settings'];
if (!in_array($tab, $allowedTabs, true)) $tab = 'overview';

// ── Flash ─────────────────────────────────────────────────────
$flash = $_SESSION['admin_flash'] ?? null;
unset($_SESSION['admin_flash']);

// ═══════════════════════════════════════════════════════════════
//  POST HANDLERS
// ═══════════════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['_action'] ?? '';

    // ── CREATE USER ──────────────────────────────────────────
    if ($action === 'create_user') {
        $uname   = trim($_POST['username'] ?? '');
        $email   = trim($_POST['email']    ?? '');
        $pass    = trim($_POST['password'] ?? '');
        $newRole = trim($_POST['role']     ?? 'Student');
        if (!in_array($newRole, ['Student','Lab Technician','Admin'], true)) $newRole = 'Student';

        if ($uname && $email && $pass) {
            if (users_find_by_username($uname) || users_find_by_email($email)) {
                $_SESSION['admin_flash'] = ['type'=>'error','msg'=>'Username or email already exists.'];
            } else {
                users_upsert([
                    '_id'       => new_id(),
                    'email'     => $email,
                    'username'  => $uname,
                    'password'  => password_hash($pass, PASSWORD_BCRYPT),
                    'role'      => $newRole,
                    'createdAt' => now_iso(),
                    'updatedAt' => now_iso(),
                ]);
                $_SESSION['admin_flash'] = ['type'=>'success','msg'=>"User «{$uname}» created."];
            }
        } else {
            $_SESSION['admin_flash'] = ['type'=>'error','msg'=>'All fields are required.'];
        }
        redirect_to('/admin_dashboard.php?tab=users');
    }

    // ── EDIT USER ────────────────────────────────────────────
    if ($action === 'edit_user') {
        $uid2    = trim($_POST['user_id']  ?? '');
        $uname   = trim($_POST['username'] ?? '');
        $email   = trim($_POST['email']    ?? '');
        $newRole = trim($_POST['role']     ?? '');
        $pass    = trim($_POST['password'] ?? '');

        $existing = users_find_by_id($uid2);
        if ($existing && $uname && $email) {
            $updated             = $existing;
            $updated['username'] = $uname;
            $updated['email']    = $email;
            $updated['role']     = in_array($newRole, ['Student','Lab Technician','Admin'], true) ? $newRole : $existing['role'];
            $updated['updatedAt']= now_iso();
            if ($pass !== '') {
                $updated['password'] = password_hash($pass, PASSWORD_BCRYPT);
            }
            users_upsert($updated);
            $_SESSION['admin_flash'] = ['type'=>'success','msg'=>"User «{$uname}» updated."];
        } else {
            $_SESSION['admin_flash'] = ['type'=>'error','msg'=>'User not found or missing fields.'];
        }
        redirect_to('/admin_dashboard.php?tab=users');
    }

    // ── DELETE USER ──────────────────────────────────────────
    if ($action === 'delete_user') {
        $uid2 = trim($_POST['user_id'] ?? '');
        if ($uid2 && $uid2 !== $sessionId) {
            $target = users_find_by_id($uid2);
            if ($target) {
                users_delete_by_username((string) ($target['username'] ?? ''));
                $_SESSION['admin_flash'] = ['type'=>'success','msg'=>'User deleted.'];
            }
        } else {
            $_SESSION['admin_flash'] = ['type'=>'error','msg'=>'Cannot delete your own account.'];
        }
        redirect_to('/admin_dashboard.php?tab=users');
    }

    // ── CREATE LAB ──────────────────────────────────────────
    if ($action === 'create_lab') {
        $className = trim($_POST['class_name'] ?? '');
        $number    = (int) ($_POST['number'] ?? 0);
 
        if ($className && $number > 0) {
            if (labs_find_by_number($number) !== null) {
                $_SESSION['admin_flash'] = ['type'=>'error','msg'=>"Lab number {$number} already exists."];
            } else {
                labs_insert([
                    '_id'       => new_id(),
                    'class'     => $className,
                    'number'    => $number,
                    'createdAt' => now_iso(),
                    'updatedAt' => now_iso(),
                ]);
                $_SESSION['admin_flash'] = ['type'=>'success','msg'=>"Lab {$number} ({$className}) created."];
            }
        } else {
            $_SESSION['admin_flash'] = ['type'=>'error','msg'=>'Lab number and class name are required.'];
        }
        redirect_to('/admin_dashboard.php?tab=labs');
    }
 
    // ── EDIT LAB ────────────────────────────────────────────
    if ($action === 'edit_lab') {
        $labId     = trim($_POST['lab_id']     ?? '');
        $className = trim($_POST['class_name'] ?? '');
        $number    = (int) ($_POST['number'] ?? 0);
 
        if ($labId && $className && $number > 0) {
            // Check if number conflicts with another lab
            $existing = labs_find_by_number($number);
            if ($existing !== null && (string)$existing['_id'] !== $labId) {
                $_SESSION['admin_flash'] = ['type'=>'error','msg'=>"Lab number {$number} is already used by another lab."];
            } else {
                labs_update($labId, ['class' => $className, 'number' => $number]);
                $_SESSION['admin_flash'] = ['type'=>'success','msg'=>"Lab {$number} ({$className}) updated."];
            }
        } else {
            $_SESSION['admin_flash'] = ['type'=>'error','msg'=>'All fields are required.'];
        }
        redirect_to('/admin_dashboard.php?tab=labs');
    }
 
    // ── DELETE LAB ──────────────────────────────────────────
    if ($action === 'delete_lab') {
        $labId = trim($_POST['lab_id'] ?? '');
        if ($labId) {
            $lab = labs_find_by_id($labId);
            if ($lab) {
                labs_delete($labId);
                $_SESSION['admin_flash'] = ['type'=>'success','msg'=>'Lab deleted. All related reservations and events were also removed.'];
            } else {
                $_SESSION['admin_flash'] = ['type'=>'error','msg'=>'Lab not found.'];
            }
        }
        redirect_to('/admin_dashboard.php?tab=labs');
    }

    // ── CREATE EVENT ──────────────────────────────────────────
    if ($action === 'create_event') {
        $labId  = trim($_POST['lab_id']      ?? '');
        $name   = trim($_POST['name']        ?? '');
        $desc   = trim($_POST['description'] ?? '');
        $date   = trim($_POST['date']        ?? '');
        $tStart = trim($_POST['time_start']  ?? '');
        $tEnd   = trim($_POST['time_end']    ?? '');

        if ($labId && $name && $date && $tStart && $tEnd) {
            $event = events_insert([
                'lab'         => $labId,
                'name'        => $name,
                'description' => $desc,
                'date'        => $date,
                'time_start'  => $tStart,
                'time_end'    => $tEnd,
                'created_by'  => $sessionId,
            ]);
            $cancelled = event_cancel_conflicting($event);
            $msg = "Event «{$name}» scheduled.";
            if ($cancelled > 0) {
                $msg .= " {$cancelled} conflicting reservation(s) were cancelled and users notified.";
            }
            $_SESSION['admin_flash'] = ['type'=>'success','msg'=>$msg];
        } else {
            $_SESSION['admin_flash'] = ['type'=>'error','msg'=>'All fields except description are required.'];
        }
        redirect_to('/admin_dashboard.php?tab=events');
    }

    // ── EDIT EVENT ────────────────────────────────────────────
    if ($action === 'edit_event') {
        $evId   = trim($_POST['event_id']    ?? '');
        $labId  = trim($_POST['lab_id']      ?? '');
        $name   = trim($_POST['name']        ?? '');
        $desc   = trim($_POST['description'] ?? '');
        $date   = trim($_POST['date']        ?? '');
        $tStart = trim($_POST['time_start']  ?? '');
        $tEnd   = trim($_POST['time_end']    ?? '');

        if ($evId && $labId && $name && $date && $tStart && $tEnd) {
            events_update($evId, [
                'lab'         => $labId,
                'name'        => $name,
                'description' => $desc,
                'date'        => $date,
                'time_start'  => $tStart,
                'time_end'    => $tEnd,
            ]);
            // Re-run conflict cancellation with updated window.
            $updatedEvent = events_find_by_id($evId);
            $cancelled = $updatedEvent ? event_cancel_conflicting($updatedEvent) : 0;
            $msg = "Event «{$name}» updated.";
            if ($cancelled > 0) {
                $msg .= " {$cancelled} newly conflicting reservation(s) cancelled.";
            }
            $_SESSION['admin_flash'] = ['type'=>'success','msg'=>$msg];
        } else {
            $_SESSION['admin_flash'] = ['type'=>'error','msg'=>'All fields except description are required.'];
        }
        redirect_to('/admin_dashboard.php?tab=events');
    }

    // ── DELETE EVENT ──────────────────────────────────────────
    if ($action === 'delete_event') {
        $evId = trim($_POST['event_id'] ?? '');
        if ($evId && events_delete($evId)) {
            $_SESSION['admin_flash'] = ['type'=>'success','msg'=>'Event deleted. Previously cancelled reservations remain cancelled.'];
        } else {
            $_SESSION['admin_flash'] = ['type'=>'error','msg'=>'Event not found.'];
        }
        redirect_to('/admin_dashboard.php?tab=events');
    }

    // ── SYSTEM SETTINGS ───────────────────────────────────────
    if ($action === 'save_settings') {
        $_SESSION['sys_settings'] = [
            'site_name'      => trim($_POST['site_name']      ?? 'EZLabs'),
            'grace_minutes'  => max(1, (int) ($_POST['grace_minutes']  ?? 10)),
            'advance_days'   => max(1, (int) ($_POST['advance_days']   ?? 7)),
            'cancel_minutes' => max(1, (int) ($_POST['cancel_minutes'] ?? 30)),
            'max_seats'      => max(1, (int) ($_POST['max_seats']      ?? 35)),
        ];
        $_SESSION['admin_flash'] = ['type'=>'success','msg'=>'Settings saved.'];
        redirect_to('/admin_dashboard.php?tab=settings');
    }
}

// ═══════════════════════════════════════════════════════════════
//  FETCH DATA
// ═══════════════════════════════════════════════════════════════

// ── Users list ───────────────────────────────────────────────
$search     = trim($_GET['q']           ?? '');
$roleFilter = trim($_GET['role_filter'] ?? '');
$page       = max(1, (int) ($_GET['p'] ?? 1));
$perPage    = 10;

$allUsers = users_all();
$filtered = array_filter($allUsers, function (array $u) use ($search, $roleFilter): bool {
    $matchSearch = $search === ''
        || stripos($u['username'], $search) !== false
        || stripos($u['email'],    $search) !== false;
    $matchRole = $roleFilter === '' || $u['role'] === $roleFilter;
    return $matchSearch && $matchRole;
});
$filtered    = array_values($filtered);
// Sort newest first
usort($filtered, fn($a, $b) => strcmp((string)($b['createdAt']??''), (string)($a['createdAt']??'')));

$totalUsers = count($filtered);
$totalPages = max(1, (int) ceil($totalUsers / $perPage));
$page       = min($page, $totalPages);
$users      = array_slice($filtered, ($page - 1) * $perPage, $perPage);

// ── Events list ──────────────────────────────────────────────
$allEvents  = events_all();
$allLabs    = labs_all();
// Build lab lookup: id => label
$labById = [];
foreach ($allLabs as $l) {
    $labById[(string)$l['_id']] = 'Lab ' . $l['number'] . ' (' . $l['class'] . ')';
}

// ── Overview stats ───────────────────────────────────────────
$statStudents = count(array_filter($allUsers, fn($u) => $u['role'] === 'Student'));
$statTechs    = count(array_filter($allUsers, fn($u) => $u['role'] === 'Lab Technician'));
$statAdmins   = count(array_filter($allUsers, fn($u) => $u['role'] === 'Admin'));
$statLabs     = count($allLabs);
$allRes       = reservations_all();
$statRes      = count(array_filter($allRes, fn($r) => reservation_status($r) === 'Scheduled'));
$statEvents   = count($allEvents);

// ── System settings ───────────────────────────────────────────
$settings = $_SESSION['sys_settings'] ?? [
    'site_name'      => 'EZLabs',
    'grace_minutes'  => 10,
    'advance_days'   => 7,
    'cancel_minutes' => 30,
    'max_seats'      => 35,
];

// ── Time options for event form (7 AM – 10 PM in 30-min steps) ─
$timeOptions = [];
for ($h = 7; $h <= 22; $h++) {
    foreach ([0, 30] as $m) {
        if ($h === 22 && $m === 30) break;
        $ampm   = $h < 12 ? 'AM' : 'PM';
        $hour12 = $h % 12 ?: 12;
        $timeOptions[] = sprintf('%d:%02d %s', $hour12, $m, $ampm);
    }
}

?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Admin Dashboard — EZLabs</title>
  <style>
    /* ═══ RESET ══════════════════════════════════════════════ */
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    :root {
      /* Primary brand colors 
      --primary:       #22543d;    
      --primary-light: #e6f4ea;    
      --primary-dark:  #059669;    
      
      /* Backgrounds */
      --bg:            #f9fafb;   
      --surface:       #ffffff;    
      --surface2:      #f0f0f0;    
      
      /* Borders & dividers */
      --border:        #e5e7eb;    
      
      /* Text colors */
      --text:          #22543d;    
      --muted:         #6b7280;    
      
      /* Status colors */
      --danger:        #f87171;    
      --success:       #16a34a;    
      --warn:          #fbbf24;    
      --event:         #7c3aed;    
      
      /* Accent colors */
      --accent:        #22543d;    
      --accent2:       #059669;    
      
      /* Sizing */
      --radius:        6px;        
      --sidebar-w:     240px;
      --font:          'Arial', 'Segoe UI', system-ui, sans-serif;
      --mono:          'Courier New', monospace;
    }

    @import url('https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;600&display=swap');
    /* ═══ BODY & LAYOUT ═══════════════════════════════════════════ */
html { font-size: 15px; }
    body { 
      font-family: var(--font); 
      background: var(--bg); 
      color: var(--text); 
      min-height: 100vh; 
      display: flex; 
      flex-direction: column; 
    }

    ::-webkit-scrollbar { width: 6px; height: 6px; }
    ::-webkit-scrollbar-track { background: var(--bg); }
    ::-webkit-scrollbar-thumb { background: var(--border); border-radius: 3px; }

    .app { display: flex; height: 100vh; overflow: hidden; }
    .main { flex: 1; display: flex; flex-direction: column; overflow: hidden; }
    .content { flex: 1; overflow-y: auto; padding: 28px 32px; background: var(--bg); }

    /* ─── Sidebar ────────────────────────��──────────────────── */
    .sidebar { 
      width: var(--sidebar-w); 
      background: var(--surface); 
      border-right: 1px solid var(--border); 
      display: flex; 
      flex-direction: column; 
      flex-shrink: 0; 
      overflow-y: auto; 
    }

    .sidebar-logo { 
      padding: 20px 24px 16px; 
      border-bottom: 1px solid var(--border); 
    }

    .sidebar-logo h1 { 
      font-size: 1.35rem; 
      font-weight: 700; 
      color: var(--primary); 
      letter-spacing: -.5px; 
    }

    .sidebar-logo span { 
      font-size: .7rem; 
      text-transform: uppercase; 
      letter-spacing: 2px; 
      color: var(--muted); 
      font-weight: 500; 
    }

    .sidebar-nav { padding: 12px 0; flex: 1; }

    .nav-section-label { 
      font-size: .65rem; 
      text-transform: uppercase; 
      letter-spacing: 1.5px; 
      color: var(--muted); 
      padding: 14px 20px 6px; 
      font-weight: 600; 
    }

    .nav-item { 
      display: flex; 
      align-items: center; 
      gap: 12px; 
      padding: 10px 20px; 
      text-decoration: none; 
      color: var(--muted); 
      font-size: .875rem; 
      font-weight: 500; 
      border-left: 3px solid transparent;
      transition: all 0.2s;
    }

    .nav-item:hover { 
      color: var(--text); 
      background: rgba(34, 84, 61, 0.06); 
    }

    .nav-item.active { 
      color: var(--primary); 
      background: rgba(34, 84, 61, 0.1); 
      border-left-color: var(--primary); 
    }

    .nav-item .icon { 
      font-size: 1.1rem; 
      width: 20px; 
      text-align: center; 
    }

    .sidebar-footer { 
      border-top: 1px solid var(--border); 
      padding: 14px 20px; 
    }

    .sidebar-footer a { 
      display: flex; 
      align-items: center; 
      gap: 10px; 
      color: var(--danger); 
      text-decoration: none; 
      font-size: .85rem; 
      font-weight: 500; 
      opacity: .8; 
      transition: opacity .15s; 
    }

    .sidebar-footer a:hover { opacity: 1; }

    /* ─── Top bar ───────────────────────────────────────────── */
    .topbar { 
      height: 60px; 
      background: var(--primary);
      border-bottom: 1px solid var(--border); 
      display: flex; 
      align-items: center; 
      padding: 0 28px; 
      gap: 16px; 
      flex-shrink: 0; 
      color: white;
    }

    .topbar-title { 
      font-size: 1rem; 
      font-weight: 600; 
      flex: 1; 
      color: white;
    }

    .topbar-badge { 
      background: rgba(255, 255, 255, 0.2); 
      color: white; 
      font-size: .7rem; 
      font-weight: 600; 
      padding: 3px 10px; 
      border-radius: 99px; 
      border: 1px solid rgba(255, 255, 255, 0.3);
    }

    .topbar-user { 
      display: flex; 
      align-items: center; 
      gap: 8px; 
      font-size: .8rem; 
      color: rgba(255, 255, 255, 0.9);
    }

    .topbar-avatar { 
      width: 32px; 
      height: 32px; 
      border-radius: 50%; 
      background: rgba(255, 255, 255, 0.2); 
      display: flex; 
      align-items: center; 
      justify-content: center;
      color: white;
      font-weight: bold;
    }

    /* ═══ FLASH MESSAGES ════════════════════════════════════════ */
    .flash { 
      display: flex; 
      align-items: flex-start; 
      gap: 10px; 
      padding: 12px 18px; 
      border-radius: var(--radius); 
      margin-bottom: 20px; 
      font-size: .875rem; 
      font-weight: 500; 
      animation: slideDown .3s ease-out;
    }

    @keyframes slideDown { 
      from { opacity: 0; transform: translateY(-8px); } 
      to { opacity: 1; transform: none; } 
    }

    .flash.success { 
      background: rgba(22, 163, 74, 0.12); 
      border: 1px solid rgba(22, 163, 74, 0.3); 
      color: var(--success); 
    }

    .flash.error { 
      background: rgba(248, 113, 113, 0.12); 
      border: 1px solid rgba(248, 113, 113, 0.3); 
      color: var(--danger); 
    }

    /* ═══ PAGE HEADER ════════════════════════════════════════ */
    .page-header { margin-bottom: 24px; }
    .page-header h2 { 
      font-size: 1.5rem; 
      font-weight: 700; 
      letter-spacing: -.4px; 
      color: var(--text);
    }
    .page-header p { 
      color: var(--muted); 
      font-size: .875rem; 
      margin-top: 4px; 
    }

    /* ═══ STAT CARDS ════════════════════════════════════════ */
    .stats-grid { 
      display: grid; 
      grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); 
      gap: 14px; 
      margin-bottom: 24px; 
    }

    .stat-card { 
      background: var(--surface); 
      border: 1px solid var(--border); 
      border-radius: var(--radius); 
      padding: 18px 20px; 
      transition: border-color .2s; 
    }

    .stat-card:hover { border-color: var(--primary); }

    .stat-label { 
      font-size: .65rem; 
      text-transform: uppercase; 
      letter-spacing: 1.2px; 
      color: var(--muted); 
      font-weight: 600; 
    }

    .stat-val { 
      font-size: 2rem; 
      font-weight: 700; 
      margin-top: 6px; 
      font-family: var(--mono); 
      color: var(--primary); 
    }

    .stat-sub { 
      font-size: .72rem; 
      color: var(--muted); 
      margin-top: 3px; 
    }

    /* ═══ CARD ══════════════════════════════════════════════ */
    .card { 
      background: var(--surface); 
      border: 1px solid var(--border); 
      border-radius: var(--radius); 
      padding: 22px 24px; 
      margin-bottom: 20px; 
    }

    .card-title { 
      font-size: .95rem; 
      font-weight: 700; 
      margin-bottom: 16px; 
      display: flex; 
      align-items: center; 
      gap: 8px; 
      color: var(--text);
    }

    .card-title .icon { color: var(--primary); }

    /* ═══ TOOLBAR ════════════════════════════════════════════ */
    .toolbar { 
      display: flex; 
      gap: 10px; 
      align-items: center; 
      flex-wrap: wrap; 
      margin-bottom: 18px; 
    }

    .toolbar input[type=text], 
    .toolbar select { 
      background: var(--surface2); 
      border: 1px solid var(--border); 
      color: var(--text); 
      padding: 8px 14px; 
      border-radius: 6px; 
      font-size: .85rem; 
      font-family: var(--font);
    }

    .toolbar input[type=text]:focus, 
    .toolbar select:focus { 
      border-color: var(--primary); 
      outline: none;
    }

    .toolbar input[type=text] { min-width: 200px; }

    /* ═══ BUTTONS ════════════════════════════════════════════ */
    .btn { 
      display: inline-flex; 
      align-items: center; 
      gap: 6px; 
      padding: 8px 16px; 
      border-radius: 6px; 
      font-size: .84rem; 
      font-weight: 600; 
      font-family: var(--font); 
      border: 1px solid transparent;
      cursor: pointer;
      transition: all 0.2s;
    }

    .btn-primary { 
      background: var(--primary); 
      color: white; 
      border-color: var(--primary); 
    }

    .btn-primary:hover { 
      background: #1a3d2a;
      opacity: 0.9;
    }

    .btn-danger { 
      background: rgba(248, 113, 113, 0.15); 
      color: var(--danger); 
      border-color: rgba(248, 113, 113, 0.3); 
    }

    .btn-danger:hover { 
      background: rgba(248, 113, 113, 0.25); 
    }

    .btn-ghost { 
      background: transparent; 
      color: var(--muted); 
      border-color: var(--border); 
    }

    .btn-ghost:hover { 
      color: var(--text); 
      border-color: var(--muted); 
      background: var(--surface2);
    }

    .btn-event { 
      background: rgba(124, 58, 237, 0.15); 
      color: #7c3aed; 
      border-color: rgba(124, 58, 237, 0.3); 
    }

    .btn-event:hover { 
      background: rgba(124, 58, 237, 0.25); 
    }

    .btn-sm { padding: 5px 10px; font-size: .78rem; }

    /* ═══ TABLE ═════════════════════════════════════════════ */
    .table-wrap { 
      overflow-x: auto; 
      border-radius: var(--radius); 
      border: 1px solid var(--border); 
    }

    table { 
      width: 100%; 
      border-collapse: collapse; 
      font-size: .84rem; 
    }

    thead tr { background: var(--surface2); }

    thead th { 
      padding: 11px 16px; 
      text-align: left; 
      font-size: .7rem; 
      text-transform: uppercase; 
      letter-spacing: 1px; 
      color: var(--muted); 
      font-weight: 600; 
      white-space: nowrap; 
    }

    tbody tr { 
      border-top: 1px solid var(--border); 
      transition: background .1s; 
    }

    tbody tr:hover { background: rgba(34, 84, 61, 0.04); }

    tbody td { 
      padding: 11px 16px; 
      color: var(--text); 
      vertical-align: middle; 
    }

    /* ═══ BADGES ════════════════════════════════════════════ */
    .role-badge { 
      display: inline-block; 
      padding: 2px 10px; 
      border-radius: 99px; 
      font-size: .7rem; 
      font-weight: 700; 
      text-transform: uppercase; 
      letter-spacing: .8px; 
    }

    .role-Student { 
      background: rgba(34, 84, 61, 0.15); 
      color: var(--primary); 
      border: 1px solid rgba(34, 84, 61, 0.3); 
    }

    .role-Lab-Technician { 
      background: rgba(251, 191, 36, 0.15); 
      color: var(--warn); 
      border: 1px solid rgba(251, 191, 36, 0.3); 
    }

    .role-Admin { 
      background: rgba(248, 113, 113, 0.15); 
      color: var(--danger); 
      border: 1px solid rgba(248, 113, 113, 0.3); 
    }

    .event-badge { 
      display: inline-block; 
      padding: 2px 10px; 
      border-radius: 99px; 
      font-size: .7rem; 
      font-weight: 700; 
      background: rgba(124, 58, 237, 0.15); 
      color: #7c3aed; 
      border: 1px solid rgba(124, 58, 237, 0.25); 
    }

    /* ═══ FORM ══════════════════════════════════════════════ */
    .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
    .form-grid.three { grid-template-columns: 1fr 1fr 1fr; }
    .form-group { display: flex; flex-direction: column; gap: 6px; }
    .form-group.full { grid-column: 1 / -1; }

    label { 
      font-size: .78rem; 
      font-weight: 600; 
      color: var(--muted); 
      text-transform: uppercase; 
      letter-spacing: .8px; 
    }

    .form-control { 
      background: var(--surface2); 
      border: 1px solid var(--border); 
      color: var(--text); 
      padding: 10px 14px; 
      border-radius: 6px; 
      font-size: .88rem; 
      font-family: var(--font); 
      outline: none;
    }

    .form-control:focus { 
      border-color: var(--primary); 
      box-shadow: 0 0 0 2px rgba(34, 84, 61, 0.1);
    }

    select.form-control option { background: var(--surface2); }
    textarea.form-control { resize: vertical; min-height: 80px; }

    .form-hint { 
      font-size: .73rem; 
      color: var(--muted); 
      margin-top: 2px; 
    }

    .form-actions { display: flex; gap: 10px; margin-top: 20px; }

    /* ═══ MODAL ═════════════════════════════════════════════ */
    .modal-overlay { 
      position: fixed; 
      inset: 0; 
      background: rgba(0, 0, 0, 0.5); 
      backdrop-filter: blur(4px); 
      z-index: 200; 
      display: none; 
      align-items: center; 
      justify-content: center; 
    }

    .modal-overlay.open { display: flex; }

    .modal { 
      background: var(--surface); 
      border: 1px solid var(--border); 
      border-radius: 14px; 
      padding: 28px 30px; 
      width: 100%; 
      max-width: 540px; 
      max-height: 92vh; 
      overflow-y: auto; 
      animation: modalIn .3s ease-out;
    }

    @keyframes modalIn { 
      from { opacity: 0; transform: scale(0.95); } 
      to { opacity: 1; transform: none; } 
    }

    .modal-header { 
      display: flex; 
      align-items: center; 
      justify-content: space-between; 
      margin-bottom: 22px; 
    }

    .modal-header h3 { 
      font-size: 1.1rem; 
      font-weight: 700; 
      color: var(--text);
    }

    .modal-close { 
      background: none; 
      border: none; 
      cursor: pointer; 
      color: var(--muted); 
      font-size: 1.4rem; 
      line-height: 1; 
      transition: color .15s; 
    }

    .modal-close:hover { color: var(--text); }

    /* ═══ PAGINATION ════════════════════════════════════════ */
    .pagination { 
      display: flex; 
      gap: 6px; 
      align-items: center; 
      margin-top: 16px; 
      flex-wrap: wrap; 
    }

    .pagination a, 
    .pagination span { 
      padding: 6px 12px; 
      border-radius: 6px; 
      font-size: .8rem; 
      font-weight: 600; 
      text-decoration: none; 
    }

    .pagination a { 
      background: var(--surface2); 
      color: var(--muted); 
      border: 1px solid var(--border); 
      transition: all .15s; 
    }

    .pagination a:hover { 
      color: var(--text); 
      border-color: var(--muted); 
      background: var(--primary);
      color: white;
    }

    .pagination span { 
      background: var(--primary); 
      color: white; 
      border: 1px solid var(--primary); 
    }

    /* ═══ SETTINGS ══════════════════════════════════════════ */
    .settings-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }

    .settings-note { 
      background: rgba(34, 84, 61, 0.08); 
      border: 1px solid rgba(34, 84, 61, 0.2); 
      border-radius: 8px; 
      padding: 12px 16px; 
      font-size: .8rem; 
      color: var(--muted); 
      margin-bottom: 20px; 
    }

    /* ═══ EVENT CARD ════════════════════════════════════════ */
    .event-list { display: grid; gap: 14px; }

    .event-item { 
      background: var(--surface2); 
      border: 1px solid var(--border); 
      border-radius: var(--radius); 
      padding: 18px 20px; 
      display: flex; 
      gap: 16px; 
      align-items: flex-start; 
      transition: border-color .2s;
    }

    .event-item:hover { border-color: #7c3aed; }

    .event-item-meta { flex: 1; min-width: 0; }

    .event-item-name { 
      font-weight: 700; 
      font-size: .95rem; 
      margin-bottom: 4px; 
      color: var(--text);
    }

    .event-item-desc { 
      font-size: .82rem; 
      color: var(--muted); 
      margin-bottom: 10px; 
      white-space: pre-wrap; 
      word-break: break-word; 
    }

    .event-item-tags { display: flex; gap: 8px; flex-wrap: wrap; }

    .event-tag { 
      font-size: .72rem; 
      padding: 2px 9px; 
      border-radius: 99px; 
      font-weight: 600; 
    }

    .event-tag-lab { 
      background: rgba(34, 84, 61, 0.1); 
      color: var(--primary); 
      border: 1px solid rgba(34, 84, 61, 0.25); 
    }

    .event-tag-date { 
      background: rgba(124, 58, 237, 0.1); 
      color: #7c3aed; 
      border: 1px solid rgba(124, 58, 237, 0.25); 
    }

    .event-tag-time { 
      background: rgba(251, 191, 36, 0.1); 
      color: var(--warn); 
      border: 1px solid rgba(251, 191, 36, 0.25); 
    }

    .event-actions { display: flex; flex-direction: column; gap: 8px; flex-shrink: 0; }

    /* ═══ LAB CARDS ═════════════���════════════════════════════ */
    .lab-item {
      background: var(--surface);
      border: 1px solid var(--border);
      border-radius: var(--radius);
      padding: 18px 20px;
      display: flex;
      gap: 16px;
      align-items: flex-start;
      transition: all .2s;
    }

    .lab-item:hover {
      border-color: var(--primary);
      box-shadow: 0 4px 12px rgba(34, 84, 61, 0.1);
    }

    .lab-item-meta { flex: 1; }

    .lab-item-name {
      font-weight: 700;
      font-size: .95rem;
      margin-bottom: 4px;
      color: var(--text);
    }

    .lab-item-tags { display: flex; gap: 8px; flex-wrap: wrap; }

    .lab-tag {
      font-size: .72rem;
      padding: 2px 9px;
      border-radius: 99px;
      font-weight: 600;
    }

    .lab-tag-class {
      background: rgba(34, 84, 61, 0.1);
      color: var(--primary);
      border: 1px solid rgba(34, 84, 61, 0.25);
    }

    .lab-actions { display: flex; flex-direction: column; gap: 8px; flex-shrink: 0; }

    .lab-number-badge {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      width: 48px;
      height: 48px;
      border-radius: 10px;
      background: rgba(34, 84, 61, 0.15);
      border: 1px solid rgba(34, 84, 61, 0.25);
      font-weight: 700;
      color: var(--primary);
    }

    /* ═══ PLACEHOLDER ════════════════════════════════════════ */
    .placeholder-section { 
      background: var(--surface); 
      border: 1px dashed var(--border); 
      border-radius: var(--radius); 
      padding: 48px 28px; 
      text-align: center; 
      color: var(--muted); 
    }

    .placeholder-section .ph-icon { 
      font-size: 2.5rem; 
      margin-bottom: 12px; 
      opacity: .5; 
    }

    .placeholder-section h3 { 
      font-size: 1.05rem; 
      font-weight: 600; 
      margin-bottom: 8px; 
      color: var(--text); 
    }

    .placeholder-section p { 
      font-size: .85rem; 
      max-width: 380px; 
      margin: 0 auto; 
    }

    .coming-badge { 
      display: inline-block; 
      margin-top: 14px; 
      padding: 4px 14px; 
      border-radius: 99px; 
      font-size: .7rem; 
      font-weight: 700; 
      text-transform: uppercase; 
      letter-spacing: 1px; 
      background: rgba(34, 84, 61, 0.15);
      color: var(--primary);
    }

    /* ═══ RESPONSIVE ════════════════════════════════════════ */
    @media (max-width: 768px) {
      .sidebar { width: 60px; }
      .sidebar-logo h1, .sidebar-logo span, .nav-item span, .nav-section-label, .sidebar-footer a span { display: none; }
      .nav-item { padding: 12px; justify-content: center; }
      .content { padding: 16px 14px; }
      .form-grid, .form-grid.three, .settings-grid { grid-template-columns: 1fr; }
      .event-item { flex-direction: column; }
      .event-actions { flex-direction: row; }
      .topbar { flex-wrap: wrap; }
    }
  </style>
</head>
<body>

<!-- ════════════════════════════════════════════════════════════
     MODALS — Users
════════════════════════════════════════════════════════════ -->

<!-- Create User -->
<div class="modal-overlay" id="modal-create-user">
  <div class="modal">
    <div class="modal-header">
      <h3>Create New User</h3>
      <button class="modal-close" onclick="closeModal('modal-create-user')">&times;</button>
    </div>
    <form method="POST" action="?tab=users">
      <input type="hidden" name="_action" value="create_user" />
      <div class="form-grid">
        <div class="form-group">
          <label>Username</label>
          <input class="form-control" type="text" name="username" required placeholder="e.g. jdelacruz" />
        </div>
        <div class="form-group">
          <label>Email</label>
          <input class="form-control" type="email" name="email" required placeholder="user@dlsu.edu.ph" />
        </div>
        <div class="form-group">
          <label>Password</label>
          <input class="form-control" type="password" name="password" required minlength="8" />
        </div>
        <div class="form-group">
          <label>Role</label>
          <select class="form-control" name="role">
            <option value="Student">Student</option>
            <option value="Lab Technician">Lab Technician</option>
            <option value="Admin">Admin</option>
          </select>
        </div>
      </div>
      <div class="form-actions">
        <button type="submit" class="btn btn-primary">Create User</button>
        <button type="button" class="btn btn-ghost" onclick="closeModal('modal-create-user')">Cancel</button>
      </div>
    </form>
  </div>
</div>

<!-- Edit User -->
<div class="modal-overlay" id="modal-edit-user">
  <div class="modal">
    <div class="modal-header">
      <h3 style="display:flex;align-items:center;gap:8px;"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>Edit User</h3>
      <button class="modal-close" onclick="closeModal('modal-edit-user')">&times;</button>
    </div>
    <form method="POST" action="?tab=users">
      <input type="hidden" name="_action" value="edit_user" />
      <input type="hidden" name="user_id" id="edit-user-id" />
      <div class="form-grid">
        <div class="form-group">
          <label>Username</label>
          <input class="form-control" type="text" name="username" id="edit-username" required />
        </div>
        <div class="form-group">
          <label>Email</label>
          <input class="form-control" type="email" name="email" id="edit-email" required />
        </div>
        <div class="form-group">
          <label>Role</label>
          <select class="form-control" name="role" id="edit-role">
            <option value="Student">Student</option>
            <option value="Lab Technician">Lab Technician</option>
            <option value="Admin">Admin</option>
          </select>
        </div>
        <div class="form-group">
          <label>New Password <span style="font-weight:400;text-transform:none;">(blank = keep)</span></label>
          <input class="form-control" type="password" name="password" minlength="8" />
        </div>
      </div>
      <div class="form-actions">
        <button type="submit" class="btn btn-primary">Save Changes</button>
        <button type="button" class="btn btn-ghost" onclick="closeModal('modal-edit-user')">Cancel</button>
      </div>
    </form>
  </div>
</div>

<!-- Delete User -->
<div class="modal-overlay" id="modal-delete-user">
  <div class="modal" style="max-width:400px;text-align:center;">
    <div style="display:flex;justify-content:center;margin-bottom:12px;"><svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="var(--danger)" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg></div>
    <h3 style="margin-bottom:8px;font-size:1.1rem;">Delete this user?</h3>
    <p style="color:var(--muted);font-size:.85rem;margin-bottom:22px;">
      All their reservations will also be permanently removed. This cannot be undone.
    </p>
    <form method="POST" action="?tab=users">
      <input type="hidden" name="_action" value="delete_user" />
      <input type="hidden" name="user_id" id="delete-user-id" />
      <div style="display:flex;gap:10px;justify-content:center;">
        <button type="submit" class="btn btn-danger">Yes, Delete</button>
        <button type="button" class="btn btn-ghost" onclick="closeModal('modal-delete-user')">Cancel</button>
      </div>
    </form>
  </div>
</div>

<!-- ════════════════════════════════════════════════════
     MODALS — Labs
════════════════════════════════════════════════════ -->
<div class="modal-overlay" id="modal-create-lab">
  <div class="modal">
    <div class="modal-header"><h3>Add New Laboratory</h3><button class="modal-close" onclick="closeModal('modal-create-lab')">&times;</button></div>
    <p style="font-size:.82rem;color:var(--muted);margin-bottom:18px;">
      Labs have a fixed 7×5 seat grid (35 seats). Each seat can be reserved per 30-minute time slot from 7:00 AM to 7:00 PM.
    </p>
    <form method="POST" action="?tab=labs">
      <input type="hidden" name="_action" value="create_lab" />
      <div class="form-grid">
        <div class="form-group">
          <label>Lab Number</label>
          <input class="form-control" type="number" name="number" min="1" max="99" required placeholder="e.g. 6" />
          <span class="form-hint">Must be unique across all labs.</span>
        </div>
        <div class="form-group">
          <label>Class / Subject Code</label>
          <input class="form-control" type="text" name="class_name" required placeholder="e.g. CCPROG3" maxlength="50" />
          <span class="form-hint">Subject code associated with this lab.</span>
        </div>
      </div>
      <div style="margin-top:16px;">
        <label style="margin-bottom:8px;display:block;">Seat Grid Preview (7 rows × 5 columns)</label>
        <div class="seat-grid-preview">
          <?php for ($r = 1; $r <= 7; $r++): ?>
            <div class="seat-row">
              <?php for ($c = 1; $c <= 5; $c++): ?>
                <div class="seat-cell"><?= (($r-1)*5)+$c ?></div>
              <?php endfor; ?>
            </div>
          <?php endfor; ?>
        </div>
      </div>
      <div class="form-actions">
        <button type="submit" class="btn btn-lab">Add Laboratory</button>
        <button type="button" class="btn btn-ghost" onclick="closeModal('modal-create-lab')">Cancel</button>
      </div>
    </form>
  </div>
</div>
 
<div class="modal-overlay" id="modal-edit-lab">
  <div class="modal">
    <div class="modal-header"><h3>Edit Laboratory</h3><button class="modal-close" onclick="closeModal('modal-edit-lab')">&times;</button></div>
    <form method="POST" action="?tab=labs">
      <input type="hidden" name="_action" value="edit_lab" />
      <input type="hidden" name="lab_id" id="edit-lab-id" />
      <div class="form-grid">
        <div class="form-group">
          <label>Lab Number</label>
          <input class="form-control" type="number" name="number" id="edit-lab-number" min="1" max="99" required />
          <span class="form-hint">Changing this may affect reservation display names.</span>
        </div>
        <div class="form-group">
          <label>Class / Subject Code</label>
          <input class="form-control" type="text" name="class_name" id="edit-lab-class" required maxlength="50" />
        </div>
      </div>
      <div class="form-actions">
        <button type="submit" class="btn btn-primary">Save Changes</button>
        <button type="button" class="btn btn-ghost" onclick="closeModal('modal-edit-lab')">Cancel</button>
      </div>
    </form>
  </div>
</div>
 
<div class="modal-overlay" id="modal-delete-lab">
  <div class="modal" style="max-width:420px;text-align:center;">
    <div style="display:flex;justify-content:center;margin-bottom:12px;">⚠️</div>
    <h3 style="margin-bottom:8px;font-size:1.1rem;">Delete this laboratory?</h3>
    <p style="color:var(--muted);font-size:.85rem;margin-bottom:4px;">
      This will permanently delete <strong id="delete-lab-name" style="color:var(--text);"></strong>.
    </p>
    <p style="color:var(--danger);font-size:.82rem;margin-bottom:22px;">All reservations, seat assignments, and events for this lab will also be deleted.</p>
    <form method="POST" action="?tab=labs">
      <input type="hidden" name="_action" value="delete_lab" />
      <input type="hidden" name="lab_id" id="delete-lab-id" />
      <div style="display:flex;gap:10px;justify-content:center;">
        <button type="submit" class="btn btn-danger">Yes, Delete Lab</button>
        <button type="button" class="btn btn-ghost" onclick="closeModal('modal-delete-lab')">Cancel</button>
      </div>
    </form>
  </div>
</div>

<!-- ════════════════════════════════════════════════════════════
     MODALS — Events
════════════════════════════════════════════════════════════ -->

<!-- Create Event -->
<div class="modal-overlay" id="modal-create-event">
  <div class="modal">
    <div class="modal-header">
      <h3>Schedule New Event</h3>
      <button class="modal-close" onclick="closeModal('modal-create-event')">&times;</button>
    </div>
    <p style="font-size:.82rem;color:var(--muted);margin-bottom:18px;">
      All seats in the selected lab will be blocked for the chosen time window.
      Any existing Scheduled reservations that overlap will be automatically cancelled
      and the description below will be shown to affected students as the reason.
    </p>
    <form method="POST" action="?tab=events">
      <input type="hidden" name="_action" value="create_event" />
      <div class="form-grid">
        <div class="form-group">
          <label>Event Name</label>
          <input class="form-control" type="text" name="name" required placeholder="e.g. Lab Maintenance" />
        </div>
        <div class="form-group">
          <label>Laboratory</label>
          <select class="form-control" name="lab_id" required>
            <option value="">— Select lab —</option>
            <?php foreach ($allLabs as $l): ?>
              <option value="<?= e($l['_id']) ?>">
                Lab <?= (int)$l['number'] ?> — <?= e($l['class']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label>Date</label>
          <input class="form-control" type="date" name="date" required
                 min="<?= date('Y-m-d') ?>" />
        </div>
        <div class="form-group" style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
          <div>
            <label>Start Time</label>
            <select class="form-control" name="time_start" required>
              <?php foreach ($timeOptions as $t): ?>
                <option value="<?= e($t) ?>"><?= e($t) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label>End Time</label>
            <select class="form-control" name="time_end" required>
              <?php foreach ($timeOptions as $t): ?>
                <option value="<?= e($t) ?>"><?= e($t) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="form-group full">
          <label>Description (shown to students whose reservations are cancelled)</label>
          <textarea class="form-control" name="description" rows="3"
                    placeholder="e.g. Lab 2 is closed on April 1 from 8AM–10AM for scheduled electrical maintenance. We apologise for the inconvenience."></textarea>
          <span class="form-hint">Leave blank to show only the event name as the reason.</span>
        </div>
      </div>
      <div class="form-actions">
        <button type="submit" class="btn btn-event">Schedule Event</button>
        <button type="button" class="btn btn-ghost" onclick="closeModal('modal-create-event')">Cancel</button>
      </div>
    </form>
  </div>
</div>

<!-- Edit Event -->
<div class="modal-overlay" id="modal-edit-event">
  <div class="modal">
    <div class="modal-header">
      <h3 style="display:flex;align-items:center;gap:8px;"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>Edit Event</h3>
      <button class="modal-close" onclick="closeModal('modal-edit-event')">&times;</button>
    </div>
    <p style="font-size:.82rem;color:var(--muted);margin-bottom:18px;">
      Saving will re-run conflict detection for the updated window and cancel any
      newly conflicting reservations.
    </p>
    <form method="POST" action="?tab=events">
      <input type="hidden" name="_action" value="edit_event" />
      <input type="hidden" name="event_id" id="edit-event-id" />
      <div class="form-grid">
        <div class="form-group">
          <label>Event Name</label>
          <input class="form-control" type="text" name="name" id="edit-event-name" required />
        </div>
        <div class="form-group">
          <label>Laboratory</label>
          <select class="form-control" name="lab_id" id="edit-event-lab" required>
            <option value="">— Select lab —</option>
            <?php foreach ($allLabs as $l): ?>
              <option value="<?= e($l['_id']) ?>">
                Lab <?= (int)$l['number'] ?> — <?= e($l['class']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label>Date</label>
          <input class="form-control" type="date" name="date" id="edit-event-date" required />
        </div>
        <div class="form-group" style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
          <div>
            <label>Start Time</label>
            <select class="form-control" name="time_start" id="edit-event-start" required>
              <?php foreach ($timeOptions as $t): ?>
                <option value="<?= e($t) ?>"><?= e($t) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label>End Time</label>
            <select class="form-control" name="time_end" id="edit-event-end" required>
              <?php foreach ($timeOptions as $t): ?>
                <option value="<?= e($t) ?>"><?= e($t) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="form-group full">
          <label>Description</label>
          <textarea class="form-control" name="description" id="edit-event-desc" rows="3"></textarea>
        </div>
      </div>
      <div class="form-actions">
        <button type="submit" class="btn btn-event">Save Event</button>
        <button type="button" class="btn btn-ghost" onclick="closeModal('modal-edit-event')">Cancel</button>
      </div>
    </form>
  </div>
</div>

<!-- Delete Event -->
<div class="modal-overlay" id="modal-delete-event">
  <div class="modal" style="max-width:420px;text-align:center;">
    <div style="display:flex;justify-content:center;margin-bottom:12px;"><svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="var(--danger)" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg></div>
    <h3 style="margin-bottom:8px;font-size:1.1rem;">Delete this event?</h3>
    <p style="color:var(--muted);font-size:.85rem;margin-bottom:22px;">
      The event will be removed and the lab slots will open up again.
      Reservations that were already cancelled by this event will <strong>remain cancelled</strong>.
    </p>
    <form method="POST" action="?tab=events">
      <input type="hidden" name="_action" value="delete_event" />
      <input type="hidden" name="event_id" id="delete-event-id" />
      <div style="display:flex;gap:10px;justify-content:center;">
        <button type="submit" class="btn btn-danger">Yes, Delete</button>
        <button type="button" class="btn btn-ghost" onclick="closeModal('modal-delete-event')">Cancel</button>
      </div>
    </form>
  </div>
</div>


<!-- ════════════════════════════════════════════════════════════
     APP SHELL
════════════════════════════════════════════════════════════ -->
<div class="app">

  <aside class="sidebar">
    <div class="sidebar-logo">
      <h1>EZLabs</h1>
      <span>Admin Panel</span>
    </div>
    <nav class="sidebar-nav">
      <div class="nav-section-label">Dashboard</div>
      <a href="?tab=overview" class="nav-item <?= $tab==='overview'?'active':'' ?>"><span class="icon"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg></span><span>Overview</span></a>
      <div class="nav-section-label">Management</div>
      <a href="?tab=users"    class="nav-item <?= $tab==='users'?'active':'' ?>"><span class="icon"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg></span><span>Users</span></a>
      <a href="?tab=labs"     class="nav-item <?= $tab==='labs'?'active':'' ?>"><span class="icon"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="3" width="20" height="14" rx="2"/><path d="M8 21h8"/><path d="M12 17v4"/></svg></span><span>Labs &amp; Slots</span></a>
      <a href="?tab=events"   class="nav-item <?= $tab==='events'?'active':'' ?>"><span class="icon"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg></span><span>Events</span></a>
      <div class="nav-section-label">System</div>
      <a href="?tab=settings" class="nav-item <?= $tab==='settings'?'active':'' ?>"><span class="icon"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg></span><span>Settings</span></a>
    </nav>
    <div class="sidebar-footer">
      <a href="<?= e(app_url('/logout')) ?>" class="logout-btn"><svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg><span>Logout</span></a>
    </div>
  </aside>

  <div class="main">
    <div class="topbar">
      <span class="topbar-title">
        <?php $titles=['overview'=>'Overview','users'=>'User Management','labs'=>'Labs & Slots','events'=>'Event Scheduler','settings'=>'System Settings'];
              echo e($titles[$tab] ?? 'Admin'); ?>
      </span>
      <span class="topbar-badge">Admin</span>
      <div class="topbar-user">
        <div class="topbar-avatar"><?= strtoupper(substr($sessionName,0,1)) ?></div>
        <span><?= e($sessionName) ?></span>
      </div>
    </div>

    <div class="content">

      <?php if ($flash): ?>
        <div class="flash <?= e($flash['type']) ?>">
          <?= $flash['type']==='success'
            ? '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;"><polyline points="20 6 9 17 4 12"/></svg>'
            : '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>'
          ?>
          <span><?= e($flash['msg']) ?></span>
        </div>
      <?php endif; ?>


      <!-- ═══ OVERVIEW ════════════════════════════════════════ -->
      <?php if ($tab === 'overview'): ?>
        <div class="page-header">
          <h2>System Overview</h2>
          <p>Live snapshot of EZLabs activity</p>
        </div>
        <div class="stats-grid">
          <div class="stat-card"><div class="stat-label">Students</div><div class="stat-val"><?= $statStudents ?></div><div class="stat-sub">Registered accounts</div></div>
          <div class="stat-card"><div class="stat-label">Lab Technicians</div><div class="stat-val"><?= $statTechs ?></div><div class="stat-sub">Active staff</div></div>
          <div class="stat-card"><div class="stat-label">Administrators</div><div class="stat-val"><?= $statAdmins ?></div><div class="stat-sub">Admin accounts</div></div>
          <div class="stat-card"><div class="stat-label">Laboratories</div><div class="stat-val"><?= $statLabs ?></div><div class="stat-sub">Registered rooms</div></div>
          <div class="stat-card"><div class="stat-label">Scheduled Reservations</div><div class="stat-val" style="color:var(--accent2)"><?= $statRes ?></div><div class="stat-sub">Upcoming bookings</div></div>
          <div class="stat-card"><div class="stat-label">Upcoming Events</div><div class="stat-val" style="color:var(--event)"><?= $statEvents ?></div><div class="stat-sub">Scheduled blockouts</div></div>
        </div>
        <div class="card">
          <div class="card-title"><span class="icon"><svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg></span> Quick Actions</div>
          <div style="display:flex;gap:10px;flex-wrap:wrap;">
            <button class="btn btn-primary" onclick="openModal('modal-create-user')">Add User</button>
            <button class="btn btn-event"   onclick="openModal('modal-create-event')">Schedule Event</button>
            <a class="btn btn-ghost" href="?tab=users">Manage Users</a>
            <a class="btn btn-ghost" href="?tab=events">All Events</a>
            <a class="btn btn-ghost" href="?tab=settings">Settings</a>
          </div>
        </div>
        <div class="card">
          <div class="card-title"><span class="icon"><svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg></span> Recent Users</div>
          <div class="table-wrap">
            <table>
              <thead><tr><th>Username</th><th>Email</th><th>Role</th><th>Created</th></tr></thead>
              <tbody>
                <?php
                  $recent = array_slice(array_reverse($allUsers), 0, 6);
                  foreach ($recent as $u):
                    $rc = 'role-' . str_replace(' ','-', $u['role']);
                ?>
                <tr>
                  <td style="font-weight:600;"><?= e($u['username']) ?></td>
                  <td style="color:var(--muted);"><?= e($u['email']) ?></td>
                  <td><span class="role-badge <?= e($rc) ?>"><?= e($u['role']) ?></span></td>
                  <td style="color:var(--muted);font-size:.78rem;"><?= e(substr($u['createdAt'],0,10)) ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($recent)): ?><tr><td colspan="4" style="text-align:center;padding:20px;color:var(--muted);">No users yet.</td></tr><?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>


      <!-- ═══ USERS ════════════════════════════════════════════ -->
      <?php elseif ($tab === 'users'): ?>
        <div class="page-header">
          <h2>User Management</h2>
          <p>Create, edit, and remove system accounts. Assign roles.</p>
        </div>
        <form method="GET" action="">
          <input type="hidden" name="tab" value="users" />
          <div class="toolbar">
            <input type="text" name="q" placeholder="Search username or email…" value="<?= e($search) ?>" />
            <select name="role_filter">
              <option value="">All Roles</option>
              <option value="Student"        <?= $roleFilter==='Student'?'selected':'' ?>>Student</option>
              <option value="Lab Technician" <?= $roleFilter==='Lab Technician'?'selected':'' ?>>Lab Technician</option>
              <option value="Admin"          <?= $roleFilter==='Admin'?'selected':'' ?>>Admin</option>
            </select>
            <button type="submit" class="btn btn-ghost">Filter</button>
            <?php if ($search||$roleFilter): ?><a href="?tab=users" class="btn btn-ghost">&times; Clear</a><?php endif; ?>
            <button type="button" class="btn btn-primary" onclick="openModal('modal-create-user')" style="margin-left:auto;">Add User</button>
          </div>
        </form>
        <div class="card" style="padding:0;">
          <div class="table-wrap" style="border:none;">
            <table>
              <thead><tr><th>#</th><th>Username</th><th>Email</th><th>Role</th><th>Created</th><th style="text-align:right;">Actions</th></tr></thead>
              <tbody>
                <?php if (empty($users)): ?>
                  <tr><td colspan="6" style="text-align:center;padding:32px;color:var(--muted);">No users found.</td></tr>
                <?php else: ?>
                  <?php foreach ($users as $i => $u):
                    $rc = 'role-' . str_replace(' ','-', $u['role']);
                    $rowNum = ($page-1)*$perPage + $i + 1;
                  ?>
                  <tr>
                    <td style="color:var(--muted);font-size:.78rem;font-family:var(--mono);"><?= $rowNum ?></td>
                    <td style="font-weight:600;"><?= e($u['username']) ?></td>
                    <td style="color:var(--muted);"><?= e($u['email']) ?></td>
                    <td><span class="role-badge <?= e($rc) ?>"><?= e($u['role']) ?></span></td>
                    <td style="color:var(--muted);font-size:.78rem;"><?= e(substr($u['createdAt'],0,10)) ?></td>
                    <td style="text-align:right;">
                      <div style="display:flex;gap:6px;justify-content:flex-end;">
                        <button class="btn btn-ghost btn-sm" onclick="openEditUserModal(
                          '<?= e(addslashes($u['_id'])) ?>',
                          '<?= e(addslashes($u['username'])) ?>',
                          '<?= e(addslashes($u['email'])) ?>',
                          '<?= e(addslashes($u['role'])) ?>'
                        )">Edit</button>
                        <?php if ($u['_id'] !== $sessionId): ?>
                          <button class="btn btn-danger btn-sm" onclick="openDeleteUserModal('<?= e($u['_id']) ?>')">Delete</button>
                        <?php else: ?>
                          <button class="btn btn-danger btn-sm" disabled style="opacity:.4;" title="Cannot delete yourself">Delete</button>
                        <?php endif; ?>
                      </div>
                    </td>
                  </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
        <?php if ($totalPages > 1): ?>
          <div class="pagination">
            <?php for ($pg=1; $pg<=$totalPages; $pg++): ?>
              <?php $pUrl='?tab=users&p='.$pg.'&q='.urlencode($search).'&role_filter='.urlencode($roleFilter); ?>
              <?php if ($pg===$page): ?><span><?= $pg ?></span><?php else: ?><a href="<?= $pUrl ?>"><?= $pg ?></a><?php endif; ?>
            <?php endfor; ?>
          </div>
        <?php endif; ?>
        <p style="color:var(--muted);font-size:.78rem;margin-top:10px;">
          Showing <?= count($users) ?> of <?= $totalUsers ?> user<?= $totalUsers!==1?'s':'' ?>
        </p>


      <!-- ═══ LABS (FULLY FUNCTIONAL) ══════════════════════ -->
      <?php elseif ($tab === 'labs'): ?>
        <div class="page-header">
          <h2>Labs &amp; Slots</h2>
          <p>Create, configure, and manage computer laboratories. Each lab has a fixed 7×5 seat grid (35 seats) with 30-minute slots from 7:00 AM to 7:00 PM.</p>
        </div>
 
        <div style="display:flex;justify-content:flex-end;margin-bottom:18px;">
          <button class="btn btn-lab" onclick="openModal('modal-create-lab')">Add New Laboratory</button>
        </div>
 
        <?php if (empty($allLabs)): ?>
          <div class="placeholder-section" style="border-style:solid;">
            <div class="ph-icon">🖥️</div>
            <h3>No laboratories yet</h3>
            <p>Add your first laboratory using the button above.</p>
          </div>
        <?php else: ?>
          <div class="lab-list">
            <?php foreach ($allLabs as $lab):
              // Count reservations for this lab
              $labResCount = count(array_filter($allRes, fn($r) => (string)($r['lab']??'') === (string)$lab['_id'] && reservation_status($r) === 'Scheduled'));
              $labEventCount = count(array_filter($allEvents, fn($e) => (string)($e['lab']??'') === (string)$lab['_id']));
            ?>
            <div class="lab-item">
              <div class="lab-number-badge"><?= (int)$lab['number'] ?></div>
              <div class="lab-item-meta">
                <div class="lab-item-name">Lab <?= (int)$lab['number'] ?> — <?= e($lab['class']) ?></div>
                <div class="lab-info" style="font-size:.82rem;color:var(--muted);margin-bottom:10px;">
                  7 rows × 5 columns &nbsp;·&nbsp; 35 seats total &nbsp;·&nbsp; Slots: 7:00 AM – 7:00 PM (30-min intervals)
                </div>
                <div class="lab-item-tags">
                  <span class="lab-tag lab-tag-class"><?= e($lab['class']) ?></span>
                  <span class="lab-tag" style="background:rgba(167,139,250,.1);color:var(--event);border:1px solid rgba(167,139,250,.25);"><?= $labResCount ?> scheduled reservations</span>
                  <span class="lab-tag" style="background:rgba(251,191,36,.1);color:var(--warn);border:1px solid rgba(251,191,36,.25);"><?= $labEventCount ?> events</span>
                </div>
              </div>
              <div class="lab-actions">
                <button class="btn btn-ghost btn-sm" onclick="openEditLabModal('<?= e(addslashes($lab['_id'])) ?>','<?= (int)$lab['number'] ?>','<?= e(addslashes($lab['class'])) ?>')">Edit</button>
                <button class="btn btn-danger btn-sm" onclick="openDeleteLabModal('<?= e($lab['_id']) ?>','Lab <?= (int)$lab['number'] ?> (<?= e(addslashes($lab['class'])) ?>)')">Delete</button>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
 
          <!-- Slot grid reference card -->
          <div class="card" style="margin-top:24px;">
            <div class="card-title"><span class="icon">📋</span> Slot Schedule Reference</div>
            <p style="font-size:.83rem;color:var(--muted);margin-bottom:16px;">All laboratories share the same time slot structure. Slots run every 30 minutes from 7:00 AM to 7:00 PM (24 slots per seat per day).</p>
            <div style="overflow-x:auto;">
              <table>
                <thead>
                  <tr>
                    <th>Slot #</th>
                    <?php for ($s = 0; $s < 24; $s++):
                      $mins = (7*60) + ($s*30);
                      $h = intdiv($mins,60); $m = $mins%60;
                      $period = $h >= 12 ? 'PM' : 'AM';
                      $h12 = $h % 12 ?: 12; ?>
                      <th><?= sprintf('%d:%02d %s', $h12, $m, $period) ?></th>
                    <?php endfor; ?>
                  </tr>
                </thead>
                <tbody>
                  <tr>
                    <td style="color:var(--muted);font-size:.75rem;">Index</td>
                    <?php for ($s = 0; $s < 24; $s++): ?>
                      <td style="text-align:center;font-family:var(--mono);font-size:.75rem;color:var(--accent);"><?= $s ?></td>
                    <?php endfor; ?>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        <?php endif; ?>


      <!-- ═══ EVENTS (fully functional) ═══════════════════════ -->
      <?php elseif ($tab === 'events'): ?>
        <div class="page-header">
          <h2>Event Scheduler</h2>
          <p>
            Block lab availability for a defined window. All conflicting Scheduled reservations
            are automatically cancelled — students will see your event description as the reason.
          </p>
        </div>

        <div style="display:flex;justify-content:flex-end;margin-bottom:18px;">
          <button class="btn btn-event" onclick="openModal('modal-create-event')">Schedule New Event</button>
        </div>

        <?php if (empty($allEvents)): ?>
          <div class="placeholder-section" style="border-style:solid;">
            <div class="ph-icon"><svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg></div>
            <h3>No events scheduled yet</h3>
            <p>Use the button above to schedule a lab blackout event.</p>
          </div>
        <?php else: ?>
          <div class="event-list">
            <?php foreach ($allEvents as $ev):
              $labLabel = $labById[(string)($ev['lab']??'')] ?? 'Unknown Lab';
              $isPast   = $ev['date'] < date('Y-m-d');
            ?>
            <div class="event-item" style="<?= $isPast ? 'opacity:.55;' : '' ?>">
              <div class="event-item-meta">
                <div class="event-item-name">
                  <?= e($ev['name']) ?>
                  <?php if ($isPast): ?><span class="event-badge" style="font-size:.65rem;margin-left:6px;">Past</span><?php endif; ?>
                </div>
                <?php if ($ev['description'] !== ''): ?>
                  <div class="event-item-desc"><?= e($ev['description']) ?></div>
                <?php endif; ?>
                <div class="event-item-tags">
                  <span class="event-tag event-tag-lab"><?= e($labLabel) ?></span>
                  <span class="event-tag event-tag-date"><?= e($ev['date']) ?></span>
                  <span class="event-tag event-tag-time"><?= e($ev['time_start']) ?> – <?= e($ev['time_end']) ?></span>
                </div>
              </div>
              <div class="event-actions">
                <?php if (!$isPast): ?>
                  <button class="btn btn-ghost btn-sm" onclick="openEditEventModal(
                    '<?= e(addslashes($ev['_id'])) ?>',
                    '<?= e(addslashes($ev['lab'])) ?>',
                    '<?= e(addslashes($ev['name'])) ?>',
                    '<?= e(addslashes($ev['description'])) ?>',
                    '<?= e(addslashes($ev['date'])) ?>',
                    '<?= e(addslashes($ev['time_start'])) ?>',
                    '<?= e(addslashes($ev['time_end'])) ?>'
                  )">Edit</button>
                <?php endif; ?>
                <button class="btn btn-danger btn-sm" onclick="openDeleteEventModal('<?= e($ev['_id']) ?>')">Delete</button>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>

      <!-- ═══ SETTINGS (fully functional) ════════════════════ -->
      <?php elseif ($tab === 'settings'): ?>
        <div class="page-header"><h2>System Settings</h2><p>Configure global behaviour rules for EZLabs.</p></div>
        <div class="card">
          <div class="card-title"><span class="icon"><svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg></span> Operational Rules</div>
          <div class="settings-note" style="display:flex;align-items:flex-start;gap:8px;">
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;margin-top:2px;"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            <span>These settings control reservation windows, grace periods, and seat limits.
            Changes take effect immediately for all new reservations.</span>
          </div>
          <form method="POST" action="?tab=settings">
            <input type="hidden" name="_action" value="save_settings" />
            <div class="settings-grid">
              <div class="form-group">
                <label>Site Name</label>
                <input class="form-control" type="text" name="site_name" value="<?= e($settings['site_name']) ?>" required />
                <span class="form-hint">Displayed in the browser tab and header.</span>
              </div>
              <div class="form-group">
                <label>Max Seats per Lab</label>
                <input class="form-control" type="number" name="max_seats" value="<?= (int)$settings['max_seats'] ?>" min="1" max="200" required />
                <span class="form-hint">Default seat count when creating a new lab.</span>
              </div>
              <div class="form-group">
                <label>Advance Booking Window (days)</label>
                <input class="form-control" type="number" name="advance_days" value="<?= (int)$settings['advance_days'] ?>" min="1" max="30" required />
                <span class="form-hint">Students can reserve up to this many days in advance.</span>
              </div>
              <div class="form-group">
                <label>Cancellation Cutoff (minutes)</label>
                <input class="form-control" type="number" name="cancel_minutes" value="<?= (int)$settings['cancel_minutes'] ?>" min="1" max="1440" required />
                <span class="form-hint">Students can no longer cancel within this window before their slot.</span>
              </div>
              <div class="form-group">
                <label>No-show Grace Period (minutes)</label>
                <input class="form-control" type="number" name="grace_minutes" value="<?= (int)$settings['grace_minutes'] ?>" min="1" max="60" required />
                <span class="form-hint">Lab technicians may cancel a reservation after this many minutes of no-show.</span>
              </div>
            </div>
            <div class="form-actions" style="margin-top:24px;">
              <button type="submit" class="btn btn-primary">Save Settings</button>
              <button type="reset"  class="btn btn-ghost">Reset</button>
            </div>
          </form>
        </div>
        <div class="card">
          <div class="card-title"><span class="icon"><svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><rect x="8" y="2" width="8" height="4" rx="1" ry="1"/></svg></span> Active Configuration</div>
          <div class="table-wrap">
            <table>
              <thead><tr><th>Setting</th><th>Current Value</th></tr></thead>
              <tbody>
                <tr><td>Site Name</td>        <td style="font-family:var(--mono);color:var(--accent);"><?= e($settings['site_name']) ?></td></tr>
                <tr><td>Max Seats per Lab</td><td style="font-family:var(--mono);color:var(--accent);"><?= (int)$settings['max_seats'] ?></td></tr>
                <tr><td>Advance Booking</td>  <td style="font-family:var(--mono);color:var(--accent);"><?= (int)$settings['advance_days'] ?> days</td></tr>
                <tr><td>Cancel Cutoff</td>    <td style="font-family:var(--mono);color:var(--accent);"><?= (int)$settings['cancel_minutes'] ?> minutes</td></tr>
                <tr><td>No-show Grace</td>    <td style="font-family:var(--mono);color:var(--accent);"><?= (int)$settings['grace_minutes'] ?> minutes</td></tr>
              </tbody>
            </table>
          </div>
        </div>

      <?php endif; ?>

    </div><!-- /content -->
  </div><!-- /main -->
</div><!-- /app -->

<script>
// ── Modal helpers ──────────────────────────────────────────
function openModal(id)  { document.getElementById(id).classList.add('open'); }
function closeModal(id) { document.getElementById(id).classList.remove('open'); }
document.querySelectorAll('.modal-overlay').forEach(function(o) {
  o.addEventListener('click', function(e) { if (e.target === o) o.classList.remove('open'); });
});
document.addEventListener('keydown', function(e) {
  if (e.key === 'Escape') document.querySelectorAll('.modal-overlay.open').forEach(function(m) { m.classList.remove('open'); });
});

// ── User modals ────────────────────────────────────────────
function openEditUserModal(id, username, email, role) {
  document.getElementById('edit-user-id').value  = id;
  document.getElementById('edit-username').value  = username;
  document.getElementById('edit-email').value     = email;
  document.getElementById('edit-role').value      = role;
  openModal('modal-edit-user');
}
function openDeleteUserModal(id) {
  document.getElementById('delete-user-id').value = id;
  openModal('modal-delete-user');
}

// Lab modals
function openEditLabModal(id, number, className) {
  document.getElementById('edit-lab-id').value     = id;
  document.getElementById('edit-lab-number').value = number;
  document.getElementById('edit-lab-class').value  = className;
  openModal('modal-edit-lab');
}
function openDeleteLabModal(id, name) {
  document.getElementById('delete-lab-id').value   = id;
  document.getElementById('delete-lab-name').textContent = name;
  openModal('modal-delete-lab');
}

// ── Event modals ───────────────────────────────────────────
function openEditEventModal(id, labId, name, desc, date, tStart, tEnd) {
  document.getElementById('edit-event-id').value    = id;
  document.getElementById('edit-event-name').value  = name;
  document.getElementById('edit-event-desc').value  = desc;
  document.getElementById('edit-event-date').value  = date;

  // Select the matching lab option
  var labSel = document.getElementById('edit-event-lab');
  for (var i = 0; i < labSel.options.length; i++) {
    if (labSel.options[i].value === labId) { labSel.selectedIndex = i; break; }
  }
  // Select time options
  function setSelect(selId, val) {
    var s = document.getElementById(selId);
    for (var i = 0; i < s.options.length; i++) {
      if (s.options[i].value === val) { s.selectedIndex = i; break; }
    }
  }
  setSelect('edit-event-start', tStart);
  setSelect('edit-event-end',   tEnd);
  openModal('modal-edit-event');
}
function openDeleteEventModal(id) {
  document.getElementById('delete-event-id').value = id;
  openModal('modal-delete-event');
}

// ── Flash auto-dismiss ─────────────────────────────────────
(function() {
  var f = document.querySelector('.flash');
  if (!f) return;
  setTimeout(function() {
    f.style.transition = 'opacity .4s';
    f.style.opacity = '0';
    setTimeout(function() { f.remove(); }, 400);
  }, 6000);
})();
</script>
</body>
</html>
