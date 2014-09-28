<?php
/*
* 2007-2014 PrestaShop
*
* NOTICE OF LICENSE
*
* This source file is subject to the Academic Free License (AFL 3.0)
* that is bundled with this package in the file LICENSE.txt.
* It is also available through the world-wide-web at this URL:
* http://opensource.org/licenses/afl-3.0.php
* If you did not receive a copy of the license and are unable to
* obtain it through the world-wide-web, please send an email
* to license@prestashop.com so we can send you a copy immediately.
*
* DISCLAIMER
*
* Do not edit or add to this file if you wish to upgrade PrestaShop to newer
* versions in the future. If you wish to customize PrestaShop for your
* needs please refer to http://www.prestashop.com for more information.
*
*  @author PrestaShop SA <contact@prestashop.com>
*  @copyright  2007-2014 PrestaShop SA
*  @license    http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*/

if (!defined('_PS_VERSION_'))
	exit;

class Googleanalytics extends Module
{
	/**
	* initiate Google Analytics module
	*/
	public function __construct()
	{
		$this->name = 'googleanalytics';
		$this->tab = 'analytics_stats';
		$this->version = '0.9(beta)';
		$this->author = 'MageBinary';
		$this->need_instance = 1;
		$this->ps_versions_compliancy = array('min' => '1.5', 'max' => '1.6');
		$this->bootstrap = true;

		parent::__construct();

		$this->displayName = $this->l('Google Analytics');
		$this->description = $this->l('This is the GoogleAnalytics extension for Prestashop, using enhanced e-commerce Google Analytics API');

		$this->confirmUninstall = $this->l('Are you sure you want to uninstall GoogleAnalytics?');

	}

	/**
	* install module *
	* @return bool
	*/
	public function install()
	{
		if (Shop::isFeatureActive())
		Shop::setContext(Shop::CONTEXT_ALL);

		unlink(_PS_OVERRIDE_DIR_.'class/Link.php');

		if (!parent::install() ||
			!$this->registerHook('header') ||
			!$this->registerHook('adminOrder') ||
			!$this->registerHook('footer') ||
			!$this->registerHook('home') ||
			!$this->registerHook('productfooter') ||
			!$this->registerHook('shoppingCart') ||
			!$this->registerHook('top') ||
			!$this->registerHook('backOfficeHeader') ||
			!$this->registerHook('displayBackOfficeHeader') ||
			!$this->registerHook('actionProductCancel') ||
			!$this->registerHook('actionCartSave') ||
			!$this->registerHook('displayShoppingCart'))

		return false;

		//drop transaction table
		Db::getInstance()->Execute('DROP TABLE IF EXISTS `'._DB_PREFIX_.'googleanalytics`');
		//create transaction table
		$query = 'CREATE TABLE `'._DB_PREFIX_.'googleanalytics` (
		`id_google_analytics` INT NOT NULL AUTO_INCREMENT PRIMARY KEY ,
		`id_order` INT NOT NULL ,
		`sent` Boolean,
		`date_add` DateTime
		)';

		if (!Db::getInstance()->Execute($query))
		{
			$this->uninstall();
			return false;
		}

		return true;
	}

	/**
	* uninstall module
	* @return bool
	*/
	public function uninstall()
	{
		if (!parent::uninstall())
		return false;

		//delete override class
		unlink(_PS_OVERRIDE_DIR_.'class/Link.php');

		//drop transaction table
		Db::getInstance()->Execute('DROP TABLE IF EXISTS `'._DB_PREFIX_.'googleanalytics`');

		return true;
	}




	/**
	* back office return configuration form
	* @return mixed
	*/
	public function displayForm()
	{
		// Get default Language
		$default_lang = (int)Configuration::get('PS_LANG_DEFAULT');

		$helper = new HelperForm();

		// Module, t    oken and currentIndex
		$helper->module = $this;
		$helper->name_controller = $this->name;
		$helper->token = Tools::getAdminTokenLite('AdminModules');
		$helper->currentIndex = AdminController::$currentIndex.'&configure='.$this->name;

		// Language
		$helper->default_form_language = $default_lang;
		$helper->allow_employee_form_lang = $default_lang;

		// Title and toolbar
		$helper->title = $this->displayName;
		$helper->show_toolbar = true;        // false -> remove toolbar
		$helper->toolbar_scroll = true;      // yes - > Toolbar is always visible on the top of the screen.
		$helper->submit_action = 'submit'.$this->name;
		$helper->toolbar_btn = array(
			'save' =>
				array(
					'desc' => $this->l('Save'),
					'href' => AdminController::$currentIndex.'&configure='.$this->name.'&save'.$this->name.
					'&token='.Tools::getAdminTokenLite('AdminModules'),
				),
			'back' => array(
				'href' => AdminController::$currentIndex.'&token='.Tools::getAdminTokenLite('AdminModules'),
				'desc' => $this->l('Back to list')
			)
		);

		// Init Fields form array
		$fields_form[0]['form'] = array(
			'legend' => array(
				'title' => $this->l('Google Analytics General Settings'),
			),
			'input' => array(
				//add enable&disable switch
				array(
					'type' => 'switch',
					'label' => $this->l('Enable Google Analytics Module'),
					'name' => 'googleanalytics_enable',
					'is_bool' => true,
					'required' => true,
					'values' => array(
						array(
						'id' => 'googleanalytics_enable_yes',
						'value' => 1,
						'label' => $this->l('Yes'),
						),
						array(
						'id' => 'googleanalytics_enable_no',
						'value' => 0,
						'label' => $this->l('No')
						)
					),
					'hint' => $this->l('Enable or disable Google Analytics')
				),
				//add google analytics
				array(
					'type' => 'text',
					'label' => $this->l('Business Account ID'),
					'name' => 'GA_ACCOUNT_ID',
					'size' => 20,
					'required' => true,
					'hint'=>'Get tracking Id from google'
				),

			),
			'submit' => array(
				'title' => $this->l('Save'),
				'class' => 'button'
			)
		);
		// Load current value
		$helper->fields_value['googleanalytics_enable'] = Configuration::get('googleanalytics_enable');
		$helper->fields_value['GA_ACCOUNT_ID'] = Configuration::get('GA_ACCOUNT_ID');

		return $helper->generateForm($fields_form);

	}

	/**
	* back office module configuration page content
	*/
	public function getContent()
	{
		$output = '';
		if (Tools::isSubmit('submit'.$this->name))
		{
			$error = false;
			$googleanalytics_enable = Tools::getValue('googleanalytics_enable');
			$GA_ACCOUNT_ID = Tools::getValue('GA_ACCOUNT_ID');

			if (!$error)
			{
				Configuration::updateValue('googleanalytics_enable', $googleanalytics_enable);
				Configuration::updateValue('GA_ACCOUNT_ID', $GA_ACCOUNT_ID);
				$output .= $this->displayConfirmation($this->l('Settings updated'));
			}
		}
		return $output.$this->displayForm();
	}


	/**
	* hook page header to add CSS and JS files
	*/
	public function hookDisplayHeader()
	{
		//verified
		//echo "<br><font color=red size=30px>google analytics hook display header</font><br>";

		if (Configuration::get('googleanalytics_enable') != '' && Configuration::get('GA_ACCOUNT_ID') != '')
		{

			$this->context->smarty->assign(
				array(
					'GA_ACCOUNT_ID' => Configuration::get('GA_ACCOUNT_ID'),
				)
			);

			return $this->display(__FILE__, 'hookDisplayHeader.tpl');
		}

	}


	/**
	* return a detailed transaction for google analytics
	*/
	public function wrapOrder($orderid)
	{
		$order = new Order((int)$orderid);

		if(isset($order))
		{
			$transaction = array(
				'orderid' => $orderid,
				'storename' => $this->context->shop->name,
				'grandtotal' => $order->total_paid,
				'shipping' => $order->total_shipping,
				'tax' => $order->total_paid_tax_incl,
				'url' => $this->context->link->getModuleLink('googleanalytics','ajax'),
			);
			return $transaction;
		}
		else
		{
			return null;
		}


	}


	/**
	* hook top to track transactions
	*/
	public function hookDisplayTop()
	{
		$this->context->controller->addJs($this->_path.'views/templates/js/'.'GoogleAnalyticActionLib.js');

		$controller_name = Tools::getValue('controller');


		//add google analytics order
		if ($controller_name == 'orderconfirmation')
		{
			//ORDERID OR ORDER REFERNCE?
			$orderid = $this->context->controller->id_order;
			$order = new Order($orderid);
			$order_products = array();

			foreach($order->getProducts() as $order_product)
			{
				$order_products[] = $this->wrapProduct((int)$order_product['product_id'],array('qty'=>$order_product['product_quantity']));
			}

			//var_dump($order_products); die();

			$ga_order_record = Db::getInstance()->getRow('select sent from  `'._DB_PREFIX_.'googleanalytics` where id_order = '.(int)$orderid);

			if ($ga_order_record == null)
			{
				Db::getInstance()->execute('insert into  `'._DB_PREFIX_.'googleanalytics` (id_order, sent, date_add) values ('.$orderid.',false,'.time().') ');
				$ga_order_record = Db::getInstance()->getRow('select sent from  `'._DB_PREFIX_.'googleanalytics` where id_order = '.(int)$orderid);
			}
			//var_dump($order);die();
			if ($ga_order_record['sent'] != true)
			{
				$transaction = array(
						//'id' => $orderid,
						'id' => $order->reference,
						'affiliation' => $this->context->shop->name,
						'revenue' => $order->total_paid,
						'shipping' => $order->total_shipping,
						'tax' => $order->total_paid_tax_incl - $order->total_paid_tax_excl,
						'url' => $this->context->link->getModuleLink('googleanalytics','ajax'),
				);
			$ga_scripts = $this->addTransaction($order_products, $transaction);

			return $this->runJS($ga_scripts);

			}
		}
	}

	/**
	* hook footer to load JS script for standards actions such as product clicks
	*/

	public function hookDisplayFooter()
	{
	/***Measuring a product view - Category page
	- Includes a JavaScript code on the product list page (category and sub-category) to send the product's details:
	o Product(s): id, name, type, category, brand, variant, list and position in the list.
	4
	- The “others” listing (home featured products, best sellers and news products) will display the details by re-implementing the way to get the products list from the Module into the Google Analytics Module.
	**/
		$controller_name = Tools::getValue('controller');

		$products = $this->context->smarty->getTemplateVars('products');

		//$this->debug($products);
		//var_dump($products);
		$products = $this->wrapProducts($products);



		// return category page product list
		// get variables assigned by smarty
		$ga_scripts = '';

		if ($controller_name == 'category' || $controller_name == 'search')
		{


			$ga_scripts .= $this->addProductImpressionAndClicks($products);

			/*mesuring a product view end*/

		}

		// hook add remove from cart start
		$cart_products = $this->wrapProducts($this->context->cart->getProducts());

		$this->context->smarty->assign(
			array(
				'remove_cart_products' => $cart_products,
			)
		);


		$cart_actions = $this->context->cookie->__get('ga_cart');
		if(isset($cart_actions))
		{
			$ga_scripts.=$cart_actions;
			$this->context->cookie->__unset('ga_cart');
		}
		// hook add remove from cart ends

		if ($controller_name == 'order' and Tools::getValue('step') == '1')
		{
			$ga_scripts .= $this->addProductFromCheckout($products);
		}

		if ($controller_name == 'order' )
		{
			$step = Tools::getValue('step');
			if(empty($step))
				$step = 0;

			$ProcessFlow = array('Summary','Address','Shipping','Payment','Succesful');
			if($step<sizeof($ProcessFlow))
				$ga_scripts .= $this->addCheckout($ProcessFlow[$step]);			
		}

		if ($controller_name == 'order-confirmation' )
		{
			$ga_scripts .= $this->addCheckout($this->l('Order Confirmation'));	
		}


		return $this->runJS($ga_scripts);

		//return $this->display(__FILE__, 'hookDisplayFooter.tpl');
	}

	/**
	* hook home to display generate the product list associated to home featured, news products and best sellers Modules
	*/
	public function hookDisplayHome()
	{
		$ga_scripts = '';
		//verified
		$controller_name = Tools::getValue('controller');

		//add home featured products
		if (!isset(HomeFeatured::$cache_products))
		{
			$category = new Category($this->context->shop->getCategory(), $this->context->language->id);
			$nb = (int)Configuration::get('HOME_FEATURED_NBR');
			$home_featured_products = $this->wrapProducts($category->getProducts((int)Context::getContext()->language->id, 1, ($nb ? $nb : 8), 'position'));

			$ga_scripts .= $this->addProductImpressionAndClicks($home_featured_products);
			/***
			foreach($home_featured_products as $product)
			{
				$ga_scripts .= "MBG.addProductImpression(".json_encode($product).");";
			}
			***/
		}

		//add new products list
		if (Configuration::get('NEW_PRODUCTS_NBR'))
		{
			if (Configuration::get('PS_NB_DAYS_NEW_PRODUCT') || Configuration::get('PS_BLOCK_NEWPRODUCTS_DISPLAY'))
			{
				$newProducts = Product::getNewProducts((int)$this->context->language->id, 0, (int)Configuration::get('NEW_PRODUCTS_NBR'));

				$ga_homenew_product_list = $this->wrapProducts($newProducts);

				$ga_scripts .= $this->addProductImpressionAndClicks($ga_homenew_product_list);
				/***
				foreach($ga_homenew_product_list as $product)
				{
					$ga_scripts .= "MBG.addProductImpression(".json_encode($product).");";
				}
				***/
			}
		}

		// add best sell product list
		if (!Configuration::get('PS_CATALOG_MODE') || Configuration::get('PS_BLOCK_BESTSELLERS_DISPLAY'))
		{
			$bestsell_products = ProductSale::getBestSalesLight((int)$this->context->language->id, 0, 8);
			$currency = new Currency($this->context->cart->id_currency);
			$usetax = (Product::getTaxCalculationMethod((int)$this->context->customer->id) != PS_TAX_EXC);
			foreach ($bestsell_products as &$row)
				$row['price'] = Tools::displayPrice(Product::getPriceStatic((int)$row['id_product'], $usetax), $currency);

			$ga_homebestsell_product_list = $this->wrapProducts($bestsell_products);

			$ga_scripts .= $this->addProductImpressionAndClicks($ga_homebestsell_product_list);
			/***
			foreach($ga_homebestsell_product_list as $product)
			{
				$ga_scripts .= "MBG.addProductImpression(".json_encode($product).");";
			}
			***/

		}

		return $this->runJS($ga_scripts);
/***measuring a product view start*****/
	}


	public function wrapProducts($products,$extras=array())
	{
		//$this->debug($products);
		$result_products = array();
		if(!is_array($products))
			return;
		foreach($products as $index => $product)
		{
			$result_products[] = $this->wrapProduct($product,$extras,$index);
		}



		return $result_products;
	}


	public function wrapProduct($product,$extras,$index=0)
	{

		$position = $index ? $index :'0';
		$product_qty = 1;
		$variant = null;

		if(isset($product['attributes_small'])) {
			$variant = $product['attributes_small'];
		}
		if(isset($extras['attributes_small'])) {
			$variant = $extras['attributes_small'];
		}
		/** Product Qty ***/
	    if(isset($extras['qty']))
		{

			$product_qty = $extras['qty'];
		}

		elseif(isset($product['cart_quantity']))
		{
			$product_qty = $product['cart_quantity'];

		}

		if(isset($product->id)) {
			$product = new Product($product->id, true, $this->context->language->id, $this->context->shop->id);

		}
		elseif(is_int($product)) {
			$product = new Product($product, true, $this->context->language->id, $this->context->shop->id);



		}elseif (isset($product['product_id']) && is_int($product['product_id'])) {
			$product = new Product($product['product_id'], true, $this->context->language->id, $this->context->shop->id);
			if($product['product_quantity'])
			{
				$product_qty = $product['product_quantity'];
			}

		}
		elseif(!empty($product["id_product"])) {

			/** Product Link ***/
			if(isset($product['link']))
			{
				$product_link =  $product['link'] ? :'';

				$product = new Product($product["id_product"], true, $this->context->language->id, $this->context->shop->id);

				$product->link = $product_link;
			}



		}

		else {
			return null;
		}


		if (Validate::isLoadedObject($product))
		{
			$category = new Category($product->id_category_default);
			$manufactory = Manufacturer::getNameById((int)$product->id_manufacturer);

			$producttype = 'typical';
			if (isset($product->pack) && $product->pack == 1)
				$producttype = 'pack';
			if (isset($product->vitural) && $product->vitural == 1)
				$producttype = 'vitural';

			$product_link = isset($product->link)? $product->link : '';

			$ga_product = array(
				'id'=>$product->reference,
				'name'=>Product::getProductName($product->id),
				'category'=>$category->getName(),
				'brand'=>$manufactory,
				'variant'=>$variant,
				'type'=>$producttype,
				'position'=>$position,
				'quantity'=>$product_qty,
				'list'=>Tools::getValue('controller'),
				'url'=>$product_link,
				'price'=>number_format($product->price,'2')
			);
			return $ga_product;
		}
		return null;

	}

	public function addTransaction($products,$order)
	{
		$js = '';
		foreach($products as $product)
		{
			$js .= 'MBG.add('.json_encode($product).');';
		}
		$js .= 'MBG.addTransaction('.json_encode($order).');';

		return $js;
	}

	public function addProductImpressionAndClicks($products)
	{

		$js = '';
		foreach($products as $product) {
			$js .=  "MBG.addProductImpression(".json_encode($product).");" . "MBG.addProductClick(".json_encode($product).");";
		}
		return $js;
	}

	public function addCheckout($step)
	{
		$js = '';
		$js .=  "MBG.addCheckout('".$step."'');";
		return $js;
	}

	public function addProductFromCheckout($products)
	{
		$js = '';
		//addCheckout
		if(!is_array($products))
			return;
		foreach($products as $product) {
			$js .=  "MBG.add(".json_encode($product).");";

		}
		$js .=  "MBG.addCheckout();";

		return $js;
	}
	/**
	* hook product page footer to load JS for product details view
	*/
	public function hookDisplayFooterProduct()
	{

		/***measuring a product details view start*****/
		$controller_name = Tools::getValue('controller');

		if ($controller_name == 'product')
		{
			//add product view
			$id_product = (int)Tools::getValue('id_product');
			$ga_product = $this->wrapProduct($id_product);
			return $this->runJS("MBG.addProductDetailView(".json_encode($ga_product).");");
		}

		return null;
		/***measuring a product details view end*****/
	}

	/**
	* hook shopping cart footer to send the checkout details
	*/
	public function hookDisplayShoppingCartFooter()
	{
		// hook add remove from cart
		$cart_products = $this->wrapProducts($this->context->cart->getProducts());


		$this->context->smarty->assign(
			array(
				'remove_cart_products' => $cart_products,
			)
		);

		//return $this->display(__FILE__, 'hookDisplayShoppingCartFooter.tpl');

	}

	public function runJS($jscode)
	{
		$currency = $this->context->currency->iso_code;
		$js = "
			<script>

				jQuery(document).ready(function(){
					var ga_prestashop_developerid='xxxxx';
					(window.gaDevIDs=window.gaDevIds||[]).push(ga_prestashop_developerid);
					var MBG = GoogleAnalyticEnhancedECommerce;
					MBG.setCurrency('$currency');
					".
					$jscode
					."
				});

			</script>
		";
		return $js;
	}


	/**
	* hook admin order to send transactions and refunds details
	*/
	public function hookDisplayAdminOrder()
	{
		$ga_scripts = $this->context->cookie->__get('ga_admin_refund');

		echo $this->runJS($ga_scripts);

		$this->context->cookie->__unset('ga_admin_refund');

		// old way of doing refund
		/*$orderid = $this->context->controller->id_order;
		$order = new Order($orderid);

		$this->context->smarty->assign(
			array(
				'orderid' => $orderid,
				'type' => 'standard',
				'affiliation' => $this->context->shop->name,
				'revenue' => $order->total_paid,
				'shipping' => $order->total_shipping,
				'tax' => $order->total_paid_tax_incl,
			)
		);

		return $this->display(__FILE__, 'hookDisplayAdminOrder.tpl');*/
	}

	/**
	 *  admin office header to add google analytics js
	 */
	public function hookDisplayBackOfficeHeader($params)
	{
		$this->context->controller->addJs($this->_path.'views/templates/js/'.'GoogleAnalyticActionLib.js');

		if (Configuration::get('googleanalytics_enable') != '' && Configuration::get('GA_ACCOUNT_ID') != '')
		{
			$this->context->smarty->assign(
				array(
					'GA_ACCOUNT_ID' => Configuration::get('GA_ACCOUNT_ID'),
				)
			);

			$ga_scripts = '';
			$ga_order_records = Db::getInstance()->ExecuteS('select * from  `'._DB_PREFIX_.'googleanalytics` where sent=0');

			foreach($ga_order_records as $row)
			{
				$transaction = $this->wrapOrder($row['id_order']);
				if(isset($transaction))
				{
					$transaction = json_encode($transaction);
					$ga_scripts .= 'MBG.addTransaction('.$transaction.');';
				}
			}

			return $this->display(__FILE__, 'hookDisplayBackOfficeHeader.tpl').$this->runJS($ga_scripts);
		}
	}


	 /**
	 * hook admin office header to add google analytics js
	 */
	public function hookActionProductCancel($params)
	{
		$qty_refunded = Tools::getValue('cancelQuantity');

		$Order = array(
			'id' => $params['order']->reference,
		);

		//TODO Convert below to Refund Function.
		$ga_scripts = '';
		foreach($qty_refunded as $orderdetail_id=>$qty)
		{
			$orderdetail = new OrderDetail($orderdetail_id);

			$Product = array(
				//'id' => $orderdetail->product_id,
				'id' => $orderdetail->product_reference,
				'quantity' => $qty,
			);
			//display ga refund product
			//$ga_scripts .= "MBG.refundByProduct(".json_encode($Product).",".json_encode($Order).");";
			$ga_scripts .= "MBG.add(".json_encode($Product).");";
		}
			$ga_scripts .= "MBG.refundByProduct(".json_encode($Order).");";

		$this->context->cookie->__set('ga_admin_refund', $ga_scripts);
	}
	/***
			!$this->registerHook('actionCartSave') ||
			!$this->registerHook('displayShoppingCart'))
	**/

	public function hookActionCartSave()
	{
		if(!isset($this->context->cart))
			return;

		$Cart = array(
			'addAction' => Tools::getValue('add') ? 'add':'',
			'removeAction' => Tools::getValue('delete') ? 'delete':'',
			'extraAction' => Tools::getValue('op') ? 'down':'up',
			'qty'=> Tools::getValue('qty') ? :'1'
			);
		try {
			$Cart_Products = $this->context->cart->getProducts();
		} catch (Exception $e) {

		}

		if(isset($Cart_Products))
		{
			foreach ($Cart_Products as $cart_product)
			{

				if($cart_product['id_product'] == Tools::getValue('id_product'))
				{
					$Cart['attributes_small'] = $cart_product['attributes_small'];
				}

			}
		}



		$ga_products = $this->wrapProduct((int)Tools::getValue('id_product'),$Cart);
		//print_r($Cart);

		$ga_scripts  = '';

		if($Cart['addAction'] == 'add' || $Cart['extraAction'] == 'up') {

			$ga_scripts .= "MBG.addToCart(".json_encode($ga_products).");";


		}

		if($Cart['removeAction'] == 'delete' || $Cart['extraAction'] == 'down') {

			$ga_scripts .= "MBG.removeFromCart(".json_encode($ga_products).");";


		}


		//echo $this->context->link->getModuleLink('googleanalytics','action');
		$this->context->cookie->__set('ga_cart', $this->context->cookie->__get('ga_cart').$ga_scripts);
	}

		public function displayShoppingCart($params)
	{
			//echo "DISPLAYED SHOPPING CART";

	}

		public function debug($params,$die) {

			if(is_array($params)){

				foreach ($params as $key => $value) {
					echo json_encode($value,JSON_PRETTY_PRINT);
				}
			}
			else {
				echo json_encode($params);
			}

			if($die) {
				die();
			}

		}


}
