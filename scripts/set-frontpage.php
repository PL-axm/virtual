<?php
define('CLI_SCRIPT', true);
require(__DIR__ . '/../config.php');
require_once($CFG->libdir . '/adminlib.php');

$htmlfile = __DIR__ . '/../landing/frontpage.html';
if (!file_exists($htmlfile)) {
    cli_error('No se encontro landing/frontpage.html');
}

$html = file_get_contents($htmlfile);

$course = $DB->get_record('course', ['id' => SITEID], '*', MUST_EXIST);
$course->summary = $html;
$course->summaryformat = FORMAT_HTML;
$DB->update_record('course', $course);

set_config('frontpage', '');
set_config('frontpageloggedin', '6');

echo "Pagina principal actualizada correctamente.\n";
