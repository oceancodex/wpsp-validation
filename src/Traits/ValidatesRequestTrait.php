<?php

namespace WPSPCORE\Validation\Traits;

use Illuminate\Validation\ValidationException;
use WPSPCORE\Validation\Validation;

trait ValidatesRequestTrait {

	public function passes(array $rules, array $messages = [], array $customAttributes = []) {
		try {
			$this->validate($rules, $messages, $customAttributes);
			return true;
		}
		catch (ValidationException $e) {
			return false;
		}
	}

	public function fails(array $rules, array $messages = [], array $customAttributes = []) {
		return !$this->passes($rules, $messages, $customAttributes);
	}

	public function errors(array $rules, array $messages = [], array $customAttributes = []) {
		try {
			$this->validate($rules, $messages, $customAttributes);
			return [];
		}
		catch (ValidationException $e) {
			return $e->errors();
		}
	}

	/*
	 *
	 */

	public function validate(array $rules, array $messages = [], array $customAttributes = []) {
		$data = $this->all();

		return Validation::validate($data, $rules, $messages, $customAttributes);
	}

	public function validator(array $rules, array $messages = [], array $customAttributes = []) {
		$data = $this->all();

		return Validation::make($data, $rules, $messages, $customAttributes);
	}

	public function validated(array $rules, array $messages = [], array $customAttributes = []) {
		try {
			return $this->validate($rules, $messages, $customAttributes);
		}
		catch (ValidationException $e) {
			return null;
		}
	}

	public function validateOnly(array $keys, array $rules, array $messages = [], array $customAttributes = []) {
		$data = $this->only($keys);

		return Validation::validate($data, $rules, $messages, $customAttributes);
	}

	/*
	 *
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

	protected function only(array $keys) {
		$data = $this->all();
		return array_intersect_key($data, array_flip($keys));
	}

	protected function except(array $keys) {
		$data = $this->all();
		return array_diff_key($data, array_flip($keys));
	}

}