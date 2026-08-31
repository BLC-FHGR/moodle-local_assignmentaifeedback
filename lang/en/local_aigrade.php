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
 * English language strings for local_aigrade (higher-education adaptation)
 *
 * @package    local_aigrade
 * @copyright  2025 Brian A. Pool, National Trail Local Schools
 * @copyright  2026 Higher-education adaptation, FH Graubünden
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// Plugin and core labels.
$string['pluginname'] = 'AI grading assistant';
$string['aigrade'] = 'AI grading';
$string['aigrade_help'] = 'Enables AI-assisted grading support for this assignment. The AI provides a non-binding point recommendation and written feedback; teachers make the final grading decision.';

// Capability.
$string['aigrade:grade'] = 'Use AI to support grading of submissions';

// Warning in the assignment form.
$string['aigrade_warning'] = 'AI grading notice';
$string['aigrade_warning_text'] = '<strong>Important:</strong> Always review AI-generated recommendations and feedback critically. The AI evaluates text content only and cannot assess images, formatting, layout or visual design. The AI does not assign a grade; the final decision remains with the teacher.';

// Warning on the global settings page.
$string['settings_warning_html'] = '<div class="alert alert-warning" style="margin: 20px 0; padding: 15px; background-color: #fff3cd; border: 1px solid #ffc107; border-radius: 4px;">
    <h4 style="margin-top: 0; color: #856404;"><i class="fa fa-exclamation-triangle"></i> Important: AI grading guidelines</h4>
    <p><strong>Teachers must always review AI-generated point recommendations and feedback for accuracy.</strong> The AI supports, it does not decide.</p>
    <p><strong>Best suited for:</strong> text-based work such as proposals, term papers and written assignments.</p>
    <p><strong>Limitations:</strong> the AI evaluates text content only and cannot assess:</p>
    <ul>
        <li>Images, graphics or visual elements</li>
        <li>Formatting, colours or layout</li>
        <li>Tables, charts or diagrams (structure may be lost)</li>
    </ul>
    <p><strong>Data protection:</strong> submission text is sent to the AI provider configured in Moodle. Ensure this complies with your institution\'s data protection rules and that students have been informed accordingly.</p>
</div>';

// AI assistant name (global setting).
$string['ai_name'] = 'AI assistant name';
$string['ai_name_desc'] = 'Name of the AI grading assistant as it appears on buttons (e.g. "AI"). Default is "AI".';

// AI backend selection.
$string['setting_ai_backend'] = 'AI backend';
$string['setting_ai_backend_desc'] = 'Determines which system handles grading requests. <strong>Local AI Manager</strong> uses the models configured in the "local_ai_manager" plugin (e.g. locally hosted models). <strong>Moodle core AI subsystem</strong> uses the providers configured under "Site administration → AI".';
$string['backend_ai_manager'] = 'Local AI Manager (local models)';
$string['backend_core'] = 'Moodle core AI subsystem';
$string['setting_ai_manager_purpose'] = 'Local AI Manager: purpose';
$string['setting_ai_manager_purpose_desc'] = 'Only relevant when the backend is set to "Local AI Manager". The AI grader sends the prompt through this purpose. The model used is configured per purpose and role inside Local AI Manager - choose the purpose you assigned your desired (local) model to. Recommended: "feedback".';
$string['error_ai_manager_missing'] = 'The "local_ai_manager" plugin is not installed or unavailable. Install it, or select the Moodle core AI subsystem as the backend.';

// Timeout for interactive (manual) AI requests.
$string['setting_manual_timeout'] = 'Timeout for interactive requests';
$string['setting_manual_timeout_desc'] = 'How long an AI request triggered by a grading button may take. The site-wide Local AI Manager timeout ("requesttimeout", 60 seconds by default) is meant for short requests and is too short for long rubric prompts - manual grading then aborts with "The API took too long to process your request" and nothing is stored. This value applies to the grading buttons only and only to the "Local AI Manager" backend; the site-wide setting is left untouched. Keep it below your web server request timeout (Apache "Timeout", 300 seconds by default), otherwise the browser receives a gateway error instead of a readable message. Note that bulk grading processes submissions one after another within a single request, so a large batch can still exceed the web server timeout - automatic grading on submission is the more robust route for many submissions. 0 = use the site-wide timeout.';

// Automatic grading on submission.
$string['aigrade_auto_grade'] = 'Automatically grade on submission';
$string['aigrade_auto_grade_help'] = 'When enabled, the AI automatically creates a feedback suggestion as soon as a student submits. Processing runs in the background (Moodle cron) and does not set a grade - the suggestion only appears in the panel. Requirements: a running cron and a configured grading account (see the plugin setting "Account for automatic grading").';
$string['setting_autograde_user'] = 'Account for automatic grading';
$string['setting_autograde_user_desc'] = 'Username of the account under which automatic grading runs in the background. This account must be allowed to use the local AI service (capability local/ai_manager:use), have confirmed AI usage and sufficient quota. Leave empty to instead use a teacher with grading capability in the respective course (less predictable).';
$string['setting_autograde_timeout'] = 'Timeout for background requests';
$string['setting_autograde_timeout_desc'] = 'How long an AI request made by automatic grading may take. The site-wide Local AI Manager timeout ("requesttimeout", 60 seconds by default) is meant for interactive requests and is too short for long rubric prompts - background grading then aborts with "The API took too long to process your request". This value applies to the ad-hoc task only and only to the "Local AI Manager" backend; the site-wide setting is left untouched. 0 = use the site-wide timeout.';
$string['task_autograde'] = 'Background AI grading (AI Grader)';

// Per-assignment instruction fields.
$string['aigrade_instructions_with_rubric_field'] = 'AI instructions (with rubric)';
$string['aigrade_instructions_with_rubric_field_help'] = 'Instructions for the AI when a rubric is uploaded. These instructions fully drive the grading behaviour, together with the uploaded rubric.';
$string['aigrade_instructions_without_rubric_field'] = 'AI instructions (without rubric)';
$string['aigrade_instructions_without_rubric_field_help'] = 'Instructions for the AI when no rubric is uploaded. The AI grades based on the assignment description and these instructions.';

// Global default instructions (settings page).
$string['aigrade_instructions_with_rubric'] = 'Default instructions (with rubric)';
$string['aigrade_instructions_with_rubric_desc'] = 'Default instructions for assignments that have a rubric uploaded. This text is copied into new assignments when AI grading is enabled.';
$string['aigrade_instructions_without_rubric'] = 'Default instructions (without rubric)';
$string['aigrade_instructions_without_rubric_desc'] = 'Default instructions for assignments without a rubric. The AI grades based on the assignment description. This text is copied into new assignments when AI grading is enabled.';

// Rubric upload.
$string['aigrade_rubric'] = 'Grading rubric';
$string['aigrade_rubric_help'] = 'Upload a rubric file (PDF, TXT, DOCX or DOC) that the AI will use to grade submissions.';

// Buttons.
$string['button_grade_single'] = 'Grade with {$a}';
$string['button_grade_bulk'] = 'Grade all with {$a}';

// Prompt building blocks.
$string['assignment_instructions_label'] = 'Assignment instructions:';
$string['grading_rubric_label'] = 'Grading rubric:';
$string['student_submission_label'] = 'Student submission:';
$string['default_grading_criteria'] = 'Grade this assignment based on standard academic criteria for quality, completeness and accuracy.';
$string['prompt_framing_heading'] = 'FRAMING:';
$string['prompt_max_points'] = 'Maximum achievable points: {$a}.';
$string['prompt_recommendation_only'] = 'Provide a justified point recommendation. No final grade is assigned - the teacher makes the final decision.';
$string['prompt_address_language'] = 'Respond in a factual, university-level register and address the student directly.';

// File / extraction notices.
$string['file_submission_separator'] = '--- File submission content ---';
$string['file_label'] = 'File: {$a}';
$string['slide_label'] = 'Slide {$a}:';
$string['pdf_rubric_fallback'] = 'PDF rubric: {$a}';
$string['unsupported_file_type'] = 'File type not supported for AI grading: {$a}';
$string['extraction_unavailable'] = 'Text extraction not available for this file: {$a}';
$string['extraction_error'] = 'Error extracting text: {$a}';

// Status and error messages.
$string['no_ungraded'] = 'No pending submissions found that still need feedback.';
$string['already_has_feedback'] = 'This submission already has feedback. Click again to regenerate it.';
$string['aigrade_disabled'] = 'AI grading is not enabled for this assignment.';
$string['error_no_submission'] = 'This student has not submitted any text yet. You may need to refresh the page if a submission exists.';
$string['error_no_feedback'] = 'The AI did not return feedback.';
$string['error_invalid_action'] = 'Invalid action.';
$string['ai_error'] = 'AI service error: {$a}';
$string['rubric_parse_error'] = 'Error parsing the rubric: {$a}';

// JavaScript strings (passed in from PHP).
$string['js_grading_single'] = 'Grading …';
$string['js_regrade'] = 'Regrade with {$a}';
$string['js_confirm_regrade'] = 'This submission already has feedback. Do you want to regenerate it with AI?';
$string['js_confirm_bulk'] = 'Grade all pending submissions with AI? This may take a few moments.';
$string['js_grading_bulk'] = 'Grading all submissions …';
$string['js_success_count'] = 'Successfully graded {count} submission(s).';
$string['js_error_prefix'] = 'Error: ';
$string['js_error_unknown'] = 'An unknown error occurred.';
$string['js_error_server'] = 'Error communicating with the server: ';
$string['js_panel_title'] = '{$a} feedback';
$string['js_panel_jump'] = 'Go to feedback field';
$string['js_panel_collapse'] = 'Collapse/expand';
$string['js_panel_apply'] = 'Copy to feedback field';
$string['js_panel_apply_confirm'] = 'The feedback field already contains text. Overwrite it with the AI feedback?';
$string['js_panel_apply_failed'] = 'The feedback field could not be found. Please copy the text manually.';

// Privacy (Privacy API).
$string['privacy:metadata:ai_provider'] = 'To perform grading analysis, content is sent to the AI provider configured in Moodle. This data is processed by the external AI service but not permanently stored by this plugin.';
$string['privacy:metadata:ai_provider:submissiontext'] = 'The text content of the submission sent to the AI provider for grading.';
$string['privacy:metadata:ai_provider:assignmentname'] = 'The name of the assignment, sent to provide context to the AI provider.';
$string['privacy:metadata:ai_provider:assignmentinstructions'] = 'The assignment instructions, sent as grading criteria to the AI provider.';
$string['privacy:metadata:ai_provider:rubric'] = 'The grading rubric (if uploaded), sent to guide the AI evaluation.';
$string['privacy:metadata:local_aigrade_feedback'] = 'AI feedback suggestions stored by the plugin (advisory only, not the official feedback).';
$string['privacy:metadata:local_aigrade_feedback:userid'] = 'The ID of the student whose submission the suggestion belongs to.';
$string['privacy:metadata:local_aigrade_feedback:feedback'] = 'The AI-generated feedback suggestion.';
$string['privacy:metadata:local_aigrade_feedback:timecreated'] = 'Time the suggestion was created.';
$string['privacy:metadata:local_aigrade_feedback:timemodified'] = 'Time the suggestion was last updated.';
$string['privacy:aifeedbackpath'] = 'AI feedback suggestion';
