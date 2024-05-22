<?php
class WP_GitHub_Updater {
    private $plugin_file;
    private $plugin_data;
    private $github_api_result;
    private $slug;
    private $plugin_basename;
    private $github_username;
    private $github_repository;
    private $github_token;

    public function __construct( $plugin_file ) {
        add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'set_update_transient' ) );
        add_filter( 'plugins_api', array( $this, 'set_plugin_info' ), 10, 3 );
        add_filter( 'upgrader_post_install', array( $this, 'post_install' ), 10, 3 );

        $this->plugin_file = $plugin_file;
        $this->plugin_data = get_plugin_data( $this->plugin_file );
        $this->plugin_basename = plugin_basename( $this->plugin_file );
        $this->slug = basename( $this->plugin_file, '.php' );
        $this->github_username = 'iztokinvest';
        $this->github_repository = 'doors_promotions';
    }

    private function get_repository_info() {
        if ( is_null( $this->github_api_result ) ) {
            $url = "https://api.github.com/repos/{$this->github_username}/{$this->github_repository}/releases";
            $response = wp_remote_get( $url );
            if ( is_wp_error( $response ) ) {
                return;
            }
            $this->github_api_result = json_decode( wp_remote_retrieve_body( $response ) );
        }
    }

    public function set_update_transient( $transient ) {
        if ( empty( $transient->checked ) ) {
            return $transient;
        }

        $this->get_repository_info();

        $do_update = version_compare( $this->github_api_result[0]->tag_name, $this->plugin_data['Version'], 'gt' );

        if ( $do_update ) {
            $package = $this->github_api_result[0]->zipball_url;
            $slug = $this->plugin_basename;

            $plugin = array(
                'url' => $this->plugin_data['PluginURI'],
                'slug' => $slug,
                'package' => $package,
                'new_version' => $this->github_api_result[0]->tag_name,
            );

            $transient->response[$slug] = (object) $plugin;
        }

        return $transient;
    }

    public function set_plugin_info( $false, $action, $response ) {
        $this->get_repository_info();

        if ( empty( $response->slug ) || $response->slug != $this->slug ) {
            return $false;
        }

        $response->last_updated = $this->github_api_result[0]->published_at;
        $response->slug = $this->slug;
        $response->plugin_name = $this->plugin_data['Name'];
        $response->version = $this->github_api_result[0]->tag_name;
        $response->author = $this->plugin_data['AuthorName'];
        $response->homepage = $this->plugin_data['PluginURI'];

        $response->sections = array(
            'description' => $this->plugin_data['Description'],
            'changelog' => $this->github_api_result[0]->body,
        );

        $download_link = $this->github_api_result[0]->zipball_url;
        $response->download_link = $download_link;

        return $response;
    }

    public function post_install( $true, $hook_extra, $result ) {
        global $wp_filesystem;

        $install_directory = plugin_dir_path( $this->plugin_file );
        $wp_filesystem->move( $result['destination'], $install_directory );
        $result['destination'] = $install_directory;

        if ( $this->plugin_data['Version'] != $this->github_api_result[0]->tag_name ) {
            activate_plugin( $this->plugin_basename );
        }

        return $result;
    }
}

if ( is_admin() ) {
    new WP_GitHub_Updater( __FILE__ );
}