<?php

declare(strict_types=1);

namespace Addons\Admin;

use Addons\Contract\HasHooks;
use Addons\Service\AddOnsService;

defined('ABSPATH') || exit;

/**
 * Admin settings page registered as a WooCommerce submenu.
 *
 * Stores the master toggle in the `addons_settings` option (array). Per-product
 * add-on definitions are edited in the product "Add-Ons" data tab, not here.
 * All output is escaped; all input is sanitised on save.
 */
final class Settings implements HasHooks
{
    private const PAGE = 'addons-settings';

    public function registerHooks(): void
    {
        add_action('admin_menu', [$this, 'addMenuPage']);
        add_action('admin_init', [$this, 'registerSettings']);
    }

    public function addMenuPage(): void
    {
        add_submenu_page(
            'woocommerce',
            __('Add-Ons', 'addons'),
            __('Add-Ons', 'addons'),
            'manage_woocommerce',
            self::PAGE,
            [$this, 'renderPage'],
        );
    }

    public function registerSettings(): void
    {
        register_setting(
            self::PAGE,
            AddOnsService::OPTION,
            [
                'type'              => 'array',
                'sanitize_callback' => [$this, 'sanitize'],
            ],
        );

        // The menu uses manage_woocommerce; align the options.php save capability
        // so shop managers (not just admins with manage_options) can save.
        add_filter(
            'option_page_capability_' . self::PAGE,
            static fn (): string => 'manage_woocommerce',
        );
    }

    public function renderPage(): void
    {
        if (! current_user_can('manage_woocommerce')) {
            return;
        }

        $settings = $this->settings();
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            <form method="post" action="options.php">
                <?php settings_fields(self::PAGE); ?>

                <table class="form-table" role="presentation">
                    <tbody>
                        <tr>
                            <th scope="row"><?php esc_html_e('Enable add-ons', 'addons'); ?></th>
                            <td>
                                <label for="addons_enabled">
                                    <input
                                        type="checkbox"
                                        id="addons_enabled"
                                        name="<?php echo esc_attr(AddOnsService::OPTION); ?>[enabled]"
                                        value="1"
                                        <?php checked((bool) ($settings['enabled'] ?? false), true); ?>
                                    />
                                    <?php esc_html_e('Show per-product add-on fields on the product page and apply price deltas in the cart.', 'addons'); ?>
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="addons_group_title"><?php esc_html_e('Group heading', 'addons'); ?></label>
                            </th>
                            <td>
                                <input
                                    type="text"
                                    id="addons_group_title"
                                    class="regular-text"
                                    name="<?php echo esc_attr(AddOnsService::OPTION); ?>[group_title]"
                                    value="<?php echo esc_attr((string) ($settings['group_title'] ?? '')); ?>"
                                />
                                <p class="description">
                                    <?php esc_html_e('Heading shown above the fields on the product page. Leave empty to hide it.', 'addons'); ?>
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Display', 'addons'); ?></th>
                            <td>
                                <fieldset>
                                    <legend class="screen-reader-text">
                                        <span><?php esc_html_e('Display', 'addons'); ?></span>
                                    </legend>
                                    <label for="addons_show_prices">
                                        <input
                                            type="checkbox"
                                            id="addons_show_prices"
                                            name="<?php echo esc_attr(AddOnsService::OPTION); ?>[show_prices]"
                                            value="1"
                                            <?php checked((bool) ($settings['show_prices'] ?? false), true); ?>
                                        />
                                        <?php esc_html_e('Show the price next to paid options.', 'addons'); ?>
                                    </label>
                                    <br />
                                    <label for="addons_show_required">
                                        <input
                                            type="checkbox"
                                            id="addons_show_required"
                                            name="<?php echo esc_attr(AddOnsService::OPTION); ?>[show_required]"
                                            value="1"
                                            <?php checked((bool) ($settings['show_required'] ?? false), true); ?>
                                        />
                                        <?php esc_html_e('Mark required fields with an asterisk.', 'addons'); ?>
                                    </label>
                                    <br />
                                    <label for="addons_card_style">
                                        <input
                                            type="checkbox"
                                            id="addons_card_style"
                                            name="<?php echo esc_attr(AddOnsService::OPTION); ?>[card_style]"
                                            value="1"
                                            <?php checked((bool) ($settings['card_style'] ?? false), true); ?>
                                        />
                                        <?php esc_html_e('Wrap the options in a bordered card.', 'addons'); ?>
                                    </label>
                                </fieldset>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <p class="description">
                    <?php esc_html_e('Define each product\'s add-ons in the product editor, under the "Add-Ons" tab in the Product data panel.', 'addons'); ?>
                </p>

                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }

    /**
     * Sanitise the submitted settings before save.
     *
     * @param mixed $raw
     * @return array<string, mixed>
     */
    public function sanitize(mixed $raw): array
    {
        if (! is_array($raw)) {
            $raw = [];
        }

        $defaults = $this->settings();

        return array_merge($defaults, [
            'enabled'       => ! empty($raw['enabled']),
            'group_title'   => isset($raw['group_title']) ? sanitize_text_field((string) $raw['group_title']) : '',
            'show_prices'   => ! empty($raw['show_prices']),
            'show_required' => ! empty($raw['show_required']),
            'card_style'    => ! empty($raw['card_style']),
        ]);
    }

    /**
     * Stored settings merged over packaged defaults.
     *
     * @return array<string, mixed>
     */
    private function settings(): array
    {
        $stored = get_option(AddOnsService::OPTION, []);

        if (! is_array($stored)) {
            $stored = [];
        }

        /** @var array<string, mixed> $defaults */
        $defaults = require ADDONS_DIR . 'config/defaults.php';

        return array_merge($defaults, $stored);
    }
}
