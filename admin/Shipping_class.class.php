<?php 

class Shipping_class {

	private static $instance;

	private 		$shipping_classes_loaded 			= false;
	public 			$shipping_classes 					= array();

	/**
	 * Construct function
	 */
	function __construct () {

		if (!$this->shipping_classes_loaded){

			$this->load_shipping_classes();

		}

	}

	/**
	 * Function to get instance of the class.
	 * @return self
	 */
	public static function &get_instance () {

		if ( is_null( self::$instance ) ) {
			
			self::$instance = new Shipping_class();
		
		}
		
		return self::$instance;
	
	}

	/**
	 * Function to load existing shipping classes
	 * @return void
	 */
	private function load_shipping_classes(){

		if (taxonomy_exists('product_shipping_class')){

	    	$shipping_classes_terms = get_terms( 'product_shipping_class', 'orderby=name&hide_empty=0' );

	    	foreach ($shipping_classes_terms as $shipping_class_term) {

	    		$shipping_class_vars = get_object_vars($shipping_class_term);
	    		$this->shipping_classes[$shipping_class_vars['term_id']] = array('name' => $shipping_class_vars['name'], 'slug' => $shipping_class_vars['slug']);

	    	}

	    	$this->shipping_classes_loaded = true;
	    
	    }else{

			sl_debbug('## Error. Product shipping class taxonomy does not exist.');

	    }
	    
	}

	/**
	 * Function to create new shipping class
	 * @param  string 	$shipping_class_name [description]
	 * @return integer  new shipping class id created
	 */
	public function create_shipping_class($shipping_class_name){

        $new_shipping_class = wp_insert_term( $shipping_class_name, 'product_shipping_class' );
	    
	    if( is_wp_error( $new_shipping_class ) ) {

			sl_debbug('## Error. Creating new shipping class with name '.$shipping_class_name.' : '.print_r($new_shipping_class->get_error_message(),1));
			return '';

		}else{
			
			$new_shipping_class_data = sl_get_term_by( 'term_id', $new_shipping_class['term_id'], 'product_shipping_class', 'ARRAY_A');
			$this->shipping_classes[$new_shipping_class_data['term_id']] = array('name' => $new_shipping_class_data['name'], 'slug' => $new_shipping_class_data['slug']);

			return $new_shipping_class_data['term_id'];

		}

	}

}