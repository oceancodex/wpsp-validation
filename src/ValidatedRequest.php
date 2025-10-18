<?php

namespace WPSPCORE\Validation;

use Symfony\Component\HttpFoundation\Request;
use WPSPCORE\Validation\Traits\ValidatesRequestTrait;

class ValidatedRequest extends Request {

	use ValidatesRequestTrait;

	/**
	 * Create validated request from globals
	 *
	 * @return static
	 */
	public static function capture() {
		return static::createFromGlobals();
	}

}