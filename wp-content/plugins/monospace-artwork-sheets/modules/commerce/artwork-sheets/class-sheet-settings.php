<?php
/**
 * Artwork Sheet Settings
 *
 * @package monospace-art-theme
 */

class Monospace_Sheet_Settings {
    private static $instance = null;

    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function render_page() {
        if (isset($_POST['save_sheet_settings'])) {
            $this->save_settings();
        }

        $artist_name = get_option('monospace_sheet_artist_name', 'Hens Breet');
        $artist_website = get_option('monospace_sheet_artist_website', 'https://art.monospace.com');
        $footer_content = get_option('monospace_sheet_footer_content', "{artist_name}\n{artist_website}");
        ?>
        <div class="wrap">
            <h1>Artwork Sheet Settings</h1>

            <form method="post">
                <?php wp_nonce_field('save_sheet_settings', 'sheet_settings_nonce'); ?>

                <table class="form-table">
                    <tr>
                        <th><label for="artist_name">Artist Name</label></th>
                        <td>
                            <input type="text" id="artist_name" name="artist_name"
                                   value="<?php echo esc_attr($artist_name); ?>" class="regular-text">
                        </td>
                    </tr>
                    <tr>
                        <th><label for="artist_website">Artist Website</label></th>
                        <td>
                            <input type="url" id="artist_website" name="artist_website"
                                   value="<?php echo esc_url($artist_website); ?>" class="regular-text">
                        </td>
                    </tr>
                </table>

                <h2>Footer Configuration</h2>
                <p class="description">Available placeholders: {artist_name}, {artist_website}</p>
                <table class="form-table">
                    <tr>
                        <th><label for="footer_content">Footer Content</label></th>
                        <td>
                            <textarea id="footer_content" name="footer_content" rows="5"
                                      class="large-text code"><?php echo esc_textarea($footer_content); ?></textarea>
                            <p class="description">Use placeholders above. Leave blank to hide footer. HTML allowed.</p>
                        </td>
                    </tr>
                </table>

                <p class="submit">
                    <button type="submit" name="save_sheet_settings" class="button button-primary">Save Settings</button>
                </p>
            </form>
        </div>
        <?php
    }

    private function save_settings() {
        if (!wp_verify_nonce($_POST['sheet_settings_nonce'], 'save_sheet_settings')) {
            wp_die('Security check failed');
        }

        update_option('monospace_sheet_artist_name', sanitize_text_field($_POST['artist_name']));
        update_option('monospace_sheet_artist_website', esc_url_raw($_POST['artist_website']));
        update_option('monospace_sheet_footer_content', wp_kses_post($_POST['footer_content']));

        echo '<div class="notice notice-success"><p>Settings saved.</p></div>';
    }

    public static function get_footer_content($data) {
        $footer_content = get_option('monospace_sheet_footer_content', '');

        if (empty($footer_content)) {
            return '';
        }

        $replacements = [
            '{artist_name}' => $data['artist_name'],
            '{artist_website}' => $data['artist_website']
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $footer_content);
    }
}