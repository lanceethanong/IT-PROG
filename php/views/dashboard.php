<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title><?= e($title ?? 'Dashboard') ?> - LabSlot</title>
  <link rel="stylesheet" href="/public/css/dashboard.css" />
  <link rel="stylesheet" href="/public/css/home.css" />
  <link rel="stylesheet" href="/public/css/reservation.css" />
</head>
<body data-role="<?= e($role) ?>" data-username="<?= e($username) ?>">
  <div class="dashboard-layout">
    <?php include __DIR__ . '/partials/dashboard_header.php'; ?>

    <div class="dashboard-main">
      <?php include __DIR__ . '/partials/dashboard_sidebar.php'; ?>
      <main class="content">
        <div class="clock"><?= e((new DateTime('now', new DateTimeZone('Asia/Manila')))->format('Y-m-d | h:i:s A')) ?></div>

        <?php if (!empty($searchError)): ?>
          <p style="color:#b91c1c; font-weight:600;"><?= e((string) $searchError) ?></p>
        <?php endif; ?>
        <?php if (!empty($reserveError)): ?>
          <p style="color:#b91c1c; font-weight:600;"><?= e((string) $reserveError) ?></p>
        <?php endif; ?>
        <?php if (!empty($reserveSuccess)): ?>
          <p style="color:#166534; font-weight:600;"><?= e((string) $reserveSuccess) ?></p>
        <?php endif; ?>

        <div class="dashboard-columns">
          <div class="left-column">
            <section class="rooms-section">
              <h3>Available Rooms</h3>
              <div class="rooms-list" id="rooms">
                <?php foreach (($labs ?? []) as $lab): ?>
                  <?php $isSelected = ((int) ($lab['number'] ?? 0) === (int) ($selectedLabNumber ?? 0)); ?>
                  <div class="<?= $isSelected ? 'selected' : '' ?>">
                    <a href="/dashboard/<?= e(normalize_role_for_path((string) $role)) ?>?username=<?= rawurlencode((string) $username) ?>&lab=<?= (int) ($lab['number'] ?? 0) ?>&date=<?= rawurlencode((string) ($selectedDate ?? '')) ?>&month=<?= (int) ($monthOffset ?? 0) ?>&q=<?= rawurlencode((string) ($searchQuery ?? '')) ?>&student=<?= rawurlencode((string) ($studentQuery ?? '')) ?>" style="display:block; color:inherit; text-decoration:none;">
                      Lab <?= (int) ($lab['number'] ?? 0) ?> (<?= e((string) ($lab['class'] ?? '')) ?>)
                    </a>
                  </div>
                <?php endforeach; ?>
              </div>
            </section>
          </div>

          <div class="right-column">
            <section class="calendar-section">
              <div class="calendar-controls">
                <a href="/dashboard/<?= e(normalize_role_for_path((string) $role)) ?>?username=<?= rawurlencode((string) $username) ?>&lab=<?= (int) ($selectedLabNumber ?? 0) ?>&date=<?= rawurlencode((string) ($selectedDate ?? '')) ?>&month=<?= (int) (($monthOffset ?? 0) - 1) ?>&q=<?= rawurlencode((string) ($searchQuery ?? '')) ?>&student=<?= rawurlencode((string) ($studentQuery ?? '')) ?>">
                  <button type="button">Prev</button>
                </a>
                <h2><?= e((string) ($monthLabel ?? '')) ?></h2>
                <a href="/dashboard/<?= e(normalize_role_for_path((string) $role)) ?>?username=<?= rawurlencode((string) $username) ?>&lab=<?= (int) ($selectedLabNumber ?? 0) ?>&date=<?= rawurlencode((string) ($selectedDate ?? '')) ?>&month=<?= (int) (($monthOffset ?? 0) + 1) ?>&q=<?= rawurlencode((string) ($searchQuery ?? '')) ?>&student=<?= rawurlencode((string) ($studentQuery ?? '')) ?>">
                  <button type="button">Next</button>
                </a>
              </div>
              <div class="calendar-grid" id="calendar">
                <?php foreach (($calendarDays ?? []) as $cell): ?>
                  <?php if (!empty($cell['isSelectable'])): ?>
                    <a
                      href="/dashboard/<?= e(normalize_role_for_path((string) $role)) ?>?username=<?= rawurlencode((string) $username) ?>&lab=<?= (int) ($selectedLabNumber ?? 0) ?>&date=<?= rawurlencode((string) ($cell['date'] ?? '')) ?>&month=<?= (int) ($monthOffset ?? 0) ?>&q=<?= rawurlencode((string) ($searchQuery ?? '')) ?>&student=<?= rawurlencode((string) ($studentQuery ?? '')) ?>"
                      class="<?= e((string) ($cell['cellClass'] ?? 'disabled')) ?><?= !empty($cell['isSelected']) ? ' active' : '' ?>"
                      style="text-decoration:none; color:inherit;"
                    >
                      <div><?= e((string) ($cell['weekday'] ?? '')) ?></div>
                      <div><?= e((string) ($cell['day'] ?? '')) ?></div>
                    </a>
                  <?php else: ?>
                    <div class="<?= e((string) ($cell['cellClass'] ?? 'disabled')) ?><?= !empty($cell['isSelected']) ? ' active' : '' ?>">
                      <div><?= e((string) ($cell['weekday'] ?? '')) ?></div>
                      <div><?= e((string) ($cell['day'] ?? '')) ?></div>
                    </div>
                  <?php endif; ?>
                <?php endforeach; ?>
              </div>
            </section>
          </div>
        </div>

        <div class="seat-section">
          <section class="seat-info">
            <h3 id="selectedInfo">Seat availability at Lab Room: <?= e((string) ($selectedLabName ?? '(not selected)')) ?></h3>
          </section>

          <div class="seat-legend">
            <div><div class="legend unavailable"></div><span>Unavailable</span></div>
            <div><div class="legend reserved"></div><span>Your Reservation/s</span></div>
            <div><div class="legend available"></div><span>Available</span></div>
          </div>

          <div class="seat-table-wrapper">
            <table class="seat-table" id="seatTable">
              <thead>
                <tr>
                  <th>Seat</th>
                  <?php foreach (($hours ?? []) as $hour): ?>
                    <th colspan="2"><?= e((string) $hour) ?></th>
                  <?php endforeach; ?>
                </tr>
              </thead>
              <tbody>
                <?php foreach (($seatRows ?? []) as $rowCols): ?>
                  <?php foreach ($rowCols as $seat): ?>
                    <tr>
                      <td>Seat <?= (int) ($seat['seatNumber'] ?? 0) ?></td>
                      <?php foreach (($seat['slots'] ?? []) as $slot): ?>
                        <td class="<?= e((string) ($slot['class'] ?? 'available')) ?>">
                            <?php $slotKey = (string) ($slot['slotKey'] ?? ''); ?>
                            <a
                              class="slot-link"
                              href="<?= e((string) ($slot['toggleUrl'] ?? '#')) ?>"
                              data-slot-key="<?= e($slotKey) ?>"
                              data-clickable="<?= !empty($slot['clickable']) ? '1' : '0' ?>"
                              style="display:block; width:100%; height:100%; min-height:20px; text-decoration:none;"
                            >&nbsp;</a>
                        </td>
                      <?php endforeach; ?>
                    </tr>
                  <?php endforeach; ?>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>

          <div class="action-button">
            <form method="POST" action="/dashboard/reserve" style="max-width: 560px; margin: 0 auto; text-align:left;">
              <input type="hidden" name="lab" value="<?= (int) ($selectedLabNumber ?? 0) ?>" />
              <input type="hidden" name="date" value="<?= e((string) ($selectedDate ?? '')) ?>" />
              <input type="hidden" name="month" value="<?= (int) ($monthOffset ?? 0) ?>" />
              <input type="hidden" name="q" value="<?= e((string) ($searchQuery ?? '')) ?>" />
              <input type="hidden" name="sel" value="<?= e((string) ($selectedSlotsParam ?? '')) ?>" />

              <?php if ($role === 'Lab Technician'): ?>
                <div style="margin-bottom: 12px;">
                  <label for="student_username" style="display:block; font-weight:600; margin-bottom: 4px;">Student Username</label>
                  <div class="search-wrapper" style="margin:0; max-width:none;">
                    <input
                      type="text"
                      id="technician-student-search"
                      name="student_username"
                      value="<?= e((string) ($studentQuery ?? '')) ?>"
                      autocomplete="off"
                      required
                    />
                    <div id="technician-student-search-results"></div>
                  </div>
                </div>
              <?php else: ?>
                <div class="anonymity-wrapper" style="margin: 20px auto 0; max-width: 420px; text-align: center;">
                  <label class="anonymity-checkbox-label" style="display: flex; align-items: center; justify-content: center; gap: 8px; font-size: 14px; color: #2d3748; cursor: pointer;">
                    <input type="checkbox" id="anonymityToggle" name="anonymity" style="margin: 0; transform: scale(1.1);"/>
                    <span>Reserve Anonymously</span>
                  </label>
                </div>
              <?php endif; ?>

              <button type="submit" style="margin-top: 12px;"><?= $role === 'Lab Technician' ? 'Reserve for Student' : 'Reserve Slot' ?></button>
            </form>
          </div>
        </div>
      </main>
    </div>
    <?php include __DIR__ . '/partials/footer.php'; ?>
  </div>
</body>
</html>
