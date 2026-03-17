<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title><?= e($title ?? 'View Profile') ?> - LabSlot</title>
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
                <div class="profile-info">
                  <h2><?= e((string) ($viewedUser['username'] ?? '')) ?></h2>
                  <p><?= e((string) ($viewedUser['role'] ?? '')) ?></p>
                </div>
              </div>
            </div>
            <div class="profile-right">
              <div class="about-section">
                <h3>About</h3>
                <p id="about-text"><?= e((string) ($viewedUser['description'] ?? '')) ?></p>
              </div>
            </div>
          </div>

          <?php if (($currentUser['role'] ?? '') === 'Lab Technician' && ($viewedUser['role'] ?? '') === 'Student'): ?>
            <div class="reservations">
              <h3 style="margin-bottom: 20px;"><?= e((string) ($viewedUser['username'] ?? '')) ?>'s Reservations</h3>
              <h4>Current Reservations</h4>
              <?php if (!empty($upcomingReservations)): ?>
                <div class="reservation-list">
                  <?php foreach ($upcomingReservations as $reservation): ?>
                    <div class="reservation-box">
                      <div class="info">
                        <p><strong>Seat:</strong> Row <?= (int) ($reservation['row'] ?? 0) ?>, Column <?= (int) ($reservation['column'] ?? 0) ?></p>
                        <p><strong>Lab:</strong> <?= e((string) ($reservation['lab'] ?? '')) ?></p>
                        <p><strong>Date:</strong> <?= e((string) ($reservation['date'] ?? '')) ?></p>
                        <p><strong>Time:</strong> <?= e((string) ($reservation['time_start'] ?? '')) ?> - <?= e((string) ($reservation['time_end'] ?? '')) ?></p>
                        <p><strong>Status:</strong> <span class="status <?= e(status_class((string) $reservation['status'])) ?>"><?= e((string) $reservation['status']) ?></span></p>
                      </div>
                    </div>
                  <?php endforeach; ?>
                </div>
              <?php else: ?>
                <p>No current reservations.</p>
              <?php endif; ?>

              <h4 style="margin-top: 20px;">Past Reservations</h4>
              <?php if (!empty($pastReservations)): ?>
                <div class="reservation-list">
                  <?php foreach ($pastReservations as $reservation): ?>
                    <div class="reservation-box">
                      <div class="info">
                        <p><strong>Seat:</strong> Row <?= (int) ($reservation['row'] ?? 0) ?>, Column <?= (int) ($reservation['column'] ?? 0) ?></p>
                        <p><strong>Lab:</strong> <?= e((string) ($reservation['lab'] ?? '')) ?></p>
                        <p><strong>Date:</strong> <?= e((string) ($reservation['date'] ?? '')) ?></p>
                        <p><strong>Time:</strong> <?= e((string) ($reservation['time_start'] ?? '')) ?> - <?= e((string) ($reservation['time_end'] ?? '')) ?></p>
                        <p><strong>Status:</strong> <span class="status <?= e(status_class((string) $reservation['status'])) ?>"><?= e((string) $reservation['status']) ?></span></p>
                      </div>
                    </div>
                  <?php endforeach; ?>
                </div>
              <?php else: ?>
                <p>No past reservations.</p>
              <?php endif; ?>
            </div>
          <?php endif; ?>
        </section>
      </main>
    </div>
    <?php include __DIR__ . '/partials/footer.php'; ?>
  </div>
</body>
</html>
