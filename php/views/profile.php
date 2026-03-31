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
          <div class="profile-header">
            <div class="profile-left">
              <div class="profile-card-portrait">
                <div class="profile-photo">
                  <img src="/public/assets/profile.png" alt="Profile Photo" />
                </div>
                <div class="profile-info" id="profile-fields">
                  <h2><?= e($username) ?></h2>
                  <p><?= e($role) ?></p>
                </div>
              </div>
            </div>
            <div class="profile-right">
              <div class="about-section">
                <h3>About Me</h3>
                <form method="POST" action="/profile/update">
                  <textarea id="about-text" name="description"><?= e((string) ($description ?? '')) ?></textarea>
                  <div style="margin-top: 10px;">
                    <button type="submit" class="save-btn">Save Changes</button>
                  </div>
                </form>
              </div>
            </div>
          </div>

          <?php if ($role === 'Student'): ?>
          <div class="reservations">
            <h3 style="margin-bottom: 20px;">My Reservations</h3>
            <h4>Current Reservations</h4>
            <div id="upcoming-reservations-list">
              <?php if (!empty($upcomingReservations)): ?>
                <div class="reservation-list">
                  <?php foreach ($upcomingReservations as $r): ?>
                    <div class="reservation-box <?= e(status_class((string) $r['status'])) ?>" data-created="<?= e((string) ($r['createdAt'] ?? '')) ?>">
                      <div class="info">
                        <p><strong>Seat:</strong> Row <?= (int) ($r['row'] ?? 0) ?>, Column <?= (int) ($r['column'] ?? 0) ?></p>
                        <p><strong>Lab:</strong> <?= e((string) ($r['lab'] ?? '')) ?></p>
                        <p><strong>Date:</strong> <?= e((string) ($r['date'] ?? '')) ?></p>
                        <p><strong>Time:</strong> <?= e((string) ($r['time_start'] ?? '')) ?> - <?= e((string) ($r['time_end'] ?? '')) ?></p>
                        <p><strong>Status:</strong> <span class="status <?= e(status_class((string) $r['status'])) ?>"><?= e((string) $r['status']) ?></span></p>
                        <?php if ((string) $r['status'] === 'Cancelled' && !empty($r['cancel_reason'])): ?>
                          <p><strong>Cancellation Reason:</strong> <?= e((string) $r['cancel_reason']) ?></p>
                        <?php endif; ?>
                      </div>
                      <?php if (($r['status'] ?? '') !== 'Completed' && ($r['status'] ?? '') !== 'Cancelled'): ?>
                        <div class="actions">
                          <?php if (!empty($r['canEdit'])): ?>
                            <a href="/reservation/edit?id=<?= rawurlencode((string) $r['_id']) ?>"><button class="edit-btn" type="button">Edit</button></a>
                          <?php else: ?>
                            <button class="edit-btn disabled" type="button" disabled>Edit</button>
                          <?php endif; ?>

                          <?php if (!empty($r['canDelete'])): ?>
                            <form method="POST" action="/reservation/delete" style="display:inline;">
                              <input type="hidden" name="reservation_id" value="<?= e((string) $r['_id']) ?>" />
                              <button class="delete-btn" type="submit">Delete</button>
                            </form>
                          <?php else: ?>
                            <button class="delete-btn disabled" type="button" disabled>Delete</button>
                          <?php endif; ?>
                        </div>
                      <?php endif; ?>
                    </div>
                  <?php endforeach; ?>
                </div>
              <?php else: ?>
                No current reservations.
              <?php endif; ?>
            </div>

            <h4 style="margin-top: 20px;">Past Reservations</h4>
            <div id="past-reservations-list">
              <?php if (!empty($pastReservations)): ?>
                <div class="reservation-list">
                  <?php foreach ($pastReservations as $r): ?>
                    <div class="reservation-box <?= e(status_class((string) $r['status'])) ?>" data-created="<?= e((string) ($r['createdAt'] ?? '')) ?>">
                      <div class="info">
                        <p><strong>Seat:</strong> Row <?= (int) ($r['row'] ?? 0) ?>, Column <?= (int) ($r['column'] ?? 0) ?></p>
                        <p><strong>Lab:</strong> <?= e((string) ($r['lab'] ?? '')) ?></p>
                        <p><strong>Date:</strong> <?= e((string) ($r['date'] ?? '')) ?></p>
                        <p><strong>Time:</strong> <?= e((string) ($r['time_start'] ?? '')) ?> - <?= e((string) ($r['time_end'] ?? '')) ?></p>
                        <p><strong>Status:</strong> <span class="status <?= e(status_class((string) $r['status'])) ?>"><?= e((string) $r['status']) ?></span></p>
                        <?php if ((string) $r['status'] === 'Cancelled' && !empty($r['cancel_reason'])): ?>
                          <p><strong>Cancellation Reason:</strong> <?= e((string) $r['cancel_reason']) ?></p>
                        <?php endif; ?>
                      </div>
                    </div>
                  <?php endforeach; ?>
                </div>
              <?php else: ?>
                No past reservations.
              <?php endif; ?>
            </div>
          </div>
          <?php endif; ?>

          <div class="account-actions">
            <h3>Account Actions</h3>
            <?php if (!empty($passwordFlash) && is_array($passwordFlash)): ?>
              <p style="margin: 8px 0 12px; font-weight: 600; color: <?= (($passwordFlash['type'] ?? '') === 'success') ? '#166534' : '#b91c1c' ?>;">
                <?= e((string) ($passwordFlash['message'] ?? '')) ?>
              </p>
            <?php endif; ?>
            <button type="submit" form="change-password-nav-form">Change Password</button>
            <button type="submit" form="delete-account-form">Delete Account</button>

            <form id="change-password-nav-form" method="GET" action="/account/change-password"></form>
            <form id="delete-account-form" method="POST" action="/account/delete"></form>
          </div>
        </section>
      </main>
    </div>
    <?php include __DIR__ . '/partials/footer.php'; ?>
  </div>
</body>
</html>
