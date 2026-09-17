<?php
// Limpia el resumen de la seccion 1 de la portada (landing anterior embebida en Moodle).
// La landing publica ahora la sirve local/landing a visitantes sin sesion.
define('CLI_SCRIPT', true);
require(__DIR__ . '/../config.php');
require_once($CFG->dirroot . '/course/lib.php');

$DB->set_field('course_sections', 'summary', '', ['course' => SITEID, 'section' => 1]);
$DB->set_field('course', 'summary', '', ['id' => SITEID]);
rebuild_course_cache(SITEID, true);

echo "Seccion 1 de la portada limpiada.\n";
