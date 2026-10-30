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
 * AI Grader class
 *
 * @package    local_assignmentaifeedback
 * @copyright  2026 Brian A. Pool, National Trail Local Schools
 * @copyright  2026 Anpassung Hochschulkontext FH Graubünden
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_assignmentaifeedback;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/assign/locallib.php');

/**
 * Class to handle AI grading of assignment submissions.
 *
 * This plugin provides a non-binding point recommendation and written feedback
 * to support teachers. It does NOT write a numeric grade into the gradebook -
 * the final grading decision remains with the teacher.
 */
class grader {
    /** @var \assign The assignment object */
    private $assignment;

    /** @var \context_module The module context */
    private $context;

    /** @var \stdClass The AI config */
    private $aiconfig;

    /**
     * Constructor
     *
     * @param \assign $assignment
     * @param \context_module $context
     * @param \stdClass $aiconfig
     */
    public function __construct($assignment, $context, $aiconfig) {
        $this->assignment = $assignment;
        $this->context    = $context;
        $this->aiconfig   = $aiconfig;
    }

    /**
     * Grade all submissions that do not have AI feedback yet.
     *
     * The 'count' key is always present, also on failure: it is a required field
     * of the local_assignmentaifeedback_grade_bulk external function's return structure.
     *
     * @return array Result with success status, count, and any errors
     */
    public function grade_submissions() {
        try {
            $rubric_text = $this->get_rubric_text();
            if (!$rubric_text) {
                $rubric_text = $this->get_assignment_description();
            }

            $has_rubric_text = $this->get_rubric_text();
            $instructions    = $this->resolve_instructions((bool)$has_rubric_text);

            $pending = $this->get_pending_submissions();

            if (empty($pending)) {
                return ['success' => false, 'count' => 0, 'error' => get_string('no_ungraded', 'local_assignmentaifeedback')];
            }

            $count = 0;
            foreach ($pending as $userid => $submission) {
                $fingerprint     = $this->get_submission_fingerprint($userid);
                $submission_text = $this->get_submission_text($userid);

                if (empty($submission_text)) {
                    continue;
                }

                $max_grade = $this->get_max_grade();

                $prompt   = $this->build_prompt($instructions, $rubric_text, $submission_text, $max_grade);
                $feedback = $this->call_ai_service($prompt);

                if ($feedback) {
                    $this->save_feedback($userid, $feedback, $fingerprint);
                    $count++;
                }
            }

            return ['success' => true, 'count' => $count];

        } catch (\Exception $e) {
            // 'retryable': the failure came from the AI service (timeout, gateway
            // error, quota) rather than from the submission itself, so a later
            // attempt may well succeed. Callers running in a task use this to fail
            // the task so Moodle retries it. Extra keys are ignored by
            // external_api::clean_returnvalue(), so this is safe for web services.
            return ['success' => false, 'count' => 0, 'error' => $e->getMessage(), 'retryable' => true];
        }
    }

    /**
     * Grade a single submission using AI.
     *
     * A failure caused by the AI service (rather than by the submission) carries
     * 'retryable' => true, so a caller running inside a task can fail the task and
     * let Moodle retry it later.
     *
     * @param int $userid The user ID to grade
     * @param bool $force_regrade Whether to regenerate feedback that is still current
     * @return array Result with success status and any errors
     */
    public function grade_single_submission($userid, $force_regrade = false) {
        try {
            // Taken before the text is read: should the student change the submission
            // while the AI request runs, the stored fingerprint is the older one and
            // the suggestion shows up as stale rather than as current.
            $fingerprint = $this->get_submission_fingerprint($userid);

            if (!$force_regrade && $this->has_current_feedback($userid, $fingerprint)) {
                return [
                    'success'        => false,
                    'already_graded' => true,
                    'error'          => get_string('already_has_feedback', 'local_assignmentaifeedback'),
                ];
            }

            $rubric_text = $this->get_rubric_text();
            if (!$rubric_text) {
                $rubric_text = $this->get_assignment_description();
            }

            $has_rubric_text = $this->get_rubric_text();
            $instructions    = $this->resolve_instructions((bool)$has_rubric_text);

            $submission_text = $this->get_submission_text($userid);

            if (empty($submission_text)) {
                return ['success' => false, 'error' => get_string('error_no_submission', 'local_assignmentaifeedback')];
            }

            $max_grade = $this->get_max_grade();

            $prompt   = $this->build_prompt($instructions, $rubric_text, $submission_text, $max_grade);
            $feedback = $this->call_ai_service($prompt);

            if ($feedback) {
                $this->save_feedback($userid, $feedback, $fingerprint);
                return ['success' => true, 'regraded' => $force_regrade, 'feedback' => trim($feedback)];
            }

            return ['success' => false, 'error' => get_string('error_no_feedback', 'local_assignmentaifeedback')];

        } catch (\Exception $e) {
            // See the note on 'retryable' in grade_submissions().
            return ['success' => false, 'error' => $e->getMessage(), 'retryable' => true];
        }
    }

    /**
     * Resolve the instruction text to use (per-assignment override or global default).
     *
     * @param bool $has_rubric Whether a rubric file is present
     * @return string
     */
    private function resolve_instructions($has_rubric) {
        if ($has_rubric) {
            $instructions = $this->aiconfig->instructions_with_rubric ?? '';
            if (empty($instructions)) {
                $instructions = get_config('local_assignmentaifeedback', 'default_instructions_with_rubric');
            }
        } else {
            $instructions = $this->aiconfig->instructions_without_rubric ?? '';
            if (empty($instructions)) {
                $instructions = get_config('local_assignmentaifeedback', 'default_instructions_without_rubric');
            }
        }
        return (string)$instructions;
    }

    /**
     * Get the maximum grade configured for the assignment (fallback 100).
     *
     * @return int
     */
    private function get_max_grade() {
        $max_grade = $this->assignment->get_instance()->grade;
        if ($max_grade < 0) {
            $max_grade = 100;
        }
        return $max_grade;
    }

    /**
     * Extract text from the rubric file attachment
     *
     * @return string|false The rubric text or false if not found
     */
    private function get_rubric_text() {
        $fs    = get_file_storage();
        $files = $fs->get_area_files($this->context->id, 'local_assignmentaifeedback', 'rubric',
            $this->assignment->get_instance()->id, 'filename', false);

        foreach ($files as $file) {
            $extension = strtolower(pathinfo($file->get_filename(), PATHINFO_EXTENSION));

            switch ($extension) {
                case 'pdf':
                    return $this->extract_pdf_text($file);
                case 'txt':
                    return $this->extract_txt_text($file);
                case 'docx':
                case 'doc':
                    return $this->extract_docx_text($file);
                default:
                    break;
            }
        }

        return false;
    }

    /**
     * Extract text from a PDF file
     *
     * @param \stored_file $file
     * @return string The extracted text
     */
    private function extract_pdf_text($file) {
        $tempdir  = make_request_directory();
        $tempfile = $tempdir . '/assignmentaifeedback_' . uniqid() . '.pdf';
        $file->copy_content_to($tempfile);

        try {
            $output     = [];
            $return_var = 0;
            exec("pdftotext " . escapeshellarg($tempfile) . " -", $output, $return_var);

            if ($return_var === 0 && !empty($output)) {
                $text = implode("\n", $output);
            } else {
                $text = get_string('pdf_rubric_fallback', 'local_assignmentaifeedback', $file->get_filename());
            }

            @unlink($tempfile);
            return $text;

        } catch (\Exception $e) {
            @unlink($tempfile);
            throw new \Exception(get_string('rubric_parse_error', 'local_assignmentaifeedback', $e->getMessage()));
        }
    }

    /**
     * Extract text from a TXT file
     *
     * @param \stored_file $file
     * @return string The extracted text
     */
    private function extract_txt_text($file) {
        return $file->get_content();
    }

    /**
     * Extract text from a DOCX file
     *
     * @param \stored_file $file
     * @return string The extracted text
     */
    private function extract_docx_text($file) {
        $tempdir  = make_request_directory();
        $tempfile = $tempdir . '/assignmentaifeedback_' . uniqid() . '.docx';
        $file->copy_content_to($tempfile);

        try {
            $output     = [];
            $return_var = 0;
            exec("docx2txt " . escapeshellarg($tempfile) . " -", $output, $return_var);

            if ($return_var === 0 && !empty($output)) {
                $text = implode("\n", $output);
                @unlink($tempfile);
                return $text;
            }

            if (class_exists('ZipArchive')) {
                $zip = new \ZipArchive();
                if ($zip->open($tempfile) === true) {
                    $xml = $zip->getFromName('word/document.xml');
                    $zip->close();

                    if ($xml) {
                        $text = strip_tags($xml);
                        $text = preg_replace('/\s+/', ' ', $text);
                        $text = trim($text);
                        @unlink($tempfile);
                        return $text;
                    }
                }
            }

            @unlink($tempfile);
            return get_string('pdf_rubric_fallback', 'local_assignmentaifeedback', $file->get_filename());

        } catch (\Exception $e) {
            @unlink($tempfile);
            throw new \Exception(get_string('rubric_parse_error', 'local_assignmentaifeedback', $e->getMessage()));
        }
    }

    /**
     * Get assignment description to use as grading criteria when no rubric exists
     *
     * @return string The assignment description/instructions
     */
    private function get_assignment_description() {
        $instance    = $this->assignment->get_instance();
        $description = '';

        if (!empty($instance->intro)) {
            $description = strip_tags($instance->intro, '<p><br><ul><ol><li>');
            $description = trim($description);
        }

        if (empty($description)) {
            $description = get_string('default_grading_criteria', 'local_assignmentaifeedback');
        }

        return get_string('assignment_instructions_label', 'local_assignmentaifeedback') . "\n" . $description;
    }

    /**
     * Get all submitted submissions without a current AI feedback suggestion.
     *
     * Public so the bulk endpoint can queue one background task per submission
     * instead of grading them all inside a single request.
     *
     * @return array Array of submissions keyed by userid
     */
    public function get_pending_submissions() {
        $groupid     = optional_param('group', 0, PARAM_INT);
        $participants = $this->assignment->list_participants($groupid, false);
        $pending     = [];

        foreach ($participants as $userid => $participant) {
            $submission = $this->assignment->get_user_submission($userid, false);

            if (!$submission || $submission->status !== ASSIGN_SUBMISSION_STATUS_SUBMITTED) {
                continue;
            }

            // Skip submissions whose suggestion still matches what was handed in.
            // A suggestion for an earlier version of the submission is regenerated.
            if ($this->has_current_feedback($userid)) {
                continue;
            }

            $pending[$userid] = $submission;
        }

        return $pending;
    }

    /**
     * Whether a stored AI feedback suggestion matches the current submission.
     *
     * The AI feedback is stored in the plugin's own table (advisory only, never
     * in the official feedback field), so a current row means the submission was
     * already processed and lets the graders skip it.
     *
     * @param int $userid
     * @param string|null $fingerprint Current submission fingerprint, if already known
     * @return bool
     */
    private function has_current_feedback($userid, $fingerprint = null) {
        $record = self::get_stored_feedback_record($this->assignment->get_instance()->id, $userid);

        return $record && !$this->is_feedback_stale($record, $userid, $fingerprint);
    }

    /**
     * Whether a stored suggestion was generated from an earlier version of the submission.
     *
     * Rows stored before the fingerprint was introduced carry none. For those the
     * submission's modification time is the best available hint.
     *
     * @param \stdClass $record Row from local_assignmentaifeedback_feedback
     * @param int $userid
     * @param string|null $fingerprint Current submission fingerprint, if already known
     * @return bool
     */
    public function is_feedback_stale($record, $userid, $fingerprint = null) {
        if (!empty($record->submissionhash)) {
            if ($fingerprint === null) {
                $fingerprint = $this->get_submission_fingerprint($userid);
            }
            return $record->submissionhash !== $fingerprint;
        }

        $submission = $this->assignment->get_user_submission($userid, false);
        return $submission && $submission->timemodified > $record->timemodified;
    }

    /**
     * Fingerprint of the submission content the AI reads.
     *
     * Built from the online text and the content hashes of the submitted files,
     * so it needs no text extraction and changes exactly when the student changes
     * what they hand in. Uploading an identical file again keeps the fingerprint.
     *
     * @param int $userid
     * @return string SHA-1 hex digest, or '' when there is no submission
     */
    public function get_submission_fingerprint($userid) {
        global $DB;

        $submission = $this->assignment->get_user_submission($userid, false);
        if (!$submission) {
            return '';
        }

        $parts = [];

        $parts[] = (string) $DB->get_field('assignsubmission_onlinetext', 'onlinetext',
            ['assignment' => $this->assignment->get_instance()->id, 'submission' => $submission->id]);

        $fs    = get_file_storage();
        $files = $fs->get_area_files($this->context->id, 'assignsubmission_file',
            'submission_files', $submission->id, 'filepath, filename', false);

        foreach ($files as $file) {
            $parts[] = $file->get_filepath() . $file->get_filename() . ':' . $file->get_contenthash();
        }

        return sha1(implode("\n", $parts));
    }

    /**
     * Get submission text for a user - handles online text and file uploads.
     *
     * @param int $userid
     * @return string The submission text
     */
    private function get_submission_text($userid) {
        global $DB;

        $submission = $this->assignment->get_user_submission($userid, false);
        if (!$submission) {
            return '';
        }

        $text = '';

        $onlinetext = $DB->get_record('assignsubmission_onlinetext',
            ['assignment' => $this->assignment->get_instance()->id, 'submission' => $submission->id]);

        if ($onlinetext && !empty($onlinetext->onlinetext)) {
            $text = strip_tags($onlinetext->onlinetext);
        }

        $file_text = $this->extract_file_submission_text($submission);
        if ($file_text) {
            if (!empty($text)) {
                $text .= "\n\n" . get_string('file_submission_separator', 'local_assignmentaifeedback') . "\n\n";
            }
            $text .= $file_text;
        }

        return trim($text);
    }

    /**
     * Extract text from file submissions
     *
     * @param \stdClass $submission The submission object
     * @return string Extracted text from all submitted files
     */
    private function extract_file_submission_text($submission) {
        $fs    = get_file_storage();
        $files = $fs->get_area_files($this->context->id, 'assignsubmission_file',
            'submission_files', $submission->id, 'filename', false);

        $text = '';

        foreach ($files as $file) {
            $filename     = $file->get_filename();
            $extension    = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            $file_content = '';

            switch ($extension) {
                case 'pdf':
                    $file_content = $this->extract_pdf_text($file);
                    break;
                case 'txt':
                    $file_content = $this->extract_txt_text($file);
                    break;
                case 'docx':
                case 'doc':
                    $file_content = $this->extract_docx_text($file);
                    break;
                case 'pptx':
                case 'ppt':
                    $file_content = $this->extract_pptx_text($file);
                    break;
                case 'odt':
                    $file_content = $this->extract_odt_text($file);
                    break;
                default:
                    $file_content = get_string('unsupported_file_type', 'local_assignmentaifeedback', $filename);
            }

            if ($file_content) {
                if (!empty($text)) {
                    $text .= "\n\n";
                }
                $text .= "--- " . get_string('file_label', 'local_assignmentaifeedback', $filename) . " ---\n" . $file_content;
            }
        }

        return $text;
    }

    /**
     * Extract text from a PowerPoint file
     *
     * @param \stored_file $file
     * @return string The extracted text
     */
    private function extract_pptx_text($file) {
        $tempdir  = make_request_directory();
        $tempfile = $tempdir . '/assignmentaifeedback_' . uniqid() . '.pptx';
        $file->copy_content_to($tempfile);

        try {
            if (class_exists('ZipArchive')) {
                $zip = new \ZipArchive();
                if ($zip->open($tempfile) === true) {
                    $text        = '';
                    $slide_count = 0;

                    for ($i = 1; $i <= 50; $i++) {
                        $slide_xml = $zip->getFromName("ppt/slides/slide{$i}.xml");
                        if ($slide_xml === false) {
                            break;
                        }

                        $slide_count++;
                        $slide_text = strip_tags($slide_xml);
                        $slide_text = preg_replace('/\s+/', ' ', $slide_text);
                        $text      .= "\n\n" . get_string('slide_label', 'local_assignmentaifeedback', $slide_count) . "\n" . trim($slide_text);
                    }

                    $zip->close();
                    @unlink($tempfile);
                    return trim($text);
                }
            }

            @unlink($tempfile);
            return get_string('extraction_unavailable', 'local_assignmentaifeedback', $file->get_filename());

        } catch (\Exception $e) {
            @unlink($tempfile);
            return get_string('extraction_error', 'local_assignmentaifeedback', $file->get_filename());
        }
    }

    /**
     * Extract text from an ODT file
     *
     * @param \stored_file $file
     * @return string The extracted text
     */
    private function extract_odt_text($file) {
        $tempdir  = make_request_directory();
        $tempfile = $tempdir . '/assignmentaifeedback_' . uniqid() . '.odt';
        $file->copy_content_to($tempfile);

        try {
            if (class_exists('ZipArchive')) {
                $zip = new \ZipArchive();
                if ($zip->open($tempfile) === true) {
                    $xml = $zip->getFromName('content.xml');
                    $zip->close();

                    if ($xml) {
                        $text = strip_tags($xml);
                        $text = preg_replace('/\s+/', ' ', $text);
                        $text = trim($text);
                        @unlink($tempfile);
                        return $text;
                    }
                }
            }

            @unlink($tempfile);
            return get_string('extraction_unavailable', 'local_assignmentaifeedback', $file->get_filename());

        } catch (\Exception $e) {
            @unlink($tempfile);
            return get_string('extraction_error', 'local_assignmentaifeedback', $file->get_filename());
        }
    }

    /**
     * Build the AI prompt.
     *
     * The prompt is deliberately minimal: the teacher's instructions and the
     * rubric drive the entire grading behaviour. The plugin only assembles the
     * building blocks (instructions, rubric/criteria, submission, framing) and
     * adds no hidden pedagogical or cultural context of its own.
     *
     * @param string $instructions
     * @param string $rubric_text
     * @param string $submission_text
     * @param int $max_grade
     * @return string The complete prompt
     */
    private function build_prompt($instructions, $rubric_text, $submission_text, $max_grade) {
        $assignment_label = get_string('assignment_instructions_label', 'local_assignmentaifeedback');
        $has_rubric       = (strpos($rubric_text, $assignment_label) !== 0);

        $parts = [];

        // Teacher instructions (per-assignment or global default).
        $parts[] = trim($instructions);

        // Rubric or, as fallback, the assignment description.
        if ($has_rubric) {
            $parts[] = get_string('grading_rubric_label', 'local_assignmentaifeedback') . "\n" . $rubric_text;
        } else {
            $parts[] = $rubric_text;
        }

        // The student submission to be assessed.
        $parts[] = get_string('student_submission_label', 'local_assignmentaifeedback') . "\n" . $submission_text;

        // Neutral framing.
        $framing  = get_string('prompt_framing_heading', 'local_assignmentaifeedback') . "\n";
        $framing .= "- " . get_string('prompt_max_points', 'local_assignmentaifeedback', $max_grade) . "\n";
        $framing .= "- " . get_string('prompt_recommendation_only', 'local_assignmentaifeedback') . "\n";
        $framing .= "- " . get_string('prompt_address_language', 'local_assignmentaifeedback');
        $parts[] = $framing;

        return implode("\n\n", $parts);
    }

    /**
     * Call the configured AI backend to get feedback.
     *
     * Two backends are supported:
     *  - 'ai_manager': the local_ai_manager plugin (routes to the models/instances
     *    configured there per purpose - e.g. locally hosted models).
     *  - 'core': Moodle's built-in core AI subsystem.
     *
     * @param string $prompt
     * @return string|false The AI feedback or false on error
     */
    private function call_ai_service($prompt) {
        $backend = get_config('local_assignmentaifeedback', 'ai_backend');
        if ($backend === false || $backend === '') {
            $backend = 'ai_manager';
        }

        if ($backend === 'ai_manager') {
            if (!class_exists('\local_ai_manager\manager')) {
                throw new \Exception(get_string('ai_error', 'local_assignmentaifeedback',
                    get_string('error_ai_manager_missing', 'local_assignmentaifeedback')));
            }
            return $this->call_ai_manager($prompt);
        }

        return $this->call_core_ai($prompt);
    }

    /**
     * Send the prompt through the local_ai_manager plugin.
     *
     * @param string $prompt
     * @return string The generated content
     */
    private function call_ai_manager($prompt) {
        $purpose = get_config('local_assignmentaifeedback', 'ai_manager_purpose');
        if (empty($purpose)) {
            $purpose = 'feedback';
        }

        try {
            $aimanager = new \local_ai_manager\manager($purpose);
        } catch (\Exception $e) {
            // Typically thrown when no tool/model is assigned to the chosen purpose.
            throw new \Exception(get_string('ai_error', 'local_assignmentaifeedback', $e->getMessage()));
        }

        $response = $aimanager->perform_request($prompt, 'local_assignmentaifeedback', $this->context->id);

        if ($response->get_code() === 200) {
            return $response->get_content();
        }

        $message = $response->get_errormessage();
        if (empty($message)) {
            $message = 'HTTP ' . $response->get_code();
        }
        throw new \Exception(get_string('ai_error', 'local_assignmentaifeedback', $message));
    }

    /**
     * Send the prompt through Moodle's core AI subsystem.
     *
     * @param string $prompt
     * @return string The generated content
     */
    private function call_core_ai($prompt) {
        global $USER;

        try {
            $action = new \core_ai\aiactions\generate_text(
                contextid: $this->context->id,
                userid: $USER->id,
                prompttext: $prompt
            );

            $aimanager = \core\di::get(\core_ai\manager::class);
            $response  = $aimanager->process_action($action);

            if ($response->get_success()) {
                return $response->get_response_data()['generatedcontent'] ?? '';
            } else {
                throw new \Exception($response->get_errormessage() ?: 'AI generation failed');
            }

        } catch (\Exception $e) {
            throw new \Exception(get_string('ai_error', 'local_assignmentaifeedback', $e->getMessage()));
        }
    }

    /**
     * Save the AI recommendation as a feedback comment.
     *
     * No numeric grade is written to the gradebook: the point recommendation
     * stays inside the feedback text, and the teacher sets the final grade.
     *
     * @param int $userid
     * @param string $feedback
     * @param string $fingerprint Fingerprint of the submission the feedback was generated from
     * @return bool Success status
     */
    private function save_feedback($userid, $feedback, $fingerprint) {
        global $DB;

        $feedback     = trim($feedback);
        $assignmentid = $this->assignment->get_instance()->id;
        $now          = time();

        // The AI feedback is a non-binding suggestion. It is stored in the
        // plugin's own table only and is NOT written into Moodle's feedback
        // comment field, so it can never be saved as the official feedback by
        // accident. Teachers can adopt it explicitly via the panel button.
        $existing = $DB->get_record('local_assignmentaifeedback_feedback', [
            'assignmentid' => $assignmentid,
            'userid'       => $userid,
        ]);

        if ($existing) {
            $existing->feedback       = $feedback;
            $existing->submissionhash = $fingerprint;
            $existing->timemodified   = $now;
            $DB->update_record('local_assignmentaifeedback_feedback', $existing);
        } else {
            $record = new \stdClass();
            $record->assignmentid   = $assignmentid;
            $record->userid         = $userid;
            $record->feedback       = $feedback;
            $record->submissionhash = $fingerprint;
            $record->timecreated    = $now;
            $record->timemodified = $now;
            $DB->insert_record('local_assignmentaifeedback_feedback', $record);
        }

        return true;
    }

    /**
     * Fetch the stored AI feedback suggestion for a submission.
     *
     * @param int $assignmentid
     * @param int $userid
     * @return string The stored feedback HTML, or empty string if none
     */
    public static function get_stored_feedback($assignmentid, $userid) {
        $record = self::get_stored_feedback_record($assignmentid, $userid);

        return $record ? (string) $record->feedback : '';
    }

    /**
     * Fetch the stored AI feedback row for a submission.
     *
     * The panel needs 'timemodified' to tell a newly generated suggestion from the
     * one it is already showing, so it can poll while a queued run is pending.
     *
     * @param int $assignmentid
     * @param int $userid
     * @return \stdClass|false The record, or false if none
     */
    public static function get_stored_feedback_record($assignmentid, $userid) {
        global $DB;

        return $DB->get_record('local_assignmentaifeedback_feedback', [
            'assignmentid' => $assignmentid,
            'userid'       => $userid,
        ]);
    }
}
