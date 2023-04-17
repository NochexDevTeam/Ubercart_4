<?php

namespace Drupal\uc_nochex\Controller;

use Drupal\Component\Utility\SafeMarkup;
use Drupal\Component\Utility\Unicode;
use Drupal\Core\Controller\ControllerBase;
use Drupal\uc_cart\CartManagerInterface;
use Drupal\Core\Url;
use Drupal\uc_order\Entity\Order;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Controller routines for uc_nochex.
 */
class NochexController extends ControllerBase {

  /**
   * The cart manager.
   *
   * @var \Drupal\uc_cart\CartManager
   */
  protected $cartManager;

  /**
   *
   * @param \Drupal\uc_cart\CartManagerInterface $cart_manager
   *   The cart manager.
   */
  public function __construct(CartManagerInterface $cart_manager) {
    $this->cartManager = $cart_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    // @todo: Also need to inject logger
    return new static(
      $container->get('uc_cart.manager')
    );
  }


  /**
   * Finalizes nochex transaction.
   *
   * @param int $cart_id
   *   The cart identifier.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request of the page.
   */
  public function cancel($cart_id = 0, Request $request) {
   
    $order = Order::load($_GET["oc"]);
		
    $plugin = \Drupal::service('plugin.manager.uc_payment.method')->createFromOrder($order);  
	$configuration = $plugin->getConfiguration();
		
	$order->setStatusId($configuration["canStatus"])->save();	
	uc_order_comment_save($_GET["oc"], 0, $this->t('Order has been cancelled.'));      
	$order->save();
	
	$emptyCart = \Drupal::service('uc_cart.manager')->emptyCart();

	
	header("Location:".Url::fromRoute('uc_cart.checkout', [], ['absolute' => TRUE])->toString());

	die("Cancelled Order");
	break;
	
  }

  public function emptyCart($id = NULL) {
    $this->get($id)->emptyCart();
  }

  /**
   * Finalizes nochex transaction.
   *
   * @param int $cart_id
   *   The cart identifier.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request of the page.
   */
  public function complete($cart_id = 0, Request $request) {
   
    $order = Order::load($_GET["oc"]);
	
    $plugin = \Drupal::service('plugin.manager.uc_payment.method')->createFromOrder($order);
	$configuration = $plugin->getConfiguration();
	
$order->setStatusId($configuration["compStatus"])->save();

    return $this->cartManager->completeSale($order);
  }

  /**
   * React on INS messages from nochex.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request of the page.
   */
  public function apc(Request $request) {
  
  $postvars = http_build_query($_POST);
  
  $url = "https://secure.nochex.com/apc/apc.aspx";		 	
		$ch = curl_init ();
		curl_setopt ($ch, CURLOPT_URL, $url);
		curl_setopt ($ch, CURLOPT_POST, true);
		curl_setopt ($ch, CURLOPT_POSTFIELDS, $postvars);
		curl_setopt ($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt ($ch, CURLOPT_SSL_VERIFYHOST, false);
		curl_setopt ($ch, CURLOPT_SSL_VERIFYPEER, 0);
		$output = curl_exec ($ch);
		curl_close ($ch);
		
	 
	$order = Order::load($_POST["order_id"]);
	$plugin = \Drupal::service('plugin.manager.uc_payment.method')->createFromOrder($order);
	$configuration = $plugin->getConfiguration();
	
	if($output == "AUTHORISED"){	
		$msg = "APC was " . $output . ", and this was a " . $_POST["status"]." transaction";	
	}else{
		$msg = "APC was " . $output . ", and this was a " . $_POST["status"]." transaction";
	}
		
    uc_order_comment_save($_POST["order_id"], 0, $msg);
	uc_payment_enter($_POST["order_id"], 'nochex_apc', $_POST["amount"], $order->getOwnerId(), NULL, $msg, 'admin');
	
	die("");
	
  }

}
