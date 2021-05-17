<?php

declare(strict_types=1);

namespace Baraja\Localization;


use Baraja\Doctrine\EntityManager;
use Doctrine\DBAL\Exception\TableNotFoundException;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Nette\Caching\Cache;
use Nette\Caching\Storage;
use Nette\Http\Request;

final class Localization
{
	private Cache $cache;

	private ?string $localeDomain = null;

	private ?string $localeParameter = null;

	private ?string $localeDefined = null;

	private ?string $localeContext = null;

	private ?string $currentDomain = null;

	private ?LocalizationStatus $status = null;


	public function __construct(
		private EntityManager $entityManager,
		Storage $storage
	) {
		$this->cache = new Cache($storage, 'baraja-localization');
	}


	public static function normalize(string $locale): string
	{
		$locale = strtolower(trim($locale));
		if (!preg_match('/^[a-z]{2}$/', $locale)) {
			throw new \InvalidArgumentException(
				'Locale "' . $locale . '" is invalid, because it must be 2 [a-z] characters.'
				. "\n" . 'To solve this issue: Use alphabet locale like "en", "de", "cs".',
			);
		}

		return $locale;
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
	 */
	public function getLocale(bool $fallbackToContextLocale = false): string
	{
		if (PHP_SAPI === 'cli') {
			throw new \RuntimeException('Localization: Current locale is not available in CLI.');
		}
		if ($this->localeDomain === null) {
			$this->localeDomain = $this->getStatus()->getDomainToLocale()[$this->currentDomain] ?? null;
		}

		$locale = $this->localeDefined ?? $this->localeParameter ?? $this->localeDomain;

		if ($fallbackToContextLocale === true && $locale === null) { // Fallback only in case of unmatched locale
			if ($this->localeContext === null) {
				throw new LocalizationException('Context locale is empty.');
			}
			$locale = $this->localeContext;
		}
		if ($locale === null) {
			throw new LocalizationException(
				'Can not resolve current locale. Explored inputs:' . "\n"
				. 'Defined: "' . ($this->localeDefined ?? 'null') . '", '
				. 'URL parameter: "' . ($this->localeParameter ?? 'null') . '", '
				. 'domain: "' . ($this->localeDomain ?? 'null') . '".' . "\n"
				. 'Did you defined default locale for all domains or use router rewriting?',
			);
		}

		return self::normalize($locale);
	}


	/**
	 * @internal use for routing or other locale logic.
	 */
	public function setLocale(string $locale): self
	{
		$this->localeDefined = self::normalize($locale);

		return $this;
	}


	/**
	 * @internal use for specific context cases, for example CMS manager.
	 */
	public function setContextLocale(string $contextLocale): self
	{
		$this->localeContext = self::normalize($contextLocale);

		return $this;
	}


	/**
	 * Return Domain::ENVIRONMENT_* constant for current request.
	 * If environment detection failed, method keep "production".
	 * In case of CLI return "production".
	 */
	public function getEnvironment(): string
	{
		if (PHP_SAPI === 'cli' || $this->currentDomain === null) {
			return Domain::ENVIRONMENT_PRODUCTION;
		}

		return $this->getStatus()->getDomainToEnvironment()[$this->currentDomain] ?? Domain::ENVIRONMENT_PRODUCTION;
	}


	/**
	 * @return string[]
	 */
	public function getAvailableLocales(): array
	{
		return $this->getStatus()->getAvailableLocales();
	}


	public function getDefaultLocale(): string
	{
		return $this->getStatus()->getDefaultLocale();
	}


	/**
	 * Rewriting table for the most used languages sorted according to national customs.
	 * For example, if there is no Slovak, it is better to rewrite the language first to Czech and then to English.
	 *
	 * In format: [
	 *    'locale' => ['fallback', ...]
	 * ]
	 *
	 * For example: [
	 *    'cs' => ['sk', 'en']
	 * ]
	 *
	 * @return string[][]
	 */
	public function getFallbackLocales(): array
	{
		return $this->getStatus()->getFallbackLocales();
	}


	/**
	 * Define basic localization configuration by current HTTP request.
	 *
	 * Main localization match is defined by current domain (locale by domain detection).
	 * Secondary detection (for multiple locales within a single domain) is GET ?locale parameter.
	 *
	 * @internal for DIC.
	 */
	public function processHttpRequest(Request $request): void
	{
		if (PHP_SAPI === 'cli') {
			throw new \RuntimeException('Localization: Processing HTTP request is not available in CLI.');
		}

		$url = $request->getUrl();
		$localeParameter = $url->getQueryParameter('locale');
		if (\is_string($localeParameter) === true) {
			$this->localeParameter = self::normalize($localeParameter);
		}
		$this->currentDomain = str_replace('www.', '', $url->getDomain(4));
	}


	/**
	 * Clear whole internal domain cache and return current relevant localize settings.
	 *
	 * @internal
	 */
	public function clearCache(): void
	{
		$this->cache->remove('configuration');
	}


	/**
	 * Create internal LocalizationStatus entity from cache.
	 */
	public function getStatus(): LocalizationStatus
	{
		if ($this->status !== null) {
			return $this->status;
		}
		$config = $this->cache->load('configuration');
		if ($config === null) {
			$config = $this->createCache();
			$this->cache->save('configuration', $config, [
				Cache::EXPIRE => '30 minutes',
			]);
		}

		return $this->status = new LocalizationStatus(
			$config['availableLocales'],
			$config['defaultLocale'],
			$config['fallbackLocales'],
			$config['localeToTitleSuffix'],
			$config['localeToTitleSeparator'],
			$config['localeToTitleFormat'],
			$config['localeToSiteName'],
			$config['domainToLocale'],
			$config['domainToEnvironment'],
			$config['domainToProtected'],
			$config['domainToScheme'],
			$config['domainToUseWww'],
			$config['domainByEnvironment'],
			$config['domains'],
		);
	}


	/**
	 * @throws NoResultException|NonUniqueResultException
	 */
	public function getLocaleEntity(string $locale): Locale
	{
		return $this->entityManager->getRepository(Locale::class)
			->createQueryBuilder('locale')
			->where('locale.locale = :locale')
			->setParameter('locale', $locale)
			->setMaxResults(1)
			->getQuery()
			->getSingleResult();
	}


	/**
	 * @return mixed[]
	 */
	private function createCache(): array
	{
		$defaultLocale = null;
		$availableLocales = [];
		$localeToTitleSuffix = [];
		$localeToTitleSeparator = [];
		$localeToTitleFormat = [];
		$localeToSiteName = [];
		$domainToLocale = [];
		$domainToEnvironment = [];
		$domainIsProtected = [];
		$domainToScheme = [];
		$domainToUseWww = [];
		$domainByEnvironment = [];

		try {
			/** @var mixed[][]|mixed[][][] $domains */
			$domains = $this->entityManager->getRepository(Domain::class)
				->createQueryBuilder('domain')
				->select('domain, locale')
				->leftJoin('domain.locale', 'locale')
				->getQuery()
				->getArrayResult();
		} catch (TableNotFoundException) {
			throw new LocalizationException(
				'Localization database tables does not exist. Please create tables and insert default configuration first.' . "\n"
				. 'To solve this issue: Please create tables ("core__localization_domain" and "core__localization_locale") with default data.',
			);
		}

		if ($domains === []) {
			throw new LocalizationException(
				'Domain list is empty. Please define project domains to table "core__localization_domain".',
			);
		}

		foreach ($domains as $domain) {
			$domainToLocale[$domain['domain']] = $locale = (string) ($domain['locale']['locale'] ?? 'en');
			$domainToEnvironment[$domain['domain']] = (string) $domain['environment'];
			$domainIsProtected[$domain['domain']] = (bool) $domain['protected'];
			$domainToScheme[$domain['domain']] = ((bool) $domain['https']) === true ? 'https' : 'http';
			$domainToUseWww[$domain['domain']] = (bool) $domain['www'];
			if (isset($domainByEnvironment[$domain['environment']][$locale]) === false || $domain['default'] === true) {
				if (isset($domainByEnvironment[$domain['environment']]) === false) {
					$domainByEnvironment[$domain['environment']] = [];
				}
				$domainByEnvironment[$domain['environment']][$locale] = (string) $domain['domain'];
			}
		}

		$locales = $this->entityManager->getRepository(Locale::class)
			->createQueryBuilder('locale')
			->select('PARTIAL locale.{id, locale, default, titleSuffix, titleSeparator, titleFormat, siteName}')
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
			$localeToTitleSuffix[$locale['locale']] = $locale['titleSuffix'];
			$localeToTitleSeparator[$locale['locale']] = $locale['titleSeparator'];
			$localeToTitleFormat[$locale['locale']] = $locale['titleFormat'];
			$localeToSiteName[$locale['locale']] = $locale['siteName'];
		}

		return [
			'availableLocales' => $availableLocales,
			'defaultLocale' => $defaultLocale,
			'fallbackLocales' => [], // TODO: Implement smart logic for get fallback languages by common convention.
			'localeToTitleSuffix' => $localeToTitleSuffix,
			'localeToTitleSeparator' => $localeToTitleSeparator,
			'localeToTitleFormat' => $localeToTitleFormat,
			'localeToSiteName' => $localeToSiteName,
			'domainToLocale' => $domainToLocale,
			'domainToEnvironment' => $domainToEnvironment,
			'domainToProtected' => $domainIsProtected,
			'domainToScheme' => $domainToScheme,
			'domainToUseWww' => $domainToUseWww,
			'domainByEnvironment' => $domainByEnvironment,
			'domains' => $domains,
		];
	}
}
