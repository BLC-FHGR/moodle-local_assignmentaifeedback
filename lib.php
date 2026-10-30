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
 * Library functions for local_assignmentaifeedback
 *
 * @package    local_assignmentaifeedback
 * @copyright  2026 Brian A. Pool, National Trail Local Schools
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Hook to extend the course module settings form (assignment settings)
 *
 * @param object $formwrapper The moodleform_mod instance
 * @param object $mform The MoodleQuickForm instance
 */
function local_assignmentaifeedback_coursemodule_standard_elements($formwrapper, $mform) {
    global $DB, $PAGE;
    
    $current = $formwrapper->get_current();

    // Only for assignment module
    if (!isset($current->modulename) || $current->modulename !== 'assign') {
        return;
    }

    // Editing the AI settings needs its own capability: being allowed to grade with the AI
    // does not imply being allowed to switch it on or to rewrite the prompt. get_context()
    // is the course context while the assignment is being created and the module context
    // afterwards; the capability is inherited down from category and course either way.
    if (!has_capability('local/assignmentaifeedback:configure', $formwrapper->get_context())) {
        return;
    }

    // Get existing config if editing
    $update = optional_param('update', 0, PARAM_INT);
    $aiconfig = null;
    
    if ($update) {
        $cm = get_coursemodule_from_id('assign', $update);
        if ($cm) {
            $aiconfig = $DB->get_record('local_assignmentaifeedback_config', ['assignmentid' => $cm->instance]);
        }
    }
    
    // Add AI Grading section
    $mform->addElement('header', 'assignmentaifeedback_header', get_string('assignmentaifeedback', 'local_assignmentaifeedback'));
    
    // Add warning message
    $mform->addElement('static', 'assignmentaifeedback_warning', '', get_string('assignmentaifeedback_warning_text', 'local_assignmentaifeedback'));
        
    // Enable AI grading checkbox
    $mform->addElement('advcheckbox', 'assignmentaifeedback_enabled', get_string('assignmentaifeedback', 'local_assignmentaifeedback'));
    $mform->addHelpButton('assignmentaifeedback_enabled', 'assignmentaifeedback', 'local_assignmentaifeedback');
    if ($aiconfig) {
        $mform->setDefault('assignmentaifeedback_enabled', $aiconfig->enabled);
    }

    // Automatically grade on submission (opt-in, runs asynchronously via cron).
    $mform->addElement('advcheckbox', 'assignmentaifeedback_auto_grade', get_string('assignmentaifeedback_auto_grade', 'local_assignmentaifeedback'));
    $mform->addHelpButton('assignmentaifeedback_auto_grade', 'assignmentaifeedback_auto_grade', 'local_assignmentaifeedback');
    $mform->setType('assignmentaifeedback_auto_grade', PARAM_INT);
    $mform->disabledIf('assignmentaifeedback_auto_grade', 'assignmentaifeedback_enabled');
    if ($aiconfig && isset($aiconfig->auto_grade)) {
        $mform->setDefault('assignmentaifeedback_auto_grade', $aiconfig->auto_grade);
    }

    // AI instructions WITH rubric - full Moodle editor
    $mform->addElement('editor', 'assignmentaifeedback_instructions_with_rubric_editor',
        get_string('assignmentaifeedback_instructions_with_rubric_field', 'local_assignmentaifeedback'),
        ['rows' => 8],
        ['maxfiles' => 0, 'noclean' => true, 'context' => \context_system::instance()]);
    $mform->addHelpButton('assignmentaifeedback_instructions_with_rubric_editor', 'assignmentaifeedback_instructions_with_rubric_field', 'local_assignmentaifeedback');
    $mform->disabledIf('assignmentaifeedback_instructions_with_rubric_editor', 'assignmentaifeedback_enabled');
    if ($aiconfig && !empty($aiconfig->instructions_with_rubric)) {
        $mform->setDefault('assignmentaifeedback_instructions_with_rubric_editor', [
            'text'   => $aiconfig->instructions_with_rubric,
            'format' => FORMAT_HTML,
        ]);
    } else {
        $default = get_config('local_assignmentaifeedback', 'default_instructions_with_rubric');
        $mform->setDefault('assignmentaifeedback_instructions_with_rubric_editor', [
            'text'   => (string)$default,
            'format' => FORMAT_HTML,
        ]);
    }
    
    // AI instructions WITHOUT rubric - full Moodle editor
    $mform->addElement('editor', 'assignmentaifeedback_instructions_without_rubric_editor',
        get_string('assignmentaifeedback_instructions_without_rubric_field', 'local_assignmentaifeedback'),
        ['rows' => 8],
        ['maxfiles' => 0, 'noclean' => true, 'context' => \context_system::instance()]);
    $mform->addHelpButton('assignmentaifeedback_instructions_without_rubric_editor', 'assignmentaifeedback_instructions_without_rubric_field', 'local_assignmentaifeedback');
    $mform->disabledIf('assignmentaifeedback_instructions_without_rubric_editor', 'assignmentaifeedback_enabled');
    if ($aiconfig && !empty($aiconfig->instructions_without_rubric)) {
        $mform->setDefault('assignmentaifeedback_instructions_without_rubric_editor', [
            'text'   => $aiconfig->instructions_without_rubric,
            'format' => FORMAT_HTML,
        ]);
    } else {
        $default = get_config('local_assignmentaifeedback', 'default_instructions_without_rubric');
        $mform->setDefault('assignmentaifeedback_instructions_without_rubric_editor', [
            'text'   => (string)$default,
            'format' => FORMAT_HTML,
        ]);
    }
    
    // Rubric file upload
    $mform->addElement('filemanager', 'assignmentaifeedback_rubric', 
        get_string('assignmentaifeedback_rubric', 'local_assignmentaifeedback'), 
        null,
        array(
            'subdirs' => 0,
            'maxbytes' => 10485760, // 10MB
            'maxfiles' => 1,
            'accepted_types' => array('.pdf', '.txt', '.docx', '.doc')
        ));
    $mform->addHelpButton('assignmentaifeedback_rubric', 'assignmentaifeedback_rubric', 'local_assignmentaifeedback');
    $mform->disabledIf('assignmentaifeedback_rubric', 'assignmentaifeedback_enabled');
}

/**
 * Hook to populate form data when editing
 *
 * @param object $formwrapper
 */
function local_assignmentaifeedback_coursemodule_definition_after_data($formwrapper) {
    global $DB;
    
    $current = $formwrapper->get_current();
    
    // Only for assignment module
    if (!isset($current->modulename) || $current->modulename !== 'assign') {
        return;
    }
    
    // Only when editing existing assignment (not during initial creation)
    if (!isset($current->coursemodule) || !isset($current->instance) || empty($current->coursemodule) || $current->coursemodule <= 0) {
        return;
    }
    
    $assignmentid = $current->instance;
    $context = \context_module::instance($current->coursemodule);

    // The rubric filemanager only exists when the section above was rendered.
    if (!has_capability('local/assignmentaifeedback:configure', $context)) {
        return;
    }

    // Prepare the draft area
    $draftitemid = file_get_submitted_draft_itemid('assignmentaifeedback_rubric');
    
    file_prepare_draft_area(
        $draftitemid,
        $context->id,
        'local_assignmentaifeedback',
        'rubric',
        $assignmentid,
        array('subdirs' => 0, 'maxfiles' => 1, 'maxbytes' => 10485760)
    );
    
    // Load existing config data
    $config = $DB->get_record('local_assignmentaifeedback_config', ['assignmentid' => $assignmentid]);
    
    if ($config) {
        $formwrapper->set_data(array('assignmentaifeedback_rubric' => $draftitemid));
    }
}

/**
 * Hook to save AI grading configuration when assignment is saved
 *
 * @param object $data
 * @param object $course
 * @return object Modified data
 */
function local_assignmentaifeedback_coursemodule_edit_post_actions($data, $course) {
    global $DB;
    
    // Only process assignment module
    if ($data->modulename !== 'assign') {
        return $data;
    }
    
    // Get the assignment instance ID
    if (!isset($data->instance)) {
        return $data;
    }
    
    $assignmentid = $data->instance;

    // Without the capability the form section was never rendered, so none of the fields
    // below were submitted. Writing the record anyway would store enabled = 0 and empty
    // prompts -- every save of the assignment form by a teacher who may not configure the
    // AI would silently switch it off and discard the instructions. Leave the stored
    // configuration untouched instead.
    $context = !empty($data->coursemodule)
        ? context_module::instance($data->coursemodule)
        : context_course::instance($course->id);
    if (!has_capability('local/assignmentaifeedback:configure', $context)) {
        return $data;
    }

    // Check if config exists
    $config = $DB->get_record('local_assignmentaifeedback_config', ['assignmentid' => $assignmentid]);
    
    $record = new stdClass();
    $record->assignmentid = $assignmentid;
    $record->enabled = isset($data->assignmentaifeedback_enabled) ? $data->assignmentaifeedback_enabled : 0;
    $record->auto_grade = isset($data->assignmentaifeedback_auto_grade) ? $data->assignmentaifeedback_auto_grade : 0;

    // Editor elements submit as arrays with 'text' and 'format' keys.
    if (isset($data->assignmentaifeedback_instructions_with_rubric_editor)) {
        $record->instructions_with_rubric = is_array($data->assignmentaifeedback_instructions_with_rubric_editor)
            ? $data->assignmentaifeedback_instructions_with_rubric_editor['text']
            : $data->assignmentaifeedback_instructions_with_rubric_editor;
    } else {
        $record->instructions_with_rubric = '';
    }
    if (isset($data->assignmentaifeedback_instructions_without_rubric_editor)) {
        $record->instructions_without_rubric = is_array($data->assignmentaifeedback_instructions_without_rubric_editor)
            ? $data->assignmentaifeedback_instructions_without_rubric_editor['text']
            : $data->assignmentaifeedback_instructions_without_rubric_editor;
    } else {
        $record->instructions_without_rubric = '';
    }
    $record->timemodified = time();
    
    if ($config) {
        $record->id = $config->id;
        $DB->update_record('local_assignmentaifeedback_config', $record);
    } else {
        $record->timecreated = time();
        $DB->insert_record('local_assignmentaifeedback_config', $record);
    }
    
    // Handle file upload
    if (isset($data->assignmentaifeedback_rubric)) {
        file_save_draft_area_files(
            $data->assignmentaifeedback_rubric,
            $context->id,
            'local_assignmentaifeedback',
            'rubric',
            $assignmentid,
            array('subdirs' => 0, 'maxfiles' => 1, 'maxbytes' => 10485760)
        );
    }
    
    return $data;
}

/**
 * Hook to inject AI grading buttons into assignment grading pages
 *
 * @return string Empty string (JavaScript loaded via AMD)
 */
function local_assignmentaifeedback_before_footer() {
    global $PAGE, $DB;

    // Only on assignment module pages
    if ($PAGE->pagetype !== 'mod-assign-view' && 
        $PAGE->pagetype !== 'mod-assign-grader' &&
        $PAGE->pagetype !== 'mod-assign-grading' &&
        strpos($PAGE->pagetype, 'mod-assign') === false) {
        return '';
    }
    
    // Get course module
    $cm = $PAGE->cm;
    if (!$cm || $cm->modname !== 'assign') {
        return '';
    }
    
    // Check if AI Grade is enabled for this assignment
    $config = $DB->get_record('local_assignmentaifeedback_config', ['assignmentid' => $cm->instance]);
    if (!$config || !$config->enabled) {
        return '';
    }

    // With automatic grading on submission the event observer queues every run, so the bulk
    // button has nothing left to trigger and is not offered. The single grading page keeps
    // loading its module: the side panel displays the suggestion the automatic run produced,
    // and the single button reappears there when that run cannot be relied on (outdated
    // suggestion or failed run).
    $autograde = !empty($config->auto_grade);

    // Check capability
    $context = context_module::instance($cm->id);
    if (!has_capability('local/assignmentaifeedback:grade', $context)) {
        return '';
    }
    
    // Determine if we're on individual grading page or bulk grading page
    $action = optional_param('action', '', PARAM_ALPHA);
    $userid = optional_param('userid', 0, PARAM_INT);
    $is_individual = ($action === 'grader');
    
    // Get custom AI name
    $ai_name = get_config('local_assignmentaifeedback', 'ai_name');
    if (empty($ai_name)) {
        $ai_name = 'AI';
    }
    
    // Get sesskey
    $sesskey = sesskey();
    
    // Load appropriate AMD module
    if ($is_individual) {
        $url = new moodle_url('/local/assignmentaifeedback/grade_single.php', ['id' => $cm->id, 'userid' => $userid]);
        $button_text = get_string('button_grade_single', 'local_assignmentaifeedback', $ai_name);

        $strings = [
            'inprogress'      => get_string('js_grading_single', 'local_assignmentaifeedback'),
            'regrade'         => get_string('js_regrade', 'local_assignmentaifeedback', $ai_name),
            'confirmregrade'  => get_string('js_confirm_regrade', 'local_assignmentaifeedback'),
            'errorprefix'     => get_string('js_error_prefix', 'local_assignmentaifeedback'),
            'errorunknown'    => get_string('js_error_unknown', 'local_assignmentaifeedback'),
            'errorserver'     => get_string('js_error_server', 'local_assignmentaifeedback'),
            'paneltitle'      => get_string('js_panel_title', 'local_assignmentaifeedback', $ai_name),
            'paneljump'       => get_string('js_panel_jump', 'local_assignmentaifeedback'),
            'panelcollapse'   => get_string('js_panel_collapse', 'local_assignmentaifeedback'),
            'panelmove'       => get_string('js_panel_move', 'local_assignmentaifeedback'),
            'stale'           => get_string('js_panel_stale', 'local_assignmentaifeedback'),
            'staleauto'       => get_string('js_panel_stale_auto', 'local_assignmentaifeedback'),
            'error'           => get_string('js_panel_error', 'local_assignmentaifeedback'),
            'errorretry'      => get_string('js_panel_error_retry', 'local_assignmentaifeedback'),
            'apply'           => get_string('js_panel_apply', 'local_assignmentaifeedback'),
            'applyconfirm'    => get_string('js_panel_apply_confirm', 'local_assignmentaifeedback'),
            'applyfailed'     => get_string('js_panel_apply_failed', 'local_assignmentaifeedback'),
            'queued'          => get_string('js_queued', 'local_assignmentaifeedback'),
            'pendingtimeout'  => get_string('js_pending_timeout', 'local_assignmentaifeedback'),
        ];

        $feedbackurl = new moodle_url('/local/assignmentaifeedback/get_feedback.php', ['id' => $cm->id]);

        $PAGE->requires->js_call_amd('local_assignmentaifeedback/grade_single', 'init', [
            $url->out(false),
            $button_text,
            $sesskey,
            $strings,
            $feedbackurl->out(false),
            !$autograde
        ]);
    } else {
        // The bulk module only ever adds the button, so there is nothing left to load.
        if ($autograde) {
            return '';
        }

        $url = new moodle_url('/local/assignmentaifeedback/grade.php', ['id' => $cm->id]);
        $button_text = get_string('button_grade_bulk', 'local_assignmentaifeedback', $ai_name);

        $strings = [
            'confirmbulk'  => get_string('js_confirm_bulk', 'local_assignmentaifeedback'),
            'inprogress'   => get_string('js_grading_bulk', 'local_assignmentaifeedback'),
            'successcount' => get_string('js_success_count', 'local_assignmentaifeedback'),
            'queuedcount'  => get_string('js_queued_count', 'local_assignmentaifeedback'),
            'errorprefix'  => get_string('js_error_prefix', 'local_assignmentaifeedback'),
            'errorunknown' => get_string('js_error_unknown', 'local_assignmentaifeedback'),
            'errorserver'  => get_string('js_error_server', 'local_assignmentaifeedback'),
        ];

        $PAGE->requires->js_call_amd('local_assignmentaifeedback/grade_bulk', 'init', [
            $url->out(false),
            $button_text,
            $sesskey,
            $strings
        ]);
    }
    
    return '';
}

/**
 * Serves the plugin's backup/restore functionality
 *
 * @return array
 */
function local_assignmentaifeedback_backup_callback() {
    return [
        'backup' => [
            'mod/assign' => 'backup/moodle2/backup_local_assignmentaifeedback_plugin.class.php',
        ],
        'restore' => [
            'mod/assign' => 'backup/moodle2/restore_local_assignmentaifeedback_plugin.class.php',
        ],
    ];
}
