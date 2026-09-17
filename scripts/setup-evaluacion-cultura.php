<?php
// Agrega al curso "Cultura empresarial e ideas de negocio innovadoras" (CULT-EMP)
// la evaluación final, el certificado y el criterio de finalización del curso.
// Es idempotente. Uso: /opt/alt/php83/usr/bin/php scripts/setup-evaluacion-cultura.php

define('CLI_SCRIPT', true);
require(__DIR__ . '/../config.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/course/modlib.php');
require_once($CFG->dirroot . '/mod/quiz/locallib.php');
require_once($CFG->libdir . '/questionlib.php');
require_once($CFG->libdir . '/completionlib.php');
require_once($CFG->dirroot . '/question/format/xml/format.php');

// Nombre de la entidad que certifica (igual que en el otro curso).
$entidad = 'Virtual enelmapa.co';
$contentdir = $CFG->dirroot . '/content/cultura-empresarial';

$curso = $DB->get_record('course', ['shortname' => 'CULT-EMP'], '*', MUST_EXIST);
\core\session\manager::set_user(get_admin());

echo "Curso: {$curso->fullname} (id {$curso->id})\n\n";

// 1. Sección para la evaluación.
course_create_sections_if_missing($curso, 3);
$sec = $DB->get_record('course_sections', ['course' => $curso->id, 'section' => 3], '*', MUST_EXIST);
course_update_section($curso, $sec, (object)[
    'name' => 'Evaluación y certificado',
    'summary' => '<p>Evalúa lo aprendido en los dos componentes formativos y descarga tu certificado.</p>',
    'summaryformat' => FORMAT_HTML,
]);
echo "= sección 3: Evaluación y certificado\n";

/**
 * Crea una actividad si no existe.
 */
function crear_modulo(stdClass $curso, string $modname, string $idnumber, int $section, array $extra): stdClass {
    global $DB;
    if ($cm = $DB->get_record('course_modules', ['course' => $curso->id, 'idnumber' => $idnumber])) {
        echo "  = ya existe: $idnumber\n";
        return $cm;
    }
    $moduleinfo = (object)array_merge([
        'modulename' => $modname,
        'module' => $DB->get_field('modules', 'id', ['name' => $modname], MUST_EXIST),
        'course' => $curso->id,
        'section' => $section,
        'visible' => 1,
        'visibleoncoursepage' => 1,
        'cmidnumber' => $idnumber,
        'groupmode' => 0,
        'groupingid' => 0,
        'availabilityconditionsjson' => '',
        'completion' => COMPLETION_TRACKING_AUTOMATIC,
        'completionview' => 0,
        'completionusegrade' => 0,
        'completionpassgrade' => 0,
        'completiongradeitemnumber' => null,
        'completionexpected' => 0,
        'introformat' => FORMAT_HTML,
        'showdescription' => 1,
    ], $extra);
    $moduleinfo = add_moduleinfo($moduleinfo, $curso);
    echo "  + creado: $idnumber\n";
    return $DB->get_record('course_modules', ['id' => $moduleinfo->coursemodule], '*', MUST_EXIST);
}

// 2. Evaluación final.
echo "\nEvaluación final\n";
$cfg = get_config('quiz');
$cmquiz = crear_modulo($curso, 'quiz', 'CULT-EMP-FINAL', 3, [
    'name' => 'Evaluación final del programa',
    'intro' => '<p>14 preguntas sobre los dos componentes formativos. Necesitas <strong>70 %</strong> para aprobar '
        . 'y habilitar el certificado. Tienes hasta 3 intentos y se toma la nota más alta.</p>',
    'timeopen' => 0, 'timeclose' => 0, 'timelimit' => 0,
    'overduehandling' => 'autosubmit', 'graceperiod' => 0,
    'preferredbehaviour' => 'deferredfeedback', 'canredoquestions' => 0,
    'attempts' => 3, 'attemptonlast' => 0, 'grademethod' => 1,
    'decimalpoints' => 2, 'questiondecimalpoints' => -1,
    'reviewattempt' => (int)$cfg->reviewattempt,
    'reviewcorrectness' => (int)$cfg->reviewcorrectness,
    'reviewmarks' => (int)$cfg->reviewmarks,
    'reviewspecificfeedback' => (int)$cfg->reviewspecificfeedback,
    'reviewgeneralfeedback' => (int)$cfg->reviewgeneralfeedback,
    'reviewrightanswer' => (int)$cfg->reviewrightanswer,
    'reviewoverallfeedback' => (int)$cfg->reviewoverallfeedback,
    'questionsperpage' => 1, 'navmethod' => 'free', 'shuffleanswers' => 1,
    'sumgrades' => 0, 'grade' => 10.0, 'gradepass' => 7.0,
    'quizpassword' => '', 'subnet' => '', 'browsersecurity' => '-',
    'delay1' => 0, 'delay2' => 0, 'showuserpicture' => 0, 'showblocks' => 0,
    'completionattemptsexhausted' => 0, 'completionminattempts' => 0,
    'completionusegrade' => 1, 'completionpassgrade' => 1,
]);

// 3. Preguntas.
$quiz = $DB->get_record('quiz', ['id' => $cmquiz->instance], '*', MUST_EXIST);
if ($DB->record_exists('quiz_slots', ['quizid' => $quiz->id])) {
    echo "  = la evaluación ya tiene preguntas\n";
} else {
    $contexts = new core_question\local\bank\question_edit_contexts(context_course::instance($curso->id));
    $qformat = new qformat_xml();
    $qformat->setCourse($curso);
    $qformat->setFilename($contentdir . '/preguntas-final.xml');
    $qformat->setRealfilename('preguntas-final.xml');
    $qformat->setContexts($contexts->having_one_edit_tab_cap('import'));
    $qformat->setCategory(question_make_default_categories($contexts->all()));
    $qformat->setCatfromfile(true);
    $qformat->setContextfromfile(false);
    $qformat->setStoponerror(true);
    $qformat->setMatchgrades('error');

    ob_start();
    $ok = $qformat->importprocess();
    $salida = ob_get_clean();
    if (!$ok) {
        cli_error("Falló la importación de preguntas:\n" . html_to_text($salida));
    }

    $quiz->cmid = $cmquiz->id;
    foreach ($qformat->questionids as $qid) {
        quiz_add_quiz_question($qid, $quiz, 0);
    }
    \mod_quiz\quiz_settings::create($quiz->id)->get_grade_calculator()->recompute_quiz_sumgrades();
    echo "  + " . count($qformat->questionids) . " preguntas agregadas\n";
}

// 4. Certificado, disponible solo al aprobar.
echo "\nCertificado\n";
$cmcert = $DB->get_record('course_modules', ['course' => $curso->id, 'idnumber' => 'CULT-EMP-CERT']);
if (!$cmcert) {
    $availability = json_encode((object)[
        'op' => '&',
        'c' => [(object)['type' => 'completion', 'cm' => (int)$cmquiz->id, 'e' => COMPLETION_COMPLETE_PASS]],
        'showc' => [true],
    ]);
    $cmcert = crear_modulo($curso, 'customcert', 'CULT-EMP-CERT', 3, [
        'name' => 'Tu certificado del programa',
        'intro' => '<p>Descarga aquí tu certificado en PDF, con código de verificación en línea.</p>',
        'availabilityconditionsjson' => $availability,
        'completionview' => 1,
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
    ]);
}

$cert = $DB->get_record('customcert', ['id' => $cmcert->instance], '*', MUST_EXIST);
$page = $DB->get_record('customcert_pages', ['templateid' => $cert->templateid], '*', MUST_EXIST);

if ($DB->count_records('customcert_elements', ['pageid' => $page->id]) == 0) {
    $DB->update_record('customcert_pages', (object)[
        'id' => $page->id, 'width' => 297, 'height' => 210,
        'leftmargin' => 15, 'rightmargin' => 15, 'timemodified' => time(),
    ]);

    $ancho = 267;
    $centro = \mod_customcert\element_helper::CUSTOMCERT_REF_POINT_TOPCENTER;
    $izq = \mod_customcert\element_helper::CUSTOMCERT_REF_POINT_TOPLEFT;

    $elementos = [
        ['name' => 'Marco', 'element' => 'border', 'data' => '3', 'font' => '', 'fontsize' => 0,
         'colour' => '#0A0F1D', 'posx' => 0, 'posy' => 0, 'width' => 0, 'refpoint' => null, 'alignment' => 'L'],
        ['name' => 'Entidad', 'element' => 'text', 'data' => $entidad, 'font' => 'helvetica', 'fontsize' => 14,
         'colour' => '#004AC6', 'posx' => 148, 'posy' => 28, 'width' => $ancho, 'refpoint' => $centro, 'alignment' => 'C'],
        ['name' => 'Título', 'element' => 'text', 'data' => 'CERTIFICADO DE PARTICIPACIÓN', 'font' => 'helvetica',
         'fontsize' => 26, 'colour' => '#0A0F1D', 'posx' => 148, 'posy' => 48, 'width' => $ancho, 'refpoint' => $centro, 'alignment' => 'C'],
        ['name' => 'Otorgado a', 'element' => 'text', 'data' => 'Se certifica que', 'font' => 'helvetica',
         'fontsize' => 12, 'colour' => '#434655', 'posx' => 148, 'posy' => 72, 'width' => $ancho, 'refpoint' => $centro, 'alignment' => 'C'],
        ['name' => 'Participante', 'element' => 'studentname', 'data' => null, 'font' => 'helveticaB', 'fontsize' => 24,
         'colour' => '#0A0F1D', 'posx' => 148, 'posy' => 84, 'width' => $ancho, 'refpoint' => $centro, 'alignment' => 'C'],
        ['name' => 'Completó', 'element' => 'text', 'data' => 'completó satisfactoriamente el programa',
         'font' => 'helvetica', 'fontsize' => 12, 'colour' => '#434655', 'posx' => 148, 'posy' => 108,
         'width' => $ancho, 'refpoint' => $centro, 'alignment' => 'C'],
        ['name' => 'Curso', 'element' => 'coursename', 'data' => '2', 'font' => 'helveticaB', 'fontsize' => 18,
         'colour' => '#004AC6', 'posx' => 148, 'posy' => 120, 'width' => $ancho, 'refpoint' => $centro, 'alignment' => 'C'],
        ['name' => 'Modalidad', 'element' => 'text', 'data' => 'Modalidad virtual · 2 componentes formativos',
         'font' => 'helvetica', 'fontsize' => 11, 'colour' => '#64748B', 'posx' => 148, 'posy' => 140,
         'width' => $ancho, 'refpoint' => $centro, 'alignment' => 'C'],
        ['name' => 'Fecha', 'element' => 'date',
         'data' => json_encode(['dateitem' => '-1', 'dateformat' => 'strftimedate']),
         'font' => 'helvetica', 'fontsize' => 11, 'colour' => '#434655', 'posx' => 148, 'posy' => 152,
         'width' => $ancho, 'refpoint' => $centro, 'alignment' => 'C'],
        ['name' => 'Código de verificación', 'element' => 'code', 'data' => null, 'font' => 'helvetica',
         'fontsize' => 9, 'colour' => '#64748B', 'posx' => 30, 'posy' => 188, 'width' => 120, 'refpoint' => $izq, 'alignment' => 'L'],
        ['name' => 'Verificación', 'element' => 'text',
         'data' => 'Verifica este certificado en ' . $CFG->wwwroot . '/mod/customcert/verify_certificate.php',
         'font' => 'helvetica', 'fontsize' => 8, 'colour' => '#64748B', 'posx' => 30, 'posy' => 195,
         'width' => 180, 'refpoint' => $izq, 'alignment' => 'L'],
        ['name' => 'QR', 'element' => 'qrcode', 'data' => json_encode(['width' => 28, 'height' => 28]),
         'font' => '', 'fontsize' => 0, 'colour' => '#000000', 'posx' => 240, 'posy' => 165, 'width' => 28,
         'refpoint' => $izq, 'alignment' => 'L'],
    ];

    $sequence = 1;
    foreach ($elementos as $e) {
        $e['pageid'] = $page->id;
        $e['sequence'] = $sequence++;
        $e['timecreated'] = time();
        $e['timemodified'] = time();
        $DB->insert_record('customcert_elements', (object)$e);
    }
    echo "  + plantilla del certificado creada (" . count($elementos) . " elementos)\n";
} else {
    echo "  = la plantilla ya tenía elementos\n";
}

// 5. Finalización del curso: aprobar la evaluación final.
echo "\nFinalización del curso\n";
if (!$DB->record_exists('course_completion_criteria', ['course' => $curso->id])) {
    $DB->insert_record('course_completion_criteria', (object)[
        'course' => $curso->id,
        'criteriatype' => COMPLETION_CRITERIA_TYPE_ACTIVITY,
        'module' => 'quiz',
        'moduleinstance' => $cmquiz->id,
        'courseinstance' => null, 'enrolperiod' => null, 'timeend' => null,
        'gradepass' => null, 'role' => null,
    ]);
    echo "  + criterio: aprobar la evaluación final\n";
} else {
    echo "  = ya tenía criterios de finalización\n";
}

rebuild_course_cache($curso->id, true);
purge_all_caches();

echo "\nListo: {$CFG->wwwroot}/course/view.php?id={$curso->id}\n";
