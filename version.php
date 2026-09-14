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
 * Version information for local_assignmentaifeedback
 *
 * @package    local_assignmentaifeedback
 * @copyright  2026 Brian A. Pool, National Trail Local Schools
 * @copyright  2026 Anpassung Hochschulkontext FH Graubünden
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$plugin->component = 'local_assignmentaifeedback';
$plugin->version = 2026091400;  // YYYYMMDDXX format (2026-09-14, version 00)
$plugin->requires = 2024042200;  // Moodle 4.5
$plugin->maturity = MATURITY_BETA;
$plugin->release = 'v3.0.0-fhgr';

// The grading requests are routed through local_ai_manager (https://github.com/bycs-lp/moodle-local_ai_manager),
// which has to be installed separately. ANY_VERSION: any release providing \local_ai_manager\manager works.
$plugin->dependencies = [
    'local_ai_manager' => ANY_VERSION,
];
