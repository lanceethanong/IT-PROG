document.addEventListener('DOMContentLoaded', () => {
  const form = document.getElementById('login-form');
  const emailInput = document.getElementById('email');
  const emailError = document.getElementById('emailError'); //If there is an error displays message

  // Checks user input 
  form.addEventListener('submit', function (event) {
    emailError.textContent = '';

    const email = emailInput.value.trim();

    if (!/@dlsu\.edu\.ph\s*$/.test(email)) { 
      event.preventDefault();
      emailError.textContent = 'Please use your DLSU email address.';
    }
  });
});
