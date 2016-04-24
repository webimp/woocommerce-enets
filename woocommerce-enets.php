<?php
/*
Plugin Name: WooCommerce eNETS
Description: WooCommerce eNETS lets your customers pay via eNETS.
Version: 1.0.0
Author: Web Imp Pte Ltd
Requires at least: 4.5
Tested up to: 4.5
Text Domain: woocommerce-enets
*/
if (!defined('ABSPATH')) exit;


function init_wc_gateway_enets() {
    class WC_Gateway_Enets extends WC_Payment_Gateway
    {
        /**
         * Constructor for the gateway.
         *
         * @return void
         */
        public function __construct() {
            global $woocommerce;

            $this->id           = 'enets';
            $this->icon         = apply_filters( 'woocommerce_wcCpg1_icon', '' );
            $this->has_fields   = TRUE;
            $this->method_title = __( 'eNETS', 'woocommerce-enets' );

            // Load the form fields.
            $this->init_form_fields();

            // Load the settings.
            $this->init_settings();

            // Define user set variables.
            $this->title              = $this->get_option('title');
            $this->description        = $this->get_option('description');
            $this->instructions       = $this->get_option('instructions');
            $this->enable_for_methods = $this->get_option('enable_for_methods', array());
            $this->development_mode   = (bool) ($this->get_option('development_mode') == "yes");
            $this->form_action        = $this->get_enets_url($this->development_mode);
            $this->mid                = $this->get_option('mid');

            // Actions
            if ( version_compare( WOOCOMMERCE_VERSION, '2.0.0', '>=' ) )
                add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( &$this, 'process_admin_options' ) );
            else
                add_action( 'woocommerce_update_options_payment_gateways', array( &$this, 'process_admin_options' ) );
        }




        /* Admin Panel Options. */
        public function admin_options()
        {
            ?>
            <h3><?php _e('eNETS','woocommerce-enets'); ?></h3>
            <table class="form-table">
                <?php $this->generate_settings_html(); ?>
            </table>
            <?php
        }




        /* Initialise Gateway Settings Form Fields. */
        public function init_form_fields()
        {
            global $woocommerce;

            $shipping_methods = array();

            if (is_admin())
            {
                foreach ( $woocommerce->shipping->load_shipping_methods() as $method )
                {
                    $shipping_methods[ $method->id ] = $method->get_title();
                }
            }

            $this->form_fields = array(
                'enabled' => array(
                    'title'   => __( 'Enable/Disable', 'woocommerce-enets' ),
                    'type'    => 'checkbox',
                    'label'   => __( 'Enable eNETS payment', 'woocommerce-enets' ),
                    'default' => 'no'
                ),
                'title' => array(
                    'title'       => __( 'Title', 'woocommerce-enets' ),
                    'type'        => 'text',
                    'description' => __( 'This controls the title which the user sees during checkout.', 'woocommerce-enets' ),
                    'desc_tip'    => TRUE,
                    'default'     => __( 'eNETS', 'woocommerce-enets' )
                ),
                'mid' => array(
                    'title'       => __( 'Merchant ID', 'woocommerce-enets' ),
                    'type'        => 'text',
                    'description' => __( 'Unique merchant ID issued by an eNETS administrator (For UMID transactions, this field should contain the UMID)', 'woocommerce-enets' ),
                    'desc_tip'    => TRUE,
                    'default'     => __( '', 'woocommerce-enets' )
                ),
                'description' => array(
                    'title'       => __( 'Description', 'woocommerce-enets' ),
                    'type'        => 'textarea',
                    'description' => __( 'This controls the description which the user sees during checkout.', 'woocommerce-enets' ),
                    'desc_tip'    => TRUE,
                    'default'     => __( '', 'woocommerce-enets' )
                ),
                'instructions' => array(
                    'title'       => __( 'Instructions', 'woocommerce-enets' ),
                    'type'        => 'textarea',
                    'description' => __( 'Instructions that will be added to the thank you page.', 'woocommerce-enets' ),
                    'desc_tip'    => TRUE,
                    'default'     => __( '', 'woocommerce-enets' )
                ),
                'development_mode' => array(
                    'title'       => __( 'Development Mode', 'woocommerce-enets' ),
                    'type'        => 'checkbox',
                    'label'       => __( 'Enable Test Mode', 'woocommerce-enets' ),
                    'description' => __( 'Use eNETS testing URL for submissions.', 'woocommerce-enets' ),
                    'default'     => FALSE,
                    'desc_tip'    => TRUE,
                ),
                'enable_for_methods' => array(
                    'title'       => __( 'Enable for shipping methods', 'woocommerce-enets' ),
                    'type'        => 'multiselect',
                    'class'       => 'chosen_select',
                    'css'         => 'width: 350px;',
                    'default'     => '',
                    'description' => __( 'If eNETS is only available for certain methods, set it up here. Leave blank to enable for all methods.', 'woocommerce-enets' ),
                    'options'     => $shipping_methods,
                    'desc_tip'    => TRUE,
                ),
            );
        }




        /* Process the payment and return the result. */
        public function process_payment($order_id)
        {
            global $woocommerce;

            $order = new WC_Order($order_id);

            $form_values = array(
                'amount'      => $order->get_total(),
                'mid'         => $this->mid,
                'form_action' => $this->form_action,
                'txnRef'      => $order->id,
                'order_id'    => $order->id,
                'umapiType'   => 'lite',
            );

            $querystring = http_build_query( $form_values );

            // mark as on-hold (we're awaiting the payment to go through)
            $order->update_status('pending', __('Awaiting eNETS gateway payment completion.', 'woocommerce'));

            // reduce stock levels
            $order->reduce_order_stock();

            // empty cart
            $woocommerce->cart->empty_cart();

            // add dropdown list in settings to select page
            return array(
                'result'   => 'success',
                'redirect' => site_url('enets-payment') . '?' . $querystring,
            );
        }




        private function get_enets_url($dev = FALSE)
        {
            if ($dev)
                return 'https://test.enets.sg/enets2/enps.do';
            else
                return 'https://www.enets.sg/enets2/enps.do';
        }




        /* Output for the order received page. */
        public function thankyou()
        {
            echo $this->instructions != '' ? wpautop( $this->instructions ) : '';
        }
    }
}
add_action('plugins_loaded', 'init_wc_gateway_enets');




function enets_shortcode()
{
    global $woocommerce;

    $amount      = $_GET['amount'];
    $mid         = $_GET['mid'];
    $txnRef      = $_GET['txnRef'];
    $umapiType   = $_GET['umapiType'];
    $form_action = $_GET['form_action'];

    ?>
    <div class="col-xs-12">
        <h3 class="no-margin-top">Confirm Payment</h3>
        <div class="table-responsive">
            <table class="table margin-top">
                <tbody>
                    <tr>
                        <td><strong>Product</strong></td>
                        <td><strong>Quantity</strong></td>
                        <td><strong>Price</strong></td>
                        <td><strong>GST 7%</strong></td>
                        <td><strong>Order Total</strong></td>
                    </tr>
                    <?php
                    $order       = new WC_Order( $_GET['order_id'] );
                    $items       = $order->get_items();
                    $order_total = $order->get_total();

                    foreach( $items as $item )
                    {
                        $name       = $item['name'];
                        $qty        = $item['qty'];
                        $flat_price = $item['line_total'];
                        $tax_price  = $item['line_tax'];
                        $price      = $flat_price + $tax_price;

                        echo '<tr>';
                        echo '<td>'. $name .'</td>';
                        echo '<td>'. $qty .'</td>';
                        echo '<td>$'. $flat_price .'</td>';
                        echo '<td>$'. $tax_price .'</td>';
                        echo '<td>$'. $price .'</td>';
                        echo '</tr>';

                    } ?>
                </tbody>
            </table>
            <div class="pull-right margin-top">
                <strong>Total amount to be paid:</strong>
                <h4><strong>SGD $<?php echo $order_total; ?></strong></h4>
            </div>
        </div>

        <?php $gateway = new WC_Gateway_Enets(); ?>

        <form name="cart" method="post" action="<?php echo $form_action; ?>">
            <input type="hidden" name="amount" value="<?php echo $amount; ?>">
            <input type="hidden" name="txnRef" value="<?php echo $txnRef; ?>">
            <input type="hidden" name="mid" value="<?php echo $mid; ?>">
            <input type="hidden" name="umapiType" value="<?php echo $umapiType; ?>">
            <button type="submit" class="btn btn-success center-block margin-top">Continue Payment by eNETS</button>
        </form>
    </div>
<?php
}
add_shortcode('enets_form', 'enets_shortcode');




function add_wc_gateway_enets( $methods )
{
    $methods[] = 'WC_Gateway_Enets';
    return $methods;
}
add_filter('woocommerce_payment_gateways', 'add_wc_gateway_enets');
