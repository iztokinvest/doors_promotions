<?php

// Регистриране на настройките
add_action('admin_menu', 'cf_cache_manager_menu');
add_action('admin_bar_menu', 'cf_cache_manager_admin_bar', 999); // Добавяне в администраторската лента

function cf_cache_manager_menu()
{
	add_management_page('Cloudflare Cache Manager', 'CF Cache Manager', 'manage_options', 'cf-cache-manager', 'cf_cache_manager_page');
}

function cf_cache_manager_admin_bar($wp_admin_bar)
{
	if (!is_user_logged_in() || !current_user_can('manage_options')) {
		return;
	}

	$api_token = get_option('cf_api_token');
	$domain = get_option('cf_domain', parse_url(get_site_url(), PHP_URL_HOST));
	$zone_id = get_zone_id_from_domain($api_token, $domain);
	$current_time = time();

	if ($api_token && $zone_id) {
		$current_url = (is_admin() ? admin_url() : home_url(add_query_arg(null, null)));
		$args = [
			'id' => 'cf-cache-manager',
			'title' => 'Cloudflare Cache',
			'href' => admin_url('tools.php?page=cf-cache-manager'),
		];
		$wp_admin_bar->add_node($args);

		$wp_admin_bar->add_node([
			'id' => 'cf-cache-purge',
			'parent' => 'cf-cache-manager',
			'title' => 'Изчисти кеша на тази страница: ' . esc_url($current_url),
			'href' => add_query_arg(['cf_action' => 'purge', 'cf_url' => urlencode($current_url)], admin_url('tools.php?page=cf-cache-manager')),
		]);

		$dev_mode_node = [
			'id' => 'cf-cache-dev-mode',
			'parent' => 'cf-cache-manager',
			'title' => 'Премахни целия кеш за 3 часа',
			'href' => add_query_arg(['cf_action' => 'dev_mode'], admin_url('tools.php?page=cf-cache-manager')),
		];

		// Проверка на статуса на Development Mode директно от Cloudflare
		$dev_mode_status = cf_get_development_mode_status($zone_id, $api_token);
		if ($dev_mode_status && $dev_mode_status['value'] === 'on' && isset($dev_mode_status['time_remaining'])) {
			$remaining_seconds = $dev_mode_status['time_remaining'];
			$hours = floor($remaining_seconds / 3600);
			$minutes = floor(($remaining_seconds % 3600) / 60);
			$seconds = $remaining_seconds % 60;
			$initial_time = sprintf('%d:%02d:%02d', $hours, $minutes, $seconds);
			$dev_mode_node['meta'] = [
				'html' => '<span id="cf-dev-mode-timer" style="font-size: 0.8em; color: green;">Без кеш още: <span id="cf-remaining-time">' . $initial_time . '</span></span>' .
					'<script type="text/javascript">
							  var cf_endTime = ' . ($current_time + $remaining_seconds) . ' * 1000;
							  var cf_timer = setInterval(function() {
								  var now = new Date().getTime();
								  var distance = cf_endTime - now;
								  if (distance < 0) {
									  clearInterval(cf_timer);
									  document.getElementById("cf-remaining-time").innerHTML = "0:00:00";
									  window.location.reload();
								  } else {
									  var hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
									  var minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
									  var seconds = Math.floor((distance % (1000 * 60)) / 1000);
									  document.getElementById("cf-remaining-time").innerHTML = hours + ":" + (minutes < 10 ? "0" : "") + minutes + ":" + (seconds < 10 ? "0" : "") + seconds;
								  }
							  }, 1000);
						  </script>',
			];
		}

		$wp_admin_bar->add_node($dev_mode_node);
	}
}

function cf_cache_manager_page()
{
	$current_time = time();

	// Съхраняване на токена и домейна, ако са изпратени
	if (isset($_POST['cf_token']) && current_user_can('manage_options')) {
		update_option('cf_api_token', sanitize_text_field($_POST['cf_token']));
	}
	if (isset($_POST['cf_domain']) && current_user_can('manage_options')) {
		update_option('cf_domain', sanitize_text_field($_POST['cf_domain']));
	}

	// Извличане на zone_id
	$api_token = get_option('cf_api_token');
	$domain = get_option('cf_domain', parse_url(get_site_url(), PHP_URL_HOST));
	$zone_id = get_zone_id_from_domain($api_token, $domain);

	$last_purge_all_time = get_option('last_purge_all_time', 0);

	// Обработка на действия от администраторската лента чрез GET
	$redirect_url = '';
	if (isset($_GET['cf_action']) && current_user_can('manage_options')) {
		$current_time = time();
		$cooldown_period = 15 * 60; // 15 минути за Purge All
		$dev_mode_duration = 3 * 60 * 60; // 3 часа за Development Mode
		$last_purge_all_time = get_option('last_purge_all_time', 0);

		if ($api_token && $zone_id) {
			$action = sanitize_text_field($_GET['cf_action']);
			$url = isset($_GET['cf_url']) ? esc_url_raw(urldecode($_GET['cf_url'])) : get_site_url() . '/';
			$redirect_url = $url; // Запазваме URL-а за редирект след 5 секунди

			// Проверка дали домейнът е локален
			$parsed_url = parse_url($url);
			$host = $parsed_url['host'] ?? '';
			if (preg_match('/\.(test|local|dev)$/', $host)) {
				echo '<div class="error"><p>Предупреждение: Домейнът "' . esc_html($host) . '" изглежда локален и може да не е конфигуриран в Cloudflare. Действието може да не се изпълни.</p></div>';
			}

			// Проверка дали URL-то е свързано с Zone ID
			$zone_info = get_zone_info_from_id($api_token, $zone_id);
			if ($zone_info && !empty($zone_info['name']) && strpos($url, $zone_info['name']) === false) {
				echo '<div class="error"><p>Грешка: URLът "' . esc_url($url) . '" не е свързан с домейна "' . esc_html($zone_info['name']) . '" в този Zone ID.</p></div>';
			} else {
				// Проверка на статуса на Development Mode директно от Cloudflare
				$dev_mode_status = cf_get_development_mode_status($zone_id, $api_token);
				$dev_mode_active = ($dev_mode_status && $dev_mode_status['value'] === 'on' && isset($dev_mode_status['time_remaining']) && $dev_mode_status['time_remaining'] > 0);

				switch ($action) {
					case 'purge':
						$result = cf_purge_cache($zone_id, $api_token, ['files' => [$url]]);
						if (!is_wp_error($result) && $result === true) {
							echo '<div class="updated"><p>Кешът за URL ' . esc_url($url) . ' беше успешно изчистен!</p></div>';
						} else {
							$error_msg = is_wp_error($result) ? $result->get_error_message() : 'Неуспешно почистване на кеша.';
							echo '<div class="error"><p>' . $error_msg . '</p></div>';
						}
						break;

					case 'dev_mode':
						// Първо изпълни Purge All
						if ($dev_mode_active) {
							echo '<div class="error"><p>Purge All е забранен, докато Development Mode е активен!</p></div>';
						} elseif ($current_time - $last_purge_all_time < $cooldown_period) {
							$time_left = $cooldown_period - ($current_time - $last_purge_all_time);
							echo '<div class="error"><p>Purge All може да се изпълни отново след ' . $time_left . ' секунди.</p></div>';
						} else {
							$purge_result = cf_purge_cache($zone_id, $api_token, ['purge_everything' => true]);
							if (!is_wp_error($purge_result) && $purge_result === true) {
								update_option('last_purge_all_time', $current_time);
								echo '<div class="updated"><p>Целият кеш на Cloudflare беше успешно изчистен!</p></div>';
							} else {
								$error_msg = is_wp_error($purge_result) ? $purge_result->get_error_message() : 'Неуспешно почистване на целия кеш.';
								echo '<div class="error"><p>' . $error_msg . '</p></div>';
							}
						}

						// Активирай Development Mode
						$result = cf_set_development_mode($zone_id, $api_token, 'on');
						if (!is_wp_error($result) && $result === true) {
							echo '<div class="updated"><p>Development Mode е активиран за 3 часа. Purge All е забранен!</p></div>';
						} else {
							$error_msg = is_wp_error($result) ? $result->get_error_message() : 'Неуспешна активация на Development Mode.';
							echo '<div class="error"><p>' . $error_msg . '</p></div>';
						}
						break;

					default:
						echo '<div class="error"><p>Невалидно действие.</p></div>';
						break;
				}
			}
		}
	}

	if (isset($_POST['action']) && $api_token && $zone_id && current_user_can('manage_options')) {
		$current_time = time();
		$cooldown_period = 15 * 60; // 15 минути за Purge All
		$dev_mode_duration = 3 * 60 * 60; // 3 часа за Development Mode

		// Проверка на статуса на Development Mode директно от Cloudflare
		$dev_mode_status = cf_get_development_mode_status($zone_id, $api_token);
		$dev_mode_active = ($dev_mode_status && $dev_mode_status['value'] === 'on' && isset($dev_mode_status['time_remaining']) && $dev_mode_status['time_remaining'] > 0);

		switch ($_POST['action']) {
			case 'purge':
				$result = cf_purge_cache($zone_id, $api_token, ['files' => [get_site_url() . '/']]);
				if (!is_wp_error($result) && $result === true) {
					echo '<div class="updated"><p>Кешът за URL ' . esc_url(get_site_url() . '/') . ' беше успешно изчистен!</p></div>';
				} else {
					$error_msg = is_wp_error($result) ? $result->get_error_message() : 'Неуспешно почистване на кеша.';
					echo '<div class="error"><p>' . $error_msg . '</p></div>';
				}
				break;

			case 'dev_mode':
				// Първо изпълни Purge All
				if ($dev_mode_active) {
					echo '<div class="error"><p>Purge All е забранен, докато Development Mode е активен!</p></div>';
				} elseif ($current_time - $last_purge_all_time < $cooldown_period) {
					$time_left = $cooldown_period - ($current_time - $last_purge_all_time);
					echo '<div class="error"><p>Purge All може да се изпълни отново след ' . $time_left . ' секунди.</p></div>';
				} else {
					$purge_result = cf_purge_cache($zone_id, $api_token, ['purge_everything' => true]);
					if (!is_wp_error($purge_result) && $purge_result === true) {
						update_option('last_purge_all_time', $current_time);
						echo '<div class="updated"><p>Целият кеш на Cloudflare беше успешно изчистен!</p></div>';
					} else {
						$error_msg = is_wp_error($purge_result) ? $purge_result->get_error_message() : 'Неуспешно почистване на целия кеш.';
						echo '<div class="error"><p>' . $error_msg . '</p></div>';
					}
				}

				// Активирай Development Mode
				$result = cf_set_development_mode($zone_id, $api_token, 'on');
				if (!is_wp_error($result) && $result === true) {
					echo '<div class="updated"><p>Development Mode е активиран за 3 часа. Purge All е забранен!</p></div>';
				} else {
					$error_msg = is_wp_error($result) ? $result->get_error_message() : 'Неуспешна активация на Development Mode.';
					echo '<div class="error"><p>' . $error_msg . '</p></div>';
				}
				break;
		}
	}

	// Проверка дали Development Mode е активен директно от Cloudflare за страницата
	$dev_mode_status = cf_get_development_mode_status($zone_id, $api_token);
	$dev_mode_active = ($dev_mode_status && $dev_mode_status['value'] === 'on' && isset($dev_mode_status['time_remaining']) && $dev_mode_status['time_remaining'] > 0);
	$dev_mode_end_time = $current_time + ($dev_mode_status['time_remaining'] ?? 0);

	// Ако Dev Mode е изтекъл, деактивирай го (но Cloudflare го деактивира автоматично, така че това може да не е нужно, но за сигурност)
	if ($dev_mode_status && $dev_mode_status['value'] === 'on' && ($dev_mode_status['time_remaining'] ?? 0) <= 0) {
		$result = cf_set_development_mode($zone_id, $api_token, 'off');
		if (!is_wp_error($result) && $result === true) {
			echo '<div class="updated"><p>Development Mode е изтекъл и е деактивиран.</p></div>';
		}
	}
?>

	<div class="wrap">
		<h1>Cloudflare Cache Manager</h1>
		<form method="post" style="margin-bottom: 20px;">
			<label for="cf_token">Cloudflare API Token:</label><br>
			<input type="password" name="cf_token" id="cf_token" value="<?php echo esc_attr(get_option('cf_api_token') ? str_repeat('*', 32) : ''); ?>" style="width: 300px;" readonly>
			<br><br>
			<label for="cf_domain">Домейн:</label><br>
			<input type="text" name="cf_domain" id="cf_domain" value="<?php echo esc_attr(get_option('cf_domain', parse_url(get_site_url(), PHP_URL_HOST))); ?>" style="width: 300px;">
			<input type="submit" class="button" value="Запази">
		</form>

		<?php if ($api_token && $zone_id): ?>
			<form method="post">
				<input type="hidden" name="action" value="purge">
				<!-- <input type="submit" class="button" value="Purge (по URL)"> -->
			</form>
			<form method="post">
				<input type="hidden" name="action" value="dev_mode">
				<input type="submit" class="button" value="Премахни целия кеш за 3 часа">
			</form>
			<?php if ($dev_mode_active): ?>
				<div id="dev-mode-counter" style="margin-top: 20px; font-size: 16px; color: #0066cc;">Оставащо време без кеш: <span id="remaining-time"></span></div>
				<script type="text/javascript">
					var endTime = <?php echo $dev_mode_end_time; ?> * 1000; // Конвертиране в милисекунди
					var timer = setInterval(function() {
						var now = new Date().getTime();
						var distance = endTime - now;
						if (distance < 0) {
							clearInterval(timer);
							document.getElementById('remaining-time').innerHTML = '0:00:00';
							window.location.reload(); // Презареди страницата, за да деактивира Dev Mode
						} else {
							var hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
							var minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
							var seconds = Math.floor((distance % (1000 * 60)) / 1000);
							document.getElementById('remaining-time').innerHTML = hours + ':' + (minutes < 10 ? '0' : '') + minutes + ':' + (seconds < 10 ? '0' : '') + seconds;
						}
					}, 1000);
				</script>
			<?php endif; ?>
		<?php elseif ($api_token && !$zone_id): ?>
			<div class="error">
				<p>Не можах да намеря Zone ID за домейна <?php echo $domain; ?>. Провери домейна или токена.</p>
			</div>
		<?php else: ?>
			<p>Моля, въведи API токен и домейн, за да използваш функциите.</p>
		<?php endif; ?>

		<?php if (!empty($redirect_url) && strpos($_SERVER['REQUEST_URI'], 'cf_action') !== false): ?>
			<script type="text/javascript">
				var timeLeft = 5;
				var timer = setInterval(function() {
					document.getElementById('counter').innerHTML = 'Ще бъдете пренасочени обратно след ' + timeLeft + ' секунди...';
					timeLeft -= 1;
					if (timeLeft < 0) {
						clearInterval(timer);
						window.location.href = '<?php echo esc_url($redirect_url); ?>';
					}
				}, 1000);
			</script>
			<div id="counter" style="margin-top: 20px; font-size: 16px; color: #0066cc;">5 секунди...</div>
			<meta http-equiv="refresh" content="5;url=<?php echo esc_url($redirect_url); ?>">
		<?php endif; ?>
	</div>
<?php
}

// Функция за извличане на статуса на Development Mode
function cf_get_development_mode_status($zone_id, $api_token)
{
	$api_url = "https://api.cloudflare.com/client/v4/zones/{$zone_id}/settings/development_mode";
	$args = [
		'headers' => [
			'Authorization' => "Bearer {$api_token}",
			'Content-Type' => 'application/json',
		],
		'method' => 'GET',
		'timeout' => 30,
	];

	$response = wp_remote_request($api_url, $args);
	if (is_wp_error($response)) {
		return false;
	}

	$body = json_decode(wp_remote_retrieve_body($response), true);
	if ($body['success'] && !empty($body['result'])) {
		return $body['result'];
	}
	return false;
}

// Нова функция за извличане на информация за Zone
function get_zone_info_from_id($api_token, $zone_id)
{
	$api_url = "https://api.cloudflare.com/client/v4/zones/" . $zone_id;
	$args = [
		'headers' => [
			'Authorization' => "Bearer {$api_token}",
			'Content-Type' => 'application/json',
		],
		'method' => 'GET',
		'timeout' => 30,
	];

	$response = wp_remote_request($api_url, $args);
	if (is_wp_error($response)) {
		return false;
	}

	$body = json_decode(wp_remote_retrieve_body($response), true);
	if ($body['success'] && !empty($body['result'])) {
		return $body['result'];
	}
	return false;
}

// Функция за извличане на Zone ID
function get_zone_id_from_domain($api_token, $domain)
{
	$api_url = "https://api.cloudflare.com/client/v4/zones?name=" . urlencode($domain);
	$args = [
		'headers' => [
			'Authorization' => "Bearer {$api_token}",
			'Content-Type' => 'application/json',
		],
		'method' => 'GET',
		'timeout' => 30,
	];

	$response = wp_remote_request($api_url, $args);
	if (is_wp_error($response)) {
		return false;
	}

	$body = json_decode(wp_remote_retrieve_body($response), true);
	if ($body['success'] && !empty($body['result'][0]['id'])) {
		return $body['result'][0]['id'];
	}
	return false;
}

// Функция за изчистване на кеша
function cf_purge_cache($zone_id, $api_token, $data)
{
	$api_url = "https://api.cloudflare.com/client/v4/zones/{$zone_id}/purge_cache";
	$args = [
		'headers' => [
			'Authorization' => "Bearer {$api_token}",
			'Content-Type' => 'application/json',
		],
		'body' => json_encode($data),
		'method' => 'POST',
		'timeout' => 30,
	];

	$response = wp_remote_request($api_url, $args);
	if (is_wp_error($response)) {
		return $response;
	}

	$body = json_decode(wp_remote_retrieve_body($response), true);
	if ($body['success']) {
		// Проверка за грешки в отговора
		if (!empty($body['errors'])) {
			return new WP_Error('api_error', $body['errors'][0]['message']);
		}
		return true;
	}
	return new WP_Error('api_error', $body['errors'][0]['message'] ?? 'Неизвестна грешка при почистване на кеша.');
}

// Функция за активиране/деактивиране на Development Mode
function cf_set_development_mode($zone_id, $api_token, $value)
{
	$api_url = "https://api.cloudflare.com/client/v4/zones/{$zone_id}/settings/development_mode";
	$args = [
		'headers' => [
			'Authorization' => "Bearer {$api_token}",
			'Content-Type' => 'application/json',
		],
		'body' => json_encode(['value' => $value]),
		'method' => 'PATCH',
		'timeout' => 30,
	];

	$response = wp_remote_request($api_url, $args);
	if (is_wp_error($response)) {
		return $response;
	}

	$body = json_decode(wp_remote_retrieve_body($response), true);
	if ($body['success']) {
		if (!empty($body['errors'])) {
			return new WP_Error('api_error', $body['errors'][0]['message']);
		}
		return true;
	}
	return new WP_Error('api_error', $body['errors'][0]['message'] ?? 'Неизвестна грешка при настройка на Development Mode.');
}
?>