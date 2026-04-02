<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title><?= e($title ?? 'Profile') ?> - LabSlot</title>
  <link rel="stylesheet" href="/public/css/dashboard.css" />
  <link rel="stylesheet" href="/public/css/profile.css" />
  <link rel="stylesheet" href="/public/css/reservation.css" />
</head>
<body data-role="<?= e($role) ?>" data-username="<?= e($username) ?>">
  <div class="dashboard-layout">
    <?php include __DIR__ . '/partials/dashboard_header.php'; ?>
    <div class="dashboard-main">
      <?php include __DIR__ . '/partials/dashboard_sidebar.php'; ?>
      <main class="content">
        <section class="profile-section">
          <h2 style="margin-bottom: 1.5rem; font-size: 1.5rem; font-weight: bold;">
            All Reservations
          </h2>

          <!-- CURRENT / UPCOMING RESERVATIONS -->
          <div class="reservations">
            <h3 style="margin-bottom: 1rem;">Current &amp; Upcoming Reservations</h3>
            <?php if (!empty($currentReservations)): ?>
              <div class="reservation-list">
                <?php foreach ($currentReservations as $r): ?>
                  <div class="reservation-box <?= e(status_class((string) ($r['status'] ?? ''))) ?>">
                    <div class="info">
                      <p><strong>Student:</strong> <?= e((string) ($r['student'] ?? '')) ?></p>
                      <p><strong>Seat:</strong> Row <?= (int) ($r['row'] ?? 0) ?>, Column <?= (int) ($r['column'] ?? 0) ?></p>
                      <p><strong>Lab:</strong> <?= e((string) ($r['lab'] ?? '')) ?></p>
                      <p><strong>Date:</strong> <?= e((string) ($r['date'] ?? '')) ?></p>
                      <p><strong>Time:</strong> <?= e((string) ($r['time_start'] ?? '')) ?> – <?= e((string) ($r['time_end'] ?? '')) ?></p>
                      <p><strong>Status:</strong>
                        <span class="status <?= e(status_class((string) ($r['status'] ?? ''))) ?>">
                          <?= e((string) ($r['status'] ?? '')) ?>
                        </span>
                      </p>
                      <?php if ((string) ($r['status'] ?? '') === 'Cancelled' && !empty($r['cancel_reason'])): ?>
                        <p><strong>Cancellation Reason:</strong> <?= e((string) $r['cancel_reason']) ?></p>
                      <?php endif; ?>
                    </div>
                    <div class="actions">
                      <?php if (!empty($r['canEdit'])): ?>
                        <a href="/reservation/edit?id=<?= rawurlencode((string) $r['_id']) ?>">
                          <button class="edit-btn" type="button">Edit</button>
                        </a>
                      <?php else: ?>
                        <button class="edit-btn disabled" type="button" disabled title="Cannot edit at this time">Edit</button>
                      <?php endif; ?>

                      <?php if (!empty($r['canDelete'])): ?>
                        <form method="POST" action="/reservation/delete" style="display:inline;">
                          <input type="hidden" name="reservation_id" value="<?= e((string) $r['_id']) ?>" />
                          <button class="delete-btn" type="submit"
                            onclick="return confirm('Cancel this reservation for <?= e(addslashes((string)($r['student']??''))) ?>?')">
                            Cancel (No-show)
                          </button>
                        </form>
                      <?php else: ?>
                        <button class="delete-btn disabled" type="button" disabled
                          title="Can only cancel within 10 minutes of start time when student hasn't arrived">
                          Cancel
                        </button>
                      <?php endif; ?>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php else: ?>
              <p style="color: #6b7280; padding: 1rem 0;">No current or upcoming reservations.</p>
            <?php endif; ?>
          </div>

          <!-- PAST RESERVATIONS -->
          <div class="reservations" style="margin-top: 2rem;">
            <h3 style="margin-bottom: 1rem;">Past Reservations</h3>
            <?php if (!empty($pastReservations)): ?>
              <div class="reservation-list">
                <?php foreach ($pastReservations as $r): ?>
                  <div class="reservation-box <?= e(status_class((string) ($r['status'] ?? ''))) ?>">
                    <div class="info">
                      <p><strong>Student:</strong> <?= e((string) ($r['student'] ?? '')) ?></p>
                      <p><strong>Seat:</strong> Row <?= (int) ($r['row'] ?? 0) ?>, Column <?= (int) ($r['column'] ?? 0) ?></p>
                      <p><strong>Lab:</strong> <?= e((string) ($r['lab'] ?? '')) ?></p>
                      <p><strong>Date:</strong> <?= e((string) ($r['date'] ?? '')) ?></p>
                      <p><strong>Time:</strong> <?= e((string) ($r['time_start'] ?? '')) ?> – <?= e((string) ($r['time_end'] ?? '')) ?></p>
                      <p><strong>Status:</strong>
                        <span class="status <?= e(status_class((string) ($r['status'] ?? ''))) ?>">
                          <?= e((string) ($r['status'] ?? '')) ?>
                        </span>
                      </p>
                      <?php if ((string) ($r['status'] ?? '') === 'Cancelled' && !empty($r['cancel_reason'])): ?>
                        <p><strong>Cancellation Reason:</strong> <?= e((string) $r['cancel_reason']) ?></p>
                      <?php endif; ?>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php else: ?>
              <p style="color: #6b7280; padding: 1rem 0;">No past reservations found.</p>
            <?php endif; ?>
          </div>

        </section>

      </main>
    </div>
    <?php include __DIR__ . '/partials/footer.php'; ?>
  </div>
</body>
</html>