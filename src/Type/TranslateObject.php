<?php

declare(strict_types=1);

namespace Baraja\Localization;


use Nette\MemberAccessException;
use Nette\SmartObject;
use Nette\Utils\ObjectHelpers;

trait TranslateObject
{
	use SmartObject;

	/**
	 * ->getName(): Translation           get property value
	 * ->setName('Jan'): void             set value in current locale
	 * ->setName('Honza', 'cs'): void     set value in specific locale
	 *
	 * @param string[] $args
	 */
	public function __call(string $name, array $args): mixed
	{
		if (
			property_exists($this, $name)
			&& ObjectHelpers::hasProperty(static::class, $name) === 'event'
		) {
			if (is_iterable($this->$name)) {
				foreach ($this->$name as $handler) {
					$handler(...$args);
				}
			}

			return null;
		}
		if (preg_match('/^(?:get|is)([A-Z].*)$/', $name, $getter)) {
			return $this->{$this->firstLower($getter[1])};
		}
		if (preg_match('/^set([A-Z].*)$/', $name, $setter)) {
			$propertyName = $this->firstLower($setter[1]);
			$this->{$propertyName} = $this->createTranslationEntity(
				value: $args[0] ?? null,
				propertyName: $propertyName,
				locale: $args[1] ?? null,
			);
		} else {
			static $recursion = false;

			if ($recursion === true) {
				$recursion = false;
				throw new MemberAccessException(
					'Call to undefined method ' . static::class . '::' . $name . '()'
					. (property_exists($this, $name) ? ', did you mean property $' . $name . '?' : ''),
				);
			}

			$recursion = true;
			$dynamicCall = $this->$name($args);
			$recursion = false;

			return $dynamicCall;
		}

		return null;
	}


	final public function setPropertyTranslateValue(string $property, ?string $value, ?string $locale): void
	{
		$ref = new \ReflectionClass($this);
		$prop = $ref->getProperty($property);
		$prop->setAccessible(true);
		$prop->setValue($this, new Translation($value, $locale));
	}


	private function firstLower(string $haystack): string
	{
		return mb_strtolower($haystack[0] ?? '', 'UTF-8') . \substr($haystack, 1);
	}


	private function createTranslationEntity(mixed $value, string $propertyName, ?string $locale = null): Translation
	{
		$ref = $this->getPropertyReflection($propertyName);
		if ($ref->isInitialized($this) === false) { // PHP 7.4 support for typed property without default value
			return new Translation($value, $locale);
		}
		/** @var Translation|null $translation */
		$translation = $this->{$propertyName};
		if ($translation === null) {
			return new Translation($value, $locale);
		}
		if ($translation->addTranslate($value, $locale) === true) {
			$translation = $translation->regenerate();
		}

		return $translation;
	}


	private function getPropertyReflection(string $propertyName): \ReflectionProperty
	{
		static $cache = [];
		$key = static::class . '::' . $propertyName;
		$createReflection = static function (object $class, string $propertyName): \ReflectionProperty {
			try {
				$ref = new \ReflectionProperty($class, $propertyName);
				$ref->setAccessible(true);

				return $ref;
			} catch (\ReflectionException) {
				$refClass = new \ReflectionClass($class);
				$parentClass = $refClass->getParentClass();
				while ($parentClass !== false) {
					try {
						$ref = $parentClass->getProperty($propertyName);
						$ref->setAccessible(true);

						return $ref;
					} catch (\ReflectionException) {
						$parentClass = $refClass->getParentClass();
					}
				}
			}
			$e = new \RuntimeException('Property $' . $propertyName . ' in entity "' . $class::class . '" does not exist.');
			$e->tracyAction = [
				'link' => 'https://stackoverflow.com/questions/26187097/doctrine-reflectionexception-property-does-not-exist',
				'label' => 'more info',
			];

			throw $e;
		};

		return $cache[$key] ?? $cache[$key] = $createReflection($this, $propertyName);
	}
}
