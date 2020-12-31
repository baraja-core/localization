<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli' || class_exists(\Baraja\PackageManager\Composer\TaskManager::class) === false) {
	return;
}

\Baraja\PackageManager\Composer\TaskManager::get()->addTask(
	new \Baraja\Localization\DomainAndLocaleTask(\Baraja\PackageManager\PackageRegistrator::get())
);
