<?php

namespace Drupal\uc_nochex\Plugin\Ubercart\PaymentMethod;

use Drupal\Component\Utility\Unicode;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\uc_order\OrderInterface;
use Drupal\uc_payment\OffsitePaymentMethodPluginInterface;
use Drupal\uc_payment\PaymentMethodPluginBase;

/**
 * Defines the nochex payment method.
 *
 * @UbercartPaymentMethod(
 *   id = "nochex",
 *   name = @Translation("nochex"),
 *   redirect = "\Drupal\uc_nochex\Form\NochexForm",
 * )
 */
class Nochex extends PaymentMethodPluginBase implements OffsitePaymentMethodPluginInterface {

  /**
   * {@inheritdoc}
   */
  public function getDisplayLabel($label) {
  
    $build['label'] = array(
      '#plain_text' => $label,
      '#suffix' => '<br />',
    );
    $build['image'] = array(
      '#theme' => 'image',
      '#uri' => 'https://www.nochex.com/logobase-secure-images/logobase-banners/clear.png',
      '#alt' => $this->t('Nochex APC Module'),
      '#attributes' => array('style' => 'max-width:250px;"'),
    );

    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'check' => FALSE,
      'transType' => 'No',
      'XMLColl' => 'No',
      'HideBil' => 'No',
      'postSep' => 'No',
      'compStatus' => 'processing',
      'canStatus' => 'abandoned',
      'sid' => '',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form['sid'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Nochex Merchant ID / Email Address'),
      '#description' => $this->t('Your registered Nochex Merchant ID / Email Address.'),
      '#default_value' => $this->configuration['sid'],
    );
	$form['transType'] = array(
      '#type' => 'radios',
      '#title' => $this->t('Test Mode'),
      '#options' => array(
        'Yes' => $this->t('Test'),
        'No' => $this->t('Live'),
      ),
      '#default_value' => $this->configuration['transType'],
    );
	$form['XMLColl'] = array(
      '#type' => 'radios',
      '#title' => $this->t('Detailed Product Information'),
      '#options' => array(
        'Yes' => $this->t('Yes'),
        'No' => $this->t('No'),
      ),
      '#default_value' => $this->configuration['XMLColl'],
    );
	$form['HideBil'] = array(
      '#type' => 'radios',
      '#title' => $this->t('Hide Billing Details'),
      '#options' => array(
        'Yes' => $this->t('Yes'),
        'No' => $this->t('No'),
      ),
      '#default_value' => $this->configuration['HideBil'],
    );
	$form['postSep'] = array(
      '#type' => 'radios',
      '#title' => $this->t('Postage'),
      '#options' => array(
        'Yes' => $this->t('Yes'),
        'No' => $this->t('No'),
      ),
      '#default_value' => $this->configuration['postSep'],
    );
		$form['compStatus'] = array(
      '#type' => 'select',
      '#title' => $this->t('Payment Completed Status'),
      '#options' => array(
        'processing' => $this->t('Processing'),
        'pending' => $this->t('Pending'),
        'payment_received' => $this->t('Payment Received'),
        'completed' => $this->t('Completed'),
      ),
      '#default_value' => $this->configuration['compStatus'],
    );
	  $form['canStatus'] = array(
      '#type' => 'select',
      '#title' => $this->t('Order Cancelled Status'),
      '#options' => array(
        'abandoned' => $this->t('Abandoned'),
        'canceled' => $this->t('Cancelled'),
        'pending' => $this->t('Pending'), 
      ),
      '#default_value' => $this->configuration['canStatus'],
    );
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $this->configuration['sid'] = $form_state->getValue('sid');
    $this->configuration['transType'] = $form_state->getValue('transType');
    $this->configuration['XMLColl'] = $form_state->getValue('XMLColl');
    $this->configuration['HideBil'] = $form_state->getValue('HideBil');
    $this->configuration['postSep'] = $form_state->getValue('postSep');
    $this->configuration['compStatus'] = $form_state->getValue('compStatus');
    $this->configuration['canStatus'] = $form_state->getValue('canStatus');
  }

  /**
   * {@inheritdoc}
   */
  public function cartDetails(OrderInterface $order, array $form, FormStateInterface $form_state) {
    $build = array();
    $session = \Drupal::service('session');
    if ($this->configuration['check']) {
      $build['pay_method'] = array(
        '#type' => 'select',
        '#title' => $this->t('Select your payment type:'),
        '#default_value' => $session->get('pay_method') == 'CK' ? 'CK' : 'CC',
        '#options' => array(
          'CC' => $this->t('Credit card'),
          'CK' => $this->t('Online check'),
        ),
      );
      $session->remove('pay_method');
    }

    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function cartProcess(OrderInterface $order, array $form, FormStateInterface $form_state) {
    $session = \Drupal::service('session');
    if (NULL != $form_state->getValue(['panes', 'payment', 'details', 'pay_method'])) {
      $session->set('pay_method', $form_state->getValue(['panes', 'payment', 'details', 'pay_method']));
    }
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function cartReviewTitle() {
   
      return $this->t('Credit / Debit Card (Nochex)');
  
  }

  /**
   * {@inheritdoc}
   */
  public function buildRedirectForm(array $form, FormStateInterface $form_state, OrderInterface $order = NULL) {
    $address = $order->getAddress('billing');
	
   $delivery_address = $order->getAddress('delivery');
	
    if ($address->country) {
      $country = \Drupal::service('country_manager')->getCountry($address->country)->getAlpha3();
    }
    else {
      $country = '';
    }

 
	$description = "";
	$xmlCollection = "<items>";
    foreach ($order->products as $product) {
	  $description .= "Product Name: " .$product->title->value. " Qty: ". $product->qty->value . " Price: ". uc_currency_format($product->price->value, FALSE, FALSE, '.');
	  $xmlCollection .= "<item><id></id><name>" .$product->title->value. "</name><description></description><quantity>" .$product->qty->value. "</quantity><price>" .uc_currency_format($product->price->value, FALSE, FALSE, '.'). "</price></item>";
    }
	$xmlCollection .= "</items>";

if ($this->configuration['transType'] == "No"){

$testTransaction = "";

}else{

$testTransaction = "100";

}


if ($this->configuration['HideBil'] == "No"){

$hideBilling = "";

}else{

$hideBilling = "true";

}

if ($this->configuration['XMLColl'] == "No"){

$xmlCollection = ""; 

}else{

$description = "Order (#".$order->id().")";

}

    $data = array(
      'merchant_id' => $this->configuration['sid'],
      'billing_fullname' => Unicode::substr($address->first_name . ' ' . $address->last_name, 0, 128),
      'billing_address' => Unicode::substr($address->street1, 0, 64) . ", ". Unicode::substr($address->street2, 0, 64),
      'billing_city' => Unicode::substr($address->city, 0, 64),
      'billing_postcode' => Unicode::substr($address->postal_code, 0, 16),
	  'delivery_fullname' => Unicode::substr($delivery_address->first_name . ' ' . $delivery_address->last_name, 0, 128),
      'delivery_address' => Unicode::substr($delivery_address->street1, 0, 64) . ", ". Unicode::substr($delivery_address->street2, 0, 64),
      'delivery_city' => Unicode::substr($delivery_address->city, 0, 64),
      'delivery_postcode' => Unicode::substr($delivery_address->postal_code, 0, 16),
      'email_address' => Unicode::substr($order->getEmail(), 0, 64),
      'customer_phone_number' => Unicode::substr($address->phone, 0, 16),
      'order_id' => $order->id(),
      'amount' => uc_currency_format($order->getTotal(), FALSE, FALSE, '.'),
      'description' => $description, 
      'hide_billing_details' => $hideBilling, 
      'test_transaction' => $testTransaction, 
      'xml_item_collection' => $xmlCollection, 
      'cancel_url' => Url::fromRoute('uc_nochex.cancel', ['oc' => $order->id()], ['absolute' => TRUE])->toString(),
      'callback_url' => Url::fromRoute('uc_nochex.apc', [], ['absolute' => TRUE])->toString(),
      'success_url' => Url::fromRoute('uc_nochex.complete', ['oc' => $order->id()], ['absolute' => TRUE])->toString(),
      'test_success_url' => Url::fromRoute('uc_nochex.complete', ['oc' => $order->id()], ['absolute' => TRUE])->toString(),
    );

    $form['#action'] = "https://secure.nochex.com/default.aspx";

    foreach ($data as $name => $value) {
      $form[$name] = array('#type' => 'hidden', '#value' => $value);
    }

    $form['actions'] = array('#type' => 'actions');
    $form['actions']['submit'] = array(
      '#type' => 'submit',
      '#value' => $this->t('Submit order'),
    );

    return $form;
  }

}
