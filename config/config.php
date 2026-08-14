<?php
/**
 * config/config.php
 * Configuración central del sistema
 */

define('APP_NAME',    'SistemaQR Control');
define('APP_VERSION', '1.0.0');

// Ruta física del servidor (para require_once)
define('ROOT_PATH',   dirname(__DIR__) . '/');

// Alias por si alguna otra parte de tu código usa APP_ROOT
define('APP_ROOT',    dirname(__DIR__));

// Ruta web del navegador
define('BASE_URL', 'https://controlasistencia.duckdns.org/');

// ── Zona horaria ──────────────────────────────────────────────
date_default_timezone_set('America/Lima');

// ── Base de datos ─────────────────────────────────────────────
define('DB_HOST',    getenv('DB_HOST')    ?: 'localhost');
define('DB_PORT',    getenv('DB_PORT')    ?: '3306');
define('DB_NAME',    getenv('DB_NAME')    ?: 'filomena_100');
define('DB_USER',    getenv('DB_USER')    ?: 'admin');
define('DB_PASS',    getenv('DB_PASS')    ?: '12345678');
define('DB_CHARSET', 'utf8mb4');

// ── Horas de comedor (valores por defecto, sobreescribibles desde BD) ──
define('HORA_DESAYUNO_INI', '06:00');
define('HORA_DESAYUNO_FIN', '10:59');
define('HORA_ALMUERZO_INI', '12:00');
define('HORA_ALMUERZO_FIN', '15:59');
define('HORA_CENA_INI',     '16:00');
define('HORA_CENA_FIN',     '23:59');

// ── Jornada laboral ───────────────────────────────────────────
define('HORAS_PROGRAMADAS_DEFAULT', 11);

// ── Rutas ─────────────────────────────────────────────────────
define('VIEWS_PATH',   ROOT_PATH . 'views');
define('EXPORTS_PATH', ROOT_PATH . 'exports');
?>