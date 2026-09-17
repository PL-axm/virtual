<?php
// Crea (o actualiza) las categorias y los cursos del catalogo de la landing.
// Es idempotente: se puede ejecutar varias veces sin duplicar nada.
// Uso: /opt/alt/php83/usr/bin/php scripts/setup-catalogo.php

define('CLI_SCRIPT', true);
require(__DIR__ . '/../config.php');
require_once($CFG->dirroot . '/course/lib.php');

$imgdir = $CFG->dirroot . '/local/landing/assets/img';

// Categorias: idnumber => nombre.
$categorias = [
    'FINANZAS'     => 'Finanzas',
    'HABILIDADES'  => 'Habilidades Blandas',
    'LIDERAZGO'    => 'Liderazgo',
    'SERVICIO'     => 'Atención al Cliente',
    'HERRAMIENTAS' => 'Herramientas Digitales',
    'NORMATIVA'    => 'Normatividad Legal',
];

// Cursos del catalogo mostrado en la landing.
$cursos = [
    [
        'shortname' => 'COM-ASE',
        'fullname'  => 'Comunicación Asertiva',
        'category'  => 'HABILIDADES',
        'semanas'   => 3,
        'visible'   => 1, // Curso piloto.
        'imagen'    => 'comunicacion.jpg',
        'summary'   => 'Desarrolla habilidades para comunicarte con claridad, empatía y efectividad en el trabajo y en las relaciones interpersonales.',
    ],
    [
        'shortname' => 'FIN-PER',
        'fullname'  => 'Finanzas Personales',
        'category'  => 'FINANZAS',
        'semanas'   => 4,
        'visible'   => 0,
        'imagen'    => 'finanzas.jpg',
        'summary'   => 'Aprende a manejar tu dinero, crear presupuestos, ahorrar e invertir de forma inteligente para un bienestar integral.',
    ],
    [
        'shortname' => 'LID-EQU',
        'fullname'  => 'Liderazgo para Equipos',
        'category'  => 'LIDERAZGO',
        'semanas'   => 4,
        'visible'   => 0,
        'imagen'    => 'liderazgo.jpg',
        'summary'   => 'Herramientas para liderar equipos, tomar decisiones bajo presión y motivar a tu grupo de trabajo hacia objetivos claros.',
    ],
    [
        'shortname' => 'SER-CLI',
        'fullname'  => 'Servicio al Cliente',
        'category'  => 'SERVICIO',
        'semanas'   => 2,
        'visible'   => 0,
        'imagen'    => 'servicio.jpg',
        'summary'   => 'Técnicas probadas para ofrecer una atención excepcional, resolver fricciones comerciales y fidelizar a tus clientes.',
    ],
    [
        'shortname' => 'EXC-TRA',
        'fullname'  => 'Excel para el Trabajo',
        'category'  => 'HERRAMIENTAS',
        'semanas'   => 3,
        'visible'   => 0,
        'imagen'    => 'excel.jpg',
        'summary'   => 'Domina las herramientas esenciales de Excel, fórmulas clave y tablas dinámicas para ser más productivo en tu día a día.',
    ],
    [
        'shortname' => 'SST-BAS',
        'fullname'  => 'Seguridad y Salud en el Trabajo',
        'category'  => 'NORMATIVA',
        'semanas'   => 3,
        'visible'   => 0,
        'imagen'    => 'sst.jpg',
        'summary'   => 'Conoce la normativa colombiana y las mejores prácticas operativas para mantener un entorno laboral seguro.',
    ],
];

// 1. Categorias.
$catids = [];
$orden = 0;
foreach ($categorias as $idnumber => $nombre) {
    $orden++;
    $existente = $DB->get_record('course_categories', ['idnumber' => $idnumber]);
    if ($existente) {
        $catids[$idnumber] = $existente->id;
        echo "= categoria ya existe: $nombre\n";
        continue;
    }
    $cat = core_course_category::create((object)[
        'name' => $nombre,
        'idnumber' => $idnumber,
        'parent' => 0,
        'sortorder' => $orden,
    ]);
    $catids[$idnumber] = $cat->id;
    echo "+ categoria creada: $nombre\n";
}

// 2. Cursos.
foreach ($cursos as $c) {
    $data = (object)[
        'shortname'        => $c['shortname'],
        'fullname'         => $c['fullname'],
        'category'         => $catids[$c['category']],
        'summary'          => $c['summary'],
        'summaryformat'    => FORMAT_HTML,
        'format'           => 'topics',
        'numsections'      => $c['semanas'],
        'visible'          => $c['visible'],
        'enablecompletion' => 1,
        'showreports'      => 1,
        'lang'             => '',
        'startdate'        => usergetmidnight(time()),
    ];

    $existente = $DB->get_record('course', ['shortname' => $c['shortname']]);
    if ($existente) {
        $data->id = $existente->id;
        unset($data->numsections, $data->startdate);
        update_course($data);
        $curso = $DB->get_record('course', ['id' => $existente->id]);
        echo "= curso actualizado: {$c['fullname']}\n";
    } else {
        $curso = create_course($data);
        echo "+ curso creado: {$c['fullname']}\n";
    }

    // Imagen del curso (la misma de la landing).
    $origen = $imgdir . '/' . $c['imagen'];
    $context = context_course::instance($curso->id);
    $fs = get_file_storage();
    if (file_exists($origen) && !$fs->get_area_files($context->id, 'course', 'overviewfiles', 0, 'filename', false)) {
        $fs->create_file_from_pathname([
            'contextid' => $context->id,
            'component' => 'course',
            'filearea'  => 'overviewfiles',
            'itemid'    => 0,
            'filepath'  => '/',
            'filename'  => $c['imagen'],
        ], $origen);
        echo "  + imagen asignada\n";
    }
}

rebuild_course_cache(0, true);
purge_all_caches();

echo "\nCatalogo listo: " . count($categorias) . " categorias, " . count($cursos) . " cursos.\n";
