<?php

declare(strict_types=1);

namespace Baraja\Localization;


use Baraja\Doctrine\ORM\DI\OrmAnnotationsExtension;
use Nette\DI\CompilerExtension;
use Nette\DI\Definitions\ServiceDefinition;
use Nette\Http\Request;
use Nette\PhpGenerator\ClassType;

final class LocalizationExtension extends CompilerExtension
{

	/**
	 * @return string[]
	 */
	public static function mustBeDefinedBefore(): array
	{
		return [OrmAnnotationsExtension::class];
	}


	public function beforeCompile(): void
	{
		OrmAnnotationsExtension::addAnnotationPath('Baraja\Localization', __DIR__ . '/Entity');

		/** @var ServiceDefinition $localization */
		$localization = $this->getContainerBuilder()->getDefinitionByType(Localization::class);

		/** @var ServiceDefinition $httpRequest */
		$httpRequest = $this->getContainerBuilder()->getDefinitionByType(Request::class);

		$localization->addSetup(
			'if (PHP_SAPI !== \'cli\') {' . "\n"
			. "\t" . '$service->processHttpRequest($this->getService(?));' . "\n"
			. '}' . "\n"
			. LocalizationHelper::class . '::setLocalization($service)',
			[$httpRequest->getName()]
		);
	}


	/**
	 * @param ClassType $class
	 */
	public function afterCompile(ClassType $class): void
	{
		if (PHP_SAPI === 'cli') {
			return;
		}

		/** @var ServiceDefinition $localization */
		$localization = $this->getContainerBuilder()->getDefinitionByType(Localization::class);

		$class->getMethod('initialize')->addBody(
			'// localization.' . "\n"
			. '(function () {' . "\n"
			. "\t" . '$this->getService(?);' . "\n"
			. '})();', [$localization->getName()]
		);
	}
}
