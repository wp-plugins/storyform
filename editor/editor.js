(function e(t,n,r){function s(o,u){if(!n[o]){if(!t[o]){var a=typeof require=="function"&&require;if(!u&&a)return a(o,!0);if(i)return i(o,!0);var f=new Error("Cannot find module '"+o+"'");throw f.code="MODULE_NOT_FOUND",f}var l=n[o]={exports:{}};t[o][0].call(l.exports,function(e){var n=t[o][1][e];return s(n?n:e)},l,l.exports,e,t,n,r)}return n[o].exports}var i=typeof require=="function"&&require;for(var o=0;o<r.length;o++)s(r[o]);return s})({1:[function(require,module,exports){
(function (global){
"use strict";

var _createClass = (function () { function defineProperties(target, props) { for (var key in props) { var prop = props[key]; prop.configurable = true; if (prop.value) prop.writable = true; } Object.defineProperties(target, props); } return function (Constructor, protoProps, staticProps) { if (protoProps) defineProperties(Constructor.prototype, protoProps); if (staticProps) defineProperties(Constructor, staticProps); return Constructor; }; })();

var _inherits = function (subClass, superClass) { if (typeof superClass !== "function" && superClass !== null) { throw new TypeError("Super expression must either be null or a function, not " + typeof superClass); } subClass.prototype = Object.create(superClass && superClass.prototype, { constructor: { value: subClass, enumerable: false, writable: true, configurable: true } }); if (superClass) subClass.__proto__ = superClass; };

var _classCallCheck = function (instance, Constructor) { if (!(instance instanceof Constructor)) { throw new TypeError("Cannot call a class as a function"); } };

var mixin = require("./object").mixin;

var eventMixin = require("./event-utils").eventMixin;

global.Debug && (global.Debug.setNonUserCodeExceptions = true);

var ListenerType = (function (_mixin) {
    function ListenerType() {
        _classCallCheck(this, ListenerType);

        if (_mixin != null) {
            _mixin.apply(this, arguments);
        }
    }

    _inherits(ListenerType, _mixin);

    return ListenerType;
})(mixin(eventMixin));

ListenerType.supportedForProcessing = false;

var promiseEventListeners = new ListenerType();
// make sure there is a listeners collection so that we can do a more trivial check below
promiseEventListeners._listeners = {};
var errorET = "error";
var canceledName = "Canceled";
var tagWithStack = false;
var tag = {
    promise: 1,
    thenPromise: 2,
    errorPromise: 4,
    exceptionPromise: 8,
    completePromise: 16 };
tag.all = tag.promise | tag.thenPromise | tag.errorPromise | tag.exceptionPromise | tag.completePromise;

//
// Global error counter, for each error which enters the system we increment this once and then
// the error number travels with the error as it traverses the tree of potential handlers.
//
// When someone has registered to be told about errors (Promise.callonerror) promises
// which are in error will get tagged with a ._errorId field. This tagged field is the
// contract by which nested promises with errors will be identified as chaining for the
// purposes of the callonerror semantics. If a nested promise in error is encountered without
// a ._errorId it will be assumed to be foreign and treated as an interop boundary and
// a new error id will be minted.
//
var error_number = 1;

//
// The state machine has a interesting hiccup in it with regards to notification, in order
// to flatten out notification and avoid recursion for synchronous completion we have an
// explicit set of *_notify states which are responsible for notifying their entire tree
// of children. They can do this because they know that immediate children are always
// ThenPromise instances and we can therefore reach into their state to access the
// _listeners collection.
//
// So, what happens is that a Promise will be fulfilled through the _completed or _error
// messages at which point it will enter a *_notify state and be responsible for to move
// its children into an (as appropriate) success or error state and also notify that child's
// listeners of the state transition, until leaf notes are reached.
//

var state_created, // -> working
state_working, // -> error | error_notify | success | success_notify | canceled | waiting
state_waiting, // -> error | error_notify | success | success_notify | waiting_canceled
state_waiting_canceled, // -> error | error_notify | success | success_notify | canceling
state_canceled, // -> error | error_notify | success | success_notify | canceling
state_canceling, // -> error_notify
state_success_notify, // -> success
state_success, // -> .
state_error_notify, // -> error
state_error; // -> .

// Noop function, used in the various states to indicate that they don't support a given
// message. Named with the somewhat cute name '_' because it reads really well in the states.

function _() {}

// Initial state
//
state_created = {
    name: "created",
    enter: function enter(promise) {
        promise._setState(state_working);
    },
    cancel: _,
    done: _,
    then: _,
    _completed: _,
    _error: _,
    _notify: _,
    _progress: _,
    _setCompleteValue: _,
    _setErrorValue: _
};

// Ready state, waiting for a message (completed/error/progress), able to be canceled
//
state_working = {
    name: "working",
    enter: _,
    cancel: function cancel(promise) {
        promise._setState(state_canceled);
    },
    done: done,
    then: then,
    _completed: completed,
    _error: error,
    _notify: _,
    _progress: progress,
    _setCompleteValue: setCompleteValue,
    _setErrorValue: setErrorValue
};

// Waiting state, if a promise is completed with a value which is itself a promise
// (has a then() method) it signs up to be informed when that child promise is
// fulfilled at which point it will be fulfilled with that value.
//
state_waiting = {
    name: "waiting",
    enter: function enter(promise) {
        var waitedUpon = promise._value;
        var error = (function (_error) {
            var _errorWrapper = function error(_x) {
                return _error.apply(this, arguments);
            };

            _errorWrapper.toString = function () {
                return _error.toString();
            };

            return _errorWrapper;
        })(function (value) {
            if (waitedUpon._errorId) {
                promise._chainedError(value, waitedUpon);
            } else {
                // Because this is an interop boundary we want to indicate that this
                //  error has been handled by the promise infrastructure before we
                //  begin a new handling chain.
                //
                callonerror(promise, value, detailsForHandledError, waitedUpon, error);
                promise._error(value);
            }
        });
        error.handlesOnError = true;
        waitedUpon.then(promise._completed.bind(promise), error, promise._progress.bind(promise));
    },
    cancel: function cancel(promise) {
        promise._setState(state_waiting_canceled);
    },
    done: done,
    then: then,
    _completed: completed,
    _error: error,
    _notify: _,
    _progress: progress,
    _setCompleteValue: setCompleteValue,
    _setErrorValue: setErrorValue
};

// Waiting canceled state, when a promise has been in a waiting state and receives a
// request to cancel its pending work it will forward that request to the child promise
// and then waits to be informed of the result. This promise moves itself into the
// canceling state but understands that the child promise may instead push it to a
// different state.
//
state_waiting_canceled = {
    name: "waiting_canceled",
    enter: function enter(promise) {
        // Initiate a transition to canceling. Triggering a cancel on the promise
        // that we are waiting upon may result in a different state transition
        // before the state machine pump runs again.
        promise._setState(state_canceling);
        var waitedUpon = promise._value;
        if (waitedUpon.cancel) {
            waitedUpon.cancel();
        }
    },
    cancel: _,
    done: done,
    then: then,
    _completed: completed,
    _error: error,
    _notify: _,
    _progress: progress,
    _setCompleteValue: setCompleteValue,
    _setErrorValue: setErrorValue
};

// Canceled state, moves to the canceling state and then tells the promise to do
// whatever it might need to do on cancelation.
//
state_canceled = {
    name: "canceled",
    enter: function enter(promise) {
        // Initiate a transition to canceling. The _cancelAction may change the state
        // before the state machine pump runs again.
        promise._setState(state_canceling);
        promise._cancelAction();
    },
    cancel: _,
    done: done,
    then: then,
    _completed: completed,
    _error: error,
    _notify: _,
    _progress: progress,
    _setCompleteValue: setCompleteValue,
    _setErrorValue: setErrorValue
};

// Canceling state, commits to the promise moving to an error state with an error
// object whose 'name' and 'message' properties contain the string "Canceled"
//
state_canceling = {
    name: "canceling",
    enter: function enter(promise) {
        var error = new Error(canceledName);
        error.name = error.message;
        promise._value = error;
        promise._setState(state_error_notify);
    },
    cancel: _,
    done: _,
    then: _,
    _completed: _,
    _error: _,
    _notify: _,
    _progress: _,
    _setCompleteValue: _,
    _setErrorValue: _
};

// Success notify state, moves a promise to the success state and notifies all children
//
state_success_notify = {
    name: "complete_notify",
    enter: function enter(promise) {
        promise.done = CompletePromise.prototype.done;
        promise.then = CompletePromise.prototype.then;
        if (promise._listeners) {
            var queue = [promise];
            var p;
            while (queue.length) {
                p = queue.pop();
                p._state._notify(p, queue);
            }
        }
        promise._setState(state_success);
    },
    cancel: _,
    done: null, /*error to get here */
    then: null, /*error to get here */
    _completed: _,
    _error: _,
    _notify: notifySuccess,
    _progress: _,
    _setCompleteValue: _,
    _setErrorValue: _
};

// Success state, moves a promise to the success state and does NOT notify any children.
// Some upstream promise is owning the notification pass.
//
state_success = {
    name: "success",
    enter: function enter(promise) {
        promise.done = CompletePromise.prototype.done;
        promise.then = CompletePromise.prototype.then;
        promise._cleanupAction();
    },
    cancel: _,
    done: null, /*error to get here */
    then: null, /*error to get here */
    _completed: _,
    _error: _,
    _notify: notifySuccess,
    _progress: _,
    _setCompleteValue: _,
    _setErrorValue: _
};

// Error notify state, moves a promise to the error state and notifies all children
//
state_error_notify = {
    name: "error_notify",
    enter: function enter(promise) {
        promise.done = ErrorPromise.prototype.done;
        promise.then = ErrorPromise.prototype.then;
        if (promise._listeners) {
            var queue = [promise];
            var p;
            while (queue.length) {
                p = queue.pop();
                p._state._notify(p, queue);
            }
        }
        promise._setState(state_error);
    },
    cancel: _,
    done: null, /*error to get here*/
    then: null, /*error to get here*/
    _completed: _,
    _error: _,
    _notify: notifyError,
    _progress: _,
    _setCompleteValue: _,
    _setErrorValue: _
};

// Error state, moves a promise to the error state and does NOT notify any children.
// Some upstream promise is owning the notification pass.
//
state_error = {
    name: "error",
    enter: function enter(promise) {
        promise.done = ErrorPromise.prototype.done;
        promise.then = ErrorPromise.prototype.then;
        promise._cleanupAction();
    },
    cancel: _,
    done: null, /*error to get here*/
    then: null, /*error to get here*/
    _completed: _,
    _error: _,
    _notify: notifyError,
    _progress: _,
    _setCompleteValue: _,
    _setErrorValue: _
};

//
// The statemachine implementation follows a very particular pattern, the states are specified
// as static stateless bags of functions which are then indirected through the state machine
// instance (a Promise). As such all of the functions on each state have the promise instance
// passed to them explicitly as a parameter and the Promise instance members do a little
// dance where they indirect through the state and insert themselves in the argument list.
//
// We could instead call directly through the promise states however then every caller
// would have to remember to do things like pumping the state machine to catch state transitions.
//

var PromiseStateMachine = (function () {
    function PromiseStateMachine() {
        _classCallCheck(this, PromiseStateMachine);

        this._listeners = null;
        this._nextState = null;
        this._state = null;
        this._value = null;
    }

    PromiseStateMachine.prototype.cancel = function cancel() {
        /// <signature helpKeyword="PromiseStateMachine.cancel">
        /// <summary locid="PromiseStateMachine.cancel">
        /// Attempts to cancel the fulfillment of a promised value. If the promise hasn't
        /// already been fulfilled and cancellation is supported, the promise enters
        /// the error state with a value of Error("Canceled").
        /// </summary>
        /// </signature>
        this._state.cancel(this);
        this._run();
    };

    PromiseStateMachine.prototype.done = function done(onComplete, onError, onProgress) {
        /// <signature helpKeyword=".PromiseStateMachine.done">
        /// <summary locid=".PromiseStateMachine.done">
        /// Allows you to specify the work to be done on the fulfillment of the promised value,
        /// the error handling to be performed if the promise fails to fulfill
        /// a value, and the handling of progress notifications along the way.
        ///
        /// After the handlers have finished executing, this function throws any error that would have been returned
        /// from then() as a promise in the error state.
        /// </summary>
        /// <param name="onComplete" type="Function" locid=".PromiseStateMachine.done_p:onComplete">
        /// The function to be called if the promise is fulfilled successfully with a value.
        /// The fulfilled value is passed as the single argument. If the value is null,
        /// the fulfilled value is returned. The value returned
        /// from the function becomes the fulfilled value of the promise returned by
        /// then(). If an exception is thrown while executing the function, the promise returned
        /// by then() moves into the error state.
        /// </param>
        /// <param name="onError" type="Function" optional="true" locid=".PromiseStateMachine.done_p:onError">
        /// The function to be called if the promise is fulfilled with an error. The error
        /// is passed as the single argument. If it is null, the error is forwarded.
        /// The value returned from the function is the fulfilled value of the promise returned by then().
        /// </param>
        /// <param name="onProgress" type="Function" optional="true" locid=".PromiseStateMachine.done_p:onProgress">
        /// the function to be called if the promise reports progress. Data about the progress
        /// is passed as the single argument. Promises are not required to support
        /// progress.
        /// </param>
        /// </signature>
        this._state.done(this, onComplete, onError, onProgress);
    };

    PromiseStateMachine.prototype.then = function then(onComplete, onError, onProgress) {
        /// <signature helpKeyword=".PromiseStateMachine.then">
        /// <summary locid=".PromiseStateMachine.then">
        /// Allows you to specify the work to be done on the fulfillment of the promised value,
        /// the error handling to be performed if the promise fails to fulfill
        /// a value, and the handling of progress notifications along the way.
        /// </summary>
        /// <param name="onComplete" type="Function" locid=".PromiseStateMachine.then_p:onComplete">
        /// The function to be called if the promise is fulfilled successfully with a value.
        /// The value is passed as the single argument. If the value is null, the value is returned.
        /// The value returned from the function becomes the fulfilled value of the promise returned by
        /// then(). If an exception is thrown while this function is being executed, the promise returned
        /// by then() moves into the error state.
        /// </param>
        /// <param name="onError" type="Function" optional="true" locid=".PromiseStateMachine.then_p:onError">
        /// The function to be called if the promise is fulfilled with an error. The error
        /// is passed as the single argument. If it is null, the error is forwarded.
        /// The value returned from the function becomes the fulfilled value of the promise returned by then().
        /// </param>
        /// <param name="onProgress" type="Function" optional="true" locid=".PromiseStateMachine.then_p:onProgress">
        /// The function to be called if the promise reports progress. Data about the progress
        /// is passed as the single argument. Promises are not required to support
        /// progress.
        /// </param>
        /// <returns type=".Promise" locid=".PromiseStateMachine.then_returnValue">
        /// The promise whose value is the result of executing the complete or
        /// error function.
        /// </returns>
        /// </signature>
        return this._state.then(this, onComplete, onError, onProgress);
    };

    PromiseStateMachine.prototype._chainedError = function _chainedError(value, context) {
        var result = this._state._error(this, value, detailsForChainedError, context);
        this._run();
        return result;
    };

    PromiseStateMachine.prototype._completed = function _completed(value) {
        var result = this._state._completed(this, value);
        this._run();
        return result;
    };

    PromiseStateMachine.prototype._error = function _error(value) {
        var result = this._state._error(this, value, detailsForError);
        this._run();
        return result;
    };

    PromiseStateMachine.prototype._progress = function _progress(value) {
        this._state._progress(this, value);
    };

    PromiseStateMachine.prototype._setState = function _setState(state) {
        this._nextState = state;
    };

    PromiseStateMachine.prototype._setCompleteValue = function _setCompleteValue(value) {
        this._state._setCompleteValue(this, value);
        this._run();
    };

    PromiseStateMachine.prototype._setChainedErrorValue = function _setChainedErrorValue(value, context) {
        var result = this._state._setErrorValue(this, value, detailsForChainedError, context);
        this._run();
        return result;
    };

    PromiseStateMachine.prototype._setExceptionValue = function _setExceptionValue(value) {
        var result = this._state._setErrorValue(this, value, detailsForException);
        this._run();
        return result;
    };

    PromiseStateMachine.prototype._run = function _run() {
        while (this._nextState) {
            this._state = this._nextState;
            this._nextState = null;
            this._state.enter(this);
        }
    };

    return PromiseStateMachine;
})();

PromiseStateMachine.supportedForProcessing = false;

//
// Implementations of shared state machine code.
//

function completed(promise, value) {
    var targetState;
    if (value && typeof value === "object" && typeof value.then === "function") {
        targetState = state_waiting;
    } else {
        targetState = state_success_notify;
    }
    promise._value = value;
    promise._setState(targetState);
}
function createErrorDetails(exception, error, promise, id, parent, handler) {
    return {
        exception: exception,
        error: error,
        promise: promise,
        handler: handler,
        id: id,
        parent: parent
    };
}
function detailsForHandledError(promise, errorValue, context, handler) {
    var exception = context._isException;
    var errorId = context._errorId;
    return createErrorDetails(exception ? errorValue : null, exception ? null : errorValue, promise, errorId, context, handler);
}
function detailsForChainedError(promise, errorValue, context) {
    var exception = context._isException;
    var errorId = context._errorId;
    setErrorInfo(promise, errorId, exception);
    return createErrorDetails(exception ? errorValue : null, exception ? null : errorValue, promise, errorId, context);
}
function detailsForError(promise, errorValue) {
    var errorId = ++error_number;
    setErrorInfo(promise, errorId);
    return createErrorDetails(null, errorValue, promise, errorId);
}
function detailsForException(promise, exceptionValue) {
    var errorId = ++error_number;
    setErrorInfo(promise, errorId, true);
    return createErrorDetails(exceptionValue, null, promise, errorId);
}
function done(promise, onComplete, onError, onProgress) {
    pushListener(promise, { c: onComplete, e: onError, p: onProgress });
}
function error(promise, value, onerrorDetails, context) {
    promise._value = value;
    callonerror(promise, value, onerrorDetails, context);
    promise._setState(state_error_notify);
}
function notifySuccess(promise, queue) {
    var value = promise._value;
    var listeners = promise._listeners;
    if (!listeners) {
        return;
    }
    promise._listeners = null;
    var i, len;
    for (i = 0, len = Array.isArray(listeners) ? listeners.length : 1; i < len; i++) {
        var listener = len === 1 ? listeners : listeners[i];
        var onComplete = listener.c;
        var target = listener.promise;
        if (target) {
            try {
                target._setCompleteValue(onComplete ? onComplete(value) : value);
            } catch (ex) {
                target._setExceptionValue(ex);
            }
            if (target._state !== state_waiting && target._listeners) {
                queue.push(target);
            }
        } else {
            CompletePromise.prototype.done.call(promise, onComplete);
        }
    }
}
function notifyError(promise, queue) {
    var value = promise._value;
    var listeners = promise._listeners;
    if (!listeners) {
        return;
    }
    promise._listeners = null;
    var i, len;
    for (i = 0, len = Array.isArray(listeners) ? listeners.length : 1; i < len; i++) {
        var listener = len === 1 ? listeners : listeners[i];
        var onError = listener.e;
        var target = listener.promise;
        if (target) {
            try {
                if (onError) {
                    if (!onError.handlesOnError) {
                        callonerror(target, value, detailsForHandledError, promise, onError);
                    }
                    target._setCompleteValue(onError(value));
                } else {
                    target._setChainedErrorValue(value, promise);
                }
            } catch (ex) {
                target._setExceptionValue(ex);
            }
            if (target._state !== state_waiting && target._listeners) {
                queue.push(target);
            }
        } else {
            ErrorPromise.prototype.done.call(promise, null, onError);
        }
    }
}
function callonerror(promise, value, onerrorDetailsGenerator, context, handler) {
    if (promiseEventListeners._listeners[errorET]) {
        if (value instanceof Error && value.message === canceledName) {
            return;
        }
        promiseEventListeners.dispatchEvent(errorET, onerrorDetailsGenerator(promise, value, context, handler));
    }
}
function progress(promise, value) {
    var listeners = promise._listeners;
    if (listeners) {
        var i, len;
        for (i = 0, len = Array.isArray(listeners) ? listeners.length : 1; i < len; i++) {
            var listener = len === 1 ? listeners : listeners[i];
            var onProgress = listener.p;
            if (onProgress) {
                try {
                    onProgress(value);
                } catch (ex) {}
            }
            if (!(listener.c || listener.e) && listener.promise) {
                listener.promise._progress(value);
            }
        }
    }
}
function pushListener(promise, listener) {
    var listeners = promise._listeners;
    if (listeners) {
        // We may have either a single listener (which will never be wrapped in an array)
        // or 2+ listeners (which will be wrapped). Since we are now adding one more listener
        // we may have to wrap the single listener before adding the second.
        listeners = Array.isArray(listeners) ? listeners : [listeners];
        listeners.push(listener);
    } else {
        listeners = listener;
    }
    promise._listeners = listeners;
}
// The difference beween setCompleteValue()/setErrorValue() and complete()/error() is that setXXXValue() moves
// a promise directly to the success/error state without starting another notification pass (because one
// is already ongoing).
function setErrorInfo(promise, errorId, isException) {
    promise._isException = isException || false;
    promise._errorId = errorId;
}
function setErrorValue(promise, value, onerrorDetails, context) {
    promise._value = value;
    callonerror(promise, value, onerrorDetails, context);
    promise._setState(state_error);
}
function setCompleteValue(promise, value) {
    var targetState;
    if (value && typeof value === "object" && typeof value.then === "function") {
        targetState = state_waiting;
    } else {
        targetState = state_success;
    }
    promise._value = value;
    promise._setState(targetState);
}
function then(promise, onComplete, onError, onProgress) {
    var result = new ThenPromise(promise);
    pushListener(promise, { promise: result, c: onComplete, e: onError, p: onProgress });
    return result;
}

//
// Internal implementation detail promise, ThenPromise is created when a promise needs
// to be returned from a then() method.
//

var ThenPromise = (function (_PromiseStateMachine) {
    function ThenPromise(creator) {
        _classCallCheck(this, ThenPromise);

        if (tagWithStack && (tagWithStack === true || tagWithStack & tag.thenPromise)) {
            this._stack = Promise._getStack();
        }

        this._creator = null;
        this._creator = creator;
        this._setState(state_created);
        this._run();
    }

    _inherits(ThenPromise, _PromiseStateMachine);

    ThenPromise.prototype._cancelAction = function _cancelAction() {
        if (this._creator) {
            this._creator.cancel();
        }
    };

    ThenPromise.prototype._cleanupAction = function _cleanupAction() {
        this._creator = null;
    };

    return ThenPromise;
})(PromiseStateMachine);

ThenPromise.supportedForProcessing = false;

//
// Slim promise implementations for already completed promises, these are created
// under the hood on synchronous completion paths as well as by Promise.wrap
// and Promise.wrapError.
//

var ErrorPromise = (function () {
    function ErrorPromise(value) {
        _classCallCheck(this, ErrorPromise);

        if (tagWithStack && (tagWithStack === true || tagWithStack & tag.errorPromise)) {
            this._stack = Promise._getStack();
        }

        this._value = value;
        callonerror(this, value, detailsForError);
    }

    ErrorPromise.prototype.cancel = function cancel() {};

    ErrorPromise.prototype.done = function done(unused, onError) {
        /// <signature helpKeyword=".PromiseStateMachine.done">
        /// <summary locid=".PromiseStateMachine.done">
        /// Allows you to specify the work to be done on the fulfillment of the promised value,
        /// the error handling to be performed if the promise fails to fulfill
        /// a value, and the handling of progress notifications along the way.
        ///
        /// After the handlers have finished executing, this function throws any error that would have been returned
        /// from then() as a promise in the error state.
        /// </summary>
        /// <param name='onComplete' type='Function' locid=".PromiseStateMachine.done_p:onComplete">
        /// The function to be called if the promise is fulfilled successfully with a value.
        /// The fulfilled value is passed as the single argument. If the value is null,
        /// the fulfilled value is returned. The value returned
        /// from the function becomes the fulfilled value of the promise returned by
        /// then(). If an exception is thrown while executing the function, the promise returned
        /// by then() moves into the error state.
        /// </param>
        /// <param name='onError' type='Function' optional='true' locid=".PromiseStateMachine.done_p:onError">
        /// The function to be called if the promise is fulfilled with an error. The error
        /// is passed as the single argument. If it is null, the error is forwarded.
        /// The value returned from the function is the fulfilled value of the promise returned by then().
        /// </param>
        /// <param name='onProgress' type='Function' optional='true' locid=".PromiseStateMachine.done_p:onProgress">
        /// the function to be called if the promise reports progress. Data about the progress
        /// is passed as the single argument. Promises are not required to support
        /// progress.
        /// </param>
        /// </signature>
        var value = this._value;
        if (onError) {
            try {
                if (!onError.handlesOnError) {
                    callonerror(null, value, detailsForHandledError, this, onError);
                }
                var result = onError(value);
                if (result && typeof result === "object" && typeof result.done === "function") {
                    // If a promise is returned we need to wait on it.
                    result.done();
                }
                return;
            } catch (ex) {
                value = ex;
            }
        }
        if (value instanceof Error && value.message === canceledName) {
            // suppress cancel
            return;
        }
        // force the exception to be thrown asyncronously to avoid any try/catch blocks
        //
        setImmediate(function () {
            throw value;
        });
    };

    ErrorPromise.prototype.then = function then(unused, onError) {
        /// <signature helpKeyword=".PromiseStateMachine.then">
        /// <summary locid=".PromiseStateMachine.then">
        /// Allows you to specify the work to be done on the fulfillment of the promised value,
        /// the error handling to be performed if the promise fails to fulfill
        /// a value, and the handling of progress notifications along the way.
        /// </summary>
        /// <param name='onComplete' type='Function' locid=".PromiseStateMachine.then_p:onComplete">
        /// The function to be called if the promise is fulfilled successfully with a value.
        /// The value is passed as the single argument. If the value is null, the value is returned.
        /// The value returned from the function becomes the fulfilled value of the promise returned by
        /// then(). If an exception is thrown while this function is being executed, the promise returned
        /// by then() moves into the error state.
        /// </param>
        /// <param name='onError' type='Function' optional='true' locid=".PromiseStateMachine.then_p:onError">
        /// The function to be called if the promise is fulfilled with an error. The error
        /// is passed as the single argument. If it is null, the error is forwarded.
        /// The value returned from the function becomes the fulfilled value of the promise returned by then().
        /// </param>
        /// <param name='onProgress' type='Function' optional='true' locid=".PromiseStateMachine.then_p:onProgress">
        /// The function to be called if the promise reports progress. Data about the progress
        /// is passed as the single argument. Promises are not required to support
        /// progress.
        /// </param>
        /// <returns type=".Promise" locid=".PromiseStateMachine.then_returnValue">
        /// The promise whose value is the result of executing the complete or
        /// error function.
        /// </returns>
        /// </signature>

        // If the promise is already in a error state and no error handler is provided
        // we optimize by simply returning the promise instead of creating a new one.
        //
        if (!onError) {
            return this;
        }
        var result;
        var value = this._value;
        try {
            if (!onError.handlesOnError) {
                callonerror(null, value, detailsForHandledError, this, onError);
            }
            result = new CompletePromise(onError(value));
        } catch (ex) {
            // If the value throw from the error handler is the same as the value
            // provided to the error handler then there is no need for a new promise.
            //
            if (ex === value) {
                result = this;
            } else {
                result = new ExceptionPromise(ex);
            }
        }
        return result;
    };

    return ErrorPromise;
})();

ErrorPromise.supportedForProcessing = false;

var ExceptionPromise = (function (_ErrorPromise) {
    function ExceptionPromise(value) {
        _classCallCheck(this, ExceptionPromise);

        if (tagWithStack && (tagWithStack === true || tagWithStack & tag.exceptionPromise)) {
            this._stack = Promise._getStack();
        }

        this._value = value;
        callonerror(this, value, detailsForException);
    }

    _inherits(ExceptionPromise, _ErrorPromise);

    return ExceptionPromise;
})(ErrorPromise);

ExceptionPromise.supportedForProcessing = false;

var CompletePromise = (function () {
    function CompletePromise(value) {
        _classCallCheck(this, CompletePromise);

        if (tagWithStack && (tagWithStack === true || tagWithStack & tag.completePromise)) {
            this._stack = Promise._getStack();
        }

        if (value && typeof value === "object" && typeof value.then === "function") {
            var result = new ThenPromise(null);
            result._setCompleteValue(value);
            return result;
        }
        this._value = value;
    }

    CompletePromise.prototype.cancel = function cancel() {};

    CompletePromise.prototype.done = function done(onComplete) {
        /// <signature helpKeyword=".PromiseStateMachine.done">
        /// <summary locid=".PromiseStateMachine.done">
        /// Allows you to specify the work to be done on the fulfillment of the promised value,
        /// the error handling to be performed if the promise fails to fulfill
        /// a value, and the handling of progress notifications along the way.
        ///
        /// After the handlers have finished executing, this function throws any error that would have been returned
        /// from then() as a promise in the error state.
        /// </summary>
        /// <param name='onComplete' type='Function' locid=".PromiseStateMachine.done_p:onComplete">
        /// The function to be called if the promise is fulfilled successfully with a value.
        /// The fulfilled value is passed as the single argument. If the value is null,
        /// the fulfilled value is returned. The value returned
        /// from the function becomes the fulfilled value of the promise returned by
        /// then(). If an exception is thrown while executing the function, the promise returned
        /// by then() moves into the error state.
        /// </param>
        /// <param name='onError' type='Function' optional='true' locid=".PromiseStateMachine.done_p:onError">
        /// The function to be called if the promise is fulfilled with an error. The error
        /// is passed as the single argument. If it is null, the error is forwarded.
        /// The value returned from the function is the fulfilled value of the promise returned by then().
        /// </param>
        /// <param name='onProgress' type='Function' optional='true' locid=".PromiseStateMachine.done_p:onProgress">
        /// the function to be called if the promise reports progress. Data about the progress
        /// is passed as the single argument. Promises are not required to support
        /// progress.
        /// </param>
        /// </signature>
        if (!onComplete) {
            return;
        }
        try {
            var result = onComplete(this._value);
            if (result && typeof result === "object" && typeof result.done === "function") {
                result.done();
            }
        } catch (ex) {
            // force the exception to be thrown asynchronously to avoid any try/catch blocks
            setImmediate(function () {
                throw ex;
            });
        }
    };

    CompletePromise.prototype.then = function then(onComplete) {
        /// <signature helpKeyword=".PromiseStateMachine.then">
        /// <summary locid=".PromiseStateMachine.then">
        /// Allows you to specify the work to be done on the fulfillment of the promised value,
        /// the error handling to be performed if the promise fails to fulfill
        /// a value, and the handling of progress notifications along the way.
        /// </summary>
        /// <param name='onComplete' type='Function' locid=".PromiseStateMachine.then_p:onComplete">
        /// The function to be called if the promise is fulfilled successfully with a value.
        /// The value is passed as the single argument. If the value is null, the value is returned.
        /// The value returned from the function becomes the fulfilled value of the promise returned by
        /// then(). If an exception is thrown while this function is being executed, the promise returned
        /// by then() moves into the error state.
        /// </param>
        /// <param name='onError' type='Function' optional='true' locid=".PromiseStateMachine.then_p:onError">
        /// The function to be called if the promise is fulfilled with an error. The error
        /// is passed as the single argument. If it is null, the error is forwarded.
        /// The value returned from the function becomes the fulfilled value of the promise returned by then().
        /// </param>
        /// <param name='onProgress' type='Function' optional='true' locid=".PromiseStateMachine.then_p:onProgress">
        /// The function to be called if the promise reports progress. Data about the progress
        /// is passed as the single argument. Promises are not required to support
        /// progress.
        /// </param>
        /// <returns type=".Promise" locid=".PromiseStateMachine.then_returnValue">
        /// The promise whose value is the result of executing the complete or
        /// error function.
        /// </returns>
        /// </signature>
        try {
            // If the value returned from the completion handler is the same as the value
            // provided to the completion handler then there is no need for a new promise.
            //
            var newValue = onComplete ? onComplete(this._value) : this._value;
            return newValue === this._value ? this : new CompletePromise(newValue);
        } catch (ex) {
            return new ExceptionPromise(ex);
        }
    };

    return CompletePromise;
})();

CompletePromise.supportedForProcessing = false;

//
// Promise is the user-creatable .Promise object.
//

function timeout(timeoutMS) {
    var id, iId;
    return new Promise(function (c) {
        if (timeoutMS) {
            id = setTimeout(c, timeoutMS);
        } else {
            iId = setImmediate(c);
        }
    }, function () {
        if (id) {
            clearTimeout(id);
        }
        if (iId) {
            clearImmediate(iId);
        }
    });
}

function timeoutWithPromise(timeout, promise) {
    var cancelPromise = function cancelPromise() {
        promise.cancel();
    };
    var cancelTimeout = function cancelTimeout() {
        timeout.cancel();
    };
    timeout.then(cancelPromise);
    promise.then(cancelTimeout, cancelTimeout);
    return promise;
}

var staticCanceledPromise;

var Promise = (function (_PromiseStateMachine2) {
    function Promise(init, oncancel) {
        _classCallCheck(this, Promise);

        /// <signature helpKeyword=".Promise">
        /// <summary locid=".Promise">
        /// A promise provides a mechanism to schedule work to be done on a value that
        /// has not yet been computed. It is a convenient abstraction for managing
        /// interactions with asynchronous APIs.
        /// </summary>
        /// <param name="init" type="Function" locid=".Promise_p:init">
        /// The function that is called during construction of the  promise. The function
        /// is given three arguments (complete, error, progress). Inside this function
        /// you should add event listeners for the notifications supported by this value.
        /// </param>
        /// <param name="oncancel" optional="true" locid=".Promise_p:oncancel">
        /// The function to call if a consumer of this promise wants
        /// to cancel its undone work. Promises are not required to
        /// support cancellation.
        /// </param>
        /// </signature>

        if (tagWithStack && (tagWithStack === true || tagWithStack & tag.promise)) {
            this._stack = Promise._getStack();
        }
        this._oncancel = null;
        this._oncancel = oncancel;
        this._setState(state_created);
        this._run();

        try {
            var complete = this._completed.bind(this);
            var error = this._error.bind(this);
            var progress = this._progress.bind(this);
            init(complete, error, progress);
        } catch (ex) {
            this._setExceptionValue(ex);
        }
    }

    _inherits(Promise, _PromiseStateMachine2);

    Promise.prototype._cancelAction = function _cancelAction() {
        if (this._oncancel) {
            try {
                this._oncancel();
            } catch (ex) {}
        }
    };

    Promise.prototype._cleanupAction = function _cleanupAction() {
        this._oncancel = null;
    };

    Promise.addEventListener = function addEventListener(eventType, listener, capture) {
        /// <signature helpKeyword=".Promise.addEventListener">
        /// <summary locid=".Promise.addEventListener">
        /// Adds an event listener to the control.
        /// </summary>
        /// <param name="eventType" locid=".Promise.addEventListener_p:eventType">
        /// The type (name) of the event.
        /// </param>
        /// <param name="listener" locid=".Promise.addEventListener_p:listener">
        /// The listener to invoke when the event is raised.
        /// </param>
        /// <param name="capture" locid=".Promise.addEventListener_p:capture">
        /// Specifies whether or not to initiate capture.
        /// </param>
        /// </signature>
        promiseEventListeners.addEventListener(eventType, listener, capture);
    };

    Promise.any = function any(values) {
        /// <signature helpKeyword=".Promise.any">
        /// <summary locid=".Promise.any">
        /// Returns a promise that is fulfilled when one of the input promises
        /// has been fulfilled.
        /// </summary>
        /// <param name="values" type="Array" locid=".Promise.any_p:values">
        /// An array that contains promise objects or objects whose property
        /// values include promise objects.
        /// </param>
        /// <returns type=".Promise" locid=".Promise.any_returnValue">
        /// A promise that on fulfillment yields the value of the input (complete or error).
        /// </returns>
        /// </signature>
        return new Promise(function (complete, error, progress) {
            var keys = Object.keys(values);
            var errors = Array.isArray(values) ? [] : {};
            if (keys.length === 0) {
                complete();
            }
            var canceled = 0;
            keys.forEach(function (key) {
                Promise.as(values[key]).then(function () {
                    complete({ key: key, value: values[key] });
                }, function (e) {
                    if (e instanceof Error && e.name === canceledName) {
                        if (++canceled === keys.length) {
                            complete(Promise.cancel);
                        }
                        return;
                    }
                    error({ key: key, value: values[key] });
                });
            });
        }, function () {
            var keys = Object.keys(values);
            keys.forEach(function (key) {
                var promise = Promise.as(values[key]);
                if (typeof promise.cancel === "function") {
                    promise.cancel();
                }
            });
        });
    };

    Promise.as = function as(value) {
        /// <signature helpKeyword=".Promise.as">
        /// <summary locid=".Promise.as">
        /// Returns a promise. If the object is already a promise it is returned;
        /// otherwise the object is wrapped in a promise.
        /// </summary>
        /// <param name="value" locid=".Promise.as_p:value">
        /// The value to be treated as a promise.
        /// </param>
        /// <returns type=".Promise" locid=".Promise.as_returnValue">
        /// A promise.
        /// </returns>
        /// </signature>
        if (value && typeof value === "object" && typeof value.then === "function") {
            return value;
        }
        return new CompletePromise(value);
    };

    Promise.dispatchEvent = function dispatchEvent(eventType, details) {
        /// <signature helpKeyword=".Promise.dispatchEvent">
        /// <summary locid=".Promise.dispatchEvent">
        /// Raises an event of the specified type and properties.
        /// </summary>
        /// <param name="eventType" locid=".Promise.dispatchEvent_p:eventType">
        /// The type (name) of the event.
        /// </param>
        /// <param name="details" locid=".Promise.dispatchEvent_p:details">
        /// The set of additional properties to be attached to the event object.
        /// </param>
        /// <returns type="Boolean" locid=".Promise.dispatchEvent_returnValue">
        /// Specifies whether preventDefault was called on the event.
        /// </returns>
        /// </signature>
        return promiseEventListeners.dispatchEvent(eventType, details);
    };

    Promise.is = function is(value) {
        /// <signature helpKeyword=".Promise.is">
        /// <summary locid=".Promise.is">
        /// Determines whether a value fulfills the promise contract.
        /// </summary>
        /// <param name="value" locid=".Promise.is_p:value">
        /// A value that may be a promise.
        /// </param>
        /// <returns type="Boolean" locid=".Promise.is_returnValue">
        /// true if the specified value is a promise, otherwise false.
        /// </returns>
        /// </signature>
        return value && typeof value === "object" && typeof value.then === "function";
    };

    Promise.join = function join(values) {
        /// <signature helpKeyword=".Promise.join">
        /// <summary locid=".Promise.join">
        /// Creates a promise that is fulfilled when all the values are fulfilled.
        /// </summary>
        /// <param name="values" type="Object" locid=".Promise.join_p:values">
        /// An object whose fields contain values, some of which may be promises.
        /// </param>
        /// <returns type=".Promise" locid=".Promise.join_returnValue">
        /// A promise whose value is an object with the same field names as those of the object in the values parameter, where
        /// each field value is the fulfilled value of a promise.
        /// </returns>
        /// </signature>
        return new Promise(function (complete, error, progress) {
            var keys = Object.keys(values);
            var errors = Array.isArray(values) ? [] : {};
            var results = Array.isArray(values) ? [] : {};
            var undefineds = 0;
            var pending = keys.length;
            var argDone = function argDone(key) {
                if (--pending === 0) {
                    var errorCount = Object.keys(errors).length;
                    if (errorCount === 0) {
                        complete(results);
                    } else {
                        var canceledCount = 0;
                        keys.forEach(function (key) {
                            var e = errors[key];
                            if (e instanceof Error && e.name === canceledName) {
                                canceledCount++;
                            }
                        });
                        if (canceledCount === errorCount) {
                            complete(Promise.cancel);
                        } else {
                            error(errors);
                        }
                    }
                } else {
                    progress({ Key: key, Done: true });
                }
            };
            keys.forEach(function (key) {
                var value = values[key];
                if (value === undefined) {
                    undefineds++;
                } else {
                    Promise.then(value, function (value) {
                        results[key] = value;argDone(key);
                    }, function (value) {
                        errors[key] = value;argDone(key);
                    });
                }
            });
            pending -= undefineds;
            if (pending === 0) {
                complete(results);
                return;
            }
        }, function () {
            Object.keys(values).forEach(function (key) {
                var promise = Promise.as(values[key]);
                if (typeof promise.cancel === "function") {
                    promise.cancel();
                }
            });
        });
    };

    Promise.removeEventListener = function removeEventListener(eventType, listener, capture) {
        /// <signature helpKeyword=".Promise.removeEventListener">
        /// <summary locid=".Promise.removeEventListener">
        /// Removes an event listener from the control.
        /// </summary>
        /// <param name='eventType' locid=".Promise.removeEventListener_eventType">
        /// The type (name) of the event.
        /// </param>
        /// <param name='listener' locid=".Promise.removeEventListener_listener">
        /// The listener to remove.
        /// </param>
        /// <param name='capture' locid=".Promise.removeEventListener_capture">
        /// Specifies whether or not to initiate capture.
        /// </param>
        /// </signature>
        promiseEventListeners.removeEventListener(eventType, listener, capture);
    };

    Promise.then = function then(value, onComplete, onError, onProgress) {
        /// <signature helpKeyword=".Promise.then">
        /// <summary locid=".Promise.then">
        /// A static version of the promise instance method then().
        /// </summary>
        /// <param name="value" locid=".Promise.then_p:value">
        /// the value to be treated as a promise.
        /// </param>
        /// <param name="onComplete" type="Function" locid=".Promise.then_p:complete">
        /// The function to be called if the promise is fulfilled with a value.
        /// If it is null, the promise simply
        /// returns the value. The value is passed as the single argument.
        /// </param>
        /// <param name="onError" type="Function" optional="true" locid=".Promise.then_p:error">
        /// The function to be called if the promise is fulfilled with an error. The error
        /// is passed as the single argument.
        /// </param>
        /// <param name="onProgress" type="Function" optional="true" locid=".Promise.then_p:progress">
        /// The function to be called if the promise reports progress. Data about the progress
        /// is passed as the single argument. Promises are not required to support
        /// progress.
        /// </param>
        /// <returns type=".Promise" locid=".Promise.then_returnValue">
        /// A promise whose value is the result of executing the provided complete function.
        /// </returns>
        /// </signature>
        return Promise.as(value).then(onComplete, onError, onProgress);
    };

    Promise.thenEach = function thenEach(values, onComplete, onError, onProgress) {
        /// <signature helpKeyword=".Promise.thenEach">
        /// <summary locid=".Promise.thenEach">
        /// Performs an operation on all the input promises and returns a promise
        /// that has the shape of the input and contains the result of the operation
        /// that has been performed on each input.
        /// </summary>
        /// <param name="values" locid=".Promise.thenEach_p:values">
        /// A set of values (which could be either an array or an object) of which some or all are promises.
        /// </param>
        /// <param name="onComplete" type="Function" locid=".Promise.thenEach_p:complete">
        /// The function to be called if the promise is fulfilled with a value.
        /// If the value is null, the promise returns the value.
        /// The value is passed as the single argument.
        /// </param>
        /// <param name="onError" type="Function" optional="true" locid=".Promise.thenEach_p:error">
        /// The function to be called if the promise is fulfilled with an error. The error
        /// is passed as the single argument.
        /// </param>
        /// <param name="onProgress" type="Function" optional="true" locid=".Promise.thenEach_p:progress">
        /// The function to be called if the promise reports progress. Data about the progress
        /// is passed as the single argument. Promises are not required to support
        /// progress.
        /// </param>
        /// <returns type=".Promise" locid=".Promise.thenEach_returnValue">
        /// A promise that is the result of calling Promise.join on the values parameter.
        /// </returns>
        /// </signature>
        var result = Array.isArray(values) ? [] : {};
        Object.keys(values).forEach(function (key) {
            result[key] = Promise.as(values[key]).then(onComplete, onError, onProgress);
        });
        return Promise.join(result);
    };

    Promise.timeout = (function (_timeout) {
        var _timeoutWrapper = function timeout(_x, _x2) {
            return _timeout.apply(this, arguments);
        };

        _timeoutWrapper.toString = function () {
            return _timeout.toString();
        };

        return _timeoutWrapper;
    })(function (time, promise) {
        /// <signature helpKeyword=".Promise.timeout">
        /// <summary locid=".Promise.timeout">
        /// Creates a promise that is fulfilled after a timeout.
        /// </summary>
        /// <param name="timeout" type="Number" optional="true" locid=".Promise.timeout_p:timeout">
        /// The timeout period in milliseconds. If this value is zero or not specified
        /// setImmediate is called, otherwise setTimeout is called.
        /// </param>
        /// <param name="promise" type="Promise" optional="true" locid=".Promise.timeout_p:promise">
        /// A promise that will be canceled if it doesn't complete before the
        /// timeout has expired.
        /// </param>
        /// <returns type=".Promise" locid=".Promise.timeout_returnValue">
        /// A promise that is completed asynchronously after the specified timeout.
        /// </returns>
        /// </signature>
        var to = timeout(time);
        return promise ? timeoutWithPromise(to, promise) : to;
    });

    Promise.wrap = function wrap(value) {
        /// <signature helpKeyword=".Promise.wrap">
        /// <summary locid=".Promise.wrap">
        /// Wraps a non-promise value in a promise. You can use this function if you need
        /// to pass a value to a function that requires a promise.
        /// </summary>
        /// <param name="value" locid=".Promise.wrap_p:value">
        /// Some non-promise value to be wrapped in a promise.
        /// </param>
        /// <returns type=".Promise" locid=".Promise.wrap_returnValue">
        /// A promise that is successfully fulfilled with the specified value
        /// </returns>
        /// </signature>
        return new CompletePromise(value);
    };

    Promise.wrapError = function wrapError(error) {
        /// <signature helpKeyword=".Promise.wrapError">
        /// <summary locid=".Promise.wrapError">
        /// Wraps a non-promise error value in a promise. You can use this function if you need
        /// to pass an error to a function that requires a promise.
        /// </summary>
        /// <param name="error" locid=".Promise.wrapError_p:error">
        /// A non-promise error value to be wrapped in a promise.
        /// </param>
        /// <returns type=".Promise" locid=".Promise.wrapError_returnValue">
        /// A promise that is in an error state with the specified value.
        /// </returns>
        /// </signature>
        return new ErrorPromise(error);
    };

    Promise._getStack = function _getStack() {
        if (global.Debug && Debug.debuggerEnabled) {
            try {
                throw new Error();
            } catch (e) {
                return e.stack;
            }
        }
    };

    // ADDED

    Promise.requestAnimationFrame = (function (_requestAnimationFrame) {
        var _requestAnimationFrameWrapper = function requestAnimationFrame() {
            return _requestAnimationFrame.apply(this, arguments);
        };

        _requestAnimationFrameWrapper.toString = function () {
            return _requestAnimationFrame.toString();
        };

        return _requestAnimationFrameWrapper;
    })(function () {
        return new Promise(function (c, e) {
            requestAnimationFrame(c);
        });
    });

    Promise.addEventPromiseListener = function addEventPromiseListener(obj, name, bubbles) {
        return new Promise(function (c, e) {
            obj.addEventListener(name, function fn(ev) {
                c({ event: ev, listener: fn });
            }, bubbles);
        });
    };

    Promise.order = function order(fns) {
        return next(0, fns);
    };

    // Runs the async function fn over each item in the values and returns a Promise with the array of returns

    Promise.map = function map(values, fn) {
        return _map(values, 0, [], fn);
    };

    // Runs the async function fn over each item in the values until a function returns
    // a true value.

    Promise.attemptEach = function attemptEach(values, fn) {
        return _attemptEach(values, 0, fn);
    }
    // ENDADDED
    ;

    _createClass(Promise, null, {
        cancel: {
            /// <field type=".Promise" helpKeyword=".Promise.cancel" locid=".Promise.cancel">
            /// Canceled promise value, can be returned from a promise completion handler
            /// to indicate cancelation of the promise chain.
            /// </field>

            get: function () {
                return staticCanceledPromise = staticCanceledPromise || new ErrorPromise(new ErrorFromName(canceledName));
            }
        },
        _veryExpensiveTagWithStack: {
            get: function () {
                return tagWithStack;
            },
            set: function (value) {
                tagWithStack = value;
            }
        }
    });

    return Promise;
})(PromiseStateMachine);

module.exports = Promise;

Promise.supportedForProcessing = false;
Promise._veryExpensiveTagWithStack_tag = tag;
Object.defineProperties(Promise, createEventProperties(errorET));

//ADDED
function next(index, fns) {
    return fns[index].then(function () {
        return next(index + 1, fns);
    });
}

function _map(items, index, results, fn) {
    if (items.length === index) {
        return Promise.wrap(results);
    }
    var item = items[index];
    return fn(item, index).then(function (result) {
        results.push(result);
        return _map(items, index + 1, results, fn);
    });
}

function _attemptEach(items, index, fn) {
    if (items.length === index) {
        return Promise.wrap(false);
    }
    var item = items[index];
    return fn(item, index).then(function (result) {
        if (result) {
            return Promise.wrap(result);
        }
        return _attemptEach(items, index + 1, fn);
    });
}

//ENDADDED

var SignalPromise = (function (_PromiseStateMachine3) {
    function SignalPromise(cancel) {
        _classCallCheck(this, SignalPromise);

        this._oncancel = cancel;
        this._setState(state_created);
        this._run();
    }

    _inherits(SignalPromise, _PromiseStateMachine3);

    SignalPromise.prototype._cancelAction = function _cancelAction() {
        this._oncancel && this._oncancel();
    };

    SignalPromise.prototype._cleanupAction = function _cleanupAction() {
        this._oncancel = null;
    };

    return SignalPromise;
})(PromiseStateMachine);

SignalPromise.supportedForProcessing = false;

var Signal = (function () {
    function Signal(oncancel) {
        _classCallCheck(this, Signal);

        this._promise = new SignalPromise(oncancel);
    }

    Signal.prototype.cancel = function cancel() {
        this._promise.cancel();
    };

    Signal.prototype.complete = function complete(value) {
        this._promise._completed(value);
    };

    Signal.prototype.error = function error(value) {
        this._promise._error(value);
    };

    Signal.prototype.progress = function progress(value) {
        this._promise._progress(value);
    };

    _createClass(Signal, {
        promise: {
            get: function () {
                return this._promise;
            }
        }
    });

    return Signal;
})();

Signal.supportedForProcessing = false;

function createEventProperty(name) {
    var eventPropStateName = "_on" + name + "state";

    return {
        get: function get() {
            var state = this[eventPropStateName];
            return state && state.userHandler;
        },
        set: function set(handler) {
            var state = this[eventPropStateName];
            if (handler) {
                if (!state) {
                    state = { wrapper: function wrapper(evt) {
                            return state.userHandler(evt);
                        }, userHandler: handler };
                    Object.defineProperty(this, eventPropStateName, { value: state, enumerable: false, writable: true, configurable: true });
                    this.addEventListener(name, state.wrapper, false);
                }
                state.userHandler = handler;
            } else if (state) {
                this.removeEventListener(name, state.wrapper, false);
                this[eventPropStateName] = null;
            }
        },
        enumerable: true
    };
}

function createEventProperties(events) {
    /// <signature helpKeyword="createEventProperties">
    /// <summary locid="createEventProperties">
    /// Creates an object that has one property for each name passed to the function.
    /// </summary>
    /// <param name="events" locid=".createEventProperties_p:events">
    /// A variable list of property names.
    /// </param>
    /// <returns type="Object" locid=".createEventProperties_returnValue">
    /// The object with the specified properties. The names of the properties are prefixed with 'on'.
    /// </returns>
    /// </signature>
    var props = {};
    for (var i = 0, len = arguments.length; i < len; i++) {
        var name = arguments[i];
        props["on" + name] = createEventProperty(name);
    }
    return props;
}

var ErrorFromName = (function (_Error) {
    function ErrorFromName(name, message) {
        _classCallCheck(this, ErrorFromName);

        /// <signature helpKeyword=".ErrorFromName">
        /// <summary locid=".ErrorFromName">
        /// Creates an Error object with the specified name and message properties.
        /// </summary>
        /// <param name="name" type="String" locid=".ErrorFromName_p:name">The name of this error. The name is meant to be consumed programmatically and should not be localized.</param>
        /// <param name="message" type="String" optional="true" locid=".ErrorFromName_p:message">The message for this error. The message is meant to be consumed by humans and should be localized.</param>
        /// <returns type="Error" locid=".ErrorFromName_returnValue">Error instance with .name and .message properties populated</returns>
        /// </signature>
        this.name = name;
        this.message = message || name;
    }

    _inherits(ErrorFromName, _Error);

    return ErrorFromName;
})(Error);

ErrorFromName.supportedForProcessing = false;

/// <signature helpKeyword=".PromiseStateMachine.cancel">
/// <summary locid=".PromiseStateMachine.cancel">
/// Attempts to cancel the fulfillment of a promised value. If the promise hasn't
/// already been fulfilled and cancellation is supported, the promise enters
/// the error state with a value of Error("Canceled").
/// </summary>
/// </signature>

/// <signature helpKeyword=".PromiseStateMachine.cancel">
/// <summary locid=".PromiseStateMachine.cancel">
/// Attempts to cancel the fulfillment of a promised value. If the promise hasn't
/// already been fulfilled and cancellation is supported, the promise enters
/// the error state with a value of Error("Canceled").
/// </summary>
/// </signature>

}).call(this,typeof global !== "undefined" ? global : typeof self !== "undefined" ? self : typeof window !== "undefined" ? window : {})
},{"./event-utils":2,"./object":3}],2:[function(require,module,exports){
"use strict";

var _createClass = (function () { function defineProperties(target, props) { for (var key in props) { var prop = props[key]; prop.configurable = true; if (prop.value) prop.writable = true; } Object.defineProperties(target, props); } return function (Constructor, protoProps, staticProps) { if (protoProps) defineProperties(Constructor.prototype, protoProps); if (staticProps) defineProperties(Constructor, staticProps); return Constructor; }; })();

var _classCallCheck = function (instance, Constructor) { if (!(instance instanceof Constructor)) { throw new TypeError("Cannot call a class as a function"); } };

exports.createEventProperties = createEventProperties;
Object.defineProperty(exports, "__esModule", {
    value: true
});
"use strict";

var DOMEventMixin = {
    _domElement: null,

    addEventListener: function addEventListener(type, listener, useCapture) {
        /// <signature helpKeyword="DOMEventMixin.addEventListener">
        /// <summary locid="DOMEventMixin.addEventListener">
        /// Adds an event listener to the control.
        /// </summary>
        /// <param name="type" type="String" locid="DOMEventMixin.addEventListener_p:type">
        /// The type (name) of the event.
        /// </param>
        /// <param name="listener" type="Function" locid="DOMEventMixin.addEventListener_p:listener">
        /// The listener to invoke when the event gets raised.
        /// </param>
        /// <param name="useCapture" type="Boolean" locid="DOMEventMixin.addEventListener_p:useCapture">
        /// true to initiate capture; otherwise, false.
        /// </param>
        /// </signature>
        (this.element || this._domElement).addEventListener(type, listener, useCapture || false);
    },
    dispatchEvent: function dispatchEvent(type, eventProperties) {
        /// <signature helpKeyword="DOMEventMixin.dispatchEvent">
        /// <summary locid="DOMEventMixin.dispatchEvent">
        /// Raises an event of the specified type, adding the specified additional properties.
        /// </summary>
        /// <param name="type" type="String" locid="DOMEventMixin.dispatchEvent_p:type">
        /// The type (name) of the event.
        /// </param>
        /// <param name="eventProperties" type="Object" locid="DOMEventMixin.dispatchEvent_p:eventProperties">
        /// The set of additional properties to be attached to the event object when the event is raised.
        /// </param>
        /// <returns type="Boolean" locid="DOMEventMixin.dispatchEvent_returnValue">
        /// true if preventDefault was called on the event, otherwise false.
        /// </returns>
        /// </signature>
        var eventValue = document.createEvent("Event");
        eventValue.initEvent(type, true, true);
        eventValue.detail = eventProperties;
        if (typeof eventProperties === "object") {
            Object.keys(eventProperties).forEach(function (key) {
                eventValue[key] = eventProperties[key];
            });
        }
        return (this.element || this._domElement).dispatchEvent(eventValue);
    },
    removeEventListener: function removeEventListener(type, listener, useCapture) {
        /// <signature helpKeyword="DOMEventMixin.removeEventListener">
        /// <summary locid="DOMEventMixin.removeEventListener">
        /// Removes an event listener from the control.
        /// </summary>
        /// <param name="type" type="String" locid="DOMEventMixin.removeEventListener_p:type">
        /// The type (name) of the event.
        /// </param>
        /// <param name="listener" type="Function" locid="DOMEventMixin.removeEventListener_p:listener">
        /// The listener to remove.
        /// </param>
        /// <param name="useCapture" type="Boolean" locid="DOMEventMixin.removeEventListener_p:useCapture">
        /// true to initiate capture; otherwise, false.
        /// </param>
        /// </signature>
        (this.element || this._domElement).removeEventListener(type, listener, useCapture || false);
    }
};

exports.DOMEventMixin = DOMEventMixin;
function createEventProperty(name) {
    var eventPropStateName = "_on" + name + "state";

    return {
        get: function get() {
            var state = this[eventPropStateName];
            return state && state.userHandler;
        },
        set: function set(handler) {
            var state = this[eventPropStateName];
            if (handler) {
                if (!state) {
                    state = { wrapper: function wrapper(evt) {
                            return state.userHandler(evt);
                        }, userHandler: handler };
                    Object.defineProperty(this, eventPropStateName, { value: state, enumerable: false, writable: true, configurable: true });
                    this.addEventListener(name, state.wrapper, false);
                }
                state.userHandler = handler;
            } else if (state) {
                this.removeEventListener(name, state.wrapper, false);
                this[eventPropStateName] = null;
            }
        },
        enumerable: true
    };
}

function createEventProperties(events) {
    /// <signature helpKeyword="WinJS.Utilities.createEventProperties">
    /// <summary locid="WinJS.Utilities.createEventProperties">
    /// Creates an object that has one property for each name passed to the function.
    /// </summary>
    /// <param name="events" locid="WinJS.Utilities.createEventProperties_p:events">
    /// A variable list of property names.
    /// </param>
    /// <returns type="Object" locid="WinJS.Utilities.createEventProperties_returnValue">
    /// The object with the specified properties. The names of the properties are prefixed with 'on'.
    /// </returns>
    /// </signature>
    var props = {};
    for (var i = 0, len = arguments.length; i < len; i++) {
        var name = arguments[i];
        props["on" + name] = createEventProperty(name);
    }
    return props;
}

var EventMixinEvent = (function () {
    function EventMixinEvent(type, detail, target) {
        _classCallCheck(this, EventMixinEvent);

        this.detail = detail;
        this.target = target;
        this.timeStamp = Date.now();
        this.type = type;
        this.bubbles = false; // TODO: writable -false
        this.cancelable = false; // TODO: writable -false
        this.trusted = false; // TODO: writable -false
        this.eventPhase = false; // TODO: writable -false
    }

    EventMixinEvent.prototype.preventDefault = function preventDefault() {
        this._preventDefaultCalled = true;
    };

    EventMixinEvent.prototype.stopImmediatePropagation = function stopImmediatePropagation() {
        this._stopImmediatePropagationCalled = true;
    };

    EventMixinEvent.prototype.stopPropagation = function stopPropagation() {};

    _createClass(EventMixinEvent, {
        currentTarget: {
            get: function () {
                return this.target;
            }
        },
        defaultPrevented: {
            get: function () {
                return this._preventDefaultCalled;
            }
        }
    });

    return EventMixinEvent;
})();

EventMixinEvent.supportedForProcessing = false;

var eventMixin = {
    _listeners: null,

    addEventListener: function addEventListener(type, listener, useCapture) {
        /// <signature helpKeyword="Utilities.eventMixin.addEventListener">
        /// <summary locid=".Utilities.eventMixin.addEventListener">
        /// Adds an event listener to the control.
        /// </summary>
        /// <param name="type" locid=".Utilities.eventMixin.addEventListener_p:type">
        /// The type (name) of the event.
        /// </param>
        /// <param name="listener" locid=".Utilities.eventMixin.addEventListener_p:listener">
        /// The listener to invoke when the event gets raised.
        /// </param>
        /// <param name="useCapture" locid=".Utilities.eventMixin.addEventListener_p:useCapture">
        /// if true initiates capture, otherwise false.
        /// </param>
        /// </signature>
        useCapture = useCapture || false;
        this._listeners = this._listeners || {};
        var eventListeners = this._listeners[type] = this._listeners[type] || [];
        for (var i = 0, len = eventListeners.length; i < len; i++) {
            var l = eventListeners[i];
            if (l.useCapture === useCapture && l.listener === listener) {
                return;
            }
        }
        eventListeners.push({ listener: listener, useCapture: useCapture });
    },
    dispatchEvent: function dispatchEvent(type, details) {
        /// <signature helpKeyword=".Utilities.eventMixin.dispatchEvent">
        /// <summary locid=".Utilities.eventMixin.dispatchEvent">
        /// Raises an event of the specified type and with the specified additional properties.
        /// </summary>
        /// <param name="type" locid=".Utilities.eventMixin.dispatchEvent_p:type">
        /// The type (name) of the event.
        /// </param>
        /// <param name="details" locid=".Utilities.eventMixin.dispatchEvent_p:details">
        /// The set of additional properties to be attached to the event object when the event is raised.
        /// </param>
        /// <returns type="Boolean" locid=".Utilities.eventMixin.dispatchEvent_returnValue">
        /// true if preventDefault was called on the event.
        /// </returns>
        /// </signature>
        var listeners = this._listeners && this._listeners[type];
        if (listeners) {
            var eventValue = new EventMixinEvent(type, details, this);
            // Need to copy the array to protect against people unregistering while we are dispatching
            listeners = listeners.slice(0, listeners.length);
            for (var i = 0, len = listeners.length; i < len && !eventValue._stopImmediatePropagationCalled; i++) {
                listeners[i].listener(eventValue);
            }
            return eventValue.defaultPrevented || false;
        }
        return false;
    },
    removeEventListener: function removeEventListener(type, listener, useCapture) {
        /// <signature helpKeyword=".Utilities.eventMixin.removeEventListener">
        /// <summary locid=".Utilities.eventMixin.removeEventListener">
        /// Removes an event listener from the control.
        /// </summary>
        /// <param name="type" locid=".Utilities.eventMixin.removeEventListener_p:type">
        /// The type (name) of the event.
        /// </param>
        /// <param name="listener" locid=".Utilities.eventMixin.removeEventListener_p:listener">
        /// The listener to remove.
        /// </param>
        /// <param name="useCapture" locid=".Utilities.eventMixin.removeEventListener_p:useCapture">
        /// Specifies whether to initiate capture.
        /// </param>
        /// </signature>
        useCapture = useCapture || false;
        var listeners = this._listeners && this._listeners[type];
        if (listeners) {
            for (var i = 0, len = listeners.length; i < len; i++) {
                var l = listeners[i];
                if (l.listener === listener && l.useCapture === useCapture) {
                    listeners.splice(i, 1);
                    if (listeners.length === 0) {
                        delete this._listeners[type];
                    }
                    // Only want to remove one element for each call to removeEventListener
                    break;
                }
            }
        }
    }
};
exports.eventMixin = eventMixin;

},{}],3:[function(require,module,exports){
"use strict";

var _inherits = function (subClass, superClass) { if (typeof superClass !== "function" && superClass !== null) { throw new TypeError("Super expression must either be null or a function, not " + typeof superClass); } subClass.prototype = Object.create(superClass && superClass.prototype, { constructor: { value: subClass, enumerable: false, writable: true, configurable: true } }); if (superClass) subClass.__proto__ = superClass; };

var _classCallCheck = function (instance, Constructor) { if (!(instance instanceof Constructor)) { throw new TypeError("Cannot call a class as a function"); } };

exports.mixin = mixin;
exports.mix = mix;
exports.mixInstance = mixInstance;
Object.defineProperty(exports, "__esModule", {
    value: true
});
"use strict";

function mixin(Parent) {
    for (var _len = arguments.length, mixins = Array(_len > 1 ? _len - 1 : 0), _key = 1; _key < _len; _key++) {
        mixins[_key - 1] = arguments[_key];
    }

    if (Parent.constructor !== Object) {
        var i, l;

        var _ret = (function () {
            var Mixed = (function (_Parent) {
                function Mixed() {
                    _classCallCheck(this, Mixed);

                    if (_Parent != null) {
                        _Parent.apply(this, arguments);
                    }
                }

                _inherits(Mixed, _Parent);

                return Mixed;
            })(Parent);

            for (i = 0, l = mixins.length; i < l; i++) {
                initializeProperties(Mixed.prototype, mixins[i]);
            }
            return {
                v: Mixed
            };
        })();

        if (typeof _ret === "object") {
            return _ret.v;
        }
    } else {
        var i, l;

        var _ret2 = (function () {
            var Mixed = function Mixed() {
                _classCallCheck(this, Mixed);
            };

            mixins.unshift(Parent);
            for (i = 0, l = mixins.length; i < l; i++) {
                initializeProperties(Mixed.prototype, mixins[i]);
            }
            return {
                v: Mixed
            };
        })();

        if (typeof _ret2 === "object") {
            return _ret2.v;
        }
    }
}

function mix(Class) {
    for (var _len = arguments.length, mixins = Array(_len > 1 ? _len - 1 : 0), _key = 1; _key < _len; _key++) {
        mixins[_key - 1] = arguments[_key];
    }

    for (var i = 0, l = mixins.length; i < l; i++) {
        initializeProperties(Class.prototype, mixins[i]);
    }
    return Class;
}

function mixInstance(instance) {
    for (var _len = arguments.length, mixins = Array(_len > 1 ? _len - 1 : 0), _key = 1; _key < _len; _key++) {
        mixins[_key - 1] = arguments[_key];
    }

    for (var i = 0, l = mixins.length; i < l; i++) {
        initializeProperties(instance, mixins[i]);
    }
}

function initializeProperties(target, members, prefix) {
    var keys = Object.keys(members);
    var isArray = Array.isArray(target);
    var properties;
    var i, len;
    for (i = 0, len = keys.length; i < len; i++) {
        var key = keys[i];
        var enumerable = key.charCodeAt(0) !== /*_*/95;
        var member = members[key];
        if (member && typeof member === "object") {
            if (member.value !== undefined || typeof member.get === "function" || typeof member.set === "function") {
                if (member.enumerable === undefined) {
                    member.enumerable = enumerable;
                }
                if (prefix && member.setName && typeof member.setName === "function") {
                    member.setName(prefix + "." + key);
                }
                properties = properties || {};
                properties[key] = member;
                continue;
            }
        }
        if (!enumerable) {
            properties = properties || {};
            properties[key] = { value: member, enumerable: enumerable, configurable: true, writable: true };
            continue;
        }
        if (isArray) {
            target.forEach(function (target) {
                target[key] = member;
            });
        } else {
            target[key] = member;
        }
    }
    if (properties) {
        if (isArray) {
            target.forEach(function (target) {
                Object.defineProperties(target, properties);
            });
        } else {
            Object.defineProperties(target, properties);
        }
    }
}

},{}],4:[function(require,module,exports){
"use strict";

if (!Array.prototype.find) {
    Object.defineProperty(Array.prototype, "find", {
        enumerable: false,
        value: (function (_value) {
            var _valueWrapper = function value(_x) {
                return _value.apply(this, arguments);
            };

            _valueWrapper.toString = function () {
                return _value.toString();
            };

            return _valueWrapper;
        })(function (predicate) {
            if (this == null) {
                throw new TypeError("Array.prototype.find called on null or undefined");
            }
            if (typeof predicate !== "function") {
                throw new TypeError("predicate must be a function");
            }
            var list = Object(this);
            var length = list.length >>> 0;
            var thisArg = arguments[1];
            var value;

            for (var i = 0; i < length; i++) {
                value = list[i];
                if (predicate.call(thisArg, value, i, list)) {
                    return value;
                }
            }
            return undefined;
        })
    });
}

if (!Array.prototype.findIndex) {
    Object.defineProperty(Array.prototype, "findIndex", {
        enumerable: false,
        value: (function (_value) {
            var _valueWrapper = function value(_x) {
                return _value.apply(this, arguments);
            };

            _valueWrapper.toString = function () {
                return _value.toString();
            };

            return _valueWrapper;
        })(function (predicate) {
            if (this == null) {
                throw new TypeError("Array.prototype.findIndex called on null or undefined");
            }
            if (typeof predicate !== "function") {
                throw new TypeError("predicate must be a function");
            }
            var list = Object(this);
            var length = list.length >>> 0;
            var thisArg = arguments[1];
            var value;

            for (var i = 0; i < length; i++) {
                value = list[i];
                if (predicate.call(thisArg, value, i, list)) {
                    return i;
                }
            }
            return -1;
        })
    });
}

// Production steps of ECMA-262, Edition 6, 22.1.2.1
// Reference: https://people.mozilla.org/~jorendorff/es6-draft.html#sec-array.from
if (!Array.from) {
    Array.from = (function () {
        var toStr = Object.prototype.toString;
        var isCallable = function isCallable(fn) {
            return typeof fn === "function" || toStr.call(fn) === "[object Function]";
        };
        var toInteger = function toInteger(value) {
            var number = Number(value);
            if (isNaN(number)) {
                return 0;
            }
            if (number === 0 || !isFinite(number)) {
                return number;
            }
            return (number > 0 ? 1 : -1) * Math.floor(Math.abs(number));
        };
        var maxSafeInteger = Math.pow(2, 53) - 1;
        var toLength = function toLength(value) {
            var len = toInteger(value);
            return Math.min(Math.max(len, 0), maxSafeInteger);
        };

        // The length property of the from method is 1.
        return function from(arrayLike /*, mapFn, thisArg */) {
            // 1. Let C be the this value.
            var C = this;

            // 2. Let items be ToObject(arrayLike).
            var items = Object(arrayLike);

            // 3. ReturnIfAbrupt(items).
            if (arrayLike == null) {
                throw new TypeError("Array.from requires an array-like object - not null or undefined");
            }

            // 4. If mapfn is undefined, then let mapping be false.
            var mapFn = arguments.length > 1 ? arguments[1] : void undefined;
            var T;
            if (typeof mapFn !== "undefined") {
                // 5. else     
                // 5. a If IsCallable(mapfn) is false, throw a TypeError exception.
                if (!isCallable(mapFn)) {
                    throw new TypeError("Array.from: when provided, the second argument must be a function");
                }

                // 5. b. If thisArg was supplied, let T be thisArg; else let T be undefined.
                if (arguments.length > 2) {
                    T = arguments[2];
                }
            }

            // 10. Let lenValue be Get(items, "length").
            // 11. Let len be ToLength(lenValue).
            var len = toLength(items.length);

            // 13. If IsConstructor(C) is true, then
            // 13. a. Let A be the result of calling the [[Construct]] internal method of C with an argument list containing the single item len.
            // 14. a. Else, Let A be ArrayCreate(len).
            var A = isCallable(C) ? Object(new C(len)) : new Array(len);

            // 16. Let k be 0.
            var k = 0;
            // 17. Repeat, while k < len… (also steps a - h)
            var kValue;
            while (k < len) {
                kValue = items[k];
                if (mapFn) {
                    A[k] = typeof T === "undefined" ? mapFn(kValue, k) : mapFn.call(T, kValue, k);
                } else {
                    A[k] = kValue;
                }
                k += 1;
            }
            // 18. Let putStatus be Put(A, "length", len, true).
            A.length = len;
            // 20. Return A.
            return A;
        };
    })();
}

},{}],5:[function(require,module,exports){
"use strict";

var CanvasPrototype = window.HTMLCanvasElement && window.HTMLCanvasElement.prototype,
    hasBlobConstructor = window.Blob && (function () {
    try {
        return Boolean(new Blob());
    } catch (e) {
        return false;
    }
})(),
    hasArrayBufferViewSupport = hasBlobConstructor && window.Uint8Array && (function () {
    try {
        return new Blob([new Uint8Array(100)]).size === 100;
    } catch (e) {
        return false;
    }
})(),
    BlobBuilder = window.BlobBuilder || window.WebKitBlobBuilder || window.MozBlobBuilder || window.MSBlobBuilder,
    dataURLtoBlob = (hasBlobConstructor || BlobBuilder) && window.atob && window.ArrayBuffer && window.Uint8Array && function (dataURI) {
    var byteString, arrayBuffer, intArray, i, mimeString, bb;
    if (dataURI.split(",")[0].indexOf("base64") >= 0) {
        // Convert base64 to raw binary data held in a string:
        byteString = atob(dataURI.split(",")[1]);
    } else {
        // Convert base64/URLEncoded data component to raw binary data:
        byteString = decodeURIComponent(dataURI.split(",")[1]);
    }
    // Write the bytes of the string to an ArrayBuffer:
    arrayBuffer = new ArrayBuffer(byteString.length);
    intArray = new Uint8Array(arrayBuffer);
    for (i = 0; i < byteString.length; i += 1) {
        intArray[i] = byteString.charCodeAt(i);
    }
    // Separate out the mime component:
    mimeString = dataURI.split(",")[0].split(":")[1].split(";")[0];
    // Write the ArrayBuffer (or ArrayBufferView) to a blob:
    if (hasBlobConstructor) {
        return new Blob([hasArrayBufferViewSupport ? intArray : arrayBuffer], { type: mimeString });
    }
    bb = new BlobBuilder();
    bb.append(arrayBuffer);
    return bb.getBlob(mimeString);
};
if (window.HTMLCanvasElement && !CanvasPrototype.toBlob) {
    if (CanvasPrototype.mozGetAsFile) {
        CanvasPrototype.toBlob = function (callback, type, quality) {
            if (quality && CanvasPrototype.toDataURL && dataURLtoBlob) {
                callback(dataURLtoBlob(this.toDataURL(type, quality)));
            } else {
                callback(this.mozGetAsFile("blob", type));
            }
        };
    } else if (CanvasPrototype.toDataURL && dataURLtoBlob) {
        CanvasPrototype.toBlob = function (callback, type, quality) {
            callback(dataURLtoBlob(this.toDataURL(type, quality)));
        };
    }
}
// if (typeof define === 'function' && define.amd) {
//     define(function () {
//         return dataURLtoBlob;
//     });
// } else {
//     window.dataURLtoBlob = dataURLtoBlob;
// }

},{}],6:[function(require,module,exports){
/*
 * classList.js: Cross-browser full element.classList implementation.
 * 2015-03-12
 *
 * By Eli Grey, http://eligrey.com
 * License: Dedicated to the public domain.
 *   See https://github.com/eligrey/classList.js/blob/master/LICENSE.md
 */

/*global self, document, DOMException */

/*! @source http://purl.eligrey.com/github/classList.js/blob/master/classList.js */

"use strict";

if ("document" in self) {

	// Full polyfill for browsers with no classList support
	if (!("classList" in document.createElement("_"))) {

		(function (view) {

			"use strict";

			if (!("Element" in view)) return;

			var classListProp = "classList",
			    protoProp = "prototype",
			    elemCtrProto = view.Element[protoProp],
			    objCtr = Object,
			    strTrim = String[protoProp].trim || function () {
				return this.replace(/^\s+|\s+$/g, "");
			},
			    arrIndexOf = Array[protoProp].indexOf || function (item) {
				var i = 0,
				    len = this.length;
				for (; i < len; i++) {
					if (i in this && this[i] === item) {
						return i;
					}
				}
				return -1;
			}
			// Vendors: please allow content code to instantiate DOMExceptions
			,
			    DOMEx = function DOMEx(type, message) {
				this.name = type;
				this.code = DOMException[type];
				this.message = message;
			},
			    checkTokenAndGetIndex = function checkTokenAndGetIndex(classList, token) {
				if (token === "") {
					throw new DOMEx("SYNTAX_ERR", "An invalid or illegal string was specified");
				}
				if (/\s/.test(token)) {
					throw new DOMEx("INVALID_CHARACTER_ERR", "String contains an invalid character");
				}
				return arrIndexOf.call(classList, token);
			},
			    ClassList = function ClassList(elem) {
				var trimmedClasses = strTrim.call(elem.getAttribute("class") || ""),
				    classes = trimmedClasses ? trimmedClasses.split(/\s+/) : [],
				    i = 0,
				    len = classes.length;
				for (; i < len; i++) {
					this.push(classes[i]);
				}
				this._updateClassName = function () {
					elem.setAttribute("class", this.toString());
				};
			},
			    classListProto = ClassList[protoProp] = [],
			    classListGetter = function classListGetter() {
				return new ClassList(this);
			};
			// Most DOMException implementations don't allow calling DOMException's toString()
			// on non-DOMExceptions. Error's toString() is sufficient here.
			DOMEx[protoProp] = Error[protoProp];
			classListProto.item = function (i) {
				return this[i] || null;
			};
			classListProto.contains = function (token) {
				token += "";
				return checkTokenAndGetIndex(this, token) !== -1;
			};
			classListProto.add = function () {
				var tokens = arguments,
				    i = 0,
				    l = tokens.length,
				    token,
				    updated = false;
				do {
					token = tokens[i] + "";
					if (checkTokenAndGetIndex(this, token) === -1) {
						this.push(token);
						updated = true;
					}
				} while (++i < l);

				if (updated) {
					this._updateClassName();
				}
			};
			classListProto.remove = function () {
				var tokens = arguments,
				    i = 0,
				    l = tokens.length,
				    token,
				    updated = false,
				    index;
				do {
					token = tokens[i] + "";
					index = checkTokenAndGetIndex(this, token);
					while (index !== -1) {
						this.splice(index, 1);
						updated = true;
						index = checkTokenAndGetIndex(this, token);
					}
				} while (++i < l);

				if (updated) {
					this._updateClassName();
				}
			};
			classListProto.toggle = function (token, force) {
				token += "";

				var result = this.contains(token),
				    method = result ? force !== true && "remove" : force !== false && "add";

				if (method) {
					this[method](token);
				}

				if (force === true || force === false) {
					return force;
				} else {
					return !result;
				}
			};
			classListProto.toString = function () {
				return this.join(" ");
			};

			if (objCtr.defineProperty) {
				var classListPropDesc = {
					get: classListGetter,
					enumerable: true,
					configurable: true
				};
				try {
					objCtr.defineProperty(elemCtrProto, classListProp, classListPropDesc);
				} catch (ex) {
					// IE 8 doesn't support enumerable:true
					if (ex.number === -2146823252) {
						classListPropDesc.enumerable = false;
						objCtr.defineProperty(elemCtrProto, classListProp, classListPropDesc);
					}
				}
			} else if (objCtr[protoProp].__defineGetter__) {
				elemCtrProto.__defineGetter__(classListProp, classListGetter);
			}
		})(self);
	} else {
		// There is full or partial native classList support, so just check if we need
		// to normalize the add/remove and toggle APIs.

		(function () {
			"use strict";

			var testElement = document.createElement("_");

			testElement.classList.add("c1", "c2");

			// Polyfill for IE 10/11 and Firefox <26, where classList.add and
			// classList.remove exist but support only one argument at a time.
			if (!testElement.classList.contains("c2")) {
				var createMethod = function createMethod(method) {
					var original = DOMTokenList.prototype[method];

					DOMTokenList.prototype[method] = function (token) {
						var i,
						    len = arguments.length;

						for (i = 0; i < len; i++) {
							token = arguments[i];
							original.call(this, token);
						}
					};
				};
				createMethod("add");
				createMethod("remove");
			}

			testElement.classList.toggle("c3", false);

			// Polyfill for IE 10 and Firefox <24, where classList.toggle does not
			// support the second argument.
			if (testElement.classList.contains("c3")) {
				var _toggle = DOMTokenList.prototype.toggle;

				DOMTokenList.prototype.toggle = function (token, force) {
					if (1 in arguments && !this.contains(token) === !force) {
						return force;
					} else {
						return _toggle.call(this, token);
					}
				};
			}

			testElement = null;
		})();
	}
}

},{}],7:[function(require,module,exports){
"use strict";

NodeList.prototype.forEach = function (fn) {
    for (var i = 0, l = this.length; i < l; i++) {
        fn(this[i], i);
    }
};

},{}],8:[function(require,module,exports){
"use strict";

if (!Object.assign) {
    Object.defineProperty(Object, "assign", {
        enumerable: false,
        configurable: true,
        writable: true,
        value: function value(target, firstSource) {
            "use strict";
            if (target === undefined || target === null) {
                throw new TypeError("Cannot convert first argument to object");
            }

            var to = Object(target);
            for (var i = 1; i < arguments.length; i++) {
                var nextSource = arguments[i];
                if (nextSource === undefined || nextSource === null) {
                    continue;
                }

                var keysArray = Object.keys(Object(nextSource));
                for (var nextIndex = 0, len = keysArray.length; nextIndex < len; nextIndex++) {
                    var nextKey = keysArray[nextIndex];
                    var desc = Object.getOwnPropertyDescriptor(nextSource, nextKey);
                    if (desc !== undefined && desc.enumerable) {
                        to[nextKey] = nextSource[nextKey];
                    }
                }
            }
            return to;
        }
    });
}

},{}],9:[function(require,module,exports){
"use strict";

var _interopRequireWildcard = function (obj) { return obj && obj.__esModule ? obj : { "default": obj }; };

var A = _interopRequireWildcard(require("./element-classlist"));

var B = _interopRequireWildcard(require("./object-assign"));

var C = _interopRequireWildcard(require("./nodelist-foreach"));

var D = _interopRequireWildcard(require("./set-immediate"));

var E = _interopRequireWildcard(require("./canvas-to-blob"));

var F = _interopRequireWildcard(require("./array-find"));

},{"./array-find":4,"./canvas-to-blob":5,"./element-classlist":6,"./nodelist-foreach":7,"./object-assign":8,"./set-immediate":10}],10:[function(require,module,exports){
"use strict";

if (!window.setImmediate) {
    window.setImmediate = function (fn) {
        return setTimeout(fn, 0);
    };
}

if (!window.clearImmediate) {
    window.clearImmediate = function (fn) {
        return clearTimeout(fn);
    };
}

},{}],11:[function(require,module,exports){
(function (global){
"use strict";

var _interopRequireWildcard = function (obj) { return obj && obj.__esModule ? obj : { "default": obj }; };

var _interopRequire = function (obj) { return obj && obj.__esModule ? obj["default"] : obj; };

var _inherits = function (subClass, superClass) { if (typeof superClass !== "function" && superClass !== null) { throw new TypeError("Super expression must either be null or a function, not " + typeof superClass); } subClass.prototype = Object.create(superClass && superClass.prototype, { constructor: { value: subClass, enumerable: false, writable: true, configurable: true } }); if (superClass) subClass.__proto__ = superClass; };

var _classCallCheck = function (instance, Constructor) { if (!(instance instanceof Constructor)) { throw new TypeError("Cannot call a class as a function"); } };

Object.defineProperty(exports, "__esModule", {
	value: true
});
"use strict";

var mixin = require("object").mixin;

var Promise = _interopRequire(require("Promise"));

var EventUtils = _interopRequireWildcard(require("event-utils"));

var EventManager = (function (_mixin) {
	function EventManager() {
		_classCallCheck(this, EventManager);
	}

	_inherits(EventManager, _mixin);

	return EventManager;
})(mixin(EventUtils.eventMixin));

var promises = [],
    eventManager = new EventManager();

global.addEventListener("message", messageReceived, false);
global.parent.postMessage({ action: "ready" }, "*");

function addEventListener() {
	eventManager.addEventListener.apply(eventManager, arguments);
}
var addEventListener;

exports.addEventListener = addEventListener;
function removeEventListener() {
	eventManager.removeEventListener.apply(eventManager, arguments);
}
var removeEventListener;

exports.removeEventListener = removeEventListener;
function messageReceived(event) {
	/*if(event.origin.indexOf(this.config.hostname) === -1){
 	return;
 }*/
	var data = event.data;
	if (!data || !data.action) {
		return;
	}

	if (data.action === "ready") {

		var source = event.source;
		var data = getPromiseData(source);
		if (data) {
			// Another function is waiting for this to be called
			if (data.completeFn) {
				data.completeFn();
				delete data.completeFn;
			}
		} else {
			promises.push({
				window: source,
				promise: new Promise(function (c, e) {
					c();
				})
			});
		}

		return;
	}

	eventManager.dispatchEvent(data.action, event);
}
var messageReceived;

exports.messageReceived = messageReceived;
function postMessage(win, data) {
	var pd = getPromiseData(win);
	if (!pd) {
		var completeFn;
		var promise = new Promise(function (c, e) {
			completeFn = c;
		});
		if (win === window.parent) {
			completeFn();
		}
		pd = {
			window: win,
			promise: promise,
			completeFn: completeFn
		};
		promises.push(pd);
	}

	var that = this;
	return pd.promise.then(function () {
		win.postMessage(data, "*");
		return new Promise(function (c, e) {
			var fn = (function (_fn) {
				var _fnWrapper = function fn(_x) {
					return _fn.apply(this, arguments);
				};

				_fnWrapper.toString = function () {
					return _fn.toString();
				};

				return _fnWrapper;
			})(function (ev) {
				if (ev.detail.source === win) {
					c(ev.detail.data);
					removeEventListener(data.action, fn, false);
				}
			});
			addEventListener(data.action, fn, false);
		});
	});
}
var postMessage;

exports.postMessage = postMessage;
/** HELPERS **/

function getPromiseData(win) {
	for (var i = 0, l = promises.length; i < l; i++) {
		var promise = promises[i];
		if (promise.window === win) {
			return promise;
		}
	}
}

}).call(this,typeof global !== "undefined" ? global : typeof self !== "undefined" ? self : typeof window !== "undefined" ? window : {})
},{"Promise":1,"event-utils":2,"object":3}],12:[function(require,module,exports){
(function (global){
"use strict";

var _interopRequireWildcard = function (obj) { return obj && obj.__esModule ? obj : { "default": obj }; };

var WindowMessageManager = _interopRequireWildcard(require("window-messages"));

var frame;

function init() {
	document.querySelector("#storyform-editor").focus();
}
document.addEventListener("DOMContentLoaded", init, false);

WindowMessageManager.addEventListener("set-post", function (ev) {
	var id = ev.detail.data.id;
	var title = ev.detail.data.title;
	global.window.history.replaceState({}, "Edit " + title, document.location.href + "&post=" + id);
});

WindowMessageManager.addEventListener("create-post", function (ev) {
	var data = ev.detail.data;

	jQuery.post(ajaxurl, {
		action: "storyform_create_post",
		_ajax_nonce: storyform_nonce,
		post_title: data.title,
		post_excerpt: data.excerpt,
		post_content: data.content,
		post_type: data.postType,
		template: data.template
	}, function (data, textStatus, jqXHR) {
		data = JSON.parse(data);
		data.action = "create-post";
		data.status = jqXHR.status;
		WindowMessageManager.postMessage(ev.detail.source, data);
	});
});

WindowMessageManager.addEventListener("get-post", function (ev) {
	var data = ev.detail.data;

	jQuery.post(ajaxurl, {
		action: "storyform_get_post",
		_ajax_nonce: storyform_nonce,
		id: data.id
	}, function (data, textStatus, jqXHR) {
		data = JSON.parse(data);
		data.action = "get-post";
		data.status = jqXHR.status;
		WindowMessageManager.postMessage(ev.detail.source, data);
	});
});

WindowMessageManager.addEventListener("update-post", function (ev) {
	var data = ev.detail.data;

	jQuery.post(ajaxurl, {
		action: "storyform_update_post",
		_ajax_nonce: storyform_nonce,
		id: data.id,
		post_title: data.title,
		post_content: data.content,
		post_excerpt: data.excerpt,
		template: data.template,
		post_type: data.postType
	}, function (data, textStatus, jqXHR) {
		data = JSON.parse(data);
		data.action = "update-post";
		data.status = jqXHR.status;
		WindowMessageManager.postMessage(ev.detail.source, data);
	});
});

WindowMessageManager.addEventListener("get-publish-url", function (ev) {
	var data = ev.detail.data;

	jQuery.post(ajaxurl, {
		action: "storyform_get_publish_url",
		_ajax_nonce: storyform_nonce,
		id: data.id,
		name: data.name
	}, function (data, textStatus, jqXHR) {
		data = JSON.parse(data);
		data.action = "get-publish-url";
		data.status = jqXHR.status;
		WindowMessageManager.postMessage(ev.detail.source, data);
	});
});

WindowMessageManager.addEventListener("delete-post", function (ev) {
	var source = ev.detail.source;
	var req = ev.detail.data;

	jQuery.post(ajaxurl, {
		action: "storyform_delete_post",
		_ajax_nonce: storyform_nonce,
		id: req.id
	}, function (data, textStatus, jqXHR) {
		data = JSON.parse(data);
		data.action = "delete-post";
		data.status = jqXHR.status;
		WindowMessageManager.postMessage(ev.detail.source, data);
	});
});

WindowMessageManager.addEventListener("redirect", function (ev) {
	var data = ev.detail.data;
	document.location.href = data.url;
});

WindowMessageManager.addEventListener("redirect-admin-edit", function (ev) {
	var req = ev.detail.data;
	jQuery.post(ajaxurl, {
		action: "storyform_redirect_admin_edit",
		_ajax_nonce: storyform_nonce,
		id: req.id
	}, function (data, textStatus, jqXHR) {
		data = JSON.parse(data);
		document.location.href = data.url;
	});
});

WindowMessageManager.addEventListener("get-post-types", function (ev) {
	var data = ev.detail.data;

	jQuery.post(ajaxurl, {
		action: "storyform_get_post_types",
		_ajax_nonce: storyform_nonce
	}, function (data, textStatus, jqXHR) {
		data = JSON.parse(data);
		data.action = "get-post-types";
		data.status = jqXHR.status;
		WindowMessageManager.postMessage(ev.detail.source, data);
	});
});

WindowMessageManager.addEventListener("select-media", selectMedia);
function selectMedia(ev) {
	if (frame) {
		frame.open();
		return;
	}

	frame = wp.media({
		title: "Select or Upload Media",
		button: {
			text: "Insert this media"
		},
		multiple: true
	});

	frame.on("select", function () {
		var models = frame.state().get("selection").models;
		var media = [];
		models.forEach(function (model) {
			var data = model.toJSON();
			media.push(data);
		});

		if (media.filter(function (item) {
			return item.type === "video";
		}).length > 1) {
			alert("Please choose only one video at a time");
			document.querySelector("#storyform-editor").focus();
			return;
		}
		var ids = media.map(function (a) {
			return a.id;
		});
		getMediaSizes(ids, function (sizes) {
			media.forEach(function (item) {
				item.sizes = sizes[item.id];
			});

			var pendingPoster = false;
			media.forEach(function (item) {
				if (item.type === "video") {
					pendingPoster = true;
					choosePoster(function (poster) {
						item.poster = poster;
						WindowMessageManager.postMessage(ev.detail.source, { action: "select-media", media: media });
					});
				}
			});

			!pendingPoster && WindowMessageManager.postMessage(ev.detail.source, { action: "select-media", media: media });
		});
	});
	frame.on("close", function () {
		document.querySelector("#storyform-editor").focus();
	});
	frame.open();
}

function choosePoster(cb) {
	var posterFrame = wp.media({
		title: "Select or Upload a video poster",
		button: {
			text: "Use as poster"
		},
		multiple: false
	});

	posterFrame.on("select", function () {
		var media = posterFrame.state().get("selection").first().toJSON();
		getMediaSizes([media.id], function (sizes) {
			media.sizes = sizes[media.id];
			cb(media);
		});
	});

	posterFrame.open();
}

function getMediaSizes(ids, cb) {
	jQuery.post(ajaxurl, {
		action: "storyform_get_media_sizes",
		ids: ids.join(","),
		_ajax_nonce: storyform_nonce
	}, function (data, textStatus, jqXHR) {
		data = JSON.parse(data);
		cb(data);
	});
}

//     {
//     "id": 2226,
//     "title": "Screen Shot 2015-01-29 at 1.31.07 PM",
//     "filename": "Screen-Shot-2015-01-29-at-1.31.07-PM.png",
//     "url": "http://storyform.co/demos/wp-content/uploads/2015/01/Screen-Shot-2015-01-29-at-1.31.07-PM.png",
//     "link": "http://storyform.co/demos/2015/01/29/understanding-big-data/screen-shot-2015-01-29-at-1-31-07-pm/",
//     "alt": "",
//     "author": "1",
//     "description": "",
//     "caption": "",
//     "name": "screen-shot-2015-01-29-at-1-31-07-pm",
//     "status": "inherit",
//     "uploadedTo": 2224,
//     "date": "2015-01-29T21:31:55.000Z",
//     "modified": "2015-01-29T21:31:55.000Z",
//     "menuOrder": 0,
//     "mime": "image/png",
//     "type": "image",
//     "subtype": "png",
//     "icon": "http://storyform.co/demos/wp-includes/images/media/default.png",
//     "dateFormatted": "2015/01/29",
//     "nonces": {
//         "update": "8b105bad9f",
//         "delete": "6e8e17ec1b",
//         "edit": "b853dbf4a0"
//     },
//     "editLink": "http://storyform.co/demos/wp-admin/post.php?post=2226&action=edit",
//     "meta": false,
//     "authorName": "narrative",
//     "uploadedToLink": "http://storyform.co/demos/wp-admin/post.php?post=2224&action=edit",
//     "uploadedToTitle": "Understanding <strong>Big Data</strong>",
//     "filesizeInBytes": 1184145,
//     "filesizeHumanReadable": "1 MB",
//     "sizes": {
//         "full": {
//             "url": "http://storyform.co/demos/wp-content/uploads/2015/01/Screen-Shot-2015-01-29-at-1.31.07-PM.png",
//             "height": 842,
//             "width": 1300,
//             "orientation": "landscape"
//         }
//     },
//     "height": 842,
//     "width": 1300,
//     "orientation": "landscape",
//     "compat": {
//         "item": "<input type=\"hidden\" name=\"attachments[2226][menu_order]\" value=\"0\" /><table class=\"compat-attachment-fields\">\t\t<tr class='compat-field-storyform_areas'>\t\t\t<th scope='row' class='label'><label for='attachments-2226-storyform_areas'><span class='alignleft'>Crop/Caption areas</span><br class='clear' /></label></th>\n\t\t\t<td class='field'><div><button class=\"button-primary\" id=\"storyform-add-overlay\" data-textContent-multiple=\"Edit crop/caption area(s)\" data-textContent=\"Add caption/crop area\"></button><script> \n\t\t\t\t(function(){\n\t\t\t\t\tvar id = \"2226\";\n\t\t\t\t\tvar url = \"http://storyform.co/demos/wp-content/uploads/2015/01/Screen-Shot-2015-01-29-at-1.31.07-PM.png\";\n\t\t\t\t\tvar areas = {\n\t\t\t\t\t\tcrop: \"rect 0.07074309185959671 0.06553398058252427 0.90865571321882 0.866504854368932\",\n\t\t\t\t\t\tcaption: \"rect 0.6546013869000692 0 1 0.3239907969044133 dark-theme\"\n\t\t\t\t\t};\n\t\t\t\t\tstoryform.initAttachmentFields && storyform.initAttachmentFields(id, url, areas);\n\t\t\t\t})()\n\t\t\t\t\n\t\t\t</script></div></td>\n\t\t</tr>\n</table>",
//         "meta": ""
//     }
// }"

}).call(this,typeof global !== "undefined" ? global : typeof self !== "undefined" ? self : typeof window !== "undefined" ? window : {})
},{"window-messages":11}]},{},[9,12]);
