<?php

declare(strict_types=1);

namespace Baraja\Localization;


use Baraja\Doctrine\UUID\UuidIdentifier;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'core__localization_domain')]
class Domain
{
	use UuidIdentifier;

	public const
		ENVIRONMENT_LOCALHOST = 'localhost',
		ENVIRONMENT_BETA = 'beta',
		ENVIRONMENT_PRODUCTION = 'production';

	public const ENVIRONMENTS = [
		self::ENVIRONMENT_LOCALHOST,
		self::ENVIRONMENT_BETA,
		self::ENVIRONMENT_PRODUCTION,
	];

	#[ORM\Column(type: 'boolean')]
	private bool $https = false;

	#[ORM\Column(type: 'string', unique: true)]
	private string $domain;

	#[ORM\Column(name: 'is_www', type: 'boolean')]
	private bool $www = false;

	#[ORM\ManyToOne(targetEntity: Locale::class, inversedBy: 'domains')]
	private ?Locale $locale;

	/**
	 * Value is constant of Domain::ENVIRONMENT_*
	 * Possible values: "localhost", "beta", "production".
	 */
	#[ORM\Column(type: 'string', length: 10)]
	private string $environment;

	#[ORM\Column(name: 'is_default', type: 'boolean')]
	private bool $default = false;

	#[ORM\Column(type: 'string', nullable: true)]
	private ?string $protectedPassword;

	#[ORM\Column(name: 'is_protected', type: 'boolean')]
	private bool $protected = true;

	#[ORM\Column(type: 'datetime_immutable')]
	private \DateTimeImmutable $insertedDate;

	#[ORM\Column(type: 'datetime_immutable')]
	private \DateTimeImmutable $updatedDate;


	public function __construct(string $domain, Locale $locale, string $environment = self::ENVIRONMENT_BETA)
	{
		$this->setDomain($domain);
		$this->locale = $locale;
		$this->setEnvironment($environment);
		$this->insertedDate = new \DateTimeImmutable('now');
		$this->updatedDate = new \DateTimeImmutable('now');
	}


	public function isHttps(): bool
	{
		return $this->https;
	}


	public function setHttps(bool $https): void
	{
		$this->https = $https;
		$this->setUpdatedDate();
	}


	public function isWww(): bool
	{
		return $this->www;
	}


	public function setWww(bool $www): void
	{
		$this->www = $www;
		$this->setUpdatedDate();
	}


	public function getDomain(): string
	{
		return $this->domain;
	}


	public function setDomain(string $domain): void
	{
		if ($domain !== 'localhost' && preg_match('/^(?:[-A-Za-z0-9]+\.)+[A-Za-z]{2,6}$/', $domain) !== 1) {
			throw new \InvalidArgumentException(sprintf('Domain "%s" is not in valid format.', $domain));
		}
		if (strlen($domain) > 255) {
			throw new \InvalidArgumentException(sprintf('The maximum length of the domain is 8 characters, but "%s" given.', $domain));
		}

		$this->domain = $domain;
		$this->setUpdatedDate();
	}


	public function getLocale(): string
	{
		if ($this->locale === null) {
			throw new \RuntimeException('Domain locale is empty. Did you select all values?');
		}

		return $this->locale->getLocale();
	}


	public function setLocale(Locale $locale): void
	{
		$this->locale = $locale;
		$this->setUpdatedDate();
	}


	public function isDefault(): bool
	{
		return $this->default;
	}


	public function setDefault(bool $default): void
	{
		$this->default = $default;
		$this->setUpdatedDate();
	}


	public function getEnvironment(): string
	{
		if (\in_array($this->environment, self::ENVIRONMENTS, true) === false) {
			throw new \RuntimeException(sprintf(
				'Environment "%s" is invalid. Please fix broken database record.',
				$this->environment,
			));
		}

		return $this->environment;
	}


	public function setEnvironment(string $environment): void
	{
		if (\in_array($environment, self::ENVIRONMENTS, true) === false) {
			throw new \InvalidArgumentException(sprintf(
				'Environment "%s" must be in "%s".',
				$environment,
				implode('", "', self::ENVIRONMENTS),
			));
		}

		$this->environment = $environment;
		$this->setUpdatedDate();
	}


	public function isLocalhost(): bool
	{
		return $this->environment === self::ENVIRONMENT_LOCALHOST;
	}


	public function isBeta(): bool
	{
		return $this->environment === self::ENVIRONMENT_BETA;
	}


	public function isProduction(): bool
	{
		return $this->environment === self::ENVIRONMENT_PRODUCTION;
	}


	/**
	 * Return as BCrypt hash.
	 */
	public function getProtectedPassword(): ?string
	{
		return $this->protectedPassword;
	}


	/**
	 * If is string, please insert plaintext password.
	 */
	public function setProtectedPassword(?string $protectedPassword): void
	{
		if ($protectedPassword !== null) {
			$hash = (string) @password_hash($protectedPassword, PASSWORD_DEFAULT, []); // @ is escalated to exception
			if ($hash === '') {
				throw new \LogicException(sprintf(
					'Computed hash for input "%s" is invalid: %s',
					$protectedPassword,
					error_get_last()['message'] ?? '',
				));
			}
			$protectedPassword = $hash;
		}

		$this->protectedPassword = $protectedPassword;
		$this->setUpdatedDate();
	}


	/**
	 * Verify process for check password is ok by internal logic.
	 */
	public function isPasswordOk(string $password): bool
	{
		if (password_verify($password, $this->protectedPassword ?? '') === true) {
			if (password_needs_rehash($this->protectedPassword ?? '', PASSWORD_DEFAULT, []) === true) {
				$this->setProtectedPassword($password);
			}

			return true;
		}

		return false;
	}


	public function isProtected(): bool
	{
		return $this->protected;
	}


	public function setProtected(bool $protected): void
	{
		$this->protected = $protected;
		$this->setUpdatedDate();
	}


	public function getInsertedDate(): \DateTimeImmutable
	{
		return $this->insertedDate;
	}


	public function getUpdatedDate(): \DateTimeImmutable
	{
		return $this->updatedDate;
	}


	private function setUpdatedDate(): void
	{
		$this->updatedDate = new \DateTimeImmutable('now');
	}
}
