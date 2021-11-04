<?php

declare(strict_types=1);

namespace Baraja\Localization;


use Baraja\PackageManager\Composer\BaseTask;
use Baraja\PackageManager\Helpers;
use Baraja\PackageManager\PackageRegistrator;
use Doctrine\DBAL\Exception\TableNotFoundException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;

/**
 * Priority: 90
 */
final class DomainAndLocaleTask extends BaseTask
{
	private EntityManagerInterface $entityManager;


	/**
	 * Create locales, validate and than create domain records.
	 */
	public function run(): bool
	{
		try {
			if (PackageRegistrator::getCiDetect() !== null) {
				echo 'CI environment detected: Schema generating skipped.';

				return true;
			}
		} catch (\Exception) {
		}

		/** @var EntityManagerInterface $em */
		$em = $this->getContainer()->getByType(EntityManagerInterface::class);
		$this->entityManager = $em;
		echo 'Locales:' . "\n\n";

		if ($this->selectLocales() === []) {
			echo 'Locale table is empty.' . "\n\n";
			$this->createLocale();
			echo "\n\n";
		} else {
			$this->fixLocales();
		}
		if (
			PackageRegistrator::isConfigurationMode() === true
			|| $this->renderLocaleTable($this->selectLocales()) === 0
		) {
			while ($this->ask('Create new locale?', ['y', 'n']) === 'y') {
				$this->createLocale();
				$this->renderLocaleTable($this->selectLocales());
			}
		} else {
			echo 'Locale settings was skipped, because table contains some locales.';
			echo 'For change some values please enable the Configuration mode.';
			echo "\n\n";
		}
		if (PackageRegistrator::isConfigurationMode() === true || $this->renderDomainTable() === 0) {
			while ($this->ask('Create new domain?', ['y', 'n']) === 'y') {
				$this->createDomain();
				$this->renderDomainTable();
			}
		} else {
			echo 'Domain settings was skipped, because table contains some domains.';
			echo 'For change some values please enable the Configuration mode.';
			echo "\n\n";
		}

		return true;
	}


	public function getName(): string
	{
		return 'Domains and locales';
	}


	private function createLocale(): void
	{
		echo '-> CREATE NEW LOCALE' . "\n\n";

		$locale = null;
		for ($ttl = 8; $ttl > 0; $ttl--) {
			$locale = strtolower($this->ask('Locale code (2 characters):') ?? '');
			if ($locale === '' || preg_match('/^[a-z]{2}$/', $locale) === 0) {
				Helpers::terminalRenderError('Locale "' . $locale . '" is invalid. Please type 2 lower characters (a-z).');
				$locale = null;
			} else {
				break;
			}
		}
		if ($locale === null) {
			$locale = 'en';
		}

		$entity = new Locale($locale);
		$this->entityManager->persist($entity);
		$this->entityManager->flush();

		/** @var Locale[] $allLocales */
		$allLocales = $this->getLocaleRepository()->findAll();

		if (\count($allLocales) === 1) {
			$entity->setDefault(true);
			$this->entityManager->flush();
		} elseif ($this->ask('Mark "' . $entity->getLocale() . '" as default?', ['y', 'n']) === 'y') {
			foreach ($allLocales as $currentLocale) {
				$currentLocale->setDefault($currentLocale->getLocale() === $entity->getLocale());
			}
			$this->entityManager->flush();
		}

		$this->fixLocales($allLocales);
	}


	private function createDomain(): void
	{
		echo "\n\n";
		while (true) {
			while (true) {
				$domain = $this->ask('What is your domain name? Keep empty for "localhost":');
				if ($domain !== null) {
					$domain = str_replace('www.', '', $domain);
					if (preg_match('/^(?!-)(?:(?:[a-zA-Z\d][a-zA-Z\d\-]{0,61})?[a-zA-Z\d]\.){1,126}(?!\d+)[a-zA-Z\d]{1,63}$/', $domain)) {
						break;
					}
					Helpers::terminalRenderError('Domain "' . $domain . '" is invalid. Use format "baraja.cz" for example.');
				} else {
					break;
				}
			}
			try {
				/** @var Domain $domainEntity */
				$domainEntity = $this->getDomainRepository()
					->createQueryBuilder('domain')
					->where('domain.domain = :domain')
					->setParameter('domain', $domain)
					->setMaxResults(1)
					->getQuery()
					->getSingleResult();

				Helpers::terminalRenderError('Domain "' . $domain . '" already exist.');
				if ($this->ask('Edit domain "' . $domain . '"?', ['y', 'n']) === 'y') {
					$this->editDomain($domainEntity);
					break;
				}
				if ($this->ask('Keep creating domain?', ['y', 'n']) === 'n') {
					break;
				}
				continue;
			} catch (NoResultException | NonUniqueResultException) {
				break;
			}
		}

		$domain ??= 'localhost';
		echo 'Creating "' . $domain . '"...' . "\n\n";

		/** @var Locale[] $locales */
		$locales = $this->getLocaleRepository()
			->createQueryBuilder('locale')
			->orderBy('locale.position', 'ASC')
			->getQuery()
			->getResult();

		$localeCodeToEntity = [];
		$defaultLocale = null;
		foreach ($locales as $locale) {
			if ($locale->isDefault() === true) {
				$defaultLocale = $locale->getLocale();
			}
			$localeCodeToEntity[$locale->getLocale()] = $locale;
		}

		/** @phpstan-ignore-next-line */
		$localeCode = $this->ask('What is locale? Keep empty for "' . $defaultLocale . '":', array_merge(array_keys($localeCodeToEntity), [null]));

		$environment = $this->ask('What is domain environment? "l" = localhost, "b" = beta, "p" = production', ['l', 'b', 'p']);
		$environments = [
			'l' => Domain::ENVIRONMENT_LOCALHOST,
			'b' => Domain::ENVIRONMENT_BETA,
			'p' => Domain::ENVIRONMENT_PRODUCTION,
		];

		$entity = new Domain($domain, $localeCodeToEntity[$localeCode ?? $defaultLocale], $environments[$environment] ?? Domain::ENVIRONMENT_BETA);
		$this->entityManager->persist($entity);
		$this->entityManager->flush();
		$entity->setHttps($this->ask('Use https for "' . $domain . '"?', ['y', 'n']) === 'y');
		$entity->setWww($this->ask('Use www prefix for "' . $domain . '"?', ['y', 'n']) === 'y');

		if ($entity->isBeta() === true && $this->ask('Use password to protect this beta domain?', ['y', 'n']) === 'y') {
			$entity->setProtected(true);
			if ($this->ask('Generate random password?', ['y', 'n']) === 'y') {
				$password = $this->generateRandomPassword();
				echo 'New password is: "' . $password . '"' . "\n\n";
			} else {
				do {
					$password = $this->ask('Please enter new password (5 characters is required):');
					if ($password !== null && mb_strlen($password, 'UTF-8') < 5) {
						Helpers::terminalRenderError('This is not good password. Please type again.');
						$password = null;
					}
				} while ($password !== null);
			}
			$entity->setProtectedPassword($password);
		} else {
			$entity->setProtected(false);
		}

		$this->entityManager->flush();

		if ($this->ask('Is "' . $domain . '" default domain for "' . $entity->getEnvironment() . '" and "' . $entity->getLocale() . '"?', ['y', 'n']) === 'y') {
			/** @var Domain[] $domainsInEnvironmentAndLocale */
			$domainsInEnvironmentAndLocale = $this->getDomainRepository()
				->createQueryBuilder('domain')
				->leftJoin('domain.locale', 'locale')
				->where('domain.environment = :environment')
				->andWhere('locale.locale = :locale')
				->setParameter('environment', $entity->getEnvironment())
				->setParameter('locale', $entity->getLocale())
				->getQuery()
				->getResult();

			foreach ($domainsInEnvironmentAndLocale as $currentDomain) {
				$currentDomain->setDefault($currentDomain->getId() === $entity->getId());
			}
		}

		$this->entityManager->flush();
	}


	private function editDomain(Domain $domain): void
	{
		Helpers::terminalRenderError('Domain editing is not supported yet, please use graphical CMS.');
	}


	/**
	 * @return array<int, array{
	 *     id: int,
	 *     locale: string,
	 *     default: bool,
	 *     active: bool,
	 *     position: int,
	 *     insertedDate: \DateTimeInterface
	 * }>
	 */
	private function selectLocales(): array
	{
		try {
			/** @phpstan-ignore-next-line */
			return $this->getLocaleRepository()
				->createQueryBuilder('locale')
				->select('PARTIAL locale.{id, locale, default, active, position, insertedDate}')
				->orderBy('locale.default', 'DESC')
				->addOrderBy('locale.active', 'DESC')
				->addOrderBy('locale.position', 'ASC')
				->getQuery()
				->getArrayResult();
		} catch (TableNotFoundException) {
			throw new LocalizationException(
				'Localization database tables does not exist. Please create tables and insert default configuration first.' . "\n"
				. 'To solve this issue: Please create tables ("core__localization_domain" and "core__localization_locale") with default data.',
			);
		}
	}


	/**
	 * Render current locale table and return count of locales.
	 *
	 * @param array<int, array{
	 *     id: int,
	 *     locale: string,
	 *     default: bool,
	 *     active: bool,
	 *     position: int,
	 *     insertedDate: \DateTimeInterface
	 * }> $locales
	 */
	private function renderLocaleTable(array $locales): int
	{
		echo "\n\n" . 'CURRENT LOCALE TABLE:' . "\n\n";
		echo '| Locale | Default | Active | Position |  Inserted date   |' . "\n";
		echo '|--------|---------|--------|----------|------------------|' . "\n";

		foreach ($locales as $locale) {
			echo '|' . str_pad($locale['locale'], 8, ' ', STR_PAD_BOTH);
			echo '|' . str_pad($locale['default'] ? 'y' : 'n', 9, ' ', STR_PAD_BOTH);
			echo '|' . str_pad($locale['active'] ? 'y' : 'n', 8, ' ', STR_PAD_BOTH);
			echo '|' . str_pad((string) $locale['position'], 10, ' ', STR_PAD_BOTH);
			echo '|' . str_pad($locale['insertedDate']->format('Y-m-d H:i'), 18, ' ', STR_PAD_BOTH);
			echo '|' . "\n";
		}

		echo "\n\n";

		return \count($locales);
	}


	/**
	 * Render current domain table and return count of domains.
	 */
	private function renderDomainTable(): int
	{
		$this->createDefaultLocalhost();

		echo "\n\n" . 'CURRENT DOMAIN TABLE:' . "\n\n";

		/** @var array<int, array{
		 *     id: int,
		 *     environment: string,
		 *     https: bool,
		 *     www: bool,
		 *     protectedPassword: string|null,
		 *     protected: bool,
		 *     default: bool,
		 *     insertedDate: \DateTimeInterface,
		 *     updatedDate: \DateTimeInterface,
		 *     domain: string,
		 *     locale: array{id: int, locale: string}|null
		 * }> $domains
		 */
		$domains = $this->getDomainRepository()
			->createQueryBuilder('domain')
			->select('PARTIAL domain.{id, environment, https, www, protectedPassword, protected, default, insertedDate, updatedDate, domain}')
			->addSelect('PARTIAL locale.{id, locale}')
			->leftJoin('domain.locale', 'locale')
			->getQuery()
			->getArrayResult();

		$environments = [];

		echo '| Environment | HTTPS | WWW | Locale | Pass | Protected | Default |  Inserted date   |  Updated date    | Domain |' . "\n";
		echo '|-------------|-------|-----|--------|------|-----------|---------|------------------|------------------|--------|' . "\n";

		foreach ($domains as $domain) {
			if (isset($domain['locale']['locale']) === false) {
				throw new \RuntimeException(
					'Database record for domain "' . $domain['domain'] . '" (' . $domain['id'] . ') is broken.'
					. "\n" . 'Please fix column "locale_id" in table "core__localization_domain" manually and run this task again.',
				);
			}

			$environments[$domain['environment']] = true;
			echo '|' . str_pad($domain['environment'], 13, ' ', STR_PAD_LEFT);
			echo '|' . str_pad($domain['https'] ? 'y' : '-', 7, ' ', STR_PAD_BOTH);
			echo '|' . str_pad($domain['www'] ? 'y' : '-', 5, ' ', STR_PAD_BOTH);
			echo '|' . str_pad($domain['locale']['locale'], 8, ' ', STR_PAD_BOTH);
			echo '|' . str_pad($domain['protectedPassword'] === null ? '-' : 'yes', 6, ' ', STR_PAD_BOTH);
			echo '|' . str_pad($domain['protected'] ? 'y' : '-', 11, ' ', STR_PAD_BOTH);
			echo '|' . str_pad($domain['default'] ? 'y' : '-', 9, ' ', STR_PAD_BOTH);
			echo '|' . str_pad($domain['insertedDate']->format('Y-m-d H:i'), 18, ' ', STR_PAD_BOTH);
			echo '|' . str_pad($domain['updatedDate']->format('Y-m-d H:i'), 18, ' ', STR_PAD_BOTH);
			echo '| ' . $domain['domain'] . ' |' . "\n";
		}

		echo "\n\n";
		echo 'Used environments: ' . implode(', ', array_keys($environments)) . "\n\n";
		if (\count(array_keys($environments)) === \count(Domain::ENVIRONMENTS)) {
			echo 'All environments exist.';
		} else {
			foreach (Domain::ENVIRONMENTS as $possibleEnvironment) {
				if (isset($environments[$possibleEnvironment]) === false) {
					echo 'Please define domain for "' . $possibleEnvironment . '" environment.' . "\n\n";
				}
			}
		}

		return \count($domains);
	}


	/**
	 * @param Locale[]|null $locales
	 */
	private function fixLocales(?array $locales = null): void
	{
		if ($locales === null) {
			/** @var Locale[] $locales */
			$locales = $this->getLocaleRepository()
				->createQueryBuilder('locale')
				->orderBy('locale.position', 'ASC')
				->getQuery()
				->getResult();
		}

		/** @var Locale[] $duplicityPositions */
		$duplicityPositions = [];
		$existDefault = false;
		$lastEntity = null;
		$usedPositions = [];
		$topPosition = 0;
		foreach ($locales as $currentLocale) {
			$lastEntity = $currentLocale;
			if ($currentLocale->isDefault() === true) {
				if ($existDefault === true) {
					$currentLocale->setDefault(false);
				}
				$existDefault = true;
			}
			if ($currentLocale->getPosition() > $topPosition) {
				$topPosition = $currentLocale->getPosition();
			}
			$usedPositions[$currentLocale->getPosition()] = true;
			if (isset($usedPositions[$currentLocale->getPosition()]) === true) {
				$duplicityPositions[] = $currentLocale;
			}
		}

		// Fix only one Locale as default
		if ($existDefault === false && $lastEntity !== null) {
			$lastEntity->setDefault(true);
		}
		if ($duplicityPositions !== []) {
			// Fix all positions will have unique number
			foreach ($duplicityPositions as $duplicityPosition) {
				$topPosition++;
				$duplicityPosition->setPosition($topPosition);
			}
			$this->entityManager->flush();

			// Use natural position sorting
			/** @var Locale[] $locales */
			$locales = $this->getLocaleRepository()
				->createQueryBuilder('locale')
				->orderBy('locale.position', 'ASC')
				->getQuery()
				->getResult();

			foreach ($locales as $localePosition => $locale) {
				$locale->setPosition($localePosition + 1);
			}
		}

		$this->entityManager->flush();
	}


	/**
	 * Try find localhost domain. If domain does not exist, automatically create environment with default configuration.
	 */
	private function createDefaultLocalhost(): void
	{
		try {
			$this->getDomainRepository()
				->createQueryBuilder('domain')
				->where('domain.domain = :domain')
				->setParameter('domain', 'localhost')
				->getQuery()
				->getSingleResult();

			return;
		} catch (NoResultException | NonUniqueResultException) {
		}

		try {
			/** @var Locale $defaultLocale */
			$defaultLocale = $this->getLocaleRepository()
				->createQueryBuilder('locale')
				->where('locale.default = true')
				->setMaxResults(1)
				->getQuery()
				->getSingleResult();
		} catch (NoResultException | NonUniqueResultException) {
			Helpers::terminalRenderError('Default locale does not exists. Can not create localhost domain. Did you registered locales correctly?');

			return;
		}

		$domain = new Domain('localhost', $defaultLocale, Domain::ENVIRONMENT_LOCALHOST);
		$domain->setHttps(false);
		$domain->setWww(false);
		$domain->setProtectedPassword(null);
		$domain->setProtected(false);
		$domain->setDefault(true);

		$this->entityManager->persist($domain);
		$this->entityManager->flush();
	}


	private function generateRandomPassword(): string
	{
		$charlist = implode('', array_merge(range('a', 'z'), range('A', 'Z'), range('0', '9')));
		$chLen = strlen($charlist);
		if ($chLen < 2) {
			throw new \LogicException('Character list must contain at least two chars.');
		}

		$res = '';
		for ($i = 0; $i < 16; $i++) {
			$res .= $charlist[random_int(0, $chLen - 1)];
		}

		return $res;
	}


	private function getLocaleRepository(): EntityRepository
	{
		return new EntityRepository(
			$this->entityManager,
			$this->entityManager->getClassMetadata(Locale::class)
		);
	}


	private function getDomainRepository(): EntityRepository
	{
		return new EntityRepository(
			$this->entityManager,
			$this->entityManager->getClassMetadata(Domain::class)
		);
	}
}
