<?php

namespace WPSPCORE\Validation;

use Illuminate\Validation\ValidationException;
use WPSPCORE\Base\BaseInstances;

class Handler extends BaseInstances {

	public $dontReport = [];
	public $dontFlash  = [
		'current_password',
		'password',
		'password_confirmation',
	];

	protected $ignitionHandler = null;

	/*
	 *
	 */

	public function __construct($mainPath = null, $rootNamespace = null, $prefixEnv = null, $extraParams = []) {
		parent::__construct($mainPath, $rootNamespace, $prefixEnv, $extraParams);
		$this->ignitionHandler = $extraParams['ignition_handler'] ?? null;
	}

	/*
	 *
	 */

	public function render(\Throwable $e) {
		// Kiểm tra xem exception có method render() không
		if (method_exists($e, 'render')) {
			try {
				$result = $e->render();

				// Nếu render() trả về giá trị hoặc đã echo, return
				if ($result !== null) {
					echo $result;
					exit;
				}

				// Nếu render() đã echo và exit, code sẽ không chạy đến đây
				return;
			}
			catch (\Throwable $renderException) {
				// Nếu render() gặp lỗi, fallback sang Ignition
				$this->fallbackToIgnition($e);
			}
		}
	}

	public function report(\Throwable $e) {
		if ($this->shouldntReport($e)) {
			return;
		}

		if (method_exists($e, 'report')) {
			return $e->report();
		}
	}

	/*
	 *
	 */

	public function register() {
		//
	}

	public function shouldReport(\Throwable $e) {
		return !$this->shouldntReport($e);
	}

	public function shouldntReport(\Throwable $e) {
		foreach ($this->dontReport as $type) {
			if ($e instanceof $type) {
				return true;
			}
		}

		return false;
	}

	public function shouldReturnJson() {
		return $this->funcs->_shouldReturnJson();
	}

	public function wantJson() {
		return $this->shouldReturnJson();
	}

	public function prepareResponse(\Throwable $e) {
		if ($this->shouldReturnJson()) {
			$this->prepareJsonResponse($e);
			exit;
		}

		$this->redirectBack(['error' => 'exception']);
		exit;
	}

	public function prepareJsonResponse(\Throwable $e) {
		$data = ['message' => $e->getMessage()];

		if ($this->funcs->env('APP_DEBUG', true) == 'true') {
			$data['exception'] = get_class($e);
			$data['file']      = $e->getFile();
			$data['line']      = $e->getLine();
			$data['trace']     = $e->getTrace();
		}

		wp_send_json($data, 500);
		exit;
	}

	public function redirectBack($params = []) {
		$redirectUrl = wp_get_raw_referer() ?: admin_url();

		foreach ($params as $key => $value) {
			$redirectUrl = add_query_arg($key, $value, $redirectUrl);
		}

		wp_safe_redirect($redirectUrl);
		exit;
	}

	public function fallbackToIgnition(\Throwable $e) {
		if ($this->ignitionHandler && is_callable($this->ignitionHandler)) {
			call_user_func($this->ignitionHandler, $e);
		}
		else {
			// Nếu không có Ignition handler, hiển thị lỗi đơn giản
			$this->prepareResponse($e);
		}
	}

	/*
	 *
	 */

	protected function handleValidationException(ValidationException $e) {
		status_header(422);

		/**
		 * Với request AJAX hoặc REST API.
		 */
		if ($this->wantJson()) {
			wp_send_json([
				'success' => false,
				'data'    => null,
				'errors'  => $e->validator->errors()->messages(),
				'message' => $e->getMessage(),
			], 422);
			exit;
		}

		/**
		 * Với request thông thường.
		 */

		// Debug mode.
		if ($this->funcs->_isDebug()) {
			$this->fallbackToIgnition($e);
		}

		// Production mode.
		else {
			// Lấy danh sách lỗi.
			$errors = $e->validator->errors()->all();

			// Tạo danh sách lỗi HTML.
			$errorList = '<ul>';
			foreach ($errors as $error) {
				$errorList .= '<li>' . $error . '</li>';
			}
			$errorList .= '</ul>';

			// Sử dụng view.
			try {
				echo $this->funcs->view('errors.default', [
					'message'      => 'Vui lòng kiểm tra lại dữ liệu bên dưới:',
					'code'         => 422,
					'errorMessage' => $errorList,
					'status'       => 'Dữ liệu không hợp lệ',
				]);
				exit;
			}
			catch (\Throwable $viewException) {
			}

			// Sử dụng wp_die.
			wp_die(
				'<h1>ERROR: 422 - Dữ liệu không hợp lệ</h1><p>' . $errorList . '</p>',
				'ERROR: 422 - Dữ liệu không hợp lệ',
				[
					'response'  => 422,
					'back_link' => true,
				]
			);
		}
	}

	protected function handleQueryException(\Throwable $e) {
		status_header(500);

		global $wpdb;

		$message  = $e->getMessage();
		$sql      = method_exists($e, 'getSql') ? $e->getSql() : null;
		$bindings = method_exists($e, 'getBindings') ? $e->getBindings() : [];

		/**
		 * Với request AJAX hoặc REST API.
		 */
		if ($this->wantJson()) {

			// Debug mode.
			if ($this->funcs->isDebug()) {
				wp_send_json([
					'success' => false,
					'data'    => null,
					'errors'  => [
						[
							'type'     => 'QueryException',
							'sql'      => $sql,
							'bindings' => $bindings,
							'error'    => $wpdb->last_error ?? null,
						],
					],
					'message' => $message,
				], 500);
			}

			// Production mode.
			else {
				wp_send_json([
					'success' => false,
					'data'    => null,
					'errors'  => [
						[
							'type'  => 'QueryException',
							'error' => $wpdb->last_error ?? null,
						],
					],
					'message' => $message,
				], 500);
			}

			exit;
		}

		/**
		 * Với request thông thường.
		 */

		// Debug mode.
		if ($this->funcs->isDebug()) {
			// Sử dụng view.
			try {
				echo $this->funcs->view('errors.query', [
					'message'  => $message,
					'sql'      => $sql ?? null,
					'bindings' => $bindings ?? [],
					'error'    => $wpdb->last_error ?? null,
				]);
				exit;
			}
			catch (\Throwable $viewException) {
			}

			// Sử dụng wp_die.
			wp_die(
				'<h1>ERROR: 500 - Lỗi truy vấn cơ sở dữ liệu</h1><p>' . $message . '</p>',
				'ERROR: 500 - Lỗi truy vấn cơ sở dữ liệu',
				[
					'response'  => 500,
					'back_link' => true,
				]
			);
		}

		// Production mode.
		else {
			// Sử dụng view.
			try {
				echo $this->funcs->view('errors.query', [
					'message' => $message,
					'error'   => $wpdb->last_error ?? null,
				]);
				exit;
			}
			catch (\Throwable $viewException) {
			}

			// Sử dụng wp_die.
			wp_die(
				'<h1>ERROR: 500 - Lỗi truy vấn cơ sở dữ liệu</h1><p>' . $message . '</p>',
				'ERROR: 500 - Lỗi truy vấn cơ sở dữ liệu',
				[
					'response'  => 500,
					'back_link' => true,
				]
			);
		}
	}

	protected function handleModelNotFoundException(\Throwable $e) {
		status_header(404);

		$message   = $e->getMessage();
		$modelName = method_exists($e, 'getModelName') ? $e->getModelName() : null;

		/**
		 * Với request AJAX hoặc REST API.
		 */
		if ($this->wantJson()) {
			wp_send_json([
				'success' => false,
				'data'    => null,
				'errors'  => [
					[
						'type' => 'ModelNotFoundException',
						'model' => $modelName,
					]
				],
				'message' => $message,
			], 404);
			exit;
		}

		/**
		 * Với request thông thường.
		 */

		// Sử dụng view.
		try {
			echo $this->funcs->view('errors.model-not-found', [
				'message' => $message,
				'model'   => $modelName,
			]);
			exit;
		}
		catch (\Throwable $viewException) {
		}

		// Sử dụng wp_die.
		wp_die(
			'<h1>ERROR: 404 - Không tìm thấy bản ghi</h1><p>' . esc_html($message) . '</p>',
			'ERROR: 404 - Không tìm thấy bản ghi',
			[
				'response'  => 404,
				'back_link' => true,
			]
		);
	}

	protected function handleAuthorizationException(\Throwable $e) {
		status_header(403);

		$message = $e->getMessage();

		/**
		 * Với request AJAX hoặc REST API.
		 */
		if ($this->wantJson()) {
			wp_send_json([
				'success' => false,
				'data'    => null,
				'errors'  => [
					[
						'type' => 'AuthorizationException',
					],
				],
				'message' => $message,
			], 403);
			exit;
		}

		/**
		 * Với request thông thường.
		 */

		// Sử dụng view.
		try {
			echo $this->funcs->view('errors.403', [
				'message' => $message,
			]);
			exit;
		}
		catch (\Throwable $viewException) {
		}

		// Sử dụng wp_die.
		wp_die(
			'<h1>ERROR: 403 - Truy cập bị từ chối</h1><p>' . $message . '</p>',
			'ERROR: 403 - Truy cập bị từ chối',
			[
				'response'  => 403,
				'back_link' => true,
			]
		);
	}

	protected function handleAuthenticationException(\Throwable $e) {
		status_header(401);

		$message    = $e->getMessage();
		$guards     = method_exists($e, 'guards') ? $e->guards() : [];
		$redirectTo = method_exists($e, 'redirectTo') ? $e->redirectTo() : null;

		/**
		 * Với request AJAX hoặc REST API.
		 */

		if ($this->wantJson()) {
			wp_send_json([
				'success' => false,
				'data'    => null,
				'errors'  => [
					[
						'type'   => 'AuthenticationException',
						'guards' => $guards,
					],
				],
				'message' => $message,
			], 401);
			exit;
		}

		/**
		 * Với request thông thường.
		 */

		// Redirect.
		if ($redirectTo) {
			wp_redirect($redirectTo);
			exit;
		}

		// Sử dụng view.
		try {
			echo $this->funcs->view('errors.401', [
				'message' => $message,
			]);
			exit;
		}
		catch (\Throwable $viewException) {
		}

		// Sử dụng wp_die.
		wp_die(
			'<h1>ERROR: 401 - Chưa xác thực</h1><p>' . $message . '</p>',
			'ERROR: 401 - Chưa xác thực',
			[
				'response'  => 401,
				'back_link' => true,
			]
		);
	}

	protected function handleHttpException(\Throwable $e) {
		$statusCode = method_exists($e, 'getStatusCode') ? $e->getStatusCode() : 500;
		$message    = $e->getMessage();
		$headers    = method_exists($e, 'getHeaders') ? $e->getHeaders() : [];

		status_header($statusCode);

		// Set headers bổ sung.
		foreach ($headers as $key => $value) {
			if (!headers_sent()) {
				header("{$key}: {$value}");
			}
		}

		/**
		 * Với request AJAX hoặc REST API.
		 */

		if ($this->wantJson()) {
			wp_send_json([
				'success' => false,
				'data'    => null,
				'errors'  => [
					[
						'type' => 'HttpException',
					],
				],
				'message' => $message,
			], $statusCode);
			exit;
		}

		/**
		 * Với request thông thường.
		 */

		// Sử dụng view.
		try {
			$viewName     = "errors.{$statusCode}";
			$viewInstance = $this->funcs->_viewInstance();

			if ($viewInstance->exists($viewName)) {
				echo $this->funcs->view($viewName, [
					'message' => $message,
					'code'    => $statusCode,
					'status'  => 'Lỗi HTTP',
				]);
				exit;
			}

			if ($viewInstance->exists('errors.default')) {
				echo $this->funcs->view('errors.default', [
					'message' => $message,
					'code'    => $statusCode,
					'status'  => 'Lỗi HTTP',
				]);
				exit;
			}
		}
		catch (\Throwable $viewException) {
		}

		// Sử dụng wp_die.
		wp_die(
			'<h1>ERROR: ' . $statusCode . ' - Lỗi HTTP</h1><p>' . $message . '</p>',
			'ERROR: ' . $statusCode . ' - Lỗi HTTP',
			[
				'response'  => $statusCode,
				'back_link' => true,
			]
		);
	}

}