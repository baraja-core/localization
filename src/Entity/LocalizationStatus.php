<?php

declare(strict_types=1);

namespace Baraja\Localization;


final class LocalizationStatus
{
	/** @var array<int, string> */
	private array $availableLocales;

	private string $defaultLocale;

	/** @var array<string, array<int, string>> */
	private array $fallbackLocales;

	/** @var array<string, string|null> */
	private array $localeToTitleSuffix;

	/** @var array<string, string|null> */
	private array $localeToTitleSeparator;

	/** @var array<string, string|null> */
	private array $localeToTitleFormat;

	/** @var array<string, string|null> */
	private array $localeToSiteName;

	/** @var array<string, string> */
	private array $domainToLocale;

	/** @var array<string, string> */
	private array $domainToEnvironment;

	/** @var array<string, bool> */
	private array $domainToProtected;

	/** @var array<string, string> */
	private array $domainToScheme;

	/** @var array<string, bool> */
	private array $domainToUseWww;

	/** @var array<string, array<string, string>> */
	private array $domainByEnvironment;

	/** @var array<int, array{id: int, locale: array{id: int, locale: string}|null, domain: string, environment: string, protected: bool, https: bool, www: bool, default: bool}> */
	private array $domains;


	/**
	 * @param array<int, string> $availableLocales
	 * @param array<string, array<int, string>> $fallbackLocales
	 * @param array<string, string|null> $localeToTitleSuffix
	 * @param array<string, string|null> $localeToTitleSeparator
	 * @param array<string, string|null> $localeToTitleFormat
	 * @param array<string, string|null> $localeToSiteName
	 * @param array<string, string> $domainToLocale
	 * @param array<string, string> $domainToEnvironment
	 * @param array<string, bool> $domainToProtected
	 * @param array<string, string> $domainToScheme
	 * @param array<string, bool> $domainToUseWww
	 * @param array<string, array<string, string>> $domainByEnvironment
	 * @param array<int, array{id: int, locale: array{id: int, locale: string}|null, domain: string, environment: string, protected: bool, https: bool, www: bool, default: bool}> $domains
	 */
	public function __construct(
		array $availableLocales,
		string $defaultLocale,
		array $fallbackLocales,
		array $localeToTitleSuffix,
		array $localeToTitleSeparator,
		array $localeToTitleFormat,
		array $localeToSiteName,
		array $domainToLocale,
		array $domainToEnvironment,
		array $domainToProtected,
		array $domainToScheme,
		array $domainToUseWww,
		array $domainByEnvironment,
		array $domains
	) {
		$this->availableLocales = $availableLocales;
		$this->defaultLocale = $defaultLocale;
		$this->fallbackLocales = $fallbackLocales;
		$this->localeToTitleSuffix = $localeToTitleSuffix;
		$this->localeToTitleSeparator = $localeToTitleSeparator;
		$this->localeToTitleFormat = $localeToTitleFormat;
		$this->localeToSiteName = $localeToSiteName;
		$this->domainToLocale = $domainToLocale;
		$this->domainToEnvironment = $domainToEnvironment;
		$this->domainToProtected = $domainToProtected;
		$this->domainToScheme = $domainToScheme;
		$this->domainToUseWww = $domainToUseWww;
		$this->domainByEnvironment = $domainByEnvironment;
		$this->domains = $domains;
	}


	/**
	 * @return array<int, string>
	 */
	public function getAvailableLocales(): array
	{
		return $this->availableLocales;
	}


	public function getDefaultLocale(): string
	{
		return $this->defaultLocale;
	}


	/**
	 * @return array<string, array<int, string>>
	 */
	public function getFallbackLocales(): array
	{
		return $this->fallbackLocales;
	}


	/**
	 * @return array<string, string|null>
	 */
	public function getLocaleToTitleSuffix(): array
	{
		return $this->localeToTitleSuffix;
	}


	/**
	 * @return array<string, string|null>
	 */
	public function getLocaleToTitleSeparator(): array
	{
		return $this->localeToTitleSeparator;
	}


	/**
	 * @return array<string, string|null>
	 */
	public function getLocaleToTitleFormat(): array
	{
		return $this->localeToTitleFormat;
	}


	/**
	 * @return array<string, string|null>
	 */
	public function getLocaleToSiteName(): array
	{
		return $this->localeToSiteName;
	}


	/**
	 * @return array<string, string>
	 */
	public function getDomainToLocale(): array
	{
		return $this->domainToLocale;
	}


	/**
	 * @return array<string, string>
	 */
	public function getDomainToEnvironment(): array
	{
		return $this->domainToEnvironment;
	}


	/**
	 * @return array<string, bool>
	 */
	public function getDomainToProtected(): array
	{
		return $this->domainToProtected;
	}


	/**
	 * @return array<string, string>
	 */
	public function getDomainToScheme(): array
	{
		return $this->domainToScheme;
	}


	/**
	 * @return array<string, bool>
	 */
	public function getDomainToUseWww(): array
	{
		return $this->domainToUseWww;
	}


	/**
	 * @return array<string, array<string, string>>
	 */
	public function getDomainByEnvironment(): array
	{
		return $this->domainByEnvironment;
	}


	/**
	 * @return array<int, array{id: int, locale: array{id: int, locale: string}|null, domain: string, environment: string, protected: bool, https: bool, www: bool, default: bool}>
	 */
	public function getDomains(): array
	{
		return $this->domains;
	}
}
