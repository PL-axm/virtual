<?php
// Prueba de extremo a extremo del curso piloto con el usuario de pruebas.
// Simula a un estudiante: marca las lecciones vistas, responde los cuestionarios
// (correctamente), verifica la finalización del curso y la emisión del certificado.
//
// SOLO PARA PRUEBAS: genera intentos reales en el curso.
// Uso: /opt/alt/php83/usr/bin/php scripts/prueba-e2e-demo.php --confirmar

define('CLI_SCRIPT', true);
require(__DIR__ . '/../config.php');
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->libdir . '/completionlib.php');
require_once($CFG->dirroot . '/mod/quiz/locallib.php');

[$opciones] = cli_get_params(['confirmar' => false]);
if (!$opciones['confirmar']) {
    cli_error("Ejecuta con --confirmar. Este script genera intentos reales en el curso piloto.");
}

$usuario = $DB->get_record('user', ['username' => 'prueba.demo'], '*', MUST_EXIST);
$curso = $DB->get_record('course', ['shortname' => 'COM-ASE'], '*', MUST_EXIST);
\core\session\manager::set_user($usuario);

$completion = new completion_info($curso);
$modinfo = get_fast_modinfo($curso, $usuario->id);

echo "Estudiante: {$usuario->firstname} {$usuario->lastname}\n";
echo "Curso: {$curso->fullname}\n\n";

// 1. Marcar como vistas las páginas de contenido.
echo "1. Lecciones\n";
foreach ($modinfo->get_cms() as $cm) {
    if ($cm->modname !== 'page') {
        continue;
    }
    $estado = $completion->get_data($cm, false, $usuario->id);
    if ($estado->completionstate == COMPLETION_COMPLETE) {
        echo "   = ya vista: {$cm->name}\n";
        continue;
    }
    // Equivale a que el estudiante abra la página: es lo que dispara la finalización por vista.
    $completion->set_module_viewed($cm, $usuario->id);
    $estado = $completion->get_data($cm, false, $usuario->id);
    echo "   + vista: {$cm->name} (estado {$estado->completionstate})\n";
}

// 2. Responder los cuestionarios correctamente.
echo "\n2. Cuestionarios\n";
foreach (['COMASE-Q1', 'COMASE-Q2', 'COMASE-FINAL'] as $idnumber) {
    $cm = $DB->get_record('course_modules', ['course' => $curso->id, 'idnumber' => $idnumber], '*', MUST_EXIST);
    $quizobj = \mod_quiz\quiz_settings::create($cm->instance, $usuario->id);
    $quiz = $quizobj->get_quiz();

    $previos = quiz_get_user_attempts($quiz->id, $usuario->id, 'finished', true);
    if ($previos) {
        $ultimo = end($previos);
        echo "   = {$quiz->name}: ya tenía intento (nota " . quiz_rescale_grade($ultimo->sumgrades, $quiz, false) . ")\n";
        continue;
    }

    $quizobj->preload_questions();
    $quizobj->load_questions();

    $quba = question_engine::make_questions_usage_by_activity('mod_quiz', $quizobj->get_context());
    $quba->set_preferred_behaviour($quiz->preferredbehaviour);

    $ahora = time();
    $intento = quiz_create_attempt($quizobj, 1, false, $ahora, false, $usuario->id);
    quiz_start_new_attempt($quizobj, $quba, $intento, 1, $ahora);
    quiz_attempt_save_started($quizobj, $quba, $intento);

    $attemptobj = \mod_quiz\quiz_attempt::create($intento->id);

    // Construye las respuestas correctas de cada pregunta.
    $respuestas = [];
    foreach ($attemptobj->get_slots() as $slot) {
        $qa = $attemptobj->get_question_attempt($slot);
        $correcta = $qa->get_question()->get_correct_response();
        foreach ($correcta as $campo => $valor) {
            $respuestas[$qa->get_field_prefix() . $campo] = $valor;
        }
        $respuestas[$qa->get_field_prefix() . ':sequencecheck'] = $qa->get_sequence_check_count();
    }
    $attemptobj->process_submitted_actions(time(), false, $respuestas);
    $attemptobj->process_finish(time(), false);

    $intento = $DB->get_record('quiz_attempts', ['id' => $intento->id], '*', MUST_EXIST);
    $nota = quiz_rescale_grade($intento->sumgrades, $quiz, false);
    echo "   + {$quiz->name}: nota $nota de {$quiz->grade}\n";
}

// 3. Recalcular la finalización del curso (lo que hace el cron).
echo "\n3. Finalización del curso\n";
$tarea = new \core\task\completion_regular_task();
$tarea->execute();

$registro = $DB->get_record('course_completions', ['course' => $curso->id, 'userid' => $usuario->id]);
$completado = $registro && $registro->timecompleted;
echo "   curso completado: " . ($completado ? 'SÍ (' . userdate($registro->timecompleted) . ')' : 'todavía no') . "\n";

// 4. Certificado.
echo "\n4. Certificado\n";
$cmcert = $DB->get_record('course_modules', ['course' => $curso->id, 'idnumber' => 'COMASE-CERT'], '*', MUST_EXIST);
$cert = $DB->get_record('customcert', ['id' => $cmcert->instance], '*', MUST_EXIST);

$modinfo = get_fast_modinfo($curso, $usuario->id);
$info = new \core_availability\info_module($modinfo->get_cm($cmcert->id));
$razon = '';
$disponible = $info->is_available($razon, false, $usuario->id);
echo "   visible para el estudiante: " . ($disponible ? 'SÍ' : "NO ($razon)") . "\n";

$emitido = $DB->get_record('customcert_issues', ['customcertid' => $cert->id, 'userid' => $usuario->id]);
if (!$emitido && $disponible) {
    \mod_customcert\certificate::issue_certificate($cert->id, $usuario->id);
    $emitido = $DB->get_record('customcert_issues', ['customcertid' => $cert->id, 'userid' => $usuario->id]);
}
if ($emitido) {
    echo "   certificado emitido, código de verificación: {$emitido->code}\n";
    echo "   verificable en: {$CFG->wwwroot}/mod/customcert/verify_certificate.php?contextid="
        . context_module::instance($cmcert->id)->id . "&code={$emitido->code}\n";
    $plantilla = new \mod_customcert\template($DB->get_record('customcert_templates', ['id' => $cert->templateid], '*', MUST_EXIST));
    $pdf = $plantilla->generate_pdf(false, $usuario->id, true);
    echo "   PDF generado: " . strlen($pdf) . " bytes\n";
} else {
    echo "   todavía no hay certificado emitido\n";
}

// 5. Notas del curso.
echo "\n5. Calificaciones\n";
require_once($CFG->libdir . '/gradelib.php');
$notas = grade_get_course_grade($usuario->id, $curso->id);
echo "   nota final del curso: " . ($notas->str_grade ?? 'sin nota') . "\n";
