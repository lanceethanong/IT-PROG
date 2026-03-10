const mongoose = require('mongoose');
const Schema = mongoose.Schema;

const error_logSchema = new Schema({
    message: { type: String, required: true },
    stack: { type: String },
    source: { type: String },
    timestamp: { type: Date, default: Date.now },
    user: { type: String }
});

module.exports = mongoose.model('error_log', error_logSchema);