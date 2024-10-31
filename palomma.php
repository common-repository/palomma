<?php

/**
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * admin area. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin.
 *
 * @link              https://palomma.com
 * @since             1.0.0
 * @package           Palomma
 *
 * @wordpress-plugin
 * Plugin Name:       Palomma
 * Plugin URI:        https://wordpress.org/plugins/wc-gateway-palomma
 * Description:       Pagos de bajo costo, rápidos y seguros. Permite a los comercios ahorrar en costos de procesamiento y aumentar conversión con pagos bancarios seguros y en un solo click.
 * Version:           1.1.1
 * Author:            Palomma SAS
 * Author URI:        https://palomma.com
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       palomma
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Currently plugin version.
 * Start at version 1.0.0 and use SemVer - https://semver.org
 * Rename this for your plugin and update it as you release new versions.
 */
define( 'PALOMMA_VERSION', '1.0.0' );

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-palomma-activator.php
 */
function activate_palomma() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-palomma-activator.php';
	Palomma_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-palomma-deactivator.php
 */
function deactivate_palomma() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-palomma-deactivator.php';
	Palomma_Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'activate_palomma' );
register_deactivation_hook( __FILE__, 'deactivate_palomma' );

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require plugin_dir_path( __FILE__ ) . 'includes/class-palomma.php';

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    1.0.0
 */
function run_palomma() {

	$plugin = new Palomma();
	$plugin->run();

}
run_palomma();

add_action('plugins_loaded', 'init_palomma_gateway_class');

// added by developer 
function init_palomma_gateway_class() {
	class WC_Palomma_Gateway extends WC_Payment_Gateway {

		public $domain;

		/**
		 * Constructor for the gateway.
		*/
		public function __construct() {

			$this->domain 						= 'palomma_payments';

			$this->id     						= 'palomma_payments';
			$this->icon   						= apply_filters('palomma_payments_icon', '');
			$this->has_fields         = false;
			$this->method_title       = __( 'Palomma Payment Gateway', $this->domain );
			$this->method_description = __( 'Allows payments with Palomma payment gateway.', $this->domain );

			// Load the settings.
			$this->init_form_fields();
			$this->init_settings();

			// Define user set variables
			$this->private_key  			= $this->get_option( 'private_key' );
			$this->integrity_key  		= $this->get_option( 'integrity_key' );
			$this->test_private_key  	= $this->get_option( 'test_private_key' );
			$this->test_integrity_key = $this->get_option( 'test_integrity_key' );
			$this->logo_url        		= $this->get_option( 'logo_url' );
			$this->testmode  					= $this->get_option( 'testmode' );
			$this->style  						= $this->get_option( 'style' );

			$this->title        			= "Paga con tu cuenta de banco con Palomma.";
			$this->subtitle        		= "Paga con tu cuenta de banco y obtén $10,000 de descuento.";
			$this->description  			= "Una nueva forma segura y fácil de pagar con cuentas bancarias.";

			// Actions
			add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
			add_action( 'woocommerce_thankyou_' . $this->id, array( $this, 'thankyou_page' ) );
			add_action( 'admin_notices', array( $this, 'pay_by_palomma_notice' ) );
			add_action( 'woocommerce_admin_order_data_after_order_details', array( $this, '_palomma_display_order_data_in_admin' ) );
		}

		/**
		 * admin notices
		*/
		public function pay_by_palomma_notice() {
			$screen = get_current_screen();
			if(!($this->private_key && $this->integrity_key && $this->test_private_key && $this->test_integrity_key) && $screen->base !== 'woocommerce_page_wc-settings') {
				?>
					<div class="error notice-error">
							<p><?php _e( 'Please activate Palomma Plugin by adding Private Key and Integrity Key <a href="' . admin_url( 'admin.php?page=wc-settings&tab=checkout&section=palomma_payments' ) . '">here</a>. If you continue to face this error notice please contact <a href="mailto:nico@palomma.com" target="_blank">Plugin Author</a>.'); ?></p>
					</div>
				<?php
			}

			if(!($this->private_key && $this->integrity_key && $this->test_private_key && $this->test_integrity_key) && $screen->base == 'woocommerce_page_wc-settings' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
				?>
					<div class="error notice-error">
							<p><?php _e( 'Please activate Palomma Plugin by adding Private Key and Integrity Key. If you continue to face this error notice please contact <a href="mailto:nico@palomma.com" target="_blank">Plugin Author</a>.'); ?></p>
					</div>
				<?php
			}

			if($screen->base == 'woocommerce_page_wc-settings' && $_SERVER['REQUEST_METHOD'] === 'POST') {
				if(!(sanitize_text_field($_POST['woocommerce_palomma_payments_private_key']) && sanitize_text_field($_POST['woocommerce_palomma_payments_integrity_key']) && sanitize_text_field($_POST['woocommerce_palomma_payments_test_private_key']) && sanitize_text_field($_POST['woocommerce_palomma_payments_test_integrity_key']))){
					?>
						<div class="error notice-error palomma-notice-error">
								<p><?php _e( 'Please activate Palomma Plugin by adding Private Key and Integrity Key. If you continue to face this error notice please contact <a href="mailto:nico@palomma.com" target="_blank">Plugin Author</a>.'); ?></p>
						</div>
					<?php
				}
			}
		}

		/**
		 * Initialise Gateway Settings Form Fields.
		*/
		public function init_form_fields() {
			$imgSrc = ($this->get_option( 'style' ) === 'no') ? plugins_url( 'images/inverted-banner.png', __FILE__ ) :  plugins_url( 'images/default-banner.png', __FILE__ );

			$this->form_fields = array(
				'enabled' => array(
					'title'   		=> __( 'Enable/Disable', $this->domain ),
					'type'    		=> 'checkbox',
					'label'   		=> __( 'Enable Palomma Payment Gateway', $this->domain ),
					'default' 		=> 'yes'
				),
				'testmode' => array(
					'title'       => 'Test mode',
					'label'       => 'Enable Test Mode',
					'type'        => 'checkbox',
					'description' => 'Enable the payment gateway in test mode.',
					'default'     => 'yes',
					'desc_tip'    => true,
				),
				'private_key' => array(
					'title'       => __( 'Private Key', $this->domain ),
					'type'        => 'text',
					'description' => __( 'Contact nico@palomma.com to get the Private Key to activate this plugin.', $this->domain ),
					'desc_tip'    => true,
				),
				'integrity_key' => array(
					'title'       => __( 'Integrity Key', $this->domain ),
					'type'        => 'text',
					'description' => __( 'Contact nico@palomma.com to get the Integrity Key to activate this plugin.', $this->domain ),
					'desc_tip'    => true,
				),
				'test_private_key' => array(
					'title'       => __( 'Test Private Key', $this->domain ),
					'type'        => 'text',
					'description' => __( 'Contact nico@palomma.com to get the Test Private Key to activate this plugin.', $this->domain ),
					'desc_tip'    => true,
				),
				'test_integrity_key' => array(
					'title'       => __( 'Test Integrity Key', $this->domain ),
					'type'        => 'text',
					'description' => __( 'Contact nico@palomma.com to get the Test Integrity Key to activate this plugin.', $this->domain ),
					'desc_tip'    => true,
				),
				'logo_url' => array(
					'title'       => __( 'Logo URL', $this->domain ),
					'type'        => 'text',
					'description' => __( '[Optional] Your business logo.', $this->domain ),
					'desc_tip'    => true,
				),
				'style' => array(
					'title'       => __( 'Style', $this->domain ),
					'label'       => 'Default <br><br><img src='.$imgSrc.' style="width:200px">',
					'type'        => 'checkbox',
					'description' => __( 'Your business banner.', $this->domain ),
					'default'     => 'yes',
					'desc_tip'    => true,
				),
			);
		}

		/**
		 * Payment description
		*/
		public function payment_fields() {
			?>
				<div class="palomma-description-wrapper <?php echo $this->style === 'no' ? 'palomma-inverted-style': '' ?>">

					<div class="palomma-description-logo">
						<img src="<?php echo $this->style === 'no' ? plugins_url( 'images/blue-logo.png', __FILE__ ) : plugins_url( 'images/white-logo.png', __FILE__ ) ?>"  />
					</div>
					<div class="palomma-description-main">
						<div class="palomma-description-main-left">
							<div class="palomma-description-title">
								<?php echo esc_html($this->subtitle) ?>
							</div>
							<div class="palomma-description">
								<?php echo esc_html($this->description) ?>
							</div>
						</div>
						<div class="palomma-description-main-right">
							<img class="information-icon" src="<?php echo $this->style === 'no' ? plugins_url( 'images/blue-information-line.png', __FILE__ ) : plugins_url( 'images/white-information-line.png', __FILE__ ); ?>"  />
						</div>
						<div class="information-wrapper">
								<div class="information-header">
									<?php if($this->logo_url) { ?>
										<div class="information-header-left">
											<img src="<?php echo esc_html($this->logo_url); ?>"  />
										</div>
									<?php } ?>
									<div class="information-header-right">
										<img src="<?php echo plugins_url( 'images/blue-logo.png', __FILE__ ); ?>"  />
									</div>
								</div>
								<div class="information-body">
									<div class="information-heading">Pago Seguro con Palomma</div>
									<div class="information-title">Obtén $10,000 en tus primeras dos compras</div>
									<div class="information-subtitle">rápido · seguro · sin deuda</div>
									<ul class="information-steps">
										<li>Selecciona a Palomma como método de pago.</li>
										<li>Regístrate. Esto solo lo haremos una vez.</li>
										<li>Vincula tu cuenta de banco - como en PSE.</li>
										<li>¡Paga y obtén el descuento!</li>
									</ul>
								</div>
								<div class="information-footer">
									Palomma es un nuevo medio de pago que te permite hacer pagos seguros con tu cuenta bancaria, más rápido y fácil que antes
								</div>
							</div>
					</div>
				</div>
			<?php
		}

		/**
		 * Output for the order received page.
		 * 
		 * @param int $order_id
		 */
		public function thankyou_page($order_id) {
		}

		/**
		 * Process the payment and return the result.
		 *
		 * @param int $order_id
		 * @return array
		 */
		public function process_payment( $order_id ) {

			$order 				= wc_get_order( $order_id );
			$order_data 	= $order->get_data();

			$base_url = $this->testmode === "yes" ? "https://api.sandbox-pay.palomma.com" : "https://api.pay.palomma.com";

			$url					= $base_url."/payment-requests";

			$payload			= array(
				"amount_cents" => (int)$order_data["total"] * 100,
				"redirect_url" => $this->get_return_url($order),
				"description"  => json_encode(array(
					"customer_id" => $order->get_user_id(),
					"transaction_id" => $order_id,
				))
			);

			$args = array(
				'headers' => array(
					"Content-Type" => "application/json",
					"Accept" => "application/json",
					"Authorization" => $this->testmode === "yes" ? $this->test_private_key : $this->private_key,
				),
				'body' => json_encode($payload),
				'timeout' => 50
			);

			$request  = wp_remote_post( $url, $args );
			$response = wp_remote_retrieve_body( $request );

			if ( is_wp_error( $request ) ) {
				echo esc_attr($request->get_error_message());
				return;
			} else {
				$data       = json_decode($response, true); 
				if($data['payment_url']) {
					if($this->testmode === "yes") {
						$order->update_meta_data( '_palomma_test_order_id', $data['id'] );
						$order->save();
					} else {
						$order->update_meta_data( '_palomma_order_id', $data['id'] );
						$order->save();
					}
					
					return array(
						'result'    => 'success',
						'redirect'  =>  $data['payment_url']
						);
				} else {
					return array(
						'result'    => $response
					);
				}
			}
		}

		/**
		 * Register rest route
		*/
		public function register_routes() {
			register_rest_route( 'palomma-payment-gateway', '/prod-callback-url', array(
				'methods' => 'POST',
				'callback' => array( $this, 'prod_callback_url' ),
				'permission_callback' => '__return_true'
			) );
			register_rest_route( 'palomma-payment-gateway', '/dev-callback-url', array(
				'methods' => 'POST',
				'callback' => array( $this, 'dev_callback_url' ),
				'permission_callback' => '__return_true'
			) );
		}

		public function permissions_check( $request ) {
			return true;
		}

		/**
		 * update prod order status
		*/
		public function prod_callback_url(WP_REST_Request $request) {
			
			$response 							=  $request->get_body();
			$d 											= json_decode($response, true);
			$data										= $d['data'];
			$signature							= $d['signature'];

			$decoded_data						= json_decode(base64_decode($data), true);

	
			$hash = hash('sha256', $data.$this->integrity_key);

			if($hash === $signature) {
				$palomma_order_id	= $decoded_data['id'];
				
				global $wpdb;
				$meta = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM ".$wpdb->postmeta." WHERE meta_key=%s AND meta_value=%s", '_palomma_order_id', $palomma_order_id ) );

				if (is_array($meta) && !empty($meta) && isset($meta[0])) {
					$order_id = $meta[0]->post_id;
				}		
				if (is_object($meta)) {
					$order_id =  $meta->post_id;
				}

				if($order_id) {
					$order						= wc_get_order($order_id);

					if($decoded_data['status'] === "APPROVED") {
						$status 					= 'wc-processing';
						$order->update_status( $status, __( 'Checkout with Palomma Payment Gateway', $this->domain ) );
						$order->add_order_note("Checkout with Palomma Payment Gateway Order status changed from Processing to Approved");
					} else if($decoded_data['status'] === "ERROR" || $decoded_data['status'] === "REJECTED") {
						$status 					= 'wc-failed';
						$order->update_status( $status, __( 'Checkout with Palomma Payment Gateway', $this->domain ) );
						$order->add_order_note("Checkout with Palomma Payment Gateway Order status changed from Pending payment to Failed");
					} else if($decoded_data['status'] === "PENDING") {
						$order->add_order_note("Checkout with Palomma Payment Gateway order status is PENDING");
					} else if($decoded_data['status'] === "APPROVED") {
						$order->add_order_note("Checkout with Palomma Payment Gateway Order status changed from Pending payment to Processing");
					}
					return array(
						'result'    => 'success',
					);
				} else {
					return array(
						'result'    => 'failure',
						'message'		=> 'order id not found'
					);
				}
		
			}
		}

		/**
		 * update prod order status
		*/
		public function dev_callback_url(WP_REST_Request $request) {
			
			$response 							=  $request->get_body();
			$d 											= json_decode($response, true);
			$data										= $d['data'];
			$signature							= $d['signature'];

			$decoded_data						= json_decode(base64_decode($data), true);

			$hash = hash('sha256', $data.$this->test_integrity_key);

			if($hash === $signature) {
				$palomma_order_id	= $decoded_data['id'];
				
				global $wpdb;
				$meta = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM ".$wpdb->postmeta." WHERE meta_key=%s AND meta_value=%s", '_palomma_test_order_id', $palomma_order_id ) );

				if (is_array($meta) && !empty($meta) && isset($meta[0])) {
					$order_id = $meta[0]->post_id;
				}		
				if (is_object($meta)) {
					$order_id =  $meta->post_id;
				}

				if($order_id) {
					$order						= wc_get_order($order_id);

					if($decoded_data['status'] === "APPROVED") {
						$status 					= 'wc-processing';
						$order->update_status( $status, __( 'Checkout with Palomma Payment Gateway', $this->domain ) );
						$order->add_order_note("Checkout with Palomma Payment Gateway Order status changed from Processing to Approved");
					} else if($decoded_data['status'] === "ERROR" || $decoded_data['status'] === "REJECTED") {
						$status 					= 'wc-failed';
						$order->update_status( $status, __( 'Checkout with Palomma Payment Gateway', $this->domain ) );
						$order->add_order_note("Checkout with Palomma Payment Gateway Order status changed from Pending payment to Failed");
					} else if($decoded_data['status'] === "PENDING") {
						$order->add_order_note("Checkout with Palomma Payment Gateway order status is PENDING");
					} else if($decoded_data['status'] === "APPROVED") {
						$order->add_order_note("Checkout with Palomma Payment Gateway Order status changed from Pending payment to Processing");
					}
					return array(
						'result'    => 'success',
					);
				} else {
					return array(
						'result'    => 'failure',
						'message'		=> 'order id not found'
					);
				}
			} 
			
		}

		public function _palomma_display_order_data_in_admin( $order ) { 
			if($this->testmode === "yes") {
				$id = get_post_meta( $order->get_id(), '_palomma_test_order_id', true );
			} else {
				$id = get_post_meta( $order->get_id(), '_palomma_order_id', true );
			}
			if($id) {
			?>
			<div style="width:100%; float: left;">
					<h3><?php _e( 'Palomma Details' ); ?></h3>
					<?php 
							echo '<p><strong>' . __( 'transaction ID' ) . ':</strong>' . $id . '</p>';
					 ?>
			</div>
		<?php 
			}
		}

	}
}

function register_items_routes() {
	$controller = new WC_Palomma_Gateway();
	$controller->register_routes();
}	
add_action( 'rest_api_init', 'register_items_routes' );

// pluin action links
add_filter( 'plugin_action_links_' . plugin_basename(__FILE__), 'palomma_plugin_action_links' );

/**
 * Show action links on the plugin screen.
 *
 * @param mixed $links Plugin Action links.
 *
 * @return array
 */
function palomma_plugin_action_links( $links ) {

	$action_links = array(
		'settings' => '<a href="' . admin_url( 'admin.php?page=wc-settings&tab=checkout&section=palomma_payments' ) . '">Settings</a>',
	);

	return array_merge( $action_links, $links );
}

