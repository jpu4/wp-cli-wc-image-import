<?php

$config = array(
    "project_name" => "cmi",
    "env" => array(
        "dev" => array(
            "db" => array(
                "name" => "",
                "user" => "",
                "pass" => "",
                "host" => ""
            ),
            "paths" => array(
                "root" => "",
                "images" => "",
                "scripts" => "/scripts",
                "backup" => "/bak",
                "site" => "/wordpress",
            ),
            "cli" => array(
                "wp_user" => "",
                "non_root" => ""
            )
        ),
        "stage" => array(),
        "prod" => array()
    )
);

define('CONFIG',$config['env']['dev']);
define('CLIENT_NAME', $config['project_name'] );
define('DB_HOST', CONFIG['db']['host']);
define('DB_NAME', CONFIG['db']['name']);
define('DB_USER', CONFIG['db']['user']);
define('DB_PASS', CONFIG['db']['pass']);
define('DIR_ROOT', CONFIG['paths']['root']);
define('DIR_SCRIPTS', DIR_ROOT . CONFIG['paths']['scripts']);
define('DIR_IMAGES', DIR_ROOT . CONFIG['paths']['images']);
define('DIR_BACKUP', DIR_ROOT . CONFIG['paths']['backup']);
define('DIR_SITE', DIR_ROOT . CONFIG['paths']['site']);
define('USER_WPCLI', CONFIG['cli']['wp_user']);
define('USER_NONROOT', CONFIG['cli']['non_root']);
define('FILE_IMPORTLOG', 'importlog.csv');
define('TIMESTAMP', date("Y-m-d H:i:s"));
define('DATETIME', date("Ymd-His"));

$mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// define('WP_CLI_ROOT', '/path/to/wp-cli');
// include WP_CLI_ROOT . '/php/wp-cli.php';

/*
    Error reporting.
*/
ini_set("error_reporting", "true");
error_reporting(E_ALL|E_STRICT);


?>