const mongoose = require('mongoose');
const Schema = mongoose.Schema;

const labSchema = new Schema({
    class:{
        type: String,
        required: true,
    },
    number:{
        type: Number,
        required: true,
        unique: true,
    },
}, {timestamps: true});

module.exports = mongoose.model('Lab', labSchema);