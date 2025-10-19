<?php

namespace WPSPCORE\Validation;

use Illuminate\Container\Container;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Translation\FileLoader;
use Illuminate\Translation\Translator;
use Illuminate\Validation\DatabasePresenceVerifier;
use Illuminate\Validation\Factory;
use WPSPCORE\Base\BaseInstances;

class Validation extends BaseInstances {

	/** @var Factory|null */
	protected static $factory = null;

	/** @var Translator|null */
	protected static $translator = null;

	/** @var \WPSPCORE\Database\Eloquent|null */
	protected static $eloquent = null;

	protected static $langPaths = [];

	public static function setLangPaths($paths) {
		self::$langPaths = (array) $paths;

		// Reset translator to reload with new paths
		self::$translator = null;
		self::$factory = null;
	}

	public static function init() {
		if (!self::$factory) {
			// Setup translator
			self::setupTranslator();

			// Create validation factory
			self::$factory = new Factory(self::$translator, Container::getInstance());

			// Setup database presence verifier for exists/unique rules
			self::setupPresenceVerifier();
		}
	}

	protected static function setupTranslator() {
		if (!self::$translator) {
			// Use custom lang paths if set, otherwise fallback
			$langPaths = !empty(self::$langPaths) ? self::$langPaths : [
				__DIR__ . '/../lang',
			];

			$loader = new FileLoader(new Filesystem(), $langPaths);

			// Get current locale from WordPress or config
			$locale = function_exists('get_locale') ? get_locale() : 'en';

			self::$translator = new Translator($loader, $locale);
		}
	}

	protected static function setupPresenceVerifier() {
		if (self::$eloquent && self::$eloquent->getCapsule()) {
			$db = self::$eloquent->getCapsule()->getDatabaseManager();
			$presenceVerifier = new DatabasePresenceVerifier($db);
			self::$factory->setPresenceVerifier($presenceVerifier);
		}
	}

	public static function setEloquentForPresenceVerifier($eloquent) {
		self::$eloquent = $eloquent;

		// Reinitialize if factory already exists
		if (self::$factory && $eloquent && $eloquent->getCapsule()) {
			$db = $eloquent->getCapsule()->getDatabaseManager();
			$presenceVerifier = new DatabasePresenceVerifier($db);
			self::$factory->setPresenceVerifier($presenceVerifier);
		}
	}

	public static function make(array $data, array $rules, array $messages = [], array $customAttributes = []) {
		self::init();
		return self::$factory->make($data, $rules, $messages, $customAttributes);
	}

	public static function validate(array $data, array $rules, array $messages = [], array $customAttributes = []) {
		$validator = self::make($data, $rules, $messages, $customAttributes);
		return $validator->validate();
	}

	public static function factory() {
		self::init();
		return self::$factory;
	}

	public static function extend($rule, $extension, $message = null) {
		self::init();
		self::$factory->extend($rule, $extension, $message);
	}

	public static function extendImplicit($rule, $extension, $message = null) {
		self::init();
		self::$factory->extendImplicit($rule, $extension, $message);
	}

	public static function replacer($rule, $replacer) {
		self::init();
		self::$factory->replacer($rule, $replacer);
	}

}