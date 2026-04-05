<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Change Password - LabSlot</title>
  <link rel="stylesheet" href="/public/css/dashboard.css" />
  <link rel="stylesheet" href="/public/css/profile.css" />
  <style>
    .about-section .form-group { position: relative; margin-bottom: 1.25rem; }
    .about-section input[type=password],
    .about-section input[type=text] { width: 100%; box-sizing: border-box; }
  </style>
</head>
<body data-role="<?= e($role) ?>" data-username="<?= e($username) ?>">
  <div class="dashboard-layout">
    <?php include __DIR__ . '/partials/dashboard_header.php'; ?>
    <div class="dashboard-main">
      <?php include __DIR__ . '/partials/dashboard_sidebar.php'; ?>
      <main class="content">
        <section class="profile-section">
          <div class="about-section" style="max-width: 520px;">
            <h3>Change Password</h3>
            <form method="POST" action="/account/change-password" novalidate>
              <div class="form-group">
                <label for="currentPassword">Current Password</label>
                <input id="currentPassword" type="password" name="currentPassword" required />
              </div>
              <div class="form-group">
                <label for="newPassword">New Password</label>
                <input id="newPassword" type="password" name="newPassword" minlength="8" required />
              </div>
              <div class="form-group">
                <label for="confirmPassword">Confirm Password</label>
                <input id="confirmPassword" type="password" name="confirmPassword" minlength="8" required />
              </div>
              <button type="submit" class="submit-btn">Change Password</button>
            </form>
          </div>
        </section>
      </main>
    </div>
    <?php include __DIR__ . '/partials/footer.php'; ?>
  </div>
  <script src="/public/javascript/validation.js"></script>
</body>
</html>
