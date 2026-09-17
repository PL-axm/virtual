<?php
define('CLI_SCRIPT', true);
require(__DIR__ . '/../config.php');
require_once($CFG->libdir . '/adminlib.php');

$htmlfile = __DIR__ . '/../landing/frontpage.html';
if (!file_exists($htmlfile)) {
    cli_error('No se encontro landing/frontpage.html');
}

$html = file_get_contents($htmlfile);

require_once($CFG->dirroot . '/course/lib.php');

// La portada (index.php) renderiza el resumen de la seccion 1 del sitio,
// no course.summary. Se escribe ahi.
$site = get_site();
course_create_sections_if_missing($site, 1);
$section = $DB->get_record('course_sections', ['course' => SITEID, 'section' => 1], '*', MUST_EXIST);
$section->summary = $html;
$section->summaryformat = FORMAT_HTML;
$section->timemodified = time();
$DB->update_record('course_sections', $section);

// Se deja tambien en el resumen del sitio (usado como meta description).
$DB->set_field('course', 'summary', $html, ['id' => SITEID]);
$DB->set_field('course', 'summaryformat', FORMAT_HTML, ['id' => SITEID]);

set_config('frontpage', '');
set_config('frontpageloggedin', '6');

rebuild_course_cache(SITEID, true);

echo "Pagina principal actualizada correctamente.\n";
