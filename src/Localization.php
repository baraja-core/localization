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

	/** @var string|null */
	private $currentDomain;

	/** @var LocalizationStatus|null */
	private $status;


	/**
	 * @param EntityManager $entityManager
	 * @param IStorage $storage
	 */
	public function __construct(EntityManager $entityManager, IStorage $storage)
	{
		if (PHP_SAPI === 'cli') {
			throw new \RuntimeException('Localization is not available in CLI.');
		}

		$this->entityManager = $entityManager;
		$this->cache = new Cache($storage, 'baraja-localization');
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
		if ($this->localeDomain === null) {
			$this->localeDomain = $this->getStatus()->getDomainToLocale()[$this->currentDomain] ?? null;
		}

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


	/**
	 * @return string[]
	 */
	public function getAvailableLocales(): array
	{
		return $this->getStatus()->getAvailableLocales();
	}


	/**
	 * @return string
	 */
	public function getDefaultLocale(): string
	{
		return $this->getStatus()->getDefaultLocale();
	}


	/**
	 * @return string[][]
	 */
	public function getFallbackLocales(): array
	{
		return $this->getStatus()->getFallbackLocales();
	}


	/**
	 * @internal for DIC.
	 * @param Request $request
	 */
	public function processHttpRequest(Request $request): void
	{
		$url = $request->getUrl();
		if (\is_string($localeParameter = $url->getQueryParameter('locale')) === true) {
			$this->localeParameter = $localeParameter;
		}
		$this->currentDomain = str_replace('www.', '', $url->getDomain(4));
	}


	/**
	 * @internal
	 * Clear whole internal domain cache and return current relevant localize settings.
	 */
	public function clearCache(): void
	{
		$this->cache->remove('configuration');
	}


	/**
	 * Create internal LocalizationStatus entity from cache.
	 *
	 * @return LocalizationStatus
	 */
	public function getStatus(): LocalizationStatus
	{
		if ($this->status !== null) {
			return $this->status;
		}

		if (($config = $this->cache->load('configuration')) === null) {
			$this->cache->save('configuration', $config = $this->createCache(), [
				Cache::EXPIRE => '30 minutes',
			]);
		}

		return $this->status = new LocalizationStatus(
			$config['availableLocales'],
			$config['defaultLocale'],
			$config['fallbackLocales'],
			$config['domainToLocale'],
			$config['domainToEnvironment'],
			$config['domainToProtected'],
			$config['domains']
		);
	}


	/**
	 * @return mixed[]
	 */
	private function createCache(): array
	{
		$defaultLocale = null;
		$availableLocales = [];
		$domainToLocale = [];
		$domainToEnvironment = [];
		$domainIsProtected = [];

		try {
			/** @var mixed[][]|mixed[][][] $domains */
			$domains = $this->entityManager->getRepository(Domain::class)
				->createQueryBuilder('domain')
				->select('domain, locale')
				->leftJoin('domain.locale', 'locale')
				->getQuery()
				->getArrayResult();
		} catch (TableNotFoundException $e) {
			LocalizationException::tableDoesNotExist();
		}

		if ($domains === []) {
			LocalizationException::domainListIsEmpty();
		}

		foreach ($domains as $domain) {
			$domainToLocale[$domain['domain']] = (string) ($domain['locale']['locale'] ?? 'en');
			$domainToEnvironment[$domain['domain']] = (string) $domain['environment'];
			$domainIsProtected[$domain['domain']] = (bool) $domain['protected'];
		}

		$locales = $this->entityManager->getRepository(Locale::class)
			->createQueryBuilder('locale')
			->select('PARTIAL locale.{id, locale, default}')
			->where('locale.active = TRUE')
			->orderBy('locale.position', 'ASC')
			->getQuery()
			->getArrayResult();

		foreach ($locales as $locale) {
			$availableLocales[] = $locale['locale'];
			if ($locale['default'] === true) {
				if ($defaultLocale !== null) {
					trigger_error('Multiple default locales: Locale "' . $defaultLocale . '" and "' . $locale['locale'] . '" is marked as default.');
				} else {
					$defaultLocale = $locale['locale'];
				}
			}
		}

		return [
			'availableLocales' => $availableLocales,
			'defaultLocale' => $defaultLocale,
			'fallbackLocales' => [],
			'domainToLocale' => $domainToLocale,
			'domainToEnvironment' => $domainToEnvironment,
			'domainToProtected' => $domainIsProtected,
			'domains' => $domains,
		];
	}
}