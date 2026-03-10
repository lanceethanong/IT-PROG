const mongoose = require('mongoose');

const uri = 'mongodb://localhost:27017/lab_res_db';

module.exports = () => mongoose.connect(uri);
