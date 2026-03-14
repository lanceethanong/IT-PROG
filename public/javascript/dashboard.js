document.addEventListener('DOMContentLoaded', function () {
  if (window.__ezlabsDashboardInit === true) {
    return;
  }
  window.__ezlabsDashboardInit = true;

  function getBasePath() {
    const marker = '/dashboard';
    const path = window.location.pathname || '';
    const idx = path.indexOf(marker);
    return idx >= 0 ? path.slice(0, idx) : '';
  }

  function appUrl(path) {
    const p = path.charAt(0) === '/' ? path : '/' + path;
    return getBasePath() + p;
  }

  const menuToggle = document.querySelector('.menu-toggle');
  const sidebar = document.getElementById('sidebar');
  if (menuToggle && sidebar) {
    menuToggle.addEventListener('click', function () {
      sidebar.classList.toggle('collapsed');
    });
  }

  const searchInput = document.querySelector('.search-input[name="q"]');
  const searchResults = document.getElementById('search-results');

  if (searchInput && searchResults) {
    let debounceTimer = null;

    function hideSearchResults() {
      searchResults.innerHTML = '';
      searchResults.classList.remove('show');
    }

    function renderSearchResults(users) {
      if (!Array.isArray(users) || users.length === 0) {
        searchResults.innerHTML = '<div class="search-result-item">No users found</div>';
        searchResults.classList.add('show');
        return;
      }

      const currentUsername = document.body.dataset.username || '';
      const html = users.map(function (u) {
        const username = String(u.username || '');
        const email = String(u.email || '');
        const role = String(u.role || '');
        const href = appUrl('/dashboard/view-profile/' + encodeURIComponent(username) + '?username=' + encodeURIComponent(currentUsername));
        return ''
          + '<a class="search-result-item" style="text-decoration:none;" href="' + href + '">'
          + '<img src="' + appUrl('/public/assets/profile.png') + '" alt="' + username + '" class="search-result-pic" />'
          + '<div class="search-result-info">'
          + '<strong>' + username + '</strong>'
          + '<small>' + email + '</small>'
          + '<div class="user-role-badge">' + role + '</div>'
          + '</div>'
          + '</a>';
      }).join('');

      searchResults.innerHTML = html;
      searchResults.classList.add('show');
    }

    searchInput.addEventListener('input', function () {
      const q = searchInput.value.trim();
      if (debounceTimer) {
        clearTimeout(debounceTimer);
      }
      if (q.length < 1) {
        hideSearchResults();
        return;
      }

      debounceTimer = setTimeout(function () {
        fetch(appUrl('/api/users/search/' + encodeURIComponent(q)))
          .then(function (r) { return r.ok ? r.json() : []; })
          .then(function (users) { renderSearchResults(users); })
          .catch(function () { hideSearchResults(); });
      }, 180);
    });

    document.addEventListener('click', function (e) {
      if (!e.target.closest('.search-wrapper')) {
        hideSearchResults();
      }
    });
  }

  const studentSearchInput = document.getElementById('technician-student-search');
  const studentSearchResults = document.getElementById('technician-student-search-results');

  if (studentSearchInput && studentSearchResults) {
    let studentDebounceTimer = null;

    function hideStudentSearchResults() {
      studentSearchResults.innerHTML = '';
      studentSearchResults.classList.remove('show');
    }

    function pickStudentUsername(username) {
      studentSearchInput.value = username;
      hideStudentSearchResults();
    }

    function renderStudentSearchResults(users) {
      const students = (Array.isArray(users) ? users : []).filter(function (u) {
        return String(u.role || '').toLowerCase() === 'student';
      });

      if (students.length === 0) {
        studentSearchResults.innerHTML = '<div class="search-result-item">No students found</div>';
        studentSearchResults.classList.add('show');
        return;
      }

      const html = students.map(function (u) {
        const username = String(u.username || '');
        const email = String(u.email || '');
        return ''
          + '<div class="search-result-item" data-username="' + username.replace(/"/g, '&quot;') + '">'
          + '<img src="' + appUrl('/public/assets/profile.png') + '" alt="' + username + '" class="search-result-pic" />'
          + '<div class="search-result-info">'
          + '<strong>' + username + '</strong>'
          + '<small>' + email + '</small>'
          + '<div class="user-role-badge">Student</div>'
          + '</div>'
          + '</div>';
      }).join('');

      studentSearchResults.innerHTML = html;
      studentSearchResults.classList.add('show');
    }

    studentSearchInput.addEventListener('input', function () {
      const q = studentSearchInput.value.trim();
      if (studentDebounceTimer) {
        clearTimeout(studentDebounceTimer);
      }

      if (q.length < 1) {
        hideStudentSearchResults();
        return;
      }

      studentDebounceTimer = setTimeout(function () {
        fetch(appUrl('/api/users/search/' + encodeURIComponent(q)))
          .then(function (r) { return r.ok ? r.json() : []; })
          .then(function (users) { renderStudentSearchResults(users); })
          .catch(function () { hideStudentSearchResults(); });
      }, 180);
    });

    studentSearchResults.addEventListener('click', function (e) {
      const item = e.target.closest('.search-result-item[data-username]');
      if (!item) {
        return;
      }
      const selectedUsername = item.getAttribute('data-username') || '';
      if (selectedUsername !== '') {
        pickStudentUsername(selectedUsername);
      }
    });

    document.addEventListener('click', function (e) {
      if (!e.target.closest('#technician-student-search') && !e.target.closest('#technician-student-search-results')) {
        hideStudentSearchResults();
      }
    });
  }

  const reserveForm = document.querySelector('form[action$="/dashboard/reserve"]');
  const selInput = reserveForm
    ? reserveForm.querySelector('input[name="sel"]')
    : document.querySelector('input[name="sel"]');
  const slotLinks = Array.from(document.querySelectorAll('.slot-link[data-slot-key]'));

  if (!selInput || slotLinks.length === 0) {
    return;
  }

  const selected = new Set();
  const initial = (selInput.value || '').trim();
  if (initial) {
    initial.split(',').forEach(function (key) {
      const v = key.trim();
      if (v) selected.add(v);
    });
  }

  function syncHiddenInput() {
    selInput.value = Array.from(selected).join(',');
  }

  function setCellClass(link, shouldSelect) {
    const td = link.closest('td');
    if (!td) return;

    if (td.classList.contains('legend') || td.classList.contains('unavailable') || td.classList.contains('disabled')) {
      return;
    }

    if (shouldSelect) {
      td.classList.remove('available');
      td.classList.add('reserved');
    } else {
      td.classList.remove('reserved');
      td.classList.add('available');
    }
  }

  // Normalize UI to selection set.
  slotLinks.forEach(function (link) {
    const key = link.dataset.slotKey || '';
    const clickable = link.dataset.clickable === '1';
    if (!clickable || key === '') return;
    setCellClass(link, selected.has(key));
  });

  slotLinks.forEach(function (link) {
    const key = link.dataset.slotKey || '';
    const clickable = link.dataset.clickable === '1';

    if (!clickable || key === '') {
      return;
    }

    link.addEventListener('click', function (e) {
      e.preventDefault();

      if (selected.has(key)) {
        selected.delete(key);
        setCellClass(link, false);
      } else {
        selected.add(key);
        setCellClass(link, true);
      }

      syncHiddenInput();
    });
  });

  if (reserveForm) {
    reserveForm.addEventListener('submit', function (e) {
      syncHiddenInput();
      if (!selInput.value.trim()) {
        e.preventDefault();
        alert('Please select at least one seat and time slot.');
      }
    });
  }
});
