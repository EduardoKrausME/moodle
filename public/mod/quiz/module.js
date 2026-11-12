// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.
/* eslint camelcase: off */

/**
 * JavaScript library for the quiz module.
 *
 * @package    mod
 * @subpackage quiz
 * @copyright  1999 onwards Martin Dougiamas  {@link http://moodle.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

M.mod_quiz = M.mod_quiz || {};

M.mod_quiz.init_attempt_form = function(Y) {
    require(['core_question/question_engine'], function(qEngine) {
        qEngine.initForm('#responseform');
    });
    Y.on('submit', M.mod_quiz.timer.stop, '#responseform');
    require(['core_form/changechecker'], function(FormChangeChecker) {
        FormChangeChecker.watchFormById('responseform');
    });
};

M.mod_quiz.init_review_form = function(Y) {
    require(['core_question/question_engine'], function(qEngine) {
        qEngine.initForm('.questionflagsaveform');
    });
    Y.on('submit', function(e) { e.halt(); }, '.questionflagsaveform');
};

M.mod_quiz.init_comment_popup = function(Y) {
    // Add a close button to the window.
    var closebutton = Y.Node.create('<input type="button" class="btn btn-secondary" />');
    closebutton.set('value', M.util.get_string('cancel', 'moodle'));
    Y.one('#id_submitbutton').ancestor().append(closebutton);
    Y.on('click', function() { window.close() }, closebutton);
}

// Code for updating the countdown timer that is used on timed quizzes.
M.mod_quiz.timer = {
    Y: null,
    // Client display deadline is initialised with the time left supplied by Moodle.
    endtime: 0,
    preview: 0,
    timeoutid: null,
    threshold: 3000,
    warningThreshold: 300,
    criticalThreshold: 100,
    state: null,
    visible: true,
    stopped: false,
    lastDisplayedSeconds: null,

    /**
     * Initialise the native timer and preference handling.
     * @param {Object} Y YUI instance.
     * @param {Number} start Time remaining in seconds from Moodle.
     * @param {Boolean} preview Whether this attempt is a preview.
     */
    init: function(Y, start, preview) {
        this.Y = Y;
        this.endtime = M.pageloadstarttime.getTime() + start * 1000;
        this.preview = preview;
        this.stopped = false;
        this.state = null;
        this.visible = true;
        this.lastDisplayedSeconds = null;
        Y.one('#quiz-timer-wrapper').setStyle('display', 'flex');

        require(['core_form/changechecker'], function(FormChangeChecker) {
            M.mod_quiz.timer.FormChangeChecker = FormChangeChecker;
        });
        Y.one('#toggle-timer').on('click', function() {
            M.mod_quiz.timer.toggleVisibility();
        });

        // The native preference is not a way to hide the final 100 seconds.
        require(['core_user/repository'], function(UserRepository) {
            UserRepository.getUserPreference('quiz_timerhidden')
                .then((response) => {
                    M.mod_quiz.timer.setVisibility(response !== '1', false);
                    return;
                }).catch((error) => {
                    M.mod_quiz.timer.setVisibility(true, false);
                    Y.log(error, 'error', 'moodle-mod_quiz');
                });
        });

        // Browser throttling cannot extend a deadline: compare actual timestamps
        // again when a background tab becomes visible.
        document.addEventListener('visibilitychange', function() {
            if (!document.hidden && !M.mod_quiz.timer.stopped) {
                M.mod_quiz.timer.update();
            }
        });
        this.update();
    },

    toggleVisibility: function() {
        this.setVisibility(!this.visible);
    },

    /**
     * Only the display and the existing preference can be changed here.
     * @param {Boolean} visible Whether to show the remaining time.
     * @param {Boolean} updatePref Whether to store the user preference.
     */
    setVisibility: function(visible, updatePref = true) {
        var timer = this.Y.one('#quiz-time-left');
        var button = this.Y.one('#toggle-timer');
        if (this.state === 'critical' || this.state === 'expired') {
            visible = true;
            updatePref = false;
        }
        this.visible = visible;
        if (visible) {
            button.setContent(M.util.get_string('hide', 'moodle'));
            button.setAttribute('aria-label', M.util.get_string('timerhide', 'quiz'));
            timer.show();
        } else {
            button.setContent(M.util.get_string('show', 'moodle'));
            button.setAttribute('aria-label', M.util.get_string('timershow', 'quiz'));
            timer.hide();
        }
        button.setAttribute('aria-expanded', visible ? 'true' : 'false');

        if (updatePref) {
            require(['core_user/repository'], function(UserRepository) {
                UserRepository.setUserPreference('quiz_timerhidden', visible ? '0' : '1');
            });
        }
    },

    /**
     * Announce a time milestone once instead of every second.
     * @param {String} state normal, warning, critical, expired.
     */
    setState: function(state) {
        if (this.state === state) {
            return;
        }
        this.state = state;
        this.Y.one('#quiz-timer').setAttribute('data-time-state', state);
        var key = '';
        if (state === 'warning') {
            key = 'timerwarning';
        } else if (state === 'critical') {
            key = 'timercritical';
        } else if (state === 'expired') {
            key = 'timesup';
        }
        var message = key ? M.util.get_string(key, 'quiz') : '';
        this.Y.one('#quiz-timer-phase').set('text', message);
        this.Y.one('#quiz-timer-announcement').set('text', message);

        var button = this.Y.one('#toggle-timer');
        if (state === 'critical' || state === 'expired') {
            // An asynchronously loaded "hidden" preference cannot undo this.
            this.setVisibility(true, false);
            button.setAttribute('disabled', true);
        } else {
            button.removeAttribute('disabled');
        }
    },

    stop: function() {
        this.stopped = true;
        if (this.timeoutid) {
            clearTimeout(this.timeoutid);
            this.timeoutid = null;
        }
    },

    two_digit: function(num) {
        return num < 10 ? '0' + num : String(num);
    },

    // Use the native expiry form submission; visual states do not change any limit.
    update: function() {
        if (M.mod_quiz.timer.stopped || !M.mod_quiz.timer.Y) {
            return;
        }
        // An autosave deadline correction or visibility event may call update
        // before the previous callback executes. Keep one scheduled loop only.
        if (M.mod_quiz.timer.timeoutid) {
            clearTimeout(M.mod_quiz.timer.timeoutid);
            M.mod_quiz.timer.timeoutid = null;
        }
        var timer = M.mod_quiz.timer;
        var Y = timer.Y;
        var secondsleft = Math.floor((timer.endtime - new Date().getTime()) / 1000);
        if (secondsleft < 0) {
            timer.setState('expired');
            timer.stop();
            Y.one('#quiz-time-left').setContent(M.util.get_string('timesup', 'quiz'));
            var input = Y.one('input[name=timeup]');
            input.set('value', 1);
            var form = input.ancestor('form');
            if (form.one('input[name=finishattempt]')) {
                form.one('input[name=finishattempt]').set('value', 0);
            }
            // An exceptionally short attempt can expire before async loading ends.
            if (timer.FormChangeChecker) {
                timer.FormChangeChecker.markFormSubmitted(input.getDOMNode());
            }
            form.submit();
            return;
        }

        if (secondsleft < timer.criticalThreshold) {
            timer.setState('critical');
        } else if (secondsleft <= timer.warningThreshold) {
            timer.setState('warning');
        } else {
            timer.setState('normal');
        }

        // Update once per visible second; a 100ms timeout still detects expiry promptly.
        if (secondsleft !== timer.lastDisplayedSeconds) {
            timer.lastDisplayedSeconds = secondsleft;
            var hours = Math.floor(secondsleft / 3600);
            var minutes = Math.floor((secondsleft % 3600) / 60);
            var seconds = secondsleft % 60;
            Y.one('#quiz-time-left').setContent(hours + ':' + timer.two_digit(minutes) +
                    ':' + timer.two_digit(seconds));
        }
        timer.timeoutid = setTimeout(timer.update, 100);
    },

    // Keep the server-derived autosave correction and native three-second tolerance.
    updateEndTime: function(timeleft) {
        if (M.mod_quiz.timer.stopped) {
            return;
        }
        var newtimeleft = new Date().getTime() + timeleft * 1000;
        if (M.mod_quiz.timer.Y === null) {
            M.mod_quiz.timer.init(window.Y, timeleft, 0);
        }
        if (Math.abs(newtimeleft - M.mod_quiz.timer.endtime) > M.mod_quiz.timer.threshold) {
            M.mod_quiz.timer.endtime = newtimeleft;
            M.mod_quiz.timer.update();
        }
    }
};

M.mod_quiz.filesUpload = {
    /**
     * YUI object.
     */
    Y: null,

    /**
     * Number of files uploading.
     */
    numberFilesUploading: 0,

    /**
     * Disable navigation block when uploading and enable navigation block when all files are uploaded.
     */
    disableNavPanel: function() {
        var quizNavigationBlock = document.getElementById('mod_quiz_navblock');
        if (quizNavigationBlock) {
            if (M.mod_quiz.filesUpload.numberFilesUploading) {
                quizNavigationBlock.classList.add('nav-disabled');
            } else {
                quizNavigationBlock.classList.remove('nav-disabled');
            }
        }
    }
};

M.mod_quiz.nav = M.mod_quiz.nav || {};

M.mod_quiz.nav.update_flag_state = function(attemptid, questionid, newstate) {
    var Y = M.mod_quiz.nav.Y;
    var navlink = Y.one('#quiznavbutton' + questionid);
    navlink.removeClass('flagged');
    if (newstate == 1) {
        navlink.addClass('flagged');
        navlink.one('.accesshide .flagstate').setContent(M.util.get_string('flagged', 'question'));
    } else {
        navlink.one('.accesshide .flagstate').setContent('');
    }
};

M.mod_quiz.nav.init = function(Y) {
    M.mod_quiz.nav.Y = Y;

    Y.all('#quiznojswarning').remove();

    var form = Y.one('#responseform');
    if (form) {
        function nav_to_page(pageno) {
            Y.one('#followingpage').set('value', pageno);

            // Automatically submit the form. We do it this strange way because just
            // calling form.submit() does not run the form's submit event handlers.
            var submit = form.one('input[name="next"]');
            submit.set('name', '');
            submit.getDOMNode().click();
        };

        Y.delegate('click', function(e) {
            if (this.hasClass('thispage')) {
                return;
            }

            e.preventDefault();

            var pageidmatch = this.get('href').match(/page=(\d+)/);
            var pageno;
            if (pageidmatch) {
                pageno = pageidmatch[1];
            } else {
                pageno = 0;
            }

            var questionidmatch = this.get('href').match(/#question-(\d+)-(\d+)/);
            if (questionidmatch) {
                form.set('action', form.get('action') + questionidmatch[0]);
            }

            nav_to_page(pageno);
        }, document.body, 'a.qnbutton');
    }

    if (Y.one('a.endtestlink')) {
        Y.on('click', function(e) {
            e.preventDefault();
            nav_to_page(-1);
        }, 'a.endtestlink');
    }

    // Navigation buttons should be disabled when the files are uploading.
    require(['core_form/events'], function(formEvent) {
        document.addEventListener(formEvent.eventTypes.uploadStarted, function() {
            M.mod_quiz.filesUpload.numberFilesUploading++;
            M.mod_quiz.filesUpload.disableNavPanel();
        });

        document.addEventListener(formEvent.eventTypes.uploadCompleted, function() {
            M.mod_quiz.filesUpload.numberFilesUploading--;
            M.mod_quiz.filesUpload.disableNavPanel();
        });
    });

    if (M.core_question_flags) {
        M.core_question_flags.add_listener(M.mod_quiz.nav.update_flag_state);
    }
};

M.mod_quiz.secure_window = {
    init: function(Y) {
        if (window.location.href.substring(0, 4) == 'file') {
            window.location = 'about:blank';
        }
        Y.delegate('contextmenu', M.mod_quiz.secure_window.prevent, document, '*');
        Y.delegate('mousedown',   M.mod_quiz.secure_window.prevent_mouse, 'body', '*');
        Y.delegate('mouseup',     M.mod_quiz.secure_window.prevent_mouse, 'body', '*');
        Y.delegate('dragstart',   M.mod_quiz.secure_window.prevent, document, '*');
        Y.delegate('selectstart', M.mod_quiz.secure_window.prevent_selection, document, '*');
        Y.delegate('cut',         M.mod_quiz.secure_window.prevent, document, '*');
        Y.delegate('copy',        M.mod_quiz.secure_window.prevent, document, '*');
        Y.delegate('paste',       M.mod_quiz.secure_window.prevent, document, '*');
        Y.on('beforeprint', function() {
            Y.one(document.body).setStyle('display', 'none');
        }, window);
        Y.on('afterprint', function() {
            Y.one(document.body).setStyle('display', 'block');
        }, window);
        Y.on('key', M.mod_quiz.secure_window.prevent, '*', 'press:67,86,88+ctrl');
        Y.on('key', M.mod_quiz.secure_window.prevent, '*', 'up:67,86,88+ctrl');
        Y.on('key', M.mod_quiz.secure_window.prevent, '*', 'down:67,86,88+ctrl');
        Y.on('key', M.mod_quiz.secure_window.prevent, '*', 'press:67,86,88+meta');
        Y.on('key', M.mod_quiz.secure_window.prevent, '*', 'up:67,86,88+meta');
        Y.on('key', M.mod_quiz.secure_window.prevent, '*', 'down:67,86,88+meta');
    },

    is_content_editable: function(n) {
        if (n.test('[contenteditable=true]')) {
            return true;
        }
        n = n.get('parentNode');
        if (n === null) {
            return false;
        }
        return M.mod_quiz.secure_window.is_content_editable(n);
    },

    prevent_selection: function(e) {
        return false;
    },

    prevent: function(e) {
        alert(M.util.get_string('functiondisabledbysecuremode', 'quiz'));
        e.halt();
    },

    prevent_mouse: function(e) {
        if (e.button == 1 && /^(INPUT|TEXTAREA|BUTTON|SELECT|LABEL|A)$/i.test(e.target.get('tagName'))) {
            // Left click on a button or similar. No worries.
            return;
        }
        if (e.button == 1 && M.mod_quiz.secure_window.is_content_editable(e.target)) {
            // Left click in Atto or similar.
            return;
        }
        e.halt();
    },

    /**
     * Initialize the event listener for the secure window close button
     *
     * @param {Object} Y YUI instance. When called from renderer, this parameter precedes the others
     * @param {String} url
     */
    init_close_button: function(Y, url) {
        Y.on('click', function(e) {
            M.mod_quiz.secure_window.close(Y, url, 0);
        }, '#secureclosebutton');
    },

    /**
     * Close the secure window, or redirect to URL if the opener is no longer present
     *
     * @param {Object} Y YUI instance. When called from renderer, this parameter precedes the others
     * @param {String} url
     * @param {Number} delay
     */
    close: function(Y, url, delay) {
        setTimeout(function() {
            if (window.opener) {
                window.opener.document.location.reload();
                window.close();
            } else {
                window.location.href = url;
            }
        }, delay*1000);
    }
};
