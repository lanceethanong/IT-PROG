<aside class="sidebar collapsed" id="sidebar">
  <nav>
    <?php $rolePath = normalize_role_for_path((string) ($role ?? 'Student')); ?>
    <form method="GET" action="/dashboard/<?= e($rolePath) ?>" style="margin:0;">
      <input type="hidden" name="username" value="<?= e($username) ?>" />
      <button type="submit">
        <img src="/public/assets/home.png" alt="Home" />
        <span class="label">Home</span>
      </button>
    </form>

    <?php if (($role ?? '') === 'Lab Technician'): ?>
    <form method="GET" action="/dashboard/technician/reservation-list" style="margin:0;">
      <input type="hidden" name="username" value="<?= e($username) ?>" />
      <button type="submit">
        <img src="/public/assets/reservation.png" alt="Reservation List" />
        <span class="label">Reservation List</span>
      </button>
    </form>
    <?php endif; ?>

    <form method="GET" action="/dashboard/<?= e($rolePath) ?>/help" style="margin:0;">
      <input type="hidden" name="username" value="<?= e($username) ?>" />
      <button type="submit">
        <img src="/public/assets/help.png" alt="Help" />
        <span class="label">Help</span>
      </button>
    </form>

    <form method="GET" action="/logout" style="margin:0;">
      <button type="submit">
        <img src="/public/assets/logout.png" alt="Logout" />
        <span class="label">Logout</span>
      </button>
    </form>
  </nav>
</aside>
