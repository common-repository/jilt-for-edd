<?php
/**
 * Jilt for EDD
 *
 * This source file is subject to the GNU General Public License v3.0
 * that is bundled with this package in the file license.txt.
 * It is also available through the world-wide-web at this URL:
 * https://www.gnu.org/licenses/gpl-3.0.html
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@jilt.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade Jilt for EDD to newer
 * versions in the future. If you wish to customize Jilt for EDD for your
 * needs please refer to http://help.jilt.com/for-developers
 *
 * @package   EDD-Jilt/Integration
 * @author    Jilt
 * @copyright Copyright (c) 2015-2020, SkyVerge, Inc.
 * @license   https://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0
 */

defined( 'ABSPATH' ) or exit;

/**
 * Jilt Payment Class
 *
 * Extends the EDD Payment class to add jilt-specific functionality
 *
 * @since 1.2.0
 */
class EDD_Jilt_Payment extends EDD_Payment {


	/**
	 * Returns an order by Jilt cart token.
	 *
	 * @since 1.3.0
	 *
	 * @param string $cart_token the cart token
	 * @return EDD_Jilt_Payment the identified order, or null if not found
	 */
	public static function find_by_cart_token( $cart_token ) {

		$query_params = array(
			'post_type'   => 'edd_payment',
			'post_status' => 'any',
			'fields'      => 'ids',
			'meta_query'  => array(
				array(
					'key'     => '_edd_jilt_cart_token',
					'value'   => $cart_token,
					'compare' => '=',
				)
			)
		);

		$result = new WP_Query( $query_params );

		if ( count( $result->posts ) > 0 ) {
			$post_id = $result->posts[0];
		} else {
			return null;
		}

		try {
			return new self( $post_id );
		} catch ( Exception $e ) {
			return null;
		}
	}


	/**
	 * Get an order by Jilt order id
	 *
	 * @since 1.2.0
	 * @param int $jilt_order_id the remote Jilt order identifier
	 * @return EDD_Jilt_Payment the identified payment, or null if not found
	 */
	public static function find_by_jilt_order_id( $jilt_order_id ) {

		$query_params = array(
			'post_type'   => 'edd_payment',
			'post_status' => 'any',
			'fields'      => 'ids',
			'meta_query'  => array(
				array(
					'key'     => '_edd_jilt_order_id',
					'value'   => $jilt_order_id,
					'compare' => '=',
				)
			)
		);

		$result = new WP_Query( $query_params );

		if ( count( $result->posts ) > 0 ) {
			$post_id = $result->posts[0];
		} else {
			return null;
		}

		try {
			return new self( $post_id );
		} catch ( Exception $e ) {
			return null;
		}
	}


	/**
	 * Get the order data for updating a Jilt order via the API
	 *
	 * @since 1.2.0
	 * @return array
	 */
	public function get_jilt_order_data() {

		$params = array(
			'name'              => $this->number,
			'order_id'          => $this->ID,
			'admin_url'         => $this->get_order_edit_url(),
			'status'            => $this->get_status(),
			'financial_status'  => $this->get_financial_status(),
			'fulfillment_status' => $this->get_fulfillment_status(),
			'total_price'       => $this->amount_to_int( $this->total ),
			'subtotal_price'    => $this->amount_to_int( $this->subtotal ),
			'total_tax'         => $this->amount_to_int( $this->tax ),
			'total_discounts'   => $this->amount_to_int( $this->get_total_discount() ),
			'total_shipping'    => $this->amount_to_int( $this->get_shipping_total() ),
			'requires_shipping' => $this->needs_shipping(),
			'currency'          => $this->currency,
			'checkout_url'      => EDD_Jilt_Checkout_Handler::get_checkout_recovery_url( $this->get_jilt_cart_token() ),
			'line_items'        => $this->get_product_line_items(),
			'fee_items'         => $this->get_fee_line_items(),
			'cart_token'        => $this->get_jilt_cart_token(),
			'test'              => $this->get_meta( '_edd_payment_mode' ) == 'test',
			'client_details'    => array(
				'browser_ip' => $this->ip,
				// TODO: 'user_agent'
			),
			'properties'        => $this->get_order_properties(),
			'customer'          => array(
				'customer_id' => $this->customer_id,
				'admin_url'   => admin_url( 'edit.php?post_type=download&page=edd-customers&view=overview&id=' . $this->customer_id ),
				'email'       => $this->email,
				'first_name'  => $this->first_name,
				'last_name'   => $this->last_name,
			),
			'billing_address' => $this->map_address_to_jilt( 'billing' ),
		);

		if ( $this->get_jilt_placed_at() ) {
			$params['placed_at'] = $this->get_jilt_placed_at();
		}

		if ( $this->get_jilt_cancelled_at() ) {
			$params['cancelled_at'] = $this->get_jilt_cancelled_at();
		}

		// TODO: consider sending this as customer meta
		// the WP user (if any)
		//if ( ! empty( $this->user_id ) ) {
		//	$params['customer']['customer_id'] = $this->user_id;
		//	$params['customer']['admin_url']   = esc_url_raw( add_query_arg( 'user_id', $this->user_id, self_admin_url( 'user-edit.php' ) ) );
		//}

		// Note: EDD doesn't seem to support the notion of a customer note

		if ( $this->marketing_consent_accepted() ) {

			$params['customer']['accepts_marketing']       = true;

			if ( ! empty( $params['client_details']['browser_ip'] ) ) {
				$params['customer']['consent_ip_address'] = $params['client_details']['browser_ip'];
			}

			$params['customer']['consent_context']   = 'checkout';
			$params['customer']['consent_timestamp'] = $this->get_jilt_placed_at();
			$params['customer']['consent_notice']    = $this->get_marketing_consent_notice();

			// let Jilt know if it was offerred but not accepted
			} elseif ( $this->marketing_consent_offered() ) {

			$params['customer']['accepts_marketing'] = false;
		}

		/**
		 * Filter the order data used for updating a Jilt order
		 * via the API
		 *
		 * @since 1.0.0
		 * @param array $params
		 * @param \EDD_Jilt_Payment $this instance
		 */
		return apply_filters( 'edd_jilt_order_params', $params, $this );
	}


	/**
	 * Get the timestamp at which this order was placed, or null
	 *
	 * @since 1.2.0
	 * @return string placed at datetime in iso8601 format, or null
	 */
	public function get_jilt_placed_at() {

		if ( $this->completed_date ) {
			return date( 'Y-m-d\TH:i:s\Z', strtotime( $this->completed_date ) );
		}

		return null;
	}


	/**
	 * Get the timestamp at which this order was cancelled, or null
	 *
	 * @since 1.2.0
	 * @return string cancelled datetime in iso8601 format, or null
	 */
	public function get_jilt_cancelled_at() {

		if ( $this->get_meta( '_edd_jilt_cancelled_at' ) ) {
			return date( 'Y-m-d\TH:i:s\Z', $this->get_meta( '_edd_jilt_cancelled_at' ) );
		}

		return null;
	}


	/**
	 * Get the Jilt cart token for an order.
	 *
	 * @since 1.2.0
	 * @return string
	 */
	public function get_jilt_cart_token() {

		return $this->get_meta( '_edd_jilt_cart_token' );
	}


	/**
	 * Get the Jilt order ID for an order.
	 *
	 * @since 1.2.0
	 * @deprecated since 1.3.0
	 * @return int|string
	 */
	public function get_jilt_order_id() {

		_deprecated_function( 'EDD_Jilt_Payment::get_jilt_order_id()', '1.3.0', 'EDD_Jilt_Payment::get_jilt_cart_token' );

		return $this->get_meta( '_edd_jilt_order_id' );
	}


	/**
	 * Get the financial status for the order.
	 *
	 * Some notes on the various EDD status:
	 *
	 * 'processing' is a bit of an oddball status, it's returned in the core
	 * status array by edd_get_payment_statuses(), however it's not described
	 * in the EDD order status help doc. According to the core EDD code:
	 * "Processing is specifically used for eChecks" and indicates that some
	 * action is waiting to be taken. It seems to basically be the eChecks
	 * version of 'pending'.
	 *
	 * 'preapproved' is not returned in the core status array by
	 * edd_get_payment_statuses() however it is used in EDD core alongside
	 * pending/processing with a comment "This payment was never completed".
	 * According to the EDD order status help doc: "A preapproved payment is
	 * one where the customer has approved the payment, but it hasn't been
	 * processed yet. It'll be processed at a later date". Based on that it
	 * sounds like a gateway-specific status, that we must map to 'pending' by
	 * default but could potentially mean 'authorized'.
	 *
	 * 'cancelled' This is a historic status that is no longer used according
	 * to the EDD order status help doc. It is mapped to a null financial
	 * status
	 *
	 * @see edd_get_payment_statuses()
	 * @see https://docs.easydigitaldownloads.com/article/1180-what-do-the-different-payment-statuses-mean
	 * @since 1.2.0
	 * @return string one of 'pending', 'authorized', 'partially_paid', 'paid',
	 *   'partially_refunded', 'refunded', 'voided', or null
	 */
	public function get_financial_status() {

		$financial_status = null;

		if ( ! empty( $this->completed_date ) && in_array( $this->status, array( 'complete', 'publish', 'revoked', 'cancelled', 'subscription' ), true ) ) {
			$financial_status = 'paid';
		} elseif ( 'refunded' === $this->status ) {
			if ( ! empty( $this->total ) ) {
				$financial_status = 'partially_refunded';
			} else {
				$financial_status = 'refunded';
			}
		} elseif ( in_array( $this->status, array( 'pending', 'processing', 'failed', 'abandoned', 'preapproved' ), true ) ) {
			$financial_status = 'pending';
		}

		/**
		 * Filter order financial status for Jilt
		 *
		 * @since 1.0.0
		 * @param string $financial_status one of 'pending', 'authorized',
		 *   'partially_paid', 'paid', 'partially_refunded', 'refunded',
		 *   'voided', or null
		 * @param EDD_Jilt_Payment $this
		 */
		$financial_status = apply_filters( 'edd_jilt_order_financial_status', $financial_status, $this );

		return $this->is_valid_financial_status( $financial_status ) ? $financial_status : null;
	}


	/**
	 * Get the fulfillment status for the order. Since core EDD doesn't support
	 * shippable products, the implementation of this method is left to 3rd
	 * party integrations.
	 *
	 * @since 1.2.0
	 * @return null|string one of 'fulfilled', 'unfulfilled', 'partial', or null
	 */
	public function get_fulfillment_status() {

		if ( ! $this->needs_shipping() ) {
			return null;
		}

		/**
		 * Filter order fulfillment status for Jilt
		 *
		 * @since 1.2.0
		 * @param string $fulfillment_status one of 'fulfilled', 'unfulfilled', 'partial', or null
		 * @param \EDD_Jilt_Payment $this
		 */
		$fulfillment_status = apply_filters( 'edd_jilt_get_order_fulfillment_status', null, $this );

		return $this->is_valid_fulfillment_status( $fulfillment_status ) ? $fulfillment_status : null;
	}


	/**
	 * Get the admin edit url for the order
	 *
	 * @since 1.2.0
	 * @return string|null
	 */
	public function get_order_edit_url() {

		return add_query_arg( array( 'id' => $this->ID ), admin_url( 'edit.php?post_type=download&page=edd-payment-history&view=view-order-details' ) );
	}


	/**
	 * Determine if the order needs shipping or not
	 *
	 * @since 1.2.0
	 * @return bool false
	 */
	public function needs_shipping() {

		/**
		 * Does this payment need shipping?
		 *
		 * @since 1.2.0
		 * @param bool $needs_shipping whether the order contains shippable items
		 * @param \EDD_Jilt_Payment $this
		 */
		return (bool) apply_filters( 'edd_jilt_order_needs_shipping', false, $this );
	}


	/**
	 * Map an EDD address to Jilt address
	 *
	 * @since 1.2.0
	 * @param string $address_type `billing`. `shipping` is reserved for when support
	 *   is added for the EDD Simple Shipping plugin
	 * @return array associative array suitable for Jilt API consumption
	 */
	public function map_address_to_jilt( $address_type = 'billing' ) {

		$address = $this->address;

		$billing_address = array(
			'email'        => $this->email,
			'first_name'   => $this->first_name,
			'last_name'    => $this->last_name,
			'address1'     => $address['line1'],
			'address2'     => $address['line2'],
			'city'         => $address['city'],
			'state_code'   => $address['state'],
			'postal_code'  => $address['zip'],
			'country_code' => $address['country'],
		);

		return $billing_address;
	}


	/**
	 * Get EDD payment address -> Jilt order address mapping
	 *
	 * @since 1.2.0
	 * @return array $mapping
	 */
	public static function get_jilt_payment_field_mapping() {

		/**
		 * Filter which EDD address fields are mapped to which Jilt address fields
		 *
		 * @since 1.0.0
		 * @param array $mapping Associative array 'edd_param' => 'jilt_param'
		 */
		return apply_filters( 'edd_jilt_address_mapping', array(
			'edd_email'       => 'email',
			'edd_first'       => 'first_name',
			'edd_last'        => 'last_name',
			'card_address'    => 'address1',
			'card_address_2'  => 'address2',
			'card_city'       => 'city',
			'card_state'      => 'state_code',
			'card_zip'        => 'postal_code',
			'billing_country' => 'country_code',
		) );
	}


	/**
	 * Get EDD payment address -> EDD customer address
	 *
	 * @since 1.2.0
	 * @return array $mapping
	 */
	public static function get_address_field_mapping() {

		return array(
			'card_address'    => 'line1',
			'card_address_2'  => 'line2',
			'card_city'       => 'city',
			'card_state'      => 'state',
			'card_zip'        => 'zip',
			'billing_country' => 'country',
		);
	}


	/**
	 * Convert a price/total to the lowest currency unit (e.g. cents)
	 *
	 * @since 1.2.0
	 * @param string|float $number
	 * @return int
	 */
	private function amount_to_int( $number ) {

		return round( $number * 100, 0 );
	}


	/**
	 * Map EDD order items to Jilt line items
	 *
	 * @since 1.2.0
	 * @return array
	 */
	private function get_product_line_items() {

		$line_items = array();

		foreach ( $this->cart_details as $item_key => $item ) {

			$download = new EDD_Download( $item['id'] );

			// prepare main line item params
			$line_item = array(
				'title'      => html_entity_decode( $download->get_name() ),
				'product_id' => $item['id'],
				'quantity'   => $item['quantity'],
				'url'        => get_the_permalink( $item['id'] ),
				'image_url'  => EDD_Jilt_Download::get_download_image_url( $download ),
				'key'        => $item_key,
				'price'      => $this->get_item_price( $item ),
				'tax_lines'  => $this->get_tax_lines( $item ),
			);

			if ( edd_use_skus() ) {
				$line_item['sku'] = $download->get_sku();
			}

			// add variation data
			if ( $download->has_variable_prices() ) {
				$variant_id  = $item['item_number']['options']['price_id'];
				$option_name = edd_get_price_option_name( $download->ID, $variant_id );

				$line_item['variant_id']     = $variant_id;
				$line_item['variant_title']  = $option_name;
				$line_item['variation']      = array( 'name' => $option_name );
			} else {
				$variant_id = null;
			}

			// line item properties (excludes price_id/quantity options)
			$line_item['properties'] = array_diff_key(
				$item['item_number']['options'],
				array_flip( array( 'price_id', 'quantity' ) )
			);

			// support for core bundled items: reference all items that this main
			// item is bundling, by key
			$bundled_items = $this->get_bundled_items( $item, $item_key );

			if ( $bundled_items ) {
				$line_item['properties']['_bundled_items'] = array();

				foreach ( $bundled_items as $bundled_item ) {
					$line_item['properties']['_bundled_items'][] = $bundled_item['key'];
				}
			}

			// include any downloadable file links
			$line_item = $this->add_downloadable_files( $line_item, $item['id'], $variant_id );

			/**
			 * Filter order item params used for updating a Jilt order
			 * via the API
			 *
			 * @since 1.0.0
			 * @param array $line_item Jilt line item data
			 * @param stdClass $item EDD line item data
			 * @param \EDD_Jilt_Payment $this instance
			 */
			$line_items[] = apply_filters( 'edd_jilt_order_line_item_params', $line_item, $item, $this );

			$line_items = array_merge( $line_items, $bundled_items );
		}

		return $line_items;
	}


	/**
	 * Add any downloadable file name/urls to the line item properties array
	 *
	 * Adapted from templates/shortcode-receipt.php
	 *
	 * @since 1.2.0
	 *
	 * @param array $line_item the Jilt line item data
	 * @param int $item_id the item id
	 * @param int $variant_id the option id if this is a variable product, otherwise null
	 * @param boolean $is_bundle true if this is a bundle product (defaults to false)
	 * @return array Jilt line item, with downlodable file properties added
	 */
	private function add_downloadable_files( $line_item, $item_id, $variant_id, $is_bundle = false ) {

		// include any downloadable file links
		$download_files = edd_get_download_files( $item_id, $variant_id );

		$product_id = $item_id;
		if ( $is_bundle ) {
			$product_id .= '_' . $variant_id;
		}

		if ( is_array( $download_files ) && $download_files ) {
			$line_item['properties']['_downloads'] = array();

			foreach ( $download_files as $filekey => $file ) {

				$meta = $this->get_meta();
				$download_file_url = edd_get_download_file_url(
					$meta['key'],
					$this->email,
					$filekey,
					$product_id,
					$variant_id
				);

				$line_item['properties']['_downloads'][] = array(
					'file_url'  => $download_file_url,
					'file_name' => html_entity_decode( edd_get_file_name( $file ) ),
				);
			}
		}

		return $line_item;
	}


	/**
	 * Get any bundled items for this item
	 *
	 * Adapted from edd_get_payment_meta_cart_details()
	 *
	 * @since 1.2.0
	 *
	 * @param array $item EDD item associative array
	 * @param int $item_key the parent item key
	 * @return array of bundled line items (if any)
	 */
	private function get_bundled_items( $item, $item_key ) {

		$line_items = array();

		if ( 'bundle' !== edd_get_download_type( $item['id'] ) ) {
			return $line_items;
		}

		$products = edd_get_bundled_products( $item['id'] );

		if ( empty( $products ) ) {
			return $line_items;
		}

		foreach ( $products as $key => $product_id ) {

			$item_id    = edd_get_bundle_item_id( $product_id );
			$variant_id = edd_get_bundle_item_price_id( $product_id );

			$download = new EDD_Download( $item_id );

			$line_item = array(
				'title'      => html_entity_decode( $download->get_name() ),
				'product_id' => $item_id,
				'quantity'   => 1,
				'url'        => get_the_permalink( $item_id ),
				'image_url'  => EDD_Jilt_Download::get_download_image_url( $download ),
				'key'        => $item_key . '_' . $key,
				'price'      => 0,
			);

			if ( edd_use_skus() ) {
				$line_item['sku'] = $download->get_sku();
			}

			// add variation data
			if ( $download->has_variable_prices() ) {
				$option_name = edd_get_price_option_name( $download->ID, $variant_id );

				$line_item['variant_id']     = $variant_id;
				$line_item['variant_title']  = $option_name;
				$line_item['variation']      = array( 'name' => $option_name );
			}

			// inherit line item properties from the parent product, plus add some of our own
			$line_item['properties'] = array_merge(
				array_diff_key(
					$item['item_number']['options'],
					array_flip( array( 'price_id', 'quantity' ) )
				),
				array(
					'_bundled_by' => $item_key,
				)
			);

			$line_item = $this->add_downloadable_files( $line_item, $item_id, $variant_id, true );

			/**
			 * Filter order item params used for updating a Jilt order
			 * via the API
			 *
			 * @since 1.0.0
			 * @param array $line_item Jilt line item data
			 * @param stdClass $item EDD line item data
			 * @param \EDD_Jilt_Payment $this instance
			 */
			$line_items[] = apply_filters( 'edd_jilt_order_line_item_params', $line_item, $item, $this );
		}

		return $line_items;
	}


	/**
	 * Get the download price, either inclusive or exclusive of tax, depending
	 * on the EDD "Display during checkout inclusive/exclusive of taxes"
	 * setting.
	 *
	 * @since 1.2.0
	 * @param array $item the product
	 * @return int the item price in pennies
	 */
	private function get_item_price( $item ) {

		$price = $item['item_price'];

		if ( edd_prices_show_tax_on_checkout() && ! edd_prices_include_tax() ) {
			$price += $item['tax'];
		}

		if ( ! edd_prices_show_tax_on_checkout() && edd_prices_include_tax() ) {
			$price -= $item['tax'];
		}

		return $this->amount_to_int( $price );
	}


	/**
	 * Get the tax lines, if any, for this item
	 *
	 * @since 1.2.0
	 * @param array Item associative array
	 * @return array of tax lines, e.g. [ [ 'amount' => 135 ] ]
	 */
	private function get_tax_lines( $item ) {

		// a simplistic implementation for now, but if EDD identifies the actual
		// taxes a la Shopify, we can make this more comprehensive
		return array(
			array(
				'amount' => $this->amount_to_int( $item['tax'] ),
			),
		);
	}



	/**
	 * Return the fee line items for this Order in the format required by Jilt
	 *
	 * @since 1.2.0
	 * @return array order fee items in Jilt format
	 */
	private function get_fee_line_items() {

		$fee_items = array();

		foreach ( $this->fees as $key => $fee ) {
			$fee_item = array(
				'title'  => html_entity_decode( $fee['label'] ),
				'key'    => $key,
				'amount' => $this->amount_to_int( $fee['amount'] ),
			);

			/**
			 * Filter order fee params used for updating a Jilt order
			 * via the API
			 *
			 * @since 1.2.0
			 * @param array $fee_item Jilt fee item data
			 * @param \stdClass $fee EDD fee object
			 * @param \EDD_Jilt_Payment $payment instance
			 */
			$fee_items[] = apply_filters( 'edd_jilt_order_fee_item_params', $fee_item, $fee, $this );
		}

		return $fee_items;
	}


	/**
	 * Get the payment status
	 *
	 * @since 1.2.0
	 * @return string one of: 'publish' (complete), 'pending', 'refunded',
	 *   'failed', 'abandoned', 'revoked', 'preapproved', 'cancelled', 'subscription'
	 */
	public function get_status() {
		// note: internally EDD uses 'publish', but 'complete' is presented to the user
		return 'publish' === $this->status ? 'complete' : $this->status;
	}


	/**
	 * Get the payment total discount amount
	 *
	 * @since 1.2.0
	 * @return float the total discount amount
	 */
	public function get_total_discount() {

		$total_discount = 0;

		foreach ( $this->cart_details as $item ) {
			$total_discount += $item['discount'];
		}

		return $total_discount;
	}


	/**
	 * Get the shipping total, if any
	 *
	 * This is a stub method for when we support the EDD Simple Shipping plugin
	 *
	 * @since 1.2.0
	 * @return float the shipping total
	 */
	public function get_shipping_total() {
		return 0;
	}


	/**
	 * Gets an array of order properties
	 *
	 * @since 1.2.0
	 * @return array order properties
	 */
	public function get_order_properties() {

		$properties = array();
		$meta = $this->get_meta();

		if ( ! empty( $meta['key'] ) ) {
			$properties['_payment_purchase_key'] = $meta['key'];
		}

		/**
		 * Get the Jilt properties for the order. These will be sent over the
		 * Jilt REST API
		 *
		 * @since 1.2.0
		 * @param array $properties associative array of order properties
		 * @param \EDD_Jilt_Payment $this payment object
		 */
		$properties = apply_filters( 'edd_jilt_get_order_properties', $properties, $this );

		return $properties;
	}


	/**
	 * Is the given financial status valid?
	 *
	 * @since 1.2.0
	 * @param string $financial_status
	 * @return boolean
	 */
	private function is_valid_financial_status( $financial_status ) {
		$valid = array(
			'pending',
			'authorized',
			'partially_paid',
			'paid',
			'partially_refunded',
			'refunded',
			'voided',
		);

		return in_array( $financial_status, $valid, true );
	}


	/**
	 * Is the given fulfillment status valid?
	 *
	 * @since 1.2.0
	 * @param string $fulfillment_status
	 * @return boolean
	 */
	private function is_valid_fulfillment_status( $fulfillment_status ) {
		$valid = array(
			'fulfilled',
			'unfulfilled',
			'partial',
		);

		return in_array( $fulfillment_status, $valid, true );
	}


	/**
	 * Determines whether marketing consent was offered at checkout.
	 *
	 * @since 1.3.3
	 *
	 * @return bool
	 */
	public function marketing_consent_offered() {
		return 'yes' === $this->get_meta( '_edd_jilt_marketing_consent_offered', true );
	}


	/**
	 * Determines whether marketing consent was accepted at checkout.
	 *
	 * @since 1.3.3
	 *
	 * @return bool
	 */
	public function marketing_consent_accepted() {
		return 'yes' === $this->get_meta( '_edd_jilt_marketing_consent_accepted', true );
	}


	/**
	 * Gets the marketing consent notice as displayed at checkout.
	 *
	 * @since 1.3.3
	 *
	 * @return string
	 */
	public function get_marketing_consent_notice() {
		return $this->get_meta( '_edd_jilt_marketing_consent_notice', true );
	}


}
