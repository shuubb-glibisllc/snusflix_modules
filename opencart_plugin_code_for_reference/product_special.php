<?php
	class ModelCatalogProductSpecial extends Model {
		public function delProductSpecial($category_id, $manufacturer_id) {
			$query = "SELECT p.product_id, p.price FROM " . DB_PREFIX . "product p LEFT JOIN oc_product_to_category pc ON p.product_id = pc.product_id WHERE pc.category_id = '" . (int)$category_id . "'";
			
			if (!empty($manufacturer_id)) {
				$query .= " AND p.manufacturer_id = '" . (int)$manufacturer_id . "'";
			}
			
			$products = $this->db->query($query);		
			
			foreach ($products->rows as $row) {		
				$this->db->query("DELETE FROM " . DB_PREFIX . "product_discount WHERE product_id = '" . (int)$row['product_id'] . "'");
			}
		}
		public function updProductSpecial($category_id, $manufacturer_id, $customer_group_id, $price, $date_start, $date_end, $quantity) {
			$query = "SELECT p.product_id, p.price FROM " . DB_PREFIX . "product p LEFT JOIN oc_product_to_category pc ON p.product_id = pc.product_id WHERE pc.category_id = '" . (int)$category_id . "'";
			
			if (!empty($manufacturer_id)) {
				$query .= " AND p.manufacturer_id = '" . (int)$manufacturer_id . "'";
			}
			
			$products = $this->db->query($query);					
			foreach ($products->rows as $row) {	
				$discounted_price = $row['price'] - ($row['price'] * $price / 100);
				$discounted_price = round($discounted_price, 2);
				
				// Вставляем новую запись со скидкой
				$this->db->query("INSERT INTO " . DB_PREFIX . "product_discount SET product_id = '" . (int)$row['product_id'] . "'
                , customer_group_id = '" . (int)$customer_group_id . "'
                , priority = '0', price = '" . (float)$discounted_price . "'
                , quantity = '" . (int)$quantity . "'
                , date_start = '" . $this->db->escape($date_start) . "'
                , date_end = '" . $this->db->escape($date_end) . "'");
			}
		}
	}
	
