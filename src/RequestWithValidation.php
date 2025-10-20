<?php

namespace WPSPCORE\Validation;

use Symfony\Component\HttpFoundation\Request;
use WPSPCORE\Validation\Traits\ValidatesRequestTrait;

class RequestWithValidation extends Request {

	use ValidatesRequestTrait;

	public $validation;

	public function setValidation($validation = null) {
		$this->validation = $validation;
		return $this;
	}

	public function getValidation() {
		return $this->validation;
	}

}