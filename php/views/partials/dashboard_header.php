<header class="dashboard-header">
  <div class="menu-toggle" aria-hidden="true">&#9776;</div>
  <div class="welcome-text">Welcome, <?= e($username) ?></div> //gets the username of the logged in user and displays it in the header
  <h1 class="site-title">EZLabs</h1>

  <?php
    $headerSearchUsers = [];  //Searches for users to display in the search results on the header. This is used to provide instant search results as the user types in the search input. It retrieves all users from the database and encodes them as JSON to be used by JavaScript for filtering and displaying search results without needing additional server requests.
    foreach (users_all() as $u) {
      $headerSearchUsers[] = [
        'username' => (string) ($u['username'] ?? ''),
        'email' => (string) ($u['email'] ?? ''),
        'role' => (string) ($u['role'] ?? ''),
      ];
    }
  ?>
  <script id="search-users-json" type="application/json"><?= (string) json_encode($headerSearchUsers, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?></script>

  <div class="search-wrapper">
    <form class="search-container" method="GET" action="/dashboard/search-user"> 
      <input type="hidden" name="username" value="<?= e($username) ?>" />
      <input type="hidden" name="role" value="<?= e($role) ?>" />
      //Preserves the current filters and search parameters when performing a new search from the header.
      <?php if (isset($selectedLabNumber)): ?><input type="hidden" name="lab" value="<?= (int) $selectedLabNumber ?>" /><?php endif; ?>
      <?php if (isset($selectedDate)): ?><input type="hidden" name="date" value="<?= e((string) $selectedDate) ?>" /><?php endif; ?>
      <?php if (isset($monthOffset)): ?><input type="hidden" name="month" value="<?= (int) $monthOffset ?>" /><?php endif; ?>
      <?php if (isset($studentQuery)): ?><input type="hidden" name="student" value="<?= e((string) $studentQuery) ?>" /><?php endif; ?>
      <?php if (isset($selectedSlotsParam) && (string) $selectedSlotsParam !== ''): ?><input type="hidden" name="sel" value="<?= e((string) $selectedSlotsParam) ?>" /><?php endif; ?>
      //Inputs for searching for users 
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
    <div class="search-results<?= (!empty($searchResults) || (!empty($searchQuery) && empty($searchResults))) ? ' show' : '' ?>" id="search-results">
      <?php if (!empty($searchResults)): ?>
        <?php foreach (($searchResults ?? []) as $u): ?>
          <a
            class="search-result-item"
            style="text-decoration:none; color:inherit;"
            href="/dashboard/view-profile/<?= rawurlencode((string) ($u['username'] ?? '')) ?>?username=<?= rawurlencode((string) $username) ?>"
          >
            <img src="/public/assets/profile.png" alt="<?= e((string) ($u['username'] ?? '')) ?>" class="search-result-pic" />
            <div class="search-result-info">
              <strong><?= e((string) ($u['username'] ?? '')) ?></strong>
              <small><?= e((string) ($u['email'] ?? '')) ?></small>
              <div class="user-role-badge"><?= e((string) ($u['role'] ?? '')) ?></div>
            </div>
          </a>
        <?php endforeach; ?>
      <?php elseif (!empty($searchQuery)): ?>
        <div class="search-result-item">No users found</div>
      <?php endif; ?>
    </div>
  </div>

  //Path for each user
  <a href="/dashboard/<?= e(normalize_role_for_path($role)) ?>/profile?username=<?= rawurlencode($username) ?>" class="profile-link">
    <img src="/public/assets/profile.png" alt="Profile" class="profile-pic" />
  </a>
</header>
