<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title><?= e($title ?? 'Edit Reservation') ?> - LabSlot</title>
  <link rel="stylesheet" href="/public/css/dashboard.css" />
  <link rel="stylesheet" href="/public/css/reservation.css" />
</head>
<body data-role="<?= e($role) ?>" data-username="<?= e($username) ?>">
  <div class="dashboard-layout">
    <?php include __DIR__ . '/partials/dashboard_header.php'; ?>
    <div class="dashboard-main">
      <?php include __DIR__ . '/partials/dashboard_sidebar.php'; ?>
      <main class="content">
        <h1>Edit Reservation</h1>

        <form method="POST" action="/reservation/edit" style="max-width: 640px;">
          <input type="hidden" name="reservation_id" value="<?= e((string) ($reservation['_id'] ?? '')) ?>" />

          <div class="form-group" style="margin-bottom: 10px;">
            <label for="lab_number">Lab</label>
            <select id="lab_number" name="lab_number" required>
              <?php foreach (($labs ?? []) as $lab): ?>
                <option value="<?= (int) ($lab['number'] ?? 0) ?>" <?= ((int) ($lab['number'] ?? 0) === (int) ($labNumber ?? 0)) ? 'selected' : '' ?>>
                  Lab <?= (int) ($lab['number'] ?? 0) ?> (<?= e((string) ($lab['class'] ?? '')) ?>)
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="form-group" style="margin-bottom: 10px;">
            <label for="date">Date</label>
            <input id="date" type="date" name="date" value="<?= e((string) reservation_date_only($reservation ?? [])) ?>" required />
          </div>

          <div class="form-group" style="margin-bottom: 10px;">
            <label for="time_start">Start Time</label>
            <input id="time_start" type="text" name="time_start" value="<?= e((string) ($reservation['time_start'] ?? '')) ?>" placeholder="e.g. 11:00 AM" required />
          </div>

          <div class="form-group" style="margin-bottom: 10px;">
            <label for="time_end">End Time</label>
            <input id="time_end" type="text" name="time_end" value="<?= e((string) ($reservation['time_end'] ?? '')) ?>" placeholder="e.g. 12:00 PM" required />
          </div>

          <div class="form-group" style="margin-bottom: 10px;">
            <label for="row">Row</label>
            <input id="row" type="number" min="1" max="7" name="row" value="<?= (int) ($seat['row'] ?? 1) ?>" required />
          </div>

          <div class="form-group" style="margin-bottom: 10px;">
            <label for="column">Column</label>
            <input id="column" type="number" min="1" max="5" name="column" value="<?= (int) ($seat['column'] ?? 1) ?>" required />
          </div>

          <button type="submit" class="save-btn">Save Changes</button>
        </form>
      </main>
    </div>
    <?php include __DIR__ . '/partials/footer.php'; ?>
  </div>
</body>
</html>
