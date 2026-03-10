// vairables
let selectedRoom = null;
let selectedLabNumber = null;
let selectedDate = null;
let seatSelections = {};
let currentMonth = new Date(); //dynamically updates to current day 
let rooms = [];
let searchTimeout;
let currentReservationList = [];

// Technician Search for Student
let technicianSelectedStudent = null;
let technicianStudentVerifyError = null;

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

// just for proper url format
function getURLParams() {
  const pathMatch = window.location.pathname.match(/\/dashboard\/(student|technician)(?:\/lab\/(\d+))?/);
  const usernameParam = new URLSearchParams(window.location.search).get('username');
  return {
    role: pathMatch ? pathMatch[1] : 'student',
    labNumber: pathMatch && pathMatch[2] ? parseInt(pathMatch[2], 10) : null,
    username: usernameParam || ''
  };
}

// Get reservation ID from URL to edit
function getReservationID() {
  const match = window.location.pathname.match(/\/edit\/([^\/?#]+)/);
  return match ? match[1] : null;
}

// Search and select a student to reserve for
async function technicianStudentSearch(query) {
  const resultsContainer = document.getElementById('technician-search-results');
  if (!query || query.length < 2) { // Basically if theres no matches 
    resultsContainer.innerHTML = '';
    resultsContainer.classList.remove('show');
    return;
  }
  clearTimeout(searchTimeout); //error
  searchTimeout = setTimeout(async () => {
    try {
      const response = await fetch(`/api/users/search/${encodeURIComponent(query)}`); 
      const users = await response.json();
      const studentUsers = users.filter(user => user.role === "Student"); // filters search results to only look for students 
      if (studentUsers.length === 0) { //no results found 
        resultsContainer.innerHTML = '<div class="search-result-item">No students found</div>';
        resultsContainer.classList.add('show');
        return;
      }
      //Students the match the search parameters 
      resultsContainer.innerHTML = studentUsers.map(user => `
        <div class="search-result-item" data-userid="${user._id}" onclick="selectTechnicianStudent('${user._id}', '${user.username}', '${user.email}')">
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
      console.error('Student search failed:', error);
      resultsContainer.innerHTML = '<div class="search-result-item">Error loading results</div>';
      resultsContainer.classList.add('show');
      logError(error, 'technicianStudentSearch(query): searchTimeout');
    }
  }, 300);
}

// Student search for reservation(helper function)
let techStudentSelectedIndex = -1;
function technicianSearchStudentsForReservation(query) {
  const resultsContainer = document.getElementById('technician-student-search-results'); //Shows all reservations 
  techStudentSelectedIndex = -1;

  if (!query || query.length < 2) {
    resultsContainer.innerHTML = '';
    resultsContainer.classList.remove('show');
    return;
  }

  clearTimeout(window.technicianStudentSearchTimeout);

  window.technicianStudentSearchTimeout = setTimeout(async () => {
    try {
      const response = await fetch(`/api/users/search/${encodeURIComponent(query)}`);
      const users = await response.json();
      const studentUsers = users.filter(user => user.role === "Student");
      if (studentUsers.length === 0) {
        resultsContainer.innerHTML = '<div class="search-result-item">No students found</div>';
        resultsContainer.classList.add('show');
        return;
      }
      resultsContainer.innerHTML = studentUsers.map((user, idx) => `
        <div class="search-result-item" data-userid="${user._id}" tabindex="0" data-index="${idx}">
          <img src="/assets/profile.png" alt="${user.username}" class="search-result-pic">
          <div class="search-result-info">
            <strong>${user.username}</strong>
            <small>${user.email}</small>
            <div class="user-role-badge">${user.role}</div>
          </div>
        </div>
      `).join('');
      Array.from(resultsContainer.querySelectorAll('.search-result-item')).forEach((div, idx) => {
        div.onclick = () => {
          selectTechnicianStudent(
            studentUsers[idx]._id,
            studentUsers[idx].username,
            studentUsers[idx].email
          );
          resultsContainer.innerHTML = '';
          resultsContainer.classList.remove('show');
        };
        div.onmouseenter = () => setActiveTechStudentResult(idx);
        div.onmouseleave = () => setActiveTechStudentResult(-1);
      });
      resultsContainer.classList.add('show');
    } catch (error) {
      console.error('Technician student search failed:', error);
      resultsContainer.innerHTML = '<div class="search-result-item">Error loading results</div>';
      resultsContainer.classList.add('show');
      logError(error, 'window.technicianStudentSearchTimeout');
    }
  }, 300);
}

//Gets the student selected and makes it the active user(helper function)
function setActiveTechStudentResult(idx) {
  const resultsContainer = document.getElementById('technician-student-search-results');
  const items = Array.from(resultsContainer.querySelectorAll('.search-result-item'));
  items.forEach((item, i) => {
    if (i === idx) {
      item.classList.add('active');
      item.scrollIntoView({block: 'nearest'});
      techStudentSelectedIndex = idx;
    } else {
      item.classList.remove('active');
    }
  });
}

//Dropdown tab for better and smoother selection process(helper function)
function technicianStudentDropdownKeyHandler(e) {
  const resultsContainer = document.getElementById('technician-student-search-results');
  if (!resultsContainer.classList.contains('show')) return;
  const items = Array.from(resultsContainer.querySelectorAll('.search-result-item'));

  //Onclick commands 
  if (e.key === 'ArrowDown') {
    e.preventDefault();
    let next = (techStudentSelectedIndex + 1) % items.length;
    setActiveTechStudentResult(next);
  } else if (e.key === 'ArrowUp') {
    e.preventDefault();
    let prev = (techStudentSelectedIndex - 1 + items.length) % items.length;
    setActiveTechStudentResult(prev);
  } else if (e.key === 'Enter') {
    if (techStudentSelectedIndex >= 0 && techStudentSelectedIndex < items.length) {
      items[techStudentSelectedIndex].click();
    }
  } else if (e.key === 'Escape') {
    resultsContainer.classList.remove('show');
  }
}

//Selects the student(helper function)
function selectTechnicianStudent(studentId, username, email) {
  technicianSelectedStudent = { _id: studentId, username, email };
  const input = document.getElementById("technician-student-search");
  input.value = username + ' (' + email + ')';
  document.getElementById('technician-student-search-results').innerHTML = '';
  document.getElementById('technician-student-search-results').classList.remove('show');
  document.getElementById('technician-student-selected').textContent = `Selected Student: ${username} (${email})`;
  technicianStudentVerifyError = null;
}

//resets the student(helper function)
function clearTechnicianStudent() {
  technicianSelectedStudent = null;
  const input = document.getElementById("technician-student-search");
  input.value = "";
  document.getElementById('technician-student-search-results').innerHTML = '';
  document.getElementById('technician-student-selected').textContent = '';
}

// Search for users(Top search bar(not for reservation))
let searchSelectedIndex = -1;
async function searchUsers(query) {
  const resultsContainer = document.getElementById('search-results');
  searchSelectedIndex = -1;

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
      resultsContainer.innerHTML = users.map((user, idx) => `
        <div class="search-result-item" data-username="${user.username}" tabindex="0" data-index="${idx}" onclick="viewUserProfile('${user.username}')">
          <img src="/assets/profile.png" alt="${user.username}" class="search-result-pic">
          <div class="search-result-info">
            <strong>${user.username}</strong>
            <small>${user.email}</small>
            <div class="user-role-badge">${user.role}</div>
          </div>
        </div>
      `).join('');
      resultsContainer.classList.add('show');
      Array.from(resultsContainer.querySelectorAll('.search-result-item')).forEach((div, idx) => {
        div.onmouseenter = () => setActiveSearchResult(idx);
        div.onmouseleave = () => setActiveSearchResult(-1);
      });

    } catch (error) {
      console.error('Search failed:', error);
      resultsContainer.innerHTML = '<div class="search-result-item">Error loading results</div>';
      resultsContainer.classList.add('show');
      logError(error, 'searchUsers(query): searchTimeout');
    }
  }, 300);
}

//Shows the results from the query
function setActiveSearchResult(idx) {
  const resultsContainer = document.getElementById('search-results');
  const items = Array.from(resultsContainer.querySelectorAll('.search-result-item'));
  items.forEach((item, i) => {
    if (i === idx) {
      item.classList.add('active');
      item.scrollIntoView({block: 'nearest'});
      searchSelectedIndex = idx;
    } else {
      item.classList.remove('active');
    }
  });
}
function searchDropdownKeyHandler(e) {
  const resultsContainer = document.getElementById('search-results');
  if (!resultsContainer.classList.contains('show')) return;
  const items = Array.from(resultsContainer.querySelectorAll('.search-result-item'));
  if (e.key === 'ArrowDown') {
    e.preventDefault();
    let next = (searchSelectedIndex + 1) % items.length;
    setActiveSearchResult(next);
  } else if (e.key === 'ArrowUp') {
    e.preventDefault();
    let prev = (searchSelectedIndex - 1 + items.length) % items.length;
    setActiveSearchResult(prev);
  } else if (e.key === 'Enter') {
    if (searchSelectedIndex >= 0 && searchSelectedIndex < items.length) {
      items[searchSelectedIndex].click();
    }
  } else if (e.key === 'Escape') {
    resultsContainer.classList.remove('show');
  }
}

//Allows user to view searched user profile
function viewUserProfile(username) {
  const currentRole = document.body.dataset.role.includes('Technician') ? 'technician' : 'student';
  const currentUsername = document.body.dataset.username;
  window.location.href = `/dashboard/view-profile/${encodeURIComponent(username)}?username=${encodeURIComponent(currentUsername)}`;
}

// Fetch labs/rooms
async function fetchRoomsAndSelect() {
  try {
    const res = await fetch('/api/labs'); //gets all the labs
    const data = await res.json();
    rooms = data.map(lab => ({ //Maps out all relevant fields of each lab
      id: lab._id,
      number: lab.number,
      name: `Lab ${lab.number} (${lab.class})`
    }));
    rooms.sort((a, b) => a.number - b.number); //Sorts the labs based on number
    const { labNumber } = getURLParams();
    if (labNumber) { //Tries to find a lab based on numner
      const found = rooms.find(l => l.number === labNumber);
      if (found) { //Displays the lab if found
        selectedRoom = found.name;
        selectedLabNumber = found.number;
      }
    }
    renderRooms(); //Renders the rooms 
  } catch (error) {
    console.error('Failed to load labs:', error);
    document.getElementById("rooms").innerHTML = "<p style='color:red'>Failed to load rooms</p>";
    logError(error, 'fetchRoomsAndSelect()');
  }
}

//Clock function
function updateClock() {
  const now = new Date();
  document.getElementById("clock").textContent =
    now.toLocaleDateString() + " | " + now.toLocaleTimeString();
}
setInterval(updateClock, 1000);
updateClock();
//Renders all rooms
function renderRooms() {
  const container = document.getElementById("rooms");
  container.innerHTML = "";
  const { role, username } = getURLParams(); //URL parameters 
  rooms.forEach(lab => {
    const div = document.createElement("div");
    div.textContent = lab.name;
    div.className = selectedRoom === lab.name ? "room-item selected" : "room-item";
    div.onclick = () => { // When a user clicks on a lab allow for the front and backend to display relevant information of that lab
      selectedRoom = lab.name;
      selectedLabNumber = lab.number;
      renderRooms();
      renderSeatInfo();
      fetchReservationList().then(renderSeats);
      updateReserveButton();
      window.history.pushState({}, '', `/dashboard/${role}/lab/${lab.number}?username=${encodeURIComponent(username)}`);
    };
    container.appendChild(div); // makes room for next lab
  });
}
//Monthly calendar
function renderMonth() {
  document.getElementById("monthLabel").textContent = currentMonth.toLocaleString("default", {
    month: "long",
    year: "numeric"
  });
}

//Changes month
function changeMonth(offset) {
  currentMonth.setMonth(currentMonth.getMonth() + offset);
  renderMonth();
  renderCalendar();
}

//Generates a calendar
function renderCalendar() {
  const calendar = document.getElementById("calendar");
  calendar.innerHTML = "";
  const today = new Date(); //gets the current date and time 
  today.setHours(0, 0, 0, 0);
  const limit = new Date(today);
  limit.setDate(today.getDate() + 7); //Only show 7 days at a time 

  const start = new Date(currentMonth.getFullYear(), currentMonth.getMonth(), 1); //Current parameters
  const startDay = start.getDay();
  let date = new Date(start);
  date.setDate(date.getDate() - startDay);
//Displays date boxes(42 by default)
  for (let i = 0; i < 42; i++) {
    const key = date.toDateString();
    const div = document.createElement("div");
    const isPast = date < today; //if date is past
    const isBeyond = date > limit; //if date is beyond the 7 days
    const isCurrentMonth = date.getMonth() === currentMonth.getMonth();
    const isSelected = selectedDate === key;
    //Disables date element from being selected 
    if (!isCurrentMonth || isPast || isBeyond) {
      div.className = "disabled";
    } else { //User can click on a date and generate information from that date
      div.className = "hoverable";
      div.onclick = () => {
        selectedDate = key;
        renderCalendar();
        renderSeatInfo();
        fetchReservationList().then(renderSeats);
        updateReserveButton();
      };
    }
    if (isSelected) div.classList.add("active");
    div.innerHTML = `<div>${date.toLocaleString("default", { weekday: "short" })}</div><div>${date.getDate()}</div>`;
    calendar.appendChild(div); //Makes room for next day
    date.setDate(date.getDate() + 1);
  }
}
//Gets all the reservations from the database
async function fetchReservationList() {
  try {
    const res = await fetch('/api/seat-lists'); //gets the resevrations
    currentReservationList = await res.json(); 
  } catch (e){
    currentReservationList = [];
    console.error('Failed to fetch reservations:', e);
    logError(e, 'fetchReservationList()');
  }
}
//Gets key(date and time)(helper)
function getKey() {
  if (!selectedDate || !selectedRoom) return null;
  return `${new Date(selectedDate).toISOString().split("T")[0]}_${selectedRoom}`;
}

//Converts time to human readable format
function timeToIndex(apiTime) {
  const time = apiTime.split(" ")[0].split(":");
  let index = (apiTime == "7 PM") ? 24 : (time[0] < 7) ? (parseInt(time[0]) - 1) * 2 + 12 : (parseInt(time[0]) - 7) * 2;
  if (time[1] == "30")
    index++;
  return index;
}

//Associates a slot to a certain time 
function slotToTime(slot) {
  const baseHour = 7;
  const totalMinutes = baseHour * 60 + slot * 30;
  const hours = Math.floor(totalMinutes / 60);
  const minutes = totalMinutes % 60;
  const period = hours >= 12 ? "PM" : "AM";
  const displayHour = hours % 12 === 0 ? 12 : hours % 12;
  const timeStr = `${String(displayHour).padStart(2, '0')}:${String(minutes).padStart(2, '0')} ${period}`;
  return timeStr;
}

//Seat Information
function renderSeatInfo() {
  document.getElementById("selectedInfo").textContent = `Seat availability at Lab Room: ${selectedRoom || "(not selected)"}`;
}

//Generates the seats per lab per day
function renderSeats() {
  const table = document.getElementById("seatTable");
  table.innerHTML = "";
  const key = getKey(); //gets the date and time 
  const hours = Array.from({ length: 12 }, (_, i) => `${7 + i}:00${i < 5 ? "am" : "pm"}`); //Labs available from 7AM-7PM
  const slots = hours.length * 2; //Divided by 2 so that each hour has 2 time slots 
  const now = new Date();
  const todayKey = new Date().toDateString();
  const isToday = todayKey === selectedDate; //Checks what day is today
  const selected = key && seatSelections[key] ? seatSelections[key] : new Set(); //Selected seat

  //Makes sure that the frontend only shows reservations for the currently selected lab and currently selected date
  const filteredReservationList = Array.isArray(currentReservationList) ? currentReservationList.filter(seatObj => {
    if (!seatObj.reservation || !seatObj.reservation.lab || !seatObj.reservation.date) return false;
    // Filter by lab
    let isLab =
      typeof seatObj.reservation.lab === 'object'
        ? seatObj.reservation.lab.number === selectedLabNumber
        : seatObj.reservation.lab === selectedLabNumber;
    // Filter by date
    const seatDate = new Date(seatObj.reservation.date);
    const selDate = selectedDate ? new Date(selectedDate) : null;
    const isDate = selDate
      ? seatDate.getFullYear() === selDate.getFullYear() &&
        seatDate.getMonth() === selDate.getMonth() &&
        seatDate.getDate() === selDate.getDate()
      : false;
    return isLab && isDate;
  }) : [];

  const header = document.createElement("thead");
  const headRow = document.createElement("tr");
  headRow.innerHTML = `<th>Seat</th>` + hours.map(h => `<th colspan="2">${h}</th>`).join("");
  header.appendChild(headRow);
  table.appendChild(header);

  const tbody = document.createElement("tbody");
  //35 seats in total(7x5)
  const rows = 7; 
  const cols = 5;


  for (let row = 0; row < rows; row++) {
    for (let col = 0; col < cols; col++) {
      const seatNum = row * cols + col + 1;
      if (seatNum > 35) break;
      const tr = document.createElement("tr"); 
      tr.innerHTML = `<td>Seat ${seatNum}</td>`; //seat number

      let listMatch = 0;
      //Just to make sure that each seat dosent interfere with the other 
      while (listMatch != filteredReservationList.length) {
        if (((filteredReservationList[listMatch].row - 1) == row && (filteredReservationList[listMatch].column - 1) == col))
          break;
        else
          listMatch++;
      }

      //Associates a slot to each seat 
      for (let slot = 0; slot < slots; slot++) {
        const td = document.createElement("td");
        const slotKey = `r${row + 1}-c${col + 1}-s${slot}`; //gives them all a unique key
        let isPast = false; //default
        if (isToday) { //date selected 
          const hour = 7 + Math.floor(slot / 2);
          const min = slot % 2 === 0 ? 0 : 30;
          isPast = now.getHours() > hour || (now.getHours() === hour && now.getMinutes() >= min);
        }

        //Command that makes sure unavailable seats cannot be booked anymore(frontend and backend)
        const unavailable = (listMatch != filteredReservationList.length) &&
          (slot >= timeToIndex(filteredReservationList[listMatch].reservation.time_start) &&
           slot < timeToIndex(filteredReservationList[listMatch].reservation.time_end));
        const disabled = !selectedRoom || !selectedDate || isPast;
        td.className = unavailable
        ? "legend unavailable"
        : disabled
        ? "disabled"
        : selected.has(slotKey)
        ? "reserved"
        : "available";
        td.onclick = () => { //Wehn a user clicks on a slot(usually when it becomes dark green)
          if (disabled || unavailable) return;
          if (!seatSelections[key]) seatSelections[key] = new Set(); 
          if (seatSelections[key].has(slotKey)) { //just making sure each seat is assoicated with a key
            seatSelections[key].delete(slotKey);
          } else {
            seatSelections[key].add(slotKey);
          }
          fetchReservationList().then(renderSeats); //updates constantly after every reservation so view is accurate
        };
        tr.appendChild(td);
      }
      tbody.appendChild(tr);
    }
  }
  table.appendChild(tbody);
}

//Helper
function updateReserveButton() 
{
  document.getElementById("reserveBtn").disabled = !(selectedRoom && selectedDate);
}

// Reserve button click handler for both student and technician
document.getElementById("reserveBtn").onclick = async () => {
  try {
    const key = getKey();
    const slotSet = seatSelections[key]; //gets slot key
    const { role, username, labNumber } = getURLParams();
    let actualUsername = username;
    let actualUserId = null;
    if (role === "technician") { //Makes sure a technician has a student to reserve for 
      if (!technicianSelectedStudent) {
        alert("Technician: You must select a student to reserve for.");
        return;
      }
      //gets the student object
      actualUsername = technicianSelectedStudent.username; 
      actualUserId = technicianSelectedStudent._id;
      let verifyRes = await fetch(`/api/users/${actualUserId}`);
      if (!verifyRes.ok) {
        alert("Selected student does not exist.");
        return;
      }
      const verifyUser = await verifyRes.json();
      if (verifyUser.role !== "Student") {
        alert("Selected user is not a student.");
        return;
      }
    }
    if (!slotSet || slotSet.size === 0) {
      alert("Please select at least one seat and time slot.");
      return;
    }
    const slotData = Array.from(slotSet).map(s => {
      const match = s.match(/r(\d+)-c(\d+)-s(\d+)/);
      if (!match) throw new Error(`Invalid slot format: ${s}`);
      return {
        row: parseInt(match[1], 10),
        column: parseInt(match[2], 10),
        slot: parseInt(match[3], 10)
      };
    });
    // Group by seat
    const seatGroups = {};
    for (const s of slotData) {
      const seatKey = `${s.row}_${s.column}`;
      if (!seatGroups[seatKey]) seatGroups[seatKey] = [];
      seatGroups[seatKey].push(s.slot);
    }
    // Validate each seat has consecutive slots
    for (const [seatKey, slots] of Object.entries(seatGroups)) {
      const sorted = slots.sort((a, b) => a - b);
      const isConsecutive = sorted.every((s, i, arr) => i === 0 || s === arr[i - 1] + 1);
      if (!isConsecutive) {
        const [row, col] = seatKey.split('_').map(Number);
        const seatNum = (row - 1) * 5 + col;
        alert(`Slots for Seat ${seatNum} must be consecutive.`);
        return;
      }
    }

    //Gets the data of all the slots including which seat it is and the time start and end 
    const allSlots = slotData.map(s => s.slot);
    const globalStartSlot = Math.min(...allSlots);
    const globalEndSlot = Math.max(...allSlots) + 1;
    const seats = Object.keys(seatGroups).map(key => {
      const [row, column] = key.split('_').map(Number);
      return { row, column };
    });
    if (!actualUsername) {
      alert("Username is required");
      return;
    }
    if (!labNumber) {
      alert("Lab number is required");
      return;
    }
    if (typeof selectedDate === 'undefined') {
      alert("Please select a date");
      return;
    }
    const anonymity = document.getElementById('anonymityToggle')?.checked || false;
    if (typeof slotToTime !== 'function') {
      alert("Time conversion function not available");
      return;
    }

    // Once reserved sends a post request to add the reservation
    const request = {
      time_start: slotToTime(globalStartSlot),
      time_end: slotToTime(globalEndSlot),
      user: actualUsername,
      lab: labNumber,
      date: new Date(selectedDate),
      anonymity,
      seats
    };
    if (role === "technician" && actualUserId) {
      request.studentId = actualUserId;
    }
    const reserveBtn = document.getElementById("reserveBtn");
    const originalText = reserveBtn.textContent;
    reserveBtn.disabled = true;
    reserveBtn.textContent = "Reserve Slot";
    let response = null;
    //If the reservation already exists(and user wants to edit it)
    if (getReservationID()) {
      response = await fetch("/api/reservations/" + getReservationID(), {
        method: "PUT",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(request)
      });
    }
    //adds a new reservation
    else {
      response = await fetch("/api/reservations", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(request)
      });
    }
    if (!response.ok) {
      let errorMessage = `HTTP ${response.status}`;
      try {
        const errorData = await response.json();
        errorMessage = errorData.message || errorData.error || errorMessage;
      } catch (e) {
        logError(e, 'Reserve button onclick (dashboard.js)');
        try {
          errorMessage = await response.text();
        } catch (e2) {
          errorMessage = `HTTP ${response.status} - ${response.statusText}`;
          logError(e2, 'Reserve button onclick (dashboard.js)');
        }
      }
      throw new Error(errorMessage);
    }
    const data = await response.json();
    alert("Reservation successful!"); //sucessful reservation
    seatSelections[key] = new Set();
    if (typeof renderSeats === 'function') {
      await fetchReservationList();
      renderSeats();
    }
    //redirects them back to either profile for student or reservation list for admin
    if (role === 'student') {
      window.location.href = `/dashboard/student/profile?username=${encodeURIComponent(actualUsername)}`;
    } else {
      window.location.href = `/dashboard/technician/reservation-list?username=${encodeURIComponent(username)}`;
    }
  } catch (error) {
    console.error("Full error object:", error);
    alert(`Failed to reserve slots: ${error.message}`); //error message
    logError(error, 'Reserve button onclick (dashboard.js)');
  } finally {
    const reserveBtn = document.getElementById("reserveBtn");
    if (reserveBtn) {
      reserveBtn.disabled = false;
      if(role === "technician")
      {
        reserveBtn.textContent = "Reserve For Student";
      }
      else
        reserveBtn.textContent = "Reserve Slot";
    }
  }
};

//Helper function 
function toManilaTime(date) {
  if (!date) return '';
  const d = new Date(date);
  d.setHours(d.getHours() + 8);
  return d;
}

// Event Listener
document.addEventListener('DOMContentLoaded', async function() {
  // Main search bar
  const searchInput = document.getElementById('user-search-input');
  if (searchInput) {
    searchInput.addEventListener('input', function(e) {
      searchUsers(e.target.value);
    });
    searchInput.addEventListener('keydown', searchDropdownKeyHandler);
  }
  // Technician student search
  const techStudentSearchInput = document.getElementById('technician-student-search');
  if (techStudentSearchInput) {
    techStudentSearchInput.addEventListener('input', function(e) {
      technicianSearchStudentsForReservation(e.target.value);
    });
    techStudentSearchInput.addEventListener('keydown', technicianStudentDropdownKeyHandler);
  }
  // Hide dropdowns on outside click
  document.addEventListener('click', function(event) {
    if (!event.target.closest('.search-wrapper')) {
      const resultsContainer = document.getElementById('search-results');
      if (resultsContainer) {
        resultsContainer.innerHTML = '';
        resultsContainer.classList.remove('show');
      }
      const techResultsContainer = document.getElementById('technician-student-search-results');
      if (techResultsContainer) {
        techResultsContainer.innerHTML = '';
        techResultsContainer.classList.remove('show');
      }
    }
  });
  fetchRoomsAndSelect();
  renderMonth();
  renderCalendar();
  await fetchReservationList();
  renderSeats();
  updateClock();
  if (typeof setupTechnicianStudentUI === "function") setupTechnicianStudentUI();
});

// Global functions
window.searchUsers = searchUsers;
window.viewUserProfile = viewUserProfile;
window.technicianStudentSearch = technicianStudentSearch;
window.selectTechnicianStudent = selectTechnicianStudent;
window.clearTechnicianStudent = clearTechnicianStudent;
window.technicianSearchStudentsForReservation = technicianSearchStudentsForReservation;
window.technicianStudentDropdownKeyHandler = technicianStudentDropdownKeyHandler;
window.searchDropdownKeyHandler = searchDropdownKeyHandler;