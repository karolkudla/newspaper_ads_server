<?php

/* Add this to your functions.php file */

use Automattic\WooCommerce\Client;

/****************************************************************************************************/
/****************************************************************************************************/
/******************************** WYKREUJ PUSTE ZAMÓWIENIE ZWROTNE **********************************/
/****************************************************************************************************/
/****************************************************************************************************/

function create_empty_response_order($order) {
	
		foreach ( $order->get_items() as $item_id => $item ) {
			/* SPRAWDZAMY NUMERY REDAKCJI ABY WYSŁAC  JE W META DO SPRAWDZANIA PRZY ODBIERANIU ZWROTEK */
			$redactions_participating[] = get_post_meta($item->get_product_id(), 'numer_redakcji', true);
			$rp = array_unique($redactions_participating);
		}
	
		$data = [
			'customer_id' => $order->get_customer_id(),
			'status' => 'pending',
			'billing' => [
				'first_name' => $order->get_billing_first_name(),
				'last_name' => $order->get_billing_last_name(),
				'address_1' => $order->get_billing_address_1(),
				'address_2' => $order->get_billing_address_2(),
				'company' => $order->get_billing_company(),
				'city' => $order->get_billing_city(),
				'state' => $order->get_billing_state(),
				'postcode' => $order->get_billing_postcode(),
				'country' => $order->get_billing_country(),
				'email' => $order->get_billing_email(),
				'phone' => $order->get_billing_phone()
			],
			'meta_data' => [
				/* Suma zamówienia klienta, aby potem obliczyć nadpłatę / niedopłatę */
				[
					"key" => "client_total",
					"value" => $order->get_total()
				],
				/* Redakcje uczestniczące w zamówieniu, aby sprawdzać kto odpowiedział, a kto nie */
				[
					"key" => "redactions_participating",
					"value" => json_encode($rp)
				],
				/* Narzut aktualny przy składaniu zamówienia, żeby na zwrotce nie było narzutu ustalonego później */
				[
					"key" => "order_margin",
					"value" => get_field('field_5fc53d5b16dd4', 6558)
				],
			]
		];

		$woocommerce_vg = new Client(
			'https://gazeta.wokulski.online',
			'ck_94a52f57e7b19a60c2546dc5125b0d5bd7ce315f',
			'cs_6fcb9eba7dbc0e470feca5ab12fd172fb79dad28',
			[
				'wp_api' => true,
				'version' => 'wc/v3'
			]
		);

		$empty_response_order = $woocommerce_vg->post('orders', $data);
		
		$info = 'Na adres '.$order->get_billing_email().' został wysłany email z potwierdzeniem. Odpowiedź redakcji będzie widoczna w zamówieniu pod numerem '.$empty_response_order->id.' po zaksięgowaniu wpłaty.';
		/* Meta dla starego zamówienia */
		update_field('field_5fb939e2da29d',$info, $order->get_id());
		
		/* Meta dla nowego zamówienia */
		update_field('field_5fb939e2da29d','Oczekiwanie na odpowiedzi redakcji.', $empty_response_order->id);
		
		$debug['redactions_participating'] = $redactions_participating;
		$debug['array_unique'] = $rp;
		
		$debug['order_id'] = $empty_response_order->id;
		$debug['response'] = $empty_response_order;
		$path = $_SERVER["DOCUMENT_ROOT"]."/RESPOND.json";
		$myfile = fopen($path, "w") or die("Unable to open file!");
		$string = print_r($debug, true);
		fwrite($myfile, $string);
		
		fclose($myfile);
		
		return $empty_response_order->id;;
	
}

/**************************************************************************************************/
/**************************************************************************************************/
/***************************** PRZESYŁANIE ZAMÓWIENIA DO GAZET ************************************/
/**************************************************************************************************/
/**************************************************************************************************/


								  /* PRZY ZMIANIE NA ON HOLD */
								 /* CZYLI ODRAZU PO ZAMÓWIENIU */
								/* ZMIENIĆ NA RĘCZNE WYSYŁANIE */
								
								
								
function send_order_data_when_status_changes_to_on_hold($order_id) {
	
	/* SPRAWDZAMY W ZAMÓWIENIU JAKIE PRODUKTY SĄ ZAKUPIONE */
	$order = wc_get_order( $order_id );
	$response_id = create_empty_response_order($order);
/*	
	$red_ids = [0051,0052];
	
	foreach ($red_ids as $id) {
		$red_keys["R00".$id] = [
			"ck"=>"ck_9d257932c663ac95fc7e91e1e66310eb824f9e8c",
			"cs"=>"cs_b685836f54a98b152372cda99e8e2498ccba6f46",
			"url"=>"https://panel".$id.".wokulski.online"
		];
	}
*/

	$red_keys = [
		"R000051" => [
			"ck"=>"ck_9d257932c663ac95fc7e91e1e66310eb824f9e8c",
			"cs"=>"cs_b685836f54a98b152372cda99e8e2498ccba6f46",
			"url"=>"https://gazeta1.wokulski.online"
		],
		"R000052" => [
			"ck"=>"ck_9d257932c663ac95fc7e91e1e66310eb824f9e8c",
			"cs"=>"cs_b685836f54a98b152372cda99e8e2498ccba6f46",
			"url"=>"https://gazeta2.wokulski.online"
		]
	];


	foreach ( $order->get_items() as $item_id => $item ) {
	   /* SPRAWDZAMY NUMER REDAKCJI PRZYPISANY DO PRODUKTU */
	   $red_id = get_post_meta($item->get_product_id(), 'numer_redakcji', true);
	   
	    $variation_id = get_post_meta($item->get_variation_id(), 'variation_mapping', true);  

	    $orders[$red_id][] = 
	   
			[
				'debug_name' => $item->get_name(),
				/* ID REDAKCYJNE */
				'product_id' => get_post_meta($item->get_product_id(), 'product_mapping', true),
				/* ID REDAKCYJNE */
				'variation_id' => $variation_id,
				'quantity' => $item->get_quantity(),

				'meta_data' => [
					["key"=>"Data Publikacji","value"=>$item->get_meta( 'Data publikacji', true )],
					["key"=>"Obraz","value"=>$item->get_meta( 'Obraz', true )],
					["key"=>"Nazwa","value"=>get_post_meta($item->get_product_id(),'nazwa',true)]
				]
				
			];
		   
	}
	
	foreach ($orders as $nr => $orders) {
		
		$woocommerce = new Client(
			$red_keys[$nr]['url'],
			$red_keys[$nr]['ck'],
			$red_keys[$nr]['cs'],
			[
				'wp_api' => true,
				'version' => 'wc/v3'
			]
		);
		
		$data = [	 
					'set_paid' => true,
					'billing' => [
						'first_name' => 'Vokulsky Group'
					],
					'line_items' => $orders,
					'meta_data' => [
						[
							'key' => 'response_order_id',
							'value' => $response_id
						]
					]
				];
						
		try {
			$order_send = $woocommerce->post('orders', $data);
		} catch (Exception $e) {
			$debug['SEND_ORDER_ERROR'] = $e->getMessage();
		}
		
		$debug['DATA'][$nr][] = $data;
		
	}

		/* JEŻELI UDAŁO SIĘ WYSŁAĆ WSZYSTKO, DAJ MAILA DO KLIENTA */
		/* JEŚLI COŚ SIĘ NIE UDAŁO, DAJ MAILA DO ADMINA */
		
		
			$msg = '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional //EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd"><!--[if IE]><html xmlns="http://www.w3.org/1999/xhtml" class="ie"><![endif]--><!--[if !IE]><!--><html style="margin: 0;padding: 0;" xmlns="http://www.w3.org/1999/xhtml"><!--<![endif]--><head> <meta http-equiv="Content-Type" content="text/html; charset=utf-8" /> <title></title> <!--[if !mso]><!--><meta http-equiv="X-UA-Compatible" content="IE=edge" /><!--<![endif]--> <meta name="viewport" content="width=device-width" /><style type="text/css"> @media only screen and (min-width: 620px){.wrapper{min-width:600px !important}.wrapper h1{}.wrapper h1{font-size:22px !important;line-height:31px !important}.wrapper h2{}.wrapper h2{font-size:20px !important;line-height:28px !important}.wrapper h3{}.wrapper h3{font-size:18px !important;line-height:26px !important}.column{}.wrapper .size-8{font-size:8px !important;line-height:14px !important}.wrapper .size-9{font-size:9px !important;line-height:16px !important}.wrapper .size-10{font-size:10px !important;line-height:18px !important}.wrapper .size-11{font-size:11px !important;line-height:19px !important}.wrapper .size-12{font-size:12px !important;line-height:19px !important}.wrapper .size-13{font-size:13px !important;line-height:21px !important}.wrapper .size-14{font-size:14px !important;line-height:21px !important}.wrapper .size-15{font-size:15px !important;line-height:23px !important}.wrapper .size-16{font-size:16px !important;line-height:24px !important}.wrapper .size-17{font-size:17px !important;line-height:26px !important}.wrapper .size-18{font-size:18px !important;line-height:26px !important}.wrapper .size-20{font-size:20px !important;line-height:28px !important}.wrapper .size-22{font-size:22px !important;line-height:31px !important}.wrapper .size-24{font-size:24px !important;line-height:32px !important}.wrapper .size-26{font-size:26px !important;line-height:34px !important}.wrapper .size-28{font-size:28px !important;line-height:36px !important}.wrapper .size-30{font-size:30px !important;line-height:38px !important}.wrapper .size-32{font-size:32px !important;line-height:40px !important}.wrapper .size-34{font-size:34px !important;line-height:43px !important}.wrapper .size-36{font-size:36px !important;line-height:43px !important}.wrapper .size-40{font-size:40px !important;line-height:47px !important}.wrapper .size-44{font-size:44px !important;line-height:50px !important}.wrapper .size-48{font-size:48px !important;line-height:54px !important}.wrapper .size-56{font-size:56px !important;line-height:60px !important}.wrapper .size-64{font-size:64px !important;line-height:63px !important}} </style> <meta name="x-apple-disable-message-reformatting" /> <style type="text/css"> body { margin: 0; padding: 0; } table { border-collapse: collapse; table-layout: fixed; } * { line-height: inherit; } [x-apple-data-detectors] { color: inherit !important; text-decoration: none !important; } .wrapper .footer__share-button a:hover, .wrapper .footer__share-button a:focus { color: #ffffff !important; } .btn a:hover, .btn a:focus, .footer__share-button a:hover, .footer__share-button a:focus, .email-footer__links a:hover, .email-footer__links a:focus { opacity: 0.8; } .preheader, .header, .layout, .column { transition: width 0.25s ease-in-out, max-width 0.25s ease-in-out; } .preheader td { padding-bottom: 8px; } .layout, div.header { max-width: 400px !important; -fallback-width: 95% !important; width: calc(100% - 20px) !important; } div.preheader { max-width: 360px !important; -fallback-width: 90% !important; width: calc(100% - 60px) !important; } .snippet, .webversion { Float: none !important; } .stack .column { max-width: 400px !important; width: 100% !important; } .fixed-width.has-border { max-width: 402px !important; } .fixed-width.has-border .layout__inner { box-sizing: border-box; } .snippet, .webversion { width: 50% !important; } .ie .btn { width: 100%; } .ie .stack .column, .ie .stack .gutter { display: table-cell; float: none !important; } .ie div.preheader, .ie .email-footer { max-width: 560px !important; width: 560px !important; } .ie .snippet, .ie .webversion { width: 280px !important; } .ie div.header, .ie .layout { max-width: 600px !important; width: 600px !important; } .ie .two-col .column { max-width: 300px !important; width: 300px !important; } .ie .three-col .column, .ie .narrow { max-width: 200px !important; width: 200px !important; } .ie .wide { width: 400px !important; } .ie .stack.fixed-width.has-border, .ie .stack.has-gutter.has-border { max-width: 602px !important; width: 602px !important; } .ie .stack.two-col.has-gutter .column { max-width: 290px !important; width: 290px !important; } .ie .stack.three-col.has-gutter .column, .ie .stack.has-gutter .narrow { max-width: 188px !important; width: 188px !important; } .ie .stack.has-gutter .wide { max-width: 394px !important; width: 394px !important; } .ie .stack.two-col.has-gutter.has-border .column { max-width: 292px !important; width: 292px !important; } .ie .stack.three-col.has-gutter.has-border .column, .ie .stack.has-gutter.has-border .narrow { max-width: 190px !important; width: 190px !important; } .ie .stack.has-gutter.has-border .wide { max-width: 396px !important; width: 396px !important; } .ie .fixed-width .layout__inner { border-left: 0 none white !important; border-right: 0 none white !important; } .ie .layout__edges { display: none; } .mso .layout__edges { font-size: 0; } .layout-fixed-width, .mso .layout-full-width { background-color: #ffffff; } @media only screen and (min-width: 620px) { .column, .gutter { display: table-cell; Float: none !important; vertical-align: top; } div.preheader, .email-footer { max-width: 560px !important; width: 560px !important; } .snippet, .webversion { width: 280px !important; } div.header, .layout, .one-col .column { max-width: 600px !important; width: 600px !important; } .fixed-width.has-border, .fixed-width.x_has-border, .has-gutter.has-border, .has-gutter.x_has-border { max-width: 602px !important; width: 602px !important; } .two-col .column { max-width: 300px !important; width: 300px !important; } .three-col .column, .column.narrow, .column.x_narrow { max-width: 200px !important; width: 200px !important; } .column.wide, .column.x_wide { width: 400px !important; } .two-col.has-gutter .column, .two-col.x_has-gutter .column { max-width: 290px !important; width: 290px !important; } .three-col.has-gutter .column, .three-col.x_has-gutter .column, .has-gutter .narrow { max-width: 188px !important; width: 188px !important; } .has-gutter .wide { max-width: 394px !important; width: 394px !important; } .two-col.has-gutter.has-border .column, .two-col.x_has-gutter.x_has-border .column { max-width: 292px !important; width: 292px !important; } .three-col.has-gutter.has-border .column, .three-col.x_has-gutter.x_has-border .column, .has-gutter.has-border .narrow, .has-gutter.x_has-border .narrow { max-width: 190px !important; width: 190px !important; } .has-gutter.has-border .wide, .has-gutter.x_has-border .wide { max-width: 396px !important; width: 396px !important; } } @supports (display: flex) { @media only screen and (min-width: 620px) { .fixed-width.has-border .layout__inner { display: flex !important; } } } @media only screen and (-webkit-min-device-pixel-ratio: 2), only screen and (min--moz-device-pixel-ratio: 2), only screen and (-o-min-device-pixel-ratio: 2/1), only screen and (min-device-pixel-ratio: 2), only screen and (min-resolution: 192dpi), only screen and (min-resolution: 2dppx) { .fblike { background-image: url(https://i7.createsend1.com/static/eb/master/13-the-blueprint-3/images/fblike@2x.png) !important; } .tweet { background-image: url(https://i8.createsend1.com/static/eb/master/13-the-blueprint-3/images/tweet@2x.png) !important; } .linkedinshare { background-image: url(https://i9.createsend1.com/static/eb/master/13-the-blueprint-3/images/lishare@2x.png) !important; } .forwardtoafriend { background-image: url(https://i10.createsend1.com/static/eb/master/13-the-blueprint-3/images/forward@2x.png) !important; } } @media (max-width: 321px) { .fixed-width.has-border .layout__inner { border-width: 1px 0 !important; } .layout, .stack .column { min-width: 320px !important; width: 320px !important; } .border { display: none; } .has-gutter .border { display: table-cell; } } .mso div { border: 0 none white !important; } .mso .w560 .divider { Margin-left: 260px !important; Margin-right: 260px !important; } .mso .w360 .divider { Margin-left: 160px !important; Margin-right: 160px !important; } .mso .w260 .divider { Margin-left: 110px !important; Margin-right: 110px !important; } .mso .w160 .divider { Margin-left: 60px !important; Margin-right: 60px !important; } .mso .w354 .divider { Margin-left: 157px !important; Margin-right: 157px !important; } .mso .w250 .divider { Margin-left: 105px !important; Margin-right: 105px !important; } .mso .w148 .divider { Margin-left: 54px !important; Margin-right: 54px !important; } .mso .size-8, .ie .size-8 { font-size: 8px !important; line-height: 14px !important; } .mso .size-9, .ie .size-9 { font-size: 9px !important; line-height: 16px !important; } .mso .size-10, .ie .size-10 { font-size: 10px !important; line-height: 18px !important; } .mso .size-11, .ie .size-11 { font-size: 11px !important; line-height: 19px !important; } .mso .size-12, .ie .size-12 { font-size: 12px !important; line-height: 19px !important; } .mso .size-13, .ie .size-13 { font-size: 13px !important; line-height: 21px !important; } .mso .size-14, .ie .size-14 { font-size: 14px !important; line-height: 21px !important; } .mso .size-15, .ie .size-15 { font-size: 15px !important; line-height: 23px !important; } .mso .size-16, .ie .size-16 { font-size: 16px !important; line-height: 24px !important; } .mso .size-17, .ie .size-17 { font-size: 17px !important; line-height: 26px !important; } .mso .size-18, .ie .size-18 { font-size: 18px !important; line-height: 26px !important; } .mso .size-20, .ie .size-20 { font-size: 20px !important; line-height: 28px !important; } .mso .size-22, .ie .size-22 { font-size: 22px !important; line-height: 31px !important; } .mso .size-24, .ie .size-24 { font-size: 24px !important; line-height: 32px !important; } .mso .size-26, .ie .size-26 { font-size: 26px !important; line-height: 34px !important; } .mso .size-28, .ie .size-28 { font-size: 28px !important; line-height: 36px !important; } .mso .size-30, .ie .size-30 { font-size: 30px !important; line-height: 38px !important; } .mso .size-32, .ie .size-32 { font-size: 32px !important; line-height: 40px !important; } .mso .size-34, .ie .size-34 { font-size: 34px !important; line-height: 43px !important; } .mso .size-36, .ie .size-36 { font-size: 36px !important; line-height: 43px !important; } .mso .size-40, .ie .size-40 { font-size: 40px !important; line-height: 47px !important; } .mso .size-44, .ie .size-44 { font-size: 44px !important; line-height: 50px !important; } .mso .size-48, .ie .size-48 { font-size: 48px !important; line-height: 54px !important; } .mso .size-56, .ie .size-56 { font-size: 56px !important; line-height: 60px !important; } .mso .size-64, .ie .size-64 { font-size: 64px !important; line-height: 63px !important; } </style>  <!--[if !mso]><!--><style type="text/css"> @import url(https://fonts.googleapis.com/css?family=PT+Serif:400,700,400italic,700italic); </style><link href="https://fonts.googleapis.com/css?family=PT+Serif:400,700,400italic,700italic" rel="stylesheet" type="text/css" /><!--<![endif]--><style type="text/css"> body{background-color:#fff}.logo a:hover,.logo a:focus{color:#1e2e3b !important}.mso .layout-has-border{border-top:1px solid #ccc;border-bottom:1px solid #ccc}.mso .layout-has-bottom-border{border-bottom:1px solid #ccc}.mso .border,.ie .border{background-color:#ccc}.mso h1,.ie h1{}.mso h1,.ie h1{font-size:22px !important;line-height:31px !important}.mso h2,.ie h2{}.mso h2,.ie h2{font-size:20px !important;line-height:28px !important}.mso h3,.ie h3{}.mso h3,.ie h3{font-size:18px !important;line-height:26px !important}.mso .layout__inner,.ie .layout__inner{}.mso .footer__share-button p{}.mso .footer__share-button p{font-family:Avenir,sans-serif} </style><meta name="robots" content="noindex,nofollow" /> <meta property="og:title" content="My First Campaign" /> </head> <!--[if mso]> <body class="mso"> <![endif]--> <!--[if !mso]><!--> <body class="no-padding" style="margin: 0;padding: 0;-webkit-text-size-adjust: 100%;"> <!--<![endif]--> <table class="wrapper" style="border-collapse: collapse;table-layout: fixed;min-width: 320px;width: 100%;background-color: #fff;" cellpadding="0" cellspacing="0" role="presentation"><tbody><tr><td> <div role="banner"> <div class="preheader" style="Margin: 0 auto;max-width: 560px;min-width: 280px; width: 280px;width: calc(28000% - 167440px);"> <div style="border-collapse: collapse;display: table;width: 100%;"> <!--[if (mso)|(IE)]><table align="center" class="preheader" cellpadding="0" cellspacing="0" role="presentation"><tr><td style="width: 280px" valign="top"><![endif]--> <div class="snippet" style="display: table-cell;Float: left;font-size: 12px;line-height: 19px;max-width: 280px;min-width: 140px; width: 140px;width: calc(14000% - 78120px);padding: 10px 0 5px 0;color: #94a4b0;font-family: Avenir,sans-serif;">  </div> <!--[if (mso)|(IE)]></td><td style="width: 280px" valign="top"><![endif]--> <div class="webversion" style="display: table-cell;Float: left;font-size: 12px;line-height: 19px;max-width: 280px;min-width: 139px; width: 139px;width: calc(14100% - 78680px);padding: 10px 0 5px 0;text-align: right;color: #94a4b0;font-family: Avenir,sans-serif;">  </div> <!--[if (mso)|(IE)]></td></tr></table><![endif]--> </div> </div>  </div> <div> <div style="background-color: #09063e;"> <div class="layout one-col stack" style="Margin: 0 auto;max-width: 600px;min-width: 320px; width: 320px;width: calc(28000% - 167400px);overflow-wrap: break-word;word-wrap: break-word;word-break: break-word;"> <div class="layout__inner" style="border-collapse: collapse;display: table;width: 100%;"> <!--[if (mso)|(IE)]><table width="100%" cellpadding="0" cellspacing="0" role="presentation"><tr class="layout-full-width" style="background-color: #09063e;"><td class="layout__edges">&nbsp;</td><td style="width: 600px" class="w560"><![endif]--> <div class="column" style="text-align: left;color: #94a4b0;font-size: 14px;line-height: 21px;font-family: Avenir,sans-serif;">  <div style="Margin-left: 20px;Margin-right: 20px;"> <div style="mso-line-height-rule: exactly;line-height: 30px;font-size: 1px;">&nbsp;</div> </div>  <div style="font-size: 12px;font-style: normal;font-weight: normal;line-height: 19px;" align="center"> <a style="text-decoration: underline;transition: opacity 0.1s ease-in;color: #14215b;" href="https://gazeta.wokulski.online"><img style="border: 0;display: block;height: auto;width: 100%;max-width: 243px;" alt="Kingston logo" width="243" src="https://gazeta.wokulski.online/wp-content/uploads/2020/12/email_logo-9903cf0514028a3c.png" /></a> </div>  <div style="Margin-left: 20px;Margin-right: 20px;Margin-top: 20px;"> <div style="mso-line-height-rule: exactly;line-height: 12px;font-size: 1px;">&nbsp;</div> </div>  </div> <!--[if (mso)|(IE)]></td><td class="layout__edges">&nbsp;</td></tr></table><![endif]--> </div> </div> </div>  <div style="background-color: #ffffff;"> <div class="layout one-col stack" style="Margin: 0 auto;max-width: 600px;min-width: 320px; width: 320px;width: calc(28000% - 167400px);overflow-wrap: break-word;word-wrap: break-word;word-break: break-word;"> <div class="layout__inner" style="border-collapse: collapse;display: table;width: 100%;"> <!--[if (mso)|(IE)]><table width="100%" cellpadding="0" cellspacing="0" role="presentation"><tr class="layout-full-width" style="background-color: #ffffff;"><td class="layout__edges">&nbsp;</td><td style="width: 600px" class="w560"><![endif]--> <div class="column" style="text-align: left;color: #94a4b0;font-size: 14px;line-height: 21px;font-family: Avenir,sans-serif;">  <div style="Margin-left: 20px;Margin-right: 20px;"> <div style="mso-line-height-rule: exactly;line-height: 40px;font-size: 1px;">&nbsp;</div> </div>  <div style="Margin-left: 20px;Margin-right: 20px;"> <div style="mso-line-height-rule: exactly;mso-text-raise: 11px;vertical-align: middle;"> <h1 class="size-34" style="Margin-top: 0;Margin-bottom: 20px;font-style: normal;font-weight: normal;color: #fff;font-size: 30px;line-height: 38px;font-family: calibri,carlito,pt sans,trebuchet ms,sans-serif;text-align: center;" lang="x-size-34"><span class="font-calibri"><span style="color:#000000"><font color="#14215b"><strong>Twoja propozycja kampanii reklamowej zosta&#322;a wys&#322;ana do redakcji.</strong></font></span></span></h1> </div> </div>  <div style="Margin-left: 20px;Margin-right: 20px;"> <div style="mso-line-height-rule: exactly;mso-text-raise: 11px;vertical-align: middle;"> <p class="size-16" style="Margin-top: 0;Margin-bottom: 0;font-family: avenir,sans-serif;font-size: 16px;line-height: 24px;text-align: center;" lang="x-size-16"><span class="font-avenir"><span style="color:#000000">Poinformujemy Ci&#281; o ofercie zwrotnej, gdy tylko b&#281;dzie gotowa. </span></span></p> </div> </div>  <div style="Margin-left: 20px;Margin-right: 20px;Margin-top: 20px;"> <div style="mso-line-height-rule: exactly;mso-text-raise: 11px;vertical-align: middle;"> <p class="size-14" style="Margin-top: 0;Margin-bottom: 0;font-size: 14px;line-height: 21px;" lang="x-size-14"> <span style="color:#808080;"> Twoje zamówienia możesz znaleźć w zakładce "Moje zamówienia",<br>po zalogowaniu się na <a href="https://gazeta.wokulski.online">https://gazeta.wokulski.online</a> </span> </p> </div> </div>  </div> <!--[if (mso)|(IE)]></td><td class="layout__edges">&nbsp;</td></tr></table><![endif]--> </div> </div> </div>  <div class="layout one-col fixed-width stack" style="Margin: 0 auto;max-width: 600px;min-width: 320px; width: 320px;width: calc(28000% - 167400px);overflow-wrap: break-word;word-wrap: break-word;word-break: break-word;"> <div class="layout__inner" style="border-collapse: collapse;display: table;width: 100%;background-color: #ffffff;"> <!--[if (mso)|(IE)]><table align="center" cellpadding="0" cellspacing="0" role="presentation"><tr class="layout-fixed-width" style="background-color: #ffffff;"><td style="width: 600px" class="w560"><![endif]--> <div class="column" style="text-align: left;color: #94a4b0;font-size: 14px;line-height: 21px;font-family: Avenir,sans-serif;">  <div style="Margin-left: 20px;Margin-right: 20px;"> <div style="mso-line-height-rule: exactly;line-height: 40px;font-size: 1px;">&nbsp;</div> </div>  </div> <!--[if (mso)|(IE)]></td></tr></table><![endif]--> </div> </div>  <div class="layout two-col has-gutter stack" style="Margin: 0 auto;max-width: 600px;min-width: 320px; width: 320px;width: calc(28000% - 167400px);overflow-wrap: break-word;word-wrap: break-word;word-break: break-word;"> <div class="layout__inner" style="border-collapse: collapse;display: table;width: 100%;"> <!--[if (mso)|(IE)]><table align="center" cellpadding="0" cellspacing="0" role="presentation"><tr><td style="width: 290px" valign="top" class="w250"><![endif]--> <div class="column" style="max-width: 320px;min-width: 290px; width: 320px;width: calc(18290px - 3000%);Float: left;"> <table class="column__background" style="border-collapse: collapse;table-layout: fixed;background-color: #ffffff;" cellpadding="0" cellspacing="0" width="100%" role="presentation"> <tbody><tr> <td style="text-align: left;color: #94a4b0;font-size: 14px;line-height: 21px;font-family: Avenir,sans-serif;">  <div style="Margin-left: 20px;Margin-right: 20px;"> <div style="line-height:20px;font-size:1px">&nbsp;</div> </div>  <div style="Margin-left: 20px;Margin-right: 20px;"> <div style="font-size: 12px;font-style: normal;font-weight: normal;line-height: 19px;Margin-bottom: 20px;" align="left"> <img style="border: 0;display: block;height: auto;width: 100%;max-width: 34px;" alt="1" width="34" src="https://gazeta.wokulski.online/wp-content/uploads/2020/12/number-1-99051403cf3cf03c.png" /> </div> </div>  <div style="Margin-left: 20px;Margin-right: 20px;"> <div style="mso-line-height-rule: exactly;mso-text-raise: 11px;vertical-align: middle;"> <h2 style="Margin-top: 0;Margin-bottom: 16px;font-style: normal;font-weight: normal;color: #fff;font-size: 17px;line-height: 26px;font-family: PT Serif,Georgia,serif;"><span style="color:#000000">Twoja Propozycja</span></h2> </div> </div>  <div style="font-size: 12px;font-style: normal;font-weight: normal;line-height: 19px;" align="center"> <a style="text-decoration: underline;transition: opacity 0.1s ease-in;color: #14215b;" href="http://www.example.com"><img style="border: 0;display: block;height: auto;width: 100%;max-width: 480px;" alt="Student moving in" width="290" src="https://gazeta.wokulski.online/wp-content/uploads/2020/12/AdobeStock_303279682-1024x683-9900000b6d028a3c.jpeg" /></a> </div>  <div style="Margin-left: 20px;Margin-right: 20px;Margin-top: 20px;"> <div style="mso-line-height-rule: exactly;mso-text-raise: 11px;vertical-align: middle;"> <p class="size-14" style="Margin-top: 0;Margin-bottom: 0;font-size: 14px;line-height: 21px;" lang="x-size-14"><span style="color:#000000">Twoj&#261; propozycj&#281; kampanii reklamowej mo&#380;esz podejrze&#263; tutaj:</span></p><p class="size-14" style="Margin-top: 20px;Margin-bottom: 20px;font-size: 14px;line-height: 21px;" lang="x-size-14"><span style="color:#000000"><a target="_blank" href="https://gazeta.wokulski.online/konto/view-order/'.$order_id.'/">Zapytanie ofertowe</a></span></p> </div> </div>  <div style="Margin-left: 20px;Margin-right: 20px;"> <div style="line-height:20px;font-size:1px">&nbsp;</div> </div>  </td> </tr> </tbody></table> </div> <!--[if (mso)|(IE)]></td><td style="width: 20px"><![endif]--><div class="gutter" style="Float: left;width: 20px;">&nbsp;</div><!--[if (mso)|(IE)]></td><td style="width: 290px" valign="top" class="w250"><![endif]--> <div class="column" style="max-width: 320px;min-width: 290px; width: 320px;width: calc(18290px - 3000%);Float: left;"> <table class="column__background" style="border-collapse: collapse;table-layout: fixed;background-color: #ffffff;" cellpadding="0" cellspacing="0" width="100%" role="presentation"> <tbody><tr> <td style="text-align: left;color: #94a4b0;font-size: 14px;line-height: 21px;font-family: Avenir,sans-serif;">  <div style="Margin-left: 20px;Margin-right: 20px;"> <div style="line-height:20px;font-size:1px">&nbsp;</div> </div>  <div style="Margin-left: 20px;Margin-right: 20px;"> <div style="font-size: 12px;font-style: normal;font-weight: normal;line-height: 19px;Margin-bottom: 20px;" align="left"> <img style="border: 0;display: block;height: auto;width: 100%;max-width: 34px;" alt="2" width="34" src="https://gazeta.wokulski.online/wp-content/uploads/2020/12/number-2-99051403cf3cf03c.png" /> </div> </div>  <div style="Margin-left: 20px;Margin-right: 20px;"> <div style="mso-line-height-rule: exactly;mso-text-raise: 11px;vertical-align: middle;"> <h2 style="Margin-top: 0;Margin-bottom: 16px;font-style: normal;font-weight: normal;color: #fff;font-size: 17px;line-height: 26px;font-family: PT Serif,Georgia,serif;"><span style="color:#000000">Propozycja redakcji</span></h2> </div> </div>  <div style="font-size: 12px;font-style: normal;font-weight: normal;line-height: 19px;" align="center"> <img style="border: 0;display: block;height: auto;width: 100%;max-width: 480px;" alt="Student talking with parent" width="290" src="https://gazeta.wokulski.online/wp-content/uploads/2020/12/AdobeStock_283325313_Editorial_Use_Only-2048x1369-9900000b6d028a3c.jpeg" /> </div>  <div style="Margin-left: 20px;Margin-right: 20px;Margin-top: 20px;"> <div style="mso-line-height-rule: exactly;mso-text-raise: 11px;vertical-align: middle;"> <p class="size-14" style="Margin-top: 0;Margin-bottom: 0;font-size: 14px;line-height: 21px;" lang="x-size-14"><span style="color:#000000">Propozycja redakcji pojawi si&#281; pod tym linkiem do 72h. </span></p><p class="size-14" style="Margin-top: 20px;Margin-bottom: 0;font-size: 14px;line-height: 21px;" lang="x-size-14"><span style="color:#000000"><a target="_blank" href="https://gazeta.wokulski.online/konto/view-order/'.$response_id.'/">Oferta redakcji</a></span></p><p class="size-14" style="Margin-top: 20px;Margin-bottom: 20px;font-size: 14px;line-height: 21px;" lang="x-size-14"><span style="color:#000000">Poinformujemy Ci&#281; o tym, kolejn&#261; wiadomo&#347;ci&#261;&nbsp;e-mail.</span></p> </div> </div>  <div style="Margin-left: 20px;Margin-right: 20px;"> <div style="line-height:20px;font-size:1px">&nbsp;</div> </div>  </td> </tr> </tbody></table> </div> <!--[if (mso)|(IE)]></td></tr></table><![endif]--> </div> </div>  <div class="layout one-col fixed-width stack" style="Margin: 0 auto;max-width: 600px;min-width: 320px; width: 320px;width: calc(28000% - 167400px);overflow-wrap: break-word;word-wrap: break-word;word-break: break-word;"> <div class="layout__inner" style="border-collapse: collapse;display: table;width: 100%;background-color: #ffffff;"> <!--[if (mso)|(IE)]><table align="center" cellpadding="0" cellspacing="0" role="presentation"><tr class="layout-fixed-width" style="background-color: #ffffff;"><td style="width: 600px" class="w560"><![endif]--> <div class="column" style="text-align: left;color: #94a4b0;font-size: 14px;line-height: 21px;font-family: Avenir,sans-serif;">  <div style="Margin-left: 20px;Margin-right: 20px;"> <div style="mso-line-height-rule: exactly;line-height: 20px;font-size: 1px;">&nbsp;</div> </div>  </div> <!--[if (mso)|(IE)]></td></tr></table><![endif]--> </div> </div>   <div class="layout one-col fixed-width stack" style="Margin: 0 auto;max-width: 600px;min-width: 320px; width: 320px;width: calc(28000% - 167400px);overflow-wrap: break-word;word-wrap: break-word;word-break: break-word;">    <div class="layout__inner" style="border-radius: 25px; border-collapse: collapse;display: table;width: 100%;background-color: #0d1b57;"> <!--[if (mso)|(IE)]><table align="center" cellpadding="0" cellspacing="0" role="presentation"><tr class="layout-fixed-width" style="background-color: #0d1b57;"><td style="width: 600px" class="w560"><![endif]-->      <div class="column" style="text-align: left;color: #94a4b0;font-size: 14px;line-height: 21px;font-family: Avenir,sans-serif;">    <div style="font-size: 12px;font-style: normal;font-weight: normal;line-height: 19px;" align="center"> <img class="gnd-corner-image gnd-corner-image-center gnd-corner-image-top" style="border-radius: 25px 25px 0 0; display: block;height: auto;width: 100%;max-width: 900px;" alt="College student celebrating graduation" width="600" src="https://gazeta.wokulski.online/wp-content/uploads/2020/12/AdobeStock_275546162-scaled-9900000000079e3c.jpeg" /> </div>  <div style="Margin-left: 20px;Margin-right: 20px;Margin-top: 20px;"> <div style="mso-line-height-rule: exactly;line-height: 0px;font-size: 1px;">&nbsp;</div> </div>  <div style="Margin-left: 20px;Margin-right: 20px;"> <div style="mso-line-height-rule: exactly;mso-text-raise: 11px;vertical-align: middle;"> <p class="size-24" style="Margin-top: 0;Margin-bottom: 0px;font-size: 20px;line-height: 28px;text-align: center;" lang="x-size-24"><span style="color:#ffffff">IT Solutions for Press</span></p> </div> </div>  <div style="font-size: 12px;font-style: normal;font-weight: normal;line-height: 19px;" align="center"> <a style="text-decoration: underline;transition: opacity 0.1s ease-in;color: #14215b;" href="https://wokulski.online"><img style="border: 0;display: block;height: auto;width: 100%;max-width: 185px;" alt="Kingston logo" width="185" src="https://gazeta.wokulski.online/wp-content/uploads/2020/12/vg_logo_mini-w-9904510a2801453c.png" /></a> </div>  <div style="Margin-left: 20px;Margin-right: 20px;Margin-top: 20px;"> <div style="mso-line-height-rule: exactly;line-height: 0px;font-size: 1px;">&nbsp;</div> </div>  </div> <!--[if (mso)|(IE)]></td></tr></table><![endif]--> </div> </div>  <div style="mso-line-height-rule: exactly;line-height: 20px;font-size: 20px;">&nbsp;</div>   <div role="contentinfo"> <div class="layout email-footer stack" style="Margin: 0 auto;max-width: 600px;min-width: 320px; width: 320px;width: calc(28000% - 167400px);overflow-wrap: break-word;word-wrap: break-word;word-break: break-word;"> <div class="layout__inner" style="border-collapse: collapse;display: table;width: 100%;"> <!--[if (mso)|(IE)]><table align="center" cellpadding="0" cellspacing="0" role="presentation"><tr class="layout-email-footer"><td style="width: 400px;" valign="top" class="w360"><![endif]--> <div class="column wide" style="text-align: left;font-size: 12px;line-height: 19px;color: #94a4b0;font-family: Avenir,sans-serif;Float: left;max-width: 400px;min-width: 320px; width: 320px;width: calc(8000% - 47600px);"> <div style="Margin-left: 20px;Margin-right: 20px;Margin-top: 10px;Margin-bottom: 10px;">  <div style="font-size: 12px;line-height: 19px;"> <div>Vokulsky Group Sp. z o.o.<br /> Leszczyny 49A,<br /> 25-008 G&#243;rno<br /> KRS 0000817266<br /> NIP 6572949234<br /> REGON 385005507</div> </div> <div style="font-size: 12px;line-height: 19px;Margin-top: 18px;"> <div>Otrzyma&#322;e&#347; tego emaila, poniewa&#380; z&#322;o&#380;y&#322;e&#347; zam&#243;wienie kampanii reklamowej na https://gazeta.wokulski.online</div> </div> <!--[if mso]>&nbsp;<![endif]--> </div> </div> <!--[if (mso)|(IE)]></td><td style="width: 200px;" valign="top" class="w160"><![endif]--> <div class="column narrow" style="text-align: left;font-size: 12px;line-height: 19px;color: #94a4b0;font-family: Avenir,sans-serif;Float: left;max-width: 320px;min-width: 200px; width: 320px;width: calc(72200px - 12000%);"> <div style="Margin-left: 20px;Margin-right: 20px;Margin-top: 10px;Margin-bottom: 10px;">  </div> </div> <!--[if (mso)|(IE)]></td></tr></table><![endif]--> </div> </div>  </div> <div style="line-height:40px;font-size:40px;">&nbsp;</div> </div></td></tr></tbody></table>  </body></html> ';
			$headers .= "Content-Type: text/html; charset=UTF-8\r\n";
			$headers .= "From: <biuro@wokulski.online>" . "\r\n";
			mail($order->get_billing_email(),"Dziękujemy za złożenie zamówienia. Czekamy na odpowiedzi redakcji.", $msg, $headers);
	


		$debug['ORIGINAL_ORDER'] = $order;	
		$debug['TEST_ORDER_SEND'] = $order_send;
	
		$d = new DateTime();
		$debug[] = $d->format('Y-m-d\TH:i:s');
		$path = $_SERVER["DOCUMENT_ROOT"]."/DEBUGi.json";
		$myfile = fopen($path, "w") or die("Unable to open file!");
		$string = print_r($debug, true);
		fwrite($myfile, $string);
		
		fclose($myfile);
	
}


add_action( 'woocommerce_order_status_on-hold', 'send_order_data_when_status_changes_to_on_hold');


/***********************************************************************************/
/***************************** PRODUCT ORDER META DATA *****************************/
/******* TYLKO READ ONLY DLA KLIENTÓW NA ZAMÓWIENIU I DLA NAS NA ZAMÓWIENIU ********/
/***********************************************************************************/

add_action('woocommerce_checkout_create_order_line_item', 'save_meta_data_in_admin_order', 20, 4);
function save_meta_data_in_admin_order($item, $cart_item_key, $values, $order) {
  
	if ( $obraz = $values['wccpf_obraz']['user_val']['url'] ) {
        $item->update_meta_data( 'Obraz', $obraz ); // Save as order item
    };
	
	if ( $nazwa = get_post_meta($item->get_product_id(), 'nazwa', true) ) {
        $item->update_meta_data( 'Nazwa Gazety/Portalu', $nazwa ); // Save as order item
    };
	
	if ( $url = get_post_meta($item->get_product_id(), 'adres_url', true) ) {
        $item->update_meta_data( 'Adres WWW', $url ); // Save as order item
    };
	
	if ( $data_wydania = $values['data_wydania']  ) {
        $item->update_meta_data( 'Data publikacji', $data_wydania ); // Save as order item
    };
	
	if ( $naklad = get_post_meta($item->get_product_id(), 'naklad_odwiedziny', true) ) {
        $item->update_meta_data( 'Nakład/Liczba odwiedzin /miesięcznie', $naklad ); // Save as order item
    };
	
	if ( $region = get_post_meta($item->get_product_id(), 'lokalizacja_medium', true) ) {

		$region_unjson = json_decode(wp_unslash($region),true);
		foreach ($region_unjson as $reg) {
			$region_array[] = str_replace(['Polska','województwo'],'',$reg);
		}
		
		$item->update_meta_data( 'Region', implode("<br>",$region_array) );
    };
	
	/*
	$debug['region'] = implode("<br>",$region_array);
	
	$d = new DateTime();
		$debug[] = $d->format('Y-m-d\TH:i:s');
		$path = $_SERVER["DOCUMENT_ROOT"]."/META.json";
		$myfile = fopen($path, "w") or die("Unable to open file!");
		$string = print_r($debug, true);
		fwrite($myfile, $string);
		
		fclose($myfile);
	*/
	
	
}

/************************************************************************************************/
/************************************************************************************************/
/************************************************************************************************/
/************ PRZY UPDAJCIE ORDERU LICZ NADPLATE / NIEDOPLATE + DODAJ NARZUT ********************/
/************************************************************************************************/
/************************************************************************************************/
/************************************************************************************************/

function action_woocommerce_update_order( $post_id ) { 
	
			$woocommerce_vg = new Client(
				'https://gazeta.wokulski.online',
				'ck_94a52f57e7b19a60c2546dc5125b0d5bd7ce315f',
				'cs_6fcb9eba7dbc0e470feca5ab12fd172fb79dad28',
				[
					'wp_api' => true,
					'version' => 'wc/v3'
				]
			);

			$order = wc_get_order( $post_id );
			
			/* Total z zamówienia klienta razem z marżą - meta */
			$order_total_from_vg_client = $order->get_meta('client_total');

			/* Total z odpowiedzi redakcji bez marży */
			$order_total_new = $order->get_total();
			
			
			/* Sprawdzamy ile jest wszystkich redakcji */
			$all_redactions_participating = json_decode($order->get_meta('redactions_participating',true),true);
			
			/* Sprawdzamy ile z redakcji dało zwrotkę */
			foreach ( $order->get_items() as $item_id => $item ) {
				$redactions_present_in_order[] = $item->get_meta('Numer Redakcji',true);
			}
			
			/* Usuwamy powtórzenia */
			$redactions_responses_to_now = array_unique($redactions_present_in_order);
			

		
			$debug["NOWY_ORDER_TOTAL"] = $order_total_new;
			$debug["IS_THIS_REST_API_CALL"] = $order->get_meta('is_this_rest_api_call');
			$debug["KLIENT_ZAMOWIL_NA_TAKA_KWOTE"] = $order_total_from_vg_client;
			$debug['WSZYSTKIE_REDAKCJE'] = $all_redactions_participating;
			$debug['REDAKCJE_DO_TEJ_PORY'] = $redactions_responses_to_now;
			$debug['WSZYSTKIE_REDAKCJE_LICZBA'] = count($all_redactions_participating);
			$debug['REDAKCJE_DO_TEJ_PORY_LICZBA'] = count($redactions_responses_to_now);
			
			
			
			/* Jeżeli ilość wszystkich redakcji = ilość redakcji z oferty zwrotnej */
			/* Obliczaj total dla zamówienia uwzględniając poprzednią wpłatę */
			$count_all_redactions_participating = count($all_redactions_participating);
			$count_redactions_responses_to_now = count($redactions_responses_to_now);
			
			/* Tylko jeśli ma custom field rest api call yes */
			/* Tylko jeśli wszystkie redakcje dały już odpowiedź */
			if (	
				$order_total_new !== "0.00" &&
				$order->get_meta('is_this_rest_api_call') == 'yes' &&
				$order_total_from_vg_client !== '' &&
				$count_all_redactions_participating == $count_redactions_responses_to_now
			) {
		
				$debug['StATUS'] = "Wszystkie redakcje dały odpowiedź. Można liczyć.";
				
				/* Zamiana na liczbę */
				$float_total = floatval($order_total_new);
			
				/* Pobiera narzut z chwili zamówienia */
				$order_margin = $order->get_meta('order_margin');
				
				/* Narzut w formie dziesiętnej */
				$order_margin_float = floatval($order_margin)/100;
			
				/* Redakcja wysyła w swoich cenach, więc dodajemy narzut z chwili zamówienia */
				$order_total_new_with_margin = $float_total + ($float_total*$order_margin_float);
				
				/* Zerujemy kwotę zamówienia */
				foreach ( $order->get_items() as $item_id => $item ) {
					$line_items[] = [
						'id' => $item_id,
						'total' => '0'
					];
				}
				
				/* Znajdujemy ostatnią pozycję w zamówieniu */
				$last_line = count($line_items)-1;
				
				/* Dopłata - jeżeli nowe zamówienie z marżą jest większe nie poprzednie */
				if (	$order_total_new_with_margin > 	$order_total_from_vg_client	   ) {
					
					$how_much_to_pay = $order_total_new_with_margin - $order_total_from_vg_client;
					$debug["NALEZY_SIE"] = "Doplata ".$how_much_to_pay;
					
					/* Dodajemy pozycję dodatnią, przy ostatnim produkcie */
					$line_items[$last_line]['total'] = strval($how_much_to_pay);
					
					$info_for_client = "Propozycja gotowa. Dopłata do nowej propozycji ".$how_much_to_pay. "zł";
				}
				
				/* Zwrot dla klienta */
				if (	$order_total_new_with_margin < 	$order_total_from_vg_client	   ) {
					
					$how_much_to_pay = $order_total_new_with_margin - $order_total_from_vg_client;
					$debug["NALEZY_SIE"] = "Zwrot dla klienta ".$how_much_to_pay;
					
					$line_items[$last_line]['total'] = strval($how_much_to_pay);
					
					$info_for_client = "Propozycja gotowa. Zwracamy ".$how_much_to_pay. "zł";
				}
				
				/* Brak różnicy, więc ta sama kwota */
				if (	$order_total_new_with_margin == $order_total_from_vg_client	   ) {
					
					$debug["NALEZY_SIE"] = "BRAK DOPŁATY";
					$info_for_client = "Propozycja gotowa.";
					
				}
				
				
				/* Nadaj API CALL NO, aby nie liczyło drugi raz */
				$data = [
					'line_items' => $line_items,
					'meta_data' => [
						[
							"key" => "is_this_rest_api_call",
							"value" => "no"
						]
					]
				];
				
				try {
					$debug["NADAJE_API_CALL_NO"] = $woocommerce_vg->put('orders/'.$post_id, $data);
				} catch (Exception $e) {
					$debug['SEND_ORDER_ERROR'] = $e->getMessage();
				}
					
				update_field('field_5fb939e2da29d',$info_for_client, $post_id);	
					
					
				/* WYSYŁĄMY MAILA DO KLIENTA O PROPOZYCJI GOTOWEJ Z LINKIEM DO PROPOZYCJI */
				$msg = '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional //EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd"><!--[if IE]> <html xmlns="http://www.w3.org/1999/xhtml" class="ie"> <![endif]--><!--[if !IE]><!--> <html style="margin: 0;padding: 0;" xmlns="http://www.w3.org/1999/xhtml"> <!--<![endif]--> <head> <meta http-equiv="Content-Type" content="text/html; charset=utf-8" /> <title></title> <!--[if !mso]><!--> <meta http-equiv="X-UA-Compatible" content="IE=edge" /> <!--<![endif]--> <meta name="viewport" content="width=device-width" /> <style type="text/css"> @media only screen and (min-width: 620px){.wrapper{min-width:600px !important}.wrapper h1{}.wrapper h1{font-size:22px !important;line-height:31px !important}.wrapper h2{}.wrapper h2{font-size:20px !important;line-height:28px !important}.wrapper h3{}.wrapper h3{font-size:18px !important;line-height:26px !important}.column{}.wrapper .size-8{font-size:8px !important;line-height:14px !important}.wrapper .size-9{font-size:9px !important;line-height:16px !important}.wrapper .size-10{font-size:10px !important;line-height:18px !important}.wrapper .size-11{font-size:11px !important;line-height:19px !important}.wrapper .size-12{font-size:12px !important;line-height:19px !important}.wrapper .size-13{font-size:13px !important;line-height:21px !important}.wrapper .size-14{font-size:14px !important;line-height:21px !important}.wrapper .size-15{font-size:15px !important;line-height:23px !important}.wrapper .size-16{font-size:16px !important;line-height:24px !important}.wrapper .size-17{font-size:17px !important;line-height:26px !important}.wrapper .size-18{font-size:18px !important;line-height:26px !important}.wrapper .size-20{font-size:20px !important;line-height:28px !important}.wrapper .size-22{font-size:22px !important;line-height:31px !important}.wrapper .size-24{font-size:24px !important;line-height:32px !important}.wrapper .size-26{font-size:26px !important;line-height:34px !important}.wrapper .size-28{font-size:28px !important;line-height:36px !important}.wrapper .size-30{font-size:30px !important;line-height:38px !important}.wrapper .size-32{font-size:32px !important;line-height:40px !important}.wrapper .size-34{font-size:34px !important;line-height:43px !important}.wrapper .size-36{font-size:36px !important;line-height:43px !important}.wrapper .size-40{font-size:40px !important;line-height:47px !important}.wrapper .size-44{font-size:44px !important;line-height:50px !important}.wrapper .size-48{font-size:48px !important;line-height:54px !important}.wrapper .size-56{font-size:56px !important;line-height:60px !important}.wrapper .size-64{font-size:64px !important;line-height:63px !important}} </style> <meta name="x-apple-disable-message-reformatting" /> <style type="text/css"> body { margin: 0; padding: 0; } table { border-collapse: collapse; table-layout: fixed; } * { line-height: inherit; } [x-apple-data-detectors] { color: inherit !important; text-decoration: none !important; } .wrapper .footer__share-button a:hover, .wrapper .footer__share-button a:focus { color: #ffffff !important; } .btn a:hover, .btn a:focus, .footer__share-button a:hover, .footer__share-button a:focus, .email-footer__links a:hover, .email-footer__links a:focus { opacity: 0.8; } .preheader, .header, .layout, .column { transition: width 0.25s ease-in-out, max-width 0.25s ease-in-out; } .preheader td { padding-bottom: 8px; } .layout, div.header { max-width: 400px !important; -fallback-width: 95% !important; width: calc(100% - 20px) !important; } div.preheader { max-width: 360px !important; -fallback-width: 90% !important; width: calc(100% - 60px) !important; } .snippet, .webversion { Float: none !important; } .stack .column { max-width: 400px !important; width: 100% !important; } .fixed-width.has-border { max-width: 402px !important; } .fixed-width.has-border .layout__inner { box-sizing: border-box; } .snippet, .webversion { width: 50% !important; } .ie .btn { width: 100%; } .ie .stack .column, .ie .stack .gutter { display: table-cell; float: none !important; } .ie div.preheader, .ie .email-footer { max-width: 560px !important; width: 560px !important; } .ie .snippet, .ie .webversion { width: 280px !important; } .ie div.header, .ie .layout { max-width: 600px !important; width: 600px !important; } .ie .two-col .column { max-width: 300px !important; width: 300px !important; } .ie .three-col .column, .ie .narrow { max-width: 200px !important; width: 200px !important; } .ie .wide { width: 400px !important; } .ie .stack.fixed-width.has-border, .ie .stack.has-gutter.has-border { max-width: 602px !important; width: 602px !important; } .ie .stack.two-col.has-gutter .column { max-width: 290px !important; width: 290px !important; } .ie .stack.three-col.has-gutter .column, .ie .stack.has-gutter .narrow { max-width: 188px !important; width: 188px !important; } .ie .stack.has-gutter .wide { max-width: 394px !important; width: 394px !important; } .ie .stack.two-col.has-gutter.has-border .column { max-width: 292px !important; width: 292px !important; } .ie .stack.three-col.has-gutter.has-border .column, .ie .stack.has-gutter.has-border .narrow { max-width: 190px !important; width: 190px !important; } .ie .stack.has-gutter.has-border .wide { max-width: 396px !important; width: 396px !important; } .ie .fixed-width .layout__inner { border-left: 0 none white !important; border-right: 0 none white !important; } .ie .layout__edges { display: none; } .mso .layout__edges { font-size: 0; } .layout-fixed-width, .mso .layout-full-width { background-color: #ffffff; } @media only screen and (min-width: 620px) { .column, .gutter { display: table-cell; Float: none !important; vertical-align: top; } div.preheader, .email-footer { max-width: 560px !important; width: 560px !important; } .snippet, .webversion { width: 280px !important; } div.header, .layout, .one-col .column { max-width: 600px !important; width: 600px !important; } .fixed-width.has-border, .fixed-width.x_has-border, .has-gutter.has-border, .has-gutter.x_has-border { max-width: 602px !important; width: 602px !important; } .two-col .column { max-width: 300px !important; width: 300px !important; } .three-col .column, .column.narrow, .column.x_narrow { max-width: 200px !important; width: 200px !important; } .column.wide, .column.x_wide { width: 400px !important; } .two-col.has-gutter .column, .two-col.x_has-gutter .column { max-width: 290px !important; width: 290px !important; } .three-col.has-gutter .column, .three-col.x_has-gutter .column, .has-gutter .narrow { max-width: 188px !important; width: 188px !important; } .has-gutter .wide { max-width: 394px !important; width: 394px !important; } .two-col.has-gutter.has-border .column, .two-col.x_has-gutter.x_has-border .column { max-width: 292px !important; width: 292px !important; } .three-col.has-gutter.has-border .column, .three-col.x_has-gutter.x_has-border .column, .has-gutter.has-border .narrow, .has-gutter.x_has-border .narrow { max-width: 190px !important; width: 190px !important; } .has-gutter.has-border .wide, .has-gutter.x_has-border .wide { max-width: 396px !important; width: 396px !important; } } @supports (display: flex) { @media only screen and (min-width: 620px) { .fixed-width.has-border .layout__inner { display: flex !important; } } } @media only screen and (-webkit-min-device-pixel-ratio: 2), only screen and (min--moz-device-pixel-ratio: 2), only screen and (-o-min-device-pixel-ratio: 2/1), only screen and (min-device-pixel-ratio: 2), only screen and (min-resolution: 192dpi), only screen and (min-resolution: 2dppx) { .fblike { background-image: url(https://i7.createsend1.com/static/eb/master/13-the-blueprint-3/images/fblike@2x.png) !important; } .tweet { background-image: url(https://i8.createsend1.com/static/eb/master/13-the-blueprint-3/images/tweet@2x.png) !important; } .linkedinshare { background-image: url(https://i9.createsend1.com/static/eb/master/13-the-blueprint-3/images/lishare@2x.png) !important; } .forwardtoafriend { background-image: url(https://i10.createsend1.com/static/eb/master/13-the-blueprint-3/images/forward@2x.png) !important; } } @media (max-width: 321px) { .fixed-width.has-border .layout__inner { border-width: 1px 0 !important; } .layout, .stack .column { min-width: 320px !important; width: 320px !important; } .border { display: none; } .has-gutter .border { display: table-cell; } } .mso div { border: 0 none white !important; } .mso .w560 .divider { Margin-left: 260px !important; Margin-right: 260px !important; } .mso .w360 .divider { Margin-left: 160px !important; Margin-right: 160px !important; } .mso .w260 .divider { Margin-left: 110px !important; Margin-right: 110px !important; } .mso .w160 .divider { Margin-left: 60px !important; Margin-right: 60px !important; } .mso .w354 .divider { Margin-left: 157px !important; Margin-right: 157px !important; } .mso .w250 .divider { Margin-left: 105px !important; Margin-right: 105px !important; } .mso .w148 .divider { Margin-left: 54px !important; Margin-right: 54px !important; } .mso .size-8, .ie .size-8 { font-size: 8px !important; line-height: 14px !important; } .mso .size-9, .ie .size-9 { font-size: 9px !important; line-height: 16px !important; } .mso .size-10, .ie .size-10 { font-size: 10px !important; line-height: 18px !important; } .mso .size-11, .ie .size-11 { font-size: 11px !important; line-height: 19px !important; } .mso .size-12, .ie .size-12 { font-size: 12px !important; line-height: 19px !important; } .mso .size-13, .ie .size-13 { font-size: 13px !important; line-height: 21px !important; } .mso .size-14, .ie .size-14 { font-size: 14px !important; line-height: 21px !important; } .mso .size-15, .ie .size-15 { font-size: 15px !important; line-height: 23px !important; } .mso .size-16, .ie .size-16 { font-size: 16px !important; line-height: 24px !important; } .mso .size-17, .ie .size-17 { font-size: 17px !important; line-height: 26px !important; } .mso .size-18, .ie .size-18 { font-size: 18px !important; line-height: 26px !important; } .mso .size-20, .ie .size-20 { font-size: 20px !important; line-height: 28px !important; } .mso .size-22, .ie .size-22 { font-size: 22px !important; line-height: 31px !important; } .mso .size-24, .ie .size-24 { font-size: 24px !important; line-height: 32px !important; } .mso .size-26, .ie .size-26 { font-size: 26px !important; line-height: 34px !important; } .mso .size-28, .ie .size-28 { font-size: 28px !important; line-height: 36px !important; } .mso .size-30, .ie .size-30 { font-size: 30px !important; line-height: 38px !important; } .mso .size-32, .ie .size-32 { font-size: 32px !important; line-height: 40px !important; } .mso .size-34, .ie .size-34 { font-size: 34px !important; line-height: 43px !important; } .mso .size-36, .ie .size-36 { font-size: 36px !important; line-height: 43px !important; } .mso .size-40, .ie .size-40 { font-size: 40px !important; line-height: 47px !important; } .mso .size-44, .ie .size-44 { font-size: 44px !important; line-height: 50px !important; } .mso .size-48, .ie .size-48 { font-size: 48px !important; line-height: 54px !important; } .mso .size-56, .ie .size-56 { font-size: 56px !important; line-height: 60px !important; } .mso .size-64, .ie .size-64 { font-size: 64px !important; line-height: 63px !important; } </style> <!--[if !mso]><!--> <style type="text/css"> @import url(https://fonts.googleapis.com/css?family=PT+Serif:400,700,400italic,700italic); </style> <link href="https://fonts.googleapis.com/css?family=PT+Serif:400,700,400italic,700italic" rel="stylesheet" type="text/css" /> <!--<![endif]--> <style type="text/css"> body{background-color:#fff}.logo a:hover,.logo a:focus{color:#1e2e3b !important}.mso .layout-has-border{border-top:1px solid #ccc;border-bottom:1px solid #ccc}.mso .layout-has-bottom-border{border-bottom:1px solid #ccc}.mso .border,.ie .border{background-color:#ccc}.mso h1,.ie h1{}.mso h1,.ie h1{font-size:22px !important;line-height:31px !important}.mso h2,.ie h2{}.mso h2,.ie h2{font-size:20px !important;line-height:28px !important}.mso h3,.ie h3{}.mso h3,.ie h3{font-size:18px !important;line-height:26px !important}.mso .layout__inner,.ie .layout__inner{}.mso .footer__share-button p{}.mso .footer__share-button p{font-family:Avenir,sans-serif} </style> <meta name="robots" content="noindex,nofollow" /> <meta property="og:title" content="My First Campaign" /> </head> <!--[if mso]> <body class="mso"> <![endif]--> <!--[if !mso]><!--> <body class="no-padding" style="margin: 0;padding: 0;-webkit-text-size-adjust: 100%;"> <!--<![endif]--> <table class="wrapper" style="border-collapse: collapse;table-layout: fixed;min-width: 320px;width: 100%;background-color: #fff;" cellpadding="0" cellspacing="0" role="presentation"> <tbody> <tr> <td> <div role="banner"> <div class="preheader" style="Margin: 0 auto;max-width: 560px;min-width: 280px; width: 280px;width: calc(28000% - 167440px);"> <div style="border-collapse: collapse;display: table;width: 100%;"> <!--[if (mso)|(IE)]> <table align="center" class="preheader" cellpadding="0" cellspacing="0" role="presentation"> <tr> <td style="width: 280px" valign="top"> <![endif]--> <div class="snippet" style="display: table-cell;Float: left;font-size: 12px;line-height: 19px;max-width: 280px;min-width: 140px; width: 140px;width: calc(14000% - 78120px);padding: 10px 0 5px 0;color: #94a4b0;font-family: Avenir,sans-serif;">  </div> <!--[if (mso)|(IE)]> </td> <td style="width: 280px" valign="top"> <![endif]--> <div class="webversion" style="display: table-cell;Float: left;font-size: 12px;line-height: 19px;max-width: 280px;min-width: 139px; width: 139px;width: calc(14100% - 78680px);padding: 10px 0 5px 0;text-align: right;color: #94a4b0;font-family: Avenir,sans-serif;">  </div> <!--[if (mso)|(IE)]> </td> </tr> </table> <![endif]--> </div> </div> </div> <div> <div style="background-color: #09063e;"> <div class="layout one-col stack" style="Margin: 0 auto;max-width: 600px;min-width: 320px; width: 320px;width: calc(28000% - 167400px);overflow-wrap: break-word;word-wrap: break-word;word-break: break-word;"> <div class="layout__inner" style="border-collapse: collapse;display: table;width: 100%;"> <!--[if (mso)|(IE)]> <table width="100%" cellpadding="0" cellspacing="0" role="presentation"> <tr class="layout-full-width" style="background-color: #09063e;"> <td class="layout__edges">&nbsp;</td> <td style="width: 600px" class="w560"> <![endif]--> <div class="column" style="text-align: left;color: #94a4b0;font-size: 14px;line-height: 21px;font-family: Avenir,sans-serif;"> <div style="Margin-left: 20px;Margin-right: 20px;"> <div style="mso-line-height-rule: exactly;line-height: 30px;font-size: 1px;">&nbsp;</div> </div> <div style="font-size: 12px;font-style: normal;font-weight: normal;line-height: 19px;" align="center"> <a style="text-decoration: underline;transition: opacity 0.1s ease-in;color: #14215b;" href="https://gazeta.wokulski.online"><img style="border: 0;display: block;height: auto;width: 100%;max-width: 243px;" alt="Kingston logo" width="243" src="https://gazeta.wokulski.online/wp-content/uploads/2020/12/email_logo-9903cf0514028a3c.png" /></a> </div> <div style="Margin-left: 20px;Margin-right: 20px;Margin-top: 20px;"> <div style="mso-line-height-rule: exactly;line-height: 12px;font-size: 1px;">&nbsp;</div> </div> </div> <!--[if (mso)|(IE)]> </td> <td class="layout__edges">&nbsp;</td> </tr> </table> <![endif]--> </div> </div> </div> <div style="background-color: #ffffff;"> <div class="layout one-col stack" style="Margin: 0 auto;max-width: 600px;min-width: 320px; width: 320px;width: calc(28000% - 167400px);overflow-wrap: break-word;word-wrap: break-word;word-break: break-word;"> <div class="layout__inner" style="border-collapse: collapse;display: table;width: 100%;"> <!--[if (mso)|(IE)]> <table width="100%" cellpadding="0" cellspacing="0" role="presentation"> <tr class="layout-full-width" style="background-color: #ffffff;"> <td class="layout__edges">&nbsp;</td> <td style="width: 600px" class="w560"> <![endif]--> <div class="column" style="text-align: left;color: #94a4b0;font-size: 14px;line-height: 21px;font-family: Avenir,sans-serif;"> <div style="Margin-left: 20px;Margin-right: 20px;"> <div style="mso-line-height-rule: exactly;line-height: 40px;font-size: 1px;">&nbsp;</div> </div> <div style="Margin-left: 20px;Margin-right: 20px;"> <div style="mso-line-height-rule: exactly;mso-text-raise: 11px;vertical-align: middle;"> <h1 class="size-34" style="Margin-top: 0;Margin-bottom: 20px;font-style: normal;font-weight: normal;color: #fff;font-size: 30px;line-height: 38px;font-family: calibri,carlito,pt sans,trebuchet ms,sans-serif;text-align: center;" lang="x-size-34"> <span class="font-calibri"> <span style="color:#000000"> <font color="#14215b"> <strong>Propozycja Twojej Kampanii Reklamowej jest gotowa.</strong> </font> </span> </span> </h1> </div> </div> <div style="Margin-left: 20px;Margin-right: 20px;"> <div style="mso-line-height-rule: exactly;mso-text-raise: 11px;vertical-align: middle;"> <p class="size-16" style="Margin-top: 0;Margin-bottom: 0;font-family: avenir,sans-serif;font-size: 16px;line-height: 24px;text-align: center;" lang="x-size-16"> <span class="font-avenir"> <span style="color:#000000">i czeka na Ciebie w linku poniżej:</span> </span> </p> <p style="text-align: center;"><a target="_blank" href="https://gazeta.wokulski.online/konto/view-order/'.$post_id.'/">Wejdź, aby zobaczyć propozycję</a></p> </div> </div> <div style="Margin-left: 20px;Margin-right: 20px;Margin-top: 20px;"> <div style="mso-line-height-rule: exactly;mso-text-raise: 11px;vertical-align: middle;"> <p class="size-14" style="Margin-top: 0;Margin-bottom: 0;font-size: 14px;line-height: 21px;" lang="x-size-14"> <span style="color:#808080;">Wszystkie Twoje zamówienia możesz znaleźć w zakładce "Moje zamówienia",<br>po zalogowaniu się na <a href="https://gazeta.wokulski.online">https://gazeta.wokulski.online</a> </span> </p> </div> </div> </div> <!--[if (mso)|(IE)]> </td> <td class="layout__edges">&nbsp;</td> </tr> </table> <![endif]--> </div> </div> </div> <div class="layout one-col fixed-width stack" style="Margin: 0 auto;max-width: 600px;min-width: 320px; width: 320px;width: calc(28000% - 167400px);overflow-wrap: break-word;word-wrap: break-word;word-break: break-word;"> <div class="layout__inner" style="border-collapse: collapse;display: table;width: 100%;background-color: #ffffff;"> <!--[if (mso)|(IE)]> <table align="center" cellpadding="0" cellspacing="0" role="presentation"> <tr class="layout-fixed-width" style="background-color: #ffffff;"> <td style="width: 600px" class="w560"> <![endif]--> <div class="column" style="text-align: left;color: #94a4b0;font-size: 14px;line-height: 21px;font-family: Avenir,sans-serif;"> <div style="Margin-left: 20px;Margin-right: 20px;"> <div style="mso-line-height-rule: exactly;line-height: 40px;font-size: 1px;">&nbsp;</div> </div> </div> <!--[if (mso)|(IE)]> </td> </tr> </table> <![endif]--> </div> </div> <div class="layout one-col fixed-width stack" style="Margin: 0 auto;max-width: 600px;min-width: 320px; width: 320px;width: calc(28000% - 167400px);overflow-wrap: break-word;word-wrap: break-word;word-break: break-word;"> <div class="layout__inner" style="border-collapse: collapse;display: table;width: 100%;background-color: #ffffff;"> <!--[if (mso)|(IE)]> <table align="center" cellpadding="0" cellspacing="0" role="presentation"> <tr class="layout-fixed-width" style="background-color: #ffffff;"> <td style="width: 600px" class="w560"> <![endif]--> <div class="column" style="text-align: left;color: #94a4b0;font-size: 14px;line-height: 21px;font-family: Avenir,sans-serif;"> <div style="Margin-left: 20px;Margin-right: 20px;"> <div style="mso-line-height-rule: exactly;line-height: 20px;font-size: 1px;">&nbsp;</div> </div> </div> <!--[if (mso)|(IE)]> </td> </tr> </table> <![endif]--> </div> </div> <div class="layout one-col fixed-width stack" style="Margin: 0 auto;max-width: 600px;min-width: 320px; width: 320px;width: calc(28000% - 167400px);overflow-wrap: break-word;word-wrap: break-word;word-break: break-word;"> <div class="layout__inner" style="border-radius: 25px; border-collapse: collapse;display: table;width: 100%;background-color: #0d1b57;"> <!--[if (mso)|(IE)]> <table align="center" cellpadding="0" cellspacing="0" role="presentation"> <tr class="layout-fixed-width" style="background-color: #0d1b57;"> <td style="width: 600px" class="w560"> <![endif]--> <div class="column" style="text-align: left;color: #94a4b0;font-size: 14px;line-height: 21px;font-family: Avenir,sans-serif;"> <div style="font-size: 12px;font-style: normal;font-weight: normal;line-height: 19px;" align="center"> <img class="gnd-corner-image gnd-corner-image-center gnd-corner-image-top" style="border-radius: 25px 25px 0 0; display: block;height: 100px;width: 100%;max-width: 900px; object-fit: cover;" alt="College student celebrating graduation" width="600" src="https://gazeta.wokulski.online/wp-content/uploads/2020/12/AdobeStock_275546162-scaled-9900000000079e3c.jpeg" /> </div> <div style="Margin-left: 20px;Margin-right: 20px;Margin-top: 20px;"> <div style="mso-line-height-rule: exactly;line-height: 0px;font-size: 1px;">&nbsp;</div> </div> <div style="Margin-left: 20px;Margin-right: 20px;"> <div style="mso-line-height-rule: exactly;mso-text-raise: 11px;vertical-align: middle;"> <p class="size-24" style="Margin-top: 0;Margin-bottom: 0px;font-size: 20px;line-height: 28px;text-align: center;" lang="x-size-24"><span style="color:#ffffff">IT Solutions for Press</span></p> </div> </div> <div style="font-size: 12px;font-style: normal;font-weight: normal;line-height: 19px;" align="center"> <a style="text-decoration: underline;transition: opacity 0.1s ease-in;color: #14215b;" href="https://wokulski.online"><img style="border: 0;display: block;height: auto;width: 100%;max-width: 185px;" alt="Kingston logo" width="185" src="https://gazeta.wokulski.online/wp-content/uploads/2020/12/vg_logo_mini-w-9904510a2801453c.png" /></a> </div> <div style="Margin-left: 20px;Margin-right: 20px;Margin-top: 20px;"> <div style="mso-line-height-rule: exactly;line-height: 0px;font-size: 1px;">&nbsp;</div> </div> </div> <!--[if (mso)|(IE)]> </td> </tr> </table> <![endif]--> </div> </div> <div style="mso-line-height-rule: exactly;line-height: 20px;font-size: 20px;">&nbsp;</div> <div role="contentinfo"> <div class="layout email-footer stack" style="Margin: 0 auto;max-width: 600px;min-width: 320px; width: 320px;width: calc(28000% - 167400px);overflow-wrap: break-word;word-wrap: break-word;word-break: break-word;"> <div class="layout__inner" style="border-collapse: collapse;display: table;width: 100%;"> <!--[if (mso)|(IE)]> <table align="center" cellpadding="0" cellspacing="0" role="presentation"> <tr class="layout-email-footer"> <td style="width: 400px;" valign="top" class="w360"> <![endif]--> <div class="column wide" style="text-align: left;font-size: 12px;line-height: 19px;color: #94a4b0;font-family: Avenir,sans-serif;Float: left;max-width: 400px;min-width: 320px; width: 320px;width: calc(8000% - 47600px);"> <div style="Margin-left: 20px;Margin-right: 20px;Margin-top: 10px;Margin-bottom: 10px;"> <div style="font-size: 12px;line-height: 19px;"> <div>Vokulsky Group Sp. z o.o.<br /> Leszczyny 49A,<br /> 25-008 G&#243;rno<br /> KRS 0000817266<br /> NIP 6572949234<br /> REGON 385005507 </div> </div> <div style="font-size: 12px;line-height: 19px;Margin-top: 18px;"> <div>Otrzyma&#322;e&#347; tego emaila, poniewa&#380; z&#322;o&#380;y&#322;e&#347; zam&#243;wienie kampanii reklamowej na https://gazeta.wokulski.online </div> </div> <!--[if mso]>&nbsp;<![endif]--> </div> </div> <!--[if (mso)|(IE)]> </td> <td style="width: 200px;" valign="top" class="w160"> <![endif]--> <div class="column narrow" style="text-align: left;font-size: 12px;line-height: 19px;color: #94a4b0;font-family: Avenir,sans-serif;Float: left;max-width: 320px;min-width: 200px; width: 320px;width: calc(72200px - 12000%);"> <div style="Margin-left: 20px;Margin-right: 20px;Margin-top: 10px;Margin-bottom: 10px;">  </div> </div> <!--[if (mso)|(IE)]> </td> </tr> </table> <![endif]--> </div> </div> </div> <div style="line-height:40px;font-size:40px;">&nbsp;</div> </div> </td> </tr> </tbody> </table> </body> </html>';
				$headers = "Content-Type: text/html; charset=UTF-8\r\n";
				$headers .= "From: <biuro@wokulski.online>" . "\r\n";
				mail($order->get_billing_email(),"Propozycja gotowa",$msg,$headers);	
					
				/* Oznaczamy, że propozycja gotowa, aby móc wyświetlić pole akceptacji */
				update_field('field_5fcbee28bb73d','1', $post_id);	
					
			} else {
				
				$we_wait_for_x_redactions = $count_all_redactions_participating - $count_redactions_responses_to_now;
				$debug['StATUS'] = $we_wait_for_x_redactions." redakcji nie dało odpowiedzi.";

				$debug['rest'] = "Nie spełnia podstawowych parametrów";
				$debug['time'] = date("H:i:s") . substr((string)microtime(), 1, 8);

			}	
			
			$path = $_SERVER["DOCUMENT_ROOT"]."/UPDATE.json";
			$myfile = fopen($path, "a") or die("Unable to open file!");
			$string = print_r($debug, true);
			fwrite($myfile, $string);
			
			fclose($myfile);
	
}
add_action( 'woocommerce_update_order', 'action_woocommerce_update_order', 10, 1 ); 



/* ZABLOKUJ LINK DO PRODUKTU W KOSZYKU */
add_filter( 'woocommerce_cart_item_permalink', '__return_null' );

/* ZABLOKUJ LINK DO PRODUKTU W ZAMÓWIENIU */
add_filter( 'woocommerce_order_item_permalink', '__return_false' );

/* ODBLOKUJ STANDARDOWE CUSTOM FIELDY */
add_filter('acf/settings/remove_wp_meta_box', '__return_false');

;?>











