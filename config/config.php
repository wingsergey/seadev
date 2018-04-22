<?php
define('DEBUG_MODE', 1);

define('ROOT_DIR', __DIR__ . '/../');
define('DIR_FS_CATALOG', ROOT_DIR);
define('TWIG_CACHE_DIR', ROOT_DIR . 'compilation_cache/');
define('DIR_FS_APP', ROOT_DIR . 'src/');
define('DIR_FS_VIEWS', DIR_FS_APP . 'view/');
define('DIR_FS_CONTROLLER', DIR_FS_APP . 'controller/');
define('DIR_FS_MODEL', DIR_FS_APP . 'model/');
define('DIR_FS_HELPERS', DIR_FS_APP . 'helpers/');
define('DIR_FS_EXCEPTIONS', DIR_FS_APP . 'exception/');

error_reporting(DEBUG_MODE);

require_once ROOT_DIR . '/vendor/autoload.php';
ini_set("display_errors", 0);
require_once DIR_FS_HELPERS . '/ErrorLogger.class.php';
require_once DIR_FS_EXCEPTIONS . '/HttpException.php';

// controllers
require_once DIR_FS_CONTROLLER . '/ApiController.php';

$templateDirs = array(DIR_FS_VIEWS);
$loader = new Twig_Loader_Filesystem($templateDirs);

$twig = new Twig_Environment($loader, array(
    'cache'       => TWIG_CACHE_DIR,
    'auto_reload' => !DEBUG_MODE,
));
