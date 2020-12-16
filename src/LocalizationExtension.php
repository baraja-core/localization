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
		$builder = $this->getContainerBuilder();

		OrmAnnotationsExtension::addAnnotationPathToManager($builder, 'Baraja\Localization', __DIR__ . '/Entity');
		DatabaseExtension::addCustomType('translate', TranslateType::class);

		$builder->addDefinition($this->prefix('localization'))
			->setFactory(Localization::class)
			->addSetup(
				'if (PHP_SAPI !== \'cli\') {' . "\n"
				. "\t" . '$service->processHttpRequest(?);' . "\n"
				. '}' . "\n"
				. LocalizationHelper::class . '::setLocalization($service)',
				['@' . Request::class]
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
