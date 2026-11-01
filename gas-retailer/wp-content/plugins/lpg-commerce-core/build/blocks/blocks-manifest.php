<?php
// This file is generated. Do not modify it manually.
return array(
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
	)
);
