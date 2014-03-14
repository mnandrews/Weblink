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
 * weblink module main user interface
 *
 * @package    mod
 * @subpackage weblink
 * @copyright  
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../../config.php');
require_once("$CFG->dirroot/mod/weblink/locallib.php");
require_once($CFG->libdir . '/completionlib.php');

$id       = optional_param('id', 0, PARAM_INT);        // Course module ID
$u        = optional_param('u', 0, PARAM_INT);         // weblink instance id
$redirect = optional_param('redirect', 0, PARAM_BOOL);

if ($u) {  // Two ways to specify the module
    $weblink = $DB->get_record('weblink', array('id'=>$u), '*', MUST_EXIST);
    $cm = get_coursemodule_from_instance('weblink', $weblink->id, $weblink->course, false, MUST_EXIST);

} else {
    $cm = get_coursemodule_from_id('weblink', $id, 0, false, MUST_EXIST);
    $weblink = $DB->get_record('weblink', array('id'=>$cm->instance), '*', MUST_EXIST);
}

$course = $DB->get_record('course', array('id'=>$cm->course), '*', MUST_EXIST);

require_course_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/weblink:view', $context);

add_to_log($course->id, 'weblink', 'view', 'view.php?id='.$cm->id, $weblink->id, $cm->id);

// Update 'viewed' state if required by completion system
$completion = new completion_info($course);
$completion->set_module_viewed($cm);

$PAGE->set_weblink('/mod/weblink/view.php', array('id' => $cm->id));

// Make sure weblink exists before generating output - some older sites may contain empty urls
// Do not use PARAM_weblink here, it is too strict and does not support general URIs!
$extweblink = trim($weblink->externalweblink);
if (empty($extweblink) or $extweblink === 'http://') {
    weblink_print_header($weblink, $cm, $course);
    weblink_print_heading($weblink, $cm, $course);
    weblink_print_intro($weblink, $cm, $course);
    notice(get_string('invalidstoredweblink', 'weblink'), new moodle_weblink('/course/view.php', array('id'=>$cm->course)));
    die;
}
unset($extweblink);

$displaytype = weblink_get_final_display_type($weblink);
if ($displaytype == RESOURCELIB_DISPLAY_OPEN) {
    // For 'open' links, we always redirect to the content - except if the user
    // just chose 'save and display' from the form then that would be confusing
    if (!isset($_SERVER['HTTP_REFERER']) || strpos($_SERVER['HTTP_REFERER'], 'modedit.php') === false) {
        $redirect = true;
    }
}

if ($redirect) {
    // coming from course page or weblink index page,
    // the redirection is needed for completion tracking and logging
    $fullweblink = str_replace('&amp;', '&', weblink_get_full_weblink($weblink, $cm, $course));

    if (!course_get_format($course)->has_view_page()) {
        // If course format does not have a view page, add redirection delay with a link to the edit page.
        // Otherwise teacher is redirected to the external weblink without any possibility to edit activity or course settings.
        $editweblink = null;
        if (has_capability('moodle/course:manageactivities', $context)) {
            $editweblink = new moodle_weblink('/course/modedit.php', array('update' => $cm->id));
            $edittext = get_string('editthisactivity');
        } else if (has_capability('moodle/course:update', $context->get_course_context())) {
            $editweblink = new moodle_weblink('/course/edit.php', array('id' => $course->id));
            $edittext = get_string('editcoursesettings');
        }
        if ($editweblink) {
            redirect($fullweblink, html_writer::link($editweblink, $edittext)."<br/>".
                    get_string('pageshouldredirect'), 10);
        }
    }
    redirect($fullweblink);
}

switch ($displaytype) {
    case RESOURCELIB_DISPLAY_EMBED:
        weblink_display_embed($weblink, $cm, $course);
        break;
    case RESOURCELIB_DISPLAY_FRAME:
        weblink_display_frame($weblink, $cm, $course);
        break;
    default:
        weblink_print_workaround($weblink, $cm, $course);
        break;
}
