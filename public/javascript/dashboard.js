document.addEventListener('DOMContentLoaded', function () {
  if (window.__ezlabsDashboardInit === true) {
    return;
  }
  window.__ezlabsDashboardInit = true;

  const menuToggle = document.querySelector('.menu-toggle');
  const sidebar = document.getElementById('sidebar');
  if (menuToggle && sidebar) {
    menuToggle.addEventListener('click', function () {
      sidebar.classList.toggle('collapsed');
    });
  }

  const searchInput = document.querySelector('.search-input[name="q"]');
  const searchResults = document.getElementById('search-results');
  const currentUsername = (document.body && document.body.dataset && document.body.dataset.username) ? document.body.dataset.username : '';
  const searchDataNode = document.getElementById('search-users-json');

  function getBasePath() {
    const marker = '/dashboard';
    const path = window.location.pathname || '';
    const idx = path.indexOf(marker);
    return idx >= 0 ? path.slice(0, idx) : '';
  }

  if (searchInput && searchResults) {
    let allUsers = [];
    if (searchDataNode && searchDataNode.textContent) {
      try {
        const parsed = JSON.parse(searchDataNode.textContent);
        if (Array.isArray(parsed)) {
          allUsers = parsed;
        }
      } catch (err) {
        allUsers = [];
      }
    }

    function hideSearchResultsIfEmpty() {
      if (searchResults.children.length === 0) {
        searchResults.innerHTML = '';
      }
      searchResults.classList.remove('show');
    }

    function escapeHtml(value) {
      return String(value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
    }

    function renderSearchResults(users, q) {
      if (!q) {
        searchResults.innerHTML = '';
        searchResults.classList.remove('show');
        return;
      }

      if (!Array.isArray(users) || users.length === 0) {
        searchResults.innerHTML = '<div class="search-result-item">No users found</div>';
        searchResults.classList.add('show');
        return;
      }

      const html = users.map(function (u) {
        const username = String(u.username || '');
        const email = String(u.email || '');
        const role = String(u.role || '');
        const href = getBasePath() + '/dashboard/view-profile/' + encodeURIComponent(username) + '?username=' + encodeURIComponent(currentUsername);
        return ''
          + '<a class="search-result-item" style="text-decoration:none; color:inherit;" href="' + href + '">'
          + '<img src="/public/assets/profile.png" alt="' + escapeHtml(username) + '" class="search-result-pic" />'
          + '<div class="search-result-info">'
          + '<strong>' + escapeHtml(username) + '</strong>'
          + '<small>' + escapeHtml(email) + '</small>'
          + '<div class="user-role-badge">' + escapeHtml(role) + '</div>'
          + '</div>'
          + '</a>';
      }).join('');

      searchResults.innerHTML = html;
      searchResults.classList.add('show');
    }

    searchInput.addEventListener('focus', function () {
      if (searchResults.children.length > 0) {
        searchResults.classList.add('show');
      }
    });

    searchInput.addEventListener('input', function () {
      const q = searchInput.value.trim().toLowerCase();
      if (q.length < 1) {
        renderSearchResults([], '');
        return;
      }

      const matches = allUsers.filter(function (u) {
        const username = String(u.username || '').toLowerCase();
        const email = String(u.email || '').toLowerCase();
        return username.indexOf(q) !== -1 || email.indexOf(q) !== -1;
      }).slice(0, 12);

      renderSearchResults(matches, q);
    });

    document.addEventListener('click', function (e) {
      if (!e.target.closest('.search-wrapper')) {
        hideSearchResultsIfEmpty();
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
