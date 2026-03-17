<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>All Reservations - LabSlot</title>
  <link rel="stylesheet" href="/public/css/dashboard.css" />
  <link rel="stylesheet" href="/public/css/home.css" />
  <link rel="stylesheet" href="/public/css/reservation.css" />
</head>
<body data-role="Lab Technician" data-username="<?= e($username) ?>">
  <div class="dashboard-layout">
    <?php $role = 'Lab Technician'; include __DIR__ . '/partials/dashboard_header.php'; ?>
    <div class="dashboard-main">
      <?php include __DIR__ . '/partials/dashboard_sidebar.php'; ?>
      <main class="content">
        <h1>Reservation List</h1>
        <div id="reservation-success-message" class="success-message" style="display:none;"></div>

        <h3 style="margin-top: 10px;">Current Reservations</h3>
        <div class="reservation-list">
          <?php if (!empty($currentReservations)): ?>
          <?php foreach ($currentReservations as $reservation): ?>
            <div class="reservation-box <?= e(status_class((string) $reservation['status'])) ?>" data-created="<?= e((string) ($reservation['createdAt'] ?? '')) ?>">
              <div class="info">
                <p><strong>Student:</strong> <?= e((string) ($reservation['student'] ?? '')) ?></p>
                <p><strong>Lab:</strong> <?= e((string) ($reservation['lab'] ?? '')) ?></p>
                <p><strong>Seat:</strong> Row <?= (int) ($reservation['row'] ?? 0) ?>, Column <?= (int) ($reservation['column'] ?? 0) ?></p>
                <p><strong>Time:</strong> <?= e((string) ($reservation['time_start'] ?? '')) ?> - <?= e((string) ($reservation['time_end'] ?? '')) ?></p>
                <p><strong>Date:</strong> <?= e((string) ($reservation['date'] ?? '')) ?></p>
                <p><strong>Created At:</strong> <?= e((string) ($reservation['createdAt'] ?? '')) ?></p>
                <p class="status"><strong>Status:</strong> <span class="status <?= e(status_class((string) $reservation['status'])) ?>"><?= e((string) $reservation['status']) ?></span></p>
              </div>
              <div class="actions">
                <?php if (!empty($reservation['canEdit'])): ?>
                  <a href="/reservation/edit?id=<?= rawurlencode((string) $reservation['_id']) ?>"><button class="edit-btn" type="button">Edit</button></a>
                <?php else: ?>
                  <button class="edit-btn disabled" type="button" disabled>Edit</button>
                <?php endif; ?>

                <?php if (!empty($reservation['canDelete'])): ?>
                  <form method="POST" action="/reservation/delete" style="display:inline;">
                    <input type="hidden" name="reservation_id" value="<?= e((string) $reservation['_id']) ?>" />
                    <button class="delete-btn" type="submit">Delete</button>
                  </form>
                <?php else: ?>
                  <button class="delete-btn disabled" type="button" disabled>Delete</button>
                <?php endif; ?>
              </div>
            </div>
          <?php endforeach; ?>
          <?php else: ?>
            <p>No current reservations.</p>
          <?php endif; ?>
        </div>

        <h3 style="margin-top: 20px;">Past Reservations</h3>
        <div class="reservation-list">
          <?php if (!empty($pastReservations)): ?>
          <?php foreach ($pastReservations as $reservation): ?>
            <div class="reservation-box <?= e(status_class((string) $reservation['status'])) ?>" data-created="<?= e((string) ($reservation['createdAt'] ?? '')) ?>">
              <div class="info">
                <p><strong>Student:</strong> <?= e((string) ($reservation['student'] ?? '')) ?></p>
                <p><strong>Lab:</strong> <?= e((string) ($reservation['lab'] ?? '')) ?></p>
                <p><strong>Seat:</strong> Row <?= (int) ($reservation['row'] ?? 0) ?>, Column <?= (int) ($reservation['column'] ?? 0) ?></p>
                <p><strong>Time:</strong> <?= e((string) ($reservation['time_start'] ?? '')) ?> - <?= e((string) ($reservation['time_end'] ?? '')) ?></p>
                <p><strong>Date:</strong> <?= e((string) ($reservation['date'] ?? '')) ?></p>
                <p><strong>Created At:</strong> <?= e((string) ($reservation['createdAt'] ?? '')) ?></p>
                <p class="status"><strong>Status:</strong> <span class="status <?= e(status_class((string) $reservation['status'])) ?>"><?= e((string) $reservation['status']) ?></span></p>
              </div>
              <div class="actions">
                <?php if (!empty($reservation['canEdit'])): ?>
                  <a href="/reservation/edit?id=<?= rawurlencode((string) $reservation['_id']) ?>"><button class="edit-btn" type="button">Edit</button></a>
                <?php else: ?>
                  <button class="edit-btn disabled" type="button" disabled>Edit</button>
                <?php endif; ?>

                <?php if (!empty($reservation['canDelete'])): ?>
                  <form method="POST" action="/reservation/delete" style="display:inline;">
                    <input type="hidden" name="reservation_id" value="<?= e((string) $reservation['_id']) ?>" />
                    <button class="delete-btn" type="submit">Delete</button>
                  </form>
                <?php else: ?>
                  <button class="delete-btn disabled" type="button" disabled>Delete</button>
                <?php endif; ?>
              </div>
            </div>
          <?php endforeach; ?>
          <?php else: ?>
            <p>No past reservations.</p>
          <?php endif; ?>
        </div>
      </main>
    </div>
    <?php include __DIR__ . '/partials/footer.php'; ?>
  </div>
</body>
</html>
