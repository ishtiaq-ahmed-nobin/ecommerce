<?php

error_reporting(0);
include 'dbConfig.php';

define('APP_SECRET','123456789000');//Creating Constant

function base64url_encode($data)
{
	return rtrim(strtr(base64_encode($data), '+/','-_'),'=');
}

function base64url_decode($data)
{
	return base64_decode(strtr($data, '-_','+/'));
}

function encryptId($id)
{
	$key = hash('sha256', APP_SECRET, true);
	$iv = substr(hash('sha256', APP_SECRET), 0, 16);
	$cipher = openssl_encrypt((string)$id, 'AES-256-CBC', $key, 0, $iv);
	return base64url_encode($cipher);
}

function decryptId($token)
{
	$key = hash('sha256', APP_SECRET, true);
	$iv = substr(hash('sha256', APP_SECRET), 0, 16);
	$cipher = base64url_decode($token);
	$plain = openssl_decrypt($cipher, 'AES-256-CBC', $key, 0, $iv);
	if($plain === false || !ctype_digit($plain)) return null;
	return (int)$plain;
}

/*-----------Delete Code----------*/

$errmsg = '';
$successmsg = '';

if(isset($_GET['del']))
{
	$pid = decryptId($_GET['del']);

	if(!$pid)
	{
		$errmsg = "Invalid Delete Request!";
	}

	else
	{
		try 
		{
			$imgStmt = $DB_con->prepare("SELECT product_image FROM products WHERE id = :id");
			$imgStmt->execute([':id' => $pid]);
			$row = $imgStmt->fetch(PDO::FETCH_ASSOC);

			$delStmt = $DB_con->prepare("DELETE FROM products WHERE id = :id");
			if($delStmt->execute([':id' => $pid]))
			{
				if($row && !empty($row['product_image']))
				{
					$path = "uplodas/".$row['product_image'];
					if(is_file($path)) @unlink($path);
				}

				header("Location: index.php?page=products&msg=deleted");
				exit;
			}

			else
			{
				$errmsg = "Delete Failed!";
			}	
		} 
		catch (Exception $e) 
		{
			$errmsg = "Delete Error: ".$e->getMessage();
		}
	}
}

if(isset($_GET['msg']) && $_GET['msg'] === 'updated') $successmsg = "Product Updated";
if(isset($_GET['msg']) && $_GET['msg'] === 'deleted') $successmsg = "Product Deleted";
//Fetch all products, category, attributes

$sql = "SELECT 

			p.*,
			c.category_name,
			a.sizes,
			a.colors
		FROM products p
		LEFT JOIN categories c ON c.id = p.category_id
		LEFT JOIN attributes a ON a.product_id = p.id
		ORDER BY p.id DESC";

$stmt = $DB_con->prepare($sql);
$stmt->execute();
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

function renderSizeBadges($sizesCsv)
{
	if(!$sizesCsv) return '';

	$sizes = array_filter(array_map('trim', explode(',', $sizesCsv)));

	$html = '';

	foreach($sizes as $s)
	{
		$s = htmlspecialchars($s);
		$html .= "<span class = 'badge badge-info mr-1'>{$s}</span>";
	}

	return $html;
}


function renderColorBadges($colorsCSV)
{
	if(!$colorsCSV) return '';

	$colors = array_filter(array_map('trim', explode(',', $colorsCSV)));
	$html = '';
	foreach ($colors as $c) 
	{
		$c = strtolower($c);
		if(!preg_match('/^#[0-9a-f]{6}$/', $c)) continue;
		$cc = htmlspecialchars($c); //Cross Site Scripting (XSS)
		$html .= "<span class = 'd-inline-flex align-items-center mr-2 mb-1'>
					<span style='width:18px; height: 18px; border: 1px solid #ccc; background: {$cc}; display: inline-block; margin-right:6px; border-radius: 3px;'></span><small class = 'text-muted'>{$cc}</small>
				</span>";
	}

	return $html;
}

?>

<!DOCTYPE html>
<html>
<head>
	<title>All Products</title>

	<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css" integrity="sha384-xOolHFLEh07PJGoPkLv1IbcEPTNtaed2xpHsD9ESMhqIYd0nLMwNLD69Npy4HI+N" crossorigin="anonymous">

	<style type="text/css">
		.thumb 
		{
			width: 70px;
			height: 70px;
			object-fit: cover;
			border: 1px solid #ddd;
			border-radius: 6px;
		}

		.attr-wrap
		{
			line-height: 1.4;
		}

		.table td 
		{
			vertical-align: middle;
		}
	</style>
</head>
<body>

	<div class="container mt-5">
		<div class="d-flex align-items-center justify-content-between mb-3">
			<h2 class="mb-0">All Products</h2>
			<a href="index.php?page=addProduct" class="btn btn-primary btn-sm">+ Add Products</a>
		</div>

		<!--Error Message Script-->
		<?php 
			if(!empty($errmsg)) : ?>
			<div class="alert alert-danger"><?php echo $errmsg; ?></div> 
		<?php endif; ?>

		<!--Success Message Script-->

		<?php if(!empty($successmsg)): ?>
		<div class="alert alert-success"><?php echo $successmsg; ?></div>
		<?php endif; ?>	

		<div class="table-responsive">
			<table class="table table-bordered table-hover">
				<thead class="thead-dark">
					<tr>
						<th style="width: 70px;">#</th>
						<th>Products</th>
						<th style="width: 140px;">Stock</th>
						<th style="width: 220px;">Category</th>
						<th style="width: 180px;">Actions</th>
					</tr>					
				</thead>
				<tbody>
					<?php if(!$products) :?>
						<tr><td colspan="5" class="text-center">No Products Found!</td></tr>
						<?php else : ?>
							<?php foreach($products as $i => $p): ?>
								<?php
									$encId = encryptId($p['id']);
									$img = !empty($p['product_image']) ? "uploads/" .$p['product_image'] : "";

								?>
						<tr>
							<td><?php echo $i + 1;?></td>

							<td>
								<div class="d-flex">
									<div class="mr-3">
										<?php if($img && is_file($img)): ?>
											<img src="<?php echo htmlspecialchars($img); ?>" class = "thumb" alt = "">
											<?php else : ?>
												<div class="thumb d-flex align-items-center justify-content-center text-muted">No Image						
												</div>
										<?php endif; ?>
									</div>

									<div class="attr-wrap">
										<div><strong><?php echo htmlspecialchars($p['product_name']); ?></strong></div>
										<div class="text-muted small">
											<?php echo htmlspecialchars($p['description']); ?>
										</div>

										<?php if((int)$p['has_attributes'] === 1): ?>
											<div class="mt-2">
												<div class="mb-1">
													<small class="text-dark"><strong>Sizes:</strong></small><br>
													<?php echo renderSizeBadges($p['sizes'] ?? ''); ?>
												</div>

											<div>
												<small class="text-dark"><strong>Colors:</strong></small><br>
												<?php echo renderColorBadges($p['colors'] ?? ''); ?>
											</div>
										</div>
									<?php endif; ?>	
									</div>
								</div>
							</div>							
							</td>

							<td><?php echo (int)$p['stock_amount']; ?></td>
							<td><?php echo htmlspecialchars($p['category_name'] ?? '__'); ?></td>

							<td>
								<a href="index.php?page=editProduct&pid=<?php echo urlencode($encId); ?>" class = "btn btn-sm btn-warning">Edit</a>

								<a href="index.php?page=products&del=<?php echo urlencode($encId);?>" onclick = "return confirm('Are you sure you want to delete this product?');" class = "btn btn-sm btn-danger">Delete</a>
							</td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
				</tbody>
			</table>
		</div>
 	</div>

</body>
</html>