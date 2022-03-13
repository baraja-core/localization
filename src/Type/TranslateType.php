<?php

declare(strict_types=1);

namespace Baraja\Localization;


use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

final class TranslateType extends Type
{
	public const TRANSLATE_TYPE = 'translate';


	/**
	 * @param mixed[] $fieldDeclaration
	 */
	public function getSQLDeclaration(array $fieldDeclaration, AbstractPlatform $platform): string
	{
		return $platform->getClobTypeDeclarationSQL($fieldDeclaration);
	}


	/**
	 * @param string $value
	 * @throws LocalizationException
	 */
	public function convertToPHPValue($value, AbstractPlatform $platform): Translation
	{
		return new Translation($value);
	}


	public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
	{
		if ($value === null) {
			return null;
		}
		if ($value instanceof Translation) {
			return $value->getSerialize();
		}
		if (\is_string($value) === true) {
			return (new Translation($value))->getSerialize();
		}

		throw new LocalizationException(sprintf('Language data must be Translation entity, but type "%s" given.', get_debug_type($value)));
	}


	public function getName(): string
	{
		return self::TRANSLATE_TYPE;
	}


	public function requiresSQLCommentHint(AbstractPlatform $platform): bool
	{
		return true;
	}
}
