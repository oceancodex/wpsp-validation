<?php

namespace WPSPCORE\Validation;

use Symfony\Component\HttpFoundation\Request;
use WPSPCORE\Validation\Traits\ValidatesRequestTrait;

class RequestWithValidation extends Request {

	use ValidatesRequestTrait;

	public static function capture() {
		return static::createFromGlobals();
	}

}