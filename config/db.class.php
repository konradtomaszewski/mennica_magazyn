<?php
require('db.config.php');
require('KLogger.php');
require('mail.config.php');
require('phpmailer/class.phpmailer.php');

class db_conf
{
		public $KLogger;
		protected $app_log_dir = APP_LOG_DIR;
		protected $app_log_filename = APP_LOG_FILENAME;
		public $isConnected;
		public $user_id;
		public $user_login;
		public $phpMailer;
		protected $datab;
		protected $host = DB_HOST;
		protected $dbname = DB_NAME;
		protected $user = DB_USER;
		protected $pass = DB_PASSW;
		
		
        public function __construct()
		{
            @session_start();
			$this->isConnected = true;
			@$this->user_id = $_SESSION['mennica_magazyn_user_id'];
			@$this->user_login = $_SESSION['mennica_magazyn_login'];
			
            try 
			{ 
				//$this->datab = new PDO("sqlsrv:server=$this->host;Database=$this->dbname;", $this->user, $this->pass); 
				$this->datab = new PDO("mysql:host=$this->host;dbname=$this->dbname;", $this->user, $this->pass);
				$this->datab->exec("SET CHARACTER SET utf8");
				$this->datab->exec("SET NAMES utf8");
				//$this->datab->setAttribute(PDO::ATTR_AUTOCOMMIT,0);
				$this->datab->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION ); 
				$this->datab->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
				$this->phpMailer = new PHPMailer();
				$this->KLogger = new KLogger($this->app_log_dir, $this->app_log_filename, KLogger::DEBUG);
	
			} 
            catch(PDOException $e) 
			{ 
                $this->isConnected = false;
                throw new Exception($e->getMessage());
            }
        }
		
        public function Disconnect()
		{
            $this->datab = null;
            $this->isConnected = false;
        }
}
class document_name_generate extends db_conf
{
		public function create_document_new_delivery($arrival_type_id, $storage_id, $prefix_storage, $arrival_type_prefix)
		{
			try
			{
				$checkArrivalTemp = "SELECT id, document_name FROM arrival_products_temp WHERE storage_id='$storage_id' AND arrival_type_id='$arrival_type_id' ORDER BY id DESC LIMIT 1";
				$stmt = $this->datab->prepare($checkArrivalTemp); 
				$stmt->execute();
				$row =$stmt->fetch(PDO::FETCH_ASSOC);
				
				if(count($row['document_name']) > 0)
				{
					$last_document_name = $row['document_name'];
				}
				else
				{
					$year = date("Y");
					//$query = "SELECT MAX(document_name) as 'document_name' FROM arrivals WHERE arrival_type_id='$arrival_type_id' AND storage_id='$storage_id' AND create_date like '$year%' LIMIT 1";
					$query = "SELECT document_name as 'document_name' FROM arrival_document_name_cache WHERE arrival_type_id='$arrival_type_id' AND storage_id='$storage_id' AND create_date like '$year%' LIMIT 1";
					$stmt = $this->datab->prepare($query); 
					$stmt->execute();
					$row =$stmt->fetch(PDO::FETCH_ASSOC);
									
					if(empty($row['document_name']))
					{
						$last_document_name = $prefix_storage."/".$arrival_type_prefix."/0/".date("Y");
					}
					else
					{
						$last_document_name = $row['document_name'];
					}
				}
				//echo "last document-".$last_document_name;
				document_name_generate::new_document_name($last_document_name);				
			}
			catch(PDOException $e){
					throw new Exception($e->getMessage());
			}
		}
		
		public function create_document_name($arrival_type_id, $storage_id, $prefix_storage, $arrival_type_prefix)
		{
			try
			{
				$year = date("Y");
				//$query = "SELECT document_name as 'document_name' FROM arrivals WHERE id IN (SELECT MAX(id) FROM arrivals WHERE arrival_type_id='$arrival_type_id' AND storage_id='$storage_id' AND create_date like '$year%')";
				/*$query = "SELECT MAX(document_name) as 'document_name' FROM arrivals WHERE arrival_type_id='$arrival_type_id' AND storage_id='$storage_id' AND create_date like '$year%' LIMIT 1";*/
				$query = "SELECT document_name as 'document_name' FROM arrival_document_name_cache WHERE arrival_type_id='$arrival_type_id' AND storage_id='$storage_id' AND create_date like '$year%' LIMIT 1";
				$stmt = $this->datab->prepare($query); 
				$stmt->execute();
				$row =$stmt->fetch(PDO::FETCH_ASSOC);
								
				if(empty($row['document_name']))
				{
					$last_document_name = $prefix_storage."/".$arrival_type_prefix."/0/".date("Y");
				}
				else
				{
					$last_document_name = $row['document_name'];
				}
				
				document_name_generate::new_document_name($last_document_name);
			}
			catch(PDOException $e){
					throw new Exception($e->getMessage());
			}
		}

		public function new_document_name($last_document_name)
		{
			try
			{
				$document = explode("/", $last_document_name);
				
				$storage_prefix = $document[0];
				$arrival_type = $document[1];
				$number = $document[2];
				$year = date("Y");
				
				if($number <= 9)
				{
					if($number == 0)
					{
						$new_number = $number.'1';
					}
					else
					{
						$number = $number+1;
						$new_number = str_pad($number, 2, 0, STR_PAD_LEFT);
					}
				}
				else
				{
					$new_number = $number + 1;
				}
				
				
				$new_document_name = $storage_prefix."/".$arrival_type."/".$new_number."/".$year;
				
				echo $new_document_name;
			}
			catch(PDOException $e){
					throw new Exception($e->getMessage());
			}
		}
		
		public function update_arrival_document_name_cache($document_name, $arrival_type_id, $storage_id)
		{
			try
			{
				$query = "UPDATE arrival_document_name_cache SET document_name='$document_name', create_date=NOW() WHERE arrival_type_id='$arrival_type_id' AND storage_id='$storage_id'";
				$stmt = $this->datab->prepare($query); 
				$stmt->execute();
				
			}
			catch(PDOException $e)
			{
				throw new Exception($e->getMessage());
            }
		}
}
	
class dashboard extends db_conf
{
		public function storage_warn($count_items_warn)
		{
			try
			{
				$query = "SELECT arrival_items.product_id, products.automat_type, products.name, sum(arrival_items.quantity) as 'quantity', storage.name as 'storage_name' 
						FROM arrival_items 
						left join products ON products.id=arrival_items.product_id 
						left join storage ON storage.id=arrival_items.storage_id
						group by arrival_items.product_id, storage.name
						having sum(arrival_items.quantity) < '$count_items_warn'";
				$stmt = $this->datab->prepare($query); 
				$stmt->execute();
				$x=1;
				echo "<table id='sortTable'>";
				echo "<thead>";
				echo "<th>Lp.</th>";
				echo "<th>Magazyn</th>";
				echo "<th>Urządzenie</th>";
				echo "<th>Ilość</th>";
				echo "</thead>";
				echo "<tbody>";
				foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $row)
				{
					echo "<tr><td>".$x++."</td><td>".$row['storage_name']."</td><td>".$row['name']."</td><td>".$row['quantity']."</td></tr>";
				}
				echo "</tbody>";
				echo "</table>";
				
			}
			catch(PDOException $e){
					throw new Exception($e->getMessage());
			}
		}	
		
		public function custom_storage_warn($count_items_warn, $storage_id)
		{
			try
			{
				$query = "SELECT arrival_items.product_id, products.automat_type, products.name, sum(arrival_items.quantity) as 'quantity', storage.name as 'storage_name' 
						FROM arrival_items 
						left join products ON products.id=arrival_items.product_id 
						left join storage ON storage.id=arrival_items.storage_id
						WHERE arrival_items.storage_id='$storage_id'
						group by arrival_items.product_id, storage.name
						having sum(arrival_items.quantity) < '$count_items_warn'";
				$stmt = $this->datab->prepare($query); 
				$stmt->execute();
				$x=1;
				echo "<table id='sortTable'>";
				echo "<thead>";
				echo "<th>Lp.</th>";
				echo "<th>Magazyn</th>";
				echo "<th>Urządzenie</th>";
				echo "<th>Ilość</th>";
				echo "</thead>";
				echo "<tbody>";
				foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $row)
				{
					echo "<tr><td>".$x++."</td><td>".$row['storage_name']."</td><td>".$row['name']."</td><td>".$row['quantity']."</td></tr>";
				}
				echo "</tbody>";
				echo "</table>";
				
			}
			catch(PDOException $e){
					throw new Exception($e->getMessage());
			}
		}	
}	
	
class mennica_magazyn extends db_conf
{
		public function getAutomat_type()
		{
			try
			{
				$query = "SELECT * FROM automat_type";
				$stmt = $this->datab->prepare($query); 
				$stmt->execute();
				$automat =$stmt->fetchAll(PDO::FETCH_ASSOC);
				return $automat;
			}
			catch(PDOException $e){
				throw new Exception($e->getMessage());
			}
		}
		
		public function addAutomat_type($automat_type)
		{
			try
			{
				$query = "INSERT INTO automat_type SET name='$automat_type'";
				$stmt = $this->datab->prepare($query); 
				$stmt->execute();
				
			}
			catch(PDOException $e)
			{
				throw new Exception($e->getMessage());
            }
		}
		
		public function removeAutomat_type($automat_type)
		{
			try
			{
				$query = "DELETE FROM automat_type WHERE name='$automat_type'";
				$stmt = $this->datab->prepare($query); 
				$stmt->execute();
				
			}
			catch(PDOException $e)
			{
				throw new Exception($e->getMessage());
            }
		}
		
		public function getProductsList()
		{
			try
			{
				$query = "SELECT * FROM products WHERE is_active='1'";
				$stmt = $this->datab->prepare($query); 
				$stmt->execute();
				$points =$stmt->fetchAll(PDO::FETCH_ASSOC);
				return $points;
			}
			catch(PDOException $e){
				throw new Exception($e->getMessage());
			}
		}
		
		public function getProductDetails($product_id)
		{
			try
			{
				$query = "SELECT * FROM products WHERE id='$product_id' AND is_active='1'";
				$stmt = $this->datab->prepare($query); 
				$stmt->execute();
				$points =$stmt->fetchAll(PDO::FETCH_ASSOC);
				return $points;
			}
			catch(PDOException $e){
				throw new Exception($e->getMessage());
			}
		}
		
		public function new_product($product_name, $automat_type)
		{
			try
			{
				$query = "INSERT INTO products SET name='$product_name', automat_type='$automat_type'";
				$stmt = $this->datab->prepare($query); 
				$stmt->execute();
				
			}
			catch(PDOException $e)
			{
				throw new Exception($e->getMessage());
            }
		}
		
		public function update_product_name($product_id, $product_name, $automat_type)
		{
			try
			{
				$query = "UPDATE products SET name='$product_name', automat_type='$automat_type' WHERE id='$product_id'";
				$stmt = $this->datab->prepare($query); 
				$stmt->execute();
			}
			catch(PDOException $e){
				throw new Exception($e->getMessage());
			}
		}
		
		public function getStorageList()
		{
			try
			{
				$query = "SELECT * FROM storage";
				$stmt = $this->datab->prepare($query); 
				$stmt->execute();
				$storage =$stmt->fetchAll(PDO::FETCH_ASSOC);
				return $storage;
			}
			catch(PDOException $e){
				throw new Exception($e->getMessage());
			}
		}
		
		public function warehouses($storage_id)
		{
			try
			{
				$query = "SELECT 
						arrival_items.product_id, 
						products.automat_type as 'automat_type', 
						products.name as 'product_name', 
						sum(arrival_items.quantity) as 'product_quantity' 
						FROM arrival_items 
						left join products ON products.id=arrival_items.product_id 
						WHERE arrival_items.storage_id='$storage_id' 
						AND arrival_items.arrival_type_id != '8'
						group by arrival_items.product_id";
				$stmt = $this->datab->prepare($query); 
				$stmt->execute();
				
				echo "<table id='Table'>";
				echo "<thead>";
				echo "<th>Grupa automatów</th>";
				echo "<th>Nazwa sprzętu</th>";
				echo "<th>Ilosc (szt.)</th>";
				echo "</thead>";
				echo "<tbody>";
				foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
					echo "<tr>
							<td>".$row['automat_type']."</td>
							<td>".$row['product_name']."</td>
							<td>".$row['product_quantity']."</td>
						</tr>";
				}
				echo "</tbody>";
				echo "</table>";
						
			}
			catch(PDOException $e){
				throw new Exception($e->getMessage());
			}
		}
		
		public function report_warehouses($storage_id, &$lista)
		{
			try
			{
				$query = "SELECT 
						arrival_items.product_id, 
						products.automat_type as 'automat_type', 
						products.name as 'product_name', 
						sum(arrival_items.quantity) as 'product_quantity' 
						FROM arrival_items 
						left join products ON products.id=arrival_items.product_id 
						WHERE arrival_items.storage_id='$storage_id' 
						AND arrival_items.arrival_type_id != '8'
						group by arrival_items.product_id ORDER BY products.name ASC";
				$stmt = $this->datab->prepare($query); 
				$stmt->execute();
				
				foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) 
				{
					if(empty($lista[$row['product_name']]))
					{
						$lista[$row['product_name']]=$row['product_quantity'];

					}
					else
					{
						$lista[$row['product_name']]=$row['product_quantity']+ $lista[$row['product_name']];
					}
				}
				
						
			}
			catch(PDOException $e){
				throw new Exception($e->getMessage());
			}
		}
		
		public function report_serviceman_devices($storage_id, &$lista)
		{
			try
			{
				$query = "SELECT 
						products.name as 'product_name',
						SUM(service_request.quantity) as 'quantity',
						products.id as 'product_id',
						MAX(service_request.service_status_id) as 'service_status_id'
						FROM service_request 
						LEFT JOIN products ON products.id=service_request.product_id
						WHERE service_request.storage_id='$storage_id' AND service_request.service_status_id in (1,2,3)  
						AND service_request.arrival_id NOT IN (SELECT id FROM arrivals WHERE arrival_type_id=8) 
						GROUP BY products.name ORDER BY products.name ASC";
				$stmt = $this->datab->prepare($query); 
				$stmt->execute();
				
				foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) 
				{
					if($row['quantity'] > 0)
					{
						if(empty($lista[$row['product_name']]))
						{
							$lista[$row['product_name']]=$row['quantity'];
						}
						else
						{
							$lista[$row['product_name']]=$row['quantity']+ $lista[$row['product_name']];
						}
					}
				}
			}
			catch(PDOException $e){
				throw new Exception($e->getMessage());
			}
		}
		
		public function report_getDamagedDevices($storage_id, &$lista)
		{
			try
			{
				$query = "SELECT 
						damaged_devices.id as 'damaged_devices_id',
						damaged_devices.service_request_id as 'service_request_id',
						products.name as 'product_name',
						damaged_devices.quantity as 'quantity',
						damaged_devices.change_status_datetime as 'datetime',
						damaged_devices.product_id as 'product_id',
						damaged_devices.sn as 'sn',
						damaged_devices.storage_id as 'storage_id',
						damaged_devices.service_user_id as 'service_user_id',
						users.user_name as 'service_user_name',
						service_request.bus_number as 'bus_number',
						service_request.automat_number as 'automat_number'
						FROM damaged_devices
						LEFT JOIN products ON products.id=damaged_devices.product_id
						left JOIN service_request ON service_request.id=damaged_devices.service_request_id
						LEFT JOIN users ON users.id=damaged_devices.service_user_id
						WHERE damaged_devices.storage_id='$storage_id' AND damaged_devices.damaged_devices_status_id='0'
						ORDER BY products.name ASC";
				$stmt = $this->datab->prepare($query); 
				$stmt->execute();
				//$damagedDevices =$stmt->fetchAll(PDO::FETCH_ASSOC);
				//return $damagedDevices;
				
				foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) 
				{
					if($row['quantity'] > 0)
					{
						if(empty($lista[$row['product_name']]))
						{
							$lista[$row['product_name']]=$row['quantity'];
						}
						else
						{
							$lista[$row['product_name']]=$row['quantity']+ $lista[$row['product_name']];
						}
					}
				}
			}
			catch(PDOException $e){
				throw new Exception($e->getMessage());
			}
		}
		
		public function waiting_for_accept_devices_service($storage_id)
		{
			try
			{
				$query = "SELECT id FROM arrivals WHERE arrival_type_id='5' AND mennica_service_accept='0' AND storage_id='$storage_id'";
				$stmt = $this->datab->prepare($query); 
				$stmt->execute();
				$r =$stmt->fetchAll(PDO::FETCH_ASSOC);
				return $r;
			}
			catch(PDOException $e){
				throw new Exception($e->getMessage());
			}
		}
		
		public function report_DamagedDevices_items($broken_devices_arrival_id, &$lista)
		{
			try
			{
				$query = "SELECT 
						storage.name as 'storage_name',
						broken_product_details.product_id as 'product_id',
						broken_product_details.arrival_id as 'arrival_id',
						broken_product_details.storage_id as 'storage_id',
						count(broken_product_details.id) as 'quantity', 
						products.name as 'product_name', 
						products.automat_type as 'automat_type',
						broken_product_details.sn as 'broken_product_sn', 
						mennica_services.name as 'mennica_service_name'
						FROM broken_product_details 
						left join products ON products.id=broken_product_details.product_id 
						left join mennica_services ON mennica_services.id=broken_product_details.mennica_service_id
						left join storage ON storage.id=broken_product_details.storage_id
						WHERE broken_product_details.arrival_id = '$broken_devices_arrival_id'
						group by broken_product_details.product_id, broken_product_details.sn
						order by products.name ASC";
				$stmt = $this->datab->prepare($query); 
				$stmt->execute();
				//$pom =$stmt->fetchAll(PDO::FETCH_ASSOC);
				//return $pom;
				foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) 
				{
					if($row['quantity'] > 0)
					{
						if(empty($lista[$row['product_name']]))
						{
							$lista[$row['product_name']]=$row['quantity'];
						}
						else
						{
							$lista[$row['product_name']]=$row['quantity']+ $lista[$row['product_name']];
						}
					}
				}
			}
			catch(PDOException $e){
				throw new Exception($e->getMessage());
			}
		}
		
		public function arrivalProductsTemp($arrival_type_id, $item_name, $item_sn, $item_quantity, $item_product_id, $document_name, $storage_id)
		{
			try
			{
				$query = "INSERT INTO arrival_products_temp SET arrival_type_id='$arrival_type_id', item_name='$item_name', item_sn='$item_sn', item_quantity='$item_quantity', item_product_id='$item_product_id', document_name='$document_name', storage_id='$storage_id', create_user_id=$this->user_id, create_date=NOW()";
				$stmt = $this->datab->prepare($query); 
				$stmt->execute();
				$this->KLogger->LogINFO("Typ dokumentu: [Dostawa części (do zatwierdzenia)], Nazwa dokumentu:  [".$document_name."],  Nazwa urządzenia: [".$item_name."], ID urządzenia: [".$item_product_id."], Ilość: [".$item_quantity."]");
				
			}
			catch(PDOException $e)
			{
				throw new Exception($e->getMessage());
            }
		}
		
		public function verifyProductId_arrivalTemp($document_name)
		{
			try
			{
				$query = "SELECT item_name, item_product_id FROM arrival_products_temp WHERE document_name like '$document_name'";
				$stmt = $this->datab->prepare($query); 
				$stmt->execute();
				foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) 
				{
					mennica_magazyn::verifyProductId($row['item_name'], $row['item_product_id'], $document_name);
				}
			}
			catch(PDOException $e)
			{
				throw new Exception($e->getMessage());
            }
		}
		
		public function verifyProductId($item_name, $item_product_id, $document_name)
		{
			try
			{
				$query = "SELECT id FROM products WHERE name like '$item_name'";
				$stmt = $this->datab->prepare($query); 
				$stmt->execute();
				foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) 
				{
					$id = $row['id'];
					if($id != $item_product_id)
					{
						$query = "UPDATE arrival_products_temp SET item_product_id='$id' WHERE item_name='$item_name' AND document_name like '$document_name'";
						$stmt = $this->datab->prepare($query); 
						$stmt->execute();
						$this->KLogger->LogINFO("Wykryto niepoprawne dane dla urządzenia: [".$item_name."] w dostawie do zatwierdzenia: [".$document_name."]");
						$this->KLogger->LogINFO("Poprawiono UrządzenieID: [".$item_name. "] z błędnego UrządzenieID: [".$item_product_id."] na poprawne UrządzenieId: [".$id."] w dostawie do zatwierdzenia: [".$document_name."]");
					}
				}
				//$this->KLogger->LogINFO("UrządzenieID jest poprawne dla: [".$item_name."] w dostawie do zatwierdzenia");
			}
			catch(PDOException $e)
			{
				throw new Exception($e->getMessage());
            }
			
		}
		
		public function getAllDeliveryList($storage_id)
		{
			try
			{
				$query = "SELECT document_name FROM arrivals WHERE storage_id='$storage_id' AND arrival_type_id='1' GROUP BY document_name ORDER BY document_name DESC";
				$stmt = $this->datab->prepare($query); 
				$stmt->execute();
				$pom =$stmt->fetchAll(PDO::FETCH_ASSOC);
				return $pom;				
			}
			catch(PDOException $e){
				throw new Exception($e->getMessage());
			}
		}
		
		public function activateProduct_definitions($product_id, $is_active)
		{
			try
			{
				$query = "UPDATE products SET is_active='$is_active' WHERE id='$product_id'";
				$stmt = $this->datab->prepare($query); 
				$stmt->execute();
			}
			catch(PDOException $e){
				throw new Exception($e->getMessage());
			}
		}
		
		public function create_delivery_report($date_of, $date_to)
		{
			try
			{
				$query = "SELECT arrivals.id as 'id', arrivals.document_name as 'document_name', storage.name as 'storage_name', arrivals.create_date as 'create_date' FROM arrivals
						left join storage ON storage.id=arrivals.storage_id
						WHERE create_date between '$date_of' AND '$date_to'
						AND arrival_type_id='1' ORDER BY arrivals.document_name DESC";
				$stmt = $this->datab->prepare($query); 
				$stmt->execute();
				$pom =$stmt->fetchAll(PDO::FETCH_ASSOC);
				return $pom;			
			}
			catch(PDOException $e){
				throw new Exception($e->getMessage());
			}
		}
		
		public function report_broken_devices($date_of, $date_to)
		{
			try
			{
				$query = "SELECT 
						arrivals.document_name as 'document_name',
						arrivals.id as 'arrival_id',
						arrivals.create_date as 'create_date',
						storage.name as 'storage_name'
						FROM broken_arrival_items
						LEFT JOIN arrivals ON arrivals.id=broken_arrival_items.arrival_id
						LEFT JOIN storage ON storage.id=broken_arrival_items.storage_id
						WHERE create_date between '$date_of' AND '$date_to' AND broken_arrival_items.arrival_type_id='6'
						GROUP BY arrivals.id";
				$stmt = $this->datab->prepare($query); 
				$stmt->execute();
				$pom =$stmt->fetchAll(PDO::FETCH_ASSOC);
				return $pom;			
			}
			catch(PDOException $e){
				throw new Exception($e->getMessage());
			}
		}
		
		public function correct_arrival_delivery_temp($item_product_id, $item_sn, $item_quantity, $document_name, $storage_id)
		{
			try
			{
				$query = "UPDATE arrival_products_temp SET item_sn='$item_sn', item_quantity='$item_quantity' WHERE document_name='$document_name' AND storage_id='$storage_id' AND item_product_id='$item_product_id'";
				$stmt = $this->datab->prepare($query); 
				$stmt->execute();
			}
			catch(PDOException $e){
				throw new Exception($e->getMessage());
			}
		}
		
		public function getBrokenDevices_arrivalList()
		{
			try
			{
				$query = "SELECT * FROM arrivals WHERE arrival_type_id='5' AND ID NOT IN (SELECT arrival_id FROM broken_arrival_items WHERE mennica_service_id='2') AND mennica_service_accept='0'";
				$stmt = $this->datab->prepare($query); 
				$stmt->execute();
				$pom =$stmt->fetchAll(PDO::FETCH_ASSOC);
				return $pom;
			}
			catch(PDOException $e){
				throw new Exception($e->getMessage());
			}
		}
		
		public function getBrokenDevices_List()
		{
			try
			{
				$query = "SELECT * FROM arrivals WHERE arrival_type_id='5'";
				$stmt = $this->datab->prepare($query); 
				$stmt->execute();
				$pom =$stmt->fetchAll(PDO::FETCH_ASSOC);
				return $pom;
			}
			catch(PDOException $e){
				throw new Exception($e->getMessage());
			}
		}
		
		public function getDamagedDevices_items($broken_devices_arrival_id)
		{
			try
			{
				$query = "SELECT 
						storage.name as 'storage_name',
						broken_product_details.product_id as 'product_id',
						broken_product_details.arrival_id as 'arrival_id',
						broken_product_details.storage_id as 'storage_id',
						count(broken_product_details.id) as 'quantity', 
						products.name as 'product_name', 
						products.automat_type as 'automat_type',
						broken_product_details.sn as 'broken_product_sn', 
						mennica_services.name as 'mennica_service_name'
						FROM broken_product_details 
						left join products ON products.id=broken_product_details.product_id 
						left join mennica_services ON mennica_services.id=broken_product_details.mennica_service_id
						left join storage ON storage.id=broken_product_details.storage_id
						WHERE broken_product_details.arrival_id = '$broken_devices_arrival_id'
						group by broken_product_details.product_id, broken_product_details.sn
						order by products.name ASC";
				$stmt = $this->datab->prepare($query); 
				$stmt->execute();
				$pom =$stmt->fetchAll(PDO::FETCH_ASSOC);
				return $pom;
			}
			catch(PDOException $e){
				throw new Exception($e->getMessage());
			}
		}
		
		public function getDevices_items($arrival_id)
		{
			try
			{
				$query = "SELECT 
						products.name as 'product_name', 
						products.automat_type as 'automat_type', 
						SUM(arrival_items.quantity) as 'quantity', 
						arrival_items.quantity as 'quantity2', 
						storage.id as 'storage_id',
						storage.name as 'storage_name',
						arrival_items.arrival_id as 'arrival_id',
						arrival_items.product_id as 'product_id'
						FROM arrival_items 
						LEFT JOIN products ON products.id=arrival_items.product_id 
						LEFT JOIN storage ON storage.id=arrival_items.storage_id 
						WHERE arrival_id='$arrival_id' GROUP BY products.name";
				$stmt = $this->datab->prepare($query); 
				$stmt->execute();
				$pom =$stmt->fetchAll(PDO::FETCH_ASSOC);
				return $pom;
			}
			catch(PDOException $e){
				throw new Exception($e->getMessage());
			}
		}
		
		//public function accept_broken_arrival_items($product_id, $quantity, $arrival_id, $arrival_type_id, $storage_id)
		public function accept_broken_arrival_items($arrival_id, $arrival_type_id)
		{
			try
			{
				//$query = "INSERT INTO broken_arrival_items SET product_id='$product_id', quantity='$quantity', arrival_id='$arrival_id', arrival_type_id='$arrival_type_id', storage_id='$storage_id'";
				$query = "UPDATE broken_arrival_items SET arrival_type_id='$arrival_type_id' WHERE arrival_id='$arrival_id'";
				$stmt = $this->datab->prepare($query); 
				$stmt->execute();
			}
			catch(PDOException $e)
			{
				throw new Exception($e->getMessage());
            }
		}
		
		public function accept_arrival_broken_devices($arrival_id)
		{
			try
			{
				$query = "UPDATE arrivals SET mennica_service_accept='1', accept_user_id=$this->user_id, accept_date=NOW() WHERE id='$arrival_id'";
				$stmt = $this->datab->prepare($query); 
				$stmt->execute();
			}
			catch(PDOException $e)
			{
				throw new Exception($e->getMessage());
            }
		}
		
		public function accept_broken_product_details($product_id, $arrival_id, $storage_id, $product_status_id)
		{
			try
			{
				$query = "INSERT INTO broken_product_details SET product_id='$product_id', arrival_id='$arrival_id', storage_id='$storage_id', product_status_id='$product_status_id', product_status_change_datetime=NOW()";
				$stmt = $this->datab->prepare($query); 
				$stmt->execute();
			}
			catch(PDOException $e)
			{
				throw new Exception($e->getMessage());
            }
		}
}

class vectorsoft_magazyn extends db_conf
{
		public function utilize_paper($storage_id)
		{
			try
			{
				$query = "UPDATE damaged_devices SET damaged_devices_status_id='5' WHERE product_id='149' OR product_id='128' OR product_id='157' and storage_id='$storage_id'";
				$stmt = $this->datab->prepare($query); 
				$stmt->execute();
			}
			catch(PDOException $e){
				throw new Exception($e->getMessage());
			}
		}
		
		public function getProduct_id($product_name)
		{
			try
			{
				$query = "SELECT id FROM products WHERE name like '$product_name'";
				$stmt = $this->datab->prepare($query); 
				$stmt->execute();
				$product_id =$stmt->fetch(PDO::FETCH_ASSOC);
				return $product_id['id'];
			}
			catch(PDOException $e){
				throw new Exception($e->getMessage());
			}
		}
		
		public function getDeliveryToAcceptList($storage_id)
		{
			try
			{
				$query = "SELECT document_name FROM arrival_products_temp WHERE storage_id='$storage_id' GROUP BY document_name";
				$stmt = $this->datab->prepare($query); 
				$stmt->execute();
				$pom =$stmt->fetchAll(PDO::FETCH_ASSOC);
				return $pom;				
			}
			catch(PDOException $e){
				throw new Exception($e->getMessage());
			}
		}
		
		public function getDeliveryList($storage_id)
		{
			try
			{
				$query = "SELECT document_name FROM arrivals WHERE storage_id='$storage_id' AND arrival_type_id NOT IN (5,666) GROUP BY document_name";
				$stmt = $this->datab->prepare($query); 
				$stmt->execute();
				$pom =$stmt->fetchAll(PDO::FETCH_ASSOC);
				return $pom;				
			}
			catch(PDOException $e){
				throw new Exception($e->getMessage());
			}
		}
		
		public function getBrokenDeliveryList($storage_id)
		{
			try
			{
				$query = "SELECT document_name FROM arrivals WHERE storage_id='$storage_id' AND arrival_type_id='5' GROUP BY document_name";
				$stmt = $this->datab->prepare($query); 
				$stmt->execute();
				$pom =$stmt->fetchAll(PDO::FETCH_ASSOC);
				return $pom;				
			}
			catch(PDOException $e){
				throw new Exception($e->getMessage());
			}
		}
		
		public function getTempDeliveryItems($document_name)
		{
			try
			{
				$query = "SELECT * FROM arrival_products_temp WHERE document_name like '$document_name'";
				$stmt = $this->datab->prepare($query); 
				$stmt->execute();

				echo "<table id='table'>";
				echo "<thead>";
				echo "<th>Nazwa urządzenia</th>";
				echo "<th>Numer seryjny</th>";
				echo "<th>Ilosc</th>";
				//echo "<th>Dokument dostawy</th>";
				echo "</thead>";
				echo "<tbody>";
				foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
					echo "<tr>
							<td>".$row['item_name']."</td>
							<td><input type='text' name='item_sn[]' class='item_sn' value='".$row['item_sn']."' disabled='disabled'</td>
							<td><input type='number' name='item_quantity[]' class='item_quantity' value='".$row['item_quantity']."' disabled='disabled'></td>
							<!--<td>".$row['document_name']."</td>-->
						</tr>";
					echo "<input type='hidden' name='arrival_type_id' value='".$row['arrival_type_id']."'>";	
					echo "<input type='hidden' name='item_product_id[]' value='".$row['item_product_id']."'>";	
					echo "<input type='hidden' name='item_name[]' value='".$row['item_name']."'>";	
					echo "<input type='hidden' name='document_name' value='".$row['document_name']."'>";	
					echo "<input type='hidden' name='storage_id' value='".$row['storage_id']."'>";	
					echo "<input type='hidden' name='create_user_id' value='".$row['create_user_id']."'>";	
					echo "<input type='hidden' name='create_date' value='".$row['create_date']."'>";	
				}
				echo "</tbody>";
				echo "</table>";
				
			}
			catch(PDOException $e){
				throw new Exception($e->getMessage());
			}
		}
		public function create_arrival($arrival_type_id, $document_name, $storage_id, $create_user_id, $create_date)
		{
			try
			{
				//insert document_name to arrivals and get last_id
				$query = "INSERT INTO arrivals SET arrival_type_id='$arrival_type_id', document_name='$document_name', storage_id='$storage_id', create_user_id='$create_user_id', create_date='$create_date', accept_user_id=$this->user_id, accept_date=NOW()";
				$stmt = $this->datab->prepare($query); 
				$stmt->execute();
				$arrival_id = $this->datab->lastInsertId();
				return $arrival_id;
				
				//document_name_generate::update_arrival_document_name_cache($document_name, $arrival_type_id, $storage_id);
			}
			catch(PDOException $e){
				throw new Exception($e->getMessage());
			}
		}
		
		public function update_arrival_release_user($arrival_id, $release_user_id)
		{
			try
			{
				$query = "UPDATE arrivals SET release_user_id='$release_user_id' WHERE id='$arrival_id'";
				$stmt = $this->datab->prepare($query); 
				$stmt->execute();
				$arrival_id = $this->datab->lastInsertId();
				return $arrival_id;
				
			}
			catch(PDOException $e){
				throw new Exception($e->getMessage());
			}
		}
		
		/*public function create_broken_arrival($arrival_type_id, $document_name, $storage_id, $create_user_id, $create_date)
		{
			try
			{
				//insert document_name to broken arrivals and get last_id
				$query = "INSERT INTO broken_arrivals SET arrival_type_id='$arrival_type_id', document_name='$document_name', storage_id='$storage_id', create_user_id='$create_user_id', create_date='$create_date', accept_user_id=$this->user_id, accept_date=NOW()";
				$stmt = $this->datab->prepare($query); 
				$stmt->execute();
				$broken_arrival_id = $this->datab->lastInsertId();
				return $broken_arrival_id;
				
			}
			catch(PDOException $e){
				throw new Exception($e->getMessage());
			}
		}*/
		
		public function create_release_arrival($arrival_type_id, $document_name, $storage_id, $release_user_id)
		{
			try
			{
				//insert document_name to arrivals and get last_id
				$query = "INSERT INTO arrivals SET arrival_type_id='$arrival_type_id', document_name='$document_name', storage_id='$storage_id', create_user_id='$this->user_id', create_date=NOW(), release_user_id='$release_user_id', release_date=NOW()";
				$stmt = $this->datab->prepare($query); 
				$stmt->execute();
				$release_arrival_id = $this->datab->lastInsertId();
				return $release_arrival_id;
				
				//document_name_generate::update_arrival_document_name_cache($document_name, $arrival_type_id, $storage_id);
			
			}
			catch(PDOException $e){
				throw new Exception($e->getMessage());
			}
		}
		
		public function accept_delivery_temp($item_name, $item_sn, $item_quantity, $item_product_id, $document_name, $storage_id, $arrival_id)
		{
			try
			{
				$query = "INSERT INTO product_details SET product_id='$item_product_id', sn='$item_sn', arrival_id='$arrival_id', storage_id='$storage_id', product_status_id='1', product_status_change_datetime=NOW()";
				$stmt = $this->datab->prepare($query); 
				$stmt->execute();
			}
			catch(PDOException $e){
				throw new Exception($e->getMessage());
			}
			
		}
		
		public function broken_accept_delivery_temp($item_name, $item_sn, $item_quantity, $item_product_id, $document_name, $storage_id, $arrival_id, $mennica_service_id)
		{
			try
			{
				for($i=0; $i<$item_quantity; $i++)
				{
					$query = "INSERT INTO broken_product_details SET product_id='$item_product_id', sn='$item_sn', arrival_id='$arrival_id', storage_id='$storage_id', product_status_id='1', mennica_service_id='$mennica_service_id', product_status_change_datetime=NOW()";
					$stmt = $this->datab->prepare($query); 
					$stmt->execute();
				}
			}
			catch(PDOException $e){
				throw new Exception($e->getMessage());
			}
			
		}
		
		public function release_delivery($arrival_type_id, $item_name, $item_sn, $item_quantity, $item_product_id, $document_name, $storage_id, $arrival_id)
		{
			try
			{
				$query = "INSERT INTO product_details SET product_id='$item_product_id', sn='$item_sn', arrival_id='$arrival_id', storage_id='$storage_id', product_status_id='2', product_status_change_datetime=NOW()";
				$stmt = $this->datab->prepare($query); 
				$stmt->execute();
				
				vectorsoft_magazyn::insertItemsTo_arrival_itmes($arrival_type_id, $arrival_id, $storage_id, $item_product_id, $item_quantity, $document_name, $item_name);
			}
			catch(PDOException $e){
				throw new Exception($e->getMessage());
			}
			
		}
		
		public function update_arrival_delivery_temp($item_product_id, $item_sn, $item_quantity, $document_name, $storage_id)
		{
			try
			{
				$query = "UPDATE arrival_products_temp SET item_sn='$item_sn', item_quantity='$item_quantity' WHERE document_name='$document_name' AND storage_id='$storage_id' AND item_product_id='$item_product_id'";
				$stmt = $this->datab->prepare($query); 
				$stmt->execute();
			}
			catch(PDOException $e){
				throw new Exception($e->getMessage());
			}
			
		}
		
		public function getItemsFrom_deliveryTemp($arrival_type_id, $arrival_id, $document_name, $storage_id, $item_name)
		{
			try
			{
				$query = "SELECT item_product_id, sum(item_quantity) as 'item_quantity' FROM arrival_products_temp WHERE document_name='$document_name' AND storage_id='$storage_id' GROUP BY item_product_id";
				$stmt = $this->datab->prepare($query); 
				$stmt->execute();
				foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $row)
				{
					vectorsoft_magazyn::insertItemsTo_arrival_itmes($arrival_type_id, $arrival_id, $storage_id, $row['item_product_id'], $row['item_quantity'], $document_name, $item_name);
				}
			}
			catch(PDOException $e){
				throw new Exception($e->getMessage());
			}
		}
		
		public function getDelivery_details($document_name, $storage_id)
		{
			try
			{
				$query = "SELECT 
							arrival_items.product_id, 
							arrival_types.name as 'arrival_type',
							products.automat_type, 
							products.name as 'product_name', 
							sum(arrival_items.quantity) as 'product_quantity',
							arrivals.document_name,
							users.user_name as 'create_user',
							arrivals.create_date,
							u2.user_name as 'accept_user',
							u3.user_name as 'release_user',
							arrivals.accept_date,
							arrivals.release_date
						FROM arrival_items 
							left join products ON products.id=arrival_items.product_id 
							left join arrivals ON arrivals.id=arrival_items.arrival_id
							left join arrival_types ON arrival_types.id=arrival_items.arrival_type_id
							left join users on users.id=arrivals.create_user_id
							left join users u2 on u2.id=arrivals.accept_user_id
							left join users u3 on u3.id=arrivals.release_user_id
						WHERE arrival_items.storage_id='$storage_id' 
							AND arrivals.document_name='$document_name'
						group by arrival_items.product_id";
				
				$stmt = $this->datab->prepare($query); 
				$stmt->execute();
				$points =$stmt->fetchAll(PDO::FETCH_ASSOC);
				return $points;
			}
			catch(PDOException $e){
				throw new Exception($e->getMessage());
			}
			
		}
		
		public function getBrokenDelivery_details($document_name, $storage_id)
		{
			try
			{
				$query = "SELECT 
							broken_arrival_items.product_id, 
							arrival_types.name as 'arrival_type',
							products.automat_type, 
							products.name as 'product_name', 
							sum(broken_arrival_items.quantity) as 'product_quantity',
							arrivals.document_name,
							users.user_name as 'create_user',
							arrivals.create_date,
							u2.user_name as 'accept_user',
							u3.user_name as 'release_user',
							arrivals.accept_date,
							arrivals.release_date
						FROM broken_arrival_items 
							left join products ON products.id=broken_arrival_items.product_id 
							left join arrivals ON arrivals.id=broken_arrival_items.arrival_id
							left join arrival_types ON arrival_types.id=broken_arrival_items.arrival_type_id
							left join users on users.id=arrivals.create_user_id
							left join users u2 on u2.id=arrivals.accept_user_id
							left join users u3 on u3.id=arrivals.release_user_id
						WHERE broken_arrival_items.storage_id='$storage_id' 
							AND arrivals.document_name='$document_name'
						group by broken_arrival_items.product_id";
				
				$stmt = $this->datab->prepare($query); 
				$stmt->execute();
				$points =$stmt->fetchAll(PDO::FETCH_ASSOC);
				return $points;
			}
			catch(PDOException $e){
				throw new Exception($e->getMessage());
			}
			
		}
		
		public function insertItemsTo_arrival_itmes($arrival_type_id, $arrival_id, $storage_id, $product_id, $product_id_quantity, $document_name, $item_name)
		{
			try
			{
				$query = "INSERT INTO arrival_items SET product_id='$product_id', quantity='$product_id_quantity', arrival_id='$arrival_id', arrival_type_id='$arrival_type_id', storage_id='$storage_id'";	
				$stmt = $this->datab->prepare($query); 
				$stmt->execute();
				if($arrival_type_id == '1')
				{
					$this->KLogger->LogINFO("Typ dokumentu: [Dostawa części (zatwierdzona)], Nazwa dokumentu:  [".$document_name."],  Nazwa urządzenia: [".$item_name."], ID urządzenia: [".$product_id."], Ilość: [".$product_id_quantity."]");
				}
			}
			catch(PDOException $e){
				throw new Exception($e->getMessage());
			}
		}
		
		public function broken_insertItemsTo_arrival_itmes($arrival_type_id, $arrival_id, $storage_id, $product_id, $product_id_quantity, $mennica_service_id)
		{
			try
			{
				$query = "INSERT INTO broken_arrival_items SET product_id='$product_id', quantity='$product_id_quantity', arrival_id='$arrival_id', arrival_type_id='$arrival_type_id', storage_id='$storage_id', mennica_service_id='$mennica_service_id'";	
				$stmt = $this->datab->prepare($query); 
				$stmt->execute();
				
			}
			catch(PDOException $e){
				throw new Exception($e->getMessage());
			}
		}
		
		public function delete_deliveryTemp($document_name, $storage_id)
		{
			try
			{
				$query = "DELETE FROM arrival_products_temp WHERE document_name='$document_name' AND storage_id='$storage_id'";	
				$stmt = $this->datab->prepare($query); 
				$stmt->execute();
				
			}
			catch(PDOException $e){
				throw new Exception($e->getMessage());
			}
		}
		
		public function getProductsList($storage_id)
		{
			try
			{
				$query = "SELECT 
							arrival_items.product_id as 'product_id', 
							products.automat_type as 'automat_type',
							products.name as 'product_name', 
							products.sn_required as 'sn_required',
							sum(arrival_items.quantity) as  'product_quantity'
						FROM arrival_items 
							LEFT JOIN products ON products.id=arrival_items.product_id 
						WHERE arrival_items.storage_id='$storage_id' 
						AND arrival_items.arrival_type_id != '8'
						GROUP BY arrival_items.product_id";
				$stmt = $this->datab->prepare($query); 
				$stmt->execute();
				$points =$stmt->fetchAll(PDO::FETCH_ASSOC);
				return $points;
			}
			catch(PDOException $e){
				throw new Exception($e->getMessage());
			}
		}
		
		public function validProductSerialNumbers($product_id, $sn, $storage_id)
		{
			try
			{
				$query = "SELECT id FROM product_details WHERE storage_id='$storage_id' AND sn like '$sn' AND product_id='$product_id' 
						AND sn NOT IN (SELECT sn FROM product_details WHERE product_status_id=2 and sn <>'')";
				$stmt = $this->datab->prepare($query); 
				$stmt->execute();
				if($stmt->rowCount() > 0)
				{
					echo "ok";
				}
				else
				{
					echo "bad";
				}
			}
			catch(PDOException $e){
				throw new Exception($e->getMessage());
			}
		}
		
		public function get_arrival_types()
		{
			try
			{
				$query = "SELECT * FROM arrival_types WHERE id NOT IN (5,6)";
				$stmt = $this->datab->prepare($query); 
				$stmt->execute();
				$arrival_types =$stmt->fetchAll(PDO::FETCH_ASSOC);
				return $arrival_types;
				
			}
			catch(PDOException $e){
				throw new Exception($e->getMessage());
			}
		}
		
		public function get_arrival_details_by_type($arrival_type_id, $storage_id)
		{
			try
			{
				$query = "SELECT id, document_name FROM arrivals WHERE arrival_type_id='$arrival_type_id' AND storage_id='$storage_id' ORDER BY id DESC";
				$stmt = $this->datab->prepare($query); 
				$stmt->execute();
				$arrival_list =$stmt->fetchAll(PDO::FETCH_ASSOC);
				return $arrival_list;
				
			}
			catch(PDOException $e){
				throw new Exception($e->getMessage());
			}
		}
		
		public function warehouse($storage_id)
		{
			try
			{
				$query = "SELECT 
						arrival_items.product_id, 
						products.automat_type as 'automat_type', 
						products.name as 'product_name', 
						sum(arrival_items.quantity) as 'product_quantity' 
						FROM arrival_items 
						left join products ON products.id=arrival_items.product_id 
						WHERE arrival_items.storage_id='$storage_id' 
						AND arrival_items.arrival_type_id != '8'
						group by arrival_items.product_id";
				$stmt = $this->datab->prepare($query); 
				$stmt->execute();

				echo "<table id='table'>";
				echo "<thead>";
				echo "<th>Grupa automatów</th>";
				echo "<th>Nazwa Produktu</th>";
				echo "<th>Ilosc (szt.)</th>";
				echo "</thead>";
				echo "<tbody>";
				foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
					echo "<tr>
							<td>".$row['automat_type']."</td>
							<td>".$row['product_name']."</td>
							<td>".$row['product_quantity']."</td>
						</tr>";
						
				}
				echo "</tbody>";
				echo "</table>";
				
			}
			catch(PDOException $e){
				throw new Exception($e->getMessage());
			}
		}
		
		public function getSerwisantList($storage_id)
		{
			try
			{
				$query = "SELECT id, user_name FROM users WHERE user_group_id='3' AND storage_id='$storage_id' AND is_active='1'";
				$stmt = $this->datab->prepare($query); 
				$stmt->execute();
				$serwisants =$stmt->fetchAll(PDO::FETCH_ASSOC);
				return $serwisants;
			}
			catch(PDOException $e){
				throw new Exception($e->getMessage());
			}
		}
		
		public function getProduct_definitions()
		{
			try
			{
				$query = "SELECT * FROM products";
				$stmt = $this->datab->prepare($query); 
				$stmt->execute();
				$serwisants =$stmt->fetchAll(PDO::FETCH_ASSOC);
				return $serwisants;
			}
			catch(PDOException $e){
				throw new Exception($e->getMessage());
			}
		}
		
		public function updateProduct_definitions($product_id, $sn_required)
		{
			try
			{
				$query = "UPDATE products SET sn_required='$sn_required' WHERE id='$product_id'";
				$stmt = $this->datab->prepare($query); 
				$stmt->execute();
			}
			catch(PDOException $e){
				throw new Exception($e->getMessage());
			}
			
		}
		
				
		public function create_service_request($arrival_id, $item_product_id, $sn, $quantity, $storage_id, $release_user_id, $document_name, $item_name, $release_user_name)
		{
			try
			{
				$query = "INSERT INTO service_request SET arrival_id='$arrival_id', product_id='$item_product_id', sn='$sn', quantity='$quantity', storage_id='$storage_id', release_user_id='$release_user_id', change_status_datetime=NOW()";	
				$stmt = $this->datab->prepare($query); 
				$stmt->execute();
				$this->KLogger->LogINFO("Typ dokumentu: [transfer wydany], Nazwa dokumentu: [".$document_name."], ID dokumentu: [".$arrival_id."], Nazwa serisanta: [".$release_user_name."], ID serwisanta: [".$release_user_id."], Nazwa urządzenia: [".$item_name."], ID urządzenia: [".$item_product_id."], Ilość: [".$quantity."]");
			}
			catch(PDOException $e){
				throw new Exception($e->getMessage());
			}
		}
		
		public function devices_for_return($storage_id, $serviceman_user_id)
		{
			try
			{
				$query = "SELECT 
					service_request.id as 'service_request_id',
					service_request.arrival_id as 'arrival_id',
					SUM(service_request.quantity)*(-1) as 'quantity',
					service_request.sn as 'sn',
					products.name as 'product_name',
					products.id as 'product_id',
					MAX(service_request.service_status_id) as 'service_status_id'
					FROM service_request 
					LEFT JOIN products ON products.id=service_request.product_id
					WHERE service_request.storage_id='$storage_id' AND service_request.release_user_id='$serviceman_user_id' AND 				service_request.service_status_id IN (3,4) GROUP BY service_request.product_id";
				$stmt = $this->datab->prepare($query); 
				$stmt->execute();
				$devices =$stmt->fetchAll(PDO::FETCH_ASSOC);
				return $devices;
			}
			catch(PDOException $e){
				throw new Exception($e->getMessage());
			}
			
		}
		
		public function accept_devices_for_return($product_name, $sn, $quantity, $product_id, $document_name, $storage_id, $arrival_id)
		{
			try
			{
				$query = "INSERT INTO product_details SET product_id='$product_id', sn='$sn', arrival_id='$arrival_id', storage_id='$storage_id', product_status_id='3', product_status_change_datetime=NOW()";
				$stmt = $this->datab->prepare($query); 
				$stmt->execute();
			}
			catch(PDOException $e){
				throw new Exception($e->getMessage());
			}
			
		}
		
		public function return_to_arrival_items($product_id, $quantity, $arrival_id, $arrival_type_id, $storage_id)
		{
			try
			{
				$query = "INSERT INTO arrival_items SET product_id='$product_id', quantity='$quantity', arrival_id='$arrival_id', arrival_type_id='$arrival_type_id', storage_id='$storage_id'";	
				$stmt = $this->datab->prepare($query); 
				$stmt->execute();
				
			}
			catch(PDOException $e){
				throw new Exception($e->getMessage());
			}
		}
		
		public function return_to_service_request($arrival_id, $product_id, $sn, $quantity, $storage_id, $release_user_id, $service_status_id)
		{
			try
			{
				$query = "INSERT INTO service_request SET arrival_id='$arrival_id', product_id='$product_id', sn='$sn', quantity='$quantity', storage_id='$storage_id', release_user_id='$release_user_id', service_status_id='4', change_status_datetime=NOW()";	
				$stmt = $this->datab->prepare($query); 
				$stmt->execute();
				
			}
			catch(PDOException $e){
				throw new Exception($e->getMessage());
			}
		}
		
		//public function getDamagedDevices($storage_id, $service_user_id)
		public function getDamagedDevices($storage_id)
		{
			try
			{
				$query = "SELECT 
						damaged_devices.id as 'damaged_devices_id',
						damaged_devices.service_request_id as 'service_request_id',
						products.name as 'product_name',
						damaged_devices.quantity as 'quantity',
						damaged_devices.change_status_datetime as 'datetime',
						damaged_devices.product_id as 'product_id',
						damaged_devices.sn as 'sn',
						damaged_devices.storage_id as 'storage_id',
						damaged_devices.service_user_id as 'service_user_id',
						users.user_name as 'service_user_name',
						service_request.bus_number as 'bus_number',
						service_request.automat_number as 'automat_number'
						FROM damaged_devices
						LEFT JOIN products ON products.id=damaged_devices.product_id
						left JOIN service_request ON service_request.id=damaged_devices.service_request_id
						LEFT JOIN users ON users.id=damaged_devices.service_user_id
						WHERE damaged_devices.storage_id='$storage_id' AND damaged_devices.damaged_devices_status_id='0'
						ORDER BY users.user_name,products.name,damaged_devices.change_status_datetime ASC";
				$stmt = $this->datab->prepare($query); 
				$stmt->execute();
				$damagedDevices =$stmt->fetchAll(PDO::FETCH_ASSOC);
				return $damagedDevices;
			}
			catch(PDOException $e){
				throw new Exception($e->getMessage());
			}
		}
		
		public function getDamagedDevicesWorks($storage_id)
		{
			try
			{
				$query = "SELECT 
						damaged_devices.id as 'damaged_devices_id',
						products.name as 'product_name',
						products.automat_type as 'automat_type',
						SUM(damaged_devices.quantity) as 'quantity',
						damaged_devices.change_status_datetime as 'datetime',
						damaged_devices.product_id as 'product_id',
						damaged_devices.sn as 'sn',
						damaged_devices.storage_id as 'storage_id',
						damaged_devices.service_user_id as 'service_user_id',
						users.user_name as 'service_user_name',
						service_request.automat_number as 'automat_number'
						FROM damaged_devices
						LEFT JOIN products ON products.id=damaged_devices.product_id
						left JOIN service_request ON service_request.id=damaged_devices.service_request_id
						LEFT JOIN users ON users.id=damaged_devices.service_user_id
						WHERE damaged_devices.storage_id='$storage_id' AND damaged_devices.damaged_devices_status_id IN (2,3) GROUP BY products.name, damaged_devices.sn";
				$stmt = $this->datab->prepare($query); 
				$stmt->execute();
				$damagedDevicesWorks =$stmt->fetchAll(PDO::FETCH_ASSOC);
				return $damagedDevicesWorks;
			}
			catch(PDOException $e){
				throw new Exception($e->getMessage());
			}
		}
		
		public function getDamagedDevicesBroken($storage_id)
		{
			try
			{
				//rekordy o id 1-147 liczone były w stary sposób poprzez dodanie rekordu urządzenia z minusową ilością, gdy ilość wynosiła 0 urządzenie nie było widoczne
				//rekordy o id >147 liczone są w nowy sposób tzn updateowany jest rekord z informacją o przekazaniu do serwisu
				$query = "SELECT 
						damaged_devices.id as 'damaged_devices_id',
						products.name as 'product_name',
						products.automat_type as 'automat_type',
						SUM(damaged_devices.quantity) as 'quantity',
						damaged_devices.change_status_datetime as 'datetime',
						damaged_devices.product_id as 'product_id',
						damaged_devices.sn as 'sn',
						damaged_devices.storage_id as 'storage_id',
						damaged_devices.service_user_id as 'service_user_id',
						users.user_name as 'service_user_name',
						service_request.automat_number as 'automat_number'
						FROM damaged_devices
						LEFT JOIN products ON products.id=damaged_devices.product_id
						left JOIN service_request ON service_request.id=damaged_devices.service_request_id
						LEFT JOIN users ON users.id=damaged_devices.service_user_id
						WHERE damaged_devices.storage_id='$storage_id' AND damaged_devices.damaged_devices_status_id IN (1) AND damaged_devices.id > 147  GROUP BY products.name, damaged_devices.sn, damaged_devices.id";
				$stmt = $this->datab->prepare($query); 
				$stmt->execute();
				$damagedDevicesWorks =$stmt->fetchAll(PDO::FETCH_ASSOC);
				return $damagedDevicesWorks;
			}
			catch(PDOException $e){
				throw new Exception($e->getMessage());
			}
		}
		
		public function getDamagedDevicesReprocessing($storage_id)
		{
			try
			{
				$query = "SELECT 
						damaged_devices.id as 'damaged_devices_id',
						products.name as 'product_name',
						products.automat_type as 'automat_type',
						damaged_devices.quantity as 'quantity',
						damaged_devices.change_status_datetime as 'datetime',
						damaged_devices.product_id as 'product_id',
						damaged_devices.sn as 'sn',
						damaged_devices.storage_id as 'storage_id',
						damaged_devices.service_user_id as 'service_user_id',
						users.user_name as 'service_user_name',
						service_request.automat_number as 'automat_number',
						service_request.bus_number as 'bus_number'
						FROM damaged_devices
						LEFT JOIN products ON products.id=damaged_devices.product_id
						left JOIN service_request ON service_request.id=damaged_devices.service_request_id
						LEFT JOIN users ON users.id=damaged_devices.service_user_id
						WHERE damaged_devices.storage_id='$storage_id' AND damaged_devices.damaged_devices_status_id IN (5) AND damaged_devices.arrival_id IS NULL";
				$stmt = $this->datab->prepare($query); 
				$stmt->execute();
				$damagedDevicesWorks =$stmt->fetchAll(PDO::FETCH_ASSOC);
				return $damagedDevicesWorks;
			}
			catch(PDOException $e){
				throw new Exception($e->getMessage());
			}
		}
		
		public function update_damaged_devices($product_id, $sn, $datetime, $damaged_devices_status, $storage_id, $service_user_id, $damaged_devices_id)
		{
			try
			{
				$query = "UPDATE damaged_devices SET sn='$sn', damaged_devices_status_id='$damaged_devices_status', change_status_datetime='$datetime' WHERE id='$damaged_devices_id'";
				$stmt = $this->datab->prepare($query); 
				$stmt->execute();
			}
			catch(PDOException $e){
				throw new Exception($e->getMessage());
			}
		}
		
		public function update_service_request($service_request_id, $bus_number, $automat_number)
		{
			try
			{
				$query = "UPDATE service_request SET bus_number='$bus_number', automat_number='$automat_number' WHERE id='$service_request_id'";
				$stmt = $this->datab->prepare($query); 
				$stmt->execute();
				$this->KLogger->LogINFO("Zmiana danych pojazdu w zgłoszeniu serwisowym nr: [".$service_request_id."], przez uzytkownika: [".$this->user_login."], Nr boczny pojazdu: [".$bus_number."], Nr automatu: [".$automat_number."]");
			}
			catch(PDOException $e){
				throw new Exception($e->getMessage());
			}
		}
	
		public function transfer_from_damaged_devices_update($arrival_id, $product_id, $quantity_damaged, $sn, $storage_id, $damaged_devices_status, $damaged_devices_id, $mennica_service_id)
		{
			try
			{
				//$query = "INSERT INTO damaged_devices SET arrival_id='$arrival_id', product_id='$product_id', sn='$sn', quantity='$quantity_damaged', storage_id='$storage_id', damaged_devices_status_id='$damaged_devices_status', change_status_datetime=NOW()";
				$query = "UPDATE damaged_devices SET arrival_id='$arrival_id', damaged_devices_status_id='$damaged_devices_status', mennica_service_id='$mennica_service_id', change_status_datetime=NOW() WHERE id='$damaged_devices_id' AND product_id='$product_id' AND damaged_devices_status_id='1' and id > '147' ";
				$stmt = $this->datab->prepare($query); 
				$stmt->execute();
				$this->KLogger->LogINFO("Zmiana statusu urządzenia pobranego z automatu: [".$damaged_devices_status."], ID urządzenia: [".$product_id."], ID damaged_devices: [".$damaged_devices_id."]");
				$this->KLogger->LogINFO("UPDATE damaged_devices SET arrival_id='$arrival_id', damaged_devices_status_id='$damaged_devices_status', mennica_service_id='$mennica_service_id', change_status_datetime=NOW() WHERE id='$damaged_devices_id' AND product_id='$product_id' AND damaged_devices_status_id='1' and id > '147' ");
			}
			catch(PDOException $e){
				throw new Exception($e->getMessage());
			}
		}
		
		public function transfer_from_damaged_devices_insert($arrival_id, $product_id, $quantity_damaged, $sn, $storage_id, $damaged_devices_status, $damaged_devices_id, $mennica_service_id)
		{
			try
			{
				$query = "INSERT INTO damaged_devices SET arrival_id='$arrival_id', product_id='$product_id', sn='$sn', quantity='$quantity_damaged', storage_id='$storage_id', damaged_devices_status_id='$damaged_devices_status', change_status_datetime=NOW()";
				//$query = "UPDATE damaged_devices SET arrival_id='$arrival_id', damaged_devices_status_id='$damaged_devices_status', mennica_service_id='$mennica_service_id', change_status_datetime=NOW() WHERE product_id='$product_id' AND damaged_devices_status_id='1' and id > '147' ";
				$stmt = $this->datab->prepare($query); 
				$stmt->execute();
			}
			catch(PDOException $e){
				throw new Exception($e->getMessage());
			}
		}
		
		public function report_broken_devices($date_of, $date_to, $storage_id)
		{
			try
			{
				$query = "SELECT 
						arrivals.document_name as 'document_name',
						arrivals.id as 'arrival_id',
						arrivals.create_date as 'create_date',
						storage.name as 'storage_name'
						FROM broken_arrival_items
						LEFT JOIN arrivals ON arrivals.id=broken_arrival_items.arrival_id
						LEFT JOIN storage ON storage.id=broken_arrival_items.storage_id
						WHERE create_date between '$date_of' AND '$date_to'
						AND broken_arrival_items.storage_id='$storage_id'
						GROUP BY arrivals.id";
	
				$stmt = $this->datab->prepare($query); 
				$stmt->execute();
				$pom =$stmt->fetchAll(PDO::FETCH_ASSOC);
				return $pom;			
			}
			catch(PDOException $e){
				throw new Exception($e->getMessage());
			}
		}
		
		/**TRANSAKCJE**/
		public function release_delivery_and_create_service_request_TRANSACTION($arrival_type_id, $item_name, $item_sn, $quantity, $item_quantity, $item_product_id, $document_name, $storage_id, $arrival_id, $release_user_id, $release_user_name)
		{
			try
			{
				$this->datab->beginTransaction();
				
				vectorsoft_magazyn::release_delivery($arrival_type_id, $item_name, $item_sn, $item_quantity, $item_product_id, $document_name, $storage_id, $arrival_id);
				
				vectorsoft_magazyn::create_service_request($arrival_id, $item_product_id, $item_sn, $quantity, $storage_id, $release_user_id, $document_name,$item_name, $release_user_name);
				
				$this->datab->commit();
			}
			catch(PDOException $e){
				throw new Exception($e->getMessage());
				$this->datab->rollBack();
			}
		}
}

class serviceman extends db_conf
{
		public function a($text)
		{
			try
			{
				$query = "INSERT INTO test SET text='$text'";
				$stmt = $this->datab->prepare($query); 
				$stmt->execute();
			}
			catch(PDOException $e){
				throw new Exception($e->getMessage());
			}
		}
		
		public function b()
		{
			try
			{
				$query = "INSERT INTO test SET text='after commit b'";
				$stmt = $this->datab->prepare($query); 
				$stmt->execute();
			}
			catch(PDOException $e){
				throw new Exception($e->getMessage());
			}
		}
		public function test($text)
		{
			try
			{
				$this->datab->beginTransaction();
				
				serviceman::a($text);
				
				$this->datab->commit();
				serviceman::b();
			}
			catch(PDOException $e){
				throw new Exception($e->getMessage());
				$this->datab->rollBack();
			}
		}
		
		public function serviceman_devices($storage_id)
		{
			try
			{
				$query = "SELECT 
					users.user_name as 'serwisant',
					service_request.release_user_id,
					SUM(service_request.quantity) as 'quantity',
					products.name as 'product_name',
					products.id as 'product_id'
					FROM service_request 
					LEFT JOIN products ON products.id=service_request.product_id
					LEFT JOIN users ON users.id=service_request.release_user_id
					WHERE service_request.storage_id='$storage_id' AND 
                    release_user_id IN (SELECT id FROM users WHERE user_group_id='3' and storage_id='$storage_id') AND 
                    service_request.service_status_id in (1,2,3,5) 
					GROUP BY service_request.product_id,service_request.release_user_id		
					HAVING SUM(service_request.quantity)>0
					order by users.user_name,products.name ASC";
				$stmt = $this->datab->prepare($query); 
				$stmt->execute();
				$items =$stmt->fetchAll(PDO::FETCH_ASSOC);
				return $items;
			}
			catch(PDOException $e){
				throw new Exception($e->getMessage());
			}
		}
		
		public function getService_request($storage_id)
		{
			try
			{
				$query = "SELECT 
					service_request.id as 'service_request_id',
					service_request.arrival_id as 'arrival_id',
					SUM(service_request.quantity) as 'quantity',
					service_request.sn as 'sn',
					products.name as 'product_name',
					products.id as 'product_id',
					MAX(service_request.service_status_id) as 'service_status_id'
					FROM service_request 
					LEFT JOIN products ON products.id=service_request.product_id
					WHERE storage_id='$storage_id' AND release_user_id='$this->user_id' AND service_request.service_status_id in (1,2,3,5) GROUP BY service_request.product_id";
				$stmt = $this->datab->prepare($query); 
				$stmt->execute();
				$items =$stmt->fetchAll(PDO::FETCH_ASSOC);
				return $items;
			}
			catch(PDOException $e){
				throw new Exception($e->getMessage());
			}
		}
		
		public function getService_status()
		{
			try
			{
				$query = "SELECT * FROM service_status WHERE id NOT IN (1,4,5)";
				$stmt = $this->datab->prepare($query); 
				$stmt->execute();
				$service_status =$stmt->fetchAll(PDO::FETCH_ASSOC);
				return $service_status;
			}
			catch(PDOException $e){
				throw new Exception($e->getMessage());
			}
		}
		
		public function realize_service_request($product_id, $quantity, $sn, $service_status, $bus_number, $automat_number, $storage_id, $product_name, $service_user_name)
		{
			try
			{
				$query = "INSERT INTO service_request SET product_id='$product_id', sn='$sn', quantity='$quantity', storage_id='$storage_id', release_user_id='$this->user_id', service_status_id='$service_status', bus_number='$bus_number', automat_number='$automat_number', change_status_datetime=NOW()";
				$stmt = $this->datab->prepare($query); 
				$stmt->execute();
				$service_request_id = $this->datab->lastInsertId();
				
				if($service_status == '2')
				{
					//montaż w automacie
					$this->KLogger->LogINFO("Podjęte działanie serwisowe: [Montaż w automacie], Serwisant: [".$service_user_name."], Urządzenie: [".$product_name."], Id urządzenia: [".$product_id."], Ilość: [".$quantity."], Nr boczny pojazdu: [".$bus_number."], Nr automatu: [".$automat_number."]");
				}
				else if($service_status == '3')
				{
					$this->KLogger->LogINFO("Podjęte działanie serwisowe: [Zwrot do magazynu], Serwisant: [".$service_user_name."], Urządzenie: [".$product_name."], Id urządzenia: [".$product_id."], Ilość: [".$quantity."]");
				}
				
				serviceman::check_user_service_request($service_request_id);
				
				return $service_request_id;
				
			}
			catch(PDOException $e){
				throw new Exception($e->getMessage());
			}
		}
		
		public function check_user_service_request($service_request_id)
		{
			try
			{
				$query = "SELECT release_user_id FROM service_request WHERE id='$service_request_id'";
				$stmt = $this->datab->prepare($query); 
				$stmt->execute();
				$result = $stmt->fetch(PDO::FETCH_ASSOC);

				if($result['release_user_id'] == '0')
				{
					$query = "UPDATE service_request SET release_user_id='$this->user_id' WHERE id='$service_request_id'";
					$stmt = $this->datab->prepare($query); 
					$stmt->execute();
					$this->KLogger->LogERROR("Poprawiono dane w service_request id: ".$service_request_id);
				}	
				//else $this->KLogger->LogINFO("Sprawdzono spójność danych w service_request");
			}
			catch(PDOException $e){
				throw new Exception($e->getMessage());
			}
			
		}
		
		public function add_damaged_devices($service_request_id, $product_id, $quantity_damaged, $storage_id)
		{
			try
			{
				for($i=0; $i<$quantity_damaged; $i++)
				{	
					$query = "INSERT INTO damaged_devices SET service_request_id='$service_request_id', product_id='$product_id', quantity='1', storage_id='$storage_id', service_user_id='$this->user_id', change_status_datetime=NOW()";
					$stmt = $this->datab->prepare($query); 
					$stmt->execute();
					$damaged_devices_id = $this->datab->lastInsertId();
					$this->KLogger->LogInfo("Pobrano nowe urządzenie z automatu, ID service_request: [".$service_request_id."], ID urządzenia: [".$product_id."], Serwisant: [".$this->user_login."]");
					
					//funkcja testowa
					serviceman::check_user_damaged_devices($damaged_devices_id);
				}
			}
			catch(PDOException $e){
				throw new Exception($e->getMessage());
			}
		}
		
		/*funkcja testowa*/
		public function check_user_damaged_devices($damaged_devices_id)
		{
			try
			{
				$query = "SELECT service_user_id FROM damaged_devices WHERE id='$damaged_devices_id'";
				$stmt = $this->datab->prepare($query); 
				$stmt->execute();
				$result = $stmt->fetch(PDO::FETCH_ASSOC);

				if($result['service_user_id'] == '0')
				{
					$query = "UPDATE damaged_devices SET service_user_id='$this->user_id' WHERE id='$damaged_devices_id'";
					$stmt = $this->datab->prepare($query); 
					$stmt->execute();
					$this->KLogger->LogERROR("Poprawiono dane w damaged_devices id: ".$damaged_devices_id);
				}	
				//else $this->KLogger->LogINFO("Sprawdzono spójność danych w damaged devices");
			}
			catch(PDOException $e){
				throw new Exception($e->getMessage());
			}
			
		}
		
		public function devices_for_return($storage_id)
		{
			try
			{
				$query = "SELECT 
					service_request.id as 'service_request_id',
					service_request.arrival_id as 'arrival_id',
					SUM(service_request.quantity)*(-1) as 'quantity',
					service_request.sn as 'sn',
					products.name as 'product_name',
					products.id as 'product_id',
					MAX(service_request.service_status_id) as 'service_status_id'
					FROM service_request 
					LEFT JOIN products ON products.id=service_request.product_id
					WHERE service_request.storage_id='$storage_id' AND service_request.release_user_id='$this->user_id' AND 				service_request.service_status_id IN (3,4) GROUP BY service_request.product_id";
				$stmt = $this->datab->prepare($query); 
				$stmt->execute();
				$service_status =$stmt->fetchAll(PDO::FETCH_ASSOC);
				return $service_status;
			}
			catch(PDOException $e){
				throw new Exception($e->getMessage());
			}
		}
		
		public function history($date, $service_user_id)
		{
			try
			{
				$query = "SELECT products.name as 'product_name', 
								service_status.name as 'service_status', 
								service_status.id as 'service_status_id', 
								service_request.automat_number, 
								arrivals.document_name, 
								service_request.quantity,
								service_request.change_status_datetime, 
								u1.user_name as 'od', 
								u2.user_name as 'do'
							FROM  service_request
							LEFT JOIN products ON products.id = service_request.product_id
							LEFT JOIN service_status ON service_status.id = service_request.service_status_id
							LEFT JOIN arrivals ON arrivals.id=service_request.arrival_id
							LEFT JOIN users u1 ON u1.id=arrivals.create_user_id
							LEFT JOIN users u2 ON u2.id=arrivals.release_user_id
							WHERE  service_request.release_user_id ='$service_user_id'
							AND change_status_datetime BETWEEN  '$date 00:00:00' AND  '$date 23:59:59'
							ORDER BY service_request.change_status_datetime ASC";
				$stmt = $this->datab->prepare($query); 
				$stmt->execute();
				$hist =$stmt->fetchAll(PDO::FETCH_ASSOC);
				return $hist;
			}
			catch(PDOException $e){
				throw new Exception($e->getMessage());
			}
		}
		
}

class forward_devices extends db_conf
{
	//czyszczenie stocka serwisanta od którego przekazywane są urządzenia
	public function devices_out_serviceman($arrival_id, $product_id, $quantity, $sn, $service_status, $bus_number, $automat_number, $storage_id, $product_name, $service_user_name)
		{
			try
			{
				$query = "INSERT INTO service_request SET arrival_id='$arrival_id', product_id='$product_id', sn='$sn', quantity='$quantity', storage_id='$storage_id', release_user_id='$this->user_id', service_status_id='$service_status', bus_number='$bus_number', automat_number='$automat_number', change_status_datetime=NOW()";
				$stmt = $this->datab->prepare($query); 
				$stmt->execute();
			}
			catch(PDOException $e){
				throw new Exception($e->getMessage());
			}
		}
	
	public function devices_fwd_on_serviceman($arrival_id, $product_id, $sn, $quantity2, $storage_id, $release_user_id, $document_name, $product_name, $release_user_name)
		{
			try
			{
				$query = "INSERT INTO service_request SET arrival_id='$arrival_id', product_id='$product_id', sn='$sn', quantity='$quantity2', storage_id='$storage_id', release_user_id='$release_user_id', change_status_datetime=NOW()";	
				$stmt = $this->datab->prepare($query); 
				$stmt->execute();
			}
			catch(PDOException $e){
				throw new Exception($e->getMessage());
			}
		}
	
	public function release_delivery($arrival_type_id, $product_name, $sn, $quantity2, $product_id, $document_name, $storage_id, $arrival_id)
		{
			try
			{
				$query = "INSERT INTO product_details SET product_id='$product_id', sn='$sn', arrival_id='$arrival_id', storage_id='$storage_id', product_status_id='5', product_status_change_datetime=NOW()";
				$stmt = $this->datab->prepare($query); 
				$stmt->execute();
				
				forward_devices::insertItemsTo_arrival_itmes($arrival_type_id, $arrival_id, $storage_id, $product_id, $quantity2, $document_name, $product_name);
			}
			catch(PDOException $e){
				throw new Exception($e->getMessage());
			}
		}
	
	public function insertItemsTo_arrival_itmes($arrival_type_id, $arrival_id, $storage_id, $product_id, $quantity2, $document_name, $product_name)
		{
			try
			{
				$query = "INSERT INTO arrival_items SET product_id='$product_id', quantity='$quantity2', arrival_id='$arrival_id', arrival_type_id='$arrival_type_id', storage_id='$storage_id'";	
				$stmt = $this->datab->prepare($query); 
				$stmt->execute();
			}
			catch(PDOException $e){
				throw new Exception($e->getMessage());
			}
		}
		
	public function update_arrival_forward_serviceman($arrival_id, $release_user_id)
		{
			try
			{
				$query = "update arrivals SET release_user_id='$release_user_id' WHERE id='$arrival_id'";	
				$stmt = $this->datab->prepare($query); 
				$stmt->execute();
			}
			catch(PDOException $e){
				throw new Exception($e->getMessage());
			}
		}
	
	public function forward_devices_TRANSACTION($arrival_id, $arrival_type_id, $product_id, $quantity, $quantity2, $sn, $service_status, $bus_number, $automat_number, $storage_id, $product_name, $service_user_name, $release_user_name, $release_user_id, $document_name)
		{
			try
			{
				$this->datab->beginTransaction();
				
				//czyszczenie stocka serwisanta od którego przekazywane są urządzenia
				forward_devices::devices_out_serviceman($arrival_id, $product_id, $quantity, $sn, $service_status, $bus_number, $automat_number, $storage_id,$product_name, $service_user_name);
				
				//dodanie rekordów do wygenerowania raportu (nie wchodzi w stan)
				forward_devices::release_delivery($arrival_type_id, $product_name, $sn, $quantity2, $product_id, $document_name, $storage_id, $arrival_id);
	
				//dodanie stocka do przypisanego serwisanta
				forward_devices::devices_fwd_on_serviceman($arrival_id, $product_id, $sn, $quantity2, $storage_id, $release_user_id, $document_name, $product_name, $release_user_name);
								
				$this->datab->commit();
			}
			catch(PDOException $e){
				throw new Exception($e->getMessage());
				$this->datab->rollBack();
			}
		}
}
class user extends db_conf
{
	public function new_user($login, $password, $user_name, $email, $user_group_id, $storage_id)
		{
			try
			{
				$query = "INSERT INTO users SET login='$login', password=md5('$password'), user_name='$user_name', email='$email', user_group_id='$user_group_id', storage_id='$storage_id'";
				$stmt = $this->datab->prepare($query); 
				$stmt->execute();
				$this->KLogger->LogINFO("Użytkownik: [".$this->user_login."] utworzył nowe konto użytkownika: [".$login."]");
			}
			catch(PDOException $e){
				throw new Exception($e->getMessage());
			}
		}
	
	public function update_user($user_id, $login,  $user_name, $email, $user_group_id, $storage_id)
		{
			try
			{
				$query = "UPDATE users SET login='$login', user_name='$user_name', email='$email', user_group_id='$user_group_id', storage_id='$storage_id' WHERE id='$user_id'";
				$stmt = $this->datab->prepare($query); 
				$stmt->execute();
			}
			catch(PDOException $e){
				throw new Exception($e->getMessage());
			}
		}	
		
	public function update_user_pass($user_id, $login, $password, $user_name, $email, $user_group_id, $storage_id)
		{
			try
			{
				$query = "UPDATE users SET login='$login', password=md5('$password'), user_name='$user_name', email='$email', user_group_id='$user_group_id', storage_id='$storage_id' WHERE id='$user_id'";
				$stmt = $this->datab->prepare($query); 
				$stmt->execute();
			}
			catch(PDOException $e){
				throw new Exception($e->getMessage());
			}
		}		
		
	public function auth_user($login, $password)
		{
			try
			{
				$stmt = $this->datab->prepare("SELECT count(*) FROM users WHERE login='$login' AND password=md5('$password') AND is_active='1'");
				$stmt->execute();
				$result = $stmt->fetchColumn();
				if($result > 0)
				{
				
					user::getAuthUserInfo($login);
					$this->KLogger->LogINFO("Logowanie użytkownika: [".$login."]");
				}
				else
				{
				
					echo "Błędne dane logowania";
					$this->KLogger->LogError("Nieudane logowanie użytkownika: [".$login."]");
				}
			}
			catch(PDOException $e){
				throw new Exception($e->getMessage());
			}
		}
		
	public function change_password($user_id, $new_password)
	{
		try
			{
				$query = "UPDATE users SET password=md5('$new_password') WHERE id='$user_id'";
				$stmt = $this->datab->prepare($query); 
				$stmt->execute();
				$this->KLogger->LogINFO("Użytkownik: [".$this->user_login."] zmienił hasło do konta");
			}
			catch(PDOException $e){
				throw new Exception($e->getMessage());
			}
	}
	
	public function getAuthUserInfo($login)
		{
			try
			{
				$query = "SELECT 
							users.id as 'user_id',
							users.user_name as 'user_name',
							users.login as 'login',
							users_group.name as 'group_name',
							users.storage_id as 'storage_id',
							storage.name as 'storage_name'
							FROM users 
							LEFT JOIN users_group ON users_group.id=users.user_group_id
							LEFT JOIN storage ON storage.id=users.storage_id
							WHERE login='$login' AND is_active='1'";
				$stmt = $this->datab->query($query);
				$data = $stmt->fetchAll();
								
				$_SESSION['mennica_magazyn_user_id'] = $data[0]['user_id'];
				$_SESSION['mennica_magazyn_user_name'] = $data[0]['user_name'];
				$_SESSION['mennica_magazyn_login'] = $data[0]['login'];
				$_SESSION['mennica_magazyn_module'] = $data[0]['group_name'];
				$_SESSION['mennica_magazyn_storage_id'] = $data[0]['storage_id'];
				$_SESSION['mennica_magazyn_storage_name'] = $data[0]['storage_name'];
				echo $_SESSION['mennica_magazyn_login'];
			}
			catch(PDOException $e){
				throw new Exception($e->getMessage());
			}
		}
		
	public function getStorageList()
		{
			try
			{
				$query = "SELECT * FROM storage";
				$stmt = $this->datab->prepare($query); 
				$stmt->execute();
				$storage =$stmt->fetchAll(PDO::FETCH_ASSOC);
				return $storage;
			}
			catch(PDOException $e){
				throw new Exception($e->getMessage());
			}
		}	
		
	public function getGroupList()
		{
			try
			{
				$query = "SELECT * FROM users_group";
				$stmt = $this->datab->prepare($query); 
				$stmt->execute();
				$groups =$stmt->fetchAll(PDO::FETCH_ASSOC);
				return $groups;
			}
			catch(PDOException $e){
				throw new Exception($e->getMessage());
			}
		}	
	
	public function getUsersList()
		{
			try
			{
				$query = "SELECT 
						users.id as 'user_id',
						users.user_name as 'user_name',
						users_group.name as 'user_group_name',
						storage.name as 'storage_name'
						FROM users 
						left join storage ON storage.id=users.storage_id
						left join users_group ON users_group.id=users.user_group_id
						ORDER BY users.user_name ASC";
				$stmt = $this->datab->prepare($query); 
				$stmt->execute();
				$groups =$stmt->fetchAll(PDO::FETCH_ASSOC);
				return $groups;
			}
			catch(PDOException $e){
				throw new Exception($e->getMessage());
			}
		}	
	
	public function getUserDetails($user_id)
		{
			try
			{
				$query = "SELECT 
						users.id as 'user_id',
						users.login as 'login',
						users.email as 'email',
						users.user_name as 'user_name',
						users_group.name as 'user_group_name',
						users_group.id as 'user_group_id',
						storage.name as 'storage_name',
						storage.id as 'storage_id'
						FROM users 
						left join storage ON storage.id=users.storage_id
						left join users_group ON users_group.id=users.user_group_id
						WHERE users.id='$user_id'";
				$stmt = $this->datab->prepare($query); 
				$stmt->execute();
				$groups =$stmt->fetchAll(PDO::FETCH_ASSOC);
				return $groups;
			}
			catch(PDOException $e){
				throw new Exception($e->getMessage());
			}
		}	
		
}


class sendMail extends db_conf
{
	protected function configMail()
		{
			try
			{
				$this->phpMailer->PluginDir = MAIL_PLUGIN_DIR;
				$this->phpMailer->From = MAIL_FROM;
				$this->phpMailer->FromName = MAIL_FROM_NAME;
				$this->phpMailer->Host = MAIL_HOST;
				$this->phpMailer->Mailer = MAIL_PROTOCOL;
				$this->phpMailer->Username = MAIL_USERNAME;
				$this->phpMailer->Password = MAIL_PASSWD;
				$this->phpMailer->Port = MAIL_PORT;
				$this->phpMailer->IsHTML(true);
				$this->phpMailer->SMTPAuth = true;
				$this->phpMailer->SetLanguage("pl", "phpmailer/language/");
			}
			catch(PDOException $e){
				throw new Exception($e->getMessage());
			}
		}
	public function prepareMail($point_name, $address, $city, $order, $storage_id, $order_value)
		{
			try
			{
				if($point_name != '')
				{
					$title = 'Nowe zamówienie do punktu';
					$wiadomosc = 'Zamówienie do '.$point_name.' na ilość '.$order.' szt. rolek papieru';
					sendMail::newMail($title, $wiadomosc);
				}		
				else if($storage_id != '')
				{
					$title = 'Nowe zamówienie do magazynu';
					$wiadomosc = 'Zamówienie do magazynu'.$storage_id.' na ilość '.$order_value. 'szt. rolek papieru';
					sendMail::newMail($title, $wiadomosc);
				}
			}
			catch(PDOException $e){
				throw new Exception($e->getMessage());
			}
		}	
	
	public function newMail($title, $wiadomosc)
		{
			try
			{
				sendMail::configMail();
				
				$this->phpMailer->Subject = $title;
				$this->phpMailer->Body = $wiadomosc;
				$this->phpMailer->AddAddress("konrad.tomaszewski85@gmail.com", "kondzik");
				$this->phpMailer->Send();
				
				if($this->phpMailer->Send())
					{
						$this->KLogger->LogINFO("Wysłano mail: [".$title."], o treści; [".$wiadomosc."]");
					}
				else
					{
						$this->KLogger->LogERROR("Wystąpiły problemy z wysłaniem maila: [".$this->phpMailer->ErrorInfo."]");
					}
				
				
				$this->phpMailer->ClearAddresses();
				$this->phpMailer->ClearAttachments();
			}
			catch(PDOException $e){
				throw new Exception($e->getMessage());
			}	
		}
}
	$database = new db_conf();
	$document_name_generate = new document_name_generate();
	$dashboard = new dashboard();
	$mennica_magazyn = new mennica_magazyn();
	$vectorsoft_magazyn = new vectorsoft_magazyn();
	$serviceman = new serviceman();
	$forward_devices = new forward_devices();
	$user = new user();
	$sendMail = new sendMail();
	?>