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
 * Library functions for local_aigrade
 *
 * @package    local_aigrade
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
function local_aigrade_coursemodule_standard_elements($formwrapper, $mform) {
    global $DB, $PAGE;
    
    $current = $formwrapper->get_current();
    
    // Only for assignment module
    if (!isset($current->modulename) || $current->modulename !== 'assign') {
        return;
    }
    
    // Get existing config if editing
    $update = optional_param('update', 0, PARAM_INT);
    $aiconfig = null;
    
    if ($update) {
        $cm = get_coursemodule_from_id('assign', $update);
        if ($cm) {
            $aiconfig = $DB->get_record('local_aigrade_config', ['assignmentid' => $cm->instance]);
        }
    }
    
    // Add AI Grading section
    $mform->addElement('header', 'aigrade_header', get_string('aigrade', 'local_aigrade'));
    
    // Add warning message
    $mform->addElement('static', 'aigrade_warning', '', get_string('aigrade_warning_text', 'local_aigrade'));
        
    // Enable AI grading checkbox
    $mform->addElement('advcheckbox', 'aigrade_enabled', get_string('aigrade', 'local_aigrade'));
    $mform->addHelpButton('aigrade_enabled', 'aigrade', 'local_aigrade');
    if ($aiconfig) {
        $mform->setDefault('aigrade_enabled', $aiconfig->enabled);
    }

    // Automatically grade on submission (opt-in, runs asynchronously via cron).
    $mform->addElement('advcheckbox', 'aigrade_auto_grade', get_string('aigrade_auto_grade', 'local_aigrade'));
    $mform->addHelpButton('aigrade_auto_grade', 'aigrade_auto_grade', 'local_aigrade');
    $mform->setType('aigrade_auto_grade', PARAM_INT);
    $mform->disabledIf('aigrade_auto_grade', 'aigrade_enabled');
    if ($aiconfig && isset($aiconfig->auto_grade)) {
        $mform->setDefault('aigrade_auto_grade', $aiconfig->auto_grade);
    }

    // AI instructions WITH rubric - full Moodle editor
    $mform->addElement('editor', 'aigrade_instructions_with_rubric_editor',
        get_string('aigrade_instructions_with_rubric_field', 'local_aigrade'),
        ['rows' => 8],
        ['maxfiles' => 0, 'noclean' => true, 'context' => \context_system::instance()]);
    $mform->addHelpButton('aigrade_instructions_with_rubric_editor', 'aigrade_instructions_with_rubric_field', 'local_aigrade');
    $mform->disabledIf('aigrade_instructions_with_rubric_editor', 'aigrade_enabled');
    if ($aiconfig && !empty($aiconfig->instructions_with_rubric)) {
        $mform->setDefault('aigrade_instructions_with_rubric_editor', [
            'text'   => $aiconfig->instructions_with_rubric,
            'format' => FORMAT_HTML,
        ]);
    } else {
        $default = get_config('local_aigrade', 'default_instructions_with_rubric');
        $mform->setDefault('aigrade_instructions_with_rubric_editor', [
            'text'   => (string)$default,
            'format' => FORMAT_HTML,
        ]);
    }
    
    // AI instructions WITHOUT rubric - full Moodle editor
    $mform->addElement('editor', 'aigrade_instructions_without_rubric_editor',
        get_string('aigrade_instructions_without_rubric_field', 'local_aigrade'),
        ['rows' => 8],
        ['maxfiles' => 0, 'noclean' => true, 'context' => \context_system::instance()]);
    $mform->addHelpButton('aigrade_instructions_without_rubric_editor', 'aigrade_instructions_without_rubric_field', 'local_aigrade');
    $mform->disabledIf('aigrade_instructions_without_rubric_editor', 'aigrade_enabled');
    if ($aiconfig && !empty($aiconfig->instructions_without_rubric)) {
        $mform->setDefault('aigrade_instructions_without_rubric_editor', [
            'text'   => $aiconfig->instructions_without_rubric,
            'format' => FORMAT_HTML,
        ]);
    } else {
        $default = get_config('local_aigrade', 'default_instructions_without_rubric');
        $mform->setDefault('aigrade_instructions_without_rubric_editor', [
            'text'   => (string)$default,
            'format' => FORMAT_HTML,
        ]);
    }
    
    // Rubric file upload
    $mform->addElement('filemanager', 'aigrade_rubric', 
        get_string('aigrade_rubric', 'local_aigrade'), 
        null,
        array(
            'subdirs' => 0,
            'maxbytes' => 10485760, // 10MB
            'maxfiles' => 1,
            'accepted_types' => array('.pdf', '.txt', '.docx', '.doc')
        ));
    $mform->addHelpButton('aigrade_rubric', 'aigrade_rubric', 'local_aigrade');
    $mform->disabledIf('aigrade_rubric', 'aigrade_enabled');
}

/**
 * Hook to populate form data when editing
 *
 * @param object $formwrapper
 */
function local_aigrade_coursemodule_definition_after_data($formwrapper) {
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
    
    // Prepare the draft area
    $draftitemid = file_get_submitted_draft_itemid('aigrade_rubric');
    
    file_prepare_draft_area(
        $draftitemid,
        $context->id,
        'local_aigrade',
        'rubric',
        $assignmentid,
        array('subdirs' => 0, 'maxfiles' => 1, 'maxbytes' => 10485760)
    );
    
    // Load existing config data
    $config = $DB->get_record('local_aigrade_config', ['assignmentid' => $assignmentid]);
    
    if ($config) {
        $formwrapper->set_data(array('aigrade_rubric' => $draftitemid));
    }
}

/**
 * Hook to save AI grading configuration when assignment is saved
 *
 * @param object $data
 * @param object $course
 * @return object Modified data
 */
function local_aigrade_coursemodule_edit_post_actions($data, $course) {
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
    
    // Check if config exists
    $config = $DB->get_record('local_aigrade_config', ['assignmentid' => $assignmentid]);
    
    $record = new stdClass();
    $record->assignmentid = $assignmentid;
    $record->enabled = isset($data->aigrade_enabled) ? $data->aigrade_enabled : 0;
    $record->auto_grade = isset($data->aigrade_auto_grade) ? $data->aigrade_auto_grade : 0;

    // Editor elements submit as arrays with 'text' and 'format' keys.
    if (isset($data->aigrade_instructions_with_rubric_editor)) {
        $record->instructions_with_rubric = is_array($data->aigrade_instructions_with_rubric_editor)
            ? $data->aigrade_instructions_with_rubric_editor['text']
            : $data->aigrade_instructions_with_rubric_editor;
    } else {
        $record->instructions_with_rubric = '';
    }
    if (isset($data->aigrade_instructions_without_rubric_editor)) {
        $record->instructions_without_rubric = is_array($data->aigrade_instructions_without_rubric_editor)
            ? $data->aigrade_instructions_without_rubric_editor['text']
            : $data->aigrade_instructions_without_rubric_editor;
    } else {
        $record->instructions_without_rubric = '';
    }
    $record->timemodified = time();
    
    if ($config) {
        $record->id = $config->id;
        $DB->update_record('local_aigrade_config', $record);
    } else {
        $record->timecreated = time();
        $DB->insert_record('local_aigrade_config', $record);
    }
    
    // Handle file upload
    if (isset($data->aigrade_rubric)) {
        $context = context_module::instance($data->coursemodule);
        file_save_draft_area_files(
            $data->aigrade_rubric,
            $context->id,
            'local_aigrade',
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
function local_aigrade_before_footer() {
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
    $config = $DB->get_record('local_aigrade_config', ['assignmentid' => $cm->instance]);
    if (!$config || !$config->enabled) {
        return '';
    }
    
    // Check capability
    $context = context_module::instance($cm->id);
    if (!has_capability('local/aigrade:grade', $context)) {
        return '';
    }
    
    // Determine if we're on individual grading page or bulk grading page
    $action = optional_param('action', '', PARAM_ALPHA);
    $userid = optional_param('userid', 0, PARAM_INT);
    $is_individual = ($action === 'grader');
    
    // Get custom AI name
    $ai_name = get_config('local_aigrade', 'ai_name');
    if (empty($ai_name)) {
        $ai_name = 'AI';
    }
    
    // Get sesskey
    $sesskey = sesskey();
    
    // Load appropriate AMD module
    if ($is_individual) {
        $url = new moodle_url('/local/aigrade/grade_single.php', ['id' => $cm->id, 'userid' => $userid]);
        $button_text = get_string('button_grade_single', 'local_aigrade', $ai_name);

        $strings = [
            'inprogress'      => get_string('js_grading_single', 'local_aigrade'),
            'regrade'         => get_string('js_regrade', 'local_aigrade', $ai_name),
            'confirmregrade'  => get_string('js_confirm_regrade', 'local_aigrade'),
            'errorprefix'     => get_string('js_error_prefix', 'local_aigrade'),
            'errorunknown'    => get_string('js_error_unknown', 'local_aigrade'),
            'errorserver'     => get_string('js_error_server', 'local_aigrade'),
            'paneltitle'      => get_string('js_panel_title', 'local_aigrade', $ai_name),
            'paneljump'       => get_string('js_panel_jump', 'local_aigrade'),
            'panelcollapse'   => get_string('js_panel_collapse', 'local_aigrade'),
            'apply'           => get_string('js_panel_apply', 'local_aigrade'),
            'applyconfirm'    => get_string('js_panel_apply_confirm', 'local_aigrade'),
            'applyfailed'     => get_string('js_panel_apply_failed', 'local_aigrade'),
        ];

        $feedbackurl = new moodle_url('/local/aigrade/get_feedback.php', ['id' => $cm->id]);

        $PAGE->requires->js_call_amd('local_aigrade/grade_single', 'init', [
            $url->out(false),
            $button_text,
            $sesskey,
            $strings,
            $feedbackurl->out(false)
        ]);
    } else {
        $url = new moodle_url('/local/aigrade/grade.php', ['id' => $cm->id]);
        $button_text = get_string('button_grade_bulk', 'local_aigrade', $ai_name);

        $strings = [
            'confirmbulk'  => get_string('js_confirm_bulk', 'local_aigrade'),
            'inprogress'   => get_string('js_grading_bulk', 'local_aigrade'),
            'successcount' => get_string('js_success_count', 'local_aigrade'),
            'errorprefix'  => get_string('js_error_prefix', 'local_aigrade'),
            'errorunknown' => get_string('js_error_unknown', 'local_aigrade'),
            'errorserver'  => get_string('js_error_server', 'local_aigrade'),
        ];

        $PAGE->requires->js_call_amd('local_aigrade/grade_bulk', 'init', [
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
function local_aigrade_backup_callback() {
    return [
        'backup' => [
            'mod/assign' => 'backup/moodle2/backup_local_aigrade_plugin.class.php',
        ],
        'restore' => [
            'mod/assign' => 'backup/moodle2/restore_local_aigrade_plugin.class.php',
        ],
    ];
}
