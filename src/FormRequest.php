<?php

namespace WPSPCORE\Validation;

use WPSPCORE\Base\BaseInstances;

/**
 * @property \WPSPCORE\Validation\Validation $validation
 */
abstract class FormRequest extends BaseInstances {

	protected $data          = [];
	protected $validatedData = [];
	protected $validation;

	/*
	 *
	 */

	abstract public function rules(): array;

	/*
	 *
	 */

	protected function collectData(): array {
		return array_merge(
			$_GET ?? [],
			$_POST ?? [],
			$_FILES ?? []
		);
	}

	protected function prepareForValidation(): void {
		// Override trong subclass nếu cần
	}

	/*
	 *
	 */

	public function messages(): array {
		return [];
	}

	public function attributes(): array {
		return [];
	}

	public function authorize(): bool {
		return true;
	}

	public function validate(): array {
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

	public function safe(): array {
		return $this->validated();
	}

	public function input(string $key, $default = null) {
		return $this->data[$key] ?? $default;
	}

	public function all(): array {
		return $this->data;
	}

	public function only(array $keys): array {
		return array_intersect_key($this->data, array_flip($keys));
	}

	public function except(array $keys): array {
		return array_diff_key($this->data, array_flip($keys));
	}

	public function has(string $key): bool {
		return isset($this->data[$key]);
	}

	public function filled(string $key): bool {
		return $this->has($key) && !empty($this->data[$key]);
	}

	public function missing(string $key): bool {
		return !$this->has($key);
	}

	public function merge(array $data): void {
		$this->data = array_merge($this->data, $data);
	}

	public function replace(array $data): void {
		$this->data = $data;
	}

}