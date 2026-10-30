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
 * Client for the Open WebUI file and chat API.
 *
 * @package    local_assignmentaifeedback
 * @copyright  2026 Anpassung Hochschulkontext FH Graubünden
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_assignmentaifeedback;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/filelib.php');

/**
 * Sends PDF submission files to Open WebUI as files instead of extracted text.
 *
 * Endpoint, API key and model are taken from the local_ai_manager tool assigned to
 * the configured purpose, and the access checks local_ai_manager runs before each
 * of its own requests are repeated here (see from_ai_manager()). The requests
 * themselves go to Open WebUI directly: local_ai_manager has no way to pass files,
 * so they are neither logged nor counted there.
 *
 * The API key is only ever sent in the Authorization header. It is never part of
 * an exception message or of any output.
 */
class openwebui_client {

    /** @var string Path of the OpenAI-compatible chat endpoint of Open WebUI. */
    const CHAT_PATH = '/api/chat/completions';

    /** @var int Seconds between two checks of the file processing status. */
    const POLL_INTERVAL = 2;

    /** @var string Base URL of the Open WebUI instance, without trailing slash. */
    private $baseurl;

    /** @var string API key of the Open WebUI account. */
    private $apikey;

    /** @var string Model ID in Open WebUI. */
    private $model;

    /** @var int Timeout in seconds, per HTTP request and for the file processing. */
    private $timeout;

    /** @var bool Whether to verify the TLS certificate of the server. */
    private $verifyssl;

    /**
     * Constructor.
     *
     * @param string $baseurl Base URL of the Open WebUI instance
     * @param string $apikey API key of the Open WebUI account
     * @param string $model Model ID in Open WebUI
     * @param int $timeout Timeout in seconds
     * @param bool $verifyssl Whether to verify the TLS certificate
     */
    public function __construct(string $baseurl, string $apikey, string $model, int $timeout, bool $verifyssl = true) {
        $this->baseurl   = rtrim($baseurl, '/');
        $this->apikey    = $apikey;
        $this->model     = $model;
        $this->timeout   = $timeout;
        $this->verifyssl = $verifyssl;
    }

    /**
     * Build a client from the local_ai_manager tool assigned to a purpose.
     *
     * Runs as the current user: the tool depends on that user's role, and the same
     * checks apply that local_ai_manager runs before each of its own requests
     * (capability, tenant, lock, terms of use, scope, restrictions, quota).
     *
     * @param string $purpose local_ai_manager purpose, e.g. 'feedback'
     * @param \context $context Context the request is made in
     * @return self
     * @throws \moodle_exception When a check fails or the tool is not an Open WebUI endpoint
     */
    public static function from_ai_manager(string $purpose, \context $context): self {
        global $USER;

        if (!class_exists('\local_ai_manager\manager')) {
            throw new \moodle_exception('error_ai_manager_missing', 'local_assignmentaifeedback');
        }

        if (!has_capability('local/ai_manager:use', $context)) {
            throw new \moodle_exception('error_http403nocapability', 'local_ai_manager');
        }

        $configmanager = \core\di::get(\local_ai_manager\local\config_manager::class);
        if (!$configmanager->is_tenant_enabled()) {
            throw new \moodle_exception('error_http403disabled', 'local_ai_manager');
        }

        $userinfo = new \local_ai_manager\local\userinfo($USER->id);
        if ($userinfo->is_locked()) {
            throw new \moodle_exception('error_http403blocked', 'local_ai_manager');
        }

        $factory     = \core\di::get(\local_ai_manager\local\connector_factory::class);
        $purposeobj  = $factory->get_purpose_by_purpose_string($purpose);
        $instance    = $factory->get_connector_instance_by_purpose($purpose, $userinfo->get_role());
        if (is_null($instance)) {
            throw new \moodle_exception('error_purposenotconfigured', 'local_ai_manager', '',
                get_string('pluginname', 'aipurpose_' . $purpose));
        }
        if (!$instance->is_enabled()) {
            throw new \moodle_exception('exception_instanceunavailable', 'local_ai_manager');
        }

        if (!$userinfo->is_confirmed()) {
            throw new \moodle_exception('error_http403notconfirmed', 'local_ai_manager');
        }

        if ($userinfo->get_scope() === \local_ai_manager\local\userinfo::SCOPE_COURSES_ONLY
                && is_null(\local_ai_manager\ai_manager_utils::find_closest_parent_course_context($context))) {
            throw new \moodle_exception('error_http403coursesonly', 'local_ai_manager');
        }

        $restriction = new \local_ai_manager\hook\additional_user_restriction($userinfo, $context, $purposeobj);
        \core\di::get(\core\hook\manager::class)->dispatch($restriction);
        if (!$restriction->is_allowed()) {
            throw new \moodle_exception('error_openwebui_restricted', 'local_assignmentaifeedback', '',
                $restriction->get_message());
        }

        // Quota: the requests of this client are not counted, but a user who used up
        // or was denied the quota must not get around it this way.
        $maxrequests = (int) $configmanager->get_max_requests($purposeobj, $userinfo->get_role());
        if ($maxrequests === 0) {
            throw new \moodle_exception('error_http403usertype', 'local_ai_manager');
        }
        $usage = new \local_ai_manager\local\userusage($purposeobj, $USER->id);
        if ($usage->get_currentusage() >= $maxrequests) {
            throw new \moodle_exception('error_http429', 'local_ai_manager', '', (object) [
                'count'  => $maxrequests,
                'period' => format_time($configmanager->get_max_requests_period()),
            ]);
        }

        // Only an OpenAI-compatible tool pointing at the Open WebUI chat endpoint
        // tells us where Open WebUI is. Azure uses a different authentication.
        $endpoint = (string) $instance->get_endpoint();
        $isazure  = method_exists($instance, 'azure_enabled') && $instance->azure_enabled();
        if ($instance->get_connector() !== 'chatgpt' || $isazure
                || !str_ends_with(rtrim($endpoint, '/'), self::CHAT_PATH)) {
            throw new \moodle_exception('error_openwebui_tool', 'local_assignmentaifeedback', '', $purpose);
        }
        $baseurl = substr(rtrim($endpoint, '/'), 0, -strlen(self::CHAT_PATH));

        // Same resolution as \local_ai_manager\base_connector::get_api_key(), which is protected.
        $globalapikey = get_config('aitool_chatgpt', 'globalapikey');
        $apikey = (!empty($globalapikey) && $instance->get_useglobalapikey()) ? $globalapikey : $instance->get_apikey();
        if (empty($apikey)) {
            throw new \moodle_exception('error_openwebui_noapikey', 'local_assignmentaifeedback');
        }

        $model = (string) $instance->get_model_name();
        if ($model === '') {
            throw new \moodle_exception('error_openwebui_nomodel', 'local_assignmentaifeedback');
        }

        return new self($baseurl, $apikey, $model, local\request_timeout::background_seconds(),
            !empty(get_config('local_ai_manager', 'verifyssl')));
    }

    /**
     * Base URL of the Open WebUI instance.
     *
     * @return string
     */
    public function get_baseurl(): string {
        return $this->baseurl;
    }

    /**
     * Model ID the chat requests use.
     *
     * @return string
     */
    public function get_model(): string {
        return $this->model;
    }

    /**
     * Upload a file to Open WebUI. Processing (text extraction) starts in the background.
     *
     * @param \stored_file $file
     * @return string The Open WebUI file ID
     */
    public function upload_file(\stored_file $file): string {
        $response = $this->request('upload', 'post', '/api/v1/files/', ['file' => $file]);
        if (empty($response['id'])) {
            throw $this->error('upload', get_string('error_openwebui_badresponse', 'local_assignmentaifeedback'));
        }
        return (string) $response['id'];
    }

    /**
     * Wait until Open WebUI has extracted the content of an uploaded file.
     *
     * @param string $fileid
     * @param int|null $timeout Seconds to wait, defaults to the client timeout
     */
    public function wait_until_processed(string $fileid, ?int $timeout = null): void {
        $deadline = time() + ($timeout ?? $this->timeout);

        while (true) {
            $response = $this->request('status', 'get', '/api/v1/files/' . rawurlencode($fileid) . '/process/status');
            $status = $response['status'] ?? '';

            if ($status === 'completed') {
                return;
            }
            if ($status === 'failed') {
                throw new \moodle_exception('error_openwebui_processing', 'local_assignmentaifeedback', '',
                    $this->get_processing_error($fileid));
            }
            if (time() >= $deadline) {
                throw new \moodle_exception('error_openwebui_processtimeout', 'local_assignmentaifeedback');
            }
            sleep(self::POLL_INTERVAL);
        }
    }

    /**
     * Send a chat request with uploaded files attached in full.
     *
     * 'context' => 'full' makes Open WebUI pass the whole extracted document to the
     * model instead of the excerpts retrieval would pick.
     *
     * @param string $systemprompt System message, '' for none
     * @param string $usercontent User message
     * @param string[] $fileids Open WebUI file IDs
     * @return string The generated text
     */
    public function chat(string $systemprompt, string $usercontent, array $fileids): string {
        $messages = [];
        if ($systemprompt !== '') {
            $messages[] = ['role' => 'system', 'content' => $systemprompt];
        }
        $messages[] = ['role' => 'user', 'content' => $usercontent];

        $files = [];
        foreach ($fileids as $fileid) {
            $files[] = ['type' => 'file', 'id' => $fileid, 'context' => 'full'];
        }

        $payload = [
            'model'    => $this->model,
            'stream'   => false,
            'messages' => $messages,
        ];
        if ($files) {
            $payload['files'] = $files;
        }

        $response = $this->request('chat', 'post', self::CHAT_PATH, json_encode($payload));
        $content = $response['choices'][0]['message']['content'] ?? null;
        if (!is_string($content) || trim($content) === '') {
            throw $this->error('chat', get_string('error_openwebui_badresponse', 'local_assignmentaifeedback'));
        }
        return $content;
    }

    /**
     * Delete an uploaded file, including its vector collection.
     *
     * @param string $fileid
     */
    public function delete_file(string $fileid): void {
        $this->request('delete', 'delete', '/api/v1/files/' . rawurlencode($fileid));
    }

    /**
     * Read the error Open WebUI stored for a file whose processing failed.
     *
     * @param string $fileid
     * @return string
     */
    private function get_processing_error(string $fileid): string {
        try {
            $file = $this->request('status', 'get', '/api/v1/files/' . rawurlencode($fileid));
            if (!empty($file['data']['error']) && is_string($file['data']['error'])) {
                return \core_text::substr($file['data']['error'], 0, 300);
            }
        } catch (\moodle_exception $e) {
            // The generic message below is good enough.
            unset($e);
        }
        return get_string('error_openwebui_badresponse', 'local_assignmentaifeedback');
    }

    /**
     * Send one request and decode the JSON response.
     *
     * @param string $step Name of the step for error messages
     * @param string $method 'get', 'post' or 'delete'
     * @param string $path Path below the base URL
     * @param array|string|null $body Form fields (multipart) or a JSON string
     * @return array The decoded response
     */
    private function request(string $step, string $method, string $path, $body = null): array {
        $curl = new \curl();
        $curl->setHeader('Authorization: Bearer ' . $this->apikey);
        $curl->setHeader('Accept: application/json');
        if (is_string($body)) {
            $curl->setHeader('Content-Type: application/json');
        }

        $options = [
            'CURLOPT_TIMEOUT'        => $this->timeout,
            'CURLOPT_CONNECTTIMEOUT' => min(30, $this->timeout),
        ];
        if (!$this->verifyssl) {
            $options['CURLOPT_SSL_VERIFYPEER'] = false;
            $options['CURLOPT_SSL_VERIFYHOST'] = 0;
        }

        $url = $this->baseurl . $path;
        switch ($method) {
            case 'post':
                $raw = $curl->post($url, $body ?? '', $options);
                break;
            case 'delete':
                // curl::delete() sets anonymous basic auth; libcurl replaces its own
                // Authorization header with the one set above, so the API key is used.
                $raw = $curl->delete($url, [], $options);
                break;
            default:
                $raw = $curl->get($url, [], $options);
        }

        // Transport errors, including a URL blocked by Moodle's HTTP security settings.
        if (!empty($curl->error)) {
            throw $this->error($step, $curl->error);
        }

        $info = $curl->get_info();
        $code = (int) ($info['http_code'] ?? 0);
        $decoded = json_decode((string) $raw, true);

        if ($code < 200 || $code >= 300) {
            // Open WebUI reports errors as {"detail": "..."}.
            $detail = is_array($decoded) && isset($decoded['detail']) && is_string($decoded['detail'])
                ? $decoded['detail'] : '';
            throw $this->error($step, trim('HTTP ' . $code . ' ' . \core_text::substr($detail, 0, 300)));
        }

        if (!is_array($decoded)) {
            // DELETE answers with a bare true.
            if ($method === 'delete') {
                return [];
            }
            throw $this->error($step, get_string('error_openwebui_badresponse', 'local_assignmentaifeedback'));
        }
        return $decoded;
    }

    /**
     * Build the exception for a failed request.
     *
     * @param string $step
     * @param string $message
     * @return \moodle_exception
     */
    private function error(string $step, string $message): \moodle_exception {
        return new \moodle_exception('error_openwebui_request', 'local_assignmentaifeedback', '',
            (object) ['step' => $step, 'message' => $message]);
    }
}
