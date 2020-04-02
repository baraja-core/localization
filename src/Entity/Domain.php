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
	 * @var Locale
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
	 * @var \DateTime
	 * @ORM\Column(type="datetime")
	 */
	private $insertedDate;

	/**
	 * @var \DateTime
	 * @ORM\Column(type="datetime")
	 */
	private $updatedDate;


	/**
	 * @param string $domain
	 * @param Locale $locale
	 * @param string $environment
	 */
	public function __construct(string $domain, Locale $locale, string $environment = self::ENVIRONMENT_BETA)
	{
		$this->domain = $domain;
		$this->locale = $locale;
		$this->environment = $environment;
		$this->insertedDate = DateTime::from('now');
		$this->updatedDate = DateTime::from('now');
	}


	/**
	 * @return bool
	 */
	public function isHttps(): bool
	{
		return $this->https;
	}


	/**
	 * @param bool $https
	 */
	public function setHttps(bool $https): void
	{
		$this->https = $https;
		$this->setUpdatedDate();
	}


	/**
	 * @return string
	 */
	public function getDomain(): string
	{
		return $this->domain;
	}


	/**
	 * @param string $domain
	 */
	public function setDomain(string $domain): void
	{
		$this->domain = $domain;
		$this->setUpdatedDate();
	}


	/**
	 * @return string
	 */
	public function getLocale(): string
	{
		return $this->locale->getLocale();
	}


	/**
	 * @param Locale $locale
	 */
	public function setLocale(Locale $locale): void
	{
		$this->locale = $locale;
		$this->setUpdatedDate();
	}


	/**
	 * @return bool
	 */
	public function isDefault(): bool
	{
		return $this->default;
	}


	/**
	 * @param bool $default
	 */
	public function setDefault(bool $default): void
	{
		$this->default = $default;
		$this->setUpdatedDate();
	}


	/**
	 * @return string
	 */
	public function getEnvironment(): string
	{
		return $this->environment;
	}


	/**
	 * @param string $environment
	 */
	public function setEnvironment(string $environment): void
	{
		$this->environment = $environment;
		$this->setUpdatedDate();
	}


	/**
	 * @return \DateTime
	 */
	public function getInsertedDate(): \DateTime
	{
		return $this->insertedDate;
	}


	/**
	 * @return \DateTime
	 */
	public function getUpdatedDate(): \DateTime
	{
		return $this->updatedDate;
	}


	private function setUpdatedDate(): void
	{
		$this->updatedDate = DateTime::from('now');
	}
}