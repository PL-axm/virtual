<?php
// Aplica la identidad de la landing (colores, tipografías y logo) al tema Boost,
// para que la plataforma por dentro se vea como virtual.enelmapa.co.
// Es idempotente. Uso: /opt/alt/php83/usr/bin/php scripts/setup-tema.php

define('CLI_SCRIPT', true);
require(__DIR__ . '/../config.php');
require_once($CFG->libdir . '/adminlib.php');

$assets = $CFG->dirroot . '/local/landing/assets';

// 1. Color de marca.
set_config('brandcolor', '#004AC6', 'theme_boost');
echo "= color de marca: #004AC6\n";

// 2. Variables SCSS iniciales (paleta y tipografías del diseño).
$scsspre = <<<'SCSS'
// Paleta "Corporate EdTech Modern" — virtual.enelmapa.co
$primary:       #004AC6;
$secondary:     #16213E;
$success:       #10B981;
$info:          #2563EB;
$warning:       #F59E0B;
$danger:        #E11D48;
$light:         #F3F3FE;
$dark:          #0A0F1D;

$body-bg:       #FAF8FF;
$body-color:    #191B23;
$link-color:    #004AC6;

$font-family-sans-serif: "Inter", system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
$headings-font-family:   "Plus Jakarta Sans", "Inter", system-ui, sans-serif;
$headings-font-weight:   700;

$border-radius:     .5rem;
$border-radius-lg:  1rem;
$border-radius-sm:  .25rem;
$card-border-radius: 1rem;
$card-border-color: #E2E8F0;
SCSS;
set_config('scsspre', $scsspre, 'theme_boost');
echo "= variables SCSS aplicadas\n";

// 3. Ajustes finos sobre el tema ya compilado.
$scss = <<<'SCSS'
// --- Identidad virtual.enelmapa.co ---
$vr-navy: #0A0F1D;
$vr-navy-soft: #16213E;
$vr-blue: #004AC6;
$vr-blue-light: #2563EB;
$vr-blue-pale: #DBE1FF;
$vr-crimson: #E11D48;

h1, h2, h3, h4, h5, .h1, .h2, .h3, .h4, .h5 {
    font-family: "Plus Jakarta Sans", "Inter", system-ui, sans-serif;
    letter-spacing: -.01em;
}

// Barra superior oscura, como el encabezado de la landing.
.navbar.fixed-top {
    background-color: $vr-navy !important;
    border-bottom: 0;
    box-shadow: 0 1px 8px rgba(0, 0, 0, .12);

    .navbar-brand,
    .navbar-brand:hover,
    .nav-link,
    .dropdown-toggle,
    .btn-link,
    .editmode-switch-form,
    .editmode-switch-form label,
    .custom-control-label {
        color: #fff !important;
    }

    .nav-link:hover,
    .nav-link:focus {
        color: $vr-blue-pale !important;
    }

    .nav-link.active,
    .nav-item.active > .nav-link {
        color: #fff !important;
        border-bottom-color: $vr-crimson !important;
    }

    .icon {
        color: inherit;
    }

    .navbar-brand .logo img,
    .navbar-brand img {
        max-height: 32px;
        width: auto;
    }
}

// Botones principales.
.btn-primary {
    box-shadow: 0 4px 14px rgba(0, 74, 198, .18);

    &:hover,
    &:focus {
        background-color: $vr-blue-light;
        border-color: $vr-blue-light;
    }
}

// Llamados a la acción destacados (inscribirse, enviar intento).
.btn-danger {
    box-shadow: 0 4px 14px rgba(225, 29, 72, .25);
}

// Tarjetas y bloques con el mismo redondeo del diseño.
.card,
.block,
.activity-item,
.coursebox {
    border-radius: 1rem;
}

.activity-item {
    border: 1px solid #E2E8F0;
}

// Encabezado de curso y de página.
#page-header .page-context-header .page-header-headings h1 {
    color: $vr-navy;
}

// Barras de progreso.
.progress-bar {
    background-color: $vr-blue;
}

// Insignias de finalización.
.badge-success,
.bg-success {
    background-color: #10B981 !important;
}

// Página de ingreso.
.pagelayout-login #page-wrapper {
    background-color: #FAF8FF;
}

.pagelayout-login .card {
    border-radius: 1rem;
    box-shadow: 0 20px 25px -5px rgba(15, 23, 42, .12), 0 8px 10px -6px rgba(15, 23, 42, .06);
}

// Pie de página.
#page-footer {
    background-color: $vr-navy;
    color: #BEC6E0;

    a {
        color: $vr-blue-pale;
    }
}
SCSS;
set_config('scss', $scss, 'theme_boost');
echo "= ajustes SCSS aplicados\n";

// 4. Tipografías del diseño (Boost no permite @import de fuentes dentro del SCSS).
$fuentes = '<link rel="preconnect" href="https://fonts.googleapis.com">' . "\n"
    . '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>' . "\n"
    . '<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700'
    . '&amp;family=Plus+Jakarta+Sans:wght@600;700;800&amp;display=swap">';
$cabecera = (string)($CFG->additionalhtmlhead ?? '');
if (strpos($cabecera, 'Plus+Jakarta+Sans') === false) {
    set_config('additionalhtmlhead', trim($cabecera . "\n" . $fuentes));
    echo "+ tipografías Inter y Plus Jakarta Sans cargadas\n";
} else {
    echo "= las tipografías ya estaban configuradas\n";
}

// 5. Logo, logo compacto y favicon.
$fs = get_file_storage();
$contexto = context_system::instance();
$archivos = [
    'logo' => 'logo.svg',
    'logocompact' => 'logo-compacto.svg',
    'favicon' => 'favicon.svg',
];
foreach ($archivos as $filearea => $archivo) {
    $origen = $assets . '/' . $archivo;
    if (!file_exists($origen)) {
        echo "  ! no se encontró $archivo\n";
        continue;
    }
    // Reemplaza el archivo anterior, si lo hubiera.
    $fs->delete_area_files($contexto->id, 'core_admin', $filearea, 0);
    $fs->create_file_from_pathname([
        'contextid' => $contexto->id,
        'component' => 'core_admin',
        'filearea'  => $filearea,
        'itemid'    => 0,
        'filepath'  => '/',
        'filename'  => $archivo,
    ], $origen);
    set_config($filearea, '/' . $archivo);
    echo "+ $filearea: $archivo\n";
}

// 6. Nombre del sitio acorde con la marca.
$sitio = get_site();
if ($sitio->fullname !== 'Virtual enelmapa.co') {
    $DB->set_field('course', 'fullname', 'Virtual enelmapa.co', ['id' => SITEID]);
    echo "= nombre del sitio: Virtual enelmapa.co\n";
}

theme_reset_all_caches();
purge_all_caches();

echo "\nTema actualizado. Revisa: {$CFG->wwwroot}/my/\n";
