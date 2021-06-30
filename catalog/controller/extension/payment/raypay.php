<?php

/**
 * RayPay payment gateway
 *
 * @developer hanieh729
 * @publisher RayPay
 * @copyright (C) 2021 RayPay
 * @license http://www.gnu.org/licenses/gpl-2.0.html GPLv2 or later
 *
 * https://raypay.ir
 */
class ControllerExtensionPaymentRayPay extends Controller
{

    /**
     * @param $id
     * @return string
     */
    public function generateString($id)
    {
        return 'RayPay Invoice ID: ' . $id;
    }

    /**
     * @return mixed
     */
    public function index()
    {
        $this->load->language('extension/payment/raypay');

        $data['text_connect'] = $this->language->get('text_connect');
        $data['text_loading'] = $this->language->get('text_loading');
        $data['text_wait'] = $this->language->get('text_wait');
        $data['button_confirm'] = $this->language->get('button_confirm');

        return $this->load->view('extension/payment/raypay', $data);
    }

    /**
     *
     */
    public function confirm()
    {
        $this->load->language('extension/payment/raypay');
        $json = array();

        $this->load->model('checkout/order');
        /** @var \ModelCheckoutOrder $model */
        $model = $this->model_checkout_order;
        $order_id = $this->session->data['order_id'];
        $order_info = $model->getOrder($order_id);

        $data['return'] = $this->url->link('checkout/success', '', true);
        $data['cancel_return'] = $this->url->link('checkout/payment', '', true);
        $data['back'] = $this->url->link('checkout/payment', '', true);
        $data['order_id'] = $this->session->data['order_id'];

        $user_id = $this->config->get('payment_raypay_user_id');
        $acceptor_code = $this->config->get('payment_raypay_acceptor_code');
        $amount = $this->correctAmount($order_info);

        $desc = $this->language->get('text_order_no') . $order_info['order_id'];
        $redirectUrl = $this->url->link('extension/payment/raypay/callback', '', true) .'&order_id='. $order_id .'&';
        $invoice_id             = round(microtime(true) * 1000);

        if (empty($amount)) {
            $json['error'] = 'واحد پول انتخاب شده پشتیبانی نمی شود.';
        }
        // Customer information
        $name = $order_info['firstname'] . ' ' . $order_info['lastname'];
        $mail = $order_info['email'];
        $phone = $order_info['telephone'];

        $data = array(
            'amount'       => strval($amount),
            'invoiceID'    => strval($invoice_id),
            'userID'       => $user_id,
            'redirectUrl'  => $redirectUrl,
            'factorNumber' => strval($order_id),
            'acceptorCode' => $acceptor_code,
            'email'        => $mail,
            'mobile'       => $phone,
            'fullName'     => $name,
            'comment'      => $desc
        );

        $ch = curl_init('https://api.raypay.ir/raypay/api/v1/Payment/getPaymentTokenWithUserID');
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json'
        ));

        $result = curl_exec($ch);

        $result = json_decode($result);

        $http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

       

        if ($http_status != 200 || empty($result) || empty($result->Data)) {
            // Set Order status id to 10 (Failed) and add a history.
            $msg         = sprintf('خطا هنگام ایجاد تراکنش. کد خطا: %s - پیام خطا: %s', $http_status, $result->Message);
            $model->addOrderHistory($order_id, 10, $msg, true);
            $json['error'] = $msg;
        } else {
            // Add a specific history to the order with order status 1 (Pending);
            $model->addOrderHistory($order_id, 1, $this->generateString($invoice_id), false);
            $model->addOrderHistory($order_id, 1, 'در حال هدایت به درگاه پرداخت رای پی', false);
            $access_token = $result->Data->Accesstoken;
            $terminal_id  = $result->Data->TerminalID;

            $json['action'] = 'https://mabna.shaparak.ir:8080/Pay';
            $json['token'] = $access_token;
            $json['terminal_id'] = $terminal_id;
        }
        $this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
    }

    /**
     * http request callback
     */
    public function callback()
    {
        if ($this->session->data['payment_method']['code'] == 'raypay') {
          
            $order_id = $_GET['order_id'];
            $invoice_id = $_GET['?invoiceID'];

            $this->load->language('extension/payment/raypay');

            $this->document->setTitle($this->config->get('payment_raypay_title'));

            $data['heading_title'] = $this->config->get('payment_raypay_title');
            $data['peyment_result'] = "";

            $data['breadcrumbs'] = array();
            $data['breadcrumbs'][] = array(
                'text' => $this->language->get('text_home'),
                'href' => $this->url->link('common/home', '', true)
            );
            $data['breadcrumbs'][] = array(
                'text' => $this->config->get('payment_raypay_title'),
                'href' => $this->url->link('extension/payment/raypay/callback', '', true)
            );


            if ($this->session->data['order_id'] != $order_id) {
                $comment = 'شماره سفارش اشتباه است.';
                $data['peyment_result'] = $comment;
                $data['button_continue'] = $this->language->get('button_view_cart');
                $data['continue'] = $this->url->link('checkout/cart');
            } else {
                $this->load->model('checkout/order');

                /** @var  \ModelCheckoutOrder $model */
                $model = $this->model_checkout_order;
                $order_info = $model->getOrder($order_id);

                if (!$order_info) {
                    $comment = $this->raypay_get_failed_message($invoice_id);
                    // Set Order status id to 10 (Failed) and add a history.
                    $model->addOrderHistory($order_id, 10, $comment, true);
                    $data['peyment_result'] = $comment;
                    $data['button_continue'] = $this->language->get('button_view_cart');
                    $data['continue'] = $this->url->link('checkout/cart');
                } else {
                    $verify_data = array('order_id' => $order_id);
                    $url = 'https://api.raypay.ir/raypay/api/v1/Payment/checkInvoice?pInvoiceID=' . $invoice_id;
                        $ch = curl_init();
                        curl_setopt($ch, CURLOPT_URL, $url);
                        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($verify_data));
                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
                        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                            'Content-Type: application/json',
                        ));

                        $result = curl_exec($ch);
                        $result = json_decode($result);
                        $http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                        curl_close($ch);

                        if ($http_status != 200) {
                            $msg = sprintf('خطا هنگام بررسی تراکنش. کد خطا: %s - پیام خطا: %s', $http_status, $result->Message);
                            // Set Order status id to 10 (Failed) and add a history.
                            $model->addOrderHistory($order_id, 10, $msg, true);
                            $data['peyment_result'] = $msg;
                            $data['button_continue'] = $this->language->get('button_view_cart');
                            $data['continue'] = $this->url->link('checkout/cart');
                        } else {
                            $state           = $result->Data->State;
                            $verify_order_id = $result->Data->FactorNumber;
                            $verify_amount   = $result->Data->Amount;


                            //get result id from database
                            $sql = $this->db->query('SELECT `comment`  FROM ' . DB_PREFIX . 'order_history WHERE order_id = ' . $order_id . ' AND `comment` LIKE "' . $this->generateString($invoice_id) . '"');

                            if (empty($verify_order_id) || empty($verify_amount) || $state !== 1) {
                                $comment = $this->raypay_get_failed_message($invoice_id);
                                // Set Order status id to 10 (Failed) and add a history.
                                $model->addOrderHistory($order_id, 10, $comment, true);
                                $data['peyment_result'] = $comment;
                                $data['button_continue'] = $this->language->get('button_view_cart');
                                $data['continue'] = $this->url->link('checkout/cart');

                            } else { // Transaction is successful.

                                $comment = $this->raypay_get_success_message($invoice_id);
                                $config_successful_payment_status = $this->config->get('payment_raypay_order_status_id');
                                // Set Order status id to the configured status id and add a history.
                                $model->addOrderHistory($verify_order_id, $config_successful_payment_status, $comment, true);
                                $data['peyment_result'] = $comment;
                                $data['button_continue'] = $this->language->get('button_complete');
                                $data['continue'] = $this->url->link('checkout/success');
                            }
                        }
                   
                        
                }
            }
            $data['column_left'] = $this->load->controller('common/column_left');
            $data['column_right'] = $this->load->controller('common/column_right');
            $data['content_top'] = $this->load->controller('common/content_top');
            $data['content_bottom'] = $this->load->controller('common/content_bottom');
            $data['footer'] = $this->load->controller('common/footer');
            $data['header'] = $this->load->controller('common/header');
            $this->response->setOutput($this->load->view('extension/payment/raypay_confirm', $data));

        }
    }

    /**
     * @param $order_info
     * @return int
     */
    private function correctAmount($order_info)
    {
        $amount = $this->currency->format($order_info['total'], $order_info['currency_code'], $order_info['currency_value'], false);
        $amount = round($amount);
        $amount = $this->currency->convert($amount, $order_info['currency_code'], "RLS");
        return (int)$amount;
    }


    /**
     * @param $invoice_id
     * @return mixed
     */
    public function raypay_get_success_message($invoice_id)
    {
        return str_replace(["invoice_id}"], [$invoice_id], $this->config->get('payment_raypay_success_massage'));
    }

    /**
     * @param $invoice_id
     * @return string
     */
    public function raypay_get_failed_message($invoice_id)
    {
        return str_replace(["{invoice_id}"], [$invoice_id], $this->config->get('payment_raypay_failed_massage'));
    }
}

?>
