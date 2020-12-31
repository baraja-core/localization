<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
	return;
}

\Baraja\PackageManager\Composer\TaskManager::get()->addTask(
	new \Baraja\Localization\DomainAndLocaleTask(\Baraja\PackageManager\PackageRegistrator::get())
);
