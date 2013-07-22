<?php

use Nette\Application\Routers\Route,
	Nette\Application\Routers\RouteList,
	Nette\Application\Routers\SimpleRouter;


// Load Nette Framework or autoloader generated by Composer
require __DIR__ . '/../libs/autoload.php';

// Configure application
$configurator = new Nette\Config\Configurator;

// Enable Nette Debugger for error visualisation & logging
//$configurator->setDebugMode(TRUE);
$configurator->enableDebugger(__DIR__ . '/../log');

// Enable RobotLoader - this will load all classes automatically
$configurator->setTempDirectory(__DIR__ . '/../temp');
$configurator->createRobotLoader()
	->addDirectory(__DIR__)
    ->addDirectory(__DIR__ . '/../libs')
	->register();

// Create Dependency Injection container from config.neon file
$configurator->addConfig(__DIR__ . '/config/config.neon');
$configurator->addConfig(__DIR__ . '/config/config.local.neon', $configurator::NONE); // none section
$container = $configurator->createContainer();

// Setup router using mod_rewrite detection
if (function_exists('apache_get_modules') && in_array('mod_rewrite', apache_get_modules())) {
	$container->router[] = new Route('index.php', 'Front:Default:default', Route::ONE_WAY);

	$container->router[] = $accountRouter = new RouteList('Account');
	$accountRouter[] = new Route('account/<presenter>/<action>', 'Default:default');

    $container->router[] = $adminRouter = new RouteList('Admin');
    $adminRouter[] = new Route('ayr/module/edit/<moduleid>[/<id>]', 'Module:edit');
    $adminRouter[] = new Route('ayr/module/rowedit/<moduleid>[/<id>]', 'Module:rowedit');
    $adminRouter[] = new Route('ayr/module/rowdelete/<moduleid>[/<id>]', 'Module:rowdelete');
    $adminRouter[] = new Route('ayr/module/delete/<moduleid>[/<id>]', 'Module:delete');
    $adminRouter[] = new Route('ayr/module/newrow/<moduleid>', 'Module:newrow');

    $adminRouter[] = new Route('ayr/<presenter>/<action>[/<id>]', 'Default:default');

	$container->router[] = $frontRouter = new RouteList('Front');
	$frontRouter[] = new Route('<presenter>/<action>[/<id>]', 'Default:default');

} else {
	$container->router = new SimpleRouter('Front:Default:default');
}

$container->application->onStartup[] = function () use ($container) {
    // spusti sa chvilku po zavolani $application->run()
    // ma na starosti nastavenie konstant pre customizovanie instancie webu
    $url = $container->httpRequest->getUrl();
    $container->themeRepository->themeInit($url);
};

return $container;
