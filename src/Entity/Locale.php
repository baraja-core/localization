<?php

declare(strict_types=1);

namespace Baraja\Localization;


use Baraja\Doctrine\UUID\UuidIdentifier;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\Mapping\Index;

#[ORM\Entity]
#[ORM\Table(name: 'core__localization_locale')]
#[Index(columns: ['locale', 'id'], name: 'locale__locale_id')]
#[Index(columns: ['active'], name: 'locale__active')]
class Locale implements \Stringable
{
	use UuidIdentifier;

	#[ORM\Column(type: 'string', length: 2, unique: true)]
	private string $locale;

	#[ORM\Column(type: 'boolean')]
	private bool $active = true;

	#[ORM\Column(name: 'is_default', type: 'boolean')]
	private bool $default = false;

	#[ORM\Column(type: 'smallint')]
	private int $position = 1;

	#[ORM\Column(type: 'datetime_immutable')]
	private \DateTimeImmutable $insertedDate;

	/** @var Domain[]|Collection */
	#[ORM\OneToMany(mappedBy: 'locale', targetEntity: Domain::class)]
	private $domains;

	#[ORM\Column(type: 'string', length: 64, nullable: true)]
	private ?string $titleSuffix;

	#[ORM\Column(type: 'string', length: 8, nullable: true)]
	private ?string $titleSeparator;

	#[ORM\Column(type: 'string', length: 64, nullable: true)]
	private ?string $titleFormat;

	#[ORM\Column(type: 'string', length: 64, nullable: true)]
	private ?string $siteName;


	public function __construct(string $locale)
	{
		$this->locale = Localization::normalize($locale);
		$this->insertedDate = new \DateTimeImmutable('now');
		$this->domains = new ArrayCollection;
	}


	public function __toString(): string
	{
		return $this->getLocale();
	}


	public function getLocale(): string
	{
		return $this->locale;
	}


	public function isActive(): bool
	{
		return $this->active;
	}


	public function setActive(bool $active = true): void
	{
		$this->active = $active;
	}


	public function isDefault(): bool
	{
		return $this->default;
	}


	public function setDefault(bool $default): void
	{
		$this->default = $default;
	}


	public function getPosition(): int
	{
		return $this->position;
	}


	public function setPosition(int $position): void
	{
		if ($position < 0) {
			$position = 0;
		}
		if ($position > 32_767) {
			$position = 32_767;
		}

		$this->position = $position;
	}


	public function getInsertedDate(): \DateTimeImmutable
	{
		return $this->insertedDate;
	}


	public function getTitleSuffix(): ?string
	{
		return $this->titleSuffix;
	}


	public function setTitleSuffix(?string $titleSuffix): void
	{
		if ($titleSuffix !== null && mb_strlen($titleSuffix, 'UTF-8') > 64) {
			throw new \InvalidArgumentException(sprintf(
				'The maximum length of the title suffix is 64 characters, but "%s" given.',
				$titleSuffix,
			));
		}

		$this->titleSuffix = trim($titleSuffix ?? '') ?: null;
	}


	public function getTitleSeparator(): ?string
	{
		return $this->titleSeparator;
	}


	public function setTitleSeparator(?string $titleSeparator): void
	{
		if ($titleSeparator !== null && mb_strlen($titleSeparator, 'UTF-8') > 8) {
			throw new \InvalidArgumentException(sprintf(
				'The maximum length of the title separator is 8 characters, but "%s" given.',
				$titleSeparator,
			));
		}

		$this->titleSeparator = trim($titleSeparator ?? '') ?: null;
	}


	public function getTitleFormat(): ?string
	{
		return $this->titleFormat;
	}


	public function setTitleFormat(?string $titleFormat): void
	{
		if ($titleFormat !== null && mb_strlen($titleFormat, 'UTF-8') > 64) {
			throw new \InvalidArgumentException(sprintf(
				'The maximum length of the title format is 64 characters, but "%s" given.',
				$titleFormat,
			));
		}

		$this->titleFormat = trim($titleFormat ?? '') ?: null;
	}


	public function getSiteName(): ?string
	{
		return $this->siteName;
	}


	public function setSiteName(?string $siteName): void
	{
		if ($siteName !== null && mb_strlen($siteName, 'UTF-8') > 64) {
			throw new \InvalidArgumentException(sprintf(
				'The maximum length of the site name is 64 characters, but "%s" given.',
				$siteName,
			));
		}

		$this->siteName = trim($siteName ?? '') ?: null;
	}


	/**
	 * @return Domain[]|Collection
	 */
	public function getDomains()
	{
		return $this->domains;
	}
}
