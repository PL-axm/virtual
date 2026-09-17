<?php
// Configura el esquema de matrícula por empresas:
//  - habilita el método de matriculación por cohorte
//  - crea el rol "Coordinador de empresa" (ve el avance de su equipo, no edita cursos)
// Es idempotente. Uso: /opt/alt/php83/usr/bin/php scripts/setup-matricula.php

define('CLI_SCRIPT', true);
require(__DIR__ . '/../config.php');
require_once($CFG->libdir . '/accesslib.php');
require_once($CFG->libdir . '/adminlib.php');

// 1. Métodos de matriculación: manual (ya activo) + cohorte.
$activos = array_filter(explode(',', $CFG->enrol_plugins_enabled));
if (!in_array('cohort', $activos, true)) {
    $activos[] = 'cohort';
    set_config('enrol_plugins_enabled', implode(',', $activos));
    core_plugin_manager::reset_caches();
    echo "+ método de matriculación por cohorte habilitado\n";
} else {
    echo "= el método por cohorte ya estaba habilitado\n";
}

// 2. Rol de coordinador de empresa.
$shortname = 'coordinadorempresa';
$roleid = $DB->get_field('role', 'id', ['shortname' => $shortname]);
if (!$roleid) {
    $roleid = create_role(
        'Coordinador de empresa',
        $shortname,
        'Responsable de talento humano de la empresa cliente. Puede ver el avance y las '
            . 'calificaciones de su equipo, pero no puede editar los cursos ni calificar.'
    );
    echo "+ rol creado: Coordinador de empresa\n";
} else {
    echo "= el rol ya existe\n";
}

// Se asigna dentro de un curso.
set_role_contextlevels($roleid, [CONTEXT_COURSE]);

$capacidades = [
    'moodle/course:view'                    => CAP_ALLOW,
    'moodle/course:viewparticipants'        => CAP_ALLOW,
    'moodle/course:isincompletionreports'   => CAP_PREVENT,
    'moodle/user:viewdetails'               => CAP_ALLOW,
    'moodle/grade:viewall'                  => CAP_ALLOW,
    'gradereport/grader:view'               => CAP_ALLOW,
    'gradereport/user:view'                 => CAP_ALLOW,
    'gradereport/overview:view'             => CAP_ALLOW,
    'report/progress:view'                  => CAP_ALLOW,
    'report/outline:view'                   => CAP_ALLOW,
    'report/participation:view'             => CAP_ALLOW,
    'moodle/site:viewreports'               => CAP_ALLOW,
    'mod/assign:view'                       => CAP_ALLOW,
    'mod/quiz:view'                         => CAP_ALLOW,
];
$context = context_system::instance();
foreach ($capacidades as $cap => $permiso) {
    if (get_capability_info($cap)) {
        assign_capability($cap, $permiso, $roleid, $context->id, true);
    } else {
        echo "  ! capacidad desconocida, se omite: $cap\n";
    }
}
echo "  = permisos del rol actualizados (" . count($capacidades) . ")\n";

purge_all_caches();

echo "\nListo. Ahora cada empresa se crea con: scripts/crear-empresa.php\n";
