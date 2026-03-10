// Checks for the user input
document.addEventListener('DOMContentLoaded', () => {
  const form = document.getElementById('register-form'); //views the form data

  form.addEventListener('submit', function (event) { // Sets the fields
    const password = document.getElementById('password');
    const confirm = document.getElementById('confirmPassword');
    const password_error = document.getElementById('confirm-password-error');
    const email_input = document.getElementById('email');
    const email_error = document.getElementById('emailError');
    const role = document.getElementById('role'); 

    let isValid = true;
    //blank by default
    email_error.innerHTML = ""; 
    password_error.innerHTML = "";

    //makes sure a DLSU address is used 
    if (!/@dlsu.edu.ph\s*$/.test(email_input.value)) {
      email_error.innerHTML = "Please enter a DLSU email address";
      isValid = false;
    }
    // Passwords must match
    if (password.value !== confirm.value) {
      password_error.innerHTML = "Passwords do not match.";
      isValid = false;
    }
    // At least 8 characters
    if (password.value.length < 8) {
      password_error.innerHTML = "Passwords must be at least 8 characters";
      isValid = false;
    }
    
    if (!isValid) {
      event.preventDefault();
    }
  });
});
