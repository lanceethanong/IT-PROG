<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <link rel="stylesheet" href="/public/css/home.css" />
  <link rel="stylesheet" href="/public/css/help.css" />
  <title>Help &amp; Support</title>
</head>
<body>
  <div class="back-button-container">
    <a href="/dashboard/<?= e(normalize_role_for_path($role)) ?>?username=<?= rawurlencode($username) ?>" class="back-button"><h1>&larr; Back to Home</h1></a>
  </div>

  <div class="help-container">
    <h1 class="help-title">Help and Support</h1>

    <section class="faq-section">
      <h2 class="section-title">FAQs</h2>
      <div class="faq-list">
        <details class="faq-item">
          <summary>How do I book a lab?</summary>
          <p>You must be logged in, then select a lab and date, choose available seats and timeslots, and click reserve slot.</p>
        </details>
        <details class="faq-item">
          <summary>How do I update my profile?</summary>
          <p>Log in, click your profile icon in the top right, and use Edit Profile.</p>
        </details>
        <details class="faq-item">
          <summary>What happens when I'm late for my reservation?</summary>
          <p>Lab technicians may cancel your reservation 10 minutes before the time if you do not show up.</p>
        </details>
        <details class="faq-item">
          <summary>How do I see available slots?</summary>
          <p>Available slots for the next 7 days can be viewed on the calendar on the dashboard.</p>
        </details>
      </div>
    </section>
  </div>
</body>
</html>
