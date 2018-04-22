<?php
include_once __DIR__ . '/../config/config.php';

// default controller
$controller = 'index';
$method = 'index';

// parse query string
$urlInfo = parse_url(trim($_SERVER['REQUEST_URI'], '/'));
if (isset($urlInfo['path']) && $urlInfo['path']) {
    $pathParts = explode('/', $urlInfo['path']);
    $controller = $pathParts[0] ?? 'index';
    $method = $pathParts[1] ?? 'index';
}

// check controller and method exists
try {
    $controllerName = $controller . 'Controller';
    $controllerFile = DIR_FS_CONTROLLER . $controllerName . '.php';
    if (!file_exists($controllerFile)) {
        throw new HttpException(404, sprintf('Controller "%s" does not exists!', $controllerName));
    }

    require_once $controllerFile;
    $controllerClass = new $controllerName();

    if (!method_exists($controllerClass, $method)) {
        throw new HttpException(404, sprintf('Method "%s" does not exists!', $method));
    }

    echo $controllerClass->$method();
    exit(0);

} catch (HttpException $httpException) {
    echo $twig->render('exception.html.twig', ['body' => $httpException->getMessage()]);
    exit;

} catch (\Exception $exception) {
    echo $twig->render('exception.html.twig', ['body' => $httpException->getMessage()]);
    exit;
}

die('Something went wrong!');