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

async function del(id) {
  if (!id) {
    alert("No reservation ID provided.");
    return;
  }

  if (!confirm("Are you sure you want to delete this reservation? This cannot be undone.")) {
    return;
  }

  try {
    const response = await fetch("/api/reservation/" + id, {
      method: 'DELETE',
      headers: {
        'Content-Type': 'application/json'
      }
    });

    if (response.ok) {
      const box = document.querySelector(`.reservation-box button.delete-btn[onclick="del('${id}')"]`)?.closest('.reservation-box');
      if (box) {
        box.parentNode.removeChild(box);
      } else {
        location.reload();
      }
      alert("Reservation deleted successfully.");
    } else {
      let msg = "Failed to delete reservation.";
      try {
        const err = await response.json();
        msg = err.error || msg;
      } catch (e) {
        logError(e, 'del(id)');
      }
      alert(msg);
      console.error('Failed to delete:', response.status, msg);
    }
  } catch (error) {
    alert("Error deleting reservation.");
    console.error('Error:', error);
    logError(error, 'del(id)');
  }
}

async function openEditModal(reservation) {   
  try {     
    // Check if reservation is in past or present     
    const isPastOrPresent = isReservationInPastOrPresent(reservation);          
    
    // Fetch labs data     
    const labsResponse = await fetch('/api/labs');     
    if (!labsResponse.ok) throw new Error('Failed to fetch labs');     
    const labs = await labsResponse.json();      
    
    // Get the modal and form elements     
    const modal = document.getElementById('edit-modal');     
    const form = document.getElementById('edit-reservation-form');     
    const labSelect = document.getElementById('edit-lab');     
    const dateInput = document.getElementById('edit-date');     
    const timeStartSelect = document.getElementById('edit-time-start');     
    const timeEndSelect = document.getElementById('edit-time-end');          
    
    // Clear existing options     
    labSelect.innerHTML = '';          
    
    // Add new options     
    labs.forEach(lab => {       
      const option = document.createElement('option');       
      option.value = lab._id;       
      option.textContent = `Lab ${lab.number} (${lab.class})`;       
      labSelect.appendChild(option);     
    });      
    
    // Find the matching lab for the current reservation     
    const labName = reservation.lab || '';     
    const labMatch = labs.find(l => `Lab ${l.number} (${l.class})` === labName);          
    
    // Set form values     
    modal.style.display = 'block';     
    document.getElementById('edit-id').value = reservation._id;          
    
    // FIX: Properly format date for HTML date input to avoid timezone issues
    if (reservation.date) {
      const date = new Date(reservation.date);
      const year = date.getFullYear();
      const month = String(date.getMonth() + 1).padStart(2, '0');
      const day = String(date.getDate()).padStart(2, '0');
      dateInput.value = `${year}-${month}-${day}`;
    }
    
    timeStartSelect.value = reservation.time_start;     
    timeEndSelect.value = reservation.time_end;     
    document.getElementById('edit-lab').value = labMatch ? labMatch._id : '';     
    document.getElementById('edit-row').value = reservation.row;     
    document.getElementById('edit-column').value = reservation.column;          
    
    // Disable all fields if reservation is in past or present     
    if (isPastOrPresent) {       
      dateInput.disabled = true;       
      timeStartSelect.disabled = true;       
      timeEndSelect.disabled = true;       
      labSelect.disabled = true;       
      document.getElementById('edit-row').disabled = true;       
      document.getElementById('edit-column').disabled = true;              
      
      // Visual indication       
      const inputs = form.querySelectorAll('input, select');       
      inputs.forEach(input => {         
        input.style.backgroundColor = '#f5f5f5';         
        input.style.cursor = 'not-allowed';       
      });              
      
      // Change the submit button text       
      const submitBtn = form.querySelector('button[type="submit"]');       
      if (submitBtn) {         
        submitBtn.textContent = 'Reservation Cannot Be Edited';         
        submitBtn.disabled = true;         
        submitBtn.style.backgroundColor = '#cccccc';       
      }     
    }        
  } catch (error) {     
    console.error('Error opening edit modal:', error);     
    alert('Failed to load lab information');   
    logError(error, 'openEditModal()');
  } 
}

function closeEditModal() {
  document.getElementById('edit-modal').style.display = 'none';
}

async function edit(id) {
  if (!id) return alert("No reservation ID provided.");

  try {
    const response = await fetch("/api/reservations/" + id);
    if (!response.ok) return alert("Failed to fetch reservation.");
    const reservation = await response.json();
    
    // Check if reservation is in past or present
    if (isReservationInPastOrPresent(reservation)) {
      return alert("You cannot edit reservations that have already started or passed.");
    }
    
    openEditModal(reservation);
  } catch (error) {
    console.error('Error:', error);
    alert("Error loading reservation for editing.");
    logError(error, 'edit(id)');
  }
}




// Helper function to convert to 24-hour format
function timeTo24(timeStr) {
  const [time, period] = timeStr.split(' ');
  const [hours] = time.split(':').map(Number);
  let hours24 = hours;
  if (period === 'PM' && hours !== 12) hours24 += 12;
  if (period === 'AM' && hours === 12) hours24 = 0;
  return hours24;
}

// Helper function to prepare date for server (Manila time)
function prepareDateForServer(dateStr) {
 
  if (typeof dateStr === 'string' && /^\d{4}-\d{2}-\d{2}$/.test(dateStr)) {
    const [year, month, day] = dateStr.split('-').map(Number);
    return new Date(year, month - 1, day);
  }
  return new Date(dateStr);
}

async function checkIfCurrentReservation(reservationId, labId, row, column, date, time_start, time_end) {
  try {
    const response = await fetch(`/api/reservations/${reservationId}`);
    if (!response.ok) {
      throw new Error(`Failed to fetch reservation: ${response.status}`);
    }
    
    const reservation = await response.json();
    
    // Compare dates in Manila time
    const existingDate = new Date(reservation.date);
    const newDate = prepareDateForServer(date);
    
    // Compare ISO strings (date portion only)
    const existingDateStr = existingDate.toISOString().split('T')[0];
    const newDateStr = newDate.toISOString().split('T')[0];
    
    // Check if this is the same reservation we're editing
    const isSameReservation = (
      (reservation.lab?._id === labId || reservation.lab?.toString() === labId) &&
      reservation.row == row &&
      reservation.column == column &&
      existingDateStr === newDateStr &&
      reservation.time_start === time_start &&
      reservation.time_end === time_end
    );
    
    return isSameReservation;
    
  } catch (error) {
    console.error('Error checking current reservation:', error);
    logError(error, 'checkIfCurrentReservation(reservationId, labId, row, column, date, time_start, time_end)');
    return false;
  }
}

function isReservationInPastOrPresent(reservation) {
  if (!reservation.date || !reservation.time_start) return false;
  
  // Get current date and time in Manila time (UTC+8)
  const now = new Date();
  const manilaOffset = 8 * 60 * 60 * 1000; // UTC+8 in milliseconds
  const manilaNow = new Date(now.getTime() + manilaOffset);
  
  // Parse the reservation date and time
  const reservationDate = new Date(reservation.date);
  
  // Parse the time_start (e.g., "8:00 AM")
  const [time, period] = reservation.time_start.split(' ');
  let [hours, minutes] = time.split(':').map(Number);
  
  // Convert to 24-hour format
  if (period === 'PM' && hours !== 12) hours += 12;
  if (period === 'AM' && hours === 12) hours = 0;
  
  // Set the hours and minutes on the reservation date
  reservationDate.setHours(hours, minutes, 0, 0);
  
  // Compare with current Manila time
  return reservationDate <= manilaNow;
}

document.getElementById('edit-reservation-form').addEventListener('submit', async function (e) {
  e.preventDefault();
  
  const formData = {
    id: document.getElementById('edit-id').value,
    date: document.getElementById('edit-date').value,
    time_start: document.getElementById('edit-time-start').value,
    time_end: document.getElementById('edit-time-end').value,
    lab: document.getElementById('edit-lab').value,
    row: parseInt(document.getElementById('edit-row').value),
    column: parseInt(document.getElementById('edit-column').value)
  };

  // First check if the reservation is in the past
  try {
    const response = await fetch("/api/reservations/" + formData.id);
    if (!response.ok) throw new Error("Failed to fetch reservation data");
    const originalReservation = await response.json();
    
    if (isReservationInPastOrPresent(originalReservation)) {
      return alert("Cannot modify reservation - it has already started or passed.");
    }
  } catch (error) {
    console.error('Error checking reservation status:', error);
    logError(error, 'Form submission (reservation.js)');
    return alert("Error verifying reservation status.");
  }

  // Validate time duration
  const startHour = timeTo24(formData.time_start);
  const endHour = timeTo24(formData.time_end);
  if (endHour <= startHour) {
    alert("End time must be after start time");
    return;
  }

  try {
    // First check for availability
    const labResponse = await fetch(`/api/labs/${formData.lab}`);
    const lab = await labResponse.json();
    
    const availabilityResponse = await fetch(
      `/api/labs/${lab.number}/check-availability?date=${formData.date}&time_start=${formData.time_start}&time_end=${formData.time_end}`
    );
    const availability = await availabilityResponse.json();
    
    // Check if the seat is available (not occupied by others)
    const isSeatAvailable = availability.availableSeats.some(seat => 
      seat.row == formData.row && seat.column == formData.column
    );
    
    // Also check if this is our own existing reservation
    const isCurrentReservation = await checkIfCurrentReservation(
      formData.id,
      formData.lab,
      formData.row,
      formData.column,
      formData.date,
      formData.time_start,
      formData.time_end
    );
    
    if (!isSeatAvailable && !isCurrentReservation) {
      alert("This seat is already reserved for the selected time slot. Please choose another seat or time.");
      return;
    }

    const serverDate = prepareDateForServer(formData.date);

    const year = serverDate.getFullYear();
    const month = String(serverDate.getMonth() + 1).padStart(2, '0');
    const day = String(serverDate.getDate()).padStart(2, '0');
    formData.date = `${year}-${month}-${day}`;

    const res = await fetch("/api/reservations/" + formData.id, {
      method: 'PUT',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(formData)
    });
    
    if (!res.ok) {
      const err = await res.json();
      throw new Error(err.error || "Failed to update reservation.");
    }
    
    alert("Reservation updated successfully.");
    closeEditModal();
    location.reload();
  } catch (error) {
    console.error('Update error:', error);
    logError(error, 'Form submission (reservation.js)');
    alert(error.message);
  }
});
//Global functions
window.edit = edit;
window.closeEditModal = closeEditModal;
window.del = del;