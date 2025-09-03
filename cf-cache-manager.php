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
		$dev_mode_status = cf_get_development_mode_status($zone_id, $api_token);
		$current_url = (is_admin() ? admin_url() : home_url(add_query_arg(null, null)));
		$icon_color = $dev_mode_status && $dev_mode_status['value'] === 'on' ? '#ffffff' : '#f38020';
		$content_icon = '
        <svg xmlns="http://www.w3.org/2000/svg" aria-label="Cloudflare" role="img" width="20" height="20" viewBox="0 0 20 20" style="vertical-align: middle; margin-top: -2px;">
            <rect width="20" height="20" rx="15%" fill="#ffffff"/>
            <path fill="' . $icon_color . '" d="M13.24 12.84c0.44-1.04-0.16-1.52-0.76-1.52l-5.92-0.08c-0.16 0-0.16-0.24 0.04-0.28l6-0.08c0.68-0.04 1.48-0.6 1.72-1.32 0 0 0.4-0.84 0.36-0.96a3.88 3.88 0 0 0-7.48-0.44c-1.52-1-3.12 0.36-2.76 1.84-1.92 0.12-2.6 1.84-2.4 2.88 0 0.04 0.04 0.08 0.12 0.08h10.96c0.04 0 0.12-0.04 0.12-0.12z"/>
            <path fill="' . $icon_color . '" d="M15.24 8.96c-0.16 0-0.24-0.04-0.28 0.04l-0.2 0.84c-0.2 0.64 0.12 1.2 0.8 1.24l1.28 0.08c0.16 0 0.16 0.24-0.04 0.28l-1.32 0.04c-1.44 0.16-1.84 1.56-1.84 1.56 0 0.08 0 0.12 0.08 0.12h4.52l0.12-0.08a3.24 3.24 0 0 0-3.12-4.12"/>
        </svg>';

		// Добавяне на прогрес линията, ако Dev Mode е активен
		$progress_line = '';
		if ($dev_mode_status && $dev_mode_status['value'] === 'on' && isset($dev_mode_status['time_remaining'])) {
			$progress_line = '<div id="cf-progress-line" style="position: absolute; bottom: 0; left: 0; width: 0%; height: 2px; background-color: #f38020;"></div>';
		}

		$args = [
			'id' => 'cf-cache-manager',
			'title' => "<span class='ab-icon' style='position: relative;'>$content_icon</span> <span class='ab-label' style='color: $icon_color;'>Cloudflare Cache</span><div>$progress_line</div>",
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
			'title' => $dev_mode_status['value'] === 'on' ? 'Удължи времето без кеш до 3 часа' : 'Премахни целия кеш за период от 3 часа',
			'href' => add_query_arg(['cf_action' => 'dev_mode'], admin_url('tools.php?page=cf-cache-manager')),
		];
		$wp_admin_bar->add_node($dev_mode_node);

		if ($dev_mode_status && $dev_mode_status['value'] === 'on' && isset($dev_mode_status['time_remaining'])) {
			$remaining_seconds = $dev_mode_status['time_remaining'];
			$hours = floor($remaining_seconds / 3600);
			$minutes = floor(($remaining_seconds % 3600) / 60);
			$seconds = $remaining_seconds % 60;
			$initial_time = sprintf('%d:%02d:%02d', $hours, $minutes, $seconds);
			$dev_mode_node['meta'] = [
				'html' => '<div style="text-align:center; position: relative; margin: 10px; width: 300px;">
                <div style="width: 100%; background-color: #e0e0e0; height: 20px; border-radius: 5px; overflow: hidden;">
                    <div id="cf-progress-fill" style="width: 100%; height: 100%; background-color: #f38020; transition: width 1s linear; text-align: center; color: white; line-height: 20px;"></div>
                </div>
                <div id="cf-remaining-time" style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); font-size: 14px; font-weight: bold; color: #0f2b46;"></div>
            </div>' .
					'<script type="text/javascript">
                var cf_endTime = ' . ($current_time + $remaining_seconds) . ' * 1000;
                var totalDuration = 3 * 60 * 60 * 1000; // Общо време (3 часа в милисекунди)
                var cf_timer = setInterval(function() {
                    var now = new Date().getTime();
                    var distance = cf_endTime - now;
                    if (distance < 0) {
                        clearInterval(cf_timer);
                        document.getElementById("cf-remaining-time").innerHTML = "0:00:00";
                        document.getElementById("cf-progress-fill").style.width = "0%";
                        document.getElementById("cf-progress-line").style.width = "0%";
                        window.location.reload();
                    } else {
                        var hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                        var minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
                        var seconds = Math.floor((distance % (1000 * 60)) / 1000);
                        var progress = (distance / totalDuration) * 100; // Изчисляване на прогреса в проценти
                        document.getElementById("cf-remaining-time").innerHTML = "Без кеш още: " + hours + ":" + (minutes < 10 ? "0" : "") + minutes + ":" + (seconds < 10 ? "0" : "") + seconds;
                        document.getElementById("cf-progress-fill").style.width = progress + "%";
                        document.getElementById("cf-progress-line").style.width = progress + "%";
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
			$host = isset($parsed_url['host']) ? $parsed_url['host'] : '';
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
	$dev_mode_end_time = $current_time + (isset($dev_mode_status['time_remaining']) ? $dev_mode_status['time_remaining'] : 0);

	// Ако Dev Mode е изтекъл, деактивирай го (но Cloudflare го деактивира автоматично, така че това може да не е нужно, но за сигурност)
	if ($dev_mode_status && $dev_mode_status['value'] === 'on' && (isset($dev_mode_status['time_remaining']) ? $dev_mode_status['time_remaining'] : 0) <= 0) {
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
			<input type="password" name="cf_token" id="cf_token" value="<?php echo esc_attr(get_option('cf_api_token') ? str_repeat('*', 32) : ''); ?>" style="width: 300px;">
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
				<input type="submit" class="button" value="<?php echo ($dev_mode_active ? 'Удължи времето без кеш до 3 часа' : 'Премахни целия кеш за период от 3 часа') ?> ">
			</form>
			<?php if ($dev_mode_active): ?>
				<div id="dev-mode-progress-container" style="margin-top: 20px; font-size: 16px; color: #0066cc; position: relative;">
					<div id="dev-mode-progress-bar" style="width: 100%; background-color: #e0e0e0; height: 20px; border-radius: 5px; overflow: hidden;">
						<div id="progress-fill" style="width: 100%; height: 100%; background-color: #f38020; transition: width 1s linear; text-align: center; color: white; line-height: 20px;"></div>
					</div>
					<div id="remaining-time" style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); font-size: 16px; font-weight: bold; color: #0f2b46;"></div>
				</div>
				<script type="text/javascript">
					var endTime = <?php echo $dev_mode_end_time; ?> * 1000; // Конвертиране в милисекунди
					var totalDuration = 3 * 60 * 60 * 1000; // Общо време (3 часа в милисекунди)
					var timer = setInterval(function() {
						var now = new Date().getTime();
						var distance = endTime - now;
						if (distance < 0) {
							clearInterval(timer);
							document.getElementById('remaining-time').innerHTML = '0:00:00';
							document.getElementById('progress-fill').style.width = '0%';
							window.location.reload(); // Презареди страницата, за да деактивира Dev Mode
						} else {
							var hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
							var minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
							var seconds = Math.floor((distance % (1000 * 60)) / 1000);
							var progress = (distance / totalDuration) * 100; // Изчисляване на прогреса в проценти
							document.getElementById('remaining-time').innerHTML = "Без кеш още: " + (hours < 10 ? '0' : '') + hours + ':' + (minutes < 10 ? '0' : '') + minutes + ':' + (seconds < 10 ? '0' : '') + seconds;
							document.getElementById('progress-fill').style.width = progress + '%';
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
	return new WP_Error('api_error', isset($body['errors'][0]['message']) ? $body['errors'][0]['message'] : 'Неизвестна грешка при почистване на кеша.');
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
	return new WP_Error('api_error', isset($body['errors'][0]['message']) ? $body['errors'][0]['message'] : 'Неизвестна грешка при настройка на Development Mode.');
}
?>