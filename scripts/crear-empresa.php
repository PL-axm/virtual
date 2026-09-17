<?php
// Crea una empresa cliente: cohorte + matrícula automática en los cursos indicados.
// Quien se agregue a la cohorte queda inscrito en esos cursos como estudiante.
//
// Uso:
//   /opt/alt/php83/usr/bin/php scripts/crear-empresa.php --nombre="Textiles Andinos" \
//       --codigo=TEXTILES --cursos=COM-ASE
//
// Opciones:
//   --nombre   Nombre visible de la empresa (obligatorio)
//   --codigo   Código corto, sin espacios; se usa en la carga masiva (obligatorio)
//   --cursos   Códigos de curso separados por coma (obligatorio). Ej: COM-ASE,SST-BAS

define('CLI_SCRIPT', true);
require(__DIR__ . '/../config.php');
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->dirroot . '/cohort/lib.php');
require_once($CFG->dirroot . '/enrol/cohort/locallib.php');

[$opciones, $sinopciones] = cli_get_params(
    ['nombre' => null, 'codigo' => null, 'cursos' => null, 'help' => false],
    ['h' => 'help']
);

if ($opciones['help'] || !$opciones['nombre'] || !$opciones['codigo'] || !$opciones['cursos']) {
    echo "Uso: php scripts/crear-empresa.php --nombre=\"Empresa S.A.S\" --codigo=EMPRESA --cursos=COM-ASE\n";
    exit($opciones['help'] ? 0 : 1);
}

$nombre = trim($opciones['nombre']);
$codigo = strtoupper(preg_replace('/[^A-Za-z0-9\-_]/', '', $opciones['codigo']));
$cursoscodigos = array_filter(array_map('trim', explode(',', $opciones['cursos'])));

if ($codigo === '') {
    cli_error('El código solo admite letras, números, guiones y guiones bajos.');
}

// Cursos indicados.
$cursos = [];
foreach ($cursoscodigos as $sc) {
    $curso = $DB->get_record('course', ['shortname' => $sc]);
    if (!$curso) {
        cli_error("No existe el curso con código '$sc'.");
    }
    $cursos[] = $curso;
}

// 1. Cohorte de la empresa.
$cohorte = $DB->get_record('cohort', ['idnumber' => $codigo]);
if (!$cohorte) {
    $id = cohort_add_cohort((object)[
        'contextid' => context_system::instance()->id,
        'name' => $nombre,
        'idnumber' => $codigo,
        'description' => 'Colaboradores de ' . $nombre . '.',
        'descriptionformat' => FORMAT_HTML,
        'visible' => 1,
    ]);
    $cohorte = $DB->get_record('cohort', ['id' => $id], '*', MUST_EXIST);
    echo "+ empresa creada: $nombre (código $codigo)\n";
} else {
    echo "= la empresa ya existe: {$cohorte->name} (código $codigo)\n";
}

// 2. Matrícula automática de esa cohorte en cada curso.
$plugin = enrol_get_plugin('cohort');
if (!$plugin) {
    cli_error('El método de matriculación por cohorte no está habilitado. Ejecuta antes scripts/setup-matricula.php');
}
$estudiante = $DB->get_field('role', 'id', ['shortname' => 'student'], MUST_EXIST);

foreach ($cursos as $curso) {
    $existe = $DB->record_exists('enrol', [
        'courseid' => $curso->id, 'enrol' => 'cohort', 'customint1' => $cohorte->id,
    ]);
    if ($existe) {
        echo "  = ya estaba matriculada en: {$curso->fullname}\n";
        continue;
    }
    $plugin->add_instance($curso, [
        'name' => $nombre,
        'customint1' => $cohorte->id,
        'roleid' => $estudiante,
        'customint2' => 0, // Sin grupo.
        'status' => ENROL_INSTANCE_ENABLED,
    ]);
    echo "  + matrícula automática en: {$curso->fullname}\n";
}

// 3. Sincroniza de inmediato (por si la cohorte ya tiene personas).
enrol_cohort_sync(new null_progress_trace());

$miembros = $DB->count_records('cohort_members', ['cohortid' => $cohorte->id]);
echo "\nEmpresa lista. Colaboradores en la cohorte: $miembros\n";
echo "Para cargarlos en bloque usa el archivo content/plantillas/colaboradores.csv\n";
echo "con la columna cohort1 = $codigo, en:\n";
echo "  {$CFG->wwwroot}/admin/tool/uploaduser/index.php\n";
