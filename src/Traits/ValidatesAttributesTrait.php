<?php

namespace WPSPCORE\Validation\Traits;

use WPSPCORE\Validation\Validation;

trait ValidatesAttributesTrait {

	/**
	 * Validation rules
	 *
	 * @var array
	 */
	protected $validationRules = [];

	/**
	 * Custom validation messages
	 *
	 * @var array
	 */
	protected $validationMessages = [];

	/**
	 * Custom attribute names
	 *
	 * @var array
	 */
	protected $validationCustomAttributes = [];

	/**
	 * Validate attributes before saving
	 *
	 * @param array $data
	 * @return array
	 * @throws \Illuminate\Validation\ValidationException
	 */
	public function validateAttributes(array $data) {
		if (empty($this->validationRules)) {
			return $data;
		}

		return Validation::validate(
			$data,
			$this->validationRules,
			$this->validationMessages,
			$this->validationCustomAttributes
		);
	}

	/**
	 * Get validation rules
	 *
	 * @return array
	 */
	public function getValidationRules() {
		return $this->validationRules;
	}

	/**
	 * Set validation rules
	 *
	 * @param array $rules
	 * @return $this
	 */
	public function setValidationRules(array $rules) {
		$this->validationRules = $rules;
		return $this;
	}

	/**
	 * Get validation messages
	 *
	 * @return array
	 */
	public function getValidationMessages() {
		return $this->validationMessages;
	}

	/**
	 * Set validation messages
	 *
	 * @param array $messages
	 * @return $this
	 */
	public function setValidationMessages(array $messages) {
		$this->validationMessages = $messages;
		return $this;
	}

	/**
	 * Boot trait - register model event to validate before saving
	 */
	public static function bootValidatesAttributes() {
		static::saving(function ($model) {
			if (!empty($model->validationRules)) {
				$model->validateAttributes($model->getAttributes());
			}
		});
	}

}