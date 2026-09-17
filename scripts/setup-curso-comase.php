<?php
// Arma el curso piloto "Comunicación Asertiva" (COM-ASE): secciones, lecciones,
// foro, tarea, cuestionarios y criterio de finalización del curso.
// Es idempotente: cada actividad se identifica con un idnumber y no se duplica.
// Uso: /opt/alt/php83/usr/bin/php scripts/setup-curso-comase.php

define('CLI_SCRIPT', true);
require(__DIR__ . '/../config.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/course/modlib.php');
require_once($CFG->dirroot . '/mod/quiz/locallib.php');
require_once($CFG->libdir . '/questionlib.php');
require_once($CFG->libdir . '/completionlib.php');
require_once($CFG->dirroot . '/question/format/xml/format.php');

$contentdir = $CFG->dirroot . '/content/com-ase';
$course = $DB->get_record('course', ['shortname' => 'COM-ASE'], '*', MUST_EXIST);
$coursecontext = context_course::instance($course->id);
$admin = get_admin();
\core\session\manager::set_user($admin);

/**
 * Devuelve el cm existente con ese idnumber, o null.
 */
function buscar_cm(int $courseid, string $idnumber): ?stdClass {
    global $DB;
    $cm = $DB->get_record('course_modules', ['course' => $courseid, 'idnumber' => $idnumber]);
    return $cm ?: null;
}

/**
 * Crea una actividad si no existe todavia.
 */
function crear_modulo(stdClass $course, string $modname, string $idnumber, int $section, array $extra): stdClass {
    global $DB;

    if ($cm = buscar_cm($course->id, $idnumber)) {
        echo "  = ya existe: $idnumber\n";
        return $cm;
    }

    $moduleinfo = (object)array_merge([
        'modulename'               => $modname,
        'module'                   => $DB->get_field('modules', 'id', ['name' => $modname], MUST_EXIST),
        'course'                   => $course->id,
        'section'                  => $section,
        'visible'                  => 1,
        'visibleoncoursepage'      => 1,
        'cmidnumber'               => $idnumber,
        'groupmode'                => 0,
        'groupingid'               => 0,
        'availabilityconditionsjson' => '',
        'completion'               => COMPLETION_TRACKING_AUTOMATIC,
        'completionview'           => 1,
        'completionusegrade'       => 0,
        'completionpassgrade'      => 0,
        'completiongradeitemnumber' => null,
        'completionexpected'       => 0,
        'introformat'              => FORMAT_HTML,
        'intro'                    => '',
        'showdescription'          => 0,
    ], $extra);

    $moduleinfo = add_moduleinfo($moduleinfo, $course);
    echo "  + creado: $idnumber\n";
    return $DB->get_record('course_modules', ['id' => $moduleinfo->coursemodule], '*', MUST_EXIST);
}

/**
 * Crea una pagina con el contenido de un archivo HTML del repositorio.
 */
function crear_pagina(stdClass $course, string $idnumber, int $section, string $nombre, string $archivo): stdClass {
    global $contentdir;
    $html = file_get_contents($contentdir . '/' . $archivo);
    if ($html === false) {
        cli_error("No se pudo leer $archivo");
    }
    return crear_modulo($course, 'page', $idnumber, $section, [
        'name'                => $nombre,
        'page'                => ['text' => $html, 'format' => FORMAT_HTML, 'itemid' => null],
        'content'             => $html,
        'contentformat'       => FORMAT_HTML,
        'display'             => 5, // RESOURCELIB_DISPLAY_OPEN.
        'printheading'        => 1,
        'printintro'          => 0,
        'printlastmodified'   => 1,
    ]);
}

/**
 * Importa preguntas desde un XML a una categoria propia del curso.
 */
function importar_preguntas(stdClass $course, string $archivo): array {
    global $CFG, $DB, $contentdir;

    $contexts = new core_question\local\bank\question_edit_contexts(context_course::instance($course->id));
    $qformat = new qformat_xml();
    $qformat->setCourse($course);
    $qformat->setFilename($contentdir . '/' . $archivo);
    $qformat->setRealfilename($archivo);
    $qformat->setContexts($contexts->having_one_edit_tab_cap('import'));
    $qformat->setCategory(question_get_default_category($contexts->lowest()->id));
    $qformat->setCatfromfile(true);
    $qformat->setContextfromfile(false);
    $qformat->setStoponerror(true);
    $qformat->setMatchgrades('error');

    ob_start();
    $ok = $qformat->importprocess();
    $salida = ob_get_clean();
    if (!$ok) {
        cli_error("Fallo la importacion de $archivo:\n" . html_to_text($salida));
    }

    $ids = [];
    foreach ($qformat->questionids as $id) {
        $ids[] = $id;
    }
    echo "  + preguntas importadas desde $archivo: " . count($ids) . "\n";
    return $ids;
}

/**
 * Agrega las preguntas indicadas a un cuestionario, una por pagina.
 */
function llenar_cuestionario(stdClass $cm, array $questionids): void {
    global $DB;
    $quiz = $DB->get_record('quiz', ['id' => $cm->instance], '*', MUST_EXIST);
    $quiz->cmid = $cm->id;
    if ($DB->record_exists('quiz_slots', ['quizid' => $quiz->id])) {
        echo "  = el cuestionario ya tiene preguntas\n";
        return;
    }
    foreach ($questionids as $qid) {
        quiz_add_quiz_question($qid, $quiz, 0);
    }
    \mod_quiz\quiz_settings::create($quiz->id)->get_grade_calculator()->recompute_quiz_sumgrades();
    echo "  + preguntas agregadas al cuestionario: " . count($questionids) . "\n";
}

/**
 * Valores por defecto de un cuestionario, tomados de la configuracion del sitio.
 */
function datos_cuestionario(string $nombre, string $intro, float $nota, int $intentos, ?float $notaminima = null): array {
    $cfg = get_config('quiz');
    $datos = [
        'name'               => $nombre,
        'intro'              => $intro,
        'timeopen'           => 0,
        'timeclose'          => 0,
        'timelimit'          => 0,
        'overduehandling'    => 'autosubmit',
        'graceperiod'        => 0,
        'preferredbehaviour' => 'deferredfeedback',
        'canredoquestions'   => 0,
        'attempts'           => $intentos,
        'attemptonlast'      => 0,
        'grademethod'        => 1, // Nota mas alta.
        'decimalpoints'      => 2,
        'questiondecimalpoints' => -1,
        'reviewattempt'      => (int)$cfg->reviewattempt,
        'reviewcorrectness'  => (int)$cfg->reviewcorrectness,
        'reviewmarks'        => (int)$cfg->reviewmarks,
        'reviewspecificfeedback' => (int)$cfg->reviewspecificfeedback,
        'reviewgeneralfeedback'  => (int)$cfg->reviewgeneralfeedback,
        'reviewrightanswer'  => (int)$cfg->reviewrightanswer,
        'reviewoverallfeedback'  => (int)$cfg->reviewoverallfeedback,
        'questionsperpage'   => 1,
        'navmethod'          => 'free',
        'shuffleanswers'     => 1,
        'sumgrades'          => 0,
        'grade'              => $nota,
        'quizpassword'       => '',
        'subnet'             => '',
        'browsersecurity'    => '-',
        'delay1'             => 0,
        'delay2'             => 0,
        'showuserpicture'    => 0,
        'showblocks'         => 0,
        'completionattemptsexhausted' => 0,
        'completionminattempts' => 0,
        'completionusegrade' => 1,
        'completionview'     => 0,
    ];
    if ($notaminima !== null) {
        $datos['gradepass'] = $notaminima;
        $datos['completionpassgrade'] = 1;
    }
    return $datos;
}

echo "Curso: {$course->fullname} (id {$course->id})\n\n";

// 1. Nombres y resumen de las secciones.
$secciones = [
    1 => ['Semana 1 · Fundamentos', 'Reconoce los tres estilos de comunicación y practica la escucha activa.'],
    2 => ['Semana 2 · Herramientas prácticas', 'Aprende a expresar lo que te molesta y a poner límites sin romper la relación.'],
    3 => ['Semana 3 · Conversaciones difíciles', 'Retroalimentación, manejo del conflicto y tu plan de acción personal.'],
];
foreach ($secciones as $num => [$nombre, $resumen]) {
    $sec = $DB->get_record('course_sections', ['course' => $course->id, 'section' => $num], '*', MUST_EXIST);
    course_update_section($course, $sec, (object)[
        'name' => $nombre,
        'summary' => '<p>' . $resumen . '</p>',
        'summaryformat' => FORMAT_HTML,
    ]);
    echo "= seccion $num: $nombre\n";
}

// 2. Presentacion.
echo "\nSeccion 0 - Presentación\n";
crear_pagina($course, 'COMASE-BIENVENIDA', 0, 'Empieza aquí: cómo funciona el curso', '00-bienvenida.html');

// 3. Modulo 1.
echo "\nSemana 1\n";
crear_pagina($course, 'COMASE-L11', 1, 'Lección 1.1 · Los tres estilos de comunicación', '11-estilos.html');
crear_pagina($course, 'COMASE-L12', 1, 'Lección 1.2 · Escucha activa', '12-escucha.html');
$cmq1 = crear_modulo($course, 'quiz', 'COMASE-Q1', 1, datos_cuestionario(
    'Cuestionario de práctica · Semana 1',
    '<p>Cinco preguntas para repasar los estilos de comunicación y la escucha activa. Puedes repetirlo las veces que quieras.</p>',
    5.0, 0));

// 4. Modulo 2.
echo "\nSemana 2\n";
crear_pagina($course, 'COMASE-L21', 2, 'Lección 2.1 · Cómo decir lo que te molesta', '21-mensaje-yo.html');
crear_pagina($course, 'COMASE-L22', 2, 'Lección 2.2 · Decir que no y poner límites', '22-limites.html');
crear_modulo($course, 'forum', 'COMASE-FORO', 2, [
    'name' => 'Foro de casos · Comparte tu situación',
    'intro' => '<p>Escribe una situación real de tu trabajo en la que te costó plantear algo, sin nombres propios. '
        . 'Redacta cómo la plantearías hoy usando los cuatro pasos (hecho, efecto, propuesta, acuerdo) '
        . 'y comenta el caso de al menos un compañero.</p>',
    'type' => 'general',
    'forcesubscribe' => 0,
    'assessed' => 0,
    'scale' => 0,
    'maxbytes' => 0,
    'maxattachments' => 1,
    'blockperiod' => 0,
    'blockafter' => 0,
    'warnafter' => 0,
    'completionview' => 0,
    'completionposts' => 1,
    'completiondiscussions' => 1,
    'completionreplies' => 1,
]);
$cmq2 = crear_modulo($course, 'quiz', 'COMASE-Q2', 2, datos_cuestionario(
    'Cuestionario de práctica · Semana 2',
    '<p>Cinco preguntas sobre mensajes asertivos y límites. Puedes repetirlo las veces que quieras.</p>',
    5.0, 0));

// 5. Modulo 3.
echo "\nSemana 3\n";
crear_pagina($course, 'COMASE-L31', 3, 'Lección 3.1 · Retroalimentación que se puede usar', '31-feedback.html');
crear_pagina($course, 'COMASE-L32', 3, 'Lección 3.2 · Conversaciones difíciles y conflicto', '32-conflictos.html');
crear_modulo($course, 'assign', 'COMASE-TAREA', 3, [
    'name' => 'Plan de acción · Prepara tu conversación pendiente',
    'intro' => '<p>Elige la conversación que has venido aplazando y prepárala por escrito (máximo una página):</p>'
        . '<ol><li>El hecho concreto, con fecha.</li><li>El resultado que buscas.</li>'
        . '<li>La parte que te corresponde a ti.</li><li>Tu frase de apertura.</li>'
        . '<li>El acuerdo que propondrás al cerrar.</li></ol>'
        . '<p>Puedes escribirlo en el cuadro de texto o adjuntar un archivo.</p>',
    'alwaysshowdescription' => 1,
    'submissiondrafts' => 0,
    'requiresubmissionstatement' => 0,
    'sendnotifications' => 0,
    'sendlatenotifications' => 0,
    'sendstudentnotifications' => 1,
    'duedate' => 0,
    'cutoffdate' => 0,
    'gradingduedate' => 0,
    'allowsubmissionsfromdate' => 0,
    'grade' => 100,
    'teamsubmission' => 0,
    'requireallteammemberssubmit' => 0,
    'teamsubmissiongroupingid' => 0,
    'blindmarking' => 0,
    'hidegrader' => 0,
    'markingworkflow' => 0,
    'markingallocation' => 0,
    'attemptreopenmethod' => 'none',
    'maxattempts' => -1,
    'assignsubmission_onlinetext_enabled' => 1,
    'assignsubmission_onlinetext_wordlimitenabled' => 0,
    'assignsubmission_onlinetext_wordlimit' => 0,
    'assignsubmission_file_enabled' => 1,
    'assignsubmission_file_maxfiles' => 1,
    'assignsubmission_file_maxsizebytes' => 0,
    'assignsubmission_file_filetypes' => '',
    'assignsubmission_comments_enabled' => 0,
    'assignfeedback_comments_enabled' => 1,
    'assignfeedback_file_enabled' => 0,
    'assignfeedback_comments_commentinline' => 0,
    'completionview' => 0,
    'completionusegrade' => 0,
    'completionsubmit' => 1,
]);
$cmfinal = crear_modulo($course, 'quiz', 'COMASE-FINAL', 3, datos_cuestionario(
    'Evaluación final del curso',
    '<p>Diez preguntas sobre todo el curso. Necesitas <strong>70 %</strong> para aprobar y recibir el certificado. '
        . 'Tienes hasta 3 intentos y se toma la nota más alta.</p>',
    10.0, 3, 7.0));

// 6. Preguntas.
echo "\nBanco de preguntas\n";
llenar_cuestionario($cmq1, importar_preguntas($course, 'preguntas-m1.xml'));
llenar_cuestionario($cmq2, importar_preguntas($course, 'preguntas-m2.xml'));
llenar_cuestionario($cmfinal, importar_preguntas($course, 'preguntas-final.xml'));

// 7. Finalizacion del curso: aprobar la evaluacion final.
echo "\nFinalización del curso\n";
if (!$DB->record_exists('course_completion_criteria', ['course' => $course->id])) {
    $DB->insert_record('course_completion_criteria', (object)[
        'course' => $course->id,
        'criteriatype' => COMPLETION_CRITERIA_TYPE_ACTIVITY,
        'module' => 'quiz',
        'moduleinstance' => $cmfinal->id,
        'courseinstance' => null,
        'enrolperiod' => null,
        'timeend' => null,
        'gradepass' => null,
        'role' => null,
    ]);
    echo "  + criterio: aprobar la evaluación final\n";
} else {
    echo "  = el curso ya tiene criterios de finalización\n";
}

rebuild_course_cache($course->id, true);
purge_all_caches();

echo "\nCurso piloto listo: {$CFG->wwwroot}/course/view.php?id={$course->id}\n";
