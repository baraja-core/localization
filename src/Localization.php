<?php

declare(strict_types=1);

namespace Baraja\Localization;


use Doctrine\DBAL\Exception\TableNotFoundException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Nette\Caching\Cache;
use Nette\Caching\Storage;
use Nette\Caching\Storages\FileStorage;
use Nette\Http\Request;
use Nette\Http\Url;

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
		private EntityManagerInterface $entityManager,
		?Storage $storage = null,
	) {
		if ($storage === null) {
			$tempDir = sys_get_temp_dir() . '/localization/' . md5(__FILE__);
			if (!is_dir($tempDir) && !@mkdir($tempDir, 0777, true) && !is_dir($tempDir)) { // @ - dir may already exist
				throw new \RuntimeException(sprintf('Unable to create directory "%s".', $tempDir));
			}
			$storage = new FileStorage($tempDir);
		}
		$this->cache = new Cache($storage, 'baraja-localization');
	}


	/**
	 * de: German language content, independent of region
	 * en-GB: English language content, for GB users
	 * de-ES: German language content, for users in Spain
	 */
	public static function normalize(string $locale): string
	{
		$locale = strtolower(trim($locale));
		if (preg_match('/^([a-z]{2})(?:-([a-z]{2}))?$/', $locale, $localeParser) === 1) {
			return $localeParser[1];
		}

		throw new \InvalidArgumentException(
			sprintf('Locale "%s" is invalid, because it must be 2 [a-z] characters.', $locale)
			. "\n" . 'To solve this issue: Use alphabet locale like "en", "de", "cs".',
		);
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
			$this->localeDefined = $this->getDefaultLocale();
		}
		if ($this->localeDefined !== null) {
			return $this->localeDefined;
		}
		if ($this->localeDomain === null) {
			$this->localeDomain = $this->getStatus()->getDomainToLocale()[$this->currentDomain] ?? null;
		}

		$locale = $this->localeParameter ?? $this->localeDomain;
		if ($fallbackToContextLocale === true && $locale === null) { // Fallback only in case of unmatched locale
			if ($this->localeContext === null) {
				throw new LocalizationException('Context locale is empty.');
			}
			$locale = $this->localeContext;
		}
		if ($locale === null) {
			throw new LocalizationException(
				'Can not resolve current locale. Explored inputs:' . "\n"
				. sprintf(
					'Defined: "%s", URL parameter: "%s", domain: "%s".',
					$this->localeDefined ?? 'null',
					$this->localeParameter ?? 'null',
					$this->localeDomain ?? 'null',
				) . "\n"
				. 'Did you defined default locale for all domains or use router rewriting?',
			);
		}
		$this->localeDefined = self::normalize($locale);

		return $this->localeDefined;
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
	 * @return array<string, array<int, string>>
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
		if (is_string($localeParameter) === true) {
			try {
				$this->localeParameter = self::normalize($localeParameter);
			} catch (\InvalidArgumentException) {
				if (headers_sent() === false) {
					$canonicalUrl = new Url($url);
					$canonicalUrl->setQueryParameter('locale', null);
					if ($url->getAbsoluteUrl() !== $canonicalUrl->getAbsoluteUrl()) {
						header('Location: ' . $canonicalUrl->getAbsoluteUrl());
						die;
					}
				}
			}
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
		/** @var array{availableLocales: array<int, string>, defaultLocale: string, fallbackLocales: array<string, array<int, string>>, localeToTitleSuffix: array<string, (string|null)>, localeToTitleSeparator: array<string, (string|null)>, localeToTitleFormat: array<string, (string|null)>, localeToSiteName: array<string, (string|null)>, domainToLocale: array<string, string>, domainToEnvironment: array<string, string>, domainToProtected: array<string, bool>, domainToScheme: array<string, string>, domainToUseWww: array<string, bool>, domainByEnvironment: array<string, array<string, string>>, domains: array<int, array{id: int, locale: (array{id: int, locale: string}|null), domain: string, environment: string, protected: bool, https: bool, www: bool, default: bool}>}|null $config */
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
		$return = (new EntityRepository(
			$this->entityManager,
			$this->entityManager->getClassMetadata(Locale::class),
		))
			->createQueryBuilder('locale')
			->where('locale.locale = :locale')
			->setParameter('locale', $locale)
			->setMaxResults(1)
			->getQuery()
			->getSingleResult();
		assert($return instanceof Locale);

		return $return;
	}


	/**
	 * @return array{
	 *     availableLocales: array<int, string>,
	 *     defaultLocale: string,
	 *     fallbackLocales: array{},
	 *     localeToTitleSuffix: array<string, string|null>,
	 *     localeToTitleSeparator: array<string, string|null>,
	 *     localeToTitleFormat: array<string, string|null>,
	 *     localeToSiteName: array<string, string|null>,
	 *     domainToLocale: non-empty-array<string, string>,
	 *     domainToEnvironment: non-empty-array<string, string>,
	 *     domainToProtected: non-empty-array<string, bool>,
	 *     domainToScheme: non-empty-array<string, string>,
	 *     domainToUseWww: non-empty-array<string, bool>,
	 *     domainByEnvironment: non-empty-array<string, array<string, string>>,
	 *     domains: non-empty-array<int, array{id: int, locale: array{id: int, locale: string}|null, domain: string, environment: string, protected: bool, https: bool, www: bool, default: bool}>}>
	 * }
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
			/** @var array<int, array{id: int, locale: array{id: int, locale: string}|null, domain: string, environment: string, protected: bool, https: bool, www: bool, default: bool}>}> $domains */
			$domains = (new EntityRepository(
				$this->entityManager,
				$this->entityManager->getClassMetadata(Domain::class),
			))
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
			$locale = ($domain['locale']['locale'] ?? 'en');
			$domainToLocale[$domain['domain']] = $locale;
			$domainToEnvironment[$domain['domain']] = $domain['environment'];
			$domainIsProtected[$domain['domain']] = $domain['protected'];
			$domainToScheme[$domain['domain']] = $domain['https'] === true ? 'https' : 'http';
			$domainToUseWww[$domain['domain']] = $domain['www'];
			if (
				isset($domainByEnvironment[$domain['environment']][$locale]) === false
				|| $domain['default'] === true
			) {
				if (isset($domainByEnvironment[$domain['environment']]) === false) {
					$domainByEnvironment[$domain['environment']] = [];
				}
				$domainByEnvironment[$domain['environment']][$locale] = (string) $domain['domain'];
			}
		}

		/** @var array<int, array{id: int, locale: string, default: bool, titleSuffix: string|null, titleSeparator: string|null, titleFormat: string|null, siteName: string|null}> $locales */
		$locales = (new EntityRepository(
			$this->entityManager,
			$this->entityManager->getClassMetadata(Locale::class),
		))
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
					trigger_error(sprintf(
						'Multiple default locales: Locale "%s" and "%s" is marked as default.',
						$defaultLocale,
						$locale['locale'],
					));
				} else {
					$defaultLocale = $locale['locale'];
				}
			}
			$localeToTitleSuffix[$locale['locale']] = $locale['titleSuffix'];
			$localeToTitleSeparator[$locale['locale']] = $locale['titleSeparator'];
			$localeToTitleFormat[$locale['locale']] = $locale['titleFormat'];
			$localeToSiteName[$locale['locale']] = $locale['siteName'];
		}

		/** @phpstan-ignore-next-line */
		return [
			'availableLocales' => $availableLocales,
			'defaultLocale' => $defaultLocale,
			'fallbackLocales' => [], // TODO: array<string, array<int, string>> Implement smart logic for get fallback languages by common convention.
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
