<?php

require_once __DIR__ .'/includes/dbConfig.php';

//Routing

$page = $_GET['page'] ?? 'dashboard';

$allowedPages = [

				'dashboard' => __DIR__.'/pages/dashboard.php',
				// Products
				'products' => __DIR__.'/pages/products/allProducts.php',
				'addProduct' => __DIR__.'/pages/products/addNew.php',
				'editProduct' => __DIR__.'/pages/products/editForm.php',
				// Categories
				'categories' => __DIR__.'/pages/categories/allCategories.php',
				'addCategories' => __DIR__.'/pages/categories/addNew.php',
				'ediCategories' => __DIR__.'/pages/categories/editForm.php'

				];

if(!isset($allowedPages[$page]))
{
	$page = 'dashboard';
}

$pagePath = $allowedPages[$page];

//Layout Loading

include __DIR__.'/includes/header.php';
include __DIR__.'/includes/sidebar.php';

?>

<div class="col-md-10 content-area">
	<?php include $pagePath; ?>
</div>

<?php include __DIR__.'/includes/footer.php'; ?>