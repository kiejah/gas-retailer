<?php
// This file is generated. Do not modify it manually.
return array(
	'business-info' => array(
		'$schema' => 'https://schemas.wp.org/trunk/block.json',
		'apiVersion' => 3,
		'name' => 'lpg/business-info',
		'version' => '0.5.0',
		'title' => 'Business information',
		'category' => 'widgets',
		'icon' => 'location',
		'description' => 'Shop address, opening hours and phone from WooCommerce → Settings → LPG.',
		'keywords' => array(
			'address',
			'hours',
			'contact',
			'lpg'
		),
		'textdomain' => 'lpg-commerce-core',
		'attributes' => array(
			
		),
		'supports' => array(
			'html' => false,
			'spacing' => array(
				'margin' => true,
				'padding' => true
			),
			'typography' => array(
				'fontSize' => true
			)
		),
		'editorScript' => 'file:./index.js',
		'style' => 'file:./style-index.css',
		'render' => 'file:./render.php'
	),
	'compatible-products' => array(
		'$schema' => 'https://schemas.wp.org/trunk/block.json',
		'apiVersion' => 3,
		'name' => 'lpg/compatible-products',
		'version' => '0.3.0',
		'title' => 'Compatible products',
		'category' => 'woocommerce',
		'icon' => 'products',
		'description' => 'Cards for the products marked as compatible with this product: image, name, and price or Enquire.',
		'keywords' => array(
			'compatible',
			'related',
			'accessories',
			'lpg'
		),
		'textdomain' => 'lpg-commerce-core',
		'attributes' => array(
			'heading' => array(
				'type' => 'string',
				'default' => 'Compatible products'
			),
			'columns' => array(
				'type' => 'integer',
				'default' => 4
			),
			'limit' => array(
				'type' => 'integer',
				'default' => 8
			)
		),
		'usesContext' => array(
			'postId',
			'postType'
		),
		'supports' => array(
			'html' => false,
			'align' => array(
				'wide',
				'full'
			),
			'spacing' => array(
				'margin' => true,
				'padding' => true
			),
			'typography' => array(
				'fontSize' => true
			)
		),
		'example' => array(
			'attributes' => array(
				'heading' => 'Compatible products',
				'columns' => 4,
				'limit' => 4
			)
		),
		'editorScript' => 'file:./index.js',
		'style' => 'file:./style-index.css',
		'render' => 'file:./render.php'
	),
	'delivery-note' => array(
		'$schema' => 'https://schemas.wp.org/trunk/block.json',
		'apiVersion' => 3,
		'name' => 'lpg/delivery-note',
		'version' => '0.5.3',
		'title' => 'Delivery area note',
		'category' => 'woocommerce',
		'icon' => 'location-alt',
		'description' => 'A short note that delivery is within Nairobi only, with a WhatsApp link for other locations. For the checkout page.',
		'keywords' => array(
			'delivery',
			'shipping',
			'nairobi',
			'whatsapp',
			'lpg'
		),
		'textdomain' => 'lpg-commerce-core',
		'attributes' => array(
			'text' => array(
				'type' => 'string',
				'default' => ''
			)
		),
		'supports' => array(
			'html' => false,
			'spacing' => array(
				'margin' => true,
				'padding' => true
			),
			'typography' => array(
				'fontSize' => true
			)
		),
		'editorScript' => 'file:./index.js',
		'render' => 'file:./render.php'
	),
	'enquiry-button' => array(
		'$schema' => 'https://schemas.wp.org/trunk/block.json',
		'apiVersion' => 3,
		'name' => 'lpg/enquiry-button',
		'version' => '0.5.0',
		'title' => 'Enquire on WhatsApp',
		'category' => 'woocommerce',
		'icon' => 'format-chat',
		'description' => 'WhatsApp enquiry button with an editable message, for enquiry-only products (or all products). Shows nothing without a WhatsApp number.',
		'keywords' => array(
			'whatsapp',
			'enquiry',
			'enquire',
			'lpg'
		),
		'textdomain' => 'lpg-commerce-core',
		'attributes' => array(
			'showFor' => array(
				'type' => 'string',
				'enum' => array(
					'enquiry-only',
					'all'
				),
				'default' => 'enquiry-only'
			)
		),
		'usesContext' => array(
			'postId',
			'postType'
		),
		'supports' => array(
			'html' => false,
			'spacing' => array(
				'margin' => true,
				'padding' => true
			),
			'typography' => array(
				'fontSize' => true
			)
		),
		'editorScript' => 'file:./index.js',
		'style' => 'file:./style-index.css',
		'viewScript' => 'file:./view.js',
		'render' => 'file:./render.php'
	),
	'exchange-form' => array(
		'$schema' => 'https://schemas.wp.org/trunk/block.json',
		'apiVersion' => 3,
		'name' => 'lpg/exchange-form',
		'version' => '0.5.0',
		'title' => 'Cylinder refill / exchange form',
		'category' => 'widgets',
		'icon' => 'update',
		'description' => 'Choose a cylinder brand, size and delivery location, then continue on WhatsApp. Shows nothing without a WhatsApp number or cylinder brands.',
		'keywords' => array(
			'exchange',
			'refill',
			'cylinder',
			'whatsapp',
			'lpg'
		),
		'textdomain' => 'lpg-commerce-core',
		'attributes' => array(
			
		),
		'supports' => array(
			'html' => false,
			'spacing' => array(
				'margin' => true,
				'padding' => true
			),
			'typography' => array(
				'fontSize' => true
			),
			'align' => array(
				'wide'
			)
		),
		'editorScript' => 'file:./index.js',
		'style' => 'file:./style-index.css',
		'viewScript' => 'file:./view.js',
		'render' => 'file:./render.php'
	),
	'product-documents' => array(
		'$schema' => 'https://schemas.wp.org/trunk/block.json',
		'apiVersion' => 3,
		'name' => 'lpg/product-documents',
		'version' => '0.3.0',
		'title' => 'Product documents',
		'category' => 'woocommerce',
		'icon' => 'media-document',
		'description' => 'List of the product\'s PDF documents (datasheets, manuals, certificates) with type and file size.',
		'keywords' => array(
			'documents',
			'datasheet',
			'manual',
			'pdf',
			'lpg'
		),
		'textdomain' => 'lpg-commerce-core',
		'attributes' => array(
			'heading' => array(
				'type' => 'string',
				'default' => 'Product documents'
			)
		),
		'usesContext' => array(
			'postId',
			'postType'
		),
		'supports' => array(
			'html' => false,
			'align' => array(
				'wide',
				'full'
			),
			'spacing' => array(
				'margin' => true,
				'padding' => true
			),
			'typography' => array(
				'fontSize' => true
			)
		),
		'example' => array(
			'attributes' => array(
				'heading' => 'Product documents'
			)
		),
		'editorScript' => 'file:./index.js',
		'render' => 'file:./render.php'
	),
	'product-warranty' => array(
		'$schema' => 'https://schemas.wp.org/trunk/block.json',
		'apiVersion' => 3,
		'name' => 'lpg/product-warranty',
		'version' => '0.4.0',
		'title' => 'Product warranty',
		'category' => 'woocommerce',
		'icon' => 'shield',
		'description' => 'The product\'s warranty text. Shows nothing when the product has no warranty.',
		'keywords' => array(
			'warranty',
			'guarantee',
			'lpg'
		),
		'textdomain' => 'lpg-commerce-core',
		'attributes' => array(
			'label' => array(
				'type' => 'string',
				'default' => 'Warranty:'
			)
		),
		'usesContext' => array(
			'postId',
			'postType'
		),
		'supports' => array(
			'html' => false,
			'align' => array(
				'wide',
				'full'
			),
			'spacing' => array(
				'margin' => true,
				'padding' => true
			),
			'typography' => array(
				'fontSize' => true
			)
		),
		'example' => array(
			'attributes' => array(
				'label' => 'Warranty:'
			)
		),
		'editorScript' => 'file:./index.js',
		'render' => 'file:./render.php'
	),
	'rfq-form' => array(
		'$schema' => 'https://schemas.wp.org/trunk/block.json',
		'apiVersion' => 3,
		'name' => 'lpg/rfq-form',
		'version' => '0.5.0',
		'title' => 'Quote request form',
		'category' => 'widgets',
		'icon' => 'feedback',
		'description' => 'Request-a-quote form for commercial, industrial and household customers. Sends to the quote requests list and notifies Sales.',
		'keywords' => array(
			'quote',
			'rfq',
			'form',
			'lpg'
		),
		'textdomain' => 'lpg-commerce-core',
		'attributes' => array(
			
		),
		'supports' => array(
			'html' => false,
			'spacing' => array(
				'margin' => true,
				'padding' => true
			),
			'typography' => array(
				'fontSize' => true
			),
			'align' => array(
				'wide'
			)
		),
		'editorScript' => 'file:./index.js',
		'style' => 'file:./style-index.css',
		'viewScript' => 'file:./view.js',
		'render' => 'file:./render.php'
	),
	'spec-table' => array(
		'$schema' => 'https://schemas.wp.org/trunk/block.json',
		'apiVersion' => 3,
		'name' => 'lpg/spec-table',
		'version' => '0.3.0',
		'title' => 'Product specifications',
		'category' => 'woocommerce',
		'icon' => 'list-view',
		'description' => 'Table of the product\'s technical specifications from its spec profile. Shows nothing when the product has no specifications.',
		'keywords' => array(
			'specifications',
			'specs',
			'technical',
			'lpg'
		),
		'textdomain' => 'lpg-commerce-core',
		'attributes' => array(
			'heading' => array(
				'type' => 'string',
				'default' => 'Technical specifications'
			)
		),
		'usesContext' => array(
			'postId',
			'postType'
		),
		'supports' => array(
			'html' => false,
			'align' => array(
				'wide',
				'full'
			),
			'spacing' => array(
				'margin' => true,
				'padding' => true
			),
			'typography' => array(
				'fontSize' => true
			)
		),
		'example' => array(
			'attributes' => array(
				'heading' => 'Technical specifications'
			)
		),
		'editorScript' => 'file:./index.js',
		'style' => 'file:./style-index.css',
		'render' => 'file:./render.php'
	),
	'whatsapp-link' => array(
		'$schema' => 'https://schemas.wp.org/trunk/block.json',
		'apiVersion' => 3,
		'name' => 'lpg/whatsapp-link',
		'version' => '0.5.0',
		'title' => 'WhatsApp and phone link',
		'category' => 'widgets',
		'icon' => 'phone',
		'description' => 'A "WhatsApp us" text link and, optionally, the phone number. Shows nothing without a WhatsApp number.',
		'keywords' => array(
			'whatsapp',
			'phone',
			'contact',
			'lpg'
		),
		'textdomain' => 'lpg-commerce-core',
		'attributes' => array(
			'showPhone' => array(
				'type' => 'boolean',
				'default' => true
			)
		),
		'supports' => array(
			'html' => false,
			'spacing' => array(
				'margin' => true,
				'padding' => true
			),
			'typography' => array(
				'fontSize' => true
			)
		),
		'editorScript' => 'file:./index.js',
		'render' => 'file:./render.php'
	)
);
