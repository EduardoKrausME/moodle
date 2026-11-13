<?php
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
 * Output the grading actionbar for this activity.
 *
 * @package   mod_assign
 * @copyright 2021 Adrian Greeve <adrian@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_assign\output;

use assign;
use context_module;
use core_course\output\actionbar\group_selector;
use core_course\output\actionbar\user_selector;
use templatable;
use renderable;
use moodle_url;
use core\output\local\dropdown\dialog;

/**
 * Output the grading actionbar for this activity.
 *
 * @package   mod_assign
 * @copyright 2021 Adrian Greeve <adrian@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class grading_actionmenu implements templatable, renderable {

    /** @var int Course module ID. */
    protected int $cmid;
    /** @var bool If any submission plugins are enabled. */
    protected bool $submissionpluginenabled;
    /** @var int The number of submissions made. */
    protected int $submissioncount;
    /** @var assign The assign instance. */
    protected assign $assign;

    /** @var bool If there are submissions to download. */
    protected bool $showdownload;

    /** @var array Applied user initials filters, containing 'firstname' and 'lastname'. **/
    protected array $userinitials;

    /** @var bool true if the user has this capability. Otherwise false. */
    private bool $hasviewblind;

    /**
     * Constructor for this object.
     *
     * @param int $cmid Course module ID.
     * @param null|bool $submissionpluginenabled This parameter has been deprecated since 4.5 and should not be used anymore.
     * @param null|int $submissioncount This parameter has been deprecated since 4.5 and should not be used anymore.
     * @param assign|null $assign The assign instance. If not provided, it will be loaded based on the cmid.
     * @param array $userinitials The user initials to filter the table by.
     */
    public function __construct(
        int $cmid,
        ?bool $submissionpluginenabled = null,
        ?int $submissioncount = null,
        ?assign $assign = null,
        array $userinitials = []
    ) {
        $this->cmid = $cmid;
        if (!$assign) {
            $context = context_module::instance($cmid);
            $assign = new assign($context, null, null);
        }
        $this->assign = $assign;
        $this->showdownload = $this->assign->is_any_submission_plugin_enabled() && $this->assign->count_submissions();
        $this->userinitials = $userinitials;

        // Check if we have the elevated view capablities to see the blind details.
        $this->hasviewblind = has_capability('mod/assign:viewblinddetails', $this->assign->get_context());
    }

    /**
     * Data to render in a template.
     *
     * @param \renderer_base $output renderer base output.
     * @return array Data to render.
     */
    public function export_for_template(\renderer_base $output): array {
        global $PAGE, $USER;

        $course = $this->assign->get_course();
        $cm = get_coursemodule_from_id('assign', $this->cmid);
        $data = [];

        $userid = optional_param('userid', null, PARAM_INT);
        // Never resolve a real name for a blind marking search label unless the
        // teacher is permitted to view identities.
        $isblind = $this->assign->is_blind_marking() && !$this->hasviewblind;
        $searchtext = optional_param('search', '', PARAM_NOTAGS);
        $usersearch = $userid ? ($isblind ?
            get_string('hiddenuser', 'assign') . $this->assign->get_uniqueid_for_user($userid) :
            fullname(\core_user::get_user($userid))) : $searchtext;

        $resetlink = new moodle_url('/mod/assign/view.php', ['id' => $this->cmid, 'action' => 'grading']);
        $groupid = groups_get_course_group($course, true);
        $userselector = new user_selector(
            course: $course,
            resetlink: $resetlink,
            userid: !$isblind ? $userid : null,
            groupid: $groupid,
            usersearch: $usersearch,
            instanceid: $this->assign->get_instance()->id
        );
        $data['userselector'] = $userselector->export_for_template($output);

        $activitygroup = groups_get_activity_group($cm);
        $statusfilter = get_user_preferences('assign_filter', '');
        $filterstate = [
            'group' => $activitygroup,
            'userid' => $userid,
            'search' => $userid ? '' : $searchtext,
            'status' => $this->assign->is_any_submission_plugin_enabled() ? $statusfilter : '',
            'tifirst' => $this->userinitials['firstname'] ?? '',
            'tilast' => $this->userinitials['lastname'] ?? '',
        ];

        $statuslabels = [];
        if ($this->assign->is_any_submission_plugin_enabled()) {
            foreach ($this->assign->get_filters() as $filter) {
                if ($filter['key'] !== ASSIGN_FILTER_NONE) {
                    $statuslabels[$filter['key']] = $filter['name'];
                }
            }
        }
        $labels = [];
        if ($userid || $searchtext !== '') {
            $labels['user'] = get_string('gradingfiltersearch', 'assign', $usersearch);
        }
        if ($activitygroup && groups_get_activity_groupmode($cm, $course)) {
            $labels['group'] = get_string('gradingfiltergroup', 'assign', groups_get_group_name($activitygroup));
        }
        if (isset($statuslabels[$statusfilter])) {
            $labels['status'] = get_string('gradingfilterstatus', 'assign', $statuslabels[$statusfilter]);
        }
        if (!empty($filterstate['tifirst'])) {
            $labels['tifirst'] = get_string('gradingfilterfirstname', 'assign', $filterstate['tifirst']);
        }
        if (!empty($filterstate['tilast'])) {
            $labels['tilast'] = get_string('gradingfilterlastname', 'assign', $filterstate['tilast']);
        }

        $workflowoptions = $this->assign->get_marking_workflow_filters();
        $workflowfilter = get_user_preferences('assign_workflowfilter', '');
        if ($workflowfilter !== '' && array_key_exists($workflowfilter, $workflowoptions)) {
            $filterstate['workflowfilter'] = $workflowfilter;
            $labels['workflowfilter'] = get_string('gradingfilterworkflow', 'assign',
                $workflowoptions[$workflowfilter]);
        }

        $markeroptions = $this->assign->get_marking_allocation_filters();
        $markerfilter = get_user_preferences('assign_markerfilter', '');
        if ($markerfilter !== '' && array_key_exists($markerfilter, $markeroptions)) {
            $filterstate['markingallocationfilter'] = $markerfilter;
            $labels['markingallocationfilter'] = get_string('gradingfiltermarker', 'assign',
                $markeroptions[$markerfilter]);
        }
        if (has_capability('moodle/course:viewsuspendedusers', $this->assign->get_context()) &&
                !$this->assign->show_only_active_users()) {
            $filterstate['suspendedparticipantsfilter'] = 1;
            $labels['suspendedparticipantsfilter'] = get_string('gradingfiltersuspended', 'assign');
        }

        $navigationparams = \mod_assign\local\grading_filter_state::parameters($this->cmid, $filterstate);
        $chips = \mod_assign\local\grading_filter_state::chips($this->cmid, $filterstate, $labels);
        $data['hasactivefilters'] = !empty($chips);
        $data['activefilters'] = [];
        foreach ($chips as $chip) {
            $chip['url'] = (new moodle_url('/mod/assign/view.php', $chip['params']))->out(false);
            $chip['removelabel'] = get_string('gradingfilterremove', 'assign', $chip['label']);
            unset($chip['params']);
            $data['activefilters'][] = $chip;
        }
        if ($chips) {
            $data['pagereset'] = (new moodle_url('/mod/assign/view.php',
                \mod_assign\local\grading_filter_state::reset($this->cmid)))->out(false);
        }

        // The initials selector must carry the validated status, group, search
        // and advanced preferences, rather than reverting to a partial URL.
        $additionalparams = $navigationparams;
        unset($additionalparams['tifirst'], $additionalparams['tilast']);

        $initialselector = new \core_course\output\actionbar\initials_selector(
            course: $course,
            targeturl: 'mod/assign/view.php',
            firstinitial: $this->userinitials['firstname'] ?? '',
            lastinitial: $this->userinitials['lastname'] ?? '',
            firstinitialparam: 'tifirst',
            lastinitialparam: 'tilast',
            additionalparams: $additionalparams
        );

        $data['initialselector'] = $initialselector->export_for_template($output);

        if (groups_get_activity_groupmode($cm, $course)) {
            $gs = new group_selector($PAGE->context, false);
            $data['groupselector'] = $gs->export_for_template($output);
        }

        if ($extrafiltersdropdown = $this->get_extra_filters_dropdown($navigationparams)) {
            $PAGE->requires->js_call_amd('mod_assign/actionbar/grading/extra_filters_dropdown', 'init', []);
            $data['extrafiltersdropdown'] = $extrafiltersdropdown->export_for_template($output);
        }

        if ($this->assign->is_any_submission_plugin_enabled()) {
            ['statusmenu' => $statusmenu, 'currentvalue' => $currentvalue] =
                $this->get_status_menu($navigationparams);
            $statusselect = new \core\output\select_menu('status', $statusmenu, $currentvalue, true);
            $statusselect->set_label(get_string('status', 'mod_assign'), [], true);
            $data['statusselector'] = $statusselect->export_for_template($output);
        }

        if ($this->assign->can_grade()) {
            $url = new moodle_url('/mod/assign/view.php', [
                'id' => $this->assign->get_course_module()->id,
                'action' => 'grader',
            ]);
            $data['graderurl'] = $url->out(false);
        }

        $markingworkflow = property_exists($this->assign->get_instance(), 'markingworkflow') &&
            $this->assign->get_instance()->markingworkflow == '1' &&
            $this->assign->is_user_allocated_marker($USER->id);

        if ($markingworkflow) {
            $url = new moodle_url('/mod/assign/view.php', [
                'id' => $this->assign->get_course_module()->id,
                'action' => 'marker',
            ]);
            $data['markerurl'] = $url->out(false);
        }

        $gradingmanager = get_grading_manager($this->assign->get_context(), 'mod_assign', 'submissions');
        $controller = $gradingmanager->get_active_controller();
        $showquickgrading = empty($controller) && $this->assign->can_grade();
        if ($showquickgrading) {
            $quickgradingbaseurl = new moodle_url('/mod/assign/view.php', $navigationparams);

            $data['quickgrading'] = [
                'baseurl' => $quickgradingbaseurl->out(false),
                'enabled' => get_user_preferences('assign_quickgrading', false),
            ];
        }

        if ($this->showdownload) {
            $downloadasfoldersbaseurl = new moodle_url('/mod/assign/view.php', $navigationparams);
            $downloadasfolders = get_user_preferences('assign_downloadasfolders', 1);
            $data['downloadasfolders'] = [
                'baseurl' => $downloadasfoldersbaseurl->out(false),
                'enabled' => $downloadasfolders,
            ];
        }

        $actions = $this->get_actions();
        if ($actions) {
            $menu = new \action_menu();
            $menu->set_menu_trigger(get_string('actions'), 'btn btn-outline-primary');
            foreach ($actions as $groupkey => $actiongroup) {
                foreach ($actiongroup as $label => $url) {
                    $menu->add(new \action_menu_link_secondary(new \moodle_url($url), null, $label));
                }
                if ($groupkey !== array_key_last($actions)) {
                    $divider = new \action_menu_filler();
                    $divider->primary = false;
                    $menu->add($divider);
                }
            }

            $renderer = $PAGE->get_renderer('core');
            $data['actions'] = $renderer->render($menu);
        }

        return $data;
    }

    /**
     * Get the actions for the grading action menu.
     *
     * @return array A 2D array of actions grouped by a key in the form of key => label => URL.
     */
    private function get_actions() {
        $actions = [];
        if (
            has_capability('gradereport/grader:view', $this->assign->get_course_context())
            && has_capability('moodle/grade:viewall', $this->assign->get_course_context())
        ) {
            $url = new moodle_url('/grade/report/grader/index.php', ['id' => $this->assign->get_course()->id]);
            $actions['gradebook'][get_string('viewgradebook', 'assign')] = $url->out(false);
        }
        if ($this->assign->is_blind_marking() && has_capability('mod/assign:revealidentities', $this->assign->get_context())) {
            $url = new moodle_url('/mod/assign/view.php', [
                'id' => $this->assign->get_course_module()->id,
                'action' => 'revealidentities',
            ]);
            $actions['blindmarking'][get_string('revealidentities', 'assign')] = $url->out(false);
        }
        foreach ($this->assign->get_feedback_plugins() as $plugin) {
            if ($plugin->is_enabled() && $plugin->is_visible()) {
                foreach ($plugin->get_grading_actions() as $action => $description) {
                    $url = new moodle_url('/mod/assign/view.php', [
                        'id' => $this->assign->get_course_module()->id,
                        'plugin' => $plugin->get_type(),
                        'pluginsubtype' => 'assignfeedback',
                        'action' => 'viewpluginpage',
                        'pluginaction' => $action,
                    ]);
                    $actions['assignfeedback_' . $plugin->get_type()][$description] = $url->out(false);
                }
            }
        }
        if ($this->showdownload) {
            $url = new moodle_url('/mod/assign/view.php', [
                'id' => $this->assign->get_course_module()->id,
                'action' => 'downloadall',
            ]);
            $actions['downloadall'][get_string('downloadall', 'mod_assign')] = $url->out(false);
        }

        return $actions;
    }

    /**
     * Get the status menu for the grading action menu.
     *
     * @param array $navigationparams Authorised, whitelisted navigation state.
     * @return array An array containing the status menu and the current value.
     */
    private function get_status_menu(array $navigationparams): array {
        $statusmenu = [];
        $currentvalue = '';

        $groupedfilters = $this->assign->get_filters(true);
        foreach ($groupedfilters as $group => $filters) {
            foreach ($filters as $filter) {
                if ($filter['key'] === ASSIGN_FILTER_NONE) {
                    // The 'none' filter is not a real filter.
                    $filter['key'] = '';
                }
                $url = new moodle_url('/mod/assign/view.php',
                    array_replace($navigationparams, ['status' => $filter['key']]));
                $statusmenu[$url->out(false)] = $filter['name'];

                if ($filter['active']) {
                    $currentvalue = $url->out(false);
                }
            }
            if ($group !== array_key_last($groupedfilters)) {
                $statusmenu[] = '';
            }
        }

        return [
            'statusmenu' => $statusmenu,
            'currentvalue' => $currentvalue,
        ];
    }

    /**
     * The renderable for the extra filters dropdown, if available.
     *
     * @param array $navigationparams Authorised, whitelisted navigation state.
     * @return dialog|null The renderable for the extra filters dropdown, if available.
     */
    private function get_extra_filters_dropdown(array $navigationparams): ?dialog {
        global $OUTPUT;

        $dropdowncontentdata = [
            'actionurl' => (new moodle_url('/mod/assign/view.php'))->out(false),
            'id' => $this->assign->get_course_module()->id,
            'action' => 'grading',
            'filters' => [],
            'preserved' => [],
        ];
        // GET forms for workflow/marker filters must not discard the current
        // search, group, status, or initial-bar restrictions.
        foreach (['group', 'userid', 'search', 'status', 'tifirst', 'tilast'] as $key) {
            if (array_key_exists($key, $navigationparams)) {
                $dropdowncontentdata['preserved'][] = [
                    'name' => $key,
                    'value' => $navigationparams[$key],
                ];
            }
        }

        // If marking workflow is enabled.
        if ($this->assign->get_instance()->markingworkflow) {
            $dropdowncontentdata['filters']['markingworkflow'] = [
                'workflowfilteroptions' => $this->assign->get_marking_workflow_filters(true),
            ];

            // If marking allocation is enabled and the user has the capability to manage marker allocations.
            if ($this->assign->get_instance()->markingallocation &&
                    has_capability('mod/assign:manageallocations', $this->assign->get_context())) {
                $dropdowncontentdata['filters']['markingallocation'] = [
                    'markingallocationoptions' => $this->assign->get_marking_allocation_filters(true),
                ];
            }
        }

        if (has_capability('moodle/course:viewsuspendedusers', $this->assign->get_context())) {
            $dropdowncontentdata['filters']['suspendedparticipants'] = [
                'active' => !$this->assign->show_only_active_users(),
            ];
        }

        // If there are no available filters, return null.
        if (empty($dropdowncontentdata['filters'])) {
            return null;
        }

        // Define the content output for the extra filters dropdown menu.
        $dropdowncontent = $OUTPUT->render_from_template(
            'mod_assign/actionbar/grading/extra_filters_dropdown_body',
            $dropdowncontentdata
        );

        // Define the output for the extra filters dropdown trigger.
        $buttoncontent = $OUTPUT->render_from_template(
            'mod_assign/actionbar/grading/extra_filters_dropdown_trigger',
            ['appliedfilterscount' => $this->get_applied_extra_filters_count()]
        );

        return new dialog(
            $buttoncontent,
            $dropdowncontent,
            [
                'classes' => 'extrafilters d-flex',
                'buttonclasses' => 'btn d-flex border-none align-items-center dropdown-toggle p-0',
                'autoclose' => 'outside',
            ]
        );
    }

    /**
     * Returns the number of applied extra filters.
     *
     * @return int The number of applied extra filters.
     */
    private function get_applied_extra_filters_count(): int {
        $appliedextrafilterscount = 0;

        // Count only values which are valid for this activity and currently
        // available to the teacher, not stale preferences from another task.
        if ($this->assign->get_instance()->markingworkflow) {
            $workflowfilter = get_user_preferences('assign_workflowfilter', '');
            if ($workflowfilter !== '' &&
                    array_key_exists($workflowfilter, $this->assign->get_marking_workflow_filters())) {
                $appliedextrafilterscount++;
            }

            $canallocatemarker = $this->assign->get_instance()->markingallocation &&
                has_capability('mod/assign:manageallocations', $this->assign->get_context());
            if ($canallocatemarker) {
                $markerfilter = get_user_preferences('assign_markerfilter', '');
                if ($markerfilter !== '' &&
                        array_key_exists($markerfilter, $this->assign->get_marking_allocation_filters())) {
                    $appliedextrafilterscount++;
                }
            }
        }

        // If suspended participants are included.
        if (!$this->assign->show_only_active_users()) {
            $appliedextrafilterscount++;
        }

        return $appliedextrafilterscount;
    }
}
