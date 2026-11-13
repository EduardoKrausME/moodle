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

/**
 * Javascript controller for the "Grading" panel at the right of the page.
 *
 * @module     mod_assign/grading_panel
 * @copyright  2016 Damyon Wiese <damyon@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      3.1
 */
define([
    'jquery',
    'core/yui',
    'core/notification',
    'core/templates',
    'core/fragment',
    'core/ajax',
    'core/str',
    'mod_assign/grading_form_change_checker',
    'mod_assign/grading_save_state',
    'mod_assign/grading_assessment',
    'mod_assign/grading_events',
    'mod_assign/optional_marker',
    'core_form/events',
    'core/toast',
    'core_form/changechecker',
], function(
    $,
    Y,
    notification,
    templates,
    fragment,
    ajax,
    str,
    checker,
    GradingSaveState,
    GradingAssessment,
    GradingEvents,
    OptionalMarker,
    FormEvents,
    Toast,
    FormChangeChecker
) {

    /**
     * GradingPanel class.
     *
     * @class mod_assign/grading_panel
     * @param {String} selector The selector for the page region containing the user navigation.
     */
    var GradingPanel = function(selector) {
        this._regionSelector = selector;
        this._region = $(selector);
        this._userCache = [];
        this._saveState = new GradingSaveState();
        this._confirmingOptionalMarker = false;

        this.registerEventListeners();
    };

    /** @property {String} Selector for the page region containing the user navigation. */
    GradingPanel.prototype._regionSelector = null;

    /** @property {Integer} Remember the last user id to prevent unnessecary reloads. */
    GradingPanel.prototype._lastUserId = 0;

    /** @property {Integer} Remember the last attempt number to prevent unnessecary reloads. */
    GradingPanel.prototype._lastAttemptNumber = -1;

    /** @property {JQuery} JQuery node for the page region containing the user navigation. */
    GradingPanel.prototype._region = null;

     /** @property {Integer} The id of the next user in the grading list */
    GradingPanel.prototype.nextUserId = null;

     /** @property {Boolean} Next user exists in the grading list */
    GradingPanel.prototype.nextUser = false;

    /**
     * Fade the dom node out, update it, and fade it back.
     *
     * @private
     * @method _niceReplaceNodeContents
     * @param {JQuery} node
     * @param {String} html
     * @param {String} js
     * @return {Deferred} promise resolved when the animations are complete.
     */
    GradingPanel.prototype._niceReplaceNodeContents = function(node, html, js) {
        var promise = $.Deferred();

        node.fadeOut("fast", function() {
            templates.replaceNodeContents(node, html, js);
            node.fadeIn("fast", function() {
                promise.resolve();
            });
        });

        return promise.promise();
    };

    /**
     * Make sure all form fields have the latest saved state.
     * @private
     * @method _saveFormState
     */
    GradingPanel.prototype._saveFormState = function() {
        // Copy data from notify students checkbox which was moved out of the form.
        var checked = $('[data-region="grading-actions-form"] [name="sendstudentnotifications"]').prop("checked");
        $('.gradeform [name="sendstudentnotifications"]').val(checked);
    };

    /**
     * Make form submit via ajax.
     *
     * @private
     * @param {Object} event
     * @param {Integer} nextUserId
     * @param {Boolean} nextUser optional. Load next user in the grading list.
     * @method _submitForm
     * @fires event:formSubmittedByJavascript
     */
    GradingPanel.prototype._submitForm = function(event, nextUserId, nextUser) {
        // Never queue a second write or open multiple optional-marker prompts.
        if (this._saveState.isBusy() || this._confirmingOptionalMarker ||
                this._lastUserId <= 0 || !this._region.find('form.gradeform').length) {
            return;
        }

        // The existing optional marker confirmation remains authoritative.
        if (OptionalMarker.shouldConfirmSubmit()) {
            this._confirmingOptionalMarker = true;
            OptionalMarker.showConfirm(true).then(function(confirmed) {
                this._confirmingOptionalMarker = false;
                if (confirmed) {
                    OptionalMarker.saveEnabledState();
                    this._submitForm(event, nextUserId, nextUser);
                }
                return confirmed;
            }.bind(this)).catch(function(error) {
                this._confirmingOptionalMarker = false;
                notification.exception(error);
            }.bind(this));
            return;
        }

        const request = this._saveState.begin(this._lastUserId, this._lastAttemptNumber);
        if (!request) {
            return;
        }
        $(document).trigger('assign-grading-save-start', [request.userId]);

        // If the form has data in comment-area, then we need to save that comment
        var commentAreaElement = document.querySelector('.comment-area');
        if (commentAreaElement) {
            var commentTextAreaElement = commentAreaElement.querySelector('.db > textarea');
            if (commentTextAreaElement && commentTextAreaElement.value !== '') {
                var commentActionPostElement = commentAreaElement.querySelector('.fd a[id^="comment-action-post-"]');
                if (commentActionPostElement) {
                    commentActionPostElement.click();
                }
            }
        }

        // The form was submitted - send it via ajax instead.
        var form = $(this._region.find('form.gradeform'));

        $('[data-region="overlay"]').show();

        // Do not mark the form submitted until the server confirms success.
        // A failed or timed-out request must keep the dirty-form warning active.

        // We call this, so other modules can update the form with the latest state.
        form.trigger('save-form-state');

        // Tell all form fields we are about to submit the form.
        FormEvents.notifyFormSubmittedByJavascript(form[0]);

        // Now we get all the current values from the form.
        var data = form.serialize();
        var assignmentid = this._region.attr('data-assignmentid');

        var vars = window.location.search.substring(1).split('&');
        var marker = vars.includes('action=marker');

        // Now we can continue...
        try {
            ajax.call([{
                methodname: 'mod_assign_submit_grading_form',
                args: {assignmentid: assignmentid, userid: request.userId, marker: marker,
                    jsonformdata: JSON.stringify(data)},
                done: this._handleFormSubmissionResponse.bind(this, request, data, nextUserId, nextUser),
                fail: this._handleSaveError.bind(this, request)
            }]);
        } catch (error) {
            this._handleSaveError(request, error);
        }
    };

    /**
     * Handle form submission response.
     *
     * @private
     * @method _handleFormSubmissionResponse
     * @param {Array} formdata - submitted values
     * @param {Number} [nextUserId] The id of the user to load after the form is saved
     * @param {Boolean} [nextUser] - Whether to switch to next user in the grading list.
     * @param {Array} response List of errors.
     */
    GradingPanel.prototype._handleFormSubmissionResponse = function(request, formdata, nextUserId, nextUser, response) {
        if (!this._saveState.finish(request)) {
            return;
        }
        $('[data-region="overlay"]').hide();
        const endOfList = nextUser === false && nextUserId === null;
        if (typeof nextUserId === "undefined") {
            nextUserId = request.userId;
        }
        if (!Array.isArray(response) || response.length) {
            $(document).trigger('assign-grading-save-failed', ['validation']);
            str.get_string('errorgradechangessaveddetail', 'mod_assign')
                .then(function(str) {
                    Toast.add(str, {type: 'danger', delay: 4000});
                    return str;
                })
                .catch(notification.exception);

            // There was an error saving the grade. Re-render the form using the submitted data so we can show
            // validation errors.
            $(document).trigger('reset', [this._lastUserId, formdata, true]);
        } else {
            // The save is confirmed, but plugins or editors may have changed
            // fields after the request snapshot. Never navigate in that case.
            var form = $(this._region.find('form.gradeform'));
            form.trigger('save-form-state');
            if (form.serialize() !== formdata) {
                $(document).trigger('assign-grading-save-failed', ['newchanges']);
                return;
            }
            form.removeData('unresolved-error');
            FormChangeChecker.markFormSubmitted(form[0]);
            FormChangeChecker.resetFormDirtyState(form[0]);
            $(document).trigger('assign-grading-save-success', [request.userId]);
            str.get_string('gradechangessaveddetail', 'mod_assign')
                .then(function(message) {
                    Toast.add(message);
                }).catch(notification.exception);

            if (endOfList) {
                $(document).trigger('reset', request.userId);
                $(document).trigger('assign-grading-sequence-complete');
            } else if (nextUserId == request.userId) {
                $(document).trigger('reset', request.userId);
            } else if (nextUser) {
                $(document).trigger('done-saving-show-next', true);
            } else {
                $(document).trigger('user-changed', nextUserId);
            }
        }
    };

    /**
     * Recover a failed AJAX request without discarding the native form.
     *
     * @param {Object} request Request-local save state.
     * @param {Error} error The original AJAX failure.
     */
    GradingPanel.prototype._handleSaveError = function(request, error) {
        if (!this._saveState.finish(request)) {
            return;
        }
        $('[data-region="overlay"]').hide();
        // A transport failure is not proof that the server did not commit.
        // Preserve the current form and require the grader to verify/retry.
        this._region.find('form.gradeform').data('unresolved-error', true);
        $(document).trigger('assign-grading-save-failed', ['network']);
        notification.exception(error);
    };

    /**
     * Refresh form with default values.
     *
     * @private
     * @method _resetForm
     * @param {Event} e
     * @param {Integer} userid
     * @param {Array} formdata
     * @param {Boolean} unresolvederror
     */
    GradingPanel.prototype._resetForm = function(e, userid, formdata, unresolvederror) {
        if (this._saveState.isBusy()) {
            return;
        }
        // The form was cancelled - refresh with default values.
        var event = $.Event("custom");
        if (typeof userid == "undefined") {
            userid = this._lastUserId;
        }
        this._lastUserId = 0;
        this._refreshGradingPanel(event, userid, formdata, -1, unresolvederror);
    };

    /**
     * Open a picker to choose an older attempt.
     *
     * @private
     * @param {Object} e
     * @method _chooseAttempt
     */
    GradingPanel.prototype._chooseAttempt = function(e) {
        if (this._saveState.isBusy() || this._confirmingOptionalMarker) {
            return;
        }
        // The form is in the element pointed to by data-submissions.
        var link = $(e.target);
        var submissionsId = link.data('submissions');
        var submissionsform = $(document.getElementById(submissionsId));
        var formcopy = submissionsform.clone();
        var formhtml = formcopy.wrap($('<form/>')).html();

        str.get_strings([
            {key: 'viewadifferentattempt', component: 'mod_assign'},
            {key: 'view', component: 'core'},
            {key: 'cancel', component: 'core'},
        ]).done(function(strs) {
            notification.confirm(strs[0], formhtml, strs[1], strs[2], function() {
                var attemptnumber = $("input:radio[name='select-attemptnumber']:checked").val();
                const loadAttempt = () => this._refreshGradingPanel(null, this._lastUserId, '', attemptnumber);
                if (!checker.checkFormForChanges('[data-region="grade-panel"] .gradeform')) {
                    loadAttempt();
                    return;
                }

                // An older attempt loads an entirely new native form. Do not
                // replace an unsaved grade or feedback without explicit consent.
                str.get_strings([
                    {key: 'unsavedchanges', component: 'mod_assign'},
                    {key: 'gradingdiscardattemptquestion', component: 'mod_assign'},
                    {key: 'gradingdiscardchanges', component: 'mod_assign'},
                    {key: 'cancel', component: 'core'},
                ]).then(function(labels) {
                    notification.confirm(labels[0], labels[1], labels[2], labels[3], loadAttempt);
                    return labels;
                }).catch(notification.exception);
            }.bind(this));
        }.bind(this)).fail(notification.exception);
    };

    /**
     * Add popout buttons
     *
     * @private
     * @method _addPopoutButtons
     * @param {JQuery} selector The region selector to add popout buttons to.
     */
    GradingPanel.prototype._addPopoutButtons = function(selector) {
        var region = $(selector);

        templates.render('mod_assign/popout_button', {}).done(function(html) {
            var parents = region.find('[data-fieldtype="filemanager"],[data-fieldtype="editor"],[data-fieldtype="grading"]')
                    .closest('.fitem');
            parents.addClass('has-popout').find('label:first').parent().append(html);

            region.on('click', '[data-region="popout-button"]', this._togglePopout.bind(this));
        }.bind(this)).fail(notification.exception);
    };

    /**
     * Make a div "popout" or "popback".
     *
     * @private
     * @method _togglePopout
     * @param {Event} event
     */
    GradingPanel.prototype._togglePopout = function(event) {
        event.preventDefault();
        var container = $(event.target).closest('.fitem');
        if (container.hasClass('popout')) {
            $('.popout').removeClass('popout');
        } else {
            $('.popout').removeClass('popout');
            container.addClass('popout');
            container.addClass('moodle-has-zindex');
        }
    };

    /**
     * Get the user context - re-render the template in the page.
     *
     * @private
     * @method _refreshGradingPanel
     * @param {Event} event
     * @param {Number} userid
     * @param {String} submissiondata serialised submission data.
     * @param {Integer} attemptnumber
     * @param {Boolean} unresolvederror
     */
    GradingPanel.prototype._refreshGradingPanel = function(event, userid, submissiondata, attemptnumber, unresolvederror) {
        if (this._saveState.isBusy()) {
            return;
        }
        var contextid = this._region.attr('data-contextid');
        if (typeof submissiondata === 'undefined') {
            submissiondata = '';
        }
        if (typeof attemptnumber === 'undefined') {
            attemptnumber = -1;
        }
        if (typeof unresolvederror === 'undefined') {
            unresolvederror = false;
        }
        // Skip reloading if it is the same user.
        if (this._lastUserId == userid && this._lastAttemptNumber == attemptnumber && submissiondata === '') {
            return;
        }
        this._lastUserId = userid;
        this._lastAttemptNumber = attemptnumber;
        $(document).trigger('start-loading-user');
        // Tell behat to back off too.
        window.M.util.js_pending('mod-assign-loading-user');
        // First insert the loading template.
        templates.render('mod_assign/loading', {}).done(function(html, js) {
            // Update the page.
            this._niceReplaceNodeContents(this._region, html, js).done(function() {
                if (userid > 0) {
                    this._region.show();
                    var vars = window.location.search.substring(1).split('&');
                    var marker = vars.includes('action=marker');
                    // Reload the grading form "fragment" for this user.
                    var params = {userid: userid, attemptnumber: attemptnumber,
                        jsonformdata: JSON.stringify(submissiondata),
                        marker: marker};
                    fragment.loadFragment('mod_assign', 'gradingpanel', contextid, params).done(function(html, js) {

                        // Reset whole grading page when there is a failure in retrieving the html
                        // i.e. user no longer under "requires grading" filter when graded
                        if (html === '') {
                            $(document).trigger('reset-table', true);
                        }

                        this._niceReplaceNodeContents(this._region, html, js)
                        .done(function() {
                            checker.saveFormState('[data-region="grade-panel"] .gradeform');
                            // Read only the real, freshly rendered grading controls;
                            // never duplicate native rubric, guide or marker inputs.
                            GradingAssessment.init(this._region.find('form.gradeform')[0]);
                            OptionalMarker.saveEnabledState();
                            $(document).on('editor-content-restored', function() {
                                // If the editor has some content that has been restored
                                // then save the form state again for comparison.
                                checker.saveFormState('[data-region="grade-panel"] .gradeform');
                            });
                            $('[data-region="attempt-chooser"]').on('click', this._chooseAttempt.bind(this));
                            this._addPopoutButtons('[data-region="grade-panel"] .gradeform');
                            if (unresolvederror) {
                                $('[data-region="grade-panel"] .gradeform').data('unresolved-error', true);
                            }
                            $(document).trigger('finish-loading-user');
                            // Tell behat we are friends again.
                            window.M.util.js_complete('mod-assign-loading-user');
                        }.bind(this))
                        .fail(notification.exception);
                    }.bind(this)).fail(notification.exception);
                    $('[data-region="review-panel"]').show();
                } else {
                    this._region.hide();
                    $('[data-region="review-panel"]').hide();
                    $(document).trigger('finish-loading-user');
                    // Tell behat we are friends again.
                    window.M.util.js_complete('mod-assign-loading-user');
                }
            }.bind(this));
        }.bind(this)).fail(notification.exception);
    };

    /**
     * Get next user data and store it in global variables
     *
     * @private
     * @method _getNextUser
     * @param {Event} event
     * @param {Object} data Next user's data
     */
    GradingPanel.prototype._getNextUser = function(event, data) {
        this.nextUserId = data.nextUserId;
        this.nextUser = data.nextUser;
    };

    /**
     * Handle the save-and-show-next event
     *
     * @private
     * @method _handleSaveAndShowNext
     */
    GradingPanel.prototype._handleSaveAndShowNext = function() {
        // An explicit null records "no next participant" separately from a
        // normal Save action, which passes an undefined destination.
        this._submitForm(null, this.nextUserId ?? null, this.nextUser);
    };

    /**
     * Get the grade panel element.
     *
     * @method getPanelElement
     * @return {jQuery}
     */
    GradingPanel.prototype.getPanelElement = function() {
        return $('[data-region="grade-panel"]');
    };

    /**
     * Hide the grade panel.
     *
     * @method collapsePanel
     */
    GradingPanel.prototype.collapsePanel = function() {
        this.getPanelElement().addClass('collapsed');
    };

    /**
     * Show the grade panel.
     *
     * @method expandPanel
     */
    GradingPanel.prototype.expandPanel = function() {
        this.getPanelElement().removeClass('collapsed');
    };

    /**
     * Register event listeners for the grade panel.
     *
     * @method registerEventListeners
     */
    GradingPanel.prototype.registerEventListeners = function() {
        var docElement = $(document);
        var region = $(this._region);
        // Add an event listener to prevent form submission when pressing enter key.
        region.on('submit', 'form', function(e) {
            e.preventDefault();
        });

        docElement.on('next-user', this._getNextUser.bind(this));
        docElement.on('user-changed', this._refreshGradingPanel.bind(this));
        docElement.on('save-changes', this._submitForm.bind(this));
        docElement.on('save-and-show-next', this._handleSaveAndShowNext.bind(this));
        docElement.on('reset', this._resetForm.bind(this));

        docElement.on('save-form-state', this._saveFormState.bind(this));

        docElement.on(GradingEvents.COLLAPSE_GRADE_PANEL, function() {
            this.collapsePanel();
        }.bind(this));

        // We should expand if the review panel is collapsed.
        docElement.on(GradingEvents.COLLAPSE_REVIEW_PANEL, function() {
            this.expandPanel();
        }.bind(this));

        docElement.on(GradingEvents.EXPAND_GRADE_PANEL, function() {
            this.expandPanel();
        }.bind(this));
    };

    return GradingPanel;
});
