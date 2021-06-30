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
     * @var array
     */
    private $error = array();

    /**
     *  RayPay setting for admin
     */
    public function index()
    {
        $this->load->language('extension/payment/raypay');

        $this->document->setTitle(strip_tags($this->language->get('heading_title')));

        $this->load->model('setting/setting');

        if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
            $this->model_setting_setting->editSetting('payment_raypay', $this->request->post);

            $this->session->data['success'] = $this->language->get('text_success');

            $this->response->redirect($this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment', true));
        }

        if (isset($this->error['warning'])) {
            $data['error_warning'] = $this->error['warning'];
        } else {
            $data['error_warning'] = '';
        }

        if (isset($this->error['user_id'])) {
            $data['error_user_id'] = $this->error['user_id'];
        } else {
            $data['error_user_id'] = '';
        }
        if (isset($this->error['acceptor_code'])) {
            $data['error_acceptor_code'] = $this->error['acceptor_code'];
        } else {
            $data['error_acceptor_code'] = '';
        }

        $data['breadcrumbs'] = array();

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
        );

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_extension'),
            'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment', true)
        );

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('heading_title'),
            'href' => $this->url->link('extension/payment/raypay', 'user_token=' . $this->session->data['user_token'], true)
        );

        $data['action'] = $this->url->link('extension/payment/raypay', 'user_token=' . $this->session->data['user_token'], true);

        $data['cancel'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment', true);

        if (isset($this->request->post['payment_raypay_user_id'])) {
            $data['payment_raypay_user_id'] = $this->request->post['payment_raypay_user_id'];
        } else {
            $data['payment_raypay_user_id'] = $this->config->get('payment_raypay_user_id');
        }

        if (isset($this->request->post['payment_raypay_acceptor_code'])) {
            $data['payment_raypay_acceptor_code'] = $this->request->post['payment_raypay_acceptor_code'];
        } else {
            $data['payment_raypay_acceptor_code'] = $this->config->get('payment_raypay_acceptor_code');
        }


        if (isset($this->request->post['payment_raypay_order_status_id'])) {
            $data['payment_raypay_order_status_id'] = $this->request->post['payment_raypay_order_status_id'];
        } else {
            $data['payment_raypay_order_status_id'] = $this->config->get('payment_raypay_order_status_id');
        }

        $this->load->model('localisation/order_status');

        $data['order_statuses'] = $this->model_localisation_order_status->getOrderStatuses();

        if (isset($this->request->post['payment_raypay_status'])) {
            $data['payment_raypay_status'] = $this->request->post['payment_raypay_status'];
        } else {
            $data['payment_raypay_status'] = $this->config->get('payment_raypay_status');
        }

        if (isset($this->request->post['payment_raypay_sort_order'])) {
            $data['payment_raypay_sort_order'] = $this->request->post['payment_raypay_sort_order'];
        } else {
            $data['payment_raypay_sort_order'] = $this->config->get('payment_raypay_sort_order');
        }

        if (isset($this->request->post['payment_raypay_failed_massage'])) {
            $data['payment_raypay_failed_massage'] = $this->request->post['payment_raypay_failed_massage'];
        } else {
            $data['payment_raypay_failed_massage'] = $this->config->get('payment_raypay_failed_massage');
        }

        if (isset($this->request->post['payment_raypay_success_massage'])) {
            $data['payment_raypay_success_massage'] = $this->request->post['payment_raypay_success_massage'];
        } else {
            $data['payment_raypay_success_massage'] = $this->config->get('payment_raypay_success_massage');
        }

        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view('extension/payment/raypay', $data));
    }

    /**
     * @return bool
     */
    protected function validate()
    {
        if (!$this->user->hasPermission('modify', 'extension/payment/raypay')) {
            $this->error['warning'] = $this->language->get('error_permission');
        }

        if (!$this->request->post['payment_raypay_user_id']) {
            $this->error['user_id'] = $this->language->get('error_user_id');
        }
        if (!$this->request->post['payment_raypay_acceptor_code']) {
            $this->error['acceptor_code'] = $this->language->get('error_acceptor_code');
        }

        return !$this->error;
    }

}

?>
