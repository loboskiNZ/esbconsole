"use strict"

const EventEmitter = require('events').EventEmitter;
const util = require('util');

function DummyDriver(deviceId, options) {
    this.universe = Buffer.alloc(512, 0);
    EventEmitter.call(this);
}

util.inherits(DummyDriver, EventEmitter);

DummyDriver.prototype.init = function(cb) {
    if (cb) cb();
}

DummyDriver.prototype.start = function(cb) {
    if (cb) cb();
}

DummyDriver.prototype.stop = function(cb) {
    if (cb) cb();
}

DummyDriver.prototype.close = function(cb) {
    if (cb) cb();
}

DummyDriver.prototype.update = function(universe) {
    this.universe = universe;
    // Emit update so DMX library knows, but we won't log it in our own code
    this.emit('update', universe);
}

DummyDriver.prototype.updateAll = function(universe) {
    this.universe = universe;
    this.emit('update', universe);
}

DummyDriver.prototype.get = function(channel) {
    return this.universe[channel] || 0;
}

module.exports = DummyDriver;
