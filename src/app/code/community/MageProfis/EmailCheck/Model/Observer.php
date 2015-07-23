<?php

class MageProfis_EmailCheck_Model_Observer
{
    
    protected $_orderStates = array('pending', 'processing', 'new');
    
    public function sendOrderEmail()
    {
        $dateFrom = date('Y-m-d H:i:s', strtotime('-3 days'));
        $dateTo = date('Y-m-d H:i:s', strtotime('-15 min'));
        $collection = Mage::getModel('sales/order')->getCollection()
                ->addFieldToSelect(array('entity_id'))
                ->addFieldToFilter('email_sent',
                    array(
                        array('eq'   => 0 ), 
                        array('null' => true )
                    )
                )
                ->addFieldToFilter('state', array('in' => $this->_orderStates))
                ->addFieldToFilter('created_at', array(
                    'from' => $dateFrom,
                    'to' => $dateTo,
                ))
                ->setOrder('store_id', 'ASC')
        ;
        $storeId = null;
        $appEmulation = Mage::getSingleton('core/app_emulation');
        $initialEnvironmentInfo = null;
        foreach($collection->getAllIds() as $_id)
        {
            $order = Mage::getModel('sales/order')->load($_id);
            /** @var Mage_Sales_Model_Order $order */

            if(is_null($storeId))
            {
                $initialEnvironmentInfo = $appEmulation->startEnvironmentEmulation($storeId);
            } elseif($storeId != $order->getStoreId())
            {
                $appEmulation->stopEnvironmentEmulation($initialEnvironmentInfo);
                $initialEnvironmentInfo = $appEmulation->startEnvironmentEmulation($order->getStoreId());
            }
            $storeId = $order->getStoreId();

            // get next item, if email_sent is set or state has been changed!
            if( (int) $order->getEmailSent() == 1 && !in_array($order->getSate(), $this->_orderStates))
            {
                continue;
            }

            $payment = $order->getPayment();
            /** @var Mage_Sales_Model_Order_Payment $payment */
            if($payment && $payment->getId())
            {
                $instance = $payment->getMethodInstance();
                if($instance && in_array($instance->getCode(), array('paypal_standard', 'paypal_express', 'hpcc', 'hpsu')) && $order->getState() != 'processing')
                {
                    continue;
                }
            }

            try {
                $order->sendNewOrderEmail();
                Mage::log('Order E-Mail has been send: '. $order->getIncrementId(), null, 'orderemail.log', true);
            } catch(Exception $e)
            {
                Mage::log('Error While Sending E-Mail: '. $order->getIncrementId(), null, 'orderemail.log', true);
            }
        }
        if(!is_null($storeId))
        {
            $appEmulation->stopEnvironmentEmulation($initialEnvironmentInfo);
        }
    }
}
