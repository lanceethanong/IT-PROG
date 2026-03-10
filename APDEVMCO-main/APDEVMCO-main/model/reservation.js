const mongoose = require('mongoose');
const Schema = mongoose.Schema;

const reservationSchema = new Schema({
    time_start:{
        type: String,
        required: true,
    },
    time_end:{
        type: String,
        required: true,
    },
    user:{
        type: mongoose.Schema.Types.ObjectId,
        ref: "User",
        required: true,
    },
    lab:{
        type: mongoose.Schema.Types.ObjectId,
        ref: "Lab",
        required: true,
    },
    date:{
        type: Date,
        required: true,
    },
    anonymity:{
        type: Boolean,
        required: true,
    },
    status:{
        type: String,
        enum:['Scheduled', 'Cancelled', 'In Progress', 'Completed'],
        default: 'Scheduled',
    },
}, {timestamps: true});

module.exports = mongoose.model('Reservation', reservationSchema);