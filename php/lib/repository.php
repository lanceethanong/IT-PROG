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
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    // Auto-create events table and cancel_reason column on first load.
    events_table_ensure($pdo);

    return $pdo;
}

// ══════════════════════════════════════════════════════════════════════════════
//  EVENTS — auto-migration (runs once per process via db())
// ══════════════════════════════════════════════════════════════════════════════

/**
 * Creates the `events` table and the `cancel_reason` column on `reservations`
 * if they do not yet exist. Safe to call multiple times (idempotent).
 */
function events_table_ensure(PDO $pdo): void
{
    // events table -----------------------------------------------------------------
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS events (
            id          VARCHAR(24)  NOT NULL PRIMARY KEY,
            lab_id      VARCHAR(24)  NOT NULL,
            name        VARCHAR(191) NOT NULL,
            description TEXT         NULL,
            date        DATE         NOT NULL,
            time_start  VARCHAR(20)  NOT NULL,
            time_end    VARCHAR(20)  NOT NULL,
            created_by  VARCHAR(24)  NOT NULL,
            created_at  DATETIME     NULL,
            updated_at  DATETIME     NULL,
            CONSTRAINT fk_event_lab
                FOREIGN KEY (lab_id) REFERENCES labs(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    // cancel_reason column on reservations ----------------------------------------
    // ALTER TABLE errors silently if column already exists.
    try {
        $pdo->exec('ALTER TABLE reservations ADD COLUMN cancel_reason TEXT NULL');
    } catch (PDOException) {
        // Column already present — ignore.
    }
}

// ══════════════════════════════════════════════════════════════════════════════
//  EVENTS — helpers
// ══════════════════════════════════════════════════════════════════════════════

/** Normalise a raw DB row into the canonical event array shape. */
function event_from_row(array $r): array
{
    return [
        '_id'         => (string) $r['id'],
        'lab'         => (string) $r['lab_id'],
        'name'        => (string) $r['name'],
        'description' => (string) ($r['description'] ?? ''),
        'date'        => (string) $r['date'],
        'time_start'  => (string) $r['time_start'],
        'time_end'    => (string) $r['time_end'],
        'created_by'  => (string) $r['created_by'],
        'createdAt'   => dt_from_db($r['created_at'] ?? null),
        'updatedAt'   => dt_from_db($r['updated_at'] ?? null),
    ];
}

/** Return all events ordered by date then start time. */
function events_all(): array
{
    $rows = db()->query(
        'SELECT id, lab_id, name, description, date, time_start, time_end,
                created_by, created_at, updated_at
         FROM events
         ORDER BY date ASC, time_start ASC'
    )->fetchAll();

    return array_map('event_from_row', $rows);
}

/** Find a single event by its 24-char hex id. Returns null if not found. */
function events_find_by_id(string $id): ?array
{
    $stmt = db()->prepare(
        'SELECT id, lab_id, name, description, date, time_start, time_end,
                created_by, created_at, updated_at
         FROM events WHERE id = ?'
    );
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    return $row !== false ? event_from_row($row) : null;
}

/**
 * Return all events that apply to a given lab on a given date.
 * Called per page-load by slot_is_blocked_by_event() from dashboard.php.
 */
function events_for_lab_date(string $labId, string $date): array
{
    $stmt = db()->prepare(
        'SELECT id, lab_id, name, description, date, time_start, time_end,
                created_by, created_at, updated_at
         FROM events
         WHERE lab_id = ? AND date = ?
         ORDER BY time_start ASC'
    );
    $stmt->execute([$labId, $date]);
    return array_map('event_from_row', $stmt->fetchAll());
}

/**
 * Insert a new event. Requires keys: lab, name, description, date,
 * time_start, time_end, created_by.
 * Returns the full saved event array.
 */
function events_insert(array $event): array
{
    $id  = new_id();
    $now = now_iso();
    db()->prepare(
        'INSERT INTO events
             (id, lab_id, name, description, date, time_start, time_end, created_by, created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    )->execute([
        $id,
        (string) ($event['lab']         ?? ''),
        (string) ($event['name']        ?? ''),
        (string) ($event['description'] ?? ''),
        (string) ($event['date']        ?? ''),
        (string) ($event['time_start']  ?? ''),
        (string) ($event['time_end']    ?? ''),
        (string) ($event['created_by']  ?? ''),
        dt_to_db($now),
        dt_to_db($now),
    ]);

    return events_find_by_id($id) ?? array_merge($event, ['_id' => $id]);
}

/**
 * Update mutable fields of an existing event.
 * Returns true when a row was actually changed.
 */
function events_update(string $id, array $fields): bool
{
    $stmt = db()->prepare(
        'UPDATE events
         SET lab_id = ?, name = ?, description = ?, date = ?,
             time_start = ?, time_end = ?, updated_at = ?
         WHERE id = ?'
    );
    $stmt->execute([
        (string) ($fields['lab']         ?? ''),
        (string) ($fields['name']        ?? ''),
        (string) ($fields['description'] ?? ''),
        (string) ($fields['date']        ?? ''),
        (string) ($fields['time_start']  ?? ''),
        (string) ($fields['time_end']    ?? ''),
        dt_to_db(now_iso()),
        $id,
    ]);
    return $stmt->rowCount() > 0;
}

/** Hard-delete an event by id. */
function events_delete(string $id): bool
{
    $stmt = db()->prepare('DELETE FROM events WHERE id = ?');
    $stmt->execute([$id]);
    return $stmt->rowCount() > 0;
}

// ══════════════════════════════════════════════════════════════════════════════
//  EVENTS — seat-grid blocking (called from dashboard.php)
// ══════════════════════════════════════════════════════════════════════════════

/**
 * Returns true if $slotStart–$slotEnd overlaps with the event window.
 * Uses half-open interval: overlap = slotStart < evEnd && slotEnd > evStart.
 * Mirrors the same logic used in reservation_conflicts().
 */
function slot_overlaps_event(string $slotStart, string $slotEnd, array $event): bool
{
    $s  = parse_time_to_minutes($slotStart);
    $e  = parse_time_to_minutes($slotEnd);
    $es = parse_time_to_minutes((string) ($event['time_start'] ?? '12:00 AM'));
    $ee = parse_time_to_minutes((string) ($event['time_end']   ?? '12:00 AM'));
    return $s < $ee && $e > $es;
}

/**
 * Returns true if ANY scheduled event blocks the given lab/date/slot.
 *
 * HOW TO USE IN dashboard.php (inside the seat-grid loop, per half-hour cell):
 *
 *   if (slot_is_blocked_by_event($lab['_id'], $selectedDate, $slotStart, $slotEnd)) {
 *       // render cell with class="unavailable"
 *       // optionally attach data-reason="..." for a tooltip
 *   }
 */
function slot_is_blocked_by_event(
    string $labId,
    string $date,
    string $slotStart,
    string $slotEnd
): bool {
    foreach (events_for_lab_date($labId, $date) as $event) {
        if (slot_overlaps_event($slotStart, $slotEnd, $event)) {
            return true;
        }
    }
    return false;
}

/**
 * Returns the full description of the first event that blocks the slot,
 * falling back to the event name. Empty string if no event blocks the slot.
 *
 * Use this for the tooltip / data-reason attribute on blocked cells.
 */
function slot_block_reason(
    string $labId,
    string $date,
    string $slotStart,
    string $slotEnd
): string {
    foreach (events_for_lab_date($labId, $date) as $event) {
        if (slot_overlaps_event($slotStart, $slotEnd, $event)) {
            $desc = trim((string) ($event['description'] ?? ''));
            return $desc !== '' ? $desc : (string) ($event['name'] ?? 'Scheduled event');
        }
    }
    return '';
}

// ══════════════════════════════════════════════════════════════════════════════
//  EVENTS — conflict detection & reservation cancellation
// ══════════════════════════════════════════════════════════════════════════════

/**
 * Find every Scheduled reservation that overlaps with the given event
 * (same lab, same date, overlapping time window).
 * Returns raw reservation rows (same shape as reservations_all()).
 */
function event_conflicting_reservations(array $event): array
{
    $labId   = (string) ($event['lab']        ?? '');
    $date    = (string) ($event['date']       ?? '');
    $evStart = parse_time_to_minutes((string) ($event['time_start'] ?? '12:00 AM'));
    $evEnd   = parse_time_to_minutes((string) ($event['time_end']   ?? '12:00 AM'));

    $conflicts = [];
    foreach (reservations_all() as $reservation) {
        if ((string) ($reservation['lab'] ?? '') !== $labId) {
            continue;
        }
        if (reservation_date_only($reservation) !== $date) {
            continue;
        }
        // Only touch reservations that have not started or completed yet.
        if (reservation_status($reservation) !== 'Scheduled') {
            continue;
        }

        $rs = parse_time_to_minutes((string) ($reservation['time_start'] ?? '12:00 AM'));
        $re = parse_time_to_minutes((string) ($reservation['time_end']   ?? '12:00 AM'));

        if ($rs < $evEnd && $re > $evStart) {
            $conflicts[] = $reservation;
        }
    }

    return $conflicts;
}

/**
 * Cancel every Scheduled reservation that conflicts with $event.
 *
 * The cancellation reason stored (and shown to the student) is the event's
 * full description. Falls back to the event name if description is blank.
 *
 * Returns the count of reservations cancelled.
 */
function event_cancel_conflicting(array $event): int
{
    $reason = trim((string) ($event['description'] ?? ''));
    if ($reason === '') {
        $reason = (string) ($event['name'] ?? 'Scheduled event');
    }

    $conflicts = event_conflicting_reservations($event);
    $now       = dt_to_db(now_iso());
    $stmt      = db()->prepare(
        'UPDATE reservations
         SET status = ?, cancel_reason = ?, updated_at = ?
         WHERE id = ?'
    );

    foreach ($conflicts as $reservation) {
        $stmt->execute(['Cancelled', $reason, $now, (string) ($reservation['_id'] ?? '')]);
    }

    return count($conflicts);
}

// ══════════════════════════════════════════════════════════════════════════════
//  ORIGINAL REPOSITORY FUNCTIONS (unchanged)
// ══════════════════════════════════════════════════════════════════════════════

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
            '_id'         => (string) $r['id'],
            'email'       => (string) $r['email'],
            'username'    => (string) $r['username'],
            'description' => (string) ($r['description'] ?? ''),
            'remember'    => (bool) ((int) ($r['remember'] ?? 0)),
            'password'    => (string) $r['password'],
            'picture'     => (string) ($r['picture'] ?? 'picture.jpg'),
            'role'        => (string) $r['role'],
            'createdAt'   => dt_from_db($r['created_at'] ?? null),
            'updatedAt'   => dt_from_db($r['updated_at'] ?? null),
        ];
    }, $rows);
}

function users_save(array $users): void
{
    $pdo = db();
    $pdo->beginTransaction();
    $pdo->exec('DELETE FROM users');
    $sql  = 'INSERT INTO users (id, email, username, description, remember, password, picture, role, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';
    $stmt = $pdo->prepare($sql);
    foreach ($users as $u) {
        $stmt->execute([
            (string) ($u['_id']         ?? new_id()),
            (string) ($u['email']       ?? ''),
            (string) ($u['username']    ?? ''),
            (string) ($u['description'] ?? ''),
            !empty($u['remember']) ? 1 : 0,
            (string) ($u['password']    ?? ''),
            (string) ($u['picture']     ?? 'picture.jpg'),
            (string) ($u['role']        ?? 'Student'),
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
              email       = VALUES(email),
              username    = VALUES(username),
              description = VALUES(description),
              remember    = VALUES(remember),
              password    = VALUES(password),
              picture     = VALUES(picture),
              role        = VALUES(role),
              updated_at  = VALUES(updated_at)';
    db()->prepare($sql)->execute([
        (string) ($updated['_id']         ?? new_id()),
        (string) ($updated['email']       ?? ''),
        (string) ($updated['username']    ?? ''),
        (string) ($updated['description'] ?? ''),
        !empty($updated['remember']) ? 1 : 0,
        (string) ($updated['password']    ?? ''),
        (string) ($updated['picture']     ?? 'picture.jpg'),
        (string) ($updated['role']        ?? 'Student'),
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
            '_id'       => (string) $r['id'],
            'class'     => (string) $r['class_name'],
            'number'    => (int)    $r['number'],
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
        (string) ($lab['class']  ?? ''),
        (int)    ($lab['number'] ?? 0),
        dt_to_db((string) ($lab['createdAt'] ?? now_iso())),
        dt_to_db((string) ($lab['updatedAt'] ?? now_iso())),
    ]);
    return [
        '_id'    => $id,
        'class'  => (string) ($lab['class']  ?? ''),
        'number' => (int)    ($lab['number'] ?? 0),
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
            '_id'        => (string) $r['id'],
            'time_start' => (string) $r['time_start'],
            'time_end'   => (string) $r['time_end'],
            'user'       => (string) $r['user_id'],
            'lab'        => (string) $r['lab_id'],
            'date'       => (string) $r['date'],
            'anonymity'  => (bool) ((int) ($r['anonymity'] ?? 0)),
            'status'     => (string) ($r['status'] ?? 'Scheduled'),
            'createdAt'  => dt_from_db($r['created_at'] ?? null),
            'updatedAt'  => dt_from_db($r['updated_at'] ?? null),
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
            (string) ($r['_id']        ?? new_id()),
            (string) ($r['time_start'] ?? ''),
            (string) ($r['time_end']   ?? ''),
            (string) ($r['user']       ?? ''),
            (string) ($r['lab']        ?? ''),
            (string) ($r['date']       ?? ''),
            !empty($r['anonymity']) ? 1 : 0,
            (string) ($r['status']     ?? 'Scheduled'),
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
            '_id'         => (string) $r['id'],
            'reservation' => (string) $r['reservation_id'],
            'row'         => (int)    $r['row_num'],
            'column'      => (int)    $r['col_num'],
            'createdAt'   => dt_from_db($r['created_at'] ?? null),
            'updatedAt'   => dt_from_db($r['updated_at'] ?? null),
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
            (string) ($s['_id']         ?? new_id()),
            (string) ($s['reservation'] ?? ''),
            (int)    ($s['row']         ?? 0),
            (int)    ($s['column']      ?? 0),
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
            '_id'         => (string) $r['id'],
            'reservation' => (string) $r['reservation_id'],
            'row'         => (int)    $r['row_num'],
            'column'      => (int)    $r['col_num'],
            'createdAt'   => dt_from_db($r['created_at'] ?? null),
            'updatedAt'   => dt_from_db($r['updated_at'] ?? null),
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
            '_id'         => new_id(),
            'reservation' => $reservationId,
            'row'         => (int) $seat['row'],
            'column'      => (int) $seat['column'],
            'createdAt'   => now_iso(),
            'updatedAt'   => now_iso(),
        ];
    }
    seats_save($all);
}

function errors_all(): array
{
    $rows = db()->query('SELECT id, message, stack, source, timestamp, user FROM error_log ORDER BY timestamp DESC')->fetchAll();
    return array_map(static function (array $r): array {
        return [
            '_id'       => (string) $r['id'],
            'message'   => (string) $r['message'],
            'stack'     => (string) ($r['stack']  ?? ''),
            'source'    => (string) ($r['source'] ?? ''),
            'timestamp' => dt_from_db($r['timestamp'] ?? null),
            'user'      => (string) ($r['user']   ?? ''),
        ];
    }, $rows);
}

function errors_add(array $entry): void
{
    db()->prepare('INSERT INTO error_log (id, message, stack, source, timestamp, user) VALUES (?, ?, ?, ?, ?, ?)')->execute([
        (string) ($entry['_id']       ?? new_id()),
        (string) ($entry['message']   ?? 'Unknown error'),
        (string) ($entry['stack']     ?? ''),
        (string) ($entry['source']    ?? ''),
        dt_to_db((string) ($entry['timestamp'] ?? now_iso())),
        (string) ($entry['user']      ?? ''),
    ]);
}

function parse_time_to_minutes(string $time): int
{
    if (!preg_match('/^(\d{1,2}):(\d{2})\s*(AM|PM)$/i', trim($time), $m)) {
        return 0;
    }

    $hour   = (int) $m[1];
    $minute = (int) $m[2];
    $ampm   = strtoupper($m[3]);

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
    $date    = reservation_date_only($reservation);
    $base    = new DateTime($date . ' 00:00:00', new DateTimeZone('Asia/Manila'));
    $minutes = parse_time_to_minutes((string) ($reservation['time_start'] ?? '12:00 AM'));
    $base->modify('+' . $minutes . ' minutes');
    return $base;
}

function reservation_end_dt(array $reservation): DateTime
{
    $date    = reservation_date_only($reservation);
    $base    = new DateTime($date . ' 00:00:00', new DateTimeZone('Asia/Manila'));
    $minutes = parse_time_to_minutes((string) ($reservation['time_end'] ?? '12:00 AM'));
    $base->modify('+' . $minutes . ' minutes');
    return $base;
}

function reservation_status(array $reservation): string
{
    if ((string) ($reservation['status'] ?? '') === 'Cancelled') {
        return 'Cancelled';
    }
    $now   = new DateTime('now', new DateTimeZone('Asia/Manila'));
    $start = reservation_start_dt($reservation);
    $end   = reservation_end_dt($reservation);
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
    $now   = new DateTime('now', new DateTimeZone('Asia/Manila'));
    $start = reservation_start_dt($reservation);
    return $start->getTimestamp() - $now->getTimestamp();
}

function reservation_is_scheduled(array $reservation): bool
{
    return reservation_status($reservation) === 'Scheduled';
}

function reservation_can_edit_student(array $reservation): bool
{
    return reservation_is_scheduled($reservation)
        && reservation_seconds_until_start($reservation) > 900;
}

function reservation_can_delete_student(array $reservation): bool
{
    return reservation_is_scheduled($reservation)
        && reservation_seconds_until_start($reservation) > 0;
}

function reservation_can_edit_technician(array $reservation): bool
{
    return reservation_is_scheduled($reservation);
}

function reservation_can_delete_technician(array $reservation): bool
{
    $diff = reservation_seconds_until_start($reservation);
    return reservation_is_scheduled($reservation)
        && $diff <= 600
        && $diff > 0;
}

function enrich_reservation(array $reservation, array $seat = []): array
{
    $users = users_all();
    $labs  = labs_all();

    $user = null;
    $lab  = null;
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

    $out           = $reservation;
    $out['user']   = $user;
    $out['lab']    = $lab;
    $out['status'] = reservation_status($reservation);
    $out['date']   = reservation_date_only($reservation);

    if ($seat !== []) {
        $out['row']    = (int) ($seat['row']    ?? 0);
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
    $labId = (string) ($candidate['lab']  ?? '');
    $date  = (string) ($candidate['date'] ?? '');
    $start = parse_time_to_minutes((string) ($candidate['time_start'] ?? '12:00 AM'));
    $end   = parse_time_to_minutes((string) ($candidate['time_end']   ?? '12:00 AM'));

    $reservations = reservations_all();
    $seats        = seats_all();

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
        $rs      = parse_time_to_minutes((string) ($reservation['time_start'] ?? '12:00 AM'));
        $re      = parse_time_to_minutes((string) ($reservation['time_end']   ?? '12:00 AM'));
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
