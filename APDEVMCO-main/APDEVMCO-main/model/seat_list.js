const mongoose = require('mongoose');
const Schema = mongoose.Schema;

const seat_listSchema = new Schema({
    reservation:{
        type: mongoose.Schema.Types.ObjectId,
        ref: "Reservation",
    },
    row:{
        type: Number,
        required: true,
    },
    column:{
        type: Number,
        required: true,
    }
}, {timestamps: true});

module.exports = mongoose.model('seat_list', seat_listSchema);