//checks if user had  previously logged in(had remember me enabled)
exports.checkLoggedIn = (req, res, next) => {
    if(req.session && req.session.user){
        next(); //allows them to access anything 
    }
    else{
        res.redirect('/login'); //If user already logged out prevent them form accessing anything else 
    }
}
//Allows user who has cookies to bypass having to login again
exports.bypassLogin = (req, res, next) =>{
    if(req.session && req.session.user){ //If user has cookies instantly redirect them to their page without the need to login
        if(req.session.user.role === 'Lab Technician')
            res.redirect(`/dashboard/technician?username=${encodeURIComponent(req.session.user.username)}`);
        else if(req.session.user.role === 'Student')
            res.redirect(`/dashboard/student?username=${encodeURIComponent(req.session.user.username)}`);
    }
    else{
        next(); //If not logged in proceed to normal steps 
    }
}
//Checks if a user is a lab technician(cannot be directly created unless by admin)
exports.checkLabTech = (req, res, next) => {
    if(req.session && req.session.user && req.session.user.role === 'Lab Technician'){
        next();
    }
    else{
        res.redirect('/login');
    }
}
//Checks if a user is a student
exports.checkStudent = (req, res, next) => {
    if(req.session && req.session.user && req.session.user.role === 'Student'){
        next();
    }
    else{
        res.redirect('/login');
    }
}
//Checks if a user is an admin(cannot be directly created)
exports.checkAdmin = (req, res, next) => {
  if (req.session && req.session.user && req.session.user.role === 'Admin') {
    next();
  } else {
    res.redirect('/login');
  }
};