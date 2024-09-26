<?php
/*
Plugin Name: Doors Promotions
Plugin URI: https://github.com/iztokinvest/doors_promotions
Description: Promo banner shortcodes.
Version: 1.14.1
Author: Martin Mladenov
GitHub Plugin URI: https://github.com/iztokinvest/doors_promotions
GitHub Branch: main
*/

class WP_Promotions_Updater
{
	private $slug;
	private $pluginData;
	private $repo;
	private $githubAPIResult;

	public function __construct($plugin_file)
	{
		add_filter('pre_set_site_transient_update_plugins', [$this, 'set_update_transient']);
		add_filter('plugins_api', [$this, 'set_plugin_info'], 10, 3);
		add_filter('upgrader_post_install', [$this, 'post_install'], 10, 3);
		$this->slug = plugin_basename($plugin_file);

		if (!function_exists('get_plugin_data')) {
			require_once(ABSPATH . 'wp-admin/includes/plugin.php');
		}

		$this->pluginData = get_plugin_data($plugin_file);
		$this->repo = 'iztokinvest/doors_promotions';
	}

	private function get_repository_info()
	{
		if (is_null($this->githubAPIResult)) {
			$request = 'https://api.github.com/repos/' . $this->repo . '/releases/latest';
			$response = wp_remote_get($request);
			if (is_wp_error($response)) {
				return false;
			}
			$this->githubAPIResult = json_decode(wp_remote_retrieve_body($response));
		}
		return $this->githubAPIResult;
	}

	public function set_update_transient($transient)
	{
		if (empty($transient->checked)) {
			return $transient;
		}
		$this->get_repository_info();
		if ($this->githubAPIResult) {
			$do_update = version_compare($this->githubAPIResult->tag_name, $this->pluginData['Version'], '>');
			if ($do_update) {
				$package = $this->githubAPIResult->zipball_url;
				$transient->response[$this->slug] = (object) [
					'slug' => $this->slug,
					'new_version' => $this->githubAPIResult->tag_name,
					'url' => $this->pluginData['PluginURI'],
					'package' => $package,
				];
			}
		}
		return $transient;
	}

	public function set_plugin_info($false, $action, $response)
	{
		if (empty($response->slug) || $response->slug != $this->slug) {
			return false;
		}
		$this->get_repository_info();
		if ($this->githubAPIResult) {
			$response->last_updated = $this->githubAPIResult->published_at;
			$response->slug = $this->slug;
			$response->plugin_name  = $this->pluginData['Name'];
			$response->version = $this->githubAPIResult->tag_name;
			$response->author = $this->pluginData['AuthorName'];
			$response->homepage = $this->pluginData['PluginURI'];
			$response->download_link = $this->githubAPIResult->zipball_url;
			$response->sections = [
				'description' => $this->pluginData['Description'],
			];
		}
		return $response;
	}

	public function post_install($true, $hook_extra, $result)
	{
		global $wp_filesystem;
		$plugin_folder = WP_PLUGIN_DIR . '/' . dirname($this->slug);
		$wp_filesystem->move($result['destination'], $plugin_folder);
		$result['destination'] = $plugin_folder;
		activate_plugin($this->slug);
		return $result;
	}
}

if (is_admin()) {
	new WP_Promotions_Updater(__FILE__);
}

function promoPluginData()
{
	if (! function_exists('get_plugin_data')) {
		require_once(ABSPATH . 'wp-admin/includes/plugin.php');
	}
	$plugin_data = get_plugin_data(__FILE__);

	return $plugin_data;
}

function load_libraries($hook)
{
	$plugin_page = 'promotions';

	if (!preg_match('/promotions/', $hook)) {
		return;
	}

	wp_enqueue_style('bootstrap-css', 'https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css');
	wp_enqueue_script('bootstrap-js', 'https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.bundle.min.js', array('jquery'), null, true);
	wp_enqueue_script('codemirror-js', 'https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/codemirror.min.js', array(), '5.65.2', true);
	wp_enqueue_script('codemirror-mode-htmlmixed', 'https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/mode/htmlmixed/htmlmixed.min.js', array('codemirror-js'), '5.65.2', true);
	wp_enqueue_script('codemirror-mode-xml', 'https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/mode/xml/xml.min.js', array('codemirror-js'), '5.65.2', true);
	wp_enqueue_script('codemirror-mode-javascript', 'https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/mode/javascript/javascript.min.js', array('codemirror-js'), '5.65.2', true);
	wp_enqueue_script('codemirror-mode-css', 'https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/mode/css/css.min.js', array('codemirror-js'), '5.65.2', true);
	wp_enqueue_style('codemirror-css', 'https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/codemirror.min.css', array(), '5.65.2');
	wp_enqueue_style('codemirror-theme-monokai', 'https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/theme/monokai.min.css', array(), '5.65.2');

	// Enqueue HTMLHint
	wp_enqueue_script('htmlhint', 'https://cdnjs.cloudflare.com/ajax/libs/htmlhint/1.1.0/htmlhint.min.js', array(), '1.1.0', true);
	wp_enqueue_script('codemirror-addon-lint', 'https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/addon/lint/lint.min.js', array('codemirror-js'), '5.65.2', true);
	wp_enqueue_script('codemirror-html-lint', 'https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/addon/lint/html-lint.min.js', array('htmlhint', 'codemirror-addon-lint'), '5.65.2', true);
	wp_enqueue_style('codemirror-lint-css', 'https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/addon/lint/lint.min.css', array(), '5.65.2');

	wp_enqueue_script('awesome-notifications-js', plugin_dir_url(__FILE__) .
		'assets/js/awesome_notifications.js', array(), '1.0', true);
	wp_enqueue_style('awesome-notifications-css', plugin_dir_url(__FILE__) .
		'assets/css/awesome_notifications.css', array(), '1.0');
}

function enqueue_promotions_script()
{
	$script_version = filemtime(plugin_dir_path(__FILE__) . 'promotions.js');
	wp_register_script('promotions-script', plugin_dir_url(__FILE__) . 'promotions.js', array(), $script_version, true);
	wp_enqueue_script('promotions-script');
	wp_enqueue_style('vanillajs-datepicker-css', 'https://cdn.jsdelivr.net/npm/vanillajs-datepicker@1.3.4/dist/css/datepicker.min.css');
	wp_enqueue_script('vanillajs-datepicker-js', 'https://cdn.jsdelivr.net/npm/vanillajs-datepicker@1.3.4/dist/js/datepicker.min.js', array(), null, true);
}

function enqueue_countdown_timer_script()
{
	if (!is_admin()) {
		wp_enqueue_script('countdown-timer', plugins_url('/assets/js/countdown_timer.js', __FILE__), array(), false, true);
		wp_enqueue_script('workdays', plugins_url('/assets/js/workdays.js', __FILE__), array(), false, true);
	}
}
add_action('wp_enqueue_scripts', 'enqueue_countdown_timer_script');

function fetch_shortcodes_from_db()
{
	global $wpdb;
	$table_name = $wpdb->prefix . 'doors_promotions_templates';

	$results = $wpdb->get_results("SELECT shortcode, shortcode_name, template_content FROM $table_name", ARRAY_A);

	$shortcodes = [];
	foreach ($results as $row) {
		$shortcodes[$row['shortcode']] = [
			'name' => $row['shortcode_name'],
			'content' => $row['template_content']
		];
	}

	return $shortcodes;
}

function load_shortcode_template($content, $placeholders)
{
	if ($placeholders['worktime']) {
		$workday = additional_shortcodes('workday', $content);
		$holidays_text = additional_shortcodes('holidays_text', $content);
		$holidays = additional_shortcodes('holidays', $content);

		if (empty($holidays)) {
			$holidays_text = "";
		}

		if ($workday || $holidays_text || $holidays) {
			return "<div class='workdays'>" . $workday . $holidays_text . $holidays . "</div>";
		}
	}

	foreach ($placeholders as $key => $value) {
		$content = str_replace("[$key]", $value, $content);
	}

	return $content;
}

function shortcodes($image, $alt, $timer_days, $timer_hours, $timer_minutes, $timer_seconds)
{
	$shortcodes = [];
	$worktime = false;
	foreach (fetch_shortcodes_from_db() as $key => $data) {
		$worktime = false;
		if (preg_match('/workday/', $data['content'])) {
			$worktime = true;
		}
		$shortcodes[$key] = load_shortcode_template($data['content'], ['image' => $image, 'alt' => $alt, 'timer-days' => $timer_days, 'timer-hours' => $timer_hours, 'timer-minutes' => $timer_minutes, 'timer-seconds' => $timer_seconds, 'worktime' => $worktime]);
	}

	return $shortcodes;
}

function handle_shortcode($atts, $shortcode)
{
	global $wpdb, $product;

	$table_name = $wpdb->prefix . 'doors_promotions';
	$current_date = date('Y-m-d');

	$product_categories = (is_object($product) && method_exists($product, 'get_category_ids')) ? $product->get_category_ids() : [];

	$query = "SELECT * FROM $table_name WHERE shortcode = %s AND start_date <= %s AND end_date >= %s AND active = %d";
	$params = [$shortcode, $current_date, $current_date, 1];

	$promotions = $wpdb->get_results($wpdb->prepare($query, $params));

	foreach ($promotions as $promo) {
		if (is_null($promo->category) || in_array($promo->category, $product_categories)) {
			$timer_days = '<span id="timer-days" data-end-date="' . $promo->end_date . '"></span>';
			$timer_hours = '<span id="timer-hours" data-end-date="' . $promo->end_date . '"></span>';
			$timer_minutes = '<span id="timer-minutes" data-end-date="' . $promo->end_date . '"></span>';
			$timer_seconds = '<span id="timer-seconds" data-end-date="' . $promo->end_date . '"></span>';

			return shortcodes(esc_url($promo->image), esc_attr($promo->title), $timer_days, $timer_hours, $timer_minutes, $timer_seconds)[$shortcode];
		}
	}

	return '';
}

function additional_shortcodes($keyword, $content)
{
	if ($keyword == 'workday') {
		preg_match_all('/\[workday (.*?)\]/', $content, $workdays_matches);
		if ($workdays_matches) {
			$workdays = [];
			foreach ($workdays_matches[1] as $text) {
				$parts = explode(' ', $text);
				$day = $parts[0];
				$hours = $parts[1];

				$workdays[] = ['day' => $day, 'hours' => $hours];
			}

			$content = "<table class='table-plain branch-hours'><tbody>";

			$day_classes = [
				'Понеделник' => 'Monday',
				'Вторник' => 'Tuesday',
				'Сряда' => 'Wednesday',
				'Четвъртък' => 'Thursday',
				'Петък' => 'Friday',
				'Събота' => 'Saturday',
				'Неделя' => 'Sunday',
				'Понеделник-Петък' => 'Monday-Friday',
				'Понеделник-Събота' => 'Monday-Saturday',
			];

			foreach ($workdays as $day) {
				$content .= "<tr class='" . $day_classes[$day['day']] . "' data-day='" . $day_classes[$day['day']] . "'>
					<td>" . htmlspecialchars($day['day']) . "</td>
					<td>" . htmlspecialchars($day['hours']) . "</td>
					<td data-open-day='" . $day_classes[$day['day']] . "'></td>
				  </tr>";
			}

			$content .= "</tbody></table>";
		} else {
			$content = "";
		}
	}

	if ($keyword == 'holidays_text') {
		preg_match('/\[holidays_text\|(.*?)\]/', $content, $text_matches);

		if ($text_matches) {
			$content = "";
			$content .=	"<p class='holidays-text'>" . htmlspecialchars($text_matches[1]) . "</p>";
		} else {
			$content = "";
		}
	}

	if ($keyword == 'holidays') {
		preg_match('/\[holidays\|(.*?)\]/', $content, $holiday_matches);
		if ($holiday_matches) {
			$holiday_dates = $holiday_matches[1];

			$dates_array = explode(', ', $holiday_dates);

			foreach ($dates_array as $key => $date) {
				$date_ymd = date('Y-m-d', strtotime($date));

				if (date('Y-m-d') > $date_ymd) {
					unset($dates_array[$key]);
				}
			}

			if ($holiday_matches && count($dates_array) > 0) {
				$content = "";
				$content .= "<p class='holidays'>" . htmlspecialchars(implode(', ', $dates_array)) . "</p>";
			} else {
				$content = "";
			}
		} else {
			$content = "";
		}
	}

	return $content;
}

function clear_cache_if_needed()
{
	$site_directory = basename(ABSPATH);
	$site_directory_path = explode('//', get_site_url());

	// Define paths to cache folders
	$cache_folders = [
		'wp-rocket' => WP_CONTENT_DIR . "/cache/wp-rocket/{$site_directory_path[1]}/",
		'wp-fastest-cache' => WP_CONTENT_DIR . "/cache/all/",
	];

	$cache_needs_clearing = false;

	foreach ($cache_folders as $folder) {
		if (is_dir($folder)) {
			$stat = stat($folder);
			$creation_time = $stat['ctime'];
			$creation_date = date('Y-m-d', $creation_time);
			$current_date = date('Y-m-d');

			if ($creation_date !== $current_date) {
				$cache_needs_clearing = true;
				break;
			}
		}
	}

	if ($cache_needs_clearing) {
		clear_cache();
	}
}

function clear_cache()
{
	// Clear WP Rocket cache
	if (function_exists('rocket_clean_domain')) {
		rocket_clean_domain();
	}
	if (function_exists('rocket_clean_minify')) {
		rocket_clean_minify();
	}

	// WP Fastest Cache
	if (function_exists('wpfc_clear_all_cache')) {
		wpfc_clear_all_cache(true);
	}
}

function initialize_shortcodes()
{
	clear_cache_if_needed();
	$shortcodes = fetch_shortcodes_from_db();
	foreach ($shortcodes as $shortcode => $data) {
		add_shortcode($shortcode, function ($atts) use ($shortcode) {
			return handle_shortcode($atts, $shortcode);
		});
	}
}

function promotions_settings_page()
{
?>
	<div class="wrap">
		<h1>Промоции</h1>
		<form method="post" action="<?php echo admin_url('admin-post.php'); ?>" enctype="multipart/form-data" class="bootstrap-form">
			<input type="hidden" name="action" value="submit_promo">

			<table class="table">
				<tr>
					<th scope="row" style="width: 300px;">Качи изображение</th>
					<td>
						<div><input type="checkbox" id="remove-file-upload"> Без файл <span class="text-danger">(маркира се, ако промоцията няма да съдържа изображене)</span></div>
						<input type="file" class="form-control-file" name="promo_image" id="promo_image" required>
						<img id="promo_image_preview" src="" alt="Selected Image" style="max-width: 300px; max-height: 300px; display: none;">
					</td>
				</tr>
				<tr>
					<th scope="row">Позиция (shortcode)</th>
					<td>
						<select class="form-control" name="promo_shortcode" id="promo_shortcode" placeholder="Позиция" required>
							<option></option>
							<?php
							$shortcodes = fetch_shortcodes_from_db();
							foreach ($shortcodes as $shortcode => $data) {
								$name = esc_html($data['name']);
								echo "<option value='$shortcode' " . (isset($_GET['shortcode']) && $_GET['shortcode'] == $shortcode ? 'selected' : '') . ">$name</option>";
							}
							?>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row">Заглавие (alt)</th>
					<td>
						<input type="text" class="form-control" name="promo_title" id="promo_title" placeholder="Заглавие" <?php echo isset($_GET['title']) ? 'value="' . $_GET['title'] . '"' : ''; ?> required>
					</td>
				</tr>
				<tr>
					<th scope="row">Начална и крайна дата</th>
					<td>
						<input required type="text" class="form-control datepicker-input d-inline" name="promo_start_date" id="promo_start_date" />
						<input required type="text" class="form-control datepicker-input d-inline" name="promo_end_date" id="promo_end_date" />
					</td>
				</tr>
				<tr>
					<th scope="row">Активен</th>
					<td>
						<input type="checkbox" class="form-control" name="active_banner" id="active_banner" checked />
					</td>
				</tr>
				<tr id="promo_categories" style="<?php echo isset($_GET['category']) && $_GET['category'] > 0 ? '' : 'display:none"'; ?>">
					<th scope="row">Категории</th>
					<td>
						<?php
						$product_categories = get_terms([
							'taxonomy' => 'product_cat',
							'hide_empty' => false,
						]);

						// Initialize the hierarchy array
						$categories_hierarchy = [];

						// First pass: Set up all parent categories
						foreach ($product_categories as $category) {
							if ($category->parent == 0) {
								// This is a base category
								$categories_hierarchy[$category->term_id] = [
									'category' => $category,
									'children' => []
								];
							}
						}

						// Second pass: Attach subcategories to their parents
						foreach ($product_categories as $category) {
							if ($category->parent != 0) {
								// This is a subcategory
								if (isset($categories_hierarchy[$category->parent])) {
									$categories_hierarchy[$category->parent]['children'][] = $category;
								} else {
									// Initialize the parent if not already set
									$categories_hierarchy[$category->parent] = [
										'category' => null,
										'children' => [$category]
									];
								}
							}
						}

						// Display the categories
						foreach ($categories_hierarchy as $category_info) {
							if ($category_info['category']) {
								// Display base category in bold
								echo '<div class="form-group row"><strong><input type="checkbox" name="promo_categories[]" value="' . esc_attr($category_info['category']->term_id) . '" class="base-category" data-category-id="' . esc_attr($category_info['category']->term_id) . '" id="category_' . esc_attr($category_info['category']->term_id) . '" ' . (isset($_GET['category']) && $_GET['category'] == esc_attr($category_info['category']->term_id) ? 'checked' : '') . '>' . esc_html($category_info['category']->name) . '</strong></div>';
							}

							// Display subcategories
							if (!empty($category_info['children'])) {
								foreach ($category_info['children'] as $subcategory) {
									echo '<div class="form-group ms-3"><input type="checkbox" name="promo_categories[]" value="' . esc_attr($subcategory->term_id) . '" class="sub-category" data-parent-id="' . esc_attr($category_info['category']->term_id) . '" id="category_' . esc_attr($subcategory->term_id) . '" ' . (isset($_GET['category']) && $_GET['category'] == esc_attr($subcategory->term_id) ? 'checked' : '') . '>' . esc_html($subcategory->name) . '</div>';
								}
							}
						}
						?>
					</td>
				</tr>
			</table>

			<div class="form-group row">
				<div class="col-sm-10 offset-sm-2">
					<button type="submit" name="submit_promo" class="btn btn-primary">Запази</button>
				</div>
			</div>
		</form>
	</div>
<?php
	echo "<hr><div class='float-end me-5'>Версия на разширението: <span id='promo-extension-version'>" . promoPluginData()['Version'] . '</span></div>';
}

function promotions_list_page()
{
	global $wpdb;
	$rows_count = [
		'futured' => 0,
		'active' => 0,
		'expiring' => 0,
		'expired' => 0
	];
	$table_name = $wpdb->prefix . 'doors_promotions';

	$results = $wpdb->get_results("SELECT * FROM $table_name WHERE " . filter_where_clause() . " ORDER BY end_date DESC");

?>
	<div class="wrap">
		<h1>Списък с промоции
			<?php echo filter('promotions'); ?>
		</h1>

		<table id="promotions-list-table" class="table">
			<thead>
				<tr>
					<th>ID</th>
					<th>Категория</th>
					<th>Позиция</th>
					<th>Заглавие</th>
					<th>Изображение</th>
					<th>Начална дата</th>
					<th>Крайна дата</th>
					<th>Активен</th>
					<th colspan="3">Действия</th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ($results as $row) : ?>
					<?php
					if ($row->image) {
						$show_image = '<a target="blank" href="' . esc_url($row->image) . '"><img src="' . esc_url($row->image) . '" alt="' . esc_html($row->title) . '" class="uploadedImages" style="width: 100px;"></a>';
					} else {
						$show_image = '';
					}


					switch (true) {
						case $row->start_date > date('Y-m-d'):
							$row_color = 'style="background: #00dd7761"';
							$row_status = 'futured';
							$rows_count['futured']++;
							break;
						case $row->end_date < date('Y-m-d'):
							$row_color = 'style="background: #ff000040"';
							$row_status = 'expired';
							$rows_count['expired']++;
							break;
						case $row->end_date < date('Y-m-d', strtotime('+6 days')):
							$row_color = 'style="background: #ffff0040"';
							$row_status = 'expiring';
							$rows_count['expiring']++;
							break;
						default:
							$row_color = '';
							$row_status = 'active';
							$rows_count['active']++;

					}
					?>
					<tr <?php echo $row_color; ?>>
						<form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
							<td><?php echo $rows_count[$row_status]; ?></td>
							<td><?php echo $row->category ? get_term($row->category)->name : ''; ?></td>
							<td>
								<?php
								echo '<select name="promo_shortcode" id="promo_shortcode">';
								$shortcodes = fetch_shortcodes_from_db();
								foreach ($shortcodes as $shortcode => $data) {
									$name = esc_html($data['name']);
									$selected = ($row->shortcode == $shortcode) ? 'selected' : '';
									echo "<option value='$shortcode' $selected>$name</option>";
								}
								echo '</select>';
								?>
							</td>
							<td><textarea class="form-control" name="promo_title" id="promo_title"><?php echo esc_html($row->title); ?></textarea></td>
							<td><?php echo $show_image; ?></td>
							<td><input type="text" class="form-control datepicker-input" name="promo_start_date" id="promo_start_date" value="<?php echo date('d/m/Y', strtotime($row->start_date)); ?>" /></td>
							<td><input type="text" class="form-control datepicker-input" name="promo_end_date" id="promo_end_date" value="<?php echo date('d/m/Y', strtotime($row->end_date)); ?>" /></td>
							<td><input type="checkbox" name="promo_active" id="promo_active" <?php echo ($row->active) ? 'checked' : ''; ?> /></td>
							<td>
								<input type="hidden" name="action" value="edit_promo">
								<input type="hidden" name="promo_id" value="<?php echo esc_attr($row->id); ?>">
								<button type="submit" class="btn btn-primary">Редактирай</button>
							</td>
						</form>
						<td>
							<form method="get">
								<input type="hidden" name="page" value="promotions_settings">
								<input type="hidden" name="category" value="<?php echo esc_attr($row->category); ?>">
								<input type="hidden" name="shortcode" value="<?php echo esc_attr($row->shortcode); ?>">
								<input type="hidden" name="title" value="<?php echo esc_attr($row->title); ?>">
								<button type="submit" class="btn btn-success">Дублирай</button>
							</form>
						</td>
						<td>
							<form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
								<input type="hidden" name="action" value="delete_promo">
								<input type="hidden" name="promo_id" value="<?php echo esc_attr($row->id); ?>">
								<button type="submit" class="btn btn-danger">Изтрий</button>
							</form>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<form class="d-inline" method="post" action="<?php echo admin_url('admin-post.php'); ?>">
			<input type="hidden" name="action" value="activate_all">
			<button type="submit" class="btn btn-success">Активирай неактивните</button>
		</form>
		<form class="d-inline" method="post" action="<?php echo admin_url('admin-post.php'); ?>">
			<input type="hidden" name="action" value="delete_expired">
			<button type="submit" class="btn btn-danger">Изтрий приключените</button>
		</form>
	</div>
<?php
	echo "<hr><div class='float-end me-5'>Версия на разширението: <span id='promo-extension-version'>" . promoPluginData()['Version'] . '</span></div>';
}

function promotions_templates_page()
{
	global $wpdb;
	$table_name = $wpdb->prefix . 'doors_promotions_templates';

	$results = $wpdb->get_results("SELECT * FROM $table_name WHERE " . filter_where_clause() . "");

?>
	<div class="wrap">
		<h1>Списък с шаблони <?php echo filter('promotions_templates'); ?></h1>
		<!-- Existing Templates Table -->
		<table class="table table-striped">
			<thead>
				<tr>
					<th>ID</th>
					<th>Shortcode</th>
					<th>Име</th>
					<th>Код</th>
					<th colspan="2">Действия</th>
				</tr>
			</thead>
			<tbody>
				<tr class="bg-secondary">
					<form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
						<td colspan="2" class="w-25 align-text-top"><input type="text" class="form-control" id="shortcode" name="shortcode" required>
							<p class="text-warning">Трябва да присъстват думите:<br><b>product</b> - за продукт<br><b>worktime</b> - за работно време<br><b>text</b> - за текст<br><b>css</b> - за css</p>
						</td>
						<td class="w-25 align-text-top"><input type="text" class="form-control" id="shortcode_name" name="shortcode_name" required></td>
						<td style="text-align:left"><textarea class="form-control template_content" name="template_content"></textarea></td>
						<td colspan="2">
							<input type="hidden" name="action" value="add_new_template">
							<button type="submit" class="btn btn-success template-button" style="display:none;">Добави</button>
						</td>
					</form>
				</tr>
				<?php foreach ($results as $row) : ?>
					<tr>
						<form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
							<td><?php echo esc_html($row->id); ?></td>
							<td><input type="text" name="shortcode" value="<?php echo esc_html($row->shortcode); ?>"></td>
							<td><input type="text" name="shortcode_name" value="<?php echo esc_html($row->shortcode_name); ?>"></td>
							<td style="text-align:left"><textarea class="form-control template_content" name="template_content"><?php echo esc_html($row->template_content); ?></textarea></td>
							<td>
								<input type="hidden" name="promo_id" value="<?php echo esc_attr($row->id); ?>">
								<input type="hidden" name="action" value="update_template">
								<button type="submit" class="btn btn-primary template-button" style="display:none;">Редактирай</button>
							</td>
						</form>
						<td>
							<form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
								<input type="hidden" name="action" value="delete_template">
								<input type="hidden" name="promo_id" value="<?php echo esc_attr($row->id); ?>">
								<button type="submit" class="btn btn-danger">Изтрий</button>
							</form>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<div class="accordion accordion-flush" id="accordionFlushExample">
			<div class="accordion-item">
				<h2 class="accordion-header" id="flush-headingOne">
					<button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#flush-collapseOne" aria-expanded="false" aria-controls="flush-collapseOne">
						Банери
					</button>
				</h2>
				<div id="flush-collapseOne" class="accordion-collapse collapse" aria-labelledby="flush-headingOne" data-bs-parent="#accordionFlushExample">
					<div class="accordion-body">
						<ul>
							<li><b>[image]</b> Адрес на банер</li>
							<li><b>[alt]</b> Описание на банер</li>
						</ul>
					</div>
				</div>
			</div>
			<div class="accordion-item">
				<h2 class="accordion-header" id="flush-headingTwo">
					<button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#flush-collapseTwo" aria-expanded="false" aria-controls="flush-collapseTwo">
						Таймер
					</button>
				</h2>
				<div id="flush-collapseTwo" class="accordion-collapse collapse" aria-labelledby="flush-headingTwo" data-bs-parent="#accordionFlushExample">
					<div class="accordion-body">
						<ul>
							<li><b>[timer-days]</b> Оставащи дни от промоцията</li>
							<li><b>[timer-hours]</b> Оставащи часове от промоцията</li>
							<li><b>[timer-minutes]</b> Оставащи минути от промоцията</li>
							<li><b>[timer-seconds]</b> Оставащи секунди от промоцията</li>
						</ul>
					</div>
				</div>
			</div>
			<div class="accordion-item">
				<h2 class="accordion-header" id="flush-headingThree">
					<button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#flush-collapseThree" aria-expanded="false" aria-controls="flush-collapseThree">
						Работно време
					</button>
				</h2>
				<div id="flush-collapseThree" class="accordion-collapse collapse" aria-labelledby="flush-headingThree" data-bs-parent="#accordionFlushExample">
					<div class="accordion-body">
						<ul>
							<li><b>[workday Понеделник 09:00-18:00]</b> Работно време - вариант с всеки ден поотделно</li>
							<li><b>[workday Понеделник-Събота 09:00-18:00]</b> Работно време - кратък вариант с диапазон от дни</li>
							<li><b>[holidays_text|Шоурумът няма да работи на:]</b> Текст за почивни дни</li>
							<li><b>[holidays|01.01.2025, 02.01.2025]</b> Добавяне на почивни дни</li>
						</ul>
					</div>
				</div>
			</div>
			<div class="accordion-item">
				<h2 class="accordion-header" id="flush-headingFour">
					<button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#flush-collapseFour" aria-expanded="false" aria-controls="flush-collapseFour">
						CSS
					</button>
				</h2>
				<div id="flush-collapseFour" class="accordion-collapse collapse" aria-labelledby="flush-headingFour" data-bs-parent="#accordionFlushExample">
					<div class="accordion-body">
						<ul>
							<li><b>&lt;style&gt;&lt;/style&gt;</b> Добавяне на CSS код</li>
						</ul>
					</div>
				</div>
			</div>
		</div>
	</div>
<?php
	echo "<hr><div class='float-end me-5'>Версия на разширението: <span id='promo-extension-version'>" . promoPluginData()['Version'] . '</span></div>';
}

function handle_promotions_form()
{
	if (isset($_POST['submit_promo'])) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'doors_promotions';

		$promo_categories = isset($_POST['promo_categories']) ? $_POST['promo_categories'] : array();
		$promo_title = sanitize_text_field($_POST['promo_title']);
		$promo_shortcode = sanitize_text_field($_POST['promo_shortcode']);
		$promo_start_date = date_format(date_create_from_format('d/m/Y', sanitize_text_field($_POST['promo_start_date'])), 'Y-m-d');
		$promo_end_date = date_format(date_create_from_format('d/m/Y', sanitize_text_field($_POST['promo_end_date'])), 'Y-m-d');
		$active_banner = $_POST['active_banner'] == 'on' ? '1' : '0';
		$upload_dir = wp_upload_dir();
		$promo_image = '';

		if (!empty($_FILES['promo_image']['tmp_name'])) {
			$upload_path = $upload_dir['basedir'] . '/doors_promotions/';
			$upload_url = $upload_dir['baseurl'] . '/doors_promotions/';

			// Създай директорията, ако не съществува
			if (!file_exists($upload_path)) {
				mkdir($upload_path, 0755, true);
			}

			$filename = basename($_FILES['promo_image']['name']);
			$target_file = $upload_path . $filename;

			if (move_uploaded_file($_FILES['promo_image']['tmp_name'], $target_file)) {
				$promo_image = $upload_url . $filename;
			}
		}

		// Вмъкни данните в потребителската таблица
		if (count($promo_categories) > 0) {
			foreach ($promo_categories as $category_id) {
				$wpdb->insert(
					$table_name,
					array(
						'category' => $category_id,
						'title' => $promo_title,
						'shortcode' => $promo_shortcode,
						'image' => $promo_image,
						'start_date' => $promo_start_date,
						'end_date' => $promo_end_date,
						'active' => $active_banner
					)
				);
			}
		} else {
			$wpdb->insert(
				$table_name,
				array(
					'title' => $promo_title,
					'shortcode' => $promo_shortcode,
					'image' => $promo_image,
					'start_date' => $promo_start_date,
					'end_date' => $promo_end_date,
					'active' => $active_banner
				)
			);
		}

		clear_cache();

		// Пренасочи, за да избегнеш повторно изпращане на формата
		wp_redirect(admin_url('admin.php?page=promotions#msg=Банерът е качен'));
		exit;
	}
}

function handle_edit_promo()
{
	if (isset($_POST['action']) && $_POST['action'] == 'edit_promo' && isset($_POST['promo_id'])) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'doors_promotions';
		$promo_id = intval($_POST['promo_id']);
		$promo_title = sanitize_text_field($_POST['promo_title']);
		$promo_shortcode = sanitize_text_field($_POST['promo_shortcode']);
		$promo_start_date = date_format(date_create_from_format('d/m/Y', sanitize_text_field($_POST['promo_start_date'])), 'Y-m-d');
		$promo_end_date = date_format(date_create_from_format('d/m/Y', sanitize_text_field($_POST['promo_end_date'])), 'Y-m-d');
		$promo_active = $_POST['promo_active'] == 'on' ? '1' : '0';

		$wpdb->update(
			$table_name,
			[
				'title' => $promo_title,
				'shortcode' => $promo_shortcode,
				'start_date' => $promo_start_date,
				'end_date' => $promo_end_date,
				'active' => $promo_active
			],
			[
				'id' => $promo_id
			]
		);

		clear_cache();

		if (preg_match('/worktime/', $promo_shortcode)) {
			wp_redirect(admin_url('admin.php?page=promotions&filter=worktime#msg=Успешно редактиране.'));
		} else if (preg_match('/text/', $promo_shortcode)) {
			wp_redirect(admin_url('admin.php?page=promotions&filter=text#msg=Успешно редактиране.'));
		} else {
			wp_redirect(admin_url('admin.php?page=promotions#msg=Успешно редактиране.'));
		}

		exit;
	} else {
		var_dump($_POST);
		exit;
	}
}

function handle_delete_promo()
{
	if (isset($_POST['action']) && $_POST['action'] == 'delete_promo' && isset($_POST['promo_id'])) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'doors_promotions';
		$promo_id = intval($_POST['promo_id']);

		// Вземи данните за изображението преди да изтриеш записа
		$promo = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $promo_id));
		if ($promo) {
			// Преброй колко пъти се среща това изображение в базата данни
			$image_count = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table_name WHERE image = %s", $promo->image));

			// Изтрий записа от базата данни
			$wpdb->delete($table_name, array('id' => $promo_id));

			if ($image_count == 1) {
				$image_path = str_replace(site_url(), ABSPATH, $promo->image);
				if (file_exists($image_path)) {
					unlink($image_path);
				}
			}
		}

		clear_cache();

		// Пренасочи, за да избегнеш повторно изпращане на формата
		wp_redirect(admin_url('admin.php?page=promotions#msg=Банерът е изтрит'));
		exit;
	} else {
		// За отстраняване на грешки
		var_dump($_POST);
		exit;
	}
}

function handle_delete_expired()
{
	if (isset($_POST['action']) && $_POST['action'] == 'delete_expired') {
		global $wpdb;
		$table_name = $wpdb->prefix . 'doors_promotions';
		$today = date('Y-m-d');

		// Get all expired records
		$expired_promos = $wpdb->get_results($wpdb->prepare("SELECT * FROM $table_name WHERE end_date < %s", $today));

		foreach ($expired_promos as $promo) {
			// Count how many times this image is referenced in the database
			$image_count = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table_name WHERE image = %s", $promo->image));

			// Delete the record from the database
			$wpdb->delete($table_name, array('id' => $promo->id));

			// If the image is only referenced once, delete the image file
			if ($image_count == 1) {
				$image_path = str_replace(site_url(), ABSPATH, $promo->image);
				if (file_exists($image_path)) {
					unlink($image_path);
				}
			}
		}

		clear_cache();

		// Redirect to avoid form resubmission
		wp_redirect(admin_url('admin.php?page=promotions#msg=Банерите са изтрити'));
		exit;
	} else {
		// For debugging
		var_dump($_POST);
		exit;
	}
}

function handle_activate_all()
{
	if (isset($_POST['action']) && $_POST['action'] == 'activate_all') {
		global $wpdb;
		$table_name = $wpdb->prefix . 'doors_promotions';

		$sql = $wpdb->prepare(
			"UPDATE $table_name SET active = %d WHERE active = %d AND end_date > %s",
			1,
			0,
			current_time('mysql')
		);

		$wpdb->query($sql);

		clear_cache();
		wp_redirect(admin_url('admin.php?page=promotions#msg=Неактивните банери са активирани'));
		exit;
	} else {
		var_dump($_POST);
		exit;
	}
}

function extend_allowed_tags($tags)
{
	$tags['marquee'] = array(
		'behavior' => true,
		'bgcolor' => true,
		'direction' => true,
		'loop' => true,
	);
	$tags['style'] = array();
	return $tags;
}

add_filter('wp_kses_allowed_html', 'extend_allowed_tags');

function handle_add_new_template()
{
	if (isset($_POST['action']) && $_POST['action'] == 'add_new_template') {
		global $wpdb;
		$table_name = $wpdb->prefix . 'doors_promotions_templates';
		$shortcode = sanitize_text_field($_POST['shortcode']);
		$shortcode_name = sanitize_text_field($_POST['shortcode_name']);
		$template_content = wp_kses_post($_POST['template_content'], 'extend_allowed_tags');

		$wpdb->insert(
			$table_name,
			[
				'shortcode' => $shortcode,
				'shortcode_name' => $shortcode_name,
				'template_content' => $template_content
			]
		);

		clear_cache();

		wp_redirect(admin_url('admin.php?page=promotions_templates#msg=Шабонът е добавен'));
		exit;
	} else {
		var_dump($_POST);
		exit;
	}
}

function handle_update_template()
{
	if (isset($_POST['promo_id']) && isset($_POST['template_content'])) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'doors_promotions_templates';
		$promo_id = intval($_POST['promo_id']);
		$shortcode = sanitize_text_field($_POST['shortcode']);
		$shortcode_name = sanitize_text_field($_POST['shortcode_name']);
		$template_content = wp_kses_post($_POST['template_content'], 'extend_allowed_tags');

		// Correct the array structure
		$wpdb->update(
			$table_name,
			[
				'shortcode' => $shortcode,
				'shortcode_name' => $shortcode_name,
				'template_content' => $template_content
			],
			['id' => $promo_id]
		);

		clear_cache();

		if (preg_match('/worktime/', $shortcode)) {
			wp_redirect(admin_url('admin.php?page=promotions_templates&filter=worktime#msg=Шаблонът е редактиран.'));
		} else if (preg_match('/text/', $shortcode)) {
			wp_redirect(admin_url('admin.php?page=promotions_templates&filter=text#msg=Шаблонът е редактиран.'));
		} else {
			wp_redirect(admin_url('admin.php?page=promotions_templates#msg=Шаблонът е редактиран.'));
		}

		exit;
	} else {
		var_dump($_POST);
		exit;
	}
}

function handle_delete_template()
{
	if (isset($_POST['action']) && $_POST['action'] == 'delete_template' && isset($_POST['promo_id'])) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'doors_promotions_templates';
		$promo_id = intval($_POST['promo_id']);

		$wpdb->delete($table_name, array('id' => $promo_id));

		clear_cache();

		wp_redirect(admin_url('admin.php?page=promotions_templates#msg=Шаблонът е изтрит'));
		exit;
	}
}

function filter($redirect_to_page)
{
	return "<form method='GET' class='w-auto float-end' onChange='this.submit()'>
				<input type='hidden' name='page' value='{$redirect_to_page}'>
				<select name='filter'>
					<option value=''>Промоции</a>
					<option value='worktime' " . (isset($_GET['filter']) && $_GET['filter'] == 'worktime' ? 'selected' : '') . ">Работни времена</a>
					<option value='text' " . (isset($_GET['filter']) && $_GET['filter'] == 'text' ? 'selected' : '') . ">Текстове</a>
					<option value='css' " . (isset($_GET['filter']) && $_GET['filter'] == 'css' ? 'selected' : '') . ">CSS</a>
				</select>
			</form>";
}
function filter_where_clause()
{
	$filter = isset($_GET['filter']) && in_array($_GET['filter'], ['worktime', 'text', 'css']) ? $_GET['filter'] : '';

	$where_clause = $filter ? "shortcode LIKE '%{$filter}%'" : "shortcode NOT LIKE '%worktime%' AND shortcode NOT LIKE '%text%' AND shortcode NOT LIKE '%css%'";

	return $where_clause;
}

function promotions_menu()
{
	add_menu_page(
		'Банери за промоции',
		'Промо банери',
		'manage_options',
		'promotions',
		'promotions_list_page',
		'dashicons-money-alt',
		1
	);

	add_submenu_page(
		'promotions',
		'Качване на банер',
		'Качване на банер',
		'manage_options',
		'promotions_settings',
		'promotions_settings_page'
	);

	add_submenu_page(
		'promotions',
		'Шаблони',
		'Шаблони',
		'manage_options',
		'promotions_templates',
		'promotions_templates_page'
	);
}

function create_promotions_tables()
{
	global $wpdb;

	$promotions_table = $wpdb->prefix . 'doors_promotions';
	$templates_table = $wpdb->prefix . 'doors_promotions_templates';

	$charset_collate = $wpdb->get_charset_collate();

	$sql_promotions = "CREATE TABLE IF NOT EXISTS $promotions_table (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        category mediumint(9) NULL,
        shortcode varchar(255) NOT NULL,
        title varchar(255) NOT NULL,
        image varchar(255) NOT NULL,
        start_date date NOT NULL,
        end_date date NOT NULL,
        PRIMARY KEY (id)
    ) $charset_collate;";

	$sql_templates = "CREATE TABLE IF NOT EXISTS $templates_table (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        shortcode varchar(255) NOT NULL,
        shortcode_name varchar(255) NOT NULL,
        template_content text NOT NULL,
        PRIMARY KEY (id)
    ) $charset_collate;";

	require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
	dbDelta($sql_promotions);
	dbDelta($sql_templates);

	// Check if 'active' column exists, and add it if it doesn't
	$column = $wpdb->get_results("SHOW COLUMNS FROM $promotions_table LIKE 'active'");
	if (empty($column)) {
		$wpdb->query("ALTER TABLE $promotions_table ADD COLUMN active TINYINT(1) NOT NULL DEFAULT 1");
	}
}

function remove_admin_notices()
{
	remove_all_actions('admin_notices');
	remove_all_actions('all_admin_notices');
}

register_activation_hook(__FILE__, 'create_promotions_tables');

add_action('init', 'initialize_shortcodes', 20);

add_action('admin_init', 'remove_admin_notices');
add_action('admin_enqueue_scripts', 'load_libraries');
add_action('admin_enqueue_scripts', 'enqueue_promotions_script');
add_action('admin_menu', 'promotions_menu');
add_action('admin_post_submit_promo', 'handle_promotions_form');
add_action('admin_post_edit_promo', 'handle_edit_promo');
add_action('admin_post_delete_promo', 'handle_delete_promo');
add_action('admin_post_delete_expired', 'handle_delete_expired');
add_action('admin_post_activate_all', 'handle_activate_all');
add_action('admin_post_add_new_template', 'handle_add_new_template');
add_action('admin_post_update_template', 'handle_update_template');
add_action('admin_post_delete_template', 'handle_delete_template');
