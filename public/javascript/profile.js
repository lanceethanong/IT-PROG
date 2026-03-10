// Log errors to the database (only in JS files containing try-catch blocks)
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

  //Gets the user to be displayed
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

// Search option to find users
async function searchUsers(query) {
  const resultsContainer = document.getElementById('search-results');
  
  if (!query || query.length < 2) { // If the query is either blank or has only 1 character show nothing
    resultsContainer.innerHTML = '';
    resultsContainer.classList.remove('show');
    return;
  }

  clearTimeout(searchTimeout);
  
  searchTimeout = setTimeout(async () => {  // If no user is found
    try {
      const response = await fetch(`/api/users/search/${encodeURIComponent(query)}`); //finds users 
      const users = await response.json(); //checks mongodb
      
      if (users.length === 0) { //If no users match 
        resultsContainer.innerHTML = '<div class="search-result-item">No users found</div>';
        resultsContainer.classList.add('show');
        return;
      }

      // If user/users are found displays a preview that contains user name picture and email along with their role 
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
      
      resultsContainer.classList.add('show'); //shows all 
      
    } catch (error) { //if the search query fails
      console.error('Search failed:', error);
      resultsContainer.innerHTML = '<div class="search-result-item">Error loading results</div>';
      resultsContainer.classList.add('show');
      logError(error, 'searchUsers(query): searchTimeout');
    }
  }, 300);
}

function showDeleteModal() {
  document.getElementById('delete-modal').style.display = 'block';
}

function hideDeleteModal() {
  document.getElementById('delete-modal').style.display = 'none';
}

// If a user wants to delete their account
async function confirmDeleteAccount() {
  const username = getUsername(); //gets the users username
  if (!username) {
    alert('Could not determine your username');
    return;
  }

  // gets the users data
  try {
    const response = await fetch(`/api/users/${encodeURIComponent(username)}`, { //fetches user data
      method: 'DELETE', //deletes user data 
      headers: {
        'Content-Type': 'application/json', //storedi n json
      }
    });

    if (response.ok) { //If the user has successfully been deleted from the database
      window.location.href = '/login?accountDeleted=true'; //redirects them back to the login screen
    } else {
      const errorData = await response.json();
      alert(`Failed to delete account: ${errorData.error || 'Unknown error'}`);
    }
  } catch (error) { //Catches any errors 
    console.error('Error deleting account:', error);
    alert('An error occurred while trying to delete your account. Please try again.');
    logError(error, 'confirmDeleteAccount()');
  } finally {
    hideDeleteModal(); //removes the delete modal
  }
}
//function that enables users to edit their profile
function enableEditMode() { 
  const aboutText = document.getElementById('about-text');
  aboutText.readOnly = false; // So that the fields can be edited
  aboutText.focus();
  
  const editButtons = document.getElementById('edit-buttons'); // Shows the edit buttons
  editButtons.innerHTML = `
    <button onclick="saveProfileChanges()" class="save-btn">Save Changes</button>
    <button onclick="cancelEditMode()" class="cancel-btn">Cancel</button>
  `;
}
//Cancels edit mode
function cancelEditMode() {
  const aboutText = document.getElementById('about-text');
  aboutText.readOnly = true;
  
  const editButtons = document.getElementById('edit-buttons');
  editButtons.innerHTML = `<button onclick="enableEditMode()" class="edit-btn">Edit Profile</button>`; // used to renable edit mode
}


//Saves changes to a user profile
async function saveProfileChanges() {
  const username = getUsername();
  if (!username) {
    alert('Could not determine your username');
    return;
  }

  const description = document.getElementById('about-text').value;

  //Saves changes to database(put is used to assign values to certain fields)
  try {
    const response = await fetch(`/api/users/${encodeURIComponent(username)}`, {
      method: 'PUT',
      headers: {
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({ description })
    });

    //If changes are successful,
    if (response.ok) {
      cancelEditMode();
      alert('Profile updated successfully!');
    } 
    //unsuccessful changes
    else {
      const errorData = await response.json();
      alert(`Failed to update profile: ${errorData.error || 'Unknown error'}`);
    }
  } catch (error) {
    console.error('Error updating profile:', error);
    alert('An error occurred while updating your profile. Please try again.');
    logError(error, 'saveProfileChanges()');
  }
}

//Opens the change password block
function openPasswordModal() {
  if (!document.getElementById('password-modal')) {
    createPasswordModal();
  }
  document.getElementById('password-modal').style.display = 'block';
}

function closePasswordModal() {
  document.getElementById('password-modal').style.display = 'none';
}

//Password change box
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
            <span class="toggle-password" onclick="togglePasswordVisibility('current-password')">👁️</span>
          </div>
        </div>
        <div class="form-group">
          <label for="new-password">New Password</label>
          <div class="password-input">
            <input type="password" id="new-password" required minlength="8">
            <span class="toggle-password" onclick="togglePasswordVisibility('new-password')">👁️</span>
          </div>
          <small class="password-hint">Minimum 8 characters</small>
        </div>
        <div class="form-group">
          <label for="confirm-password">Confirm New Password</label>
          <div class="password-input">
            <input type="password" id="confirm-password" required minlength="8">
            <span class="toggle-password" onclick="togglePasswordVisibility('confirm-password')">👁️</span>
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
  // Gets the input
  document.getElementById('password-form').addEventListener('submit', async function(e) {
    e.preventDefault();
    await changePassword(); //waits for what the user inputs
  });
}

//modal to change theri password
async function changePassword() {
  const username = getUsername();
  if (!username) {
    alert('Could not determine your username');
    return;
  }
  //checks password fields
  const currentPassword = document.getElementById('current-password').value; 
  const newPassword = document.getElementById('new-password').value;
  const confirmPassword = document.getElementById('confirm-password').value;

  //If not same passwords
  if (newPassword !== confirmPassword) {
    alert('New passwords do not match!');
    return;
  }
  // at least 8 characters
  if (newPassword.length < 8) {
    alert('Password must be at least 8 characters long');
    return;
  }
  //Fills in the request to change password
  try {
    const response = await fetch(`/api/users/${encodeURIComponent(username)}/change-password`, {
      method: 'POST',// posts because it would submit the changes 
      headers: {
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({ currentPassword, newPassword }) //changes the currentpassword to the new password
    });
    //successful
    if (response.ok) {
      alert('Password changed successfully!');
      closePasswordModal();
    } 
    //unsuccessful
    else {
      const errorData = await response.json();
      alert(`Failed to change password: ${errorData.error || 'Unknown error'}`);
    }
  } catch (error) {
    console.error('Error changing password:', error);
    alert('An error occurred while changing your password. Please try again.');
    logError(error, 'changePassword()');
  }
}
// toggles the password inputted
function togglePasswordVisibility(inputId) {
  const input = document.getElementById(inputId);
  input.type = input.type === 'password' ? 'text' : 'password';
}

window.onclick = function(event) {
  const deleteModal = document.getElementById('delete-modal');
  if (event.target === deleteModal) {
    hideDeleteModal();
  }
  
  const passwordModal = document.getElementById('password-modal');
  if (passwordModal && event.target === passwordModal) {
    closePasswordModal();
  }
};
//event listeners
document.addEventListener('DOMContentLoaded', function() {
  const editButtons = document.getElementById('edit-buttons');
  const searchInput = document.getElementById('user-search-input');

  const changePassBtn = document.querySelector('.account-actions button:first-of-type');
  const deleteAccountBtn = document.querySelector('.account-actions button:last-of-type');
  
  if (changePassBtn) changePassBtn.onclick = openPasswordModal;
  if (deleteAccountBtn) deleteAccountBtn.onclick = showDeleteModal;
});
//enables global modals
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
