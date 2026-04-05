<?php


require_once __DIR__ . '/../lib/repository.php';

require_role('Admin');
$sessionUser = $_SESSION['user'] ?? [];
$sessionName = (string) ($sessionUser['username'] ?? '');
$sessionId   = session_user_id();

$tab          = $_GET['tab'] ?? 'overview';
$allowedTabs  = ['overview', 'users', 'labs', 'events', 'reservations', 'settings'];
if (!in_array($tab, $allowedTabs, true)) $tab = 'overview';

$flash = $_SESSION['admin_flash'] ?? null;
unset($_SESSION['admin_flash']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['_action'] ?? '';

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

    if ($action === 'edit_user') {
        $uid2    = trim($_POST['user_id']  ?? '');
        $uname   = trim($_POST['username'] ?? '');
        $email   = trim($_POST['email']    ?? '');
        $newRole = trim($_POST['role']     ?? '');
        $pass    = trim($_POST['password'] ?? '');

        $existing = users_find_by_id($uid2);
      if ($existing && $uname && $email) {
        // Prevent editing other Admin accounts
        if ((string)($existing['role'] ?? '') === 'Admin' && $uid2 !== $sessionId) {
          $_SESSION['admin_flash'] = ['type'=>'error','msg'=>'Cannot edit other admin accounts.'];
          redirect_to('/admin_dashboard.php?tab=users');
        }
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

    if ($action === 'delete_user') {
      $uid2 = trim($_POST['user_id'] ?? '');
      if ($uid2) {
        $target = users_find_by_id($uid2);
        if (!$target) {
          $_SESSION['admin_flash'] = ['type'=>'error','msg'=>'User not found.'];
          redirect_to('/admin_dashboard.php?tab=users');
        }

        // If target is an Admin
        if ((string)($target['role'] ?? '') === 'Admin') {
          if ($uid2 === $sessionId) {
            // Allow deleting your own admin account: remove user and end session
            users_delete_by_username((string) ($target['username'] ?? ''));
            session_destroy();
            setcookie(session_name(), '', time() - 3600, cookie_path());
            redirect_to('/');
          }
          // Prevent deleting other admins
          $_SESSION['admin_flash'] = ['type'=>'error','msg'=>'Cannot delete other admin accounts.'];
          redirect_to('/admin_dashboard.php?tab=users');
        }

        // Non-admin users may be deleted
        users_delete_by_username((string) ($target['username'] ?? ''));
        $_SESSION['admin_flash'] = ['type'=>'success','msg'=>'User deleted.'];
      }
      redirect_to('/admin_dashboard.php?tab=users');
    }

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
 
    if ($action === 'edit_lab') {
        $labId     = trim($_POST['lab_id']     ?? '');
        $className = trim($_POST['class_name'] ?? '');
        $number    = (int) ($_POST['number'] ?? 0);
 
        if ($labId && $className && $number > 0) {
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

    if ($action === 'delete_event') {
        $evId = trim($_POST['event_id'] ?? '');
        if ($evId && events_delete($evId)) {
            $_SESSION['admin_flash'] = ['type'=>'success','msg'=>'Event deleted. Previously cancelled reservations remain cancelled.'];
        } else {
            $_SESSION['admin_flash'] = ['type'=>'error','msg'=>'Event not found.'];
        }
        redirect_to('/admin_dashboard.php?tab=events');
    }

    if ($action === 'save_settings') {
      $toSave = [
        'site_name'      => trim($_POST['site_name']      ?? 'EZLabs'),
        'grace_minutes'  => max(1, (int) ($_POST['grace_minutes']  ?? 10)),
        'advance_days'   => max(1, (int) ($_POST['advance_days']   ?? 7)),
        'cancel_minutes' => max(1, (int) ($_POST['cancel_minutes'] ?? 30)),
      ];
        save_settings($toSave);
        $_SESSION['admin_flash'] = ['type'=>'success','msg'=>'Settings saved.'];
        redirect_to('/admin_dashboard.php?tab=settings');
    }
}

    // Admin reservation actions (edit/delete) handled here so Admins can CRUD all upcoming reservations
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
      $action = $_POST['_action'] ?? '';

      if ($action === 'admin_delete_reservation') {
        $reservationId = trim((string) ($_POST['reservation_id'] ?? ''));
        if ($reservationId !== '') {
          $res = reservations_find_by_id($reservationId);
          if ($res !== null && reservation_can_delete_technician($res)) {
            reservations_delete($reservationId);
            $_SESSION['admin_flash'] = ['type' => 'success', 'msg' => 'Reservation deleted.'];
          } else {
            $_SESSION['admin_flash'] = ['type' => 'error', 'msg' => 'Reservation cannot be deleted (past or protected).'];
          }
        }
        redirect_to('/admin_dashboard.php?tab=reservations');
      }

      if ($action === 'admin_edit_reservation') {
        $reservationId = trim((string) ($_POST['reservation_id'] ?? ''));
        $reservation = $reservationId !== '' ? reservations_find_by_id($reservationId) : null;
        if ($reservation === null) {
          $_SESSION['admin_flash'] = ['type' => 'error', 'msg' => 'Reservation not found.'];
          redirect_to('/admin_dashboard.php?tab=reservations');
        }

        if (!reservation_can_edit_technician($reservation)) {
          $_SESSION['admin_flash'] = ['type' => 'error', 'msg' => 'Reservation cannot be edited (past).'];
          redirect_to('/admin_dashboard.php?tab=reservations');
        }

        $date = format_manila_date((string) ($_POST['date'] ?? reservation_date_only($reservation)));
        $timeStart = (string) ($_POST['time_start'] ?? (string) ($reservation['time_start'] ?? ''));
        $timeEnd = (string) ($_POST['time_end'] ?? (string) ($reservation['time_end'] ?? ''));
        $row = (int) ($_POST['row'] ?? 1);
        $column = (int) ($_POST['column'] ?? 1);
        $labNumber = (int) ($_POST['lab_number'] ?? 0);
        $lab = labs_find_by_number($labNumber);

        if ($lab === null || $date === '' || $row < 1 || $row > 7 || $column < 1 || $column > 5 || parse_time_to_minutes($timeEnd) <= parse_time_to_minutes($timeStart)) {
          $_SESSION['admin_flash'] = ['type' => 'error', 'msg' => 'Invalid input for reservation update.'];
          redirect_to('/admin_dashboard.php?tab=reservations');
        }

        $candidate = [
          'lab' => (string) ($lab['_id'] ?? ''),
          'date' => $date,
          'time_start' => $timeStart,
          'time_end' => $timeEnd,
        ];
        $candidateSeats = [['row' => $row, 'column' => $column]];

        if (reservation_conflicts($candidate, $candidateSeats, $reservationId)) {
          $_SESSION['admin_flash'] = ['type' => 'error', 'msg' => 'Reservation conflicts with existing bookings.'];
          redirect_to('/admin_dashboard.php?tab=reservations');
        }

        $all = reservations_all();
        foreach ($all as $i => $item) {
          if ((string) ($item['_id'] ?? '') !== $reservationId) continue;
          $item['date'] = $date;
          $item['time_start'] = $timeStart;
          $item['time_end'] = $timeEnd;
          $item['lab'] = (string) ($lab['_id'] ?? '');
          $item['updatedAt'] = now_iso();
          $all[$i] = $item;
          break;
        }
        reservations_save($all);
        seats_replace_for_reservation($reservationId, $candidateSeats);
        $_SESSION['admin_flash'] = ['type' => 'success', 'msg' => 'Reservation updated.'];
        redirect_to('/admin_dashboard.php?tab=reservations');
      }
    }


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
usort($filtered, fn($a, $b) => strcmp((string)($b['createdAt']??''), (string)($a['createdAt']??'')));

$totalUsers = count($filtered);
$totalPages = max(1, (int) ceil($totalUsers / $perPage));
$page       = min($page, $totalPages);
$users      = array_slice($filtered, ($page - 1) * $perPage, $perPage);


$allEvents  = events_all();
$allLabs    = labs_all();

$labById = [];
foreach ($allLabs as $l) {
    $labById[(string)$l['_id']] = 'Lab ' . $l['number'] . ' (' . $l['class'] . ')';
}

$statStudents = count(array_filter($allUsers, fn($u) => $u['role'] === 'Student'));
$statTechs    = count(array_filter($allUsers, fn($u) => $u['role'] === 'Lab Technician'));
$statAdmins   = count(array_filter($allUsers, fn($u) => $u['role'] === 'Admin'));
$statLabs     = count($allLabs);
$allRes       = reservations_all();
$statRes      = count(array_filter($allRes, fn($r) => reservation_status($r) === 'Scheduled'));
$statEvents   = count($allEvents);

// Build combined reservation rows for admin view
$combined = [];
foreach (reservations_all() as $reservation) {
  $student = users_find_by_id((string) ($reservation['user'] ?? ''));
  $lab = labs_find_by_id((string) ($reservation['lab'] ?? ''));
  if ($student === null || $lab === null) {
    continue;
  }

  $status = reservation_status($reservation);
  $seats = seats_for_reservation((string) ($reservation['_id'] ?? ''));
  if ($seats === []) {
    $seats = [['row' => 0, 'column' => 0]];
  }

  foreach ($seats as $seat) {
    $combined[] = [
      '_id' => (string) ($reservation['_id'] ?? ''),
      'row' => (int) ($seat['row'] ?? 0),
      'column' => (int) ($seat['column'] ?? 0),
      'student' => (string) ($student['username'] ?? ''),
      'lab' => 'Lab ' . $lab['number'] . ' (' . $lab['class'] . ')',
      'time_start' => (string) ($reservation['time_start'] ?? ''),
      'time_end' => (string) ($reservation['time_end'] ?? ''),
      'date' => reservation_date_only($reservation),
      'createdAt' => (string) ($reservation['createdAt'] ?? ''),
      'status' => $status,
      'cancel_reason' => (string) ($reservation['cancel_reason'] ?? ''),
      'showDelete' => reservation_show_delete($reservation),
      'isPast' => in_array($status, ['Completed', 'Cancelled'], true),
      'canEdit' => reservation_can_edit_technician($reservation),
      'canDelete' => reservation_can_delete_technician($reservation),
    ];
  }
}

usort($combined, static fn(array $a, array $b): int => strcmp((string) ($b['createdAt'] ?? ''), (string) ($a['createdAt'] ?? '')));

$currentReservations = [];
$pastReservations = [];
foreach ($combined as $row) {
  if (in_array((string) ($row['status'] ?? ''), ['Completed', 'Cancelled'], true)) {
    $pastReservations[] = $row;
  } else {
    $currentReservations[] = $row;
  }
}

$defaults = [
  'site_name'      => 'EZLabs',
  'grace_minutes'  => 10,
  'advance_days'   => 7,
  'cancel_minutes' => 30,
];

$settings = array_merge($defaults, load_settings());

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
  <link rel="stylesheet" href="<?= e(app_url('/css/admin_dashboard.css')) ?>" />
</head>
<body>



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


<div class="modal-overlay" id="modal-delete-user">
  <div class="modal" style="max-width:400px;text-align:center;">
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
        <button type="submit" class="btn btn-primary">Add Laboratory</button>
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
      <a href="?tab=reservations" class="nav-item <?= $tab==='reservations'?'active':'' ?>"><span class="icon">📋</span><span>Reservations</span></a>
      <div class="nav-section-label">System</div>
      <a href="?tab=settings" class="nav-item <?= $tab==='settings'?'active':'' ?>"><span class="icon"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg></span><span>Settings</span></a>
    </nav>
    <div class="sidebar-footer">
      <a href="<?= e(app_url('/logout')) ?>" class="logout-btn"><svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg><span>Logout</span></a>
    </div>
  </aside>

  <div class="main">

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



      <?php if ($tab === 'overview'): ?>
        <div class="page-header">
          <div>
            <h2>System Overview</h2>
            <p>Live snapshot of EZLabs activity</p>
          </div>
          <div class="page-header-user">
            <span class="topbar-badge">Admin</span>
            <div class="topbar-user">
              <div class="topbar-avatar"><?= strtoupper(substr($sessionName,0,1)) ?></div>
              <span><?= e($sessionName) ?></span>
            </div>
          </div>
        </div>
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:18px;margin-bottom:24px;align-items:stretch;">
          <div class="stat-card"><div class="stat-label">Students</div><div class="stat-val"><?= $statStudents ?></div><div class="stat-sub">Registered accounts</div></div>
          <div class="stat-card"><div class="stat-label">Lab Technicians</div><div class="stat-val"><?= $statTechs ?></div><div class="stat-sub">Active staff</div></div>
          <div class="stat-card"><div class="stat-label">Administrators</div><div class="stat-val"><?= $statAdmins ?></div><div class="stat-sub">Admin accounts</div></div>
          <div class="stat-card"><div class="stat-label">Laboratories</div><div class="stat-val"><?= $statLabs ?></div><div class="stat-sub">Registered rooms</div></div>
          <div class="stat-card"><div class="stat-label">Reservations</div><div class="stat-val"><?= $statRes ?></div><div class="stat-sub">Upcoming bookings</div></div>
          <div class="stat-card"><div class="stat-label">Upcoming Events</div><div class="stat-val"><?= $statEvents ?></div><div class="stat-sub">Scheduled blockouts</div></div>
        </div>
        <div class="card">
          <div class="card-title" style="display:flex;align-items:center;justify-content:space-between;">
            <span style="display:flex;align-items:center;gap:6px;"><span class="icon"><svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg></span> Recent Users</span>
            <button class="btn btn-primary" onclick="openModal('modal-create-user')">Add User</button>
          </div>
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
                  <td style="font-weight:700;font-size:.9rem;color:var(--text);"><?= e($u['username']) ?></td>
                  <td style="font-size:.875rem;color:var(--text);"><?= e($u['email']) ?></td>
                  <td><span class="role-badge <?= e($rc) ?>"><?= e($u['role']) ?></span></td>
                  <td style="font-size:.84rem;color:var(--text);font-family:var(--mono);"><?= e(substr($u['createdAt'],0,10)) ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($recent)): ?><tr><td colspan="4" style="text-align:center;padding:20px;color:var(--muted);">No users yet.</td></tr><?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>

        <div class="card">
          <div class="card-title" style="display:flex;align-items:center;justify-content:space-between;">
            <span style="display:flex;align-items:center;gap:6px;"><span class="icon"><svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg></span> Recent and Upcoming Events</span>
            <button class="btn btn-event" onclick="openModal('modal-create-event')">Schedule Event</button>
          </div>
          <div class="table-wrap">
            <table>
              <thead><tr><th>Event Name</th><th>Lab Used</th><th>Date</th><th>Time Slot</th></tr></thead>
              <tbody>
                <?php
                  $recentEvents = array_slice(array_reverse($allEvents), 0, 6);
                  $today = date('Y-m-d');
                  foreach ($recentEvents as $ev):
                    $evDate   = $ev['date'] ?? '';
                    $evStatus = strtolower($ev['status'] ?? '');
                    $isPast   = ($evStatus === 'concluded' || $evStatus === 'cancelled') || ($evDate && $evDate < $today);
                    $labLabel = isset($ev['lab']) ? ($labById[(string)$ev['lab']] ?? e($ev['lab'])) : '—';
                ?>
                <tr>
                  <td style="font-weight:700;font-size:.9rem;color:var(--text);"><?= e($ev['name']) ?></td>
                  <td style="font-size:.875rem;color:var(--primary);font-weight:600;"><?= $labLabel ?></td>
                  <td>
                    <?php if ($isPast): ?>
                      <span style="display:inline-block;padding:2px 10px;border-radius:99px;font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.6px;background:rgba(107,114,128,.12);color:#4b5563;border:1px solid rgba(107,114,128,.3);">Past</span>
                    <?php else: ?>
                      <span style="font-size:.84rem;color:var(--text);font-family:var(--mono);font-weight:600;"><?= e($evDate) ?></span>
                    <?php endif; ?>
                  </td>
                  <td style="font-size:.84rem;color:var(--text);font-family:var(--mono);font-weight:500;"><?= e($ev['time_start'] ?? '—') ?> – <?= e($ev['time_end'] ?? '—') ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($recentEvents)): ?><tr><td colspan="4" style="text-align:center;padding:20px;color:var(--muted);">No events yet.</td></tr><?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>



      <?php elseif ($tab === 'users'): ?>
        <div class="page-header">
          <div>
            <h2>User Management</h2>
            <p>Create, edit, and remove system accounts. Assign roles.</p>
          </div>
          <div class="page-header-user">
            <span class="topbar-badge">Admin</span>
            <div class="topbar-user">
              <div class="topbar-avatar"><?= strtoupper(substr($sessionName,0,1)) ?></div>
              <span><?= e($sessionName) ?></span>
            </div>
          </div>
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
                        <?php if ($u['_id'] === $sessionId): ?>
                          <!-- Current admin: can edit and delete own account -->
                          <button class="btn btn-ghost btn-sm" onclick="openEditUserModal(
                            '<?= e(addslashes($u['_id'])) ?>',
                            '<?= e(addslashes($u['username'])) ?>',
                            '<?= e(addslashes($u['email'])) ?>',
                            '<?= e(addslashes($u['role'])) ?>'
                          )">Edit</button>
                          <button class="btn btn-danger btn-sm" onclick="openDeleteUserModal('<?= e($u['_id']) ?>')">Delete</button>
                        <?php else: ?>
                          <?php if ((string)($u['role'] ?? '') === 'Admin'): ?>
                            <!-- Other admins: cannot be edited or deleted -->
                            <button class="btn btn-ghost btn-sm" disabled title="Cannot edit other admin accounts" style="opacity:.5;">Edit</button>
                            <button class="btn btn-danger btn-sm" disabled title="Cannot delete other admin accounts" style="opacity:.5;">Delete</button>
                          <?php else: ?>
                            <!-- Non-admin users: normal actions -->
                            <button class="btn btn-ghost btn-sm" onclick="openEditUserModal(
                              '<?= e(addslashes($u['_id'])) ?>',
                              '<?= e(addslashes($u['username'])) ?>',
                              '<?= e(addslashes($u['email'])) ?>',
                              '<?= e(addslashes($u['role'])) ?>'
                            )">Edit</button>
                            <button class="btn btn-danger btn-sm" onclick="openDeleteUserModal('<?= e($u['_id']) ?>')">Delete</button>
                          <?php endif; ?>
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



      <?php elseif ($tab === 'labs'): ?>
        <div class="page-header">
          <div>
            <h2>Labs &amp; Slots</h2>
            <p>Create, configure, and manage computer laboratories. Each lab has a fixed 7×5 seat grid (35 seats) with 30-minute slots from 7:00 AM to 7:00 PM.</p>
          </div>
          <div class="page-header-user">
            <span class="topbar-badge">Admin</span>
            <div class="topbar-user">
              <div class="topbar-avatar"><?= strtoupper(substr($sessionName,0,1)) ?></div>
              <span><?= e($sessionName) ?></span>
            </div>
          </div>
        </div>
 
        <div style="display:flex;justify-content:flex-end;margin-bottom:18px;">
          <button class="btn btn-primary" onclick="openModal('modal-create-lab')">Add New Laboratory</button>
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
        <?php endif; ?>

      <?php elseif ($tab === 'events'): ?>
        <div class="page-header">
          <div>
            <h2>Event Scheduler</h2>
            <p>
              Block lab availability for a defined window. All conflicting Scheduled reservations
              are automatically cancelled — students will see your event description as the reason.
            </p>
          </div>
          <div class="page-header-user">
            <span class="topbar-badge">Admin</span>
            <div class="topbar-user">
              <div class="topbar-avatar"><?= strtoupper(substr($sessionName,0,1)) ?></div>
              <span><?= e($sessionName) ?></span>
            </div>
          </div>
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

      <?php elseif ($tab === 'reservations'): ?>
        <div class="page-header">
          <div>
            <h2>All Reservations</h2>
            <p>View and manage all upcoming reservations made by students or technicians.</p>
          </div>
          <div class="page-header-user">
            <span class="topbar-badge">Admin</span>
            <div class="topbar-user">
              <div class="topbar-avatar"><?= strtoupper(substr($sessionName,0,1)) ?></div>
              <span><?= e($sessionName) ?></span>
            </div>
          </div>
        </div>

        <div style="display:flex;justify-content:flex-end;margin-bottom:18px;">
          <form method="GET" action="" style="margin:0;">
            <input type="hidden" name="tab" value="reservations" />
            <input type="text" name="q" placeholder="Filter student username…" style="padding:8px;border-radius:6px;border:1px solid #ddd;" />
          </form>
        </div>

        <div class="card">
          <div class="card-title"><span class="icon">📋</span> Current Reservations</div>
          <div class="table-wrap">
            <table>
              <thead><tr><th>Student</th><th>Lab</th><th>Date</th><th>Time</th><th>Seat</th><th style="text-align:right;">Actions</th></tr></thead>
              <tbody>
                <?php if (empty($currentReservations)): ?>
                  <tr><td colspan="6" style="text-align:center;padding:18px;color:var(--muted);">No current reservations.</td></tr>
                <?php else: ?>
                  <?php foreach ($currentReservations as $r): ?>
                    <tr>
                      <td style="font-weight:600;"><?= e($r['student']) ?></td>
                      <td style="color:var(--primary);font-weight:600;"><?= e($r['lab']) ?></td>
                      <td style="font-family:var(--mono);"><?= e($r['date']) ?></td>
                      <td style="font-family:var(--mono);"><?= e($r['time_start']) ?> – <?= e($r['time_end']) ?></td>
                      <td><?= (int)$r['row'] ?>–<?= (int)$r['column'] ?></td>
                      <td style="text-align:right;">
                        <?php if ($r['canEdit']): ?>
                          <button class="btn btn-ghost btn-sm" onclick="openEditReservationModal('<?= e($r['_id']) ?>','<?= e($r['lab']) ?>','<?= e($r['date']) ?>','<?= e($r['time_start']) ?>','<?= e($r['time_end']) ?>','<?= (int)$r['row'] ?>','<?= (int)$r['column'] ?>')">Edit</button>
                        <?php else: ?>
                          <button class="btn btn-ghost btn-sm" disabled>Edit</button>
                        <?php endif; ?>
                        <?php if ($r['canDelete']): ?>
                          <form method="POST" action="?tab=reservations" style="display:inline;margin-left:8px;">
                            <input type="hidden" name="_action" value="admin_delete_reservation" />
                            <input type="hidden" name="reservation_id" value="<?= e($r['_id']) ?>" />
                            <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                          </form>
                        <?php else: ?>
                          <button class="btn btn-danger btn-sm" disabled style="margin-left:8px;">Delete</button>
                        <?php endif; ?>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>

        <div class="card" style="margin-top:14px;">
          <div class="card-title"><span class="icon">📚</span> Past Reservations</div>
          <div class="table-wrap">
            <table>
              <thead><tr><th>Student</th><th>Lab</th><th>Date</th><th>Time</th><th>Seat</th><th style="text-align:right;">Status</th></tr></thead>
              <tbody>
                <?php if (empty($pastReservations)): ?>
                  <tr><td colspan="6" style="text-align:center;padding:18px;color:var(--muted);">No past reservations.</td></tr>
                <?php else: ?>
                  <?php foreach ($pastReservations as $r): ?>
                    <tr>
                      <td style="font-weight:600;"><?= e($r['student']) ?></td>
                      <td style="color:var(--primary);font-weight:600;"><?= e($r['lab']) ?></td>
                      <td style="font-family:var(--mono);"><?= e($r['date']) ?></td>
                      <td style="font-family:var(--mono);"><?= e($r['time_start']) ?> – <?= e($r['time_end']) ?></td>
                      <td><?= (int)$r['row'] ?>–<?= (int)$r['column'] ?></td>
                      <td style="text-align:right;color:var(--muted);"><?= e($r['status']) ?></td>
                    </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Edit Reservation Modal -->
        <div class="modal-overlay" id="modal-edit-reservation-admin">
          <div class="modal">
            <div class="modal-header"><h3>Edit Reservation</h3><button class="modal-close" onclick="closeModal('modal-edit-reservation-admin')">&times;</button></div>
            <form method="POST" action="?tab=reservations">
              <input type="hidden" name="_action" value="admin_edit_reservation" />
              <input type="hidden" name="reservation_id" id="admin-edit-res-id" />
              <div class="form-grid">
                <div class="form-group">
                  <label>Lab</label>
                  <select class="form-control" name="lab_number" id="admin-edit-lab">
                    <?php foreach ($allLabs as $lab): ?>
                      <option value="<?= (int)$lab['number'] ?>"><?= 'Lab ' . (int)$lab['number'] . ' (' . e($lab['class']) . ')' ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="form-group">
                  <label>Date</label>
                  <input class="form-control" type="date" name="date" id="admin-edit-date" required />
                </div>
                <div class="form-group">
                  <label>Start</label>
                  <select class="form-control" name="time_start" id="admin-edit-start">
                    <?php foreach ($timeOptions as $t): ?><option><?= e($t) ?></option><?php endforeach; ?>
                  </select>
                </div>
                <div class="form-group">
                  <label>End</label>
                  <select class="form-control" name="time_end" id="admin-edit-end">
                    <?php foreach ($timeOptions as $t): ?><option><?= e($t) ?></option><?php endforeach; ?>
                  </select>
                </div>
                <div class="form-group">
                  <label>Row</label>
                  <input class="form-control" type="number" name="row" id="admin-edit-row" min="1" max="7" required />
                </div>
                <div class="form-group">
                  <label>Column</label>
                  <input class="form-control" type="number" name="column" id="admin-edit-column" min="1" max="5" required />
                </div>
              </div>
              <div class="form-actions">
                <button type="submit" class="btn btn-primary">Save Changes</button>
                <button type="button" class="btn btn-ghost" onclick="closeModal('modal-edit-reservation-admin')">Cancel</button>
              </div>
            </form>
          </div>
        </div>

      <?php elseif ($tab === 'settings'): ?>
        <div class="page-header">
          <div>
            <h2>System Settings</h2>
            <p>Configure global behaviour rules for EZLabs.</p>
          </div>
          <div class="page-header-user">
            <span class="topbar-badge">Admin</span>
            <div class="topbar-user">
              <div class="topbar-avatar"><?= strtoupper(substr($sessionName,0,1)) ?></div>
              <span><?= e($sessionName) ?></span>
            </div>
          </div>
        </div>
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
                <!-- Max seats removed (fixed 7x5 grid). -->
                <tr><td>Advance Booking</td>  <td style="font-family:var(--mono);color:var(--accent);"><?= (int)$settings['advance_days'] ?> days</td></tr>
                <tr><td>Cancel Cutoff</td>    <td style="font-family:var(--mono);color:var(--accent);"><?= (int)$settings['cancel_minutes'] ?> minutes</td></tr>
                <tr><td>No-show Grace</td>    <td style="font-family:var(--mono);color:var(--accent);"><?= (int)$settings['grace_minutes'] ?> minutes</td></tr>
              </tbody>
            </table>
          </div>
        </div>

      <?php endif; ?>

    </div>
  </div>
</div>

<script>

function openModal(id)  { document.getElementById(id).classList.add('open'); }
function closeModal(id) { document.getElementById(id).classList.remove('open'); }
document.querySelectorAll('.modal-overlay').forEach(function(o) {
  o.addEventListener('click', function(e) { if (e.target === o) o.classList.remove('open'); });
});
document.addEventListener('keydown', function(e) {
  if (e.key === 'Escape') document.querySelectorAll('.modal-overlay.open').forEach(function(m) { m.classList.remove('open'); });
});

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

function openEditEventModal(id, labId, name, desc, date, tStart, tEnd) {
  document.getElementById('edit-event-id').value    = id;
  document.getElementById('edit-event-name').value  = name;
  document.getElementById('edit-event-desc').value  = desc;
  document.getElementById('edit-event-date').value  = date;

  var labSel = document.getElementById('edit-event-lab');
  for (var i = 0; i < labSel.options.length; i++) {
    if (labSel.options[i].value === labId) { labSel.selectedIndex = i; break; }
  }

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

function openEditReservationModal(id, labLabel, date, tStart, tEnd, row, column) {
  document.getElementById('admin-edit-res-id').value = id;
  // select lab by matching label (Lab N (CLASS)) — fall back to first
  var labSel = document.getElementById('admin-edit-lab');
  for (var i = 0; i < labSel.options.length; i++) {
    if (labSel.options[i].text === labLabel) { labSel.selectedIndex = i; break; }
  }
  document.getElementById('admin-edit-date').value = date;

  function setSelect(id, val) {
    var s = document.getElementById(id);
    for (var j = 0; j < s.options.length; j++) {
      if (s.options[j].value === val) { s.selectedIndex = j; return; }
    }
    for (var j = 0; j < s.options.length; j++) { if (s.options[j].text === val) { s.selectedIndex = j; return; } }
  }
  setSelect('admin-edit-start', tStart);
  setSelect('admin-edit-end', tEnd);
  document.getElementById('admin-edit-row').value = row;
  document.getElementById('admin-edit-column').value = column;
  openModal('modal-edit-reservation-admin');
}

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