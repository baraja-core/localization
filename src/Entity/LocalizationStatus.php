<?php

declare(strict_types=1);

namespace Baraja\Localization;


use Nette\SmartObject;

final class LocalizationStatus
{
	use SmartObject;

	/** @var string[] */
	private $availableLocales;

	/** @var string */
	private $defaultLocale;

	/** @var string[][] */
	private $fallbackLocales;

	/** @var string[] */
	private $domainToLocale;

	/** @var string[] */
	private $domainToEnvironment;

	/** @var bool[] */
	private $domainToProtected;

	/** @var mixed[][]|mixed[][][] */
	private $domains;


	/**
	 * @param string[] $availableLocales
	 * @param string $defaultLocale
	 * @param string[][] $fallbackLocales
	 * @param string[] $domainToLocale
	 * @param string[] $domainToEnvironment
	 * @param bool[] $domainToProtected
	 * @param mixed[][]|mixed[][][] $domains
	 */
	public function __construct(array $availableLocales, string $defaultLocale, array $fallbackLocales, array $domainToLocale, array $domainToEnvironment, array $domainToProtected, array $domains)
	{
		$this->availableLocales = $availableLocales;
		$this->defaultLocale = $defaultLocale;
		$this->fallbackLocales = $fallbackLocales;
		$this->domainToLocale = $domainToLocale;
		$this->domainToEnvironment = $domainToEnvironment;
		$this->domainToProtected = $domainToProtected;
		$this->domains = $domains;
	}


	/**
	 * @return string[]
	 */
	public function getAvailableLocales(): array
	{
		return $this->availableLocales;
	}


	/**
	 * @return string
	 */
	public function getDefaultLocale(): string
	{
		return $this->defaultLocale;
	}


	/**
	 * @return string[][]
	 */
	public function getFallbackLocales(): array
	{
		return $this->fallbackLocales;
	}


	/**
	 * @return string[]
	 */
	public function getDomainToLocale(): array
	{
		return $this->domainToLocale;
	}


	/**
	 * @return string[]
	 */
	public function getDomainToEnvironment(): array
	{
		return $this->domainToEnvironment;
	}


	/**
	 * @return bool[]
	 */
	public function getDomainToProtected(): array
	{
		return $this->domainToProtected;
	}


	/**
	 * @return mixed[][]|mixed[][][]
	 */
	public function getDomains(): array
	{
		return $this->domains;
	}
}