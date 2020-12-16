<?php

declare(strict_types=1);

namespace Baraja\Localization;


/**
 * Static helper defined by DIC.
 * This feature is reserved for use in Doctrine entities.
 *
 * @internal
 */
final class LocalizationHelper
{
	private static ?Localization $localization = null;


	/**
	 * Get current locale if localization matched.
	 *
	 * @internal
	 */
	public static function getLocale(bool $fallbackToContextLocale = false): string
	{
		return self::getLocalization()->getLocale($fallbackToContextLocale);
	}


	/**
	 * @return string[][]
	 * @internal
	 */
	public static function getFallbackLocales(): array
	{
		return self::getLocalization()->getFallbackLocales();
	}


	/**
	 * @internal
	 */
	public static function getLocalization(): Localization
	{
		if (self::$localization === null) {
			throw new LocalizationException('Localization have been not defined.');
		}

		return self::$localization;
	}


	/**
	 * @internal for DIC
	 */
	public static function setLocalization(Localization $localization): void
	{
		self::$localization = $localization;
	}
}
