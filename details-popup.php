<?php
// Include WordPress functions
require_once(dirname(__FILE__, 4) . '/wp-load.php'); // Adjust the path if necessary
?>
<!DOCTYPE html>
<html lang="en">

<head>
	<meta charset="UTF-8">
	<title><?php _e('Още детайли', 'your-plugin-textdomain'); ?></title>
	<?php wp_head(); ?>
</head>

<body>
	<div class="wrap" style="padding: 10px;">
		<h1><?php _e('Информация', 'your-plugin-textdomain'); ?></h1>
		<p>
			<?php
			_e('<b>URL за изчистване на кеша:</b><br>', 'your-plugin-textdomain');
			echo esc_html(dirname(__FILE__) . DIRECTORY_SEPARATOR . 'clear_cache.php');
			?>
		</p>
	</div>
	<?php wp_footer(); ?>
</body>

</html>