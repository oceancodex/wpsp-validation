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

	/** @var array */
	protected static $langPaths = [];

	public static function setLangPaths($paths) {
		self::$langPaths = (array) $paths;

		// Reset translator to reload with new paths
		self::$translator = null;
		self::$factory = null;
	}

	/**
	 * Initialize Validation Factory
	 */
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

	/**
	 * Setup Translator for validation messages
	 */
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

	/**
	 * Setup Database Presence Verifier for validation rules like exists, unique
	 */
	protected static function setupPresenceVerifier() {
		if (self::$eloquent && self::$eloquent->getCapsule()) {
			$db = self::$eloquent->getCapsule()->getDatabaseManager();
			$presenceVerifier = new DatabasePresenceVerifier($db);
			self::$factory->setPresenceVerifier($presenceVerifier);
		}
	}

	/**
	 * Set Eloquent instance for database validation rules
	 *
	 * @param \WPSPCORE\Database\Eloquent $eloquent
	 */
	public static function setEloquentForPresenceVerifier($eloquent) {
		self::$eloquent = $eloquent;

		// Reinitialize if factory already exists
		if (self::$factory && $eloquent && $eloquent->getCapsule()) {
			$db = $eloquent->getCapsule()->getDatabaseManager();
			$presenceVerifier = new DatabasePresenceVerifier($db);
			self::$factory->setPresenceVerifier($presenceVerifier);
		}
	}

	/**
	 * Create a new Validator instance
	 *
	 * @param array $data
	 * @param array $rules
	 * @param array $messages
	 * @param array $customAttributes
	 * @return \Illuminate\Validation\Validator
	 */
	public static function make(array $data, array $rules, array $messages = [], array $customAttributes = []) {
		self::init();
		return self::$factory->make($data, $rules, $messages, $customAttributes);
	}

	/**
	 * Validate data and return validated data or throw exception
	 *
	 * @param array $data
	 * @param array $rules
	 * @param array $messages
	 * @param array $customAttributes
	 * @return array
	 * @throws \Illuminate\Validation\ValidationException
	 */
	public static function validate(array $data, array $rules, array $messages = [], array $customAttributes = []) {
		$validator = self::make($data, $rules, $messages, $customAttributes);
		return $validator->validate();
	}

	/**
	 * Get the validation factory instance
	 *
	 * @return Factory
	 */
	public static function factory() {
		self::init();
		return self::$factory;
	}

	/**
	 * Extend validator with custom rules
	 *
	 * @param string $rule
	 * @param \Closure|string $extension
	 * @param string|null $message
	 * @return void
	 */
	public static function extend($rule, $extension, $message = null) {
		self::init();
		self::$factory->extend($rule, $extension, $message);
	}

	/**
	 * Extend validator with implicit rules
	 *
	 * @param string $rule
	 * @param \Closure|string $extension
	 * @param string|null $message
	 * @return void
	 */
	public static function extendImplicit($rule, $extension, $message = null) {
		self::init();
		self::$factory->extendImplicit($rule, $extension, $message);
	}

	/**
	 * Register custom replacer for validation messages
	 *
	 * @param string $rule
	 * @param \Closure|string $replacer
	 * @return void
	 */
	public static function replacer($rule, $replacer) {
		self::init();
		self::$factory->replacer($rule, $replacer);
	}

}