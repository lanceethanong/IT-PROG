<?php

declare(strict_types=1);

require_once __DIR__ . '/php/lib/bootstrap.php';
require_once __DIR__ . '/php/lib/repository.php';

$method = request_method();
$path = request_path();

function session_username(): string
{
    return (string) ($_SESSION['user']['username'] ?? '');
}

function dashboard_context(string $fallbackRole): array
{
    $username = request_query('username', session_username()) ?? '';
    $role = request_query('role', $fallbackRole) ?? $fallbackRole;
    return ['username' => $username, 'role' => $role];
}

function build_user_reservations(string $username): array
{
    $user = users_find_by_username($username);
    if ($user === null) {
        return [[], []];
    }

    $rows = [];
    foreach (reservations_all() as $reservation) {
        if ((string) ($reservation['user'] ?? '') !== (string) ($user['_id'] ?? '')) {
            continue;
        }

        $lab = labs_find_by_id((string) ($reservation['lab'] ?? ''));
        $status = reservation_status($reservation);
        $seats = seats_for_reservation((string) ($reservation['_id'] ?? ''));
        if ($seats === []) {
            $seats = [['row' => 0, 'column' => 0]];
        }

        foreach ($seats as $seat) {
            $rows[] = [
                '_id' => (string) ($reservation['_id'] ?? ''),
                'row' => (int) ($seat['row'] ?? 0),
                'column' => (int) ($seat['column'] ?? 0),
                'lab' => $lab ? ('Lab ' . $lab['number'] . ' (' . $lab['class'] . ')') : 'Unknown Lab',
                'time_start' => (string) ($reservation['time_start'] ?? ''),
                'time_end' => (string) ($reservation['time_end'] ?? ''),
                'date' => reservation_date_only($reservation),
                'createdAt' => (string) ($reservation['createdAt'] ?? ''),
                'status' => $status,
                'showDelete' => reservation_show_delete($reservation),
                'canEdit' => reservation_can_edit_student($reservation),
                'canDelete' => reservation_can_delete_student($reservation),
            ];
        }
    }

    usort($rows, static fn(array $a, array $b): int => strcmp((string) ($b['createdAt'] ?? ''), (string) ($a['createdAt'] ?? '')));

    $past = [];
    $upcoming = [];
    foreach ($rows as $row) {
        if (in_array((string) $row['status'], ['Completed', 'Cancelled'], true)) {
            $past[] = $row;
        } else {
            $upcoming[] = $row;
        }
    }

    return [$upcoming, $past];
}

function redirect_to_profile_for_role(string $role, string $username): never
{
    $pathRole = normalize_role_for_path($role);
    redirect_to('/dashboard/' . $pathRole . '/profile?username=' . rawurlencode($username));
}

function dashboard_time_options(): array
{
    $times = [];
    for ($slot = 0; $slot <= 24; $slot++) {
        $totalMinutes = (7 * 60) + ($slot * 30);
        $hour24 = intdiv($totalMinutes, 60);
        $minute = $totalMinutes % 60;
        $period = $hour24 >= 12 ? 'PM' : 'AM';
        $hour12 = $hour24 % 12;
        if ($hour12 === 0) {
            $hour12 = 12;
        }
        $times[] = sprintf('%02d:%02d %s', $hour12, $minute, $period);
    }

    return $times;
}

function seat_number_from_row_col(int $row, int $column): int
{
    return (($row - 1) * 5) + $column;
}

function time_to_slot_index(string $time): int
{
    $minutes = parse_time_to_minutes($time);
    return (int) floor(($minutes - (7 * 60)) / 30);
}

function slot_index_to_time(int $slot): string
{
    $options = dashboard_time_options();
    return $options[$slot] ?? '07:00 AM';
}

function parse_selected_slots_param(string $raw): array
{
    if ($raw === '') {
        return [];
    }

    $result = [];
    foreach (explode(',', $raw) as $token) {
        $slotKey = trim((string) $token);
        if ($slotKey === '') {
            continue;
        }
        if (preg_match('/^r([1-7])-c([1-5])-s([0-9]|1[0-9]|2[0-3])$/', $slotKey) === 1) {
            $result[$slotKey] = true;
        }
    }

    return array_keys($result);
}

function serialize_selected_slots_param(array $slots): string
{
    if ($slots === []) {
        return '';
    }

    $unique = [];
    foreach ($slots as $slotKey) {
        $slotKey = (string) $slotKey;
        if (preg_match('/^r([1-7])-c([1-5])-s([0-9]|1[0-9]|2[0-3])$/', $slotKey) === 1) {
            $unique[$slotKey] = true;
        }
    }

    return implode(',', array_keys($unique));
}

function build_dashboard_view_data(string $needRole, array $ctx, ?int $forcedLabNumber = null): array
{
    $today = new DateTime('now', new DateTimeZone('Asia/Manila'));
    $today->setTime(0, 0, 0);
    $maxDate = clone $today;
    $maxDate->modify('+7 days');

    $monthOffset = (int) request_query('month', '0');
    if ($monthOffset < -12) {
        $monthOffset = -12;
    }
    if ($monthOffset > 12) {
        $monthOffset = 12;
    }

    $monthDate = new DateTime('first day of this month', new DateTimeZone('Asia/Manila'));
    if ($monthOffset !== 0) {
        $monthDate->modify(($monthOffset > 0 ? '+' : '') . $monthOffset . ' month');
    }

    $labs = labs_all();
    $selectedLabNumber = $forcedLabNumber ?? (int) request_query('lab', '0');
    if ($selectedLabNumber === 0 && $labs !== []) {
        $selectedLabNumber = (int) ($labs[0]['number'] ?? 0);
    }

    $selectedDate = (string) request_query('date', $today->format('Y-m-d'));
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $selectedDate) !== 1) {
        $selectedDate = $today->format('Y-m-d');
    }
    if ($selectedDate < $today->format('Y-m-d')) {
        $selectedDate = $today->format('Y-m-d');
    }
    if ($selectedDate > $maxDate->format('Y-m-d')) {
        $selectedDate = $maxDate->format('Y-m-d');
    }

    $timeOptions = dashboard_time_options();

    $calendarDays = [];
    $start = clone $monthDate;
    $startDay = (int) $start->format('w');
    $start->modify('-' . $startDay . ' day');
    for ($i = 0; $i < 42; $i++) {
        $cellDate = clone $start;
        $cellDate->modify('+' . $i . ' day');

        $ymd = $cellDate->format('Y-m-d');
        $isCurrentMonth = $cellDate->format('m') === $monthDate->format('m');
        $isPast = $cellDate < $today;
        $isBeyond = $cellDate > $maxDate;
        $isSelected = $ymd === $selectedDate;
        $isSelectable = $isCurrentMonth && !$isPast && !$isBeyond;

        $calendarDays[] = [
            'weekday' => $cellDate->format('D'),
            'day' => $cellDate->format('j'),
            'date' => $ymd,
            'isSelectable' => $isSelectable,
            'isSelected' => $isSelected,
            'cellClass' => $isSelectable ? 'hoverable' : 'disabled',
        ];
    }

    $hours = [];
    for ($h = 7; $h <= 18; $h++) {
        $hours[] = sprintf('%d:00%s', $h <= 12 ? $h : $h - 12, $h < 12 ? 'am' : 'pm');
    }

    $searchQuery = trim((string) request_query('q', ''));
    $searchResults = [];
    if ($searchQuery !== '') {
        $q = strtolower($searchQuery);
        foreach (users_all() as $user) {
            $u = strtolower((string) ($user['username'] ?? ''));
            $e = strtolower((string) ($user['email'] ?? ''));
            if (str_contains($u, $q) || str_contains($e, $q)) {
                $searchResults[] = [
                    'username' => (string) ($user['username'] ?? ''),
                    'email' => (string) ($user['email'] ?? ''),
                    'role' => (string) ($user['role'] ?? ''),
                ];
            }
        }
    }

    $slotStatus = [];
    for ($row = 1; $row <= 7; $row++) {
        for ($column = 1; $column <= 5; $column++) {
            $slotStatus[$row . '-' . $column] = array_fill(0, 24, 'available');
        }
    }

    $selectedSlots = parse_selected_slots_param((string) request_query('sel', ''));
    $selectedSlotsParam = serialize_selected_slots_param($selectedSlots);
    $selectedMap = [];
    foreach ($selectedSlots as $slotKey) {
        $selectedMap[$slotKey] = true;
    }

    $selectedLab = labs_find_by_number($selectedLabNumber);
    $slotUnavailable = [];
    if ($selectedLab !== null) {
        foreach (reservations_all() as $reservation) {
            if ((string) ($reservation['lab'] ?? '') !== (string) ($selectedLab['_id'] ?? '')) {
                continue;
            }
            if (reservation_date_only($reservation) !== $selectedDate) {
                continue;
            }

            $existingStartIdx = time_to_slot_index((string) ($reservation['time_start'] ?? '07:00 AM'));
            $existingEndIdx = time_to_slot_index((string) ($reservation['time_end'] ?? '07:30 AM'));
            foreach (seats_for_reservation((string) ($reservation['_id'] ?? '')) as $seat) {
                $seatKey = (int) $seat['row'] . '-' . (int) $seat['column'];
                if (!isset($slotStatus[$seatKey])) {
                    continue;
                }
                for ($slot = $existingStartIdx; $slot < $existingEndIdx && $slot < 24; $slot++) {
                    if ($slot >= 0) {
                        $slotStatus[$seatKey][$slot] = 'legend unavailable';
                        $slotUnavailable[$seatKey . '-s' . $slot] = true;
                    }
                }
            }
        }
    }

    $now = new DateTime('now', new DateTimeZone('Asia/Manila'));
    $selectedIsToday = $selectedDate === $today->format('Y-m-d');
    $queryBase = 'username=' . rawurlencode((string) $ctx['username'])
        . '&lab=' . rawurlencode((string) $selectedLabNumber)
        . '&date=' . rawurlencode($selectedDate)
        . '&month=' . rawurlencode((string) $monthOffset)
        . '&q=' . rawurlencode($searchQuery);
    $studentQuery = (string) request_query('student', '');
    $queryBaseNoSel = $queryBase;
    if ($studentQuery !== '') {
        $queryBase .= '&student=' . rawurlencode($studentQuery);
        $queryBaseNoSel .= '&student=' . rawurlencode($studentQuery);
    }
    if ($selectedSlotsParam !== '') {
        $queryBase .= '&sel=' . rawurlencode($selectedSlotsParam);
    }

    $seatRows = [];
    for ($row = 1; $row <= 7; $row++) {
        $cols = [];
        for ($column = 1; $column <= 5; $column++) {
            $key = $row . '-' . $column;
            $slots = [];
            for ($slot = 0; $slot < 24; $slot++) {
                $slotKey = 'r' . $row . '-c' . $column . '-s' . $slot;
                $timeMinutes = (7 * 60) + ($slot * 30);
                $slotHour = intdiv($timeMinutes, 60);
                $slotMinute = $timeMinutes % 60;
                $slotDateTime = new DateTime($selectedDate . ' 00:00:00', new DateTimeZone('Asia/Manila'));
                $slotDateTime->setTime($slotHour, $slotMinute, 0);
                $isPastSlot = $selectedIsToday && $slotDateTime <= $now;
                $isUnavailable = isset($slotUnavailable[$key . '-s' . $slot]);
                $isReserved = isset($selectedMap[$slotKey]);

                $class = 'available';
                if ($isUnavailable) {
                    $class = 'legend unavailable';
                } elseif ($isPastSlot) {
                    $class = 'disabled';
                } elseif ($isReserved) {
                    $class = 'reserved';
                }

                $toggleUrl = null;
                if (!$isUnavailable && !$isPastSlot) {
                    $toggleUrl = '/dashboard/toggle-seat?' . $queryBase
                        . '&row=' . $row
                        . '&column=' . $column
                        . '&slot=' . $slot;
                }

                $slots[] = [
                    'slotKey' => $slotKey,
                    'class' => $class,
                    'clickable' => $toggleUrl !== null,
                    'toggleUrl' => $toggleUrl,
                ];
            }

            $cols[] = [
                'row' => $row,
                'column' => $column,
                'seatNumber' => seat_number_from_row_col($row, $column),
                'slots' => $slots,
            ];
        }
        $seatRows[] = $cols;
    }

    return [
        'title' => $needRole === 'Lab Technician' ? 'Technician Dashboard' : 'Student Dashboard',
        'username' => $ctx['username'],
        'role' => $needRole,
        'labs' => $labs,
        'selectedLabNumber' => $selectedLabNumber,
        'selectedLabName' => $selectedLab ? ('Lab ' . $selectedLab['number'] . ' (' . $selectedLab['class'] . ')') : '(not selected)',
        'selectedDate' => $selectedDate,
        'todayDate' => $today->format('Y-m-d'),
        'maxDate' => $maxDate->format('Y-m-d'),
        'monthOffset' => $monthOffset,
        'monthLabel' => $monthDate->format('F Y'),
        'calendarDays' => $calendarDays,
        'hours' => $hours,
        'timeOptions' => $timeOptions,
        'seatRows' => $seatRows,
        'selectedSlotsCount' => count($selectedSlots),
        'selectedSlotsParam' => $selectedSlotsParam,
        'queryBase' => $queryBase,
        'searchQuery' => $searchQuery,
        'searchResults' => $searchResults,
        'studentQuery' => $studentQuery,
        'reserveError' => request_query('reserveError', ''),
        'reserveSuccess' => request_query('reserveSuccess', ''),
        'searchError' => request_query('searchError', ''),
    ];
}

if ($method === 'GET' && $path === '/') {
    bypass_login_if_session_exists();
    render_view('home');
}

if ($method === 'GET' && $path === '/login') {
    bypass_login_if_session_exists();
    render_view('login', ['loginError' => $_GET['error'] ?? null]);
}

if ($method === 'POST' && $path === '/login') {
    $email = trim((string) ($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $remember = (string) ($_POST['remember'] ?? '') === 'on';

    if (preg_match('/@dlsu\.edu\.ph\s*$/i', $email) !== 1) {
        render_view('login', ['loginError' => 'Please use your DLSU email address.']);
    }

    $user = users_find_by_email($email);
    if ($user === null) {
        render_view('login', ['loginError' => 'Invalid email']);
    }

    if (!password_verify($password, (string) ($user['password'] ?? ''))) {
        render_view('login', ['loginError' => 'Invalid password']);
    }

    session_regenerate_id(true);

    $_SESSION['user'] = [
        'id' => (string) $user['_id'],
        'username' => (string) $user['username'],
        'role' => (string) $user['role'],
    ];

    $cookieOpts = [
        'expires' => $remember ? (time() + (86400 * 30)) : 0,
        'path' => cookie_path(),
        'httponly' => true,
        'samesite' => 'Lax',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    ];
    setcookie(session_name(), session_id(), $cookieOpts);

    $username = rawurlencode((string) $user['username']);
    if ((string) $user['role'] === 'Admin') {
        redirect_to('/admin');
    }
    if ((string) $user['role'] === 'Lab Technician') {
        redirect_to('/dashboard/technician?username=' . $username);
    }
    redirect_to('/dashboard/student?username=' . $username);
}

if ($method === 'GET' && $path === '/register') {
    render_view('register');
}

if ($method === 'POST' && $path === '/register') {
    $username = trim((string) ($_POST['username'] ?? ''));
    $email = trim((string) ($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $confirmPassword = (string) ($_POST['confirmPassword'] ?? '');

    if ($password !== $confirmPassword) {
        render_view('register', ['registerError' => 'Passwords do not match.']);
    }

    if (preg_match('/@dlsu\.edu\.ph\s*$/i', $email) !== 1) {
        render_view('register', ['registerError' => 'Please enter a DLSU email address.']);
    }

    if (users_find_by_email($email) !== null) {
        render_view('register', ['registerError' => 'Email already in use.']);
    }

    if (users_find_by_username($username) !== null) {
        render_view('register', ['registerError' => 'Username already taken.']);
    }

    if (strlen($password) < 8) {
        render_view('register', ['registerError' => 'Password must be at least 8 characters long.']);
    }

    $newUser = [
        '_id' => new_id(),
        'email' => $email,
        'username' => $username,
        'description' => '',
        'remember' => false,
        'password' => password_hash($password, PASSWORD_BCRYPT),
        'picture' => 'picture.jpg',
        'role' => 'Student',
        'createdAt' => now_iso(),
        'updatedAt' => now_iso(),
    ];

    users_upsert($newUser);
    $_SESSION['user'] = [
        'id' => $newUser['_id'],
        'username' => $username,
        'role' => 'Student',
    ];

    redirect_to('/dashboard/student?username=' . rawurlencode($username));
}

if ($method === 'GET' && $path === '/logout') {
    session_destroy();
    setcookie(session_name(), '', time() - 3600, cookie_path());
    redirect_to('/');
}

if ($method === 'GET' && $path === '/admin') {
    require_role('Admin');
    render_view('admin', [
        'title' => 'Admin Home',
        'page' => 'home',
        'adminName' => session_username(),
        'successMessage' => $_SESSION['successMessage'] ?? null,
    ]);
}

if ($method === 'GET' && $path === '/admin/view-labtech') {
    require_role('Admin');
    $techs = array_values(array_filter(users_all(), static fn(array $u): bool => (string) ($u['role'] ?? '') === 'Lab Technician'));
    $success = $_SESSION['successMessage'] ?? null;
    unset($_SESSION['successMessage']);

    render_view('admin', [
        'title' => 'View Lab Technicians',
        'page' => 'view',
        'techs' => $techs,
        'adminName' => session_username(),
        'successMessage' => $success,
    ]);
}

if ($method === 'GET' && $path === '/admin/add-labtech') {
    require_role('Admin');
    render_view('admin', [
        'title' => 'Add Lab Technician',
        'page' => 'add',
        'adminName' => session_username(),
    ]);
}

if ($method === 'POST' && $path === '/admin/add-labtech') {
    require_role('Admin');
    $username = trim((string) ($_POST['username'] ?? ''));
    $email = trim((string) ($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    if (users_find_by_username($username) !== null) {
        render_view('admin', [
            'title' => 'Add Lab Technician',
            'page' => 'add',
            'registerError' => 'Username already taken.',
            'adminName' => session_username(),
        ]);
    }

    $newTech = [
        '_id' => new_id(),
        'email' => $email,
        'username' => $username,
        'description' => '',
        'remember' => false,
        'password' => password_hash($password, PASSWORD_BCRYPT),
        'picture' => 'picture.jpg',
        'role' => 'Lab Technician',
        'createdAt' => now_iso(),
        'updatedAt' => now_iso(),
    ];
    users_upsert($newTech);
    $_SESSION['successMessage'] = 'Lab Technician added successfully!';
    redirect_to('/admin/view-labtech');
}

if ($method === 'GET' && $path === '/admin/remove-labtech') {
    require_role('Admin');
    render_view('admin', [
        'title' => 'Remove Lab Technician',
        'page' => 'remove',
        'adminName' => session_username(),
    ]);
}

if ($method === 'POST' && $path === '/admin/remove-labtech') {
    require_role('Admin');
    $username = trim((string) ($_POST['username'] ?? ''));
    $user = users_find_by_username($username);

    if ($user === null || (string) ($user['role'] ?? '') !== 'Lab Technician') {
        render_view('admin', [
            'title' => 'Remove Lab Technician',
            'page' => 'remove',
            'removeError' => 'No such Lab Technician found.',
            'adminName' => session_username(),
        ]);
    }

    users_delete_by_username($username);
    $_SESSION['successMessage'] = 'Lab Technician removed successfully!';
    redirect_to('/admin/view-labtech');
}

if ($method === 'GET' && ($path === '/dashboard/student' || $path === '/dashboard/technician')) {
    $needRole = $path === '/dashboard/technician' ? 'Lab Technician' : 'Student';
    require_role($needRole);
    $ctx = dashboard_context($needRole);

    render_view('dashboard', build_dashboard_view_data($needRole, $ctx));
}

if ($method === 'GET' && preg_match('#^/dashboard/(student|technician)/lab/(\d+)$#', $path, $m)) {
    $needRole = $m[1] === 'technician' ? 'Lab Technician' : 'Student';
    require_role($needRole);
    $ctx = dashboard_context($needRole);

    render_view('dashboard', build_dashboard_view_data($needRole, $ctx, (int) $m[2]));
}

if ($method === 'GET' && $path === '/dashboard/toggle-seat') {
    require_login();
    $sessionUser = $_SESSION['user'] ?? null;
    if (!is_array($sessionUser)) {
        redirect_to('/login');
    }

    $username = request_query('username', session_username()) ?? session_username();
    $role = (string) ($sessionUser['role'] ?? 'Student');
    $rolePath = normalize_role_for_path($role);

    $labNumber = (int) request_query('lab', '0');
    $date = format_manila_date((string) request_query('date', ''));
    $month = (int) request_query('month', '0');
    $row = (int) request_query('row', '0');
    $column = (int) request_query('column', '0');
    $slot = (int) request_query('slot', '-1');
    $searchQuery = (string) request_query('q', '');
    $studentQuery = (string) request_query('student', '');
    $selectedSlots = parse_selected_slots_param((string) request_query('sel', ''));

    $base = '/dashboard/' . $rolePath
        . '?username=' . rawurlencode($username)
        . '&lab=' . rawurlencode((string) $labNumber)
        . '&date=' . rawurlencode($date)
        . '&month=' . rawurlencode((string) $month)
        . '&q=' . rawurlencode($searchQuery)
        . '&student=' . rawurlencode($studentQuery);

    if ($labNumber < 1 || $date === '' || $row < 1 || $row > 7 || $column < 1 || $column > 5 || $slot < 0 || $slot > 23) {
        redirect_to($base);
    }

    $slotKey = 'r' . $row . '-c' . $column . '-s' . $slot;
    $set = [];
    foreach ($selectedSlots as $s) {
        $set[$s] = true;
    }
    if (isset($set[$slotKey])) {
        unset($set[$slotKey]);
    } else {
        $set[$slotKey] = true;
    }

    $newSel = serialize_selected_slots_param(array_keys($set));
    if ($newSel !== '') {
        $base .= '&sel=' . rawurlencode($newSel);
    }
    redirect_to($base);
}

if ($method === 'POST' && $path === '/dashboard/reserve') {
    require_login();
    $sessionUser = $_SESSION['user'] ?? null;
    if (!is_array($sessionUser)) {
        redirect_to('/login');
    }

    $role = (string) ($sessionUser['role'] ?? 'Student');
    if (!in_array($role, ['Student', 'Lab Technician'], true)) {
        redirect_to('/login');
    }

    $rolePath = normalize_role_for_path($role);
    $username = (string) ($sessionUser['username'] ?? '');

    $labNumber = (int) ($_POST['lab'] ?? 0);
    $date = format_manila_date((string) ($_POST['date'] ?? ''));
    $month = (int) ($_POST['month'] ?? 0);
    $anonymity = isset($_POST['anonymity']) && $_POST['anonymity'] === 'on';
    $studentUsername = trim((string) ($_POST['student_username'] ?? ''));
    $searchQuery = trim((string) ($_POST['q'] ?? ''));
    $selectedSlotsParam = (string) ($_POST['sel'] ?? '');
    $selectedSlots = parse_selected_slots_param($selectedSlotsParam);

    $baseQuery = 'username=' . rawurlencode($username)
        . '&lab=' . rawurlencode((string) $labNumber)
        . '&date=' . rawurlencode($date)
        . '&month=' . rawurlencode((string) $month)
        . '&q=' . rawurlencode($searchQuery)
        . '&student=' . rawurlencode($studentUsername);
    if ($selectedSlotsParam !== '') {
        $baseQuery .= '&sel=' . rawurlencode($selectedSlotsParam);
    }

    if ($labNumber < 1 || $date === '') {
        redirect_to('/dashboard/' . $rolePath . '?' . $baseQuery . '&reserveError=' . rawurlencode('Please select a valid lab and date.'));
    }

    $targetUser = users_find_by_username($username);
    if ($role === 'Lab Technician') {
        $targetUser = users_find_by_username($studentUsername);
        if ($targetUser === null || (string) ($targetUser['role'] ?? '') !== 'Student') {
            redirect_to('/dashboard/technician?' . $baseQuery . '&reserveError=' . rawurlencode('Enter a valid student username.'));
        }
    }

    if ($targetUser === null) {
        redirect_to('/dashboard/' . $rolePath . '?' . $baseQuery . '&reserveError=' . rawurlencode('User account not found.'));
    }

    $lab = labs_find_by_number($labNumber);
    if ($lab === null) {
        redirect_to('/dashboard/' . $rolePath . '?' . $baseQuery . '&reserveError=' . rawurlencode('Selected lab does not exist.'));
    }

    if ($selectedSlots === []) {
        redirect_to('/dashboard/' . $rolePath . '?' . $baseQuery . '&reserveError=' . rawurlencode('Please select at least one seat and time slot.'));
    }

    $slotData = [];
    foreach ($selectedSlots as $slotKey) {
        if (preg_match('/^r([1-7])-c([1-5])-s([0-9]|1[0-9]|2[0-3])$/', $slotKey, $m) !== 1) {
            continue;
        }
        $slotData[] = [
            'row' => (int) $m[1],
            'column' => (int) $m[2],
            'slot' => (int) $m[3],
        ];
    }

    if ($slotData === []) {
        redirect_to('/dashboard/' . $rolePath . '?' . $baseQuery . '&reserveError=' . rawurlencode('Invalid seat selection.'));
    }

    $seatGroups = [];
    foreach ($slotData as $slot) {
        $seatKey = $slot['row'] . '_' . $slot['column'];
        if (!isset($seatGroups[$seatKey])) {
            $seatGroups[$seatKey] = [];
        }
        $seatGroups[$seatKey][] = $slot['slot'];
    }

    foreach ($seatGroups as $seatKey => $slots) {
        sort($slots);
        foreach ($slots as $i => $current) {
            if ($i > 0 && $current !== ($slots[$i - 1] + 1)) {
                redirect_to('/dashboard/' . $rolePath . '?' . $baseQuery . '&reserveError=' . rawurlencode('Selected slots for each seat must be consecutive.'));
            }
        }
    }

    $allSlots = array_map(static fn(array $x): int => (int) $x['slot'], $slotData);
    $globalStartSlot = min($allSlots);
    $globalEndSlot = max($allSlots) + 1;
    $timeStart = slot_index_to_time($globalStartSlot);
    $timeEnd = slot_index_to_time($globalEndSlot);

    $candidateSeats = [];
    foreach (array_keys($seatGroups) as $seatKey) {
        [$r, $c] = array_map('intval', explode('_', $seatKey));
        $candidateSeats[] = ['row' => $r, 'column' => $c];
    }

    $candidate = [
        'lab' => (string) ($lab['_id'] ?? ''),
        'date' => $date,
        'time_start' => $timeStart,
        'time_end' => $timeEnd,
    ];

    if (reservation_conflicts($candidate, $candidateSeats)) {
        redirect_to('/dashboard/' . $rolePath . '?' . $baseQuery . '&reserveError=' . rawurlencode('Seat is already reserved for that time range.'));
    }

    $reservation = [
        '_id' => new_id(),
        'time_start' => $timeStart,
        'time_end' => $timeEnd,
        'user' => (string) ($targetUser['_id'] ?? ''),
        'lab' => (string) ($lab['_id'] ?? ''),
        'date' => $date,
        'anonymity' => $anonymity,
        'status' => 'Scheduled',
        'createdAt' => now_iso(),
        'updatedAt' => now_iso(),
    ];

    $all = reservations_all();
    $all[] = $reservation;
    reservations_save($all);
    seats_replace_for_reservation((string) $reservation['_id'], $candidateSeats);

    if ($role === 'Lab Technician') {
        redirect_to('/dashboard/technician/reservation-list?username=' . rawurlencode($username));
    }

    redirect_to('/dashboard/student/profile?username=' . rawurlencode($username));
}

if ($method === 'GET' && $path === '/dashboard/search-user') {
    require_login();
    $sessionUser = $_SESSION['user'] ?? null;
    if (!is_array($sessionUser)) {
        redirect_to('/login');
    }

    $query = strtolower(trim((string) request_query('q', '')));
    $username = request_query('username', session_username()) ?? session_username();
    $role = (string) request_query('role', (string) ($sessionUser['role'] ?? 'Student'));
    $lab = (string) request_query('lab', '');
    $date = (string) request_query('date', '');
    $month = (string) request_query('month', '0');
    $student = (string) request_query('student', '');
    $sel = (string) request_query('sel', '');
    $rolePath = normalize_role_for_path($role);

    redirect_to('/dashboard/' . $rolePath
        . '?username=' . rawurlencode($username)
        . '&q=' . rawurlencode($query)
        . '&lab=' . rawurlencode($lab)
        . '&date=' . rawurlencode($date)
        . '&month=' . rawurlencode($month)
        . '&student=' . rawurlencode($student)
        . '&sel=' . rawurlencode($sel));
}

if ($method === 'GET' && ($path === '/dashboard/technician/help' || $path === '/dashboard/student/help')) {
    $needRole = str_contains($path, '/technician/') ? 'Lab Technician' : 'Student';
    require_role($needRole);

    render_view('help', [
        'username' => request_query('username', session_username()) ?? session_username(),
        'role' => $needRole,
    ]);
}

if ($method === 'GET' && ($path === '/dashboard/technician/profile' || $path === '/dashboard/student/profile')) {
    $needRole = str_contains($path, '/technician/') ? 'Lab Technician' : 'Student';
    require_role($needRole);

    $username = request_query('username', session_username()) ?? session_username();
    $user = users_find_by_username($username);
    if ($user === null || (string) ($user['role'] ?? '') !== $needRole) {
        route_not_found();
    }

    [$upcoming, $past] = build_user_reservations($username);

    render_view('profile', [
        'title' => $needRole . ' Profile',
        'username' => $username,
        'role' => $needRole,
        'description' => (string) ($user['description'] ?? ''),
        'upcomingReservations' => $upcoming,
        'pastReservations' => $past,
    ]);
}

if ($method === 'POST' && $path === '/profile/update') {
    require_login();
    $sessionUser = $_SESSION['user'] ?? null;
    if (!is_array($sessionUser)) {
        redirect_to('/login');
    }

    $username = (string) ($sessionUser['username'] ?? '');
    $role = (string) ($sessionUser['role'] ?? 'Student');
    $description = trim((string) ($_POST['description'] ?? ''));

    $user = users_find_by_username($username);
    if ($user !== null) {
        $user['description'] = $description;
        $user['updatedAt'] = now_iso();
        users_upsert($user);
    }

    redirect_to_profile_for_role($role, $username);
}

if ($method === 'POST' && $path === '/account/change-password') {
    require_login();
    $sessionUser = $_SESSION['user'] ?? null;
    if (!is_array($sessionUser)) {
        redirect_to('/login');
    }

    $username = (string) ($sessionUser['username'] ?? '');
    $role = (string) ($sessionUser['role'] ?? 'Student');
    $currentPassword = (string) ($_POST['currentPassword'] ?? '');
    $newPassword = (string) ($_POST['newPassword'] ?? '');
    $confirmPassword = (string) ($_POST['confirmPassword'] ?? '');

    $user = users_find_by_username($username);
    if ($user === null) {
        redirect_to('/login');
    }

    if (!password_verify($currentPassword, (string) ($user['password'] ?? ''))) {
        redirect_to_profile_for_role($role, $username);
    }

    if ($newPassword === '' || strlen($newPassword) < 8 || $newPassword !== $confirmPassword) {
        redirect_to_profile_for_role($role, $username);
    }

    $user['password'] = password_hash($newPassword, PASSWORD_BCRYPT);
    $user['updatedAt'] = now_iso();
    users_upsert($user);

    redirect_to_profile_for_role($role, $username);
}

if ($method === 'GET' && $path === '/account/change-password') {
    require_login();
    $sessionUser = $_SESSION['user'] ?? null;
    if (!is_array($sessionUser)) {
        redirect_to('/login');
    }

    render_view('change_password', [
        'username' => (string) ($sessionUser['username'] ?? ''),
        'role' => (string) ($sessionUser['role'] ?? 'Student'),
    ]);
}

if ($method === 'POST' && $path === '/account/delete') {
    require_login();
    $sessionUser = $_SESSION['user'] ?? null;
    if (!is_array($sessionUser)) {
        redirect_to('/login');
    }

    $username = (string) ($sessionUser['username'] ?? '');
    users_delete_by_username($username);
    session_destroy();
    setcookie(session_name(), '', time() - 3600, cookie_path());
    redirect_to('/login');
}

if ($method === 'POST' && $path === '/reservation/delete') {
    require_login();
    $sessionUser = $_SESSION['user'] ?? null;
    if (!is_array($sessionUser)) {
        redirect_to('/login');
    }

    $reservationId = trim((string) ($_POST['reservation_id'] ?? ''));
    if ($reservationId !== '') {
        $reservation = reservations_find_by_id($reservationId);
        if ($reservation !== null) {
            $role = (string) ($sessionUser['role'] ?? 'Student');
            $allowed = false;

            if ($role === 'Lab Technician') {
                $allowed = reservation_can_delete_technician($reservation);
            } else {
                $ownerId = (string) ($sessionUser['id'] ?? '');
                $allowed = ((string) ($reservation['user'] ?? '') === $ownerId)
                    && reservation_can_delete_student($reservation);
            }

            if ($allowed) {
                reservations_delete($reservationId);
            }
        }
    }

    $role = (string) ($sessionUser['role'] ?? 'Student');
    $username = (string) ($sessionUser['username'] ?? '');
    if ($role === 'Lab Technician') {
        redirect_to('/dashboard/technician/reservation-list?username=' . rawurlencode($username));
    }

    redirect_to('/dashboard/student/profile?username=' . rawurlencode($username));
}

if ($method === 'GET' && $path === '/reservation/edit') {
    require_login();
    $sessionUser = $_SESSION['user'] ?? null;
    if (!is_array($sessionUser)) {
        redirect_to('/login');
    }

    $reservationId = trim((string) request_query('id', ''));
    $reservation = $reservationId !== '' ? reservations_find_by_id($reservationId) : null;
    if ($reservation === null) {
        route_not_found();
    }

    $role = (string) ($sessionUser['role'] ?? 'Student');
    $ownerId = (string) ($sessionUser['id'] ?? '');
    $isOwner = (string) ($reservation['user'] ?? '') === $ownerId;
    $canEdit = $role === 'Lab Technician'
        ? reservation_can_edit_technician($reservation)
        : ($isOwner && reservation_can_edit_student($reservation));

    if (!$canEdit) {
        $redirectUser = rawurlencode((string) ($sessionUser['username'] ?? ''));
        if ($role === 'Lab Technician') {
            redirect_to('/dashboard/technician/reservation-list?username=' . $redirectUser);
        }
        redirect_to('/dashboard/student/profile?username=' . $redirectUser);
    }

    $seat = seats_for_reservation((string) ($reservation['_id'] ?? ''))[0] ?? ['row' => 1, 'column' => 1];
    $lab = labs_find_by_id((string) ($reservation['lab'] ?? ''));

    render_view('reservation_edit', [
        'title' => 'Edit Reservation',
        'username' => (string) ($sessionUser['username'] ?? ''),
        'role' => $role,
        'reservation' => $reservation,
        'seat' => $seat,
        'labNumber' => (int) ($lab['number'] ?? 0),
        'labs' => labs_all(),
    ]);
}

if ($method === 'POST' && $path === '/reservation/edit') {
    require_login();
    $sessionUser = $_SESSION['user'] ?? null;
    if (!is_array($sessionUser)) {
        redirect_to('/login');
    }

    $reservationId = trim((string) ($_POST['reservation_id'] ?? ''));
    $reservation = $reservationId !== '' ? reservations_find_by_id($reservationId) : null;
    if ($reservation === null) {
        route_not_found();
    }

    $role = (string) ($sessionUser['role'] ?? 'Student');
    $ownerId = (string) ($sessionUser['id'] ?? '');
    $isOwner = (string) ($reservation['user'] ?? '') === $ownerId;
    $canEdit = $role === 'Lab Technician'
        ? reservation_can_edit_technician($reservation)
        : ($isOwner && reservation_can_edit_student($reservation));

    if (!$canEdit) {
        $redirectUser = rawurlencode((string) ($sessionUser['username'] ?? ''));
        if ($role === 'Lab Technician') {
            redirect_to('/dashboard/technician/reservation-list?username=' . $redirectUser);
        }
        redirect_to('/dashboard/student/profile?username=' . $redirectUser);
    }

    $date = format_manila_date((string) ($_POST['date'] ?? reservation_date_only($reservation)));
    $timeStart = (string) ($_POST['time_start'] ?? (string) ($reservation['time_start'] ?? ''));
    $timeEnd = (string) ($_POST['time_end'] ?? (string) ($reservation['time_end'] ?? ''));
    $row = (int) ($_POST['row'] ?? 1);
    $column = (int) ($_POST['column'] ?? 1);
    $labNumber = (int) ($_POST['lab_number'] ?? 0);
    $lab = labs_find_by_number($labNumber);

    if ($lab === null || $date === '' || $row < 1 || $row > 7 || $column < 1 || $column > 5 || parse_time_to_minutes($timeEnd) <= parse_time_to_minutes($timeStart)) {
        redirect_to('/reservation/edit?id=' . rawurlencode($reservationId));
    }

    $candidate = [
        'lab' => (string) ($lab['_id'] ?? ''),
        'date' => $date,
        'time_start' => $timeStart,
        'time_end' => $timeEnd,
    ];
    $candidateSeats = [['row' => $row, 'column' => $column]];

    if (reservation_conflicts($candidate, $candidateSeats, $reservationId)) {
        redirect_to('/reservation/edit?id=' . rawurlencode($reservationId));
    }

    $all = reservations_all();
    foreach ($all as $i => $item) {
        if ((string) ($item['_id'] ?? '') !== $reservationId) {
            continue;
        }
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

    $username = (string) ($sessionUser['username'] ?? '');
    if ($role === 'Lab Technician') {
        redirect_to('/dashboard/technician/reservation-list?username=' . rawurlencode($username));
    }
    redirect_to('/dashboard/student/profile?username=' . rawurlencode($username));
}

if ($method === 'GET' && $path === '/dashboard/technician/reservation-list') {
    require_role('Lab Technician');
    $username = request_query('username', session_username()) ?? session_username();

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

    render_view('reservation', [
        'username' => $username,
        'role' => 'Lab Technician',
        'currentReservations' => $currentReservations,
        'pastReservations' => $pastReservations,
    ]);
}

if ($method === 'GET' && preg_match('#^/dashboard/view-profile/(.+)$#', $path, $m)) {
    require_login();
    $currentUsername = request_query('username', session_username()) ?? session_username();
    $targetUsername = rawurldecode((string) $m[1]);

    $currentUser = users_find_by_username($currentUsername);
    $viewedUser = users_find_by_username($targetUsername);
    if ($currentUser === null || $viewedUser === null) {
        route_not_found();
    }

    if ($currentUsername === $targetUsername) {
        $pathRole = normalize_role_for_path((string) ($currentUser['role'] ?? 'Student'));
        redirect_to('/dashboard/' . $pathRole . '/profile?username=' . rawurlencode($currentUsername));
    }

    [$upcoming, $past] = build_user_reservations($targetUsername);

    render_view('viewprofile', [
        'title' => $targetUsername . "'s Profile",
        'username' => $currentUsername,
        'role' => (string) ($currentUser['role'] ?? 'Student'),
        'currentUser' => [
            'username' => $currentUsername,
            'role' => (string) ($currentUser['role'] ?? 'Student'),
        ],
        'viewedUser' => [
            'username' => (string) ($viewedUser['username'] ?? ''),
            'role' => (string) ($viewedUser['role'] ?? ''),
            'description' => (string) ($viewedUser['description'] ?? ''),
        ],
        'upcomingReservations' => $upcoming,
        'pastReservations' => $past,
    ]);
}

if ($method === 'POST' && $path === '/api/log-error') {
    $body = request_json();
    errors_add([
        '_id' => new_id(),
        'message' => (string) ($body['message'] ?? 'Unknown error'),
        'stack' => (string) ($body['stack'] ?? ''),
        'source' => (string) ($body['source'] ?? 'Unknown'),
        'timestamp' => now_iso(),
        'user' => session_username(),
    ]);
    no_content_response();
}

if ($method === 'DELETE' && preg_match('#^/api/users/(.+)$#', $path, $m)) {
    $needle = rawurldecode((string) $m[1]);
    $user = users_find_by_username($needle);
    if ($user === null) {
        $user = users_find_by_id($needle);
    }

    if ($user === null) {
        json_response(['error' => 'User not found'], 404);
    }

    $deleted = users_delete_by_username((string) $user['username']);
    if (!$deleted) {
        json_response(['error' => 'Failed to delete account'], 500);
    }

    json_response(['success' => true, 'message' => 'Account deleted successfully']);
}

if ($method === 'DELETE' && preg_match('#^/api/reservation/(.+)$#', $path, $m)) {
    $id = (string) $m[1];
    $reservation = reservations_find_by_id($id);
    if ($reservation === null) {
        json_response(['error' => 'Reservation not found'], 404);
    }

    require_login();
    $sessionUser = $_SESSION['user'] ?? null;
    if (!is_array($sessionUser)) {
        json_response(['error' => 'Unauthorized'], 401);
    }

    $role = (string) ($sessionUser['role'] ?? 'Student');
    $allowed = false;
    if ($role === 'Lab Technician') {
        $allowed = reservation_can_delete_technician($reservation);
    } else {
        $allowed = ((string) ($reservation['user'] ?? '') === (string) ($sessionUser['id'] ?? ''))
            && reservation_can_delete_student($reservation);
    }

    if (!$allowed) {
        json_response(['error' => 'Delete not allowed at this time'], 403);
    }

    reservations_delete($id);
    json_response(['success' => true, 'message' => 'Reservation deleted successfully']);
}

if ($method === 'DELETE' && preg_match('#^/api/reservations/(.+)$#', $path, $m)) {
    $id = (string) $m[1];
    $reservation = reservations_find_by_id($id);
    if ($reservation === null) {
        json_response(['error' => 'Reservation not found'], 404);
    }

    require_login();
    $sessionUser = $_SESSION['user'] ?? null;
    if (!is_array($sessionUser)) {
        json_response(['error' => 'Unauthorized'], 401);
    }

    $role = (string) ($sessionUser['role'] ?? 'Student');
    $allowed = false;
    if ($role === 'Lab Technician') {
        $allowed = reservation_can_delete_technician($reservation);
    } else {
        $allowed = ((string) ($reservation['user'] ?? '') === (string) ($sessionUser['id'] ?? ''))
            && reservation_can_delete_student($reservation);
    }

    if (!$allowed) {
        json_response(['error' => 'Delete not allowed at this time'], 403);
    }

    reservations_delete($id);
    json_response(['success' => true, 'message' => 'Reservation deleted successfully']);
}

if ($method === 'POST' && preg_match('#^/api/users/(.+)/change-password$#', $path, $m)) {
    $username = rawurldecode((string) $m[1]);
    $body = request_json();
    $currentPassword = (string) ($body['currentPassword'] ?? '');
    $newPassword = (string) ($body['newPassword'] ?? '');

    $user = users_find_by_username($username);
    if ($user === null) {
        json_response(['error' => 'User not found'], 404);
    }

    if (!password_verify($currentPassword, (string) ($user['password'] ?? ''))) {
        json_response(['error' => 'Current password is incorrect'], 401);
    }

    $user['password'] = password_hash($newPassword, PASSWORD_BCRYPT);
    $user['updatedAt'] = now_iso();
    users_upsert($user);

    json_response(['success' => true, 'message' => 'Password updated successfully']);
}

if ($method === 'PUT' && preg_match('#^/api/users/(.+)$#', $path, $m)) {
    $needle = rawurldecode((string) $m[1]);
    $body = request_json();
    $description = (string) ($body['description'] ?? '');

    $user = users_find_by_username($needle);
    if ($user === null) {
        $user = users_find_by_id($needle);
    }

    if ($user === null) {
        json_response(['error' => 'User not found'], 404);
    }

    $user['description'] = $description;
    $user['updatedAt'] = now_iso();
    users_upsert($user);

    json_response(['success' => true, 'user' => user_without_password($user)]);
}

if ($method === 'GET' && preg_match('#^/api/users/search/(.*)$#', $path, $m)) {
    $query = strtolower(rawurldecode((string) $m[1]));
    $matches = [];

    foreach (users_all() as $user) {
        $username = strtolower((string) ($user['username'] ?? ''));
        $email = strtolower((string) ($user['email'] ?? ''));
        if ($query === '' || str_contains($username, $query) || str_contains($email, $query)) {
            $matches[] = user_without_password($user);
        }
    }

    json_response($matches);
}

if ($method === 'GET' && $path === '/api/labs') {
    json_response(labs_all());
}

if ($method === 'GET' && preg_match('#^/api/labs/(\d+)/check-availability$#', $path, $m)) {
    $labNumber = (int) $m[1];
    $date = (string) request_query('date', '');
    $timeStart = (string) request_query('time_start', '');
    $timeEnd = (string) request_query('time_end', '');

    if ($date === '' || $timeStart === '' || $timeEnd === '') {
        json_response(['error' => 'Missing required query parameters: date, time_start, time_end'], 400);
    }

    $lab = labs_find_by_number($labNumber);
    if ($lab === null) {
        json_response(['error' => 'Lab not found'], 404);
    }

    $candidate = [
        'lab' => $lab['_id'],
        'date' => format_manila_date($date),
        'time_start' => $timeStart,
        'time_end' => $timeEnd,
    ];

    $occupied = [];
    foreach (reservations_all() as $reservation) {
        if ((string) ($reservation['lab'] ?? '') !== (string) ($lab['_id'] ?? '')) {
            continue;
        }

        if (reservation_date_only($reservation) !== $candidate['date']) {
            continue;
        }

        $s1 = parse_time_to_minutes((string) ($reservation['time_start'] ?? ''));
        $e1 = parse_time_to_minutes((string) ($reservation['time_end'] ?? ''));
        $s2 = parse_time_to_minutes($timeStart);
        $e2 = parse_time_to_minutes($timeEnd);
        if (!($s1 < $e2 && $e1 > $s2)) {
            continue;
        }

        foreach (seats_for_reservation((string) ($reservation['_id'] ?? '')) as $seat) {
            $occupied[] = ['row' => (int) $seat['row'], 'column' => (int) $seat['column']];
        }
    }

    $available = [];
    for ($row = 1; $row <= 7; $row++) {
        for ($column = 1; $column <= 5; $column++) {
            $found = false;
            foreach ($occupied as $seat) {
                if ($seat['row'] === $row && $seat['column'] === $column) {
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                $available[] = ['row' => $row, 'column' => $column];
            }
        }
    }

    json_response([
        'success' => true,
        'availableSeats' => $available,
        'occupiedSeats' => $occupied,
        'totalSeats' => 35,
        'availableCount' => count($available),
        'occupiedCount' => count($occupied),
    ]);
}

if ($method === 'GET' && preg_match('#^/api/labs/(.+)/available-seats$#', $path, $m)) {
    $labId = (string) $m[1];
    $date = (string) request_query('date', '');
    $timeStart = (string) request_query('time_start', '');
    $timeEnd = (string) request_query('time_end', '');

    $lab = labs_find_by_id($labId);
    if ($lab === null) {
        json_response(['error' => 'Lab not found'], 404);
    }

    $occupied = [];
    foreach (reservations_all() as $reservation) {
        if ((string) ($reservation['lab'] ?? '') !== $labId) {
            continue;
        }

        if (reservation_date_only($reservation) !== format_manila_date($date)) {
            continue;
        }

        $rs = parse_time_to_minutes((string) ($reservation['time_start'] ?? ''));
        $re = parse_time_to_minutes((string) ($reservation['time_end'] ?? ''));
        $qs = parse_time_to_minutes($timeStart);
        $qe = parse_time_to_minutes($timeEnd);
        if (!($rs < $qe && $re > $qs)) {
            continue;
        }

        foreach (seats_for_reservation((string) ($reservation['_id'] ?? '')) as $seat) {
            $occupied[] = ['row' => (int) $seat['row'], 'column' => (int) $seat['column']];
        }
    }

    $available = [];
    for ($row = 1; $row <= 7; $row++) {
        for ($column = 1; $column <= 5; $column++) {
            $isOccupied = false;
            foreach ($occupied as $seat) {
                if ($seat['row'] === $row && $seat['column'] === $column) {
                    $isOccupied = true;
                    break;
                }
            }
            if (!$isOccupied) {
                $available[] = ['row' => $row, 'column' => $column];
            }
        }
    }

    json_response(['availableSeats' => $available, 'occupiedSeats' => $occupied]);
}

if ($method === 'GET' && preg_match('#^/api/labs/(.+)$#', $path, $m)) {
    $id = (string) $m[1];
    $lab = labs_find_by_id($id);
    if ($lab === null) {
        json_response(['error' => 'Lab not found'], 404);
    }
    json_response($lab);
}

if ($method === 'POST' && $path === '/api/labs') {
    $body = request_json();
    $lab = labs_insert([
        '_id' => new_id(),
        'class' => (string) ($body['class'] ?? ''),
        'number' => (int) ($body['number'] ?? 0),
        'createdAt' => now_iso(),
        'updatedAt' => now_iso(),
    ]);
    json_response($lab, 201);
}

if ($method === 'GET' && $path === '/api/users') {
    $users = array_map('user_without_password', users_all());
    json_response($users);
}

if ($method === 'GET' && preg_match('#^/api/users/(.+)/reservations$#', $path, $m)) {
    $userId = (string) $m[1];
    $result = [];
    foreach (reservations_all() as $reservation) {
        if ((string) ($reservation['user'] ?? '') !== $userId) {
            continue;
        }

        $lab = labs_find_by_id((string) ($reservation['lab'] ?? ''));
        $enriched = $reservation;
        $enriched['lab'] = $lab;
        $enriched['status'] = reservation_status($reservation);
        $enriched['date'] = reservation_date_only($reservation);
        $result[] = $enriched;
    }
    usort($result, static fn(array $a, array $b): int => strcmp((string) ($b['date'] ?? ''), (string) ($a['date'] ?? '')));
    json_response($result);
}

if ($method === 'GET' && preg_match('#^/api/users/(.+)$#', $path, $m)) {
    $needle = rawurldecode((string) $m[1]);
    $user = users_find_by_id($needle);
    if ($user === null) {
        $user = users_find_by_username($needle);
    }

    if ($user === null) {
        json_response(['error' => 'User not found'], 404);
    }

    json_response(user_without_password($user));
}

if ($method === 'POST' && $path === '/api/users') {
    $body = request_json();
    $user = [
        '_id' => new_id(),
        'email' => (string) ($body['email'] ?? ''),
        'username' => (string) ($body['username'] ?? ''),
        'description' => (string) ($body['description'] ?? ''),
        'remember' => false,
        'password' => password_hash((string) ($body['password'] ?? 'password123'), PASSWORD_BCRYPT),
        'picture' => 'picture.jpg',
        'role' => (string) ($body['role'] ?? 'Student'),
        'createdAt' => now_iso(),
        'updatedAt' => now_iso(),
    ];
    users_upsert($user);
    json_response(user_without_password($user), 201);
}

if ($method === 'GET' && $path === '/api/reservations') {
    $rows = [];
    foreach (reservations_all() as $reservation) {
        $rows[] = enrich_reservation($reservation);
    }
    json_response($rows);
}

if ($method === 'POST' && $path === '/api/reservations') {
    $body = request_json();
    $timeStart = (string) ($body['time_start'] ?? '');
    $timeEnd = (string) ($body['time_end'] ?? '');
    $username = (string) ($body['user'] ?? '');
    $labNumber = (int) ($body['lab'] ?? 0);
    $date = isset($body['date']) ? format_manila_date((string) $body['date']) : '';
    $seats = is_array($body['seats'] ?? null) ? $body['seats'] : [];

    if ($timeStart === '' || $timeEnd === '' || $username === '' || $labNumber === 0 || $date === '') {
        json_response(['error' => 'Missing required fields: time_start, time_end, user, lab, date'], 400);
    }

    if ($seats === []) {
        json_response(['error' => 'At least one seat must be selected'], 400);
    }

    $user = users_find_by_username($username);
    if ($user === null) {
        json_response(['error' => 'User not found'], 404);
    }

    $lab = labs_find_by_number($labNumber);
    if ($lab === null) {
        json_response(['error' => 'Lab not found'], 404);
    }

    $candidate = [
        'lab' => (string) $lab['_id'],
        'date' => $date,
        'time_start' => $timeStart,
        'time_end' => $timeEnd,
    ];

    if (reservation_conflicts($candidate, $seats)) {
        json_response(['error' => 'One or more selected seats are already reserved for this time slot'], 409);
    }

    $reservation = [
        '_id' => new_id(),
        'time_start' => $timeStart,
        'time_end' => $timeEnd,
        'user' => (string) $user['_id'],
        'lab' => (string) $lab['_id'],
        'date' => $date,
        'anonymity' => (bool) ($body['anonymity'] ?? false),
        'status' => 'Scheduled',
        'createdAt' => now_iso(),
        'updatedAt' => now_iso(),
    ];

    $all = reservations_all();
    $all[] = $reservation;
    reservations_save($all);
    seats_replace_for_reservation((string) $reservation['_id'], $seats);

    json_response([
        'success' => true,
        'message' => 'Reservation created successfully',
        'reservationId' => $reservation['_id'],
        'reservation' => [
            '_id' => $reservation['_id'],
            'time_start' => $timeStart,
            'time_end' => $timeEnd,
            'date' => $date,
            'user' => (string) $user['username'],
            'lab' => 'Lab ' . $lab['number'] . ' (' . $lab['class'] . ')',
            'seats' => $seats,
        ],
    ], 201);
}

if ($method === 'GET' && preg_match('#^/api/reservations/(.+)$#', $path, $m)) {
    $id = (string) $m[1];
    $reservation = reservations_find_by_id($id);
    if ($reservation === null) {
        json_response(['error' => 'Reservation not found'], 404);
    }

    $seat = seats_for_reservation($id)[0] ?? [];
    json_response(enrich_reservation($reservation, $seat));
}

if ($method === 'PUT' && preg_match('#^/api/reservations/(.+)$#', $path, $m)) {
    $id = (string) $m[1];
    $body = request_json();

    $all = reservations_all();
    $found = null;
    foreach ($all as $i => $reservation) {
        if ((string) ($reservation['_id'] ?? '') === $id) {
            $found = $i;
            break;
        }
    }
    if ($found === null) {
        json_response(['error' => 'Reservation not found'], 404);
    }

    $existing = $all[$found];

    $date = isset($body['date']) ? format_manila_date((string) $body['date']) : reservation_date_only($existing);
    $candidate = [
        'lab' => (string) ($body['lab'] ?? $existing['lab']),
        'date' => $date,
        'time_start' => (string) ($body['time_start'] ?? $existing['time_start']),
        'time_end' => (string) ($body['time_end'] ?? $existing['time_end']),
    ];

    $candidateSeats = [[
        'row' => (int) ($body['row'] ?? (seats_for_reservation($id)[0]['row'] ?? 1)),
        'column' => (int) ($body['column'] ?? (seats_for_reservation($id)[0]['column'] ?? 1)),
    ]];

    if (reservation_conflicts($candidate, $candidateSeats, $id)) {
        json_response(['error' => 'This seat is already reserved for the selected time slot'], 409);
    }

    $existing['time_start'] = $candidate['time_start'];
    $existing['time_end'] = $candidate['time_end'];
    $existing['date'] = $date;
    $existing['lab'] = $candidate['lab'];
    $existing['updatedAt'] = now_iso();
    $all[$found] = $existing;
    reservations_save($all);

    seats_replace_for_reservation($id, $candidateSeats);

    json_response(enrich_reservation($existing, $candidateSeats[0]));
}

if ($method === 'GET' && $path === '/api/seat-lists') {
    $rows = [];
    foreach (seats_all() as $seat) {
        $reservation = reservations_find_by_id((string) ($seat['reservation'] ?? ''));
        if ($reservation === null) {
            continue;
        }
        $rows[] = [
            '_id' => (string) ($seat['_id'] ?? ''),
            'reservation' => enrich_reservation($reservation),
            'row' => (int) ($seat['row'] ?? 0),
            'column' => (int) ($seat['column'] ?? 0),
        ];
    }
    json_response($rows);
}

if ($method === 'GET' && preg_match('#^/api/seat-lists/reservation/(.+)$#', $path, $m)) {
    json_response(seats_for_reservation((string) $m[1]));
}

route_not_found();
