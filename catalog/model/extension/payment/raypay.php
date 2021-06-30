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
class ModelExtensionPaymentRayPay extends Model
{
    public function getMethod($address)
    {
        if ($this->config->get('payment_raypay_status')) {
            $status = true;
        } else {
            $status = false;
        }
        $method_data = array();
        if ($status) {
            $method_data = array(
                'code' => 'raypay',
                'title' => $this->config->get('payment_raypay_title'),
                'terms' => '',
                'sort_order' => $this->config->get('payment_raypay_sort_order')
            );
        }
        return $method_data;
    }
}

?>
