<?php
/*
* Plugin Name: Bitnob - Accept Bitcoin Payments (On-chain & Lightning)
 * Description: Accept bitcoin payments with Bitnob
 * Version: 1.0.0
 * Author: Bitnob Technologies
 * Author URI: https://bitnob.com
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain: bitcoin-lightning-payments-by-bitnob
 * Domain Path: /languages
 * WC tested up to: 5.8.2
 * WC requires at least: 3.2.0
 */
add_filter('woocommerce_payment_gateways', 'bitnob_add_gateway_class');

function bitnob_add_gateway_class($gateways)
{
    $gateways[] = 'WC_Gateway_BitNob';
    return $gateways;
}

add_action('plugins_loaded', 'bitnob_add_gateway');
function bitnob_add_gateway()
{
    if (!class_exists('WC_Payment_Gateway')) return;
    class WC_Gateway_BitNob extends WC_Payment_Gateway
    {

        public function __construct()
        {

            $this->id = 'bitnob'; // payment gateway plugin ID

            $this->has_fields = true;
            $this->method_title = __('Bitcoin Payment Gateway - powered by Bitnob');
            $this->method_description = __('Pay with bitcoin, powered by Bitnob');
            $this->supports = array(
                'products'
            );

            // Method with all the options fields
            $this->init_form_fields();
            $this->title = $this->get_option('title');
            $this->description = $this->get_option('description');
            $this->enabled = $this->get_option('enabled');
            //$this->testmode = $this->get_option( 'testmode' );
            //$this->testapikey = $this->get_option( 'testapikey' );
            $this->apikey = $this->get_option('apikey');

            //$this->successUrl = $this->get_option('successUrl');
            $this->success_page_id = $this->settings['success_page_id'];

            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
            //add_action( 'woocommerce_api_bitnob', array( $this, 'webhookname' ) );
            add_action('woocommerce_api_wc_gateway_bitnob', array($this, 'webhookname'));

            add_action('woocommerce_receipt_bitnob', array($this, 'bitnob_checkout_receipt_page'));
            //add_action( 'woocommerce_thankyou_bitnob', array( $this, 'bitnob_thank_you_page' ) );
        }

        public function init_form_fields()
        {

            $this->form_fields = array(
                'enabled' => array(
                    'title'       => __('Enable/Disable'),
                    'label'       => __('Enable Bitnob Gateway'),
                    'type'        => 'checkbox',
                    'description' => '',
                    'default'     => 'no'
                ),
                'title' => array(
                    'title'       => __('Title'),
                    'type'        => 'text',
                    'description' => __('This controls the title which the user sees during checkout.'),
                    'default'     => 'Pay with Bitcoin, powered by Bitnob ',
                    'desc_tip'    => true,
                ),
                'description' => array(
                    'title'       => __('Description'),
                    'type'        => 'textarea',
                    'description' => __('This controls the description which the user sees during checkout.'),
                    'default'     => __('Pay with bitcoin, powered by Bitnob'),
                ),
                'apikey' => array(
                    'title'       => __('API Key'),
                    'type'        => 'text',
                ),
                'success_page_id' => array(
                    'title'         => __('Return to Success Page'),
                    'type'             => 'select',
                    'options'         => $this->bitnob_get_pages('Select Page'),
                    'description'     => __('URL of success page', 'kdc'),
                    'desc_tip'         => true
                ),

            );
        }
        public function process_payment($order_id)
        {
            global $woocommerce;
            $order     = new WC_Order($order_id);
            return array(
                'result' => 'success',
                'redirect' => $order->get_checkout_payment_url(true)
            );

            //Success URL 

        }

        public function bitnob_checkout_receipt_page($order_id)
        {
            global $woocommerce;
            $order = wc_get_order($order_id);


            $items = $order->get_items();
            $items = array_values(($items));
            if ($this->success_page_id == "" || $this->success_page_id == 0) {
                $redirect_url = $order->get_checkout_order_received_url();
            } else {
                $redirect_url = get_permalink($this->success_page_id);
            }
            $apikey = $this->testmode == 'on' ? $this->testapikey : $this->apikey;
            $urlconv = "https://api.bitnob.co/api/v1/wallets/convert-currency/";
            if (isset($_POST['bitnob']) && isset($_POST['submit']) && $_POST['bitnob'] == 'bitnob') {
                $currency = $order->currency;
                //$currency = 'USD';
                $amount = $order->total;
                $currencyconv = $this->call_curl($urlconv, json_encode(["conversion" => strtoupper($currency) . "_BTC", "amount" => $amount]), $apikey);
                //echo $currencyconv;
                $satoshi = json_decode($currencyconv, true);
                if ($satoshi['status'] == 1) {
                    $url = "https://api.bitnob.co/api/v1/checkout/";
                    $amount = $satoshi['data'];
                    $data = array(
                        'invoiceid'         => $order_id,
                        //                         'callbackUrl'      => site_url().'/wc-api/bitnob?invoiceid='.$order_id,
                        'callbackUrl'      => site_url() . '?wc-api=WC_Gateway_Bitnob&invoiceid=' . $order_id,
                        'successUrl'       => $redirect_url,
                        'description'      => $this->description,
                        'satoshis' => round(($amount) * (pow(10, 8)), 6)
                    );
                    $response = $this->call_curl($url, json_encode($data), $apikey);
                    $response_array = json_decode($response, true);
                    if ($response_array['status'] == 1) {
                        header('location: https://checkout.bitnob.co/app/' . $response_array['data']['id'] . '/');
                        exit();
                    } else {
                        $error = $response_array['message'];
                    }
                } else {
                    $error = implode(" ", $satoshi['message']);
                }
                //exit;
            }
            echo '<form method="post" action="">';
            echo '<input type="hidden" value="bitnob" name="bitnob">';
            echo '<input type="submit" name="submit" value="Pay Now">';
            if (isset($error)) {
                echo '<h2 style="color:red;">' . $error . '</h2>';
            }
            echo '</form>';
        }

        public function webhookname()
        {
            $data = file_get_contents('php://input');
            $response = json_decode($data);
            $id = $response->data->id;
            $reference = $response->data->reference;
            $invoiceId = $response->data->invoiceId;
            $orderid = $_GET['invoiceid'];
            if ($response->event == 'checkout.received.paid') {
                $response_id = $response->data->id;
                $url = "https://api.bitnob.co/api/v1/transactions/" . $response->data->transactions[0]->id;
                $apikey = $this->get_option('apikey');
                $resp = $this->sendDataCallback_curl($url, $apikey);
                $objdata = json_decode($resp);

                //print_r($objdata); 
                if ($objdata->status != true) {
                } else {
                    $id = $objdata->data->id;
                    $reference = $objdata->data->reference;
                    $invoiceId = $objdata->data->invoiceId;
                    if ($objdata->data->status == 'success') {
                        $order = wc_get_order($orderid);
                        $order->payment_complete();
                        $order->add_order_note(sanitize_text_field('Bitcoin payment successful') . ("<br>") . ('ID') . (':') . ($id . ("<br>") . ('Payment Ref:') . ($reference) . ("<br>") . ('InvoiceId:') . ($invoiceId)));
                        $order->reduce_order_stock();
                        update_option('webhook_debug', $data);
                    } else {
                        $order = wc_get_order($orderid);
                        $order->update_status('pending');
                        $order->add_order_note(sanitize_text_field('Bitcoin payment failed') . ("<br>") . ('ID') . (':') . ($id . ("<br>") . ('Payment Type :') . ("<br>") . ('Payment Ref:') . ($reference) . ("<br>") . ('InvoiceId:') . ($invoiceId)));
                        update_option('webhook_debug', $data);
                    }
                }
            } else {
                $order = wc_get_order($orderid);
                $order->update_status('pending');
                $order->add_order_note(sanitize_text_field('Bitcoin payment failed') . ("<br>") . ('ID') . (':') . ($id . ("<br>") . ('Payment Type :') . ("<br>") . ('Payment Ref:') . ($reference) . ("<br>") . ('InvoiceId:') . ($invoiceId)));
                update_option('webhook_debug', $_GET);
            }
            $order->add_order_note("Response: " . $data);
            //exit;
        }
        public function sendDataCallback_curl($url, $apikey)
        {
            $curl = curl_init();
            curl_setopt_array($curl, array(
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => "",
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => "GET",
                // CURLOPT_POSTFIELDS => $data,
                CURLOPT_HTTPHEADER => array(
                    "accept: application/json'",
                    "authorization: Bearer " . $apikey,
                    "content-type: application/json",
                ),
            ));
            $response = curl_exec($curl);
            $err = curl_error($curl);
            curl_close($curl);
            if ($err) {
                return "cURL Error #:" . $err;
            } else {
                return  $response;
            }
        }
        public function call_curl($url, $data, $apikey)
        {
            $curl = curl_init();
            curl_setopt_array($curl, array(
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => "",
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => "POST",
                CURLOPT_POSTFIELDS => $data,
                CURLOPT_HTTPHEADER => array(
                    "accept: application/json'",
                    "authorization: Bearer " . $apikey,
                    "content-type: application/json",
                ),
            ));
            $response = curl_exec($curl);
            $err = curl_error($curl);
            curl_close($curl);
            if ($err) {
                return "cURL Error #:" . $err;
            } else {
                return  $response;
            }
        }
        public function bitnob_get_pages()
        {
            $wp_pages = get_pages('sort_column=menu_order');
            $page_list = array();
            if ($title) $page_list[] = $title;
            foreach ($wp_pages as $page) {
                $prefix = '';
                // show indented child pages?
                if ($indent) {
                    $has_parent = $page->post_parent;
                    while ($has_parent) {
                        $prefix .=  ' - ';
                        $next_page = get_post($has_parent);
                        $has_parent = $next_page->post_parent;
                    }
                }
                // add to page list array array
                $page_list[$page->ID] = $prefix . $page->post_title;
            }
            return $page_list;
        }
    }
}
