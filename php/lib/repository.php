<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $cfg = [];
    $cfgPath = __DIR__ . '/../config.php';
    if (is_file($cfgPath)) {
        $loaded = include $cfgPath;
        if (is_array($loaded)) {
            $cfg = $loaded;
        }
    }

    $host = (string) ($cfg['host'] ?? (getenv('DB_HOST') ?: '127.0.0.1'));
    $port = (string) ($cfg['port'] ?? (getenv('DB_PORT') ?: '3306'));
    $name = (string) ($cfg['name'] ?? (getenv('DB_NAME') ?: 'lab_res_db'));
    $user = (string) ($cfg['user'] ?? (getenv('DB_USER') ?: 'root'));
    $pass = (string) ($cfg['pass'] ?? (getenv('DB_PASS') ?: ''));

    $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $host, $port, $name);
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    return $pdo;
}

function dt_to_db(?string $iso): ?string
{
    if ($iso === null || $iso === '') {
        return null;
    }
    $dt = new DateTime($iso, new DateTimeZone('Asia/Manila'));
    return $dt->format('Y-m-d H:i:s');
}

function dt_from_db(?string $db): string
{
    if ($db === null || $db === '') {
        return '';
    }
    $dt = new DateTime($db, new DateTimeZone('Asia/Manila'));
    return $dt->format(DATE_ATOM);
}

function new_id(): string
{
    return bin2hex(random_bytes(12));
}

function now_iso(): string
{
    return (new DateTime('now', new DateTimeZone('Asia/Manila')))->format(DATE_ATOM);
}

function users_all(): array
{
    $rows = db()->query('SELECT id, email, username, description, remember, password, picture, role, created_at, updated_at FROM users')->fetchAll();
    return array_map(static function (array $r): array {
        return [
            '_id' => (string) $r['id'],
            'email' => (string) $r['email'],
            'username' => (string) $r['username'],
            'description' => (string) ($r['description'] ?? ''),
            'remember' => (bool) ((int) ($r['remember'] ?? 0)),
            'password' => (string) $r['password'],
            'picture' => (string) ($r['picture'] ?? 'picture.jpg'),
            'role' => (string) $r['role'],
            'createdAt' => dt_from_db($r['created_at'] ?? null),
            'updatedAt' => dt_from_db($r['updated_at'] ?? null),
        ];
    }, $rows);
}

function users_save(array $users): void
{
    $pdo = db();
    $pdo->beginTransaction();
    $pdo->exec('DELETE FROM users');
    $sql = 'INSERT INTO users (id, email, username, description, remember, password, picture, role, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';
    $stmt = $pdo->prepare($sql);
    foreach ($users as $u) {
        $stmt->execute([
            (string) ($u['_id'] ?? new_id()),
            (string) ($u['email'] ?? ''),
            (string) ($u['username'] ?? ''),
            (string) ($u['description'] ?? ''),
            !empty($u['remember']) ? 1 : 0,
            (string) ($u['password'] ?? ''),
            (string) ($u['picture'] ?? 'picture.jpg'),
            (string) ($u['role'] ?? 'Student'),
            dt_to_db((string) ($u['createdAt'] ?? now_iso())),
            dt_to_db((string) ($u['updatedAt'] ?? now_iso())),
        ]);
    }
    $pdo->commit();
}

function user_without_password(array $user): array
{
    unset($user['password']);
    return $user;
}

function users_find_by_email(string $email): ?array
{
    foreach (users_all() as $user) {
        if (strcasecmp((string) ($user['email'] ?? ''), $email) === 0) {
            return $user;
        }
    }

    return null;
}

function users_find_by_username(string $username): ?array
{
    foreach (users_all() as $user) {
        if ((string) ($user['username'] ?? '') === $username) {
            return $user;
        }
    }

    return null;
}

function users_find_by_id(string $id): ?array
{
    foreach (users_all() as $user) {
        if ((string) ($user['_id'] ?? '') === $id) {
            return $user;
        }
    }

    return null;
}

function users_upsert(array $updated): void
{
    $sql = 'INSERT INTO users (id, email, username, description, remember, password, picture, role, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
              email = VALUES(email),
              username = VALUES(username),
              description = VALUES(description),
              remember = VALUES(remember),
              password = VALUES(password),
              picture = VALUES(picture),
              role = VALUES(role),
              updated_at = VALUES(updated_at)';
    db()->prepare($sql)->execute([
        (string) ($updated['_id'] ?? new_id()),
        (string) ($updated['email'] ?? ''),
        (string) ($updated['username'] ?? ''),
        (string) ($updated['description'] ?? ''),
        !empty($updated['remember']) ? 1 : 0,
        (string) ($updated['password'] ?? ''),
        (string) ($updated['picture'] ?? 'picture.jpg'),
        (string) ($updated['role'] ?? 'Student'),
        dt_to_db((string) ($updated['createdAt'] ?? now_iso())),
        dt_to_db((string) ($updated['updatedAt'] ?? now_iso())),
    ]);
}

function users_delete_by_username(string $username): bool
{
    $stmt = db()->prepare('DELETE FROM users WHERE username = ?');
    $stmt->execute([$username]);
    return $stmt->rowCount() > 0;
}

function labs_all(): array
{
    $rows = db()->query('SELECT id, class_name, number, created_at, updated_at FROM labs ORDER BY number ASC')->fetchAll();
    return array_map(static function (array $r): array {
        return [
            '_id' => (string) $r['id'],
            'class' => (string) $r['class_name'],
            'number' => (int) $r['number'],
            'createdAt' => dt_from_db($r['created_at'] ?? null),
            'updatedAt' => dt_from_db($r['updated_at'] ?? null),
        ];
    }, $rows);
}

function labs_insert(array $lab): array
{
    $id = (string) ($lab['_id'] ?? new_id());
    db()->prepare('INSERT INTO labs (id, class_name, number, created_at, updated_at) VALUES (?, ?, ?, ?, ?)')->execute([
        $id,
        (string) ($lab['class'] ?? ''),
        (int) ($lab['number'] ?? 0),
        dt_to_db((string) ($lab['createdAt'] ?? now_iso())),
        dt_to_db((string) ($lab['updatedAt'] ?? now_iso())),
    ]);

    return [
        '_id' => $id,
        'class' => (string) ($lab['class'] ?? ''),
        'number' => (int) ($lab['number'] ?? 0),
    ];
}

function labs_find_by_id(string $id): ?array
{
    foreach (labs_all() as $lab) {
        if ((string) ($lab['_id'] ?? '') === $id) {
            return $lab;
        }
    }

    return null;
}

function labs_find_by_number(int $number): ?array
{
    foreach (labs_all() as $lab) {
        if ((int) ($lab['number'] ?? 0) === $number) {
            return $lab;
        }
    }

    return null;
}

function reservations_all(): array
{
    $rows = db()->query('SELECT id, time_start, time_end, user_id, lab_id, date, anonymity, status, created_at, updated_at FROM reservations')->fetchAll();
    return array_map(static function (array $r): array {
        return [
            '_id' => (string) $r['id'],
            'time_start' => (string) $r['time_start'],
            'time_end' => (string) $r['time_end'],
            'user' => (string) $r['user_id'],
            'lab' => (string) $r['lab_id'],
            'date' => (string) $r['date'],
            'anonymity' => (bool) ((int) ($r['anonymity'] ?? 0)),
            'status' => (string) ($r['status'] ?? 'Scheduled'),
            'createdAt' => dt_from_db($r['created_at'] ?? null),
            'updatedAt' => dt_from_db($r['updated_at'] ?? null),
        ];
    }, $rows);
}

function reservations_save(array $reservations): void
{
    $pdo = db();
    $pdo->beginTransaction();
    $pdo->exec('DELETE FROM reservations');
    $stmt = $pdo->prepare('INSERT INTO reservations (id, time_start, time_end, user_id, lab_id, date, anonymity, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
    foreach ($reservations as $r) {
        $stmt->execute([
            (string) ($r['_id'] ?? new_id()),
            (string) ($r['time_start'] ?? ''),
            (string) ($r['time_end'] ?? ''),
            (string) ($r['user'] ?? ''),
            (string) ($r['lab'] ?? ''),
            (string) ($r['date'] ?? ''),
            !empty($r['anonymity']) ? 1 : 0,
            (string) ($r['status'] ?? 'Scheduled'),
            dt_to_db((string) ($r['createdAt'] ?? now_iso())),
            dt_to_db((string) ($r['updatedAt'] ?? now_iso())),
        ]);
    }
    $pdo->commit();
}

function reservations_find_by_id(string $id): ?array
{
    foreach (reservations_all() as $reservation) {
        if ((string) ($reservation['_id'] ?? '') === $id) {
            return $reservation;
        }
    }

    return null;
}

function seats_all(): array
{
    $rows = db()->query('SELECT id, reservation_id, row_num, col_num, created_at, updated_at FROM seat_lists')->fetchAll();
    return array_map(static function (array $r): array {
        return [
            '_id' => (string) $r['id'],
            'reservation' => (string) $r['reservation_id'],
            'row' => (int) $r['row_num'],
            'column' => (int) $r['col_num'],
            'createdAt' => dt_from_db($r['created_at'] ?? null),
            'updatedAt' => dt_from_db($r['updated_at'] ?? null),
        ];
    }, $rows);
}

function seats_save(array $seats): void
{
    $pdo = db();
    $pdo->beginTransaction();
    $pdo->exec('DELETE FROM seat_lists');
    $stmt = $pdo->prepare('INSERT INTO seat_lists (id, reservation_id, row_num, col_num, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?)');
    foreach ($seats as $s) {
        $stmt->execute([
            (string) ($s['_id'] ?? new_id()),
            (string) ($s['reservation'] ?? ''),
            (int) ($s['row'] ?? 0),
            (int) ($s['column'] ?? 0),
            dt_to_db((string) ($s['createdAt'] ?? now_iso())),
            dt_to_db((string) ($s['updatedAt'] ?? now_iso())),
        ]);
    }
    $pdo->commit();
}

function seats_for_reservation(string $reservationId): array
{
    $stmt = db()->prepare('SELECT id, reservation_id, row_num, col_num, created_at, updated_at FROM seat_lists WHERE reservation_id = ?');
    $stmt->execute([$reservationId]);
    $rows = $stmt->fetchAll();
    return array_map(static function (array $r): array {
        return [
            '_id' => (string) $r['id'],
            'reservation' => (string) $r['reservation_id'],
            'row' => (int) $r['row_num'],
            'column' => (int) $r['col_num'],
            'createdAt' => dt_from_db($r['created_at'] ?? null),
            'updatedAt' => dt_from_db($r['updated_at'] ?? null),
        ];
    }, $rows);
}

function seats_delete_for_reservation(string $reservationId): void
{
    db()->prepare('DELETE FROM seat_lists WHERE reservation_id = ?')->execute([$reservationId]);
}

function seats_replace_for_reservation(string $reservationId, array $newSeats): void
{
    $all = array_values(array_filter(seats_all(), static fn(array $seat): bool => (string) ($seat['reservation'] ?? '') !== $reservationId));
    foreach ($newSeats as $seat) {
        $all[] = [
            '_id' => new_id(),
            'reservation' => $reservationId,
            'row' => (int) $seat['row'],
            'column' => (int) $seat['column'],
            'createdAt' => now_iso(),
            'updatedAt' => now_iso(),
        ];
    }

    seats_save($all);
}

function errors_all(): array
{
    $rows = db()->query('SELECT id, message, stack, source, timestamp, user FROM error_log ORDER BY timestamp DESC')->fetchAll();
    return array_map(static function (array $r): array {
        return [
            '_id' => (string) $r['id'],
            'message' => (string) $r['message'],
            'stack' => (string) ($r['stack'] ?? ''),
            'source' => (string) ($r['source'] ?? ''),
            'timestamp' => dt_from_db($r['timestamp'] ?? null),
            'user' => (string) ($r['user'] ?? ''),
        ];
    }, $rows);
}

function errors_add(array $entry): void
{
    db()->prepare('INSERT INTO error_log (id, message, stack, source, timestamp, user) VALUES (?, ?, ?, ?, ?, ?)')->execute([
        (string) ($entry['_id'] ?? new_id()),
        (string) ($entry['message'] ?? 'Unknown error'),
        (string) ($entry['stack'] ?? ''),
        (string) ($entry['source'] ?? ''),
        dt_to_db((string) ($entry['timestamp'] ?? now_iso())),
        (string) ($entry['user'] ?? ''),
    ]);
}

function parse_time_to_minutes(string $time): int
{
    if (!preg_match('/^(\d{1,2}):(\d{2})\s*(AM|PM)$/i', trim($time), $m)) {
        return 0;
    }

    $hour = (int) $m[1];
    $minute = (int) $m[2];
    $ampm = strtoupper($m[3]);

    if ($ampm === 'PM' && $hour !== 12) {
        $hour += 12;
    }
    if ($ampm === 'AM' && $hour === 12) {
        $hour = 0;
    }

    return ($hour * 60) + $minute;
}

function reservation_date_only(array $reservation): string
{
    $raw = (string) ($reservation['date'] ?? '');
    if ($raw === '') {
        return '';
    }

    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw) === 1) {
        return $raw;
    }

    $dt = new DateTime($raw);
    $dt->setTimezone(new DateTimeZone('Asia/Manila'));
    return $dt->format('Y-m-d');
}

function reservation_start_dt(array $reservation): DateTime
{
    $date = reservation_date_only($reservation);
    $base = new DateTime($date . ' 00:00:00', new DateTimeZone('Asia/Manila'));
    $minutes = parse_time_to_minutes((string) ($reservation['time_start'] ?? '12:00 AM'));
    $base->modify('+' . $minutes . ' minutes');
    return $base;
}

function reservation_end_dt(array $reservation): DateTime
{
    $date = reservation_date_only($reservation);
    $base = new DateTime($date . ' 00:00:00', new DateTimeZone('Asia/Manila'));
    $minutes = parse_time_to_minutes((string) ($reservation['time_end'] ?? '12:00 AM'));
    $base->modify('+' . $minutes . ' minutes');
    return $base;
}

function reservation_status(array $reservation): string
{
    if ((string) ($reservation['status'] ?? '') === 'Cancelled') {
        return 'Cancelled';
    }

    $now = new DateTime('now', new DateTimeZone('Asia/Manila'));
    $start = reservation_start_dt($reservation);
    $end = reservation_end_dt($reservation);

    if ($now < $start) {
        return 'Scheduled';
    }

    if ($now >= $start && $now <= $end) {
        return 'In Progress';
    }

    return 'Completed';
}

function reservation_show_delete(array $reservation): bool
{
    return reservation_can_delete_technician($reservation);
}

function reservation_seconds_until_start(array $reservation): int
{
    $now = new DateTime('now', new DateTimeZone('Asia/Manila'));
    $start = reservation_start_dt($reservation);
    return $start->getTimestamp() - $now->getTimestamp();
}

function reservation_is_scheduled(array $reservation): bool
{
    return reservation_status($reservation) === 'Scheduled';
}

function reservation_can_edit_student(array $reservation): bool
{
    // Students can edit only until 15 minutes before start.
    return reservation_is_scheduled($reservation)
        && reservation_seconds_until_start($reservation) > 900;
}

function reservation_can_delete_student(array $reservation): bool
{
    // Students can delete only before reservation starts.
    return reservation_is_scheduled($reservation)
        && reservation_seconds_until_start($reservation) > 0;
}

function reservation_can_edit_technician(array $reservation): bool
{
    // Technicians can edit only while reservation is still scheduled.
    return reservation_is_scheduled($reservation);
}

function reservation_can_delete_technician(array $reservation): bool
{
    // Technicians can delete only within 10 minutes before start (not after start).
    $diff = reservation_seconds_until_start($reservation);
    return reservation_is_scheduled($reservation)
        && $diff <= 600
        && $diff > 0;
}

function enrich_reservation(array $reservation, array $seat = []): array
{
    $users = users_all();
    $labs = labs_all();

    $user = null;
    $lab = null;
    foreach ($users as $u) {
        if ((string) ($u['_id'] ?? '') === (string) ($reservation['user'] ?? '')) {
            $user = $u;
            break;
        }
    }
    foreach ($labs as $l) {
        if ((string) ($l['_id'] ?? '') === (string) ($reservation['lab'] ?? '')) {
            $lab = $l;
            break;
        }
    }

    $out = $reservation;
    $out['user'] = $user;
    $out['lab'] = $lab;
    $out['status'] = reservation_status($reservation);
    $out['date'] = reservation_date_only($reservation);

    if ($seat !== []) {
        $out['row'] = (int) ($seat['row'] ?? 0);
        $out['column'] = (int) ($seat['column'] ?? 0);
    }

    return $out;
}

function reservations_delete(string $id): bool
{
    $stmt = db()->prepare('DELETE FROM reservations WHERE id = ?');
    $stmt->execute([$id]);
    seats_delete_for_reservation($id);
    return $stmt->rowCount() > 0;
}

function reservation_conflicts(array $candidate, array $candidateSeats, ?string $ignoreReservationId = null): bool
{
    $labId = (string) ($candidate['lab'] ?? '');
    $date = (string) ($candidate['date'] ?? '');
    $start = parse_time_to_minutes((string) ($candidate['time_start'] ?? '12:00 AM'));
    $end = parse_time_to_minutes((string) ($candidate['time_end'] ?? '12:00 AM'));

    $reservations = reservations_all();
    $seats = seats_all();

    $occupied = [];
    foreach ($reservations as $reservation) {
        if ($ignoreReservationId !== null && (string) ($reservation['_id'] ?? '') === $ignoreReservationId) {
            continue;
        }

        if ((string) ($reservation['lab'] ?? '') !== $labId) {
            continue;
        }

        if (reservation_date_only($reservation) !== $date) {
            continue;
        }

        $rs = parse_time_to_minutes((string) ($reservation['time_start'] ?? '12:00 AM'));
        $re = parse_time_to_minutes((string) ($reservation['time_end'] ?? '12:00 AM'));
        $overlap = $rs < $end && $re > $start;
        if (!$overlap) {
            continue;
        }

        foreach ($seats as $seat) {
            if ((string) ($seat['reservation'] ?? '') === (string) ($reservation['_id'] ?? '')) {
                $occupied[(int) $seat['row'] . '-' . (int) $seat['column']] = true;
            }
        }
    }

    foreach ($candidateSeats as $seat) {
        $key = (int) $seat['row'] . '-' . (int) $seat['column'];
        if (isset($occupied[$key])) {
            return true;
        }
    }

    return false;
}
