<?php

namespace WPSPCORE\Validation\Traits;

use WPSPCORE\Validation\Validation;

trait ValidatesAttributesTrait {

	protected $validationRules = [];

	protected $validationMessages = [];

	protected $validationCustomAttributes = [];

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

	public function getValidationRules() {
		return $this->validationRules;
	}

	public function setValidationRules(array $rules) {
		$this->validationRules = $rules;
		return $this;
	}

	public function getValidationMessages() {
		return $this->validationMessages;
	}

	public function setValidationMessages(array $messages) {
		$this->validationMessages = $messages;
		return $this;
	}

	public static function bootValidatesAttributes() {
		static::saving(function($model) {
			if (!empty($model->validationRules)) {
				$model->validateAttributes($model->getAttributes());
			}
		});
	}

}