<?php

declare(strict_types=1);

namespace Baraja\Localization;


final class Translation
{
	/** @var array<string, string> */
	private array $storage;

	/** @var array<string, string> */
	private array $startupState = [];


	/**
	 * @throws LocalizationException
	 */
	public function __construct(?string $data, ?string $language = null)
	{
		if ($data === null) {
			return;
		}
		if ($data === 'T:[]' || str_starts_with($data, 'T:{')) { // format T:{"locale":"haystack"}
			$data = str_replace(["\r\n", "\r"], "\n", $data);
			$normalize = str_replace("\n", '\n', $data);
			$json = (string) preg_replace('/^T:/', '', $normalize);
			$flags = JSON_BIGINT_AS_STRING;

			if (PHP_VERSION_ID < 70_000) {
				if ($json === '') {
					throw new LocalizationException('Syntax error:' . "\nJson: " . $json . "\n\nOriginal data:\n" . $data);
				}
				if (\defined('JSON_C_VERSION') && preg_match('##u', $json) !== 1) {
					throw new LocalizationException('Invalid UTF-8 sequence:' . "\n" . $data, 5);
				}
				if (\defined('JSON_C_VERSION') && PHP_INT_SIZE === 8) {
					$flags &= ~JSON_BIGINT_AS_STRING; // not implemented in PECL JSON-C 1.3.2 for 64bit systems
				}
			}

			/** @var array<string, string> $storageData */
			$storageData = json_decode($json, true, 512, JSON_THROW_ON_ERROR | $flags);
			if ($error = json_last_error()) {
				throw new LocalizationException(
					json_last_error_msg() . "\nJson: " . $json . "\n\nOriginal data:\n" . $data,
					$error,
				);
			}

			$this->storage = $storageData;
			$this->startupState = $storageData;
		} else { // back compatibility for pure string
			if ($language === null) {
				$language = PHP_SAPI === 'cli'
					? LocalizationHelper::getLocalization()->getDefaultLocale()
					: LocalizationHelper::getLocale(true);
			}
			$this->storage = [$language => $data];
		}
	}


	public function __toString(): string
	{
		return $this->getTranslation() ?? '';
	}


	/**
	 * Return best translation. If language is null, use current language by automatic detection.
	 */
	public function getTranslation(?string $language = null, bool $fallback = true): ?string
	{
		if (isset($this->storage) === false) {
			return null;
		}
		if ($language === null) {
			$language = LocalizationHelper::getLocale(true);
		}
		if (isset($this->storage[$language]) === true) {
			return $this->storage[$language];
		}
		if ($fallback === true) {
			$fallbackLanguages = LocalizationHelper::getFallbackLocales();
			foreach ($fallbackLanguages[$language] ?? [] as $fallbackLanguage) {
				if (isset($this->storage[$fallbackLanguage]) === true) {
					return $this->storage[$fallbackLanguage];
				}
			}

			return $this->storage[array_keys($this->storage)[0] ?? ''] ?? null;
		}

		return '#NO_DATA#';
	}


	public function addTranslate(?string $haystack, ?string $language = null): bool
	{
		if ($language === null) {
			$language = LocalizationHelper::getLocale(true);
		}
		if (isset($this->storage[$language]) === true) {
			if ($haystack === null) {
				unset($this->storage[$language]);
			} elseif ($this->storage[$language] === $haystack) {
				return false;
			}
		}
		if ($haystack !== null) {
			if (isset($this->storage) === false) {
				$this->storage = [];
			}
			$this->storage[$language] = $haystack;
		}

		return true;
	}


	/**
	 * Serialize translate object to save in database.
	 *
	 * @internal
	 */
	public function getSerialize(): string
	{
		return 'T:' . json_encode(
			$this->storage ?? [],
			JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
				| (\defined('JSON_PRESERVE_ZERO_FRACTION') ? JSON_PRESERVE_ZERO_FRACTION : 0),
		);
	}


	/**
	 * @return array<string, string>
	 */
	public function getStartupState(): array
	{
		return $this->startupState;
	}


	/**
	 * @return array<string, string>
	 */
	public function getStorage(): array
	{
		return $this->storage ?? [];
	}


	/**
	 * It detects if storage has been set up and exists.
	 * If it returns `false`, it is probably a corrupted translation string.
	 */
	public function isStorage(): bool
	{
		return isset($this->storage);
	}


	/**
	 * @internal
	 */
	public function regenerate(): self
	{
		return new self($this->getSerialize());
	}
}
