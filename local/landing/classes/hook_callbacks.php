<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace local_landing;

/**
 * Sirve la landing publica en la portada a visitantes sin sesion.
 *
 * Los usuarios autenticados siguen viendo la portada normal de Moodle.
 *
 * @package    local_landing
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class hook_callbacks {
    /**
     * Listener for the after_config hook.
     *
     * @param \core\hook\after_config $hook
     */
    public static function after_config(\core\hook\after_config $hook): void {
        global $CFG;

        if (CLI_SCRIPT || AJAX_SCRIPT || during_initial_install() || isset($CFG->upgraderunning)) {
            return;
        }
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET' || empty($_SERVER['SCRIPT_FILENAME'])) {
            return;
        }
        if (realpath($_SERVER['SCRIPT_FILENAME']) !== realpath($CFG->dirroot . '/index.php')) {
            return;
        }
        if (isloggedin() && !isguestuser()) {
            return;
        }
        if (!empty($CFG->maintenance_enabled)) {
            return;
        }

        $file = $CFG->dirroot . '/local/landing/landing.html';
        if (!is_readable($file)) {
            return;
        }

        $html = strtr(file_get_contents($file), [
            '{{WWWROOT}}' => $CFG->wwwroot,
            '{{YEAR}}' => date('Y'),
        ]);

        header('Content-Type: text/html; charset=utf-8');
        header('Cache-Control: no-cache');
        header('X-Frame-Options: sameorigin');
        echo $html;
        exit;
    }
}
