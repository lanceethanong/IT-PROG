<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Register - LabSlot</title>
  <link rel="stylesheet" href="/public/css/signin.css" />
</head>
<body>
  <div class="login-auth-wrapper">
    <div class="login-box">
      <h1 class="login-title">REGISTER</h1>

      <?php if (!empty($registerError)): ?>
        <p style="color: red;"><?= e($registerError) ?></p>
      <?php endif; ?>

      <form action="/register" method="POST" id="register-form">
        <div class="form-group">
          <label for="username">Username</label><br />
          <input type="text" id="username" name="username" required />
        </div>

        <div class="form-group">
          <label for="email">DLSU Email</label><br />
          <input type="email" id="email" name="email" required />
          <span id="emailError" class="error-message"></span>
        </div>

        <div class="form-group">
          <label for="password">Password</label><br />
          <input type="password" id="password" name="password" required />
        </div>

        <div class="form-group">
          <label for="confirmPassword">Confirm Password</label><br />
          <input type="password" id="confirmPassword" name="confirmPassword" required />
          <span id="confirm-password-error" class="error-message"></span>
        </div>

        <div class="form-group">
          <button type="submit" class="login-button">Register</button>
        </div>

        <div class="register-link">
          Already have an account? <a href="/login">Login Now</a>
        </div>
      </form>
    </div>
  </div>
</body>
</html>
