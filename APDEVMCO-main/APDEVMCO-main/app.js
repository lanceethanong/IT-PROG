if(process.env.NODE_ENV !== 'production'){ //allows for session private key
  require('dotenv').config();
}

//Installed depencencies
const express = require('express'); 
const session = require('express-session');
const exphbs = require('express-handlebars'); 
const path = require('path'); 
const mongoose = require('mongoose');
const app = express(); 
const port = 3000;
const connectDB = require('./model/db'); //connect to db
const bcrypt = require('bcrypt');
const { checkLoggedIn, bypassLogin, checkStudent, checkLabTech, checkAdmin } = require('./middleware'); //middleware functions
app.use(express.urlencoded({ extended: true })); 
app.use(express.json()); 
//enable sessions and cookies
app.use(session({
    secret: process.env.SESSION_SECRET,
    resave: false,
    saveUninitialized: true,
    name: 'user-session',
    cookie:{
      httpOnly: true,
    }
}));
app.use(express.static(path.join(__dirname, 'public')));

//Models 
const User = require('./model/user');
const Lab = require('./model/lab');
const Reservation = require('./model/reservation');
const SeatList = require('./model/seat_list');
const ErrorLog = require('./model/error_log');

//handlebars
const hbs = exphbs.create({
  extname: '.hbs',
  layoutsDir: path.join(__dirname, 'views/layouts'), //layouts directory
  partialsDir: path.join(__dirname, 'views/partials'), //partials directory
  helpers: {
    eq: (a, b) => a === b,
    statusClass: function (status) { //color of the status
      switch (status) {
        case 'Cancelled': return 'red';
        case 'Completed': return 'green';
        case 'In Progress': return 'yellow';
        case 'Scheduled': return 'blue';
        default: return '';
      }
    },
    formatManilaDate(dateStr) { //so that time is correct 
      if (!dateStr) return "";
      let date;
      if (/^\d{4}-\d{2}-\d{2}$/.test(dateStr)) {
        date = new Date(dateStr + "T00:00:00-00:00");
      } else {
        date = new Date(dateStr);
      }


      date.setDate(date.getDate() + 1); //bug fix

      return date.getFullYear() //get year
        + "-" + String(date.getMonth() + 1).padStart(2, '0')
        + "-" + String(date.getDate()).padStart(2, '0');
    },
  }
});

app.engine('hbs', hbs.engine); //enables hbs files
app.set('view engine', 'hbs');

//error logging
async function logError(error, source, user) {
  try {
    const log = new ErrorLog({
      message: error.message,
      stack: error.stack,
      source: source || null,
      user: user || null
    });
    await log.save();
  } catch (loggingError) {
    console.error('Failed to log error:', loggingError);
  }
}

//route for login
app.get('/', bypassLogin, (req, res) => {
  res.render('handlebars/home', {
    title: 'Home Page',
    layout: 'homeLayout',
  });
});

//route for login
app.get('/login', bypassLogin, (req, res) => {
  res.render('handlebars/login', {
    title: 'Login',
    layout: 'login-signupLayout',
    page: 'login'
  });
});

//post route for login
app.post('/login', async (req, res) => {
  const { email, password, remember } = req.body; //gets input data 

  try {
    const user = await User.findOne({ email }); //tries to find an existing email

    if (!user) { //Email dosent exist
      return res.status(401).render('handlebars/login', {
        layout: 'login-signupLayout',
        title: 'Login',
        page: 'login',
        loginError: 'Invalid email'
      });
    }
    //If hashed passwords arent the same
    if (!(await bcrypt.compare(password, user.password))) {
      return res.status(401).render('handlebars/login', {
        layout: 'login-signupLayout',
        title: 'Login',
        page: 'login',
        loginError: 'Invalid password.'
      });
    }

    const role = user.role;
    const username = user.username;
    //saves session data assuming user wants to remember me 
    req.session.user = {
      id: user._id,
      username: user.username,
      role: user.role,
      remember: remember === 'on'
    };
    //if user selects remember me generate a cookie 
    if (req.session.user.remember) {
      req.session.cookie.maxAge = 1000 * 60 * 60 * 24 * 30; //cookie lifespan
    } else {
      req.session.cookie.expires = false;
    }

    let redirectURL;
    //redirects them to the correct page 
    if (role === 'Admin') {
      redirectURL = `/admin`;
    } else if (role === 'Lab Technician') {
      redirectURL = `/dashboard/technician?username=${encodeURIComponent(username)}`;
    } else if (role === 'Student') {
      redirectURL = `/dashboard/student?username=${encodeURIComponent(username)}`;
    }

    return res.redirect(redirectURL);

  } catch (err) {
    console.error('Login failed:', err);
    return res.status(500).render('handlebars/login', {
      layout: 'login-signupLayout',
      title: 'Login',
      page: 'login',
      loginError: 'Server error. Please try again.'
    });
  }
});



// Admin home page
app.get('/admin', checkAdmin, (req, res) => {
  res.render('handlebars/admin', {
    title: 'Admin Home',
    layout: 'adminLayout',
    page: 'home',
    adminName: req.session.user.username
  });
});

//Admin Routes
// View all lab technicians
app.get('/admin/view-labtech', checkAdmin, async (req, res) => {
  const techs = await User.find({ role: 'Lab Technician' }).lean();
  res.render('handlebars/admin', {
    title: "View Lab Technicians",
    layout: 'adminLayout',
    page: 'view',
    techs,
    adminName: req.session.user.username
  });
});

//Add a lab technician(view)
app.get('/admin/add-labtech', checkAdmin, (req, res) => {
  res.render('handlebars/admin', {
    title: 'Add Lab Technician',
    layout: 'adminLayout',
    page: 'add',
    adminName: req.session.user.username
  });
});

//Add lab technician(post)
app.post('/admin/add-labtech', checkAdmin, async (req, res) => {
  const { username, email, password } = req.body; //input fields 
  const existingUser = await User.findOne({ username }); //finds if user already exists
  if (existingUser) {
    return res.render('handlebars/admin', {
      title: 'Add Lab Technician',
      layout: 'adminLayout',
      page: 'add',
      registerError: 'Username already taken.',
      adminName: req.session.user.username
    });
  }
  //hashed passwords 
  const hashedPassword = await bcrypt.hash(password, 10);
  await new User({ username, email, password: hashedPassword, role: 'Lab Technician' }).save(); //saves to db
  req.session.successMessage = 'Lab Technician added successfully!';
  res.redirect('/admin/view-labtech'); //shows the added user
});

//Remove lab technician (view)
app.get('/admin/remove-labtech', checkAdmin, (req, res) => {
  res.render('handlebars/admin', {
    title: 'Remove Lab Technician',
    layout: 'adminLayout',
    page: 'remove',
    adminName: req.session.user.username
  });
});

// Remove lab technician (post)
app.post('/admin/remove-labtech', checkAdmin, async (req, res) => {
  const { username } = req.body;
  const deleted = await User.deleteOne({ username, role: 'Lab Technician' }); //checks if the user exists 
  if (deleted.deletedCount === 0) { //no lab technician found 
    return res.render('handlebars/admin', {
      title: 'Remove Lab Technician',
      layout: 'adminLayout',
      page: 'remove',
      removeError: 'No such Lab Technician found.',
      adminName: req.session.user.username
    });
  }
  req.session.successMessage = 'Lab Technician removed successfully!';
  res.redirect('/admin/view-labtech');
});

// //logout route 
// app.get('/logout', (req, res) => {
//   try {
//     if(!req.session.user.remember) { // If user logouts 
//       req.session.destroy(err =>{
//         if (err){
//           console.error('Session destruction error:', err);
//           return res.status(500).send('Server error during logout.');
//         }
//         res.clearCookie('user-session');
//         res.redirect('/');
//       });
//     } else {
//       res.redirect('/');
//     }
//   } catch (error) {
//     console.error('Logout error:', error);
//     res.status(500).send('Server error during logout.');
//   }
// });

//logout route 
app.get('/logout', (req, res) => {
  try {
    
    req.session.destroy(err => {
      if (err) {
        console.error('Session destruction error:', err);
        return res.status(500).send('Server error during logout.');
      }
      // Clear the session cookie
      res.clearCookie('user-session');
      // Redirect to home page
      res.redirect('/');
    });
  } catch (error) {
    console.error('Logout error:', error);
    res.status(500).send('Server error during logout.');
  }
});

//route for register
app.get('/register', (req, res) => {
  res.render('handlebars/register', {
    title: 'Register',
    layout: 'login-signupLayout',
    page: 'register'
  });
});
//post route for register
app.post('/register', async (req, res) => {
  const { username, email, password, confirmPassword } = req.body; //user input

  if (password !== confirmPassword) { // If password inputs dont match
    return res.render('handlebars/register', {
      layout: 'login-signupLayout',
      title: 'Register',
      page: 'register',
      registerError: 'Passwords do not match.'
    });
  }

  try {
    const existingUser = await User.findOne({ email }); //Checks if user with that email exists 
    if (existingUser) {
      return res.render('handlebars/register', {
        layout: 'login-signupLayout',
        title: 'Register',
        page: 'register',
        registerError: 'Email already in use.'
      });
    }

    const existingUsername = await User.findOne({ username }); //Checks if the username already exists 
    if (existingUsername) {
      return res.render('handlebars/register', {
        layout: 'login-signupLayout',
        title: 'Register',
        page: 'register',
        registerError: 'Username already taken.'
      });
    }

    if (password.length < 8) { //password must be 8 characters long 
      return res.render('handlebars/register', {
        layout: 'login-signupLayout',
        title: 'Register',
        page: 'register',
        registerError: 'Password must be at least 8 characters long.'
      });
    }

    const hashedPassword = await bcrypt.hash(password, 10); //hashes password

    const newUser = new User({ //saves user to db
      username,
      email,
      password: hashedPassword,
      role: 'Student',
      description: '',
      picture: 'picture.jpg',
      remember: false
    });

    await newUser.save();

    const redirectURL = `/dashboard/student?username=${encodeURIComponent(username)}`; //redirects user to dashboard(always student)
    res.redirect(redirectURL);

  } catch (err) {
    console.error('Registration error:', err);
    res.status(500).render('handlebars/register', {
      layout: 'login-signupLayout',
      title: 'Register',
      page: 'register',
      registerError: 'Something went wrong. Please try again.'
    });
  }
});


//route for student dashboard
app.get('/dashboard/student', checkStudent, (req, res) => {
  const username = req.query.username || 'Student';
  const role = req.query.role || 'Student';

  res.render('handlebars/dashboard', {
    title: 'Student Dashboard',
    layout: 'dashboard-Layout',
    username,
    role,
    rooms: [],
  });
});

//route for technician dashboard
app.get('/dashboard/technician',checkLabTech, (req, res) => {
  const username = req.query.username || 'Lab Technician';
  const role = req.query.role || 'Lab Technician';

  res.render('handlebars/dashboard', {
    title: 'Technician Dashboard',
    layout: 'dashboard-Layout',
    username,
    role,
    rooms: [],
  });
});

// Route for editing reservations
app.get('/dashboard/technician/edit/:id', checkLabTech, (req, res) => {
  const id = req.query.id;

  res.render('handlebars/dashboard', {
    title: 'Edit Reservation',
    layout: 'dashboard-Layout',
    id,
    username: "Lab Technician",
    role: "Lab Technician",
    rooms: [],
  });
});

//route for getting lab numbers 
app.get('/dashboard/:role/lab/:labNumber', (req, res) => {
  const { role, labNumber } = req.params;
  const username = req.query.username || 'Guest';

  res.render('handlebars/dashboard', {
    title: `${role} Dashboard`,
    layout: 'dashboard-Layout',
    username,
    role: role === 'technician' ? 'Lab Technician' : 'Student',
    rooms: [], 
    selectedLabNumber: labNumber
  });
});

//route for technician help
app.get('/dashboard/technician/help',checkLabTech, (req, res) => {
  const { username } = req.query;

  res.render('handlebars/help', {
    layout: 'homeLayout',
    title: 'Help & Support',
    username,
    role: 'Lab Technician'
  });
});

//route for student help
app.get('/dashboard/student/help',checkStudent, (req, res) => {
  const { username } = req.query;

  res.render('handlebars/help', {
    layout: 'homeLayout',
    title: 'Help & Support',
    username,
    role: 'Student'
  });
});

//route for technician profile 
app.get(['/dashboard/technician/profile', '/dashboard/Lab%20Technician/profile'], checkLabTech,async (req, res) => {
  const username = req.query.username;

  try {
    const user = await User.findOne({ username, role: 'Lab Technician' });
    if (!user) {
      return res.status(404).send('Technician not found');
    }
    
    res.render('handlebars/profile', {
      title: 'Technician Profile',
      layout: 'profile-Layout',
      username: user.username,
      role: user.role,
      description: user.description || ''
    });
  } catch (error) {
    console.error('Technician profile error:', error);
    res.status(500).send('Server error');
  }
});

//route for student profile
app.get('/dashboard/student/profile', checkStudent, async (req, res) => {
  const username = req.query.username;

  try {
    const student = await User.findOne({ username, role: 'Student' });
    if (!student) {
      return res.status(404).send('Student not found');
    }

    const getShowDelete = (resv) => {
      if (!resv || !resv.time_start || !resv.date) return false;
      const [sh, sm] = resv.time_start.split(':').map(Number);
      const start = new Date(resv.date);
      start.setHours(sh, sm, 0, 0);
      const now = new Date();
      return start - now <= 10 * 60 * 1000;
    };

    // fixes time to be the same as current Manila time 
    function parseLocalDateTime(dateInput, timeStr) {
      const date = new Date(dateInput); //gets current date 

      const match = timeStr.match(/(\d+)(?::(\d+))?\s*(AM|PM)/i); //human readable 
      if (!match) return date;

      let hour = parseInt(match[1], 10);
      const minute = match[2] ? parseInt(match[2], 10) : 0;
      const ampm = match[3].toUpperCase();

      //AM PM handlers 
      if (ampm === 'PM' && hour !== 12) hour += 12; 
      if (ampm === 'AM' && hour === 12) hour = 0;

      date.setHours(hour, minute, 0, 0);
      return date;
    }

    //Get current status of reservations 
    function getStatus(resv) {
      if (!resv || !resv.date || !resv.time_start || !resv.time_end) return 'Scheduled'; //by default 

      //gets the start and end time of the reservation as well as what the current time is 
      const start = parseLocalDateTime(resv.date, resv.time_start);
      const end = parseLocalDateTime(resv.date, resv.time_end);
      const now = new Date();

      if (resv.status === 'Cancelled') return 'Cancelled'; //if its cancelled 
      if (now < start) return 'Scheduled'; //if the reservation is in the future 
      if (now >= start && now <= end) return 'In Progress'; //if the reservation is the present 
      return 'Completed'; //if the reservation is in the past 
    }

    // Generates all the users reservation
    const [studentSeats] = await Promise.all([
      SeatList.find().populate({
        path: 'reservation',
        populate: [
          { path: 'user', select: 'username' },
          { path: 'lab', select: 'class number' }
        ]
      }).lean()
    ]);

    const allReservations = [];

    //Pushes all the relevant data on the students reservation list 
    for (const seat of studentSeats) {
      const r = seat.reservation;
      if (!r || !r.user || r.user.username !== username) continue;

      allReservations.push({
        _id: r._id && r._id.toString ? r._id.toString() : r._id,
        row: seat.row,
        column: seat.column,
        lab: `Lab ${r.lab.number} (${r.lab.class})`,
        time_start: r.time_start,
        time_end: r.time_end,
        date: r.date.toISOString().split('T')[0], 
        createdAt: r.createdAt.toLocaleString(),
        status: getStatus(r),
        showDelete: getShowDelete(r)
      });
    }

    allReservations.sort((a, b) => new Date(b.createdAt) - new Date(a.createdAt));
    const pastReservations = allReservations.filter(r => ['Completed', 'Cancelled'].includes(r.status)); //Shows all past reservations
    const upcomingReservations = allReservations.filter(r => !['Completed', 'Cancelled'].includes(r.status)); //Shows all future reservations 

    res.render('handlebars/profile', {
      title: 'Student Profile',
      layout: 'profile-Layout',
      username,
      role: student.role,
      description: student.description || '',
      upcomingReservations,
      pastReservations
    });
  } catch (error) {
    console.error('Student profile error:', error);
    res.status(500).send('Server error');
  }
});
//Shows reservation list of all students(for technician)
app.get('/dashboard/technician/reservation-list',checkLabTech, async (req, res) => {
  const { username } = req.query;

  try {
    const technician = await User.findOne({ username, role: 'Lab Technician' });
    if (!technician) {
      return res.status(403).send('Unauthorized access');
    }

    const getShowDelete = (resv) => {
      if (!resv || !resv.time_start || !resv.date) return false;
      const [sh, sm] = resv.time_start.split(':').map(Number);
      const start = new Date(resv.date);
      start.setHours(sh, sm, 0, 0);
      const now = new Date();
      return start - now <= 10 * 60 * 1000;
    };

  
    const [studentSeats] = await Promise.all([
      SeatList.find().populate({
        path: 'reservation',
        populate: [
          { path: 'user', select: 'username' },
          { path: 'lab', select: 'class number' }
        ]
      }).lean()
    ]);

    function parseLocalDateTime(dateInput, timeStr) {
      const date = new Date(dateInput);

      const match = timeStr.match(/(\d+)(?::(\d+))?\s*(AM|PM)/i);
      if (!match) return date;

      let hour = parseInt(match[1], 10);
      const minute = match[2] ? parseInt(match[2], 10) : 0;
      const ampm = match[3].toUpperCase();

      if (ampm === 'PM' && hour !== 12) hour += 12;
      if (ampm === 'AM' && hour === 12) hour = 0;

      date.setHours(hour, minute, 0, 0);
      return date;
    }

    //Gets the current status of reservation(same as the student view)
    function getStatus(resv) {
      if (!resv || !resv.date || !resv.time_start || !resv.time_end) return 'Scheduled';

      const start = parseLocalDateTime(resv.date, resv.time_start);
      const end = parseLocalDateTime(resv.date, resv.time_end);
      const now = new Date();

      if (resv.status === 'Cancelled') return 'Cancelled';
      if (now < start) return 'Scheduled';
      if (now >= start && now <= end) return 'In Progress';
      return 'Completed';
    }

    const combined = [];

    // Student Reservations
    for (const seat of studentSeats) {
      const r = seat.reservation;
      if (!r || !r.user || !r.lab) continue;

      const status = getStatus(r);
      const isPast = status === 'Completed' || status === 'Cancelled';

      combined.push({
        _id: r._id && r._id.toString ? r._id.toString() : r._id,
        row: seat.row,
        column: seat.column,
        student: r.user.username,
        lab: `Lab ${r.lab.number} (${r.lab.class})`,
        time_start: r.time_start,
        time_end: r.time_end,
        date: r.date.toISOString().split('T')[0],
        createdAt: r.createdAt.toLocaleString(),
        status,
        showDelete: getShowDelete(r),
        type: 'student',
        isPast
      });
    }

    combined.sort((a, b) => new Date(b.createdAt) - new Date(a.createdAt));

    res.render('handlebars/reservation', {
      title: 'All Reservations',
      layout: 'dashboard-Layout',
      username,
      role: 'Lab Technician',
      reservations: combined
    });

  } catch (err) {
    console.error('Failed to load reservation list:', err);
    res.status(500).send('Server error loading reservation list.');
  }
});

//view user profile 
app.get('/dashboard/view-profile/:username', async (req, res) => {
  const { username } = req.params;
  const currentUsername = req.query.username;
  const decodedUsername = decodeURIComponent(username);

  try {
    const [currentUser, viewedUser] = await Promise.all([
      User.findOne({ username: currentUsername }),
      User.findOne({ username: decodedUsername })
    ]);

    if (!currentUser || !viewedUser) {
      return res.status(404).send('User not found');
    }

    if (currentUser.username === viewedUser.username) { 
      return res.redirect(`/dashboard/${currentUser.role.toLowerCase()}/profile?username=${encodeURIComponent(currentUsername)}`);
    }

    let upcomingReservations = [];
    let pastReservations = [];
    
    if (currentUser.role === 'Lab Technician' && viewedUser.role === 'Student') 
      {
      /* const getStatus = (resv) => {
        if (!resv || !resv.time_start || !resv.date) return 'Unknown';
        const [sh, sm] = resv.time_start.split(':').map(Number);
        const [eh, em] = resv.time_end.split(':').map(Number);
        const start = new Date(resv.date);
        const end = new Date(resv.date);
        start.setHours(sh, sm);
        end.setHours(eh, em);
        const now = new Date();
        
        if (resv.status === 'Cancelled') return 'Cancelled';
        if (now < start) return 'Scheduled';
        if (now >= start && now <= end) return 'In Progress';
        return 'Completed';
      };*/ 
    //same as the getStatus previously
     function getStatus(resv) {
     if (!resv || !resv.date || !resv.time_start || !resv.time_end) return 'Scheduled';

      const start = parseLocalDateTime(resv.date, resv.time_start);
      const end = parseLocalDateTime(resv.date, resv.time_end);
      const now = new Date();

      if (resv.status === 'Cancelled') return 'Cancelled';
      if (now < start) return 'Scheduled';
      if (now >= start && now <= end) return 'In Progress';
      return 'Completed';
    }

      const [studentSeats, techSeats] = await Promise.all([
        SeatList.find().populate({
          path: 'reservation',
          populate: [
            { path: 'user', select: 'username' },
            { path: 'lab', select: 'class number' }
          ]
        }).lean()
      ]);

      const allReservations = [];

      for (const seat of studentSeats) {
        const r = seat.reservation;
        if (!r || !r.user || r.user.username !== viewedUser.username) continue;

        allReservations.push({
          _id: r._id && r._id.toString ? r._id.toString() : r._id,
          row: seat.row,
          column: seat.column,
          lab: `Lab ${r.lab.number} (${r.lab.class})`,
          time_start: r.time_start,
          time_end: r.time_end,
          date: r.date.toISOString().split('T')[0],
          status: getStatus(r),
          type: 'student'
        });
      }

      allReservations.sort((a, b) => new Date(b.date) - new Date(a.date));
      pastReservations = allReservations.filter(r => ['Completed', 'Cancelled'].includes(r.status));
      upcomingReservations = allReservations.filter(r => !['Completed', 'Cancelled'].includes(r.status));
    }

    res.render('handlebars/viewprofile', {
      title: `${viewedUser.username}'s Profile`,
      layout: 'dashboard-Layout',
      username: currentUser.username, 
      role: currentUser.role,
      currentUser: {
        username: currentUser.username,
        role: currentUser.role
      },
      viewedUser: {
        username: viewedUser.username,
        role: viewedUser.role,
        description: viewedUser.description || ''
      },
      upcomingReservations,
      pastReservations
    });

  } catch (error) {
    console.error('View profile error:', error);
    res.status(500).send('Server error');
  }
});

// Log errors
app.post('/api/log-error', async (req, res) => {
  try {
    const { message, stack, source } = req.body;
    const user = req.session.user.username || null;

    await logError(
      {
        message,
        stack,
      },
      source,
      user
    );

    res.sendStatus(204);
  } catch (err) {
    console.error('Failed to log error:', err);
    res.sendStatus(500);
  }
});

//Delete users
app.delete('/api/users/:username', async (req, res) => {
  try {
    const { username } = req.params;
    const decodedUsername = decodeURIComponent(username);

    const user = await User.findOne({ username: decodedUsername }); //if User is not found 
    if (!user) {
      return res.status(404).json({ error: 'User not found' });
    }

    await User.deleteOne({ username: decodedUsername }); //Deletes the user from database along with all their records 
    res.json({ success: true, message: 'Account deleted successfully' });
  } catch (error) {
    console.error('Delete account error:', error);
    res.status(500).json({ error: 'Failed to delete account' });
  }
});

// Delete reservations
app.delete('/api/reservation/:id', async (req, res) => {
  try {
    const { id } = req.params;

    
    let reservation = await Reservation.findById(id); //finds the id of the reservation(unique)
    if (reservation) 
      {
      await SeatList.deleteMany({ reservation: id }); //allow user to delete a seat(assume that user can reserve more than 1 seat)
      await Reservation.deleteOne({ _id: id }); //allows user to delete a reservation
      return res.json({ success: true, message: 'Reservation deleted successfully' }); //deletes reservation 
    }

    return res.status(404).json({ error: 'Reservation not found' });
  } catch (error) {
    console.error('Delete reservation error:', error);
    res.status(500).json({ error: 'Failed to delete reservation' });
  }
});

//Change password
app.post('/api/users/:username/change-password', async (req, res) => {
  try {
    const { username } = req.params;
    const decodedUsername = decodeURIComponent(username);
    const { currentPassword, newPassword } = req.body; //gets the current and new password 

    const user = await User.findOne({ username: decodedUsername });
    if (!user) {
      return res.status(404).json({ error: 'User not found' });
    }
    const passwordMatch = await bcrypt.compare(currentPassword, user.password); //checks if passwords match 
    if (!passwordMatch) {
      return res.status(401).json({ error: 'Current password is incorrect' });
    }

    const hashedNewPassword = await bcrypt.hash(newPassword, 10); //hashes new password 
    user.password = hashedNewPassword;

    await user.save(); //saves changes

    res.json({ success: true, message: 'Password updated successfully' });
  } catch (error) {
    console.error('Password change error:', error);
    res.status(500).json({ error: 'Failed to change password' });
  }
});

//Update user information 
app.put('/api/users/:username', async (req, res) => {
  try {
    const { username } = req.params;
    const decodedUsername = decodeURIComponent(username);
    const { description } = req.body;

    const user = await User.findOneAndUpdate(
      { username: decodedUsername },
      { description },
      { new: true }
    );

    if (!user) {
      return res.status(404).json({ error: 'User not found' });
    }

    res.json({ success: true, user });
  } catch (error) {
    console.error('Profile update error:', error);
    res.status(500).json({ error: 'Failed to update profile' });
  }
});

//search for users 
app.get('/api/users/search/:query', async (req, res) => {
  try {
    const { query } = req.params;
    const decodedQuery = decodeURIComponent(query);
    
    const users = await User.find({ //Finds if a user exists 
      $or: [
        { username: { $regex: decodedQuery, $options: 'i' } },
        { email: { $regex: decodedQuery, $options: 'i' } }
      ]
    }).select('-password');

    res.json(users);
  } catch (error) {
    console.error('Search error:', error);
    res.status(500).json({ error: 'Failed to perform search' });
  }
});

//Lab API
app.get('/api/labs', async (req, res) => {
  try {
    const labs = await Lab.find();
    res.json(labs);
  } catch (error) {
    res.status(500).json({ error: error.message });
  }
});
//get lab by id 
app.get('/api/labs/:id', async (req, res) => {
  try {
    const lab = await Lab.findById(req.params.id);
    if (!lab) {
      return res.status(404).json({ error: 'Lab not found' });
    }
    res.json(lab);
  } catch (error) {
    res.status(500).json({ error: error.message });
  }
});
//create new lab
app.post('/api/labs', async (req, res) => {
  try {
    const lab = new Lab(req.body);
    await lab.save();
    res.status(201).json(lab);
  } catch (error) {
    res.status(400).json({ error: error.message });
  }
});

//get all users 
app.get('/api/users', async (req, res) => {
  try {
    const users = await User.find().select('-password');
    res.json(users);
  } catch (error) {
    res.status(500).json({ error: error.message });
  }
});
//get user by id 
app.get('/api/users/:id', async (req, res) => {
  try {
    const user = await User.findById(req.params.id).select('-password');
    if (!user) {
      return res.status(404).json({ error: 'User not found' });
    }
    res.json(user);
  } catch (error) {
    res.status(500).json({ error: error.message });
  }
});
//create a new user 
app.post('/api/users', async (req, res) => {
  try {
    const user = new User(req.body);
    await user.save();
    const userResponse = user.toObject();
    delete userResponse.password;
    res.status(201).json(userResponse);
  } catch (error) {
    res.status(400).json({ error: error.message });
  }
});

//get all reservations 
app.get('/api/reservations', async (req, res) => {
  try {
    const reservations = await Reservation.find() //get information from each reservation
      .populate('user', 'firstName lastName email')
      .populate('lab', 'class number');
    res.json(reservations);
  } catch (error) {
    res.status(500).json({ error: error.message });
  }
});
//create a new reservation 
app.post('/api/reservations', async (req, res) => {
  try {
    const { //data to be added 
      time_start,
      time_end,
      user: username,
      lab: labNumber,
      date,
      anonymity,
      seats
    } = req.body;

    if (!time_start || !time_end || !username || !labNumber || !date) { //incomplete data 
      return res.status(400).json({ 
        error: "Missing required fields: time_start, time_end, user, lab, date" 
      });
    }
    //no seats are selected 
    if (!Array.isArray(seats) || seats.length === 0) {
      return res.status(400).json({ 
        error: "At least one seat must be selected" 
      });
    }

    const foundUser = await User.findOne({ username });
    if (!foundUser) {
      return res.status(404).json({ error: "User not found" });
    }

    const foundLab = await Lab.findOne({ number: labNumber });
    if (!foundLab) {
      return res.status(404).json({ error: "Lab not found" });
    }

    
    let reservationDate;
    if (/^\d{4}-\d{2}-\d{2}$/.test(date)) {
      
      reservationDate = new Date(date + "T00:00:00+08:00");
    } else {
      
      reservationDate = new Date(date);
    }
    //Checks for if a student had previously reserved a slot at that time 
    const overlappingStudentReservations = await Reservation.find({ //checks for the lab and date 
      lab: foundLab._id, 
      date: reservationDate,
      $or: [
        {
          $and: [ //Checks the start and endtime of the reservation
            { time_start: { $lt: time_end } }, 
            { time_end: { $gt: time_start } }
          ]
        }
      ]
    });

    // Get the seats 
    const [studentSeats] = await Promise.all([
      SeatList.find({
        reservation: { $in: overlappingStudentReservations.map(r => r._id) } //maps out all overlapping reservations 
      })
    ]);
    const allOccupiedSeatPositions = [ //Get all of the seats that have previous reservations 
      ...studentSeats.map(seat => `${seat.row}-${seat.column}`) //Maps out all occupied seat positions(marked in red)
    ];

    const requestedSeatPositions = seats.map(seat => `${seat.row}-${seat.column}`); //What seat the user wants to reserve
    const hasConflict = requestedSeatPositions.some(pos => allOccupiedSeatPositions.includes(pos)); //Compares the requested seat position with the list of all occupied seats 

    if (hasConflict) { //If something interferes 
      return res.status(409).json({ 
        error: "One or more selected seats are already reserved for this time slot" //error message 
      });
    }

    //If reservation is sucessful add it to the database 
    const reservation = new Reservation({
      time_start,
      time_end,
      user: foundUser._id,
      lab: foundLab._id,
      date: reservationDate,
      anonymity: anonymity || false,
      status: 'Scheduled' //scheduled by default 
    });

    await reservation.save(); //saves reservation to db

    const seatDocs = seats.map(seat => ({ //Maps the reservation to a seat
      reservation: reservation._id,
      row: parseInt(seat.row),
      column: parseInt(seat.column)
    }));

    await SeatList.insertMany(seatDocs); 

    res.status(201).json({ //sucessully saved(reservation created)
      success: true,
      message: "Reservation created successfully",
      reservationId: reservation._id,
      reservation: {
        _id: reservation._id,
        time_start: reservation.time_start,
        time_end: reservation.time_end,
        date: reservation.date,
        user: foundUser.username,
        lab: `Lab ${foundLab.number} (${foundLab.class})`,
        seats: seatDocs
      }
    });

  } catch (error) {
    console.error("Reservation Error:", error);

    if (error.code === 11000) { //error (just in case )
      return res.status(409).json({ 
        error: "Duplicate reservation detected" 
      });
    }

    if (error.name === 'ValidationError') { 
      return res.status(400).json({ 
        error: "Validation error: " + Object.values(error.errors).map(e => e.message).join(', ')
      });
    }

    res.status(500).json({ 
      error: "Internal server error occurred while creating reservation" 
    });
  }
});

//Checks availability of lab
app.get('/api/labs/:labNumber/check-availability', async (req, res) => {
  try {
    const { date, time_start, time_end } = req.query;
    const { labNumber } = req.params;

    if (!date || !time_start || !time_end) {
      return res.status(400).json({ 
        error: "Missing required query parameters: date, time_start, time_end" 
      });
    }

    const lab = await Lab.findOne({ number: labNumber });
    if (!lab) {
      return res.status(404).json({ error: "Lab not found" });
    }

    const reservationDate = new Date(date);

    const [studentReservations, techReservations] = await Promise.all([
      Reservation.find({
        lab: lab._id,
        date: reservationDate,
        $or: [
          {
            $and: [
              { time_start: { $lt: time_end } },
              { time_end: { $gt: time_start } }
            ]
          }
        ]
      })
    ]);

    const occupiedSeats = [];
    
    if (studentReservations.length > 0) {
      const studentSeats = await SeatList.find({
        reservation: { $in: studentReservations.map(r => r._id) }
      });
      occupiedSeats.push(...studentSeats.map(seat => ({ row: seat.row, column: seat.column })));
    }
    
    const totalSeats = [];
    for (let row = 1; row <= 7; row++) {
      for (let col = 1; col <= 5; col++) {
        totalSeats.push({ row, column: col });
      }
    }

    const availableSeats = totalSeats.filter(seat => 
      !occupiedSeats.some(occupied => 
        occupied.row === seat.row && occupied.column === seat.column
      )
    );

    res.json({
      success: true,
      availableSeats,
      occupiedSeats,
      totalSeats: totalSeats.length,
      availableCount: availableSeats.length,
      occupiedCount: occupiedSeats.length
    });

  } catch (error) {
    console.error("Check availability error:", error);
    res.status(500).json({ error: "Failed to check seat availability" });
  }
});
//Checks each reservation
app.get('/api/reservations/:id', async (req, res) => {
  try {

    let reservation = await Reservation.findById(req.params.id)
      .populate('user', 'username email')
      .populate('lab', 'class number');
  
      if (!reservation) 
      {
        return res.status(404).json({ error: 'Reservation not found' });
      }
    res.json(reservation);
  } catch (error) {
    res.status(500).json({ error: error.message });
  }
});
//Edits reservation 
app.put('/api/reservations/:id', async (req, res) => {
  try {
    const timeRegex = /^(1[0-2]|0?[1-9]):(00|30) (AM|PM)$/i;

    function to24Hour(timeStr) {
      const [time, period] = timeStr.split(' ');
      const [hours] = time.split(':').map(Number);
      if (period.toUpperCase() === 'PM' && hours !== 12) return hours + 12;
      if (period.toUpperCase() === 'AM' && hours === 12) return 0;
      return hours;
    }

    const startHour = to24Hour(req.body.time_start); 
    const endHour = to24Hour(req.body.time_end);

    if (endHour <= startHour) {
      return res.status(400).json({ error: 'Minimum 1 hour duration required' });
    }

    // FIX: Proper date handling without timezone manipulation
    let processedDate = req.body.date;
    if (req.body.date) {
      if (typeof req.body.date === 'string' && /^\d{4}-\d{2}-\d{2}$/.test(req.body.date)) {
        // Create date as local midnight without timezone conversion
        const [year, month, day] = req.body.date.split('-').map(Number);
        processedDate = new Date(year, month - 1, day);
      } else {
        processedDate = new Date(req.body.date);
      }
    }

    const reservation = await Reservation.findByIdAndUpdate(
      req.params.id,
      {
        time_start: req.body.time_start,
        time_end: req.body.time_end,
        date: processedDate,
        lab: req.body.lab
      },
      { new: true }
    ).populate('user', 'username email')
     .populate('lab', 'class number');
    
    if (!reservation) {
      throw new Error('Reservation not found');
    }

    if (req.body.row && req.body.column) {
      await SeatList.updateOne(
        { reservation: req.params.id },
        { 
          $set: { 
            row: parseInt(req.body.row),
            column: parseInt(req.body.column)
          } 
        }
      );
    }

    res.json(reservation);
  } catch (error) {
    res.status(400).json({ error: error.message });
  }
});


//get seat lists 
app.get('/api/seat-lists', async (req, res) => {
  try {
    const seatLists = await SeatList.find()
      .populate({
        path: 'reservation',
        populate: [
          { path: 'user', select: 'firstName lastName email' },
          { path: 'lab', select: 'class number' }
        ]
      });
    res.json(seatLists);
  } catch (error) {
    res.status(500).json({ error: error.message });
  }
});
//get reservations (reservation list)
app.get('/api/seat-lists/reservation/:reservationId', async (req, res) => {
  try {
    const seats = await SeatList.find({ reservation: req.params.reservationId });
    res.json(seats);
  } catch (error) {
    res.status(500).json({ error: error.message });
  }
});

//Find available seats
app.get('/api/labs/:labId/available-seats', async (req, res) => {
  try {
    const { date, time_start, time_end } = req.query;
    
    const reservations = await Reservation.find({
      lab: req.params.labId,
      date: new Date(date),
      $or: [
        { time_start: { $lt: time_end }, time_end: { $gt: time_start } }
      ]
    });
    
    const occupiedSeats = [];
    
    for (const reservation of reservations) {
      const seats = await SeatList.find({ reservation: reservation._id });
      occupiedSeats.push(...seats.map(seat => ({ row: seat.row, column: seat.column })));
    }

    
    const totalSeats = [];
    for (let row = 1; row <= 7; row++) {
      for (let col = 1; col <= 5; col++) {
        totalSeats.push({ row, column: col });
      }
    }
    
    const availableSeats = totalSeats.filter(seat => 
      !occupiedSeats.some(occupied => 
        occupied.row === seat.row && occupied.column === seat.column
      )
    );
    
    res.json({ availableSeats, occupiedSeats });
  } catch (error) {
    res.status(500).json({ error: error.message });
  }
});

// get users  reservations
app.get('/api/users/:userId/reservations', async (req, res) => {
  try {
    const reservations = await Reservation.find({ user: req.params.userId })
      .populate('lab', 'class number')
      .sort({ date: -1, time_start: 1 });
    
    res.json(reservations);
  } catch (error) {
    res.status(500).json({ error: error.message });
  }
});

// start server with MongoDB conenction
connectDB().then(() => {
  app.listen(port, () => {
    console.log(`Server is running on http://localhost:${port}`);
  }).on('error', err => console.log('Server startup failed:', err));
});

module.exports = app;