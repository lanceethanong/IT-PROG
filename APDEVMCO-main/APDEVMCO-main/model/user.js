const mongoose = require('mongoose');
const Schema = mongoose.Schema;

const userSchema = new Schema({
    email:{
        type: String,
        required: true,
        unique: true, 
        lowercase: true,
    },
    username:{
        type: String, 
        required: true,
        unique: true,
    },
    description: {
    type: String,
    default: ''
  },
    remember:{
        type: Boolean,
        default: false,
    }, 
    password:{
        type: String,
        required: true,
    },
    picture: {
    type: String,
    required: true,
    default: 'picture.jpg'
  },
    role:{
        type: String,
        enum: ['Student', 'Lab Technician','Admin'],
    },
}, {timestamps: true});

module.exports = mongoose.model('User', userSchema);