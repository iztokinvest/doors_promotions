<?php
class WP_Promotions_Updater {
    private $slug;
    private $pluginData;
    private $repo;
    private $githubAPIResult;

    public function __construct($plugin_file) {
        add_filter('pre_set_site_transient_update_plugins', [$this, 'set_update_transient']);
        add_filter('plugins_api', [$this, 'set_plugin_info'], 10, 3);
        add_filter('upgrader_post_install', [$this, 'post_install'], 10, 3);
        $this->slug = plugin_basename($plugin_file);
        $this->pluginData = get_plugin_data($plugin_file);
        $this->repo = 'iztokinvest/doors_promotions';
    }

    private function get_repository_info() {
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

    public function set_update_transient($transient) {
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

    public function set_plugin_info($false, $action, $response) {
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

    public function post_install($true, $hook_extra, $result) {
        global $wp_filesystem;
        $plugin_folder = WP_PLUGIN_DIR . '/' . dirname($this->slug);
        
        // Move files while excluding the 'data' folder
        $data_folder = $plugin_folder . '/data';
        $temp_data_folder = $result['destination'] . '/data';

        if ($wp_filesystem->exists($temp_data_folder)) {
            $wp_filesystem->move($temp_data_folder, $data_folder . '_temp', true);
        }
        
        // Move all files except the 'data' folder
        $this->custom_recursive_copy($result['destination'], $plugin_folder);

        // Restore the 'data' folder
        if ($wp_filesystem->exists($data_folder . '_temp')) {
            $wp_filesystem->move($data_folder . '_temp', $data_folder, true);
        }

        $wp_filesystem->delete($result['destination'], true);

        activate_plugin($this->slug);
        return $result;
    }

    private function custom_recursive_copy($source, $destination) {
        global $wp_filesystem;
        $dir = opendir($source);
        @mkdir($destination);

        while (($file = readdir($dir)) !== false) {
            if (($file != '.') && ($file != '..') && ($file != 'data')) {
                if (is_dir($source . '/' . $file)) {
                    $this->custom_recursive_copy($source . '/' . $file, $destination . '/' . $file);
                } else {
                    $wp_filesystem->copy($source . '/' . $file, $destination . '/' . $file);
                }
            }
        }
        closedir($dir);
    }
}

if (is_admin()) {
    new WP_Promotions_Updater(__FILE__);
}
