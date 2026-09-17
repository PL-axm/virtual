<?php
// Crea el certificado del curso piloto (COM-ASE) con su plantilla:
// titulo, nombre del participante, curso, fecha, codigo de verificacion y QR.
// Solo se habilita cuando el estudiante aprueba la evaluación final.
// Es idempotente. Uso: /opt/alt/php83/usr/bin/php scripts/setup-certificado-comase.php

define('CLI_SCRIPT', true);
require(__DIR__ . '/../config.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/course/modlib.php');
require_once($CFG->libdir . '/completionlib.php');

// Nombre de la entidad que certifica. Cámbialo por la razón social real.
$entidad = 'Virtual enelmapa.co';

$course = $DB->get_record('course', ['shortname' => 'COM-ASE'], '*', MUST_EXIST);
\core\session\manager::set_user(get_admin());

// La evaluación final debe existir: el certificado se restringe a ella.
$cmfinal = $DB->get_record('course_modules', ['course' => $course->id, 'idnumber' => 'COMASE-FINAL'], '*', MUST_EXIST);

$existente = $DB->get_record('course_modules', ['course' => $course->id, 'idnumber' => 'COMASE-CERT']);
if ($existente) {
    echo "= el certificado ya existe (cmid {$existente->id})\n";
    $cm = $existente;
} else {
    // Solo disponible tras aprobar la evaluación final.
    $availability = json_encode((object)[
        'op' => '&',
        'c' => [(object)['type' => 'completion', 'cm' => (int)$cmfinal->id, 'e' => COMPLETION_COMPLETE_PASS]],
        'showc' => [true],
    ]);

    $moduleinfo = (object)[
        'modulename' => 'customcert',
        'module' => $DB->get_field('modules', 'id', ['name' => 'customcert'], MUST_EXIST),
        'course' => $course->id,
        'section' => 3,
        'visible' => 1,
        'visibleoncoursepage' => 1,
        'cmidnumber' => 'COMASE-CERT',
        'groupmode' => 0,
        'groupingid' => 0,
        'availabilityconditionsjson' => $availability,
        'completion' => COMPLETION_TRACKING_AUTOMATIC,
        'completionview' => 1,
        'completionusegrade' => 0,
        'completionpassgrade' => 0,
        'completiongradeitemnumber' => null,
        'completionexpected' => 0,
        'name' => 'Tu certificado del curso',
        'intro' => '<p>Descarga aquí tu certificado en PDF. Incluye un código de verificación '
            . 'con el que cualquier persona puede comprobar que es auténtico.</p>',
        'introformat' => FORMAT_HTML,
        'showdescription' => 1,
        'requiredtime' => 0,
        'verifyany' => 1,
        'deliveryoption' => 'D',
        'emailstudents' => 0,
        'emailteachers' => 0,
        'emailothers' => '',
        'issueautomatically' => 1,
        'usecustomfilename' => 0,
        'customfilenamepattern' => '',
        'protection_print' => 0,
        'protection_modify' => 1,
        'protection_copy' => 0,
        'language' => '',
    ];
    $moduleinfo = add_moduleinfo($moduleinfo, $course);
    $cm = $DB->get_record('course_modules', ['id' => $moduleinfo->coursemodule], '*', MUST_EXIST);
    echo "+ certificado creado (cmid {$cm->id})\n";
}

$cert = $DB->get_record('customcert', ['id' => $cm->instance], '*', MUST_EXIST);
$page = $DB->get_record('customcert_pages', ['templateid' => $cert->templateid], '*', MUST_EXIST);

if ($DB->count_records('customcert_elements', ['pageid' => $page->id]) > 0) {
    echo "= la plantilla ya tiene elementos, no se toca\n";
    echo "\nListo: {$CFG->wwwroot}/mod/customcert/view.php?id={$cm->id}\n";
    exit(0);
}

// Página horizontal A4: 297 x 210 mm.
$DB->update_record('customcert_pages', (object)[
    'id' => $page->id,
    'width' => 297,
    'height' => 210,
    'leftmargin' => 15,
    'rightmargin' => 15,
    'timemodified' => time(),
]);

$ancho = 267; // 297 menos los márgenes.
$centro = \mod_customcert\element_helper::CUSTOMCERT_REF_POINT_TOPCENTER;

$elementos = [
    // Marco.
    ['name' => 'Marco', 'element' => 'border', 'data' => '3', 'font' => '', 'fontsize' => 0,
     'colour' => '#0A0F1D', 'posx' => 0, 'posy' => 0, 'width' => 0, 'refpoint' => null, 'alignment' => 'L'],

    ['name' => 'Entidad', 'element' => 'text', 'data' => $entidad, 'font' => 'helvetica', 'fontsize' => 14,
     'colour' => '#004AC6', 'posx' => 148, 'posy' => 28, 'width' => $ancho, 'refpoint' => $centro, 'alignment' => 'C'],

    ['name' => 'Título', 'element' => 'text', 'data' => 'CERTIFICADO DE PARTICIPACIÓN', 'font' => 'helvetica',
     'fontsize' => 26, 'colour' => '#0A0F1D', 'posx' => 148, 'posy' => 48, 'width' => $ancho,
     'refpoint' => $centro, 'alignment' => 'C'],

    ['name' => 'Otorgado a', 'element' => 'text', 'data' => 'Se certifica que', 'font' => 'helvetica',
     'fontsize' => 12, 'colour' => '#434655', 'posx' => 148, 'posy' => 72, 'width' => $ancho,
     'refpoint' => $centro, 'alignment' => 'C'],

    ['name' => 'Participante', 'element' => 'studentname', 'data' => null, 'font' => 'helveticaB', 'fontsize' => 24,
     'colour' => '#0A0F1D', 'posx' => 148, 'posy' => 84, 'width' => $ancho, 'refpoint' => $centro, 'alignment' => 'C'],

    ['name' => 'Completó', 'element' => 'text', 'data' => 'completó satisfactoriamente el curso',
     'font' => 'helvetica', 'fontsize' => 12, 'colour' => '#434655', 'posx' => 148, 'posy' => 108,
     'width' => $ancho, 'refpoint' => $centro, 'alignment' => 'C'],

    ['name' => 'Curso', 'element' => 'coursename', 'data' => '2', 'font' => 'helveticaB', 'fontsize' => 18,
     'colour' => '#004AC6', 'posx' => 148, 'posy' => 120, 'width' => $ancho, 'refpoint' => $centro, 'alignment' => 'C'],

    ['name' => 'Intensidad', 'element' => 'text', 'data' => 'Modalidad virtual · 3 módulos',
     'font' => 'helvetica', 'fontsize' => 11, 'colour' => '#64748B', 'posx' => 148, 'posy' => 140,
     'width' => $ancho, 'refpoint' => $centro, 'alignment' => 'C'],

    ['name' => 'Fecha', 'element' => 'date',
     // '-1' = fecha de emisión (CUSTOMCERT_DATE_ISSUE).
     'data' => json_encode(['dateitem' => '-1', 'dateformat' => 'strftimedate']),
     'font' => 'helvetica', 'fontsize' => 11, 'colour' => '#434655', 'posx' => 148, 'posy' => 152,
     'width' => $ancho, 'refpoint' => $centro, 'alignment' => 'C'],

    ['name' => 'Código de verificación', 'element' => 'code', 'data' => null, 'font' => 'helvetica',
     'fontsize' => 9, 'colour' => '#64748B', 'posx' => 30, 'posy' => 188, 'width' => 120,
     'refpoint' => \mod_customcert\element_helper::CUSTOMCERT_REF_POINT_TOPLEFT, 'alignment' => 'L'],

    ['name' => 'Verificación', 'element' => 'text',
     'data' => 'Verifica este certificado en ' . $CFG->wwwroot . '/mod/customcert/verify_certificate.php',
     'font' => 'helvetica', 'fontsize' => 8, 'colour' => '#64748B', 'posx' => 30, 'posy' => 195,
     'width' => 180, 'refpoint' => \mod_customcert\element_helper::CUSTOMCERT_REF_POINT_TOPLEFT, 'alignment' => 'L'],

    ['name' => 'QR', 'element' => 'qrcode', 'data' => json_encode(['width' => 28, 'height' => 28]),
     'font' => '', 'fontsize' => 0, 'colour' => '#000000', 'posx' => 240, 'posy' => 165, 'width' => 28,
     'refpoint' => \mod_customcert\element_helper::CUSTOMCERT_REF_POINT_TOPLEFT, 'alignment' => 'L'],
];

$sequence = 1;
foreach ($elementos as $e) {
    $e['pageid'] = $page->id;
    $e['sequence'] = $sequence++;
    $e['timecreated'] = time();
    $e['timemodified'] = time();
    $DB->insert_record('customcert_elements', (object)$e);
    echo "  + elemento: {$e['name']}\n";
}

rebuild_course_cache($course->id, true);
purge_all_caches();

echo "\nCertificado listo: {$CFG->wwwroot}/mod/customcert/view.php?id={$cm->id}\n";
echo "Vista previa (admin): {$CFG->wwwroot}/mod/customcert/edit.php?cmid={$cm->id}\n";
