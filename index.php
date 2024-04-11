<?  
error_reporting(E_ALL);
ini_set("display_errors", 1); 

class DBConnection  
{       
  protected $mysqli;
  private  $db_host="localhost";
  private  $db_name="cl161952_nashop";
  private  $db_username="cl161952_nashop";
  private  $db_password="aSJpKE1A";

  public function __construct()
    {
        $this->mysqli=new mysqli($this->db_host,$this->db_username,
                $this->db_password,$this->db_name) or die($this->mysqli->error);

         return $this->mysqli;
    }
	
 public function getLink()
{
    return $this->mysqli;
}

   function __destruct(){
     //Close the Connection
     $this->mysqli->close();
    }
}

class Page {
	
	public function __construct(DBConnection $db) {
		//
		session_start();
		// logout call
		if (isset($_GET['action']) && ($_GET['action'] == "logout")) {
			session_destroy();
			setcookie("customer_name", NULL, strtotime('+365 days'));
			setcookie("customer_id", NULL, strtotime('+365 days'));
			header("location: /");
		}
		// auth
		$this -> mysqli = $db -> getLink();
		$this -> auth();
	}
	
	/*function menu($category_id) {
		if (!isset($category_id)) {
			$query = "SELECT * FROM sh_category
						WHERE parent_id IS NULL";
		}
		else {
			$query = "SELECT * FROM sh_category
						WHERE parent_id = '{$category_id}'";
		} // if (!$category_id)	
		$result = $this -> mysqli -> query($query);
		if ($result -> num_rows == NULL) {
			echo "<br />";
		}
		while ($row = $result -> fetch_array()) {
			echo "{$row['name']}\n";
			$this->menu($row['id']);
		} // while
	} // f menu */
	
	function auth() {
		if (!isset($_SESSION['customer_id']) && isset($_COOKIE['customer_id'])) $_SESSION['customer_id'] = $_COOKIE['customer_id'];
		if (!isset($_SESSION['customer_name']) && isset($_COOKIE['customer_name'])) $_SESSION['customer_name'] = $_COOKIE['customer_name'];
		// auth check
		if (!isset($_SESSION['customer_id'])) {
			if(!isset($_POST['customer_inn']) && !isset($_GET['inn'])) {
				$this -> header();
				include("templates/auth.htm");
				$this -> footer();
				exit();
			} else {
				if (!$this -> authentication_request()) {
					$auth_error = TRUE;
					$this -> header();
					include("templates/auth.htm");
					echo "<div class=\"alert alert-danger\" role=\"alert\">ИНН или пароль невернен. Пожалуйста, проверьте корретность ввода ИНН (Вы ввели {$_POST['customer_inn']}) и пароля. Если все верно, обратитесь к Вашему менеджеру.</div>\n";
					$this -> footer();
					exit(); // bye bye
				}
			}
		}
	}
	
	function authentication_request() {
		if (isset($_POST['customer_inn'])) {
			$customer_inn = $_POST['customer_inn'];
			$password = $_POST['password'];
		}
		// pass without auth form directly by url
		if (isset($_GET['inn'])) {
			$customer_inn = $_GET['inn'];
			$password = $_GET['password'];
		}
		if (isset($customer_inn)) {
			$customer_inn = $this -> mysqli -> real_escape_string($customer_inn);
			$query = "SELECT * FROM sh_customer 
						WHERE inn = '{$customer_inn}'
							AND is_granted = '1'";
			$result = $this -> mysqli -> query($query);
			if($result -> num_rows === 0) {
				return FALSE; 
			} else {
				if ($password == substr(intval($customer_inn/intval(substr($customer_inn,6,9)) - 549183),0,6)) {
					$row = $result -> fetch_array(MYSQL_ASSOC);
					setcookie("customer_id", $row['id'], strtotime('+365 days'));
					setcookie("customer_name", $row['name'], strtotime('+365 days'));
					$_SESSION['customer_id'] = $row['id'];
					$_SESSION['customer_name'] = $row['name'];
					return TRUE;
				} else { // if 
					//echo substr(intval(substr($customer_inn,5,8).substr($customer_inn,3,5).substr($customer_inn,1,3)),0,6);
					return FALSE;
					
				} // else
			} // else
		} // if (isset($customer_inn))
	}
	
	public function header() {
		include("templates/header.htm");
	}
	
	function footer() { 
		include("templates/footer.htm");
	}
}

class Category {
	
	public function __construct(DBConnection $db) {
		$this->mysqli = $db->getLink();
	}
	
	function breadcrumb($category_id) {
		if (!isset($category_id)) 
			$category_id = $_GET['category_id'];
		$query = "SELECT * FROM sh_category 
					WHERE id = '{$category_id}'
						LIMIT 1";
		$result = $this -> mysqli -> query($query);
		$row = $result -> fetch_array();
		if($row['parent_id']) {
			$this -> breadcrumb($row['parent_id']);
			echo " / ";
		} 
		echo "<a href=\"/?category={$row['id']}\">{$row['name']}</a>";
	}
}

class Product {
	
	// array of html codes of filter fields
	public $filter_html = array();
	// array of sql of filters
	public $filter_sql = array();
	// 
	public $fieldrealname_list = array();
	//
	public $seasonprice = array();
	
	public function __construct(DBConnection $db) {
		$this -> mysqli = $db -> getLink();
		// get option user-friendly names (e.g. "code" => "Артикул")
		$this -> fieldrealname_list = $this -> get_fieldrealname_list();
		// get price colomn
		$this -> seasonprice = $this -> get_seasonprice_array();
	}
	
	// get price colomn
	public function get_seasonprice_array() {
		$seasonprice = array();
		$query = "SELECT * FROM sh_seasonprice";
		$result = $this -> mysqli -> query($query);
		while ($row = $result -> fetch_array(MYSQL_ASSOC)) {
			$seasonprice[$row['season']] = $row['price'];
		} // while
		return $seasonprice;
	}
	
	public function get_seasonprice_price_field($id, $id_field) {
		if ($id_field == "sku_id") {
			$query = "SELECT sh_product.season AS season FROM sh_sku
						INNER JOIN sh_product ON sh_sku.product_id = sh_product.id
							WHERE sh_sku.id = '{$id}'
								LIMIT 1";
		} else { // product_id passed
			$query = "SELECT season FROM sh_product
							WHERE sh_product.id = '{$id}'
								LIMIT 1";
		}
		$result = $this -> mysqli -> query($query);
		$row = $result -> fetch_array(MYSQL_ASSOC);
		// feels like it is crazy to get var in this way
		// but I can't avoid this
		// because it is called in the child class
		// and $this -> seasonprice won't work ;(
		$db = new DBConnection;
		$CProduct = new Product($db);
		//var_dump($CProduct -> seasonprice);
		//var_dump($row['season']);
		if (array_key_exists($row['season'], $CProduct -> seasonprice))
			return $CProduct -> seasonprice[$row['season']];
		else return "price"; // price is the name of the field in sh_sku table which is echoes by default
	}
	
	function pagination($numrows) {	
		if (isset($_GET['page']))
			$current_page = $_GET['page'];
		else $current_page = 1;
		$rows_per_page = 10;
		// pages here
		echo "<div class=\"row\">\n
				<div class=\"col-md-12\">\n
				<center>
				<div class=\"btn-group\" role=\"group\" aria-label=\"group\">";
		echo "<button type=\"button\" class=\"btn btn-default\">Страница </button>\n";
		if (isset($_GET['order'])) $order_part = "&order=".$_GET['order']; else $order_part = NULL;
		if (isset($_GET['direction'])) $direction_part = "&direction=".$_GET['direction']; else $direction_part = NULL;
		if (isset($_GET['action'])) $action_part = "&action=".$_GET['action']; else $action_part = NULL;
		$pages_to_echo_around = 1; // pages amount to be printed in the line. e.g. 1 ... 111 112 113, $pages_to_echo here is 2.
		$pages_to_echo_begin_end = 3;
		$dots_printed_begin = NULL; $dots_printed_end = NULL;
		for($i = 1; $i <= ($total_pages = ceil($numrows/$rows_per_page)); $i++) {
			if (($i <= $pages_to_echo_begin_end) || 
				($i > $total_pages - $pages_to_echo_begin_end) || 
				(($i >= ($current_page - $pages_to_echo_around)) && (($current_page + $pages_to_echo_around) >= $i)) ||
				(($i == $current_page + $pages_to_echo_around + 1) && ($i == $total_pages - $pages_to_echo_begin_end)) ||
				(($i == $current_page - $pages_to_echo_around - 1) && ($i == $pages_to_echo_begin_end + 1)) ||
				($total_pages <= ($pages_to_echo_begin_end * 2 + 1)) ||
				($i == $current_page)
				) {
				if ($current_page <> $i) {
					echo "<a class=\"btn btn-default\" href=\"{$_SERVER['PHP_SELF']}?page={$i}{$order_part}{$direction_part}{$action_part}#anchor\">{$i}</a>\n";
				} else {
					echo "<button type=\"button\" class=\"btn btn-primary\">{$i}</button>\n";
				}
			} else {
				if (($i < $current_page) && !$dots_printed_begin) {
					echo "<button type=\"button\" class=\"btn\">...</button>";
					$dots_printed_begin = TRUE;
				} 
				if (($i > $current_page) && !$dots_printed_end) {
					echo "<button type=\"button\" class=\"btn\">...</button>";
					$dots_printed_end = TRUE;
				} 
			}
		}
		//echo "<button type=\"button\" class=\"btn btn-default\">из {$total_pages}</button>\n
		echo "	</center>
			</div>
		</div>\n";
	}
	
	// list some product options in cart table
	public function get_cart_skus($sku_id) {
		$stores = $this -> get_customer_store_restrictions_query_part();
		$price = $this -> get_seasonprice_price_field($sku_id, "sku_id");
		$query = "SELECT sh_product.name AS name, sh_product.code AS code, 
					sh_sku.chr AS chr, sh_sku.{$price} AS price, {$stores['header']} FROM sh_sku
						INNER JOIN sh_product ON sh_sku.product_id = sh_product.id
							WHERE sh_sku.id = '{$sku_id}'";
							//echo $query;
		$result = $this -> mysqli -> query($query);
		$row = $result -> fetch_array();
		return $row;
	}
	
	function get_customer_store_restrictions_query_part() {
		$query = "SELECT store_id FROM sh_customer_store
						WHERE customer_id = '{$_SESSION['customer_id']}'";
		$result = $this -> mysqli -> query($query);
		$row = $result -> fetch_array(MYSQL_NUM);
		$stores = array("header" => NULL, "where" => NULL);
		$store1_where = "(sh_sku.quantity1 > 0)";
		$store2_where = "(sh_sku.quantity2 > 0)";
		$store3_where = "(sh_sku.quantity3 > 0)";
		$store4_where = "(sh_sku.quantity_expected > 0)";
		$store1_header = "sh_sku.quantity1";
		$store2_header = "sh_sku.quantity2";
		$store3_header = "sh_sku.quantity3";
		$store4_header = "sh_sku.quantity_expected";
		$stores_expected = NULL;
		if (!$result -> num_rows) {
			$stores["where"] = "{$store1_where} OR {$store2_where} OR {$store3_where} OR {$store4_where}";
			$stores["header"] = "({$store1_header} + {$store2_header} + {$store3_header}) AS quantity, {$store4_header}";
		} else {
			$stores["header"] = "(";
			foreach ($row as $key => $value) {
				switch($value) {
					case "1":
						$stores["where"] .= $store1_where;
						$stores["where"] .= " OR ";
						$stores["header"] .= $store1_header;
						$stores["header"] .= " + ";
						break;
					case "2":
						$stores["where"] .= $store2_where;
						$stores["where"] .= " OR ";
						$stores["header"] .= $store2_header;
						$stores["header"] .= " + ";
						break;
					case "3":
						$stores["where"] .= $store3_where;
						$stores["where"] .= " OR ";
						$stores["header"] .= $store3_header;
						$stores["header"] .= " + ";
						break;
					case "4":
						$stores["where"] .= $store4_where;
						$stores["where"] .= " OR ";
						$stores_expected = ", {$store4_header}";
						break;
				} // switch
			} // foreach
			$stores["where"] .= "NULL";
			$stores["header"] .= "0) AS quantity".$stores_expected;
		} // else
		return $stores;
	}
		
	function products_list() { 
		//var_dump($this -> seasonprice);
		echo "<input type=\"hidden\" id=\"cart_sum\" name=\"cart_sum\" value=\"\"/>";
		// devide result onto pages 
		if (isset($_GET['page'])) {
			$page = intval($_GET['page']);
			$limit = 10;
		} else {
			$page = 1;
			$limit = 0;
		} 
		$offset_from = ($page-1)*$limit;
		$offset_to = 10;
		// end of pages
		
		// filter part
		$filter = NULL;
		foreach ($this -> filter_sql as $key => $value) {
			$filter .= $value." ";
		}
		// get store restrictions (sh_customer_store table)
		$stores = $this -> get_customer_store_restrictions_query_part();
		// get customer trademark restrictions (sh_customer_trademark table)
		$trademarks = $this -> get_customer_trademark_restrictions();
		// end of filter part
		// count pagination
		$query = "SELECT count(DISTINCT sh_product.id) AS numrows FROM sh_product 
						LEFT JOIN sh_sku ON sh_product.id = sh_sku.product_id
							WHERE ({$stores['where']}) {$trademarks}
								{$filter}";			
								//echo $query;
		$result = $this -> mysqli -> query($query);
		$row = $result -> fetch_array();
		$pagination_numrows = $row['numrows'];
		// end of count pagination
		// is there any results ?
		if(!$pagination_numrows) {
			echo "<div class=\"alert alert-danger\" role=\"alert\">Ничего не найдено</div>\n";
		} else { // there are some rows found
			// get all product codes ('cause each product has it's sub-products with different chars)
			$query = "SELECT DISTINCT sh_product.id AS id, sh_product.season AS season, sh_product.trademark AS trademark, sh_product.code AS code, sh_product.1c_code AS 1c_code
						FROM sh_product 
							LEFT JOIN sh_sku ON sh_product.id = sh_sku.product_id
								WHERE ({$stores['where']}) {$trademarks} 
								{$filter}
									ORDER BY trademark, 1c_code
										LIMIT {$offset_from},{$offset_to}";
										//echo $query;
			$result = $this -> mysqli -> query($query);
			echo "<span id=\"anchor\"></span>";
			if ($pagination_numrows) $this -> pagination($pagination_numrows);
			echo "	<div class=\"container-fluid\">";
			echo "		<div class=\"row\">
							Найдено <span class=\"badge\">{$pagination_numrows}</span> товаров 
						</div> <!-- row -->
					</div> <!-- container-fluid -->";
			echo "	<div class=\"row\">
						&nbsp;
					</div>";
			$imagesdir = "import/img/";
			$images = scandir($imagesdir);
			while ($row = $result -> fetch_array()) {
				echo "<div class=\"panel panel-primary\">
            <div class=\"panel-body\">";
				echo "	<div class=\"row\">
							<div class=\"col-md-5\">";
				// product image
				$image = $row['1c_code'].".jpg";
				//var_dump($images);
				//var_dump($image);
				if (in_array($image, $images))
					//var_dump($images);
					//exit();
						echo "<img src=\"".$imagesdir.$image."\" class=\"img-thumbnail img-responsive\" />";
					else
						echo "<img src=\"/img/no_product_image.png\" class=\"img-thumbnail img-responsive\" />";
				echo "		</div> <!-- col-md-1 -->";
				echo "		<div class=\"col-md-7\">";
				// specify the properties we need
				// get array of properties of product
				$whattoget = array("code","trademark","name","gender","material");
				$property = $this -> get_product_properties($row['id'], $whattoget);
				// echo properties
				foreach ($property as $key => $value) {
					if ($value) {
						echo "<u>{$key}</u>: {$value}";
						echo "<br />";
					}
				} // end of product properties
				// product is a set of sku's. let say sku is the concrete item on the store
				// product may have few sku's differ by characteristics: size, color, etc.
				// we pass also season to get then price colomn (it depends on the season)
				$sku = $this -> get_product_skus($row['id'], $row['season']);
				// ok, let's echo sku's we've found
				if(isset($sku) && (sizeof($sku) != 0)) {
					// get header for sku fields
					echo "<br /><table class=\"table\">\n";
					echo "	<tr>\n";
					foreach($sku[0] as $key => $value) {
						// check if there is realname for our key
						// if there is no - return key as field name
						if(array_key_exists($key, $this -> fieldrealname_list)) $field = $this -> fieldrealname_list[$key];
							else $field = $key;
						if ($key <> "id") {
							echo "		<th>{$field}</th>\n"; 
						} // if
					} // foreach end
					// show header for quantity form fields
					echo "		<th>";
					echo "			Заказ: количество / сумма";
					echo "		</th>";
					echo "	</tr>";
					// end of header
					// let start sku's list
					for ($n = 0; $n <= sizeof($sku)-1; $n++) {
						echo "	<tr>\n";
						// "-" means we have a package instead of unit
						if (strpos($sku[$n]['chr'], "-")) {
							$storageunit = " упак.";
							// if there is a package we need to get quantity of 
							// units per package and calculate storage packages amount 
							// and price
							$query_storageunit = "SELECT quantity FROM sh_storageunit
										WHERE sku_id = '{$sku[$n]['id']}'
											LIMIT 1";
							$result_storageunit = $this -> mysqli -> query($query_storageunit);
							$row_storageunit = $result_storageunit -> fetch_array();
							// redeclare value: units -> packages
							if ($row_storageunit['quantity'] > 0) { 
								$sku[$n]['quantity_expected'] = $sku[$n]['quantity_expected'] / $row_storageunit['quantity'];
								$sku[$n]['quantity'] = $sku[$n]['quantity'] / $row_storageunit['quantity'];
								$sku[$n]['price'] = $sku[$n]['price'] * $row_storageunit['quantity'];
								$unitsperpack = "<br /><small><em>({$row_storageunit['quantity']} шт. в упак.)</em></small>";
							} else $unitsperpack = NULL;
						} else {
							$storageunit = " шт.";
							$unitsperpack = NULL;
						}
						// end of storageunit package
						// field set of sku
						foreach($sku[$n] as $key => $value) {
							if ($key == "id") {
								continue;
							}
							if ($key == "price") {
								$value = number_format($sku[$n]['price'], 2, '.', ' ');
							}
							echo "		<td>{$value}";
							if (($key == "quantity" || $key == "quantity_expected") && ($value > 0)) echo "{$storageunit}{$unitsperpack}";
							echo "</td>\n";
						} // foreach
						if (!isset($sku[$n]['quantity_expected'])) $sku[$n]['quantity_expected'] = 0;
						echo "<input type=\"hidden\" id=\"{$sku[$n]['id']}_quantity\" value=\"".($sku[$n]['quantity']+$sku[$n]['quantity_expected'])."\" />";
						// end
						echo "		<td>";
						echo Cart::show_quantity_form($sku[$n]['id']);
						echo "		</td>";
						echo "	</tr>";
					} // for
					echo "</table>\n";
				} //if
				echo "		</td>";
				echo "		</tr>"; 
				echo "</table>\n";
								echo "		</div> <!-- col-md-8 col-md-offset-1 -->";
				echo "	</div> <!-- row -->";
				echo "<br />";
				echo "          </div>
          </div>";
			} 	// while
				// wow, we've got to run here all the loop types ;)
		} // if ($pagination_numrows)
		
		// echo options
		if ($pagination_numrows) $this -> pagination($pagination_numrows);
			echo "	<div class=\"row\">
						&nbsp;
					</div>";
		//echo "</div><!-- container-fluid -->";   
	}
	
	/*public function count_product_pagination() {
		$query = "SELECT COUNT(id) AS numrows FROM sh_product
						WHERE is_disabled IS NULL";
		$result = $this -> mysqli -> query($query);
		$row = $result -> fetch_array();
		return $row['numrows'];
	}*/
	
	// get option user-friendly names (e.g. "code" => "Артикул")
	function get_fieldrealname_list() {
		$fieldrealname_list = array();
		$query = "SELECT * FROM sh_fieldrealname";
		$result = $this -> mysqli -> query($query);
		while ($row = $result -> fetch_array(MYSQL_ASSOC)) {
			$fieldrealname_list[$row['field']] = $row['name'];
		}
		return $fieldrealname_list;
	}
	
	function get_customer_trademark_restrictions() {
		$trademark_restrictions = NULL; 
		$query = "SELECT sh_trademark.name AS trademark FROM sh_customer_trademark
					INNER JOIN sh_trademark
					ON sh_customer_trademark.trademark_id = sh_trademark.id
						WHERE customer_id = '{$_SESSION['customer_id']}'";
		$result = $this -> mysqli -> query($query);
		if ($numrows = $result -> num_rows) {
			$trademark_restrictions = "AND ("; 
			$n = 1;
			while ($row = $result -> fetch_array()) {
				$trademark_restrictions .= "sh_product.trademark = '{$row['trademark']}'";
				if ($n != $numrows) echo " OR "; // last row => no OR in query
				$n++;
			}
			$trademark_restrictions .= ")";
		}
		return $trademark_restrictions;
	}
	
	function get_product_properties($product_id, $wahttoget) {
		$property = array();
		//$n = 0; // array of result: all the product's properties
		$query = "SELECT * FROM sh_product
					WHERE id = '{$product_id}'";
		$result = $this -> mysqli -> query($query);
	
		while ($row = $result -> fetch_array(MYSQL_ASSOC)) {
			foreach($row as $key => $value) {
				// check which fields we are really want to take
				if (in_array($key, $wahttoget)) {
					// check if there is realname for the field
					// else - just take the name of field as is called in the table
					if(array_key_exists($key, $this -> fieldrealname_list)) $field = $this -> fieldrealname_list[$key];
						else $field = $key;
					$property[$field] = $value;
					}
				}
			}
		return $property;
	}
	
	function get_product_skus($product_id, $season) {
		$stores = $this -> get_customer_store_restrictions_query_part();
		$price = $this -> get_seasonprice_price_field($product_id, "product_id");
		$query = "SELECT id, chr, {$stores['header']}, {$price} FROM sh_sku
					WHERE product_id = '{$product_id}'
						AND ({$stores['where']})
							ORDER BY chr";
							//echo $query;
		$result = $this -> mysqli -> query($query);
		$row = $result -> fetch_all(MYSQL_ASSOC);
		return $row;
	}
	
	function construct_filter($table, $field, $type) {
		// there are the following types supported: select, input, radio, checkbox
		//if ($type) <> "input";
		// we're taking here the values of "$field"
		// if there is realname value of it in the realnames table, they will be changed to them
		//
		// filter part
		$filter = NULL;
		foreach ($this -> filter_sql as $key => $value) {
			$filter .= $value." ";
		}
		$fieldrealname = $field."_realname";
		$stores = $this -> get_customer_store_restrictions_query_part();
		$trademarks = $this -> get_customer_trademark_restrictions();
		$query = "SELECT DISTINCT {$table}.{$field} AS {$field}, sh_valuerealname.name AS {$fieldrealname}
					FROM {$table} 
						LEFT JOIN sh_valuerealname ON {$table}.{$field} = sh_valuerealname.value
						INNER JOIN sh_sku ON sh_product.id = sh_sku.product_id 
							WHERE ({$stores['where']}) {$trademarks} AND 
								{$table}.{$field} IS NOT NULL {$filter}
									ORDER BY {$fieldrealname}, {$table}.{$field}";
									//echo $query;
		$result = $this -> mysqli -> query($query);
		switch ($type) {
			//
			case "radio": 
				// get field real name from the table
				//var_dump($this);
				$field_label = array();
				foreach($field as $key => $value) {
					if(array_key_exists($value, $this -> fieldrealname_list)) $field_label[$key] = "{$this -> fieldrealname_list[$value]}";
						else $field_label[$key] = "{$value}";
				}
				$html = "<div class=\"form-group\">";
				$html .= "	<div class=\"radio\">";
				if ((isset($_POST[$field[0]]) && $_POST[$field[0]] == "") || (isset($_SESSION[$field[0]]) && $_SESSION[$field[0]] == "")) $checked = " checked";
					else $checked = NULL;
				$html .= "		<label class=\"radio-inline\"><input type=\"radio\" name=\"{$field[0]}\" value=\"\" {$checked} /> Любое</label>\n";
				foreach($field as $key => $value) {
					/* */
					// filter logic
					if (isset($_POST['filter'])) {
						if(isset($_POST[$field[0]]) && ($_POST[$field[0]] == "")) {
							$checked = " checked";
							$_SESSION[$field[0]] = $_POST[$field[0]];
							$this -> filter_sql[$field[0]] = "";
						}
						if(isset($_POST[$field[0]]) && ($_POST[$field[0]] == $value) && ($_POST[$field[0]] <> "")) {
							$checked = " checked";
							$_SESSION[$field[0]] = $_POST[$field[0]];
							if ($value == "quantity") {
								$this -> filter_sql[$field[0]] = "AND (({$table}.quantity1 > 0) OR ({$table}.quantity2 > 0) OR ({$table}.quantity3 > 0))";
							} else {
								$this -> filter_sql[$field[0]] = "AND {$table}.{$value} > 0";
							}
						} else {
							$checked = "";			
						}
					} else { // $_POST[filter]
						// flush filter on "Сбросить фильтр" button
						if (isset($_POST['flush_filter'])) {
								unset($_SESSION[$field[0]]);
						}
						if(isset($_SESSION[$field[0]]) && ($_SESSION[$field[0]] == $value) && ($_SESSION[$field[0]] <> "")) {
							$checked = " checked"; 
							if ($value == "quantity") {
								$this -> filter_sql[$field[0]] = "AND (({$table}.quantity1 > 0) OR ({$table}.quantity2 > 0) OR ({$table}.quantity3 > 0))";
							} else {
								$this -> filter_sql[$field[0]] = "AND {$table}.{$value} > 0";
							}
						}
						else {
							$checked = ""; 
						}
					} // end of $_POST[filter] else
					/* */
					
					// filter html
					$html .= "<label class=\"radio-inline\">";
					if ($value <> "") $html .= "<input type=\"radio\" name=\"{$field[0]}\" value=\"{$value}\" {$checked} /> \n";
					$html .= "{$field_label[$key]}";
					$html .= "</label>";
					// end of filter html
				} // foreach
				$html .= "	</div>"; // radio
				$html .= "</div>";
				//echo $this -> filter_sql[$field];
				$this -> filter_html[$field[0]] = $html;
				break;
				
				/* *** */
				case "select": 
				$selected = NULL;
				// get field real name from the table
				//var_dump($this);
				
				if(array_key_exists($field, $this -> fieldrealname_list)) $field_label = "{$this -> fieldrealname_list[$field]}: ";
					else $field_label = "{$field}: ";
					
				$html = "<div class=\"form-group\">";
				$html .= "<label for=\"{$field}\">{$field_label}</label>\n";
				$html .= "<select name=\"{$field}\" class=\"form-control\" id=\"{$field}\" style=\"width: 100%;\" onchange=\"if (this.selectedIndex) this.form.submit()\">\n";
				$html .= "	<option value=\"\">Все\n";
				$html .= "	</option>\n";
				if (isset($result -> num_rows)) {
					while($row = $result -> fetch_array()) {
						/* */
						// filter logic
						//echo $_POST['filter'];
						if (isset($_POST['filter'])) {
							if(isset($_POST[$field]) && ($_POST[$field] == "")) {
								$selected = " selected";
								$_SESSION[$field] = $_POST[$field];
								$this -> filter_sql[$field] = "";
							}
							if(isset($_POST[$field]) && ($_POST[$field] == $row[$field]) && ($_POST[$field] <> "")) {
								$selected = " selected";
								$_SESSION[$field] = $_POST[$field];
								$this -> filter_sql[$field] = "AND {$table}.{$field} = '{$_POST[$field]}'";
							} else {
								$selected = "";			
							}
						} else { // $_POST[filter]
							// flush filter on "Сбросить фильтр" button
							if (isset($_POST['flush_filter'])) {
								unset($_SESSION[$field]);
							}
							if(isset($_SESSION[$field]) && ($_SESSION[$field] == $row[$field]) && ($_SESSION[$field] <> "")) {
								$selected = " selected"; 
								$this -> filter_sql[$field] = "AND {$table}.{$field} = '{$_SESSION[$field]}'";
							}
							else {
								$selected = ""; 
							}
						} // end of $_POST[filter] else
						/* */
						
						// filter html
						if ($row[$field] <> "") $html .= "	<option value=\"{$row[$field]}\"{$selected}>\n";
						if (isset ($row[$fieldrealname])) $html .= "{$row[$fieldrealname]}";
							else $html .= "{$row[$field]}";
						$html .= "	</option>\n";
						// end of filter html
					}
				} // if 
				$html .= "</select>\n";
				$html .= "</div>";
				//echo $this -> filter_sql[$field];
				$this -> filter_html[$field] = $html;
				break;
				
				//// *** ////
				case "input": 
				// get field real name from the table
				//var_dump($this);
				if(array_key_exists($field, $this -> fieldrealname_list)) $field_label = "{$this -> fieldrealname_list[$field]}: ";
					else $field_label = "{$field}: ";
				$html = "<div class=\"form-group\">";
				$html = "<label for=\"{$field}\">{$field_label}</label>\n";
				// filter logic
				//echo $_POST['filter'];
				if (isset($_POST['filter'])) {
					$_POST[$field] = trim($_POST[$field]);
					if(isset($_POST[$field]) && ($_POST[$field] == "")) {
						$value = $_POST[$field];
						$_SESSION[$field] = $_POST[$field];
						$this -> filter_sql[$field] = "";
					}
					if(isset($_POST[$field]) && ($_POST[$field] <> "")) {
						$value = $_POST[$field];
						$_SESSION[$field] = $_POST[$field];
						$this -> filter_sql[$field] = "AND {$table}.{$field} LIKE '%{$_POST[$field]}%'";
					} else {
						$value = "";	
					}
				} else { // $_POST[filter]
					// flush filter on "Сбросить фильтр" button
					if (isset($_POST['flush_filter'])) {
						unset($_SESSION[$field]);
					}
					if(isset($_SESSION[$field]) && ($_SESSION[$field] <> "")) {
						$value = $_SESSION[$field]; 
						$this -> filter_sql[$field] = "AND {$table}.{$field} LIKE '%{$_SESSION[$field]}%'";
					}
					else {
						$value = ""; 
					}
				} // end of $_POST[filter] else
				/* */
				// filter html
				$html .= "<input name=\"{$field}\" class=\"form-control\" style=\"width: 100%;\" id=\"{$field}\" value=\"{$value}\" size=\"25px\" />\n";
				$html .= "</div>";
				$this -> filter_html[$field] = $html;
				break;
				
				//// *** ////
				case "checkbox": 
				// get field real name from the table
				//var_dump($this);
				if(array_key_exists($field, $this -> fieldrealname_list)) $field_label = "{$this -> fieldrealname_list[$field]}";
					else $field_label = "{$field}";
				$html = "<div class=\"form-group\" style=\"padding-right: 10px; padding-bottom: 10px; \">";
				// filter logic
				//echo $_POST['filter'];
				if (isset($_POST['filter'])) {
					if(isset($_POST[$field]) && ($_POST[$field] == "")) {
						$checked = " checked";
						$_SESSION[$field] = $_POST[$field];
						$this -> filter_sql[$field] = "";
					}
					if(isset($_POST[$field]) && ($_POST[$field] <> "")) {
						$checked = " checked";
						$_SESSION[$field] = $_POST[$field];
						$this -> filter_sql[$field] = "AND {$table}.{$field} > 0";
					} else {	
						$checked = "";
					}
				} else { // $_POST[filter]
					// flush filter on "Сбросить фильтр" button
					if (isset($_POST['flush_filter'])) {
						unset($_SESSION[$field]);
					}
					if(isset($_SESSION[$field]) && ($_SESSION[$field] <> "")) {
						$checked = " checked"; 
						$this -> filter_sql[$field] = "AND {$table}.{$field} > 0";
					}
					else {
						$checked = "";
					}
				} // end of $_POST[filter] else
				/* */
				// filter html
				$html .= "<label class=\"checkbox-inline\"><input type=\"checkbox\" name=\"{$field}\" id=\"{$field}\" value=\"1\" {$checked} /> {$field_label}</label>\n";
				$html .= "</div>";
				$this -> filter_html[$field] = $html;
				break;
		}
	}
}

class Cart extends Product {
	
	public function __construct(DBConnection $db) {
		$this -> mysqli = $db -> getLink();
	}
	
	function show() { 
		$query = "SELECT * FROM sh_cart 
					WHERE customer_id = '{$_SESSION['customer_id']}'";
		$result = $this -> mysqli -> query($query);
		//
		echo "<div class=\"panel panel-default\">
				<div class=\"panel-heading\">
					<div class=\"row\">
						<div class=\"col-md-6\">
							<h3 class=\"panel-title\">Корзина</h3>
						</div>
						<div class=\"col-md-6\">
							<p style=\"text-align: right;\">
								<button type=\"button\" class=\"btn btn-xs btn-danger\" onclick=\"flushCart('flush');\">Очистить корзину</button>
							</p>
						</div>
					</div>
				</div>
				<div class=\"panel-body\">";
		// $is_displayed_in_products_list parameter
		// means that if we call this method from the Product class to show this form in product list
		// we don't show some fields like product, code, name, etc.
		if ($result -> num_rows) {
			echo "<div id=\"cart\">";
			//echo "<div class=\"container-fluid\">";
			//echo "	<div class=\"row\">";
			//echo "		<div class=\"col-md-12\">
			echo "			<table class=\"table table-hover\">
								<tbody>";
			echo "				<thead>
									<tr>";
			$stores = $this -> get_customer_store_restrictions_query_part();
			if (strpos($stores['header'], "quantity_expected"))
				$whattoget = array("name", "code", "chr", "quantity", "quantity_expected", "price");
			else 
				$whattoget = array("name", "code", "chr", "quantity", "price");
			$this -> fieldrealname_list = $this -> get_fieldrealname_list();
			foreach($whattoget as $key) {
				// check if there is realname for our key
				// if there is no - return key as field name
				if(array_key_exists($key, $this -> fieldrealname_list)) $field = $this -> fieldrealname_list[$key];
					else $field = $key;
					if ($key == "price") echo "<th>Цена</th>\n";
						else
					echo "		<th>{$field}</th>\n";
			} // foreach
			echo "				<th>Заказ: количество <span class=\"glyphicon glyphicon-option-vertical\" aria-hidden=\"true\" style=\"text-size: 8px;\"></span> сумма</th>";
			echo   					"</tr>
								</thead>
				";
			while ($row = $result -> fetch_array()) {
				$sku = parent::get_cart_skus($row['sku_id']);
				// package or unit?
				if (strpos($sku['chr'], "-")) {
					$storageunit = " упак.";
					// if there is a package we need to get quantity of 
					// units per package and calculate storage packages amount 
					// and price
					$query_storageunit = "SELECT quantity FROM sh_storageunit
								WHERE sku_id = '{$row['sku_id']}'
									LIMIT 1";
					$result_storageunit = $this -> mysqli -> query($query_storageunit);
					$row_storageunit = $result_storageunit -> fetch_array();
					// redeclare value: units -> packages
					if ($row_storageunit['quantity'] > 0) { 
						$sku['quantity_expected'] = $sku['quantity_expected'] / $row_storageunit['quantity'];
						$sku['quantity'] = $sku['quantity'] / $row_storageunit['quantity'];
						$sku['price'] = $sku['price'] * $row_storageunit['quantity'];
						$unitsperpack = "<br /><small><em>({$row_storageunit['quantity']} шт. в упак.)</em></small>";
					} else $unitsperpack = NULL;
				} else {
					$storageunit = " шт.";
					$unitsperpack = NULL;
				}
				if ($row['quantity'] == 1) $disabled = "disabled"; else $disabled = ""; 
				// $row = cart related array
				// $sku = common product options array
				// vars we pass to show_quantity_form() method
				//
				$quantity_ordered = Cart::get_quantity($_SESSION['customer_id'], $row['sku_id']);
				echo "
							<tr>
										<td class=\"col-md-2\">{$sku['name']}</td>
										<td class=\"col-md-3\">{$sku['code']}</td>
										<td class=\"col-md-1\">{$sku['chr']}</td>
										<td class=\"col-md-1\">{$sku['quantity']}";
				if ($sku['quantity'] > 0) echo "{$storageunit}{$unitsperpack}";
				echo 					"</td>";
				if (strpos($stores['header'], "quantity_expected")) {
						echo "			<td class=\"col-md-1\">{$sku['quantity_expected']}";
					if ($sku['quantity_expected'] > 0) {
						echo "{$storageunit}{$unitsperpack}";
					}
				}
				if (!isset($sku['quantity_expected'])) $sku['quantity_expected'] = 0;
						echo "<input type=\"hidden\" id=\"{$row['sku_id']}_quantity\" value=\"".($sku['quantity']+$sku['quantity_expected'])."\" />";
				echo "</td>
										<td class=\"col-md-1\">".number_format($sku['price'], 2, '.', ' ')."</td>
										<td class=\"col-md-3\"><div id=\"div_{$row['sku_id']}\">
												<form id=\"form_{$row['sku_id']}\">
													<input type=\"hidden\" name=\"sku_id\" id=\"sku_id\" value=\"{$row['sku_id']}\" />
													".$this -> show_quantity_form($row['sku_id'])."
												</form>
											</div></td>
									 </tr>
				";
			} // while ($row = $result -> fetch_array())
			echo "								</tbody>
							</table>";
			echo "		<form class=\"form-inline\">
							<div class=\"form-group\">
								<label for=\"cart_sum\">Всего на сумму:</label>
								<input type=\"text\" class=\"form-control\" placeholder=\"{$this -> get_cart_sum()}\" id=\"cart_sum\" name=\"cart_sum\" value=\"\" disabled />
							</div>
							<div class=\"form-group\">
								<button onclick=\"confirmOrder('confirm_order');\" class=\"btn btn-default btn-primary\" type=\"button\">Оформить заказ</button>
							</div>
						</form>";
			echo "</div>"; //cart div
			echo "</div>"; //panel-body div
			echo "</div>"; //panel div
		} else { // if numrows
			echo "<div class=\"alert alert-info\" role=\"alert\">Ваша корзина пока еще пуста. <a href=\"/\" class=\"alert-link\">Перейти в каталог</a>.</div>\n";
		}
	} // Cart::show
	
	function cabinet() { 
		$query = "SELECT sh_order.id AS order_id, 
						SUM(sh_ordered.price * sh_ordered.quantity) AS order_amount, 
						sh_order.stamp AS order_date 
							FROM sh_order LEFT JOIN sh_customer ON sh_order.customer_id = sh_customer.id 
								LEFT JOIN sh_ordered ON sh_order.id = sh_ordered.order_id 
									WHERE customer_id = '{$_SESSION['customer_id']}'
										GROUP BY order_id 
											ORDER BY order_id DESC";
		$result = $this -> mysqli -> query($query);
		//
		echo "<div class=\"panel panel-default\">
				<div class=\"panel-heading\">
					<div class=\"row\">
						<div class=\"col-md-12\">
							<h3 class=\"panel-title\">Мои заказы</h3>
						</div>
					</div>
				</div>
				<div class=\"panel-body\">";
		if ($result -> num_rows) {
			echo "<div id=\"cart\">";
			echo "			<table class=\"table table-hover\">
								<tbody>";
			echo "				<thead>
									<tr>";
			/*$whattoget = array("order_id", "order_amount", "chr", "quantity", "quantity_expected", "price3");
			$this -> fieldrealname_list = $this -> get_fieldrealname_list();
			foreach($whattoget as $key) {
				// check if there is realname for our key
				// if there is no - return key as field name
				if(array_key_exists($key, $this -> fieldrealname_list)) $field = $this -> fieldrealname_list[$key];
					else $field = $key;*/
					echo "		<th>Номер заказа</th>\n";
					echo "		<th>Дата</th>\n";
					echo "		<th>Сумма</th>\n";
					echo "		<th>Состав заказа (PDF)</th>\n";
			//} // foreach
			echo   					"</tr>
								</thead>";
			// rows here
			while ($row = $result -> fetch_array()) {
				echo "
							<tr>
										<td class=\"col-md-1\">{$row['order_id']}</td>
										<td class=\"col-md-1\">".date('d.m.Y H:i', strtotime($row['order_date']))."</td>
										<td class=\"col-md-1\">{$row['order_amount']}</td>
										<td class=\"col-md-1\"><a href=\"/printforms/{$row['order_id']}.pdf\" target=\"_blank\"><span style=\"font-size:16px\" class=\"glyphicon glyphicon-download-alt btn-lg\" aria-hidden=\"true\"></span></a></td>
									 </tr>
				";
			} // while ($row = $result -> fetch_array())
			echo "								</tbody>
							</table>";
			echo "</div>"; //panel-body div
			echo "</div>"; //panel div
		} else { // if numrows
			echo "<div class=\"alert alert-info\" role=\"alert\">Список заказов пока еще пуст. <a href=\"/\" class=\"alert-link\">Перейти в каталог</a>.</div>\n";
		}
	}
	
	function get_cart_sum() {
		$query = "SELECT SUM(sh_cart.quantity * sh_cart.price) AS cart_sum 
					FROM sh_cart 
							WHERE customer_id = '{$_SESSION['customer_id']}'";
		$result = $this -> mysqli -> query($query);
		$row = $result -> fetch_array();
		return $row['cart_sum'];
	}
	
	// we show this fields several times
	// so there is method for it
	function show_quantity_form($sku_id) {
		$sku = parent::get_cart_skus($sku_id);
		$quantity = Cart::get_quantity($_SESSION['customer_id'], $sku_id);
		//
/*		if ($quantity > $sku['quantity'] + $sku['quantity_expected']) {
			$btn_type = "danger";
			$alert = "onclick=\"alert('Заказано больше доступного количества');\"";
		} else {*/
			$btn_type = "default";
			$alert = "";
		/*}*/
		// "-" means we have a package instead of unit
		if (strpos($sku['chr'], "-")) {
			// if there is a package we need to get quantity of 
			// units per package and calculate storage packages amount 
			// and price
			$query_storageunit = "SELECT quantity FROM sh_storageunit
									WHERE sku_id = '{$sku_id}'
										LIMIT 1";
			$result_storageunit = $this -> mysqli -> query($query_storageunit);
			$row_storageunit = $result_storageunit -> fetch_array();
			// redeclare price
			if ($row_storageunit['quantity'] > 0) { 
				$sku['price'] = $sku['price'] * $row_storageunit['quantity'];
			}
		}
		// end of storageunit package
		if ($quantity == 1) {
			$decrease_part =  NULL;
			$delete_part = " deleteProduct('{$sku_id}','{$sku_id}','{$quantity}','{$sku['price']}','delete'); get_cart_sum(-{$sku['price']}); ";
		} else { 
			$decrease_part = "addProduct('{$sku_id}','{$sku_id}',{$quantity},{$sku['price']},'change_quantity','decrease'); get_cart_sum(-{$sku['price']}); ";
			$delete_part = NULL;
		}
		$increase_part = "addProduct('{$sku_id}','{$sku_id}',{$quantity},{$sku['price']},'change_quantity','increase'); get_cart_sum({$sku['price']}); ";
		$elements = "
			<div id=\"{$sku_id}\">
				<form>
					<div class=\"form-group\">
									<button class=\"btn btn-default btn-sm\" type=\"button\" id=\"button_decrease_{$sku_id}\" onclick=\"{$decrease_part}{$delete_part}\">-</button>
									<button class=\"btn btn-{$btn_type} btn-sm\" type=\"button\" style=\"width: 40px;\"{$alert}>{$quantity}</button>
									<button class=\"btn btn-default btn-sm\" type=\"button\" id=\"button_decrease_{$sku_id}\" onclick=\"{$increase_part}\">+</button>	
									&nbsp;<span class=\"glyphicon glyphicon-option-vertical\" aria-hidden=\"true\"></span>
									".number_format($quantity*$sku['price'], 2, '.', ' ')."
									<input type=\"hidden\" id=\"product_sum\" name=\"product_sum\" value=\"".$quantity*$sku['price']."\" />
					</div>
				</form>
			</div>";
		// addProduct() function: addProduct(div_id, sku_id, quantity)
		//
		// why do we pass price to cart_sum() javascript?
		// it is because the part of page updates by ajax and there is 
		// displayed only the previous value of cart_sum instead of actual one...
		// guess it is not the best way
		return $elements;
	}
	
	function change_quantity($sku_id,$price) { 
		if ($_GET['direction'] == "increase") { $direction = "quantity + 1"; }
		if ($_GET['direction'] == "decrease") { $direction = "quantity - 1"; }
		// quantity = IF ({$direction} <> '0', {$direction}, quantity);
		$query = "INSERT INTO sh_cart(customer_id, sku_id, quantity, price) 
					VALUES ('{$_SESSION['customer_id']}','{$sku_id}','1',{$price})
						ON DUPLICATE KEY UPDATE
							quantity = {$direction}";
							//echo $query;
		$this -> mysqli -> query($query); 
		echo $this -> show_quantity_form($sku_id);
	}
	
	function delete($sku_id) { 
		$query = "DELETE FROM sh_cart 
						WHERE customer_id = '{$_SESSION['customer_id']}'
							AND sku_id = '{$sku_id}'";
		$this -> mysqli -> query($query);
		echo "<div id=\"{$sku_id}\">
					<button type=\"button\" class=\"btn btn-default\" disabled>Удалено из корзины!</button> <button type=\"button\" class=\"btn btn-danger\" onclick=\"addProduct('{$sku_id}','{$sku_id}','0',{$_GET['sku_price']},'change_quantity','increase'); get_cart_sum({$_GET['sku_price']});\" />Восстановить</button>
				</div>
				</div>";
	}
	
	function get_quantity($customer_id, $sku_id) {
		$query = "SELECT quantity FROM sh_cart 
					WHERE customer_id = '{$_SESSION['customer_id']}'
						AND sku_id = '{$sku_id}'
							LIMIT 1";
		$result = $this -> mysqli -> query($query);
		if ($result -> num_rows) {	
			$row = $result -> fetch_array();
			$quantity = $row['quantity'];
		} else {
			$quantity = 0;
		}
		return $quantity;
	}
	
	function confirm_order() { 
		$query = "INSERT INTO sh_order(customer_id) 
					VALUES('{$_SESSION['customer_id']}')";
		if ($this -> mysqli -> query($query)) {
			$query = "INSERT INTO sh_ordered(order_id, sku_id, quantity, price) 
						SELECT sh_order.id, sh_cart.sku_id, sh_cart.quantity, sh_cart.price FROM sh_order, sh_cart 
							WHERE sh_cart.customer_id = '{$_SESSION['customer_id']}' 
								AND sh_order.id = (SELECT MAX(id) FROM sh_order WHERE customer_id = '{$_SESSION['customer_id']}')";
			if ($this -> mysqli -> query($query)) {
				$this -> flushc();
				$order_id = $this -> generate_pdf();
				echo "<div>Благодарим Вас за заказ. Вы можете скачать печатную форму заказа в формате PDF: <a href=\"/printforms/{$order_id}.pdf\">{$order_id}.pdf</a></div>";
			}
		}
		/* */
		// notify us
		$query = "SELECT email FROM sh_notification";
		$result = $this -> mysqli -> query($query);
		if (isset($result -> num_rows)) {
			$from = "ООО \"Невский Альянс\" <office@nevsky-alliance.com>";
			$body = "Здравствуйте!

На электронной витрине http://shop.nevsky-alliance.com был создан новый заказ.

Клиент: {$_SESSION['customer_name']}
Скачать печатную форму заказа: http://shop.nevsky-alliance.com/printforms/{$order_id}.pdf
";
			$subject = "Новый заказ на сайте shop.nevsky-alliance.com";
			$headers = "Content-type: text/plain; charset=utf-8"."\r\n"."From: {$from}"."\r\n"."Reply-To: {$from}"."\r\n";
			// let's send
			while($row = $result -> fetch_array()) {
				mail($row['email'], $subject, $body, $headers);
			} // while
		} // if
		/* */
	}
	
	function generate_pdf() {
		require($_SERVER['DOCUMENT_ROOT'].'/includes/tcpdf/tcpdf.php');
		$pdf = new TCPDF('P', 'mm', 'A4', true, 'freeserif', false);
		/* set fpdf parameters */
		// set default font subsetting mode
		$pdf->setFontSubsetting(true);
		$pdf->SetFont('freeserif', '', 12);
		$pdf->SetAuthor('Nevsky Alliance Ltd.');
		$pdf->SetTitle('Печатная форма заявки на заказ');
		// убираем на всякий случай шапку и футер документа 
		$pdf->setPrintHeader(false); 
		$pdf->setPrintFooter(false); 
		// устанавливаем отступы (20 мм - слева, 25 мм - сверху, 25 мм - справа)
		$pdf->SetMargins(20, 25, 25); 
		$pdf->SetTextColor(50,60,100);
		$pdf->AddPage('L');
		$pdf->SetDisplayMode('real','default');
		/* */
		$pdf->SetXY(100,30);
		$label = "Печатная форма заявки на заказ {$_SESSION['customer_name']}";
		$pdf->Cell(160,10,$label,1,0,'C',0);
		$pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);
		$pdf->Image($_SERVER['DOCUMENT_ROOT'].'/img/logo.jpg',10,10,50,'','','http://www.nevsky-alliance.com/', false, 300);
		$pdf->SetXY(10,50);
		$pdf->SetFontSize(12);
		$query = "SELECT sh_product.name AS name, sh_product.code AS code, sh_ordered.price AS price, sh_sku.chr AS chr, sh_ordered.quantity AS quantity, sh_ordered.quantity * sh_ordered.price AS sku_sum, sh_order.id AS order_id
					FROM sh_ordered 
						INNER JOIN sh_order ON sh_ordered.order_id = sh_order.id
						INNER JOIN sh_sku ON sh_ordered.sku_id = sh_sku.id
						INNER JOIN sh_product ON sh_sku.product_id = sh_product.id
							WHERE sh_order.customer_id = '{$_SESSION['customer_id']}' 
								AND sh_order.id = (SELECT MAX(id) FROM sh_order WHERE customer_id = '{$_SESSION['customer_id']}')";
		$result = $this -> mysqli -> query($query);
		$html = "<table width=\"100%\">";
		$html .= "<tr><td width=\"30%\"><strong>НАИМЕНОВАНИЕ</strong></td><td width=\"25%\"><strong>АРТИКУЛ</strong></td><td width=\"10%\"><strong>РАЗМЕР</strong></td><td width=\"10%\"><strong>ЦЕНА (руб.)</strong></td><td width=\"10%\"><strong>КОЛ-ВО</strong></td><td width=\"15%\"><strong>СУММА (руб.)</strong></td></tr>";
		while ($row = $result -> fetch_array()) {
			$order_id = $row['order_id'];
			$html .= "<tr><td width=\"30%\">{$row['name']}</td><td width=\"25%\">{$row['code']}</td><td width=\"10%\">{$row['chr']}</td><td width=\"10%\">{$row['price']}</td><td width=\"10%\">{$row['quantity']}</td><td width=\"15%\">{$row['sku_sum']}</td></tr>";
		}
		$html .= "</table>";
		$pdf->writeHTML($html, true, false, true, false, '');
		$pdf->Output($_SERVER['DOCUMENT_ROOT'].'/printforms/'.$order_id.'.pdf','F');
		return $order_id;
	}
	
	function flushc() { 
		$query = "DELETE FROM sh_cart 
					WHERE customer_id = '{$_SESSION['customer_id']}'";
		if ($this -> mysqli -> query($query)) { return TRUE; }
			else { return FALSE; }
	}
}

$db = new DBConnection;
$CCart = new Cart($db);
$CPage = new Page($db);
$CProduct = new Product($db);
$CCategory = new Category($db);
if (isset($_GET['cart_action'])) {
	switch($_GET['cart_action']) {
		case "get_cart_sum": 
			$CCart -> get_cart_sum();
			break;
		case "change_quantity": 
			$CCart -> change_quantity($_GET['sku_id'], $_GET['price']); 
			break;
		case "delete": 
			$CCart -> delete($_GET['sku_id']);
			break;
		case "confirm_order": 
			$CCart -> confirm_order(); 
			break;
		case "flush": 
			$CCart -> flushc(); 
			echo "<div class=\"alert alert-success\" role=\"alert\">Корзина успешно очищена!</div>\n";
			break;
	} 
} else {
	$CPage -> header();
	if (isset($_GET['action'])) {
		switch($_GET['action']) {
			case "cart":
				$CCart -> show();
			break;
			case "cabinet":
				$CCart -> cabinet();
			break;
		} // switch
	} else { // no action
		$CProduct -> construct_filter("sh_product","trademark","select");
		$CProduct -> construct_filter("sh_product","season","select");
		$CProduct -> construct_filter("sh_product","name","select");
		$CProduct -> construct_filter("sh_product","gender","select");
		$CProduct -> construct_filter("sh_product","code","input");
		//$CProduct -> construct_filter("sh_sku","quantity","checkbox");
		//$CProduct -> construct_filter("sh_sku","quantity_expected","checkbox");
		$stores = $CProduct -> get_customer_store_restrictions_query_part();
			if (strpos($stores['header'], "quantity_expected"))
				$CProduct -> construct_filter("sh_sku",array("quantity","quantity_expected"),"radio");
		echo "<div class=\"panel panel-default\">
				<div class=\"panel-heading\">
					<h3 class=\"panel-title\">Используйте фильтр, чтобы отобрать нужные товары</h3>
				</div>
				<div class=\"panel-body\" style=\"background-image:url('/img/header-bg-1.png');\">";
		echo "<form class=\"form-inline\" name=\"filter\" method=\"POST\" action=\"/\">";
		echo "	<input type=\"hidden\" name=\"filter\" value=\"1\" />";
		echo "<div class=\"row\">";
		echo "	<div class=\"col-md-2\">";
		echo 		$CProduct -> filter_html['trademark'];
		echo "	</div>";
		echo "	<div class=\"col-md-4\">";
		echo 		$CProduct -> filter_html['season'];
		echo "	</div>";
		echo "	<div class=\"col-md-4\">";
		echo 		$CProduct -> filter_html['name'];
		echo "	</div>";
		echo "	<div class=\"col-md-2\">";
		echo 		$CProduct -> filter_html['gender'];
		echo "	</div>";
		echo "</div>";
		echo "<div class=\"row\" style=\"padding-top: 15px;\">";
		echo "	<div class=\"col-md-6\">";
		echo 		$CProduct -> filter_html['code'];
		//echo "	</div>";
		echo "	<div class=\"col-md-4\">";
		if (strpos($stores['header'], "quantity_expected")) {
			echo "		<label>Наличие:</label><br />";
			echo 		$CProduct -> filter_html['quantity'];
		}
		echo "	</div>";
		echo "</div>";
		echo "<div class=\"row\" style=\"padding-top: 15px;\">";
		echo "	<div class=\"col-md-1\">";
		echo "		<div class=\"form-group\">";
		echo "			<input type=\"submit\" value=\"Найти\" class=\"btn btn-primary\" />";
		echo "		</div>";
		echo "</form>";
		echo "	</div>"; // col-md
		echo "	<div class=\"col-md-1\">";
		echo "<form class=\"form-inline\" name=\"filter\" method=\"POST\" action=\"/\">";
		echo "	<input type=\"hidden\" name=\"flush_filter\" value=\"1\" />";
		echo "		<div class=\"form-group\">";
		echo "			<input type=\"submit\" value=\"Очистить фильтр\" class=\"btn btn-default\" />";
		echo "		</div>";
		echo "</form>";
		echo "	</div>"; // col-md
		echo "</div>"; // row
		echo "		</div>"; //panel-body
		echo "	</div>";	//panel
		$CProduct -> products_list();	
	}
	$CPage -> footer();
}
?>