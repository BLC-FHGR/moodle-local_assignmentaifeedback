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
 * Test the Open WebUI connection: upload a PDF, wait for processing, chat, delete.
 *
 * @package    local_assignmentaifeedback
 * @copyright  2026 Anpassung Hochschulkontext FH Graubünden
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->libdir . '/filelib.php');

[$options, $unrecognized] = cli_get_params(
    ['file' => '', 'user' => '', 'cmid' => 0, 'help' => false],
    ['f' => 'file', 'u' => 'user', 'h' => 'help']
);

if ($unrecognized) {
    cli_error(get_string('cliunknowoption', 'admin', implode("\n  ", $unrecognized)));
}

if ($options['help'] || $options['file'] === '' || $options['user'] === '') {
    cli_writeln("Test the Open WebUI connection of local_assignmentaifeedback.

Takes endpoint, API key and model from the Local AI Manager tool assigned to the
configured purpose, for the role of the given user, and runs the same access checks
as a grading run. Then uploads the PDF, waits for Open WebUI to process it, asks the
model about it with a fixed prompt and deletes the file again.

Options:
  -f, --file=PATH     PDF file to send
  -u, --user=NAME     Username to run as (e.g. the account for automatic grading)
      --cmid=ID       Course module to run the access checks in (default: system context)
  -h, --help          Print this help

Example:
  sudo -u www-data php local/assignmentaifeedback/cli/test_openwebui.php --file=/tmp/test.pdf --user=aigrader
");
    exit(0);
}

$path = $options['file'];
if (!is_readable($path) || !is_file($path)) {
    cli_error('File not readable: ' . $path);
}

$user = core_user::get_user_by_username($options['user'], '*', null, IGNORE_MISSING);
if (!$user || $user->deleted || $user->suspended) {
    cli_error('No active user with username: ' . $options['user']);
}
\core\cron::setup_user($user);

$context = $options['cmid'] ? context_module::instance((int) $options['cmid']) : context_system::instance();

$purpose = get_config('local_assignmentaifeedback', 'ai_manager_purpose') ?: 'feedback';

// A check of the end of the document shows whether Open WebUI passed the whole document:
// with retrieval instead of the full content, the last sentence is often missing.
$prompt = 'Summarise the attached document in three sentences. Then quote its last sentence verbatim.';

$fs = get_file_storage();
$draftfile = null;
$fileid = null;

try {
    $client = \local_assignmentaifeedback\openwebui_client::from_ai_manager($purpose, $context);
    cli_writeln('Purpose:  ' . $purpose);
    cli_writeln('Open WebUI: ' . $client->get_baseurl());
    cli_writeln('Model:    ' . $client->get_model());
    cli_writeln('Timeout:  ' . \local_assignmentaifeedback\local\request_timeout::background_seconds() . ' s');

    // upload_file() takes a stored_file, as it will for submissions.
    $draftfile = $fs->create_file_from_pathname([
        'contextid' => context_user::instance($user->id)->id,
        'component' => 'user',
        'filearea'  => 'draft',
        'itemid'    => file_get_unused_draft_itemid(),
        'filepath'  => '/',
        'filename'  => clean_filename(basename($path)),
    ], $path);

    $started = microtime(true);
    $fileid = $client->upload_file($draftfile);
    cli_writeln('Uploaded: file id ' . $fileid);

    $client->wait_until_processed($fileid);
    cli_writeln(sprintf('Processed after %.1f s', microtime(true) - $started));

    $answer = $client->chat('', $prompt, [$fileid]);
    cli_writeln(sprintf('Answer after %.1f s:', microtime(true) - $started));
    cli_writeln('');
    cli_writeln($answer);
    cli_writeln('');
    $exitcode = 0;

} catch (\Throwable $e) {
    cli_problem('Failed: ' . $e->getMessage());
    $exitcode = 1;

} finally {
    if ($fileid !== null) {
        try {
            $client->delete_file($fileid);
            cli_writeln('Deleted:  file id ' . $fileid);
        } catch (\Throwable $e) {
            cli_problem('Could not delete file id ' . $fileid . ' in Open WebUI: ' . $e->getMessage());
            $exitcode = 1;
        }
    }
    if ($draftfile) {
        $draftfile->delete();
    }
}

exit($exitcode);
