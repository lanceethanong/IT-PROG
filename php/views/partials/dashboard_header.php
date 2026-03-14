<header class="dashboard-header">
  <div class="menu-toggle" aria-hidden="true">&#9776;</div>
  <div class="welcome-text">Welcome, <?= e($username) ?></div>
  <h1 class="site-title">EZLabs</h1>

  <div class="search-wrapper">
    <form class="search-container" method="GET" action="/dashboard/search-user">
      <input type="hidden" name="username" value="<?= e($username) ?>" />
      <input type="hidden" name="role" value="<?= e($role) ?>" />
      <?php if (isset($selectedLabNumber)): ?><input type="hidden" name="lab" value="<?= (int) $selectedLabNumber ?>" /><?php endif; ?>
      <?php if (isset($selectedDate)): ?><input type="hidden" name="date" value="<?= e((string) $selectedDate) ?>" /><?php endif; ?>
      <?php if (isset($monthOffset)): ?><input type="hidden" name="month" value="<?= (int) $monthOffset ?>" /><?php endif; ?>
      <?php if (isset($studentQuery)): ?><input type="hidden" name="student" value="<?= e((string) $studentQuery) ?>" /><?php endif; ?>
      <?php if (isset($selectedSlotsParam) && (string) $selectedSlotsParam !== ''): ?><input type="hidden" name="sel" value="<?= e((string) $selectedSlotsParam) ?>" /><?php endif; ?>
      <input
        type="text"
        placeholder="Search users..."
        class="search-input"
        name="q"
        value="<?= e((string) ($searchQuery ?? '')) ?>"
      />
      <button class="search-button" type="submit">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
          <path d="M11.742 10.344a6.5 6.5 0 1 0-1.397 1.398h-.001c.03.04.062.078.098.115l3.85 3.85a1 1 0 0 0 1.415-1.414l-3.85-3.85a1.007 1.007 0 0 0-.115-.1zM12 6.5a5.5 5.5 0 1 1-11 0 5.5 5.5 0 0 1 11 0z"/>
        </svg>
      </button>
    </form>
    <div class="search-results" id="search-results"></div>
  </div>

  <a href="/dashboard/<?= e(normalize_role_for_path($role)) ?>/profile?username=<?= rawurlencode($username) ?>" class="profile-link">
    <img src="/public/assets/profile.png" alt="Profile" class="profile-pic" />
  </a>
</header>
