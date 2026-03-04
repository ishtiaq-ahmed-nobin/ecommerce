<?php
$errmsg = "";
$successmsg = "";

//Fetch Categories

$cat_stmt = $DB_con->prepare("SELECT * FROM categories ORDER BY category_name ASC");
$cat_stmt->execute();
$categories = $cat_stmt->fetchAll(PDO::FETCH_ASSOC);

if(isset($_POST['btnsave']))
{
	$productname = trim($_POST['product_name'] ?? '');
	$description = trim($_POST['description'] ?? '');
	$productstock = trim($_POST['product_stock'] ?? '');
	$category_id = trim($_POST['category_id'] ?? '');	

	//Attributes Part

	$has_attributes = isset($_POST['has_attributes']) ? 1 : 0;
	$sizes = isset($_POST['sizes']) ? implode(',', $_POST['sizes']) : '';
	$colors = trim($_POST['colors'] ?? '');

	//Image File

	$imgfile = $_FILES['product_image']['name'] ?? '';
	$tmp_dir = $_FILES['product_image']['tmp_name'] ?? '';
	$imgsize = $_FILES['product_image']['size'] ?? 0;

	if(empty($productname) || empty($description) || empty($imgfile) || $productstock === '' || empty($category_id))
	{
		$errmsg = "All fields are required";
		header('refresh: 2');
	}

	else
	{
		$upload_dir = "uploads/";
		if(!is_dir($upload_dir))
		{
			mkdir($upload_dir, 0777, true);
		}

		$imgext = strtolower(pathinfo($imgfile, PATHINFO_EXTENSION));
		$valid_extension = ['jpg','jpeg','png','gif'];
		$productpic = rand(1000, 1000000000).".".$imgext;

		if(!in_array($imgext, $valid_extension))
		{
			$errmsg = "Invalid image type (jpg, jpeg, png, gif only)";
		}

		elseif($imgsize > 5000000)
		{
			$errmsg = "Imgae size is too large (max 5MB)";
		}

		else
		{
			move_uploaded_file($tmp_dir, $upload_dir . $productpic);
		}
	}

	//Insert to DB

	if(empty($errmsg))
	{
		try 
		{
			$DB_con->beginTransaction();

			$stmt = $DB_con->prepare("INSERT INTO products (product_name, description, product_image, stock_amount, has_attributes, category_id) VALUES (:pname,:pdesc,:ppic,:pstock, :hasattr, :cat_id)");

			$stmt->bindParam(':pname', $productname);
			$stmt->bindParam(':pdesc', $description);
			$stmt->bindParam(':ppic', $productpic);
			$stmt->bindParam(':pstock', $productstock);
			$stmt->bindParam(':hasattr', $has_attributes);
			$stmt->bindParam(':cat_id', $category_id);

			if($stmt->execute())
			{
				$lastProductId = $DB_con->lastInsertId();

				if($has_attributes)
				{
					$attr_stmt = $DB_con->prepare("INSERT INTO attributes (product_id, sizes, colors) VALUES (:pid, :sizes, :colors)");
					$attr_stmt->bindParam(':pid', $lastProductId);
					$attr_stmt->bindParam(':sizes', $sizes);
					$attr_stmt->bindParam(':colors', $colors);
					$attr_stmt->execute();
				}

				$DB_con->commit();
				$successmsg = "New Product Added Successfully";
				header('refresh: 5');
			}

			else
			{
				$DB_con->rollback();
				$errmsg = "Error while Inserting";
			}
		} 
		catch (Exception $e) 
		{
			$DB_con->rollback();
			$errmsg = "DB Error: ".$e->getMessage();
		}
	}
}
?>

<!DOCTYPE html>
<html>
<head>
	<title>Add New Products</title>

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
		<h2 class="mb-4">Add Products</h2>
		<a href="index.php?page=products" class="btn btn-outline-secondary btn-sm">View All</a>
		</div>

		<?php
			//Error Message
		 if(!empty($errmsg)):
		?>
		<div class="alert alert-danger"><?php echo $errmsg;?></div>
	<?php endif; ?>

		<?php  //Success Message 
			if(!empty($successmsg)):
		?>
		<div class="alert alert-success"><?php echo $successmsg; ?></div>
	<?php endif; ?>
		 

		

		<form method="post" enctype="multipart/form-data">
		  <div class="form-group">
		  	<label><strong>Product Name:</strong></label>
			<input type="text" name="product_name" class="form-control" value="<?php echo $_POST['product_name'] ?? ''; ?>">
		  </div>

		  <div class="form-group">
		  	<label><strong>Description:</strong></label>
			<textarea name="description" class="form-control" rows="4">
				<?php  echo $_POST['description'] ?? ''; ?>
			</textarea>
		  </div>

		  <div class="form-group">
		  	<label><strong>Product Image:</strong></label>
			<input type="file" name="product_image" class="form-control-file">
		  </div>

		  <div class="form-group">
		  	<label><strong>Stock Amount:</strong></label>
			<input type="number" name="product_stock" class="form-control" value="<?php echo $_POST['product_stock'] ?? ''; ?>">
		  </div>

		  <div class="form-group">
		  	<label><strong>Category:</strong></label>
		  	<select name="category_id" class="form-control">
		  		<option value="">Select Category</option>
		  		<?php foreach($categories as $cat): ?>

		  			<option value="<?php echo $cat['id']; ?>" <?php echo (isset($_POST['category_id']) && $_POST['category_id'] == $cat['id']) ? 'selected' : ''; ?>><?php echo $cat['category_name']; ?>	  				
		  			</option>
		  		<?php endforeach;?>
		  	</select>
		  </div>

		  <div class="custom-control custom-checkbox mb-3">
		  	<input type="checkbox" name="has_attributes" class="custom-control-input" id="hasAttributes" onchange="toggleAttributes()" <?php echo isset($_POST['has_attributes']) ? 'checked' : ''; ?>>

		  	<label class="custom-control-label" for="hasAttributes"><strong>Has Attributes?</strong></label>
		  </div>

		  <!--Attributes Section-->

		  <div id="attributeSection" style="display: none;">
		  	<div class="form-group">
		  		<label><strong>Sizes:</strong></label><br>
		  		<div class="custom-control custom-checkbox custom-control-inline">
		  			<input type="checkbox" name="sizes[]" class="custom-control-input" id="sizeL" value="L">
		  			<label class="custom-control-label" for="sizeL">L</label>
		  		</div>

		  		<div class="custom-control custom-checkbox custom-control-inline">
		  			<input type="checkbox" name="sizes[]" class="custom-control-input" id="sizeXL" value="XL">
		  			<label class="custom-control-label" for="sizeXL">XL</label>
		  		</div>

		  		<div class="custom-control custom-checkbox custom-control-inline">
		  			<input type="checkbox" name="sizes[]" class="custom-control-input" id="sizeXXL" value="XXL">
		  			<label class="custom-control-label" for="sizeXXL">XXL</label>
		  		</div>
		  	</div>

		  	<div class="form-group">
		  		<label><strong>Colors:</strong></label>
		  		<div class="d-flex align-items-center">
		  			<input type="color" name="colors" id="colorPicker" class="mr-3" value="#000000">
		  			<button type="button" class="btn btn-secondary btn-sm" onclick="addColor()">Add Color</button>
		  			<button type="button" class="btn btn-outline-danger btn-sm ml-2" onclick="clearColor()">Clear</button>
		  		</div>

		  		<div id="colorList" class="mt-3"></div>

		  		<input type="hidden" name="colors" id="colors" value="">
		  	</div>
		  </div>
			
		<button type="submit" name="btnsave" class="btn btn-success">Save</button>
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