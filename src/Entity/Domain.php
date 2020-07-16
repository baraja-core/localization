<?php

declare(strict_types=1);

namespace Baraja\Localization;


use Baraja\Doctrine\UUID\UuidIdentifier;
use Doctrine\ORM\Mapping as ORM;
use Nette\SmartObject;
use Nette\Utils\DateTime;

/**
 * @ORM\Entity()
 * @ORM\Table(name="core__localization_domain")
 */
class Domain
{
	use UuidIdentifier;
	use SmartObject;

	public const ENVIRONMENT_LOCALHOST = 'localhost';

	public const ENVIRONMENT_BETA = 'beta';

	public const ENVIRONMENT_PRODUCTION = 'production';

	/**
	 * @var bool
	 * @ORM\Column(type="boolean")
	 */
	private $https = false;

	/**
	 * @var string
	 * @ORM\Column(type="string", unique=true)
	 */
	private $domain;

	/**
	 * @var bool
	 * @ORM\Column(type="boolean", name="`is_www`")
	 */
	private $www = false;

	/**
	 * @var Locale|null
	 * @ORM\ManyToOne(targetEntity="\Baraja\Localization\Locale", inversedBy="domains")
	 */
	private $locale;

	/**
	 * localhost|beta|production
	 *
	 * @var string
	 * @ORM\Column(type="string", length=10)
	 */
	private $environment;

	/**
	 * @var bool
	 * @ORM\Column(type="boolean", name="`is_default`")
	 */
	private $default = false;

	/**
	 * @var string|null
	 * @ORM\Column(type="string", nullable=true)
	 */
	private $protectedPassword;

	/**
	 * @var bool
	 * @ORM\Column(type="boolean", name="`is_protected`")
	 */
	private $protected = true;

	/**
	 * @var \DateTime
	 * @ORM\Column(type="datetime")
	 */
	private $insertedDate;

	/**
	 * @var \DateTime
	 * @ORM\Column(type="datetime")
	 */
	private $updatedDate;


	public function __construct(string $domain, Locale $locale, string $environment = self::ENVIRONMENT_BETA)
	{
		$this->domain = $domain;
		$this->locale = $locale;
		$this->environment = $environment;
		$this->insertedDate = DateTime::from('now');
		$this->updatedDate = DateTime::from('now');
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
		return $this->environment;
	}


	public function setEnvironment(string $environment): void
	{
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
	 *
	 * @return string|null
	 */
	public function getProtectedPassword(): ?string
	{
		return $this->protectedPassword;
	}


	/**
	 * If is string, please insert plaintext password.
	 *
	 * @param string|null $protectedPassword
	 */
	public function setProtectedPassword(?string $protectedPassword): void
	{
		if ($protectedPassword !== null) {
			if (($hash = @password_hash($protectedPassword, PASSWORD_DEFAULT, [])) === false) { // @ is escalated to exception
				throw new \RuntimeException('Computed hash is invalid. ' . error_get_last()['message']);
			}
			$protectedPassword = (string) $hash;
		}

		$this->protectedPassword = $protectedPassword;
		$this->setUpdatedDate();
	}


	/**
	 * Verify process for check password is ok by internal logic.
	 *
	 * @param string $password
	 * @return bool
	 */
	public function isPasswordOk(string $password): bool
	{
		if (($is = password_verify($password, $this->protectedPassword ?? '')) === true && password_needs_rehash($this->protectedPassword, PASSWORD_DEFAULT, []) === true) {
			$this->setProtectedPassword($password);
		}

		return $is;
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


	public function getInsertedDate(): \DateTime
	{
		return $this->insertedDate;
	}


	public function getUpdatedDate(): \DateTime
	{
		return $this->updatedDate;
	}


	private function setUpdatedDate(): void
	{
		$this->updatedDate = DateTime::from('now');
	}
}
