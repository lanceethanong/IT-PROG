<?php
require_once __DIR__ . '/../lib/repository.php';

// Assume session user is admin or whoever
$sessionUser = $_SESSION['user'] ?? [];
$createdBy = (string) ($sessionUser['_id'] ?? '');

$title = $_POST['title'] ?? '';
$lab_id = $_POST['lab_id'] ?? '';
$date = $_POST['date'] ?? '';
$start = $_POST['start_time'] ?? '';
$end = $_POST['end_time'] ?? '';
$description = $_POST['description'] ?? $title;

// Insert event
$event = events_insert([
    'lab' => $lab_id,
    'name' => $title,
    'description' => $description,
    'date' => $date,
    'time_start' => $start,
    'time_end' => $end,
    'created_by' => $createdBy,
]);

// Cancel conflicting reservations
$cancelled = event_cancel_conflicting($event);

echo "Event created and $cancelled conflicting reservation(s) cancelled.";
?>