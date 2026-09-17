<?php
// Crea el curso "Cultura empresarial e ideas de negocio innovadoras" y carga
// los tres paquetes web (información del programa + 2 componentes formativos)
// como recursos de archivo, descomprimiéndolos dentro de Moodle.
//
// Los .zip deben estar en el servidor (por defecto ~/paquetes/CULTURA_EMPRESARIAL_IDEAS_NEGOCIO).
// Es idempotente. Uso:
//   /opt/alt/php83/usr/bin/php scripts/setup-curso-cultura.php
//   /opt/alt/php83/usr/bin/php scripts/setup-curso-cultura.php --dir=/ruta/a/los/zip

define('CLI_SCRIPT', true);
require(__DIR__ . '/../config.php');
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->libdir . '/resourcelib.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/course/modlib.php');

[$opciones] = cli_get_params(['dir' => null]);
$dir = $opciones['dir'] ?: '/home/moralesbilma/paquetes/CULTURA_EMPRESARIAL_IDEAS_NEGOCIO';

\core\session\manager::set_user(get_admin());

// 1. Categoría.
$catidnumber = 'EMPRENDIMIENTO';
$cat = $DB->get_record('course_categories', ['idnumber' => $catidnumber]);
if (!$cat) {
    $cat = core_course_category::create((object)[
        'name' => 'Emprendimiento',
        'idnumber' => $catidnumber,
        'parent' => 0,
    ]);
    echo "+ categoría creada: Emprendimiento\n";
} else {
    echo "= categoría existente: {$cat->name}\n";
}

// 2. Curso.
$shortname = 'CULT-EMP';
$curso = $DB->get_record('course', ['shortname' => $shortname]);
if (!$curso) {
    $curso = create_course((object)[
        'shortname' => $shortname,
        'fullname' => 'Cultura empresarial e ideas de negocio innovadoras',
        'category' => $cat->id,
        'summary' => '<p>Programa de formación complementaria sobre cultura empresarial, '
            . 'entorno emprendedor y formulación de ideas de negocio innovadoras.</p>',
        'summaryformat' => FORMAT_HTML,
        'format' => 'topics',
        'numsections' => 2,
        'visible' => 0, // Oculto hasta que se revise.
        'enablecompletion' => 1,
        'showreports' => 1,
        'startdate' => usergetmidnight(time()),
    ]);
    echo "+ curso creado: {$curso->fullname}\n";
} else {
    echo "= curso existente: {$curso->fullname}\n";
}

// 3. Secciones.
$secciones = [
    1 => ['Componente formativo 1', 'Entorno empresarial y cultura emprendedora.'],
    2 => ['Componente formativo 2', 'Formulación de ideas de negocio e innovación empresarial.'],
];
foreach ($secciones as $num => [$nombre, $resumen]) {
    $sec = $DB->get_record('course_sections', ['course' => $curso->id, 'section' => $num], '*', MUST_EXIST);
    course_update_section($curso, $sec, (object)[
        'name' => $nombre,
        'summary' => '<p>' . $resumen . '</p>',
        'summaryformat' => FORMAT_HTML,
    ]);
    echo "= sección $num: $nombre\n";
}

// 4. Paquetes web como recursos de archivo.
$paquetes = [
    [
        'idnumber' => 'CULT-EMP-INFO',
        'seccion'  => 0,
        'nombre'   => 'Información del programa',
        'intro'    => '<p>Presentación del programa: objetivos, duración, modalidad y ficha descargable en PDF.</p>',
        'zip'      => '01230000_CFINFO_ejecutable.zip',
    ],
    [
        'idnumber' => 'CULT-EMP-CF01',
        'seccion'  => 1,
        'nombre'   => 'Entorno empresarial y cultura emprendedora',
        'intro'    => '<p>Componente formativo 1. Se abre en una ventana nueva.</p>',
        'zip'      => '1230000_CF01_CULTURA_EMPRESARIAL_IDEAS_NEGOCIO.zip',
    ],
    [
        'idnumber' => 'CULT-EMP-CF02',
        'seccion'  => 2,
        'nombre'   => 'Formulación de ideas de negocio e innovación empresarial',
        'intro'    => '<p>Componente formativo 2. Se abre en una ventana nueva.</p>',
        'zip'      => '1230000_CF02_CULTURA_EMPRESARIAL_IDEAS_NEGOCIO.zip',
    ],
];

$fs = get_file_storage();
$packer = get_file_packer('application/zip');

foreach ($paquetes as $p) {
    $zip = $dir . '/' . $p['zip'];
    if (!is_readable($zip)) {
        cli_error("No se encontró el paquete: $zip");
    }

    $cm = $DB->get_record('course_modules', ['course' => $curso->id, 'idnumber' => $p['idnumber']]);
    if (!$cm) {
        $moduleinfo = (object)[
            'modulename' => 'resource',
            'module' => $DB->get_field('modules', 'id', ['name' => 'resource'], MUST_EXIST),
            'course' => $curso->id,
            'section' => $p['seccion'],
            'visible' => 1,
            'visibleoncoursepage' => 1,
            'cmidnumber' => $p['idnumber'],
            'groupmode' => 0,
            'groupingid' => 0,
            'availabilityconditionsjson' => '',
            'completion' => COMPLETION_TRACKING_AUTOMATIC,
            'completionview' => 1,
            'completionusegrade' => 0,
            'completionpassgrade' => 0,
            'completiongradeitemnumber' => null,
            'completionexpected' => 0,
            'name' => $p['nombre'],
            'intro' => $p['intro'],
            'introformat' => FORMAT_HTML,
            'showdescription' => 1,
            'display' => RESOURCELIB_DISPLAY_NEW, // Ventana nueva: el paquete usa toda la pantalla.
            'printintro' => 1,
            'showsize' => 0,
            'showtype' => 0,
            'showdate' => 0,
            'popupwidth' => 620,
            'popupheight' => 450,
            'filterfiles' => 0,
            'revision' => 1,
            'files' => 0, // Los archivos se cargan más abajo, descomprimiendo el paquete.
        ];
        $moduleinfo = add_moduleinfo($moduleinfo, $curso);
        $cm = $DB->get_record('course_modules', ['id' => $moduleinfo->coursemodule], '*', MUST_EXIST);
        echo "+ recurso creado: {$p['nombre']}\n";
    } else {
        echo "= recurso existente: {$p['nombre']}\n";
    }

    $context = context_module::instance($cm->id);
    $existentes = $fs->get_area_files($context->id, 'mod_resource', 'content', 0, 'filename', false);
    if ($existentes) {
        echo "  = ya tenía " . count($existentes) . " archivos, no se vuelve a descomprimir\n";
        continue;
    }

    echo "  · descomprimiendo " . $p['zip'] . " ...\n";
    $resultado = $packer->extract_to_storage($zip, $context->id, 'mod_resource', 'content', 0, '/');
    if ($resultado === false) {
        cli_error("Falló la descompresión de {$p['zip']}");
    }

    // index.html debe quedar como archivo principal.
    $index = $fs->get_file($context->id, 'mod_resource', 'content', 0, '/', 'index.html');
    if (!$index) {
        cli_error("El paquete {$p['zip']} no tiene index.html en la raíz.");
    }
    file_set_sortorder($context->id, 'mod_resource', 'content', 0, '/', 'index.html', 1);

    $total = count($fs->get_area_files($context->id, 'mod_resource', 'content', 0, 'filename', false));
    echo "  + $total archivos cargados\n";
}

rebuild_course_cache($curso->id, true);
purge_all_caches();

echo "\nCurso listo (oculto): {$CFG->wwwroot}/course/view.php?id={$curso->id}\n";
