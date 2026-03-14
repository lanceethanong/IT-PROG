// Log errors to backend
function logError(error, source) {
  fetch('/api/log-error', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
      message: error.message || String(error),
      stack: error.stack || null,
      source: source || 'Unknown',
    })
  }).catch(console.warn);
}

function getUsername() {
  const usernameElement = document.querySelector('.profile-info h2');
  if (usernameElement) {
    return usernameElement.textContent.trim();
  }

  const urlParams = new URLSearchParams(window.location.search);
  const usernameParam = urlParams.get('username');
  if (usernameParam) {
    return usernameParam;
  }

  console.error('Could not determine username from DOM or URL');
  return null;
}

let searchTimeout;
async function searchUsers(query) {
  const resultsContainer = document.getElementById('search-results');
  if (!resultsContainer) return;

  if (!query || query.length < 2) {
    resultsContainer.innerHTML = '';
    resultsContainer.classList.remove('show');
    return;
  }

  clearTimeout(searchTimeout);
  searchTimeout = setTimeout(async () => {
    try {
      const response = await fetch(`/api/users/search/${encodeURIComponent(query)}`);
      const users = await response.json();

      if (users.length === 0) {
        resultsContainer.innerHTML = '<div class="search-result-item">No users found</div>';
        resultsContainer.classList.add('show');
        return;
      }

      resultsContainer.innerHTML = users.map(user => `
        <div class="search-result-item" data-username="${user.username}">
          <img src="/assets/profile.png" alt="${user.username}" class="search-result-pic">
          <div class="search-result-info">
            <strong>${user.username}</strong>
            <small>${user.email}</small>
            <div class="user-role-badge">${user.role}</div>
          </div>
        </div>
      `).join('');

      resultsContainer.classList.add('show');
    } catch (error) {
      console.error('Search failed:', error);
      resultsContainer.innerHTML = '<div class="search-result-item">Error loading results</div>';
      resultsContainer.classList.add('show');
      logError(error, 'searchUsers(query)');
    }
  }, 300);
}

function showDeleteModal() {
  const modal = document.getElementById('delete-modal');
  if (modal) modal.style.display = 'block';
}

function hideDeleteModal() {
  const modal = document.getElementById('delete-modal');
  if (modal) modal.style.display = 'none';
}

async function confirmDeleteAccount() {
  const username = getUsername();
  if (!username) {
    alert('Could not determine your username');
    return;
  }

  try {
    const response = await fetch(`/api/users/${encodeURIComponent(username)}`, {
      method: 'DELETE',
      headers: {
        'Content-Type': 'application/json',
      }
    });

    if (response.ok) {
      window.location.href = appUrl('/login?accountDeleted=true');
    } else {
      const errorData = await response.json();
      alert(`Failed to delete account: ${errorData.error || 'Unknown error'}`);
    }
  } catch (error) {
    console.error('Error deleting account:', error);
    alert('An error occurred while trying to delete your account. Please try again.');
    logError(error, 'confirmDeleteAccount()');
  } finally {
    hideDeleteModal();
  }
}

function enableEditMode() {
  const aboutText = document.getElementById('about-text');
  if (!aboutText) return;

  aboutText.readOnly = false;
  aboutText.focus();

  const editButtons = document.getElementById('edit-buttons');
  if (editButtons) {
    editButtons.innerHTML = `
      <button onclick="saveProfileChanges()" class="save-btn">Save Changes</button>
      <button onclick="cancelEditMode()" class="cancel-btn">Cancel</button>
    `;
  }
}

function cancelEditMode() {
  const aboutText = document.getElementById('about-text');
  if (!aboutText) return;

  aboutText.readOnly = true;

  const editButtons = document.getElementById('edit-buttons');
  if (editButtons) {
    editButtons.innerHTML = `<button onclick="enableEditMode()" class="edit-btn">Edit Profile</button>`;
  }
}

async function saveProfileChanges() {
  const username = getUsername();
  if (!username) {
    alert('Could not determine your username');
    return;
  }

  const aboutText = document.getElementById('about-text');
  if (!aboutText) return;

  const description = aboutText.value;

  try {
    const response = await fetch(`/api/users/${encodeURIComponent(username)}`, {
      method: 'PUT',
      headers: {
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({ description })
    });

    if (response.ok) {
      cancelEditMode();
      alert('Profile updated successfully!');
    } else {
      const errorData = await response.json();
      alert(`Failed to update profile: ${errorData.error || 'Unknown error'}`);
    }
  } catch (error) {
    console.error('Error updating profile:', error);
    alert('An error occurred while updating your profile. Please try again.');
    logError(error, 'saveProfileChanges()');
  }
}

function openPasswordModal() {
  if (!document.getElementById('password-modal')) {
    createPasswordModal();
  }
  const modal = document.getElementById('password-modal');
  if (modal) modal.style.display = 'block';
}

function closePasswordModal() {
  const modal = document.getElementById('password-modal');
  if (modal) modal.style.display = 'none';
}

function createPasswordModal() {
  const modal = document.createElement('div');
  modal.id = 'password-modal';
  modal.className = 'modal';

  modal.innerHTML = `
    <div class="modal-content">
      <span class="close" onclick="closePasswordModal()">&times;</span>
      <h2>Change Password</h2>
      <form id="password-form">
        <div class="form-group">
          <label for="current-password">Current Password</label>
          <div class="password-input">
            <input type="password" id="current-password" required>
            <span class="toggle-password" onclick="togglePasswordVisibility('current-password')">Show</span>
          </div>
        </div>
        <div class="form-group">
          <label for="new-password">New Password</label>
          <div class="password-input">
            <input type="password" id="new-password" required minlength="8">
            <span class="toggle-password" onclick="togglePasswordVisibility('new-password')">Show</span>
          </div>
          <small class="password-hint">Minimum 8 characters</small>
        </div>
        <div class="form-group">
          <label for="confirm-password">Confirm New Password</label>
          <div class="password-input">
            <input type="password" id="confirm-password" required minlength="8">
            <span class="toggle-password" onclick="togglePasswordVisibility('confirm-password')">Show</span>
          </div>
        </div>
        <div class="form-actions">
          <button type="button" onclick="closePasswordModal()" class="cancel-btn">Cancel</button>
          <button type="submit" class="submit-btn">Change Password</button>
        </div>
      </form>
    </div>
  `;

  document.body.appendChild(modal);
  const form = document.getElementById('password-form');
  if (form) {
    form.addEventListener('submit', async function(e) {
      e.preventDefault();
      await changePassword();
    });
  }
}

async function changePassword() {
  const username = getUsername();
  if (!username) {
    alert('Could not determine your username');
    return;
  }

  const currentPassword = document.getElementById('current-password')?.value || '';
  const newPassword = document.getElementById('new-password')?.value || '';
  const confirmPassword = document.getElementById('confirm-password')?.value || '';

  if (newPassword !== confirmPassword) {
    alert('New passwords do not match!');
    return;
  }

  if (newPassword.length < 8) {
    alert('Password must be at least 8 characters long');
    return;
  }

  try {
    const response = await fetch(`/api/users/${encodeURIComponent(username)}/change-password`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({ currentPassword, newPassword })
    });

    if (response.ok) {
      alert('Password changed successfully!');
      closePasswordModal();
    } else {
      const errorData = await response.json();
      alert(`Failed to change password: ${errorData.error || 'Unknown error'}`);
    }
  } catch (error) {
    console.error('Error changing password:', error);
    alert('An error occurred while changing your password. Please try again.');
    logError(error, 'changePassword()');
  }
}

function togglePasswordVisibility(inputId) {
  const input = document.getElementById(inputId);
  if (!input) return;
  input.type = input.type === 'password' ? 'text' : 'password';
}

window.onclick = function(event) {
  const deleteModal = document.getElementById('delete-modal');
  if (deleteModal && event.target === deleteModal) {
    hideDeleteModal();
  }

  const passwordModal = document.getElementById('password-modal');
  if (passwordModal && event.target === passwordModal) {
    closePasswordModal();
  }
};

document.addEventListener('DOMContentLoaded', function() {
  const changePassBtn = document.querySelector('.account-actions button:first-of-type');
  const deleteAccountBtn = document.querySelector('.account-actions button:last-of-type');

  if (changePassBtn) changePassBtn.onclick = openPasswordModal;
  if (deleteAccountBtn) deleteAccountBtn.onclick = showDeleteModal;
});

window.showDeleteModal = showDeleteModal;
window.hideDeleteModal = hideDeleteModal;
window.enableEditMode = enableEditMode;
window.cancelEditMode = cancelEditMode;
window.saveProfileChanges = saveProfileChanges;
window.confirmDeleteAccount = confirmDeleteAccount;
window.openPasswordModal = openPasswordModal;
window.closePasswordModal = closePasswordModal;
window.changePassword = changePassword;
window.togglePasswordVisibility = togglePasswordVisibility;
