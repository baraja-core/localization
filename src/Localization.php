<?php

declare(strict_types=1);

namespace Baraja\Localization;


use Baraja\Doctrine\EntityManager;
use Doctrine\DBAL\Exception\TableNotFoundException;
use Nette\Caching\Cache;
use Nette\Caching\IStorage;
use Nette\Http\Request;

final class Localization
{

	/** @var EntityManager */
	private $entityManager;

	/** @var Cache */
	private $cache;

	/** @var string|null */
	private $localeDomain;

	/** @var string|null */
	private $localeParameter;

	/** @var string|null */
	private $localeDefined;

	/** @var string|null */
	private $localeContext;


	/**
	 * @param EntityManager $entityManager
	 * @param IStorage $storage
	 */
	public function __construct(EntityManager $entityManager, IStorage $storage)
	{
		$this->entityManager = $entityManager;
		$this->cache = new Cache($storage, 'baraja-localization');
		$this->processCache();
	}


	/**
	 * Method return best locale for current request.
	 * Matching process use this strategy:
	 *
	 * 1. Defined locale by setLocale(), for example by router
	 * 2. Analyze of ?locale parameter in URL
	 * 3. Connected default locale to current domain
	 *
	 * If locale does not match, this logic throws exception.
	 *
	 * @param bool $fallbackToContextLocale
	 * @return string
	 */
	public function getLocale(bool $fallbackToContextLocale = false): string
	{
		$locale = $this->localeDefined ?? $this->localeParameter ?? $this->localeDomain;

		if ($fallbackToContextLocale === true) {
			if ($this->localeContext === null) {
				LocalizationException::contextLocaleIsEmpty($locale);
			}
			$locale = $this->localeContext;
		}

		if ($locale === null) {
			LocalizationException::canNotResolveLocale($this->localeDefined, $this->localeParameter, $this->localeDomain);
		}

		return $locale;
	}


	/**
	 * @internal use for routing or other locale logic.
	 * @param string $locale
	 * @return Localization
	 */
	public function setLocale(string $locale): self
	{
		$this->localeDefined = strtolower($locale);

		return $this;
	}


	/**
	 * @internal use for specific context cases, for example CMS manager.
	 * @param string $contextLocale
	 * @return Localization
	 */
	public function setContextLocale(string $contextLocale): self
	{
		$this->localeContext = strtolower($contextLocale);

		return $this;
	}


	public function getAvailableLocales(): array
	{
		return ['cs', 'en'];
	}


	public function getDefaultLocale(): string
	{
		return 'cs';
	}


	/**
	 * @return string[][]
	 */
	public function getFallbackLocales(): array
	{
		return [
			'cs' => ['sk'],
		];
	}


	/**
	 * @internal for DIC.
	 * @param Request $request
	 */
	public function processHttpRequest(Request $request): void
	{
		if (\is_string($localeParameter = $request->getUrl()->getQueryParameter('locale')) === true) {
			$this->localeParameter = $localeParameter;
		}

		try {
			$domains = $this->entityManager->getRepository(Domain::class)
				->createQueryBuilder('domain')
				->select('domain, locale')
				->leftJoin('domain.locale', 'locale')
				->getQuery()
				->getArrayResult();
		} catch (TableNotFoundException $e) {
			if (PHP_SAPI !== 'cli') {
				LocalizationException::tableDoesNotExist();
			}

			// Skipped in case of generating schema or configurator.
			return;
		}

		bdump($domains);
	}


	private function processCache(): void
	{
	}
}