<?php

declare(strict_types=1);

namespace Baraja\Localization\Bridge;


use Baraja\Localization\Localization;
use Contributte;
use Contributte\Translation\LocalesResolvers\ResolverInterface;

final class LocaleResolver implements ResolverInterface
{
	public function __construct(
		private Localization $localization,
		private ?string $contextLocale = null,
	) {
	}


	public function resolve(Contributte\Translation\Translator $translator): ?string
	{
		if ($this->contextLocale !== null) {
			return $this->contextLocale;
		}
		try {
			return $this->localization->getLocale();
		} catch (\Throwable) {
		}

		return null;
	}


	public function getContextLocale(): ?string
	{
		return $this->contextLocale;
	}


	public function setContextLocale(?string $contextLocale): void
	{
		$this->contextLocale = $contextLocale;
	}
}
