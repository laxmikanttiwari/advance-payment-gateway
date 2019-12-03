<?php
/*
 * Plugin Name: Advance Bank Transfer
 * Plugin URI: www.test.com
 * Description: Woocommerce addon payment gateway for bank transfer
 * Author: Laxmi Kant Tiwari
 * Version: 1.0.1
 *
 * 
 */

/*
 * This action hook registers our PHP class as a WooCommerce payment gateway
 */

$active_plugins = apply_filters('active_plugins', get_option('active_plugins'));
if (in_array('woocommerce/woocommerce.php', $active_plugins)) {
    add_filter('woocommerce_payment_gateways', 'add_gateway_class');

    function add_gateway_class($gateways) {
        $gateways[] = 'WC_Advance_Gateway';
        return $gateways;
    }

    add_action('plugins_loaded', 'advance_payment_init_gateway_class');
}

function advance_payment_init_gateway_class() {

    class WC_Advance_Gateway extends WC_Payment_Gateway {

        private $SUCCESS_REDIRECT_URL = "/checkout/order-received/";
        private $types = array('image/jpg' => 'jpg', 'image/jpeg' => 'jpeg', 'image/png' => 'png');
        public $testing = '';

        public function __construct() {
            $this->id = 'advance_bank_transfer'; //Gateway ID
            $this->icon = ''; //Gateway ICON
            $this->has_fields = true;
            $this->method_title = 'Advanced Bank Transfer';
            $this->method_description = 'Woocommerce Add On for Direct Bank Transfer';
            $this->supports = array(
                'products'
            );
            $this->init_form_fields();
            $this->init_settings();
            $this->title = $this->get_option('title');
            $this->description = $this->get_option('description');
            $this->instructions = $this->get_option('instructions');
            $this->enabled = $this->get_option('enabled');
            $this->allow_for_countries = $this->get_option('allow_for_countries');
            $this->allowed_type = $this->get_option('allowed_mime_types');
            $this->siteUrl = get_site_url();
            $this->account_details = get_option(
                    'woocommerce_advance_gateway_accounts', array(
                array(
                    'account_name' => $this->get_option('account_name'),
                    'account_number' => $this->get_option('account_number'),
                    'sort_code' => $this->get_option('sort_code'),
                    'bank_name' => $this->get_option('bank_name'),
                    'iban' => $this->get_option('iban'),
                    'bic' => $this->get_option('bic'),
                ),
                    )
            );
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
            add_action('woocommerce_api_' . $this->SUCCESS_CALLBACK_URL, array($this, 'payment_success'));
            add_action('woocommerce_api_' . $this->FAILURE_CALLBACK_URL, array($this, 'payment_failure'));
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'save_account_details'));
            add_filter('woocommerce_available_payment_gateways', array($this, 'advance_payment_gateway_disable'));
        }

        public function init_form_fields() {
            global $woocommerce;
            $countries_obj = new WC_Countries();
            $countries = $countries_obj->__get('countries');
            $allowed_types = $this->types;
            $this->form_fields = array(
                'enabled' => array(
                    'title' => 'Enable/Disable',
                    'label' => 'Enable Advance Payment Gateway',
                    'type' => 'checkbox',
                    'description' => '',
                    'default' => 'no'
                ),
                'title' => array(
                    'title' => 'Title',
                    'type' => 'text',
                    'description' => 'This controls the title which the user sees during checkout.',
                    'default' => 'Advance Bank Transfer',
                    'desc_tip' => true,
                ),
                'description' => array(
                    'title' => 'Description',
                    'type' => 'textarea',
                    'description' => 'This controls the description which the user sees during checkout.',
                    'default' => 'Please upload your payment receipt after Bank Transfer.',
                ),
                'instructions' => array(
                    'title' => 'Instructions',
                    'type' => 'textarea',
                    'description' => 'This controls the description which the user sees during checkout.',
                    'default' => '',
                ),
                'account_details' => array(
                    'type' => 'account_details',
                ),
                'allowed_mime_types' => array(
                    'title' => __('Supported MIME Types', 'allowed_mime'),
                    'type' => 'multiselect',
                    'class' => 'chosen_select',
                    'css' => 'width: 450px;',
                    'default' => '',
                    'description' => __('Leave blank to enable for all types.', 'allowed_mime'),
                    'options' => $allowed_types,
                    'desc_tip' => true,
                ),
                'allow_for_countries' => array(
                    'title' => __('Enable For Country', 'advance_gateway'),
                    'type' => 'multiselect',
                    'class' => 'chosen_select',
                    'css' => 'width: 450px;',
                    'default' => '',
                    'description' => __('Leave blank to enable for all methods.', 'advance_gateway'),
                    'options' => $countries,
                    'desc_tip' => true,
                ),
            );
        }

        public function payment_scripts() {

            if (!is_cart() && !is_checkout() && !isset($_GET['pay_for_order'])) {
                return;
            }

            if ('no' === $this->enabled) {
                return;
            }
        }

        public function payment_fields() {
            if ($this->description) {
                echo wpautop(wp_kses_post($this->description)); // Display some description before the payment form
            }
            echo '<fieldset id="wc-' . esc_attr($this->id) . '-cc-form" class="wc-advance-payment-form wc-payment-form" style="background:transparent;">';
            do_action('woocommerce_credit_card_form_start', $this->id);
            ?>
            <div class="form-row form-row-wide">
                <label>Payment Receipt <span class="required">*</span></label>
                <input type="file" name="payment_slip" id="payment_slip">
                <input type="hidden" value="<?php echo admin_url('admin-ajax.php'); ?>" id="ajax_url">
            </div>
            <div id="show_uploaded_file">
                <input type="hidden" name="imagepath" value="" id="imagepath">
            </div>
            <div class="clear"></div>
            <script>
                jQuery(document).ready(function () {
                    var ajax_url = "<?php echo admin_url('admin-ajax.php'); ?>";
                    var allowed_type = '<?php echo json_encode($this->allowed_type); ?>';
                    jQuery("#payment_slip").change(function () {
                        if (jQuery(this).val() != '') {
                            var file_data = jQuery('#payment_slip').prop('files')[0];
                            var form_data = new FormData();
                            form_data.append('file', file_data);
                            form_data.append('action', 'receipt_upload');
                            form_data.append('allowed_types', allowed_type);
                            jQuery.ajax({
                                url: ajax_url, // there on the admin side, do-it-yourself on the front-end
                                data: form_data,
                                type: 'POST',
                                contentType: false,
                                processData: false,
                                success: function (response) {
                                    var result = JSON.parse(response);
                                    if (result.url) {
                                        var image_url = result.url;
                                        jQuery("#imagepath").val(image_url);
                                        jQuery("#show_uploaded_file").append("<div data-url='" + image_url + "' id='remove_img' class='remove_img dashicons-no-alt dashicons-before dashicons-admin-generic text-danger'><br></div>");
                                        jQuery("#show_uploaded_file").append("<img height='50' width='50' src='" + image_url + "'>");
                                    } else {
                                        jQuery("#show_uploaded_file").append("Filetype not allowed");
                                    }
                                }
                            });
                        }
                    });
                });
            </script>   
            <?php
            do_action('woocommerce_credit_card_form_end', $this->id);

            echo '<div class="clear"></div></fieldset>';
        }

        public function validate_fields() {
            if (empty($_POST['imagepath'])) {
                wc_add_notice('Payment Recipt is required!', 'error');
                return false;
            }
            return true;
        }

        public function process_payment($order_id) {

            global $woocommerce;
            $order = new WC_Order($order_id);
            $payment_receipt_path = $_POST['imagepath'];
            update_post_meta($order_id, '_receipt_path', $payment_receipt_path);
            $order->update_status('on-hold', __('Your order will be shipped after the verification of your transaction.', 'woocommerce')); // Mark as on-hold
            $order->reduce_order_stock(); // Reduce stock levels
            $woocommerce->cart->empty_cart(); // Remove cart
            $url = $this->siteUrl . $this->SUCCESS_REDIRECT_URL . $order_id . '/?key=' . $order->order_key; // Return thankyou redirect
            return array(
                'result' => 'success',
                'redirect' => $url
            );
        }

        /**
         * Disable payment gateway if subscriptions or countries not allowed
         */
        public function advance_payment_gateway_disable($available_gateways) {
            if (!empty($this->allow_for_countries) && !empty(WC()->customer)) {
                if (isset($available_gateways['advance_bank_transfer']) && (!in_array(WC()->customer->get_billing_country(), $this->allow_for_countries))) {
                    unset($available_gateways['advance_bank_transfer']);
                }
            }
            return $available_gateways;
        }

        public function generate_account_details_html() {
            ob_start();
            $country = WC()->countries->get_base_country();
            $locale = $this->get_country_locale();
            $sortcode = isset($locale[$country]['sortcode']['label']) ? $locale[$country]['sortcode']['label'] : __('Sort code', 'woocommerce');
            ?>
            <tr valign="top">
                <th scope="row" class="titledesc"><?php esc_html_e('Account details:', 'woocommerce'); ?></th>
                <td class="forminp" id="advance_gateway_accounts">
                    <div class="wc_input_table_wrapper">
                        <table class="widefat wc_input_table sortable" cellspacing="0">
                            <thead>
                                <tr>
                                    <th class="sort">&nbsp;</th>
                                    <th><?php esc_html_e('Account name', 'woocommerce'); ?></th>
                                    <th><?php esc_html_e('Account number', 'woocommerce'); ?></th>
                                    <th><?php esc_html_e('Bank name', 'woocommerce'); ?></th>
                                    <th><?php echo esc_html($sortcode); ?></th>
                                    <th><?php esc_html_e('IBAN', 'woocommerce'); ?></th>
                                    <th><?php esc_html_e('BIC / Swift', 'woocommerce'); ?></th>
                                </tr>
                            </thead>
                            <tbody class="accounts">
                                <?php
                                $i = -1;
                                if ($this->account_details) {
                                    foreach ($this->account_details as $account) {
                                        $i++;
                                        echo '<tr class="account">
                                        <td class="sort"></td>
                                        <td><input type="text" value="' . esc_attr(wp_unslash($account['account_name'])) . '" name="advance_gateway_account_name[' . esc_attr($i) . ']" /></td>
                                        <td><input type="text" value="' . esc_attr($account['account_number']) . '" name="advance_gateway_account_number[' . esc_attr($i) . ']" /></td>
                                        <td><input type="text" value="' . esc_attr(wp_unslash($account['bank_name'])) . '" name="advance_gateway_bank_name[' . esc_attr($i) . ']" /></td>
                                        <td><input type="text" value="' . esc_attr($account['sort_code']) . '" name="advance_gateway_sort_code[' . esc_attr($i) . ']" /></td>
                                        <td><input type="text" value="' . esc_attr($account['iban']) . '" name="advance_gateway_iban[' . esc_attr($i) . ']" /></td>
                                        <td><input type="text" value="' . esc_attr($account['bic']) . '" name="advance_gateway_bic[' . esc_attr($i) . ']" /></td>
                                        </tr>';
                                    }
                                }
                                ?>
                            </tbody>
                            <tfoot>
                                <tr>
                                    <th colspan="7"><a href="#" class="add button"><?php esc_html_e('+ Add account', 'woocommerce'); ?></a> <a href="#" class="remove_rows button"><?php esc_html_e('Remove selected account(s)', 'woocommerce'); ?></a></th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </td>
            </tr>
            <?php
            return ob_get_clean();
        }

        public function save_account_details() {
            $accounts = array();
            if (isset($_POST['advance_gateway_account_name']) && isset($_POST['advance_gateway_account_number']) && isset($_POST['advance_gateway_bank_name']) && isset($_POST['advance_gateway_sort_code']) && isset($_POST['advance_gateway_iban']) && isset($_POST['advance_gateway_bic'])) {
                $account_names = wc_clean(wp_unslash($_POST['advance_gateway_account_name']));
                $account_numbers = wc_clean(wp_unslash($_POST['advance_gateway_account_number']));
                $bank_names = wc_clean(wp_unslash($_POST['advance_gateway_bank_name']));
                $sort_codes = wc_clean(wp_unslash($_POST['advance_gateway_sort_code']));
                $ibans = wc_clean(wp_unslash($_POST['advance_gateway_iban']));
                $bics = wc_clean(wp_unslash($_POST['advance_gateway_bic']));
                foreach ($account_names as $i => $name) {
                    if (!isset($account_names[$i])) {
                        continue;
                    }
                    $accounts[] = array(
                        'account_name' => $account_names[$i],
                        'account_number' => $account_numbers[$i],
                        'bank_name' => $bank_names[$i],
                        'sort_code' => $sort_codes[$i],
                        'iban' => $ibans[$i],
                        'bic' => $bics[$i],
                    );
                }
            }
            update_option('woocommerce_advance_gateway_accounts', $accounts);
        }

        private function bank_details($order_id = '') {
            if (empty($this->account_details)) {
                return;
            }

            $order = wc_get_order($order_id);
            $country = $order->get_billing_country();
            $locale = $this->get_country_locale();
            $sortcode = isset($locale[$country]['sortcode']['label']) ? $locale[$country]['sortcode']['label'] : __('Sort code', 'woocommerce');
            $advance_gateway_accounts = apply_filters('woocommerce_advance_gateway_accounts', $this->account_details);
            if (!empty($advance_gateway_accounts)) {
                $account_html = '';
                $has_details = false;
                foreach ($advance_gateway_accounts as $advance_gateway_account) {
                    $advance_gateway_account = (object) $advance_gateway_account;
                    if ($advance_gateway_account->account_name) {
                        $account_html .= '<h3 class="wc-bacs-bank-details-account-name">' . wp_kses_post(wp_unslash($advance_gateway_account->account_name)) . ':</h3>' . PHP_EOL;
                    }
                    $account_html .= '<ul class="wc-bacs-bank-details order_details advance_gateway_details">' . PHP_EOL;
                    $account_fields = apply_filters(
                            'woocommerce_advance_gateway_account_fields', array(
                        'bank_name' => array(
                            'label' => __('Bank', 'woocommerce'),
                            'value' => $advance_gateway_account->bank_name,
                        ),
                        'account_number' => array(
                            'label' => __('Account number', 'woocommerce'),
                            'value' => $advance_gateway_account->account_number,
                        ),
                        'sort_code' => array(
                            'label' => $sortcode,
                            'value' => $advance_gateway_account->sort_code,
                        ),
                        'iban' => array(
                            'label' => __('IBAN', 'woocommerce'),
                            'value' => $advance_gateway_account->iban,
                        ),
                        'bic' => array(
                            'label' => __('BIC', 'woocommerce'),
                            'value' => $advance_gateway_account->bic,
                        ),
                            ), $order_id
                    );

                    foreach ($account_fields as $field_key => $field) {
                        if (!empty($field['value'])) {
                            $account_html .= '<li class="' . esc_attr($field_key) . '">' . wp_kses_post($field['label']) . ': <strong>' . wp_kses_post(wptexturize($field['value'])) . '</strong></li>' . PHP_EOL;
                            $has_details = true;
                        }
                    }
                    $account_html .= '</ul>';
                }
                if ($has_details) {
                    echo '<section class="woocommerce-bacs-bank-details"><h2 class="wc-bacs-bank-details-heading">' . esc_html__('Our bank details', 'woocommerce') . '</h2>' . wp_kses_post(PHP_EOL . $account_html) . '</section>';
                }
            }
        }

        public function get_country_locale() {
            if (empty($this->locale)) {
                $this->locale = apply_filters(
                        'woocommerce_get_advance_gateway_locale', array(
                    'AU' => array(
                        'sortcode' => array(
                            'label' => __('BSB', 'woocommerce'),
                        ),
                    ),
                    'CA' => array(
                        'sortcode' => array(
                            'label' => __('Bank transit number', 'woocommerce'),
                        ),
                    ),
                    'IN' => array(
                        'sortcode' => array(
                            'label' => __('IFSC', 'woocommerce'),
                        ),
                    ),
                    'IT' => array(
                        'sortcode' => array(
                            'label' => __('Branch sort', 'woocommerce'),
                        ),
                    ),
                    'NZ' => array(
                        'sortcode' => array(
                            'label' => __('Bank code', 'woocommerce'),
                        ),
                    ),
                    'SE' => array(
                        'sortcode' => array(
                            'label' => __('Bank code', 'woocommerce'),
                        ),
                    ),
                    'US' => array(
                        'sortcode' => array(
                            'label' => __('Routing number', 'woocommerce'),
                        ),
                    ),
                    'ZA' => array(
                        'sortcode' => array(
                            'label' => __('Branch code', 'woocommerce'),
                        ),
                    ),
                        )
                );
            }

            return $this->locale;
        }

        /* AJAX Upload Payment Receipt */

        function handle_file_upload() {
            $wp_upload_dir = wp_upload_dir();
            $filetype = wp_check_filetype(basename($_FILES['file']['name']), null);
            $allowed_types = json_decode(stripslashes($_POST['allowed_types']));
            $filename = $_FILES['file']['name'];
            if (!function_exists('wp_handle_upload')) {
                require_once(ABSPATH . 'wp-admin/includes/file.php');
//                require_once( ABSPATH . 'wp-admin/includes/image.php' );
            }

//            $attachment = array(
//                'guid' => $wp_upload_dir['url'] . '/' . basename($filename),
//                'post_mime_type' => $filetype['type'],
//                'post_title' => preg_replace('/\.[^.]+$/', '', basename($filename)),
//                'post_content' => '',
//                'post_status' => 'inherit'
//            );
//            $attach_id = wp_insert_attachment($attachment, $filename, '');
//            $attach_data = wp_generate_attachment_metadata($attach_id, $filename);
//            wp_update_attachment_metadata($attach_id, $attach_data);


            $upload_overrides = array('test_form' => false);
            $response = array();
            if (in_array($_FILES['file']['type'], $allowed_types)) {
                $response = wp_upload_bits($_FILES["file"]["name"], null, file_get_contents($_FILES["file"]["tmp_name"]));
            }
            echo json_encode($response);
            wp_die();
        }

        /* Remove uploaded receipt file */

        function remove_upload() {
            $file_url = $_POST['url'];
            $path = parse_url($file_url, PHP_URL_PATH);
            $path = explode('/wp-content/', $path);
            $fullPath = get_home_path() . 'wp-content/' . $path[1];
            if (unlink($fullPath)) {
                return true;
            } else {
                return false;
            }
        }

        /* Showing Reciept in order details to the admin */

        function order_with_receipt($order) {
            $image_url = get_post_meta($order->id, '_receipt_path', true);
            echo "<p class='form-field form-field-wide'><label for='Payment Receipt'>Payment Receipt: </label></p><p><img src='" . $image_url . "' height='100' width='100'></p><br>";
        }

        function advance_view_order_and_thankyou_page($order_id) {
            $bank_details = get_option(
                    'woocommerce_advance_gateway_accounts', array(
                array(
                    'account_name' => get_option('account_name'),
                    'account_number' => get_option('account_number'),
                    'sort_code' => get_option('sort_code'),
                    'bank_name' => get_option('bank_name'),
                    'iban' => get_option('iban'),
                    'bic' => get_option('bic'),
                ),
                    )
            );
            ?>
            <h2>Our Bank Details</h2>
            <table class="woocommerce-table shop_table gift_info">
                <tbody>
                    <tr>

                    </tr>
                    <?php foreach ($bank_details as $details) { ?>
                    <h3><?php echo $details['account_name'] ?></h3>
                    <ul>
                        <li>Bank: <strong><?php echo $details['bank_name']; ?></strong></li>
                        <li>Account Number: <strong><?php echo $details['account_number']; ?></strong></li>
                        <li>IFSC: <strong><?php echo $details['sort_code']; ?></strong></li>
                    </ul>
                <?php } ?>
            </tbody>
            </table>
            <?php
        }

        public function advance_payment_transfer_scripts() {
            wp_enqueue_style('style-name', plugins_url('/css/advance-gateway.css', __FILE__));
            wp_enqueue_script('upload', plugins_url('/js/upload.js', __FILE__), array(), '1.0.0', true);
        }

        public function advance_payment_transfer_admin_scripts() {
            wp_enqueue_script('gateway', plugins_url('/js/advance_gateway_admin.js', __FILE__), array(), '1.0.0', true);
        }

    }

    add_action('admin_enqueue_scripts', array('WC_Advance_Gateway', 'advance_payment_transfer_admin_scripts'));
    add_action('wp_enqueue_scripts', array('WC_Advance_Gateway', 'advance_payment_transfer_scripts'));
    add_action('woocommerce_thankyou', array('WC_Advance_Gateway', 'advance_view_order_and_thankyou_page'), 20);
    add_action('woocommerce_view_order', array('WC_Advance_Gateway', 'advance_view_order_and_thankyou_page'), 20);
    add_action('woocommerce_admin_order_data_after_order_details', array('WC_Advance_Gateway', 'order_with_receipt'), 10, 1);
    add_action('wp_ajax_receipt_upload', array('WC_Advance_Gateway', 'handle_file_upload'));
    add_action('wp_ajax_remove_upload', array('WC_Advance_Gateway', 'remove_upload'));
}
