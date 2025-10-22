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

	public function invalidJson(ValidationException $e) {
		wp_send_json([
			'message' => $e->getMessage(),
			'errors'  => $e->validator->errors()->messages(),
		], 422);
		exit;
	}

	public function shouldReturnJson() {
		return wp_doing_ajax() ||
			(defined('REST_REQUEST') && REST_REQUEST) ||
			(!empty($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false);
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
		if ($this->shouldReturnJson()) {
			$this->invalidJson($e);
			exit;
		}

		/**
		 * Với request thông thường.
		 */

		if ($this->funcs->env('APP_DEBUG', true) !== 'true') {
			$this->fallbackToIgnition($e);
		}
		else {
			$errors = $e->validator->errors()->all();
			$error_list = '<ul>';
			foreach ($errors as $error) {
				$error_list .= '<li>' . esc_html($error) . '</li>';
			}
			$error_list .= '</ul>';

			// Sử dụng view.
			try {
				$viewInstance = $this->funcs->_viewInstance();
				if ($viewInstance->exists('errors.default')) {
					status_header(422);
					echo $this->funcs->view('errors.default', [
						'message' => $error_list,
						'code' => 422,
						'status' => 'Dữ liệu không hợp lệ',
					]);
					exit;
				}
			}
			catch (\Throwable $viewException) {}

			// Sử dụng wp_die.
			wp_die(
				'<h1>ERROR: 422 - Dữ liệu không hợp lệ</h1><p>' . $error_list . '</p>',
				'422 - Dữ liệu không hợp lệ',
				[
					'response'  => 422,
					'back_link' => true,
					'html' => true
				]
			);
		}
	}

	protected function handleQueryException(\Throwable $e) {
		$message = $e->getMessage();
		$sql = method_exists($e, 'getSql') ? $e->getSql() : null;
		$bindings = method_exists($e, 'getBindings') ? $e->getBindings() : [];

		/**
		 * Với request AJAX hoặc REST API.
		 */
		if ($this->shouldReturnJson()) {
			status_header(500);

			$response = [
				'success' => false,
				'error' => [
					'type' => 'QueryException',
					'message' => $message,
				],
			];

			// Chỉ hiển thị chi tiết SQL khi debug mode
			if ($this->funcs->env('APP_DEBUG', true) == 'true') {
				$response['error']['sql'] = $sql;
				$response['error']['bindings'] = $bindings;
			}

			wp_send_json($response, 500);
			exit;
		}

		/**
		 * Với request thông thường.
		 */

		// Debug mode.
		if ($this->funcs->env('APP_DEBUG', true) == 'true') {
			echo '<div style="background:white;padding:20px;border:2px solid #d63638;margin:20px;font-family:monospace;">';
			echo '<h2 style="color:#d63638;">Database Query Error</h2>';
			echo '<p><strong>Message:</strong> ' . esc_html($message) . '</p>';
			if ($sql) {
				echo '<p><strong>SQL:</strong><br><code style="background:#f0f0f1;padding:10px;display:block;overflow-x:auto;">' . esc_html($sql) . '</code></p>';
			}
			if (!empty($bindings)) {
				echo '<p><strong>Bindings:</strong><br><pre style="background:#f0f0f1;padding:10px;overflow-x:auto;">' . esc_html(print_r($bindings, true)) . '</pre></p>';
			}
			echo '</div>';
			exit;
		}

		// Production mode.
		wp_die(
			'<h1>Lỗi cơ sở dữ liệu</h1><p>Đã xảy ra lỗi khi truy vấn cơ sở dữ liệu. Vui lòng thử lại sau.</p>',
			'500 - Database Error',
			[
				'response' => 500,
				'back_link' => true,
			]
		);
	}

	protected function handleModelNotFoundException(\Throwable $e) {
		$modelName = method_exists($e, 'getModelName') ? $e->getModelName() : null;
		$message   = $e->getMessage();

		/**
		 * Với request AJAX hoặc REST API.
		 */
		if ($this->shouldReturnJson()) {
			status_header(404);
			wp_send_json([
				'success' => false,
				'error'   => [
					'type'    => 'ModelNotFoundException',
					'message' => $message,
					'model'   => $modelName,
				],
			], 404);
			exit;
		}

		/**
		 * Với request thông thường.
		 */

		// Sử dụng view.
		try {
			$viewInstance = $this->funcs->_viewInstance();
			if ($viewInstance->exists('errors.model-not-found')) {
				status_header(404);
				echo $this->funcs->view('errors.model-not-found', [
					'message' => $message,
					'model'   => $modelName,
				]);
				exit;
			}
		}
		catch (\Throwable $viewException) {
			// Nếu view bị lỗi, fallback
		}

		// Fallback: Sử dụng wp_die() với status 404
		wp_die(
			'<h1>Model not found</h1><p>' . esc_html($message) . '</p>',
			'404 - Model not found',
			[
				'response'  => 404,
				'back_link' => true,
			]
		);
	}

	protected function handleAuthorizationException(\Throwable $e) {
		$message = $e->getMessage();

		/**
		 * Với request AJAX hoặc REST API.
		 */
		if ($this->shouldReturnJson()) {
			status_header(403);
			wp_send_json([
				'success' => false,
				'error'   => [
					'type'    => 'AuthorizationException',
					'message' => $message,
				],
			], 403);
			exit;
		}

		/**
		 * Với request thông thường.
		 */
		wp_die(
			'<h1>Không có quyền truy cập</h1><p>' . esc_html($message) . '</p>',
			'403 - Forbidden',
			[
				'response'  => 403,
				'back_link' => true,
			]
		);
	}

	protected function handleAuthenticationException(\Throwable $e) {
		$message    = $e->getMessage();
		$guards     = method_exists($e, 'guards') ? $e->guards() : [];
		$redirectTo = method_exists($e, 'redirectTo') ? $e->redirectTo() : null;

		// Nếu là AJAX/API request
		if ($this->shouldReturnJson()) {
			status_header(401);
			wp_send_json([
				'success' => false,
				'error'   => [
					'type'    => 'AuthenticationException',
					'message' => $message,
					'guards'  => $guards,
				],
			], 401);
			exit;
		}

		// Nếu có redirect URL
		if ($redirectTo) {
			wp_safe_redirect($redirectTo);
			exit;
		}

		// Redirect về trang login với return URL
		$loginUrl = wp_login_url($_SERVER['REQUEST_URI'] ?? '');
		wp_safe_redirect($loginUrl);
		exit;
	}

	protected function handleHttpException(\Throwable $e) {
		$statusCode = method_exists($e, 'getStatusCode') ? $e->getStatusCode() : 500;
		$message    = $e->getMessage();
		$headers    = method_exists($e, 'getHeaders') ? $e->getHeaders() : [];

		// Set headers
		foreach ($headers as $key => $value) {
			if (!headers_sent()) {
				header("{$key}: {$value}");
			}
		}

		// Nếu là AJAX/API request
		if ($this->shouldReturnJson()) {
			status_header($statusCode);
			wp_send_json([
				'success' => false,
				'error'   => [
					'type'    => 'HttpException',
					'message' => $message,
					'code'    => $statusCode,
				],
			], $statusCode);
			exit;
		}

		// Kiểm tra view tùy chỉnh
		$viewName = "errors.{$statusCode}";

		try {
			$viewInstance = $this->funcs->_viewInstance();

			if ($viewInstance->exists($viewName)) {
				status_header($statusCode);
				echo $this->funcs->view($viewName, [
					'message'    => $message,
					'statusCode' => $statusCode,
				]);
				exit;
			}

			if ($viewInstance->exists('errors.default')) {
				status_header($statusCode);
				echo $this->funcs->view('errors.default', [
					'message'    => $message,
					'statusCode' => $statusCode,
				]);
				exit;
			}
		}
		catch (\Throwable $viewException) {
			// Fallback
		}

		// Fallback: wp_die()
		wp_die(
			'<h1>Lỗi ' . $statusCode . '</h1><p>' . esc_html($message) . '</p>',
			$statusCode . ' - Error',
			[
				'response'  => $statusCode,
				'back_link' => true,
			]
		);
	}

}