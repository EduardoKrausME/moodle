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
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.

/**
 * Route Moodle's clean URLs when using PHP's development server in CI.
 *
 * This file is never deployed as a production web server configuration.
 *
 * @package core
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$webroot = realpath(__DIR__ . '/../../public');
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/';
$path = rawurldecode($path);

// Let PHP's built-in server serve static assets and regular PHP scripts,
// including slash-argument URLs such as /lib/requirejs.php/revision/core/a.js.
$script = $path;
if (preg_match('~^(.+?\.php)(?:/.*)?$~', $path, $match)) {
    $script = $match[1];
}
$target = realpath($webroot . $script);
if ($target && str_starts_with($target, $webroot . DIRECTORY_SEPARATOR) && is_file($target)) {
    return false;
}

// Routes such as /core/esm/... need the real Moodle router; otherwise
// PHP's built-in server falls back to index.php and serves HTML as JavaScript.
require $webroot . '/r.php';
