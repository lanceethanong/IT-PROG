<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Login - LabSlot</title>
  <link rel="stylesheet" href="/public/css/signin.css" />
  <style>
    .form-group { position: relative; margin-bottom: 1.25rem; }
    input[type=password], input[type=text], input[type=email] { width: 100%; box-sizing: border-box; }
  </style>
</head>
<body>
  <div class="login-auth-wrapper">
    <div class="login-box">
      <h1 class="login-title">LOGIN</h1>
      <?php if (!empty($loginError)): ?>
        <p style="color: red;"><?= e($loginError) ?></p>
      <?php endif; ?>
      <?php if (!empty($loginSuccess)): ?>
        <p style="color: green;"><?= e($loginSuccess) ?></p>
      <?php endif; ?>
      <form action="/login" method="POST" id="login-form" novalidate>
        <div class="form-group">
          <label for="email">Email</label><br />
          <input type="email" id="email" name="email" required />
          <span id="emailError" class="error-message"></span>
        </div>

        <div class="form-group">
          <label for="password">Password</label><br />
          <input type="password" id="password" name="password" required />
        </div>

        <div class="form-options">
          <input type="checkbox" id="remember" name="remember" />
          <label for="remember">Remember Me</label>
        </div>

        <div class="form-group">
          <button type="submit" class="login-button">Login</button>
        </div>

        <div class="register-link">
          New Here? <a href="/register">Create an Account</a>
        </div>
      </form>
    </div>
  </div>
  <script src="/public/javascript/validation.js"></script>
</body>
</html>
