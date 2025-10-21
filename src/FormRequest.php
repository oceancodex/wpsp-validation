<?php

namespace WPSPCORE\Validation;

use WPSPCORE\Base\BaseInstances;

/**
 * @property \WPSPCORE\Validation\Validation $validation
 */
abstract class FormRequest extends BaseInstances {

	public $data          = [];
	public $validatedData = [];
	public $validation;

	/*
	 *
	 */

	abstract public function rules();

	/*
	 *
	 */

	public function collectData() {
		return array_merge(
			$_GET ?? [],
			$_POST ?? [],
			$_FILES ?? []
		);
	}

	public function prepareForValidation() {
		// Override trong subclass nếu cần
	}

	/*
	 *
	 */

	public function messages() {
		return [];
	}

	public function attributes() {
		return [];
	}

	public function authorize() {
		return true;
	}

	public function validate() {
		if (!$this->authorize()) {
			throw new \Exception('This action is unauthorized.');
		}

		$this->validatedData = $this->validation->validate(
			$this->data,
			$this->rules(),
			$this->messages(),
			$this->attributes()
		);

		return $this->validatedData;
	}

	public function validated($key = null, $default = null) {
		if (empty($this->validatedData)) {
			$this->validate();
		}

		if ($key === null) {
			return $this->validatedData;
		}

		return $this->validatedData[$key] ?? $default;
	}

	public function safe() {
		return $this->validated();
	}

	public function input($key, $default = null) {
		return $this->data[$key] ?? $default;
	}

	public function all() {
		return $this->data;
	}

	public function only($keys) {
		return array_intersect_key($this->data, array_flip($keys));
	}

	public function except($keys) {
		return array_diff_key($this->data, array_flip($keys));
	}

	public function has($key) {
		return isset($this->data[$key]);
	}

	public function filled($key) {
		return $this->has($key) && !empty($this->data[$key]);
	}

	public function missing($key) {
		return !$this->has($key);
	}

	public function merge($data) {
		$this->data = array_merge($this->data, $data);
	}

	public function replace($data) {
		$this->data = $data;
	}

}