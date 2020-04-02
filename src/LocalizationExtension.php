<?php

declare(strict_types=1);

namespace Baraja\Localization;


use Nette\DI\CompilerExtension;
use Nette\Http\Request;
use Nette\PhpGenerator\ClassType;

final class LocalizationExtension extends CompilerExtension
{

	/**
	 * @param ClassType $class
	 */
	public function afterCompile(ClassType $class): void
	{
		$class->getMethod('initialize')->addBody(
			'$this->getByType(?)->processHttpRequest($this->getByType(?));' . "\n", [
				Localization::class,
				Request::class,
			]
		);
	}
}