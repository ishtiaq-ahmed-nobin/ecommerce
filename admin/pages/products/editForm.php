<?php
 define('APP_SECRET','123456789000');//Creating Constant

function base64url_encode($data)
{
	return rtrim(strtr(base64_encode($data), '+/','-_'),'=');
}

function base64url_decode($data)
{
	return base64_decode(strtr($data, '-_','+/'));
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

/* Validate Product ID */

if(!isset($_GET['pid']))
{
	die("Invalid Request!");
}

$pid = decryptId($_GET['pid']);

if(!$pid)
{
	die("Invalid Request!");
}

$errmsg = '';
$successmsg = '';

/* Category Fetch */

$cat_stmt = $DB_con->prepare("SELECT * FROM categories ORDER BY category_name ASC");
$cat_stmt->execute();
$categories = $cat_stmt->fetchAll(PDO::FETCH_ASSOC);

/* Fetch Products, Attributes */

$pstmt = $DB_con->prepare("SELECT * FROM products WHERE id = :id");
$pstmt->execute([':id' => $pid]);
$product = $pstmt->fetch(PDO::FETCH_ASSOC);

if(!$product)
{
	die("Product Not Found!");
}

$astmt = $DB_con->prepare("SELECT * FROM attributes WHERE product_id = :pid LIMIT 1");
$astmt->execute([':pid' => $pid]);
$attr = $astmt->fetch(PDO::FETCH_ASSOC);

$existingSizes = $attr['sizes'] ?? '';
$existingColors = $attr['colors'] ?? '';

if(isset($_POST['btnupdate']))
{
	$productname = trim($_POST['product_name'] ?? '');
	$description = trim($_POST['description'] ?? '');
	$productstock = trim($_POST['product_stock'] ?? '');
	$category_id = trim($_POST['category_id'] ?? '');

	$has_attributes = isset($_POST['has_attributes']) ? 1 : 0;

	$sizes = isset($_POST['sizes']) ? implode(',', $_POST['sizes']) : '';

	$colors = trim($_POST['colors'] ?? '');

	if(empty($productname) || empty($description) || $productstock === '' || empty($category_id))
	{
		$errmsg = "All fields are Required";
	}

	$newImageName = $product['product_image'];

	if(empty($errmsg) && isset($_FILES['product_image']) && !empty($_FILES['product_image']['name']))
	{
		$imgfile = $_FILES['product_image']['name'];
		$temp_dir = $_FILES['product_image']['tmp_name'];
		$imgsize = $_FILES['product_image']['size'];

		$upload_dir = "uploads/";

		if(!is_dir($upload_dir))
		{
			mkdir($upload_dir, 0777, true);
		}

		$imgext = strtolower(pathinfo($imgfile, PATHINFO_EXTENSION));
		$valid_extensions = ['jpg','jpeg','png','gif'];
		$productpic = rand(1000,100000000).".".$imgext;

		if(!in_array($imgext, $valid_extensions))
		{
			$errmsg = "Invalid image type (jpg, png, gif only)";
		}

		elseif($imgsize > 5000000)
		{
			$errmsg = "Image size too large (max 5MB)";
		}

		else
		{
			if(move_uploaded_file($temp_dir, $upload_dir . $productpic))
			{
				if(!empty($product['product_image']))
				{
					$old = $upload_dir . $product['product_image'];
					if(is_file($old)) @unlink($old);
				}

				$newImageName = $productpic;
			}

			else
			{
				$errmsg = "Image Upload Failed";
			}
		}
	}

	if(empty($errmsg))
	{
		try 
		{
			$DB_con->beginTransaction();

			//Update Products

			$up = $DB_con->prepare("UPDATE products SET

									product_name = :pname,
									description = :pdesc,
									product_image = :pimg,
									stock_amount = :pstock,
									has_attributes = :hasattr,
									category_id = :cat
							WHERE id = :id");
			$up->execute([

						':pname' => $productname,
						':pdesc' => $description,
						':pimg' => $newImageName,
						':pstock' => $productstock,
						':hasattr' => $has_attributes,
						':cat' => $category_id,
						':id' => $pid

						]);
			if($has_attributes)
			{
				$check = $DB_con->prepare("SELECT id FROM attributes WHERE product_id = :pid LIMIT 1");
				$check->execute([':pid' => $pid]);
				$exists = $check->fetch(PDO::FETCH_ASSOC);

				if($exists)
				{
					$au = $DB_con->prepare("UPDATE attributes set sizes = :sizes, colors = :colors WHERE product_id = :pid");
					$au->execute([':sizes' => $sizes, ':colors' => $colors, ':pid' => $pid]);
				}

				else
				{
					$ai = $DB_con->prepare("INSERT INTO attributes(product_id, sizes, colors) VALUES (:pid, :sizes, :colors)");
					$ai->execute([':pid' => $pid, ':sizes' => $sizes, ':colors' => $colors]);
				}
			}

			else
			{
				$ad = $DB_con->prepare("DELETE FROM attributes WHERE product_id = :pid");
				$ad->execute([':pid' => $pid]);
			}

			$DB_con->commit();
			header('Location: index.php?page=products&msg=updated');
			exit;
		} 
		catch (Exception $e) 
		{
			$DB_con->rollback();
			$errmsg = "Update Error: ".$e->getMessage();
		}
	}

	$product['product_name'] = $productname;
	$product['description'] = $description;
	$product['stock_amount'] = $productstock;
	$product['category_id'] = $category_id;
	$product['has_attributes'] = $has_attributes;
	$product['product_image'] = $newImageName;

	$existingSizes = $sizes;
	$existingColors = $colors;

}

/* Pre-check Attributes before render in UI */

$sizesArr = array_filter(array_map('trim', explode(',', (string)$existingSizes)));
$colorsStr = (string)$existingColors;
$hassAttrChecked = ((int)$product['has_attributes'] === 1);
$currentImg = !empty($product['product_image']) ? "uploads/" .$product['product_image'] : "";
?>

<!DOCTYPE html>
<html>
<head>
	<title>Edit Product</title>

	<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css" integrity="sha384-xOolHFLEh07PJGoPkLv1IbcEPTNtaed2xpHsD9ESMhqIYd0nLMwNLD69Npy4HI+N" crossorigin="anonymous">

	<style type="text/css">
		.page-wrap {max-width: 1200px;}
		.color-item {display: inline-flex; align-items: center; margin-right: 10px; margin-bottom: 10px;}
		.color-box {width: 38px; height: 22px; border: 1px solid #ccc; display: inline-block; vertical-align: middle; margin-right: 8px;}
		.color-code {font-size: 13px; margin-right: 6px;}
	</style>
</head>
<body>

	<div class="container page-wrap mt-5">
		<div class="d-flex align-items-center justify-content-between mb-3">
			<h2 class="mb-0">Edit products</h2>
			<a href="index.php?page=products" class="btn btn-primary btn-sm"><- Back</a>
		</div>

		<?php if(!empty($errmsg)): ?>
			<div class="alert alert-danger"><?php echo $errmsg; ?></div>
		<?php endif; ?>

		<form method="post" enctype = "multipart/form-data">
			<div class="form-group">
				<label><strong>Product name:</strong></label>
				<input type="text" name="product_name" class="form-control" value="<?php echo htmlspecialchars($product['product_name']); ?>">
			</div>

			<div class="form-group">
				<label><strong>Description:</strong></label>
				<textarea name="description" class="form-control" rows="4"><?php echo htmlspecialchars($product['description']); ?></textarea>
			</div>

			<div class="form-group">
				<label><strong>Current Image:</strong></label><br>
				<?php if($currentImg && is_file($currentImg)): ?>
					<img src="<?php echo htmlspecialchars($currentImg); ?>" class="thumb" alt="">
					<?php else : ?>
						<span class="text-muted">No Image</span>
					<?php endif; ?>
			</div>

			<div class="form-group">
				<label><strong>Change Image (optional):</strong></label>
				<input type="file" name="product_image" class="form-control-file">
				<small class="text-muted">Leave empty to keep the current image.</small>
			</div>

			<div class="form-group">
				<label><strong>Stock Amount:</strong></label>
				<input type="number" name="product_stock" class="form-control" value="<?php echo htmlspecialchars((string)$product['stock_amount']); ?>">
			</div>

			<div class="form-group">
				<label><strong>Category:</strong></label>
				<select name="category_id" class="form-group">
					<option value="">Select Category</option>
					<?php foreach( $categories as $cat) : ?>
						<option value="<?php echo $cat['id']; ?>" <?php echo ((int)$product['category_id'] === (int)$cat['id']) ? 'selected' : '';?>><?php echo htmlspecialchars($cat['category_name']); ?>			
						</option>
					<?php endforeach; ?>
				</select>
			</div>

			<div class="custom-control custom-checkbox mb-3">
			<input type="checkbox" name="has_attributes" class="custom-control-input" id="hasAttributes" onchange="toggleAttributes()" <?php echo $hassAttrChecked ? 'checked' : ''; ?>>
			<label class="custom-control-label" for="hasAttributes"><strong>Has Attributes ?</strong></label>
			</div>

			<div id="attributeSection" style="display: none;">
				<div class="form-group">
					<label><strong>Sizes:</strong></label><br>
					<?php

						$allSizes = ['L','XL','XXL'];
						foreach($allSizes as $s) :
							$checked = in_array($s, $sizesArr) ? 'checked' : '';
							$id = "size_" .$s; 
					?>


					<div class="custom-control custom-checkbox custom-control-inline">
						<input type="checkbox" name="sizes[]" class="custom-control-input" id="<?php echo $id; ?>" value="<?php echo $s; ?>" <?php echo $checked; ?>>
						<label class="custom-control-label" for="<?php echo $id; ?>"><?php  echo $s; ?></label>
					</div>
				<?php endforeach; ?>
				</div>

				<div class="form-group">
					<label><strong>Colors:</strong></label>
					<div class="d-flex align-items-center">
						<input type="color" name="colorPicker" id="colorPicker" class="mr-3" value="#000000">
						<button type="button" class="btn btn-secondary btn-sm" onclick="addColor()">Add Color</button>
						<button type="button" class="btn btn-outline-danger btn-sm ml-2" onclick="clearColors()">Clear</button>
					</div>

					<div id="colorList" class="mt-3">
						
					</div>

					<input type="hidden" name="colors" id="colors" value="<?php echo htmlspecialchars($colorsStr); ?>">
				</div>
			</div>

			<button type="submit" name="btnupdate" class="btn btn-success">Update</button>
		</form>
	</div>
		<script type="text/javascript">
		const attributeSection = document.getElementById('attributeSection');
		const hasAttributes = document.getElementById('hasAttributes');
		const colorPicker = document.getElementById('colorPicker');
		const colorList = document.getElementById('colorList');
		const colorsHidden = document.getElementById('colors');

		let selectedColors = [];

		function toggleAttributes()
		{
			attributeSection.style.display = hasAttributes.checked ? 'block' : 'none';

			if(!hasAttributes.checked)
			{
				selectedColors = [];
				colorsHidden.value = '';
				colorList.innerHTML = '';
			}
		}

		function addColor()
		{
			const color = (colorPicker.value || '').toLowerCase();

			if(selectedColors.includes(color)) return;

			selectedColors.push(color);
			renderColors();
		}

		function removeColor(color)
		{
			selectedColors = selectedColors.filter(c => c !== color);
			renderColors();
		}

		function clearColor()
		{
			selectedColors = [];
			renderColors();
		}

		function renderColors()
		{
			colorsHidden.value = selectedColors.join(',');

			//UI Render

			colorList.innerHTML = '';
			selectedColors.forEach(color => {

				const wrap = document.createElement('div');
				wrap.className = 'color-item';

				const box = document.createElement('span');
				box.className = 'color-box';
				box.style.background = color;

				const code = document.createElement('span');
				code.className = 'color-code';
				code.textContent = color;

				const btn = document.createElement('button');
				btn.type = 'button';
				btn.className = 'btn btn-sm btn-danger';
				btn.textContent = 'x';
				btn.onclick = () => removeColor(color);

				wrap.appendChild(box);
				wrap.appendChild(code);
				wrap.appendChild(btn);

				colorList.appendChild(wrap);
			});
		}	


		(function init(){

			toggleAttributes();

			const postedColors = "<?php echo isset($_POST['colors']) ? addslashes($_POST['colors']) : ''; ?>"

			if(postedColors && hasAttributes.checked)
			{
				selectedColors = postedColors.split(',').map(c => c.trim().toLowerCase()).filter(Boolean);
				renderColors();
			}

			<?php

				if(isset($_POST['sizes']) && is_array($_POST['sizes']))
				{
					foreach($_POST['sizes'] as $sz)
					{
						$sz = preg_replace('/[^A-Z]/','',$sz);
						if(in_array($sz, ['L','XL','XXL']))
						{
							echo "document.querySelector(\"input[name='sizes[]'][value='{$sz}']\").checked = true;\n";
						}
					}
				}

			?>

		})();
	</script>
</body>
</html>