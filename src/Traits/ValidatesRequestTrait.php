<?php

namespace WPSPCORE\Validation\Traits;

use Illuminate\Validation\ValidationException;
use WPSPCORE\Validation\Validation;

trait ValidatesRequestTrait {

	/**
	 * Validate the given request with the given rules.
	 *
	 * @param array $rules
	 * @param array $messages
	 * @param array $customAttributes
	 * @return array
	 * @throws \Illuminate\Validation\ValidationException
	 */
	public function validate(array $rules, array $messages = [], array $customAttributes = []) {
		$data = $this->all();

		return Validation::validate($data, $rules, $messages, $customAttributes);
	}

	/**
	 * Validate the given request with the given rules and return validator instance.
	 *
	 * @param array $rules
	 * @param array $messages
	 * @param array $customAttributes
	 * @return \Illuminate\Validation\Validator
	 */
	public function validator(array $rules, array $messages = [], array $customAttributes = []) {
		$data = $this->all();

		return Validation::make($data, $rules, $messages, $customAttributes);
	}

	/**
	 * Validate the given request and return validated data or default on failure.
	 *
	 * @param array $rules
	 * @param array $messages
	 * @param array $customAttributes
	 * @return array|null
	 */
	public function validated(array $rules, array $messages = [], array $customAttributes = []) {
		try {
			return $this->validate($rules, $messages, $customAttributes);
		} catch (ValidationException $e) {
			return null;
		}
	}

	/**
	 * Validate a subset of request data.
	 *
	 * @param array $keys
	 * @param array $rules
	 * @param array $messages
	 * @param array $customAttributes
	 * @return array
	 * @throws \Illuminate\Validation\ValidationException
	 */
	public function validateOnly(array $keys, array $rules, array $messages = [], array $customAttributes = []) {
		$data = $this->only($keys);

		return Validation::validate($data, $rules, $messages, $customAttributes);
	}

	/**
	 * Get all request data as array
	 *
	 * @return array
	 */
	protected function all() {
		$data = array_merge(
			$this->query->all(),
			$this->request->all(),
			$this->files->all()
		);

		// For WP_REST_Request compatibility
		if (method_exists($this, 'get_params')) {
			$data = array_merge($data, $this->get_params());
		}

		return $data;
	}

	/**
	 * Get only specified keys from request
	 *
	 * @param array $keys
	 * @return array
	 */
	protected function only(array $keys) {
		$data = $this->all();
		return array_intersect_key($data, array_flip($keys));
	}

	/**
	 * Get all except specified keys from request
	 *
	 * @param array $keys
	 * @return array
	 */
	protected function except(array $keys) {
		$data = $this->all();
		return array_diff_key($data, array_flip($keys));
	}

	/**
	 * Check if validation passes
	 *
	 * @param array $rules
	 * @param array $messages
	 * @param array $customAttributes
	 * @return bool
	 */
	public function passes(array $rules, array $messages = [], array $customAttributes = []) {
		try {
			$this->validate($rules, $messages, $customAttributes);
			return true;
		} catch (ValidationException $e) {
			return false;
		}
	}

	/**
	 * Check if validation fails
	 *
	 * @param array $rules
	 * @param array $messages
	 * @param array $customAttributes
	 * @return bool
	 */
	public function fails(array $rules, array $messages = [], array $customAttributes = []) {
		return !$this->passes($rules, $messages, $customAttributes);
	}

	/**
	 * Get validation errors from the last validation attempt
	 *
	 * @param array $rules
	 * @param array $messages
	 * @param array $customAttributes
	 * @return array
	 */
	public function errors(array $rules, array $messages = [], array $customAttributes = []) {
		try {
			$this->validate($rules, $messages, $customAttributes);
			return [];
		} catch (ValidationException $e) {
			return $e->errors();
		}
	}

}