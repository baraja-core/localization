<?php

declare(strict_types=1);

namespace Baraja\Localization;


use Baraja\Doctrine\DatabaseExtension;
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
		DatabaseExtension::addCustomType('translate', TranslateType::class);

		$builder = $this->getContainerBuilder();

		/** @var ServiceDefinition $localization */
		$localization = $builder->getDefinitionByType(Localization::class);

		/** @var ServiceDefinition $httpRequest */
		$httpRequest = $builder->getDefinitionByType(Request::class);

		$builder->addDefinition($this->prefix('localization'))
			->setFactory(Localization::class);

		$localization->addSetup(
			'if (PHP_SAPI !== \'cli\') {' . "\n"
			. "\t" . '$service->processHttpRequest($this->getService(?));' . "\n"
			. '}' . "\n"
			. LocalizationHelper::class . '::setLocalization($service)',
			[$httpRequest->getName()]
		);
	}


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
