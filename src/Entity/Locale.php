<?php

declare(strict_types=1);

namespace Baraja\Localization;


use Baraja\Doctrine\UUID\UuidIdentifier;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\Mapping\Index;
use Nette\SmartObject;
use Nette\Utils\DateTime;

/**
 * @ORM\Entity()
 * @ORM\Table(
 *    name="core__localization_locale",
 *    indexes={
 *       @Index(name="locale__locale_id", columns={"locale", "id"})
 *    }
 * )
 */
class Locale
{
	use UuidIdentifier;
	use SmartObject;

	/**
	 * @var string
	 * @ORM\Column(type="string", unique=true, length=2)
	 */
	private $locale;

	/**
	 * @var bool
	 * @ORM\Column(type="boolean")
	 */
	private $active = true;

	/**
	 * @var bool
	 * @ORM\Column(type="boolean", name="`is_default`")
	 */
	private $default = false;

	/**
	 * @var int
	 * @ORM\Column(type="integer")
	 */
	private $position = 1;

	/**
	 * @var \DateTime
	 * @ORM\Column(type="datetime")
	 */
	private $insertedDate;

	/**
	 * @var Domain[]|Collection
	 * @ORM\OneToMany(targetEntity="Domain", mappedBy="locale")
	 */
	private $domains;


	/**
	 * @param string $locale
	 */
	public function __construct(string $locale)
	{
		$this->locale = strtolower($locale);
		$this->insertedDate = DateTime::from('now');
		$this->domains = new ArrayCollection;
	}


	/**
	 * @return string
	 */
	public function __toString(): string
	{
		return $this->getLocale();
	}


	/**
	 * @return string
	 */
	public function getLocale(): string
	{
		return $this->locale;
	}


	/**
	 * @return bool
	 */
	public function isActive(): bool
	{
		return $this->active;
	}


	/**
	 * @param bool $active
	 */
	public function setActive(bool $active = true): void
	{
		$this->active = $active;
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
	}


	/**
	 * @return int
	 */
	public function getPosition(): int
	{
		return $this->position;
	}


	/**
	 * @param int $position
	 */
	public function setPosition(int $position): void
	{
		$this->position = $position;
	}


	/**
	 * @return \DateTime
	 */
	public function getInsertedDate(): \DateTime
	{
		return $this->insertedDate;
	}
}