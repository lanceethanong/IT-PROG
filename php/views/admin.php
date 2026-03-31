<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title><?= e($title ?? 'Admin') ?></title>
  <link rel="stylesheet" href="/public/css/admin.css" />
</head>
<body>
  <h1><?= e($title ?? 'Admin') ?></h1>
  <p>Logged in as: <strong><?= e($adminName ?? '') ?></strong></p>
  <nav style="margin-bottom: 20px;">
    <a href="/admin"><button type="button">Home</button></a>
    <a href="/admin/view-labtech"><button type="button">View All Lab Technicians</button></a>
    <a href="/admin/add-labtech"><button type="button">Add Lab Technician</button></a>
    <a href="/admin/remove-labtech"><button type="button">Remove Lab Technician</button></a>
    <a href="/admin/events"><button type="button">Manage Events</button></a>
    <a href="/logout"><button type="button" style="float:right;">Logout</button></a>
  </nav>

  <?php if (($page ?? '') === 'home'): ?>
    <h2>Welcome, Admin!</h2>
    <p>Select an action using the buttons above.</p>
  <?php endif; ?>

  <?php if (!empty($successMessage)): ?>
    <div style="color: green; font-weight: bold; margin-bottom: 10px;"><?= e($successMessage) ?></div>
  <?php endif; ?>

  <?php if (($page ?? '') === 'view'): ?>
    <h2>All Lab Technicians</h2>
    <ul>
      <?php if (!empty($techs)): ?>
        <?php foreach ($techs as $tech): ?>
          <li><strong><?= e((string) ($tech['username'] ?? '')) ?></strong> (<?= e((string) ($tech['email'] ?? '')) ?>)</li>
        <?php endforeach; ?>
      <?php else: ?>
        <li>No lab technicians found.</li>
      <?php endif; ?>
    </ul>
  <?php endif; ?>

  <?php if (($page ?? '') === 'add'): ?>
    <h2>Add Lab Technician</h2>
    <?php if (!empty($registerError)): ?>
      <div style="color: red; font-weight: bold;"><?= e($registerError) ?></div>
    <?php endif; ?>
    <form action="/admin/add-labtech" method="POST">
      <label>Username: <input type="text" name="username" required /></label><br />
      <label>Email: <input type="email" name="email" required /></label><br />
      <label>Password: <input type="password" name="password" required /></label><br />
      <button type="submit">Add</button>
    </form>
  <?php endif; ?>

  <?php if (($page ?? '') === 'remove'): ?>
    <h2>Remove Lab Technician</h2>
    <?php if (!empty($removeError)): ?>
      <div style="color: red; font-weight: bold;"><?= e($removeError) ?></div>
    <?php endif; ?>
    <form action="/admin/remove-labtech" method="POST">
      <label>Username: <input type="text" name="username" required /></label><br />
      <button type="submit">Remove</button>
    </form>
  <?php endif; ?>

  <?php if (($page ?? '') === 'events'): ?>
    <h2>Manage Events</h2>
    <?php if (!empty($successMessage)): ?>
      <div style="color: green; font-weight: bold; margin-bottom: 10px;"><?= e($successMessage) ?></div>
    <?php endif; ?>
    <h3>Create New Event</h3>
    <form action="/admin/create-event" method="POST">
      <label>Event Name: <input type="text" name="name" required /></label><br />
      <label>Lab: 
        <select name="lab_id" required>
          <option value="">Select Lab</option>
          <?php if (!empty($labs)): ?>
            <?php foreach ($labs as $lab): ?>
              <option value="<?= e($lab['_id']) ?>">Lab <?= e($lab['number']) ?> - <?= e($lab['class']) ?></option>
            <?php endforeach; ?>
          <?php endif; ?>
        </select>
      </label><br />
      <label>Date: <input type="date" name="date" required /></label><br />
      <label>Start Time: <input type="time" name="time_start" required /></label><br />
      <label>End Time: <input type="time" name="time_end" required /></label><br />
      <label>Description: <textarea name="description"></textarea></label><br />
      <button type="submit">Create Event</button>
    </form>
    <h3>Existing Events</h3>
    <ul>
      <?php if (!empty($events)): ?>
        <?php foreach ($events as $event): ?>
          <li>
            <?= e($event['name']) ?> on <?= e($event['date']) ?> from <?= e($event['time_start']) ?> to <?= e($event['time_end']) ?>
            <form action="/admin/delete-event" method="POST" style="display:inline;">
              <input type="hidden" name="event_id" value="<?= e($event['_id']) ?>" />
              <button type="submit">Delete</button>
            </form>
          </li>
        <?php endforeach; ?>
      <?php else: ?>
        <li>No events found.</li>
      <?php endif; ?>
    </ul>
  <?php endif; ?>
</body>
</html>
