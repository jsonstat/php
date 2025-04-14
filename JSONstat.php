<?php
/**
 * JSONstat PHP Library v.1.0.0
 * 
 * A PHP library for working with JSON-stat datasets
 * (https://json-stat.org/)
 * 
 * This library provides functions to parse, navigate, and extract data
 * from JSON-stat dataset documents.
 */

class JSONstat {
	/**
	 * JSON-stat object
	 * @var object
	 */
	private $jsonstat;
	

	/**
	 * Constructor - initializes a new JSONstat object from a JSON string or URL
	 * 
	 * @param string $source Either a JSON string or a URL to fetch JSON-stat data from
	 * @param array $options Optional configuration options (like proxy settings)
	 * @throws Exception If the JSON-stat data is invalid or cannot be retrieved
	 */
	public function __construct($source, $options = array()) {
		if (filter_var($source, FILTER_VALIDATE_URL)) {
			$jsonData = $this->fetch($source, $options);
		} else {
			$jsonData = $source;
		}
		
		$this->parseJSONstat($jsonData);
	}
	
	/**
	 * Fetches JSON data from a URL
	 * 
	 * @param string $url The URL to fetch data from
	 * @param array $options Optional configuration options
	 * @return string The JSON response
	 * @throws Exception If the URL cannot be fetched
	 */
	private function fetch($url, $options = array()) {
		$ch = curl_init($url);
		
		// Default cURL options
		$defaultOptions = array(
			CURLOPT_CONNECTTIMEOUT => 5,
			CURLOPT_RETURNTRANSFER => 1
		);
		
		// Merge user options with defaults (user options take precedence)
		$curlOptions = $options + $defaultOptions;
		
		curl_setopt_array($ch, $curlOptions);
		
		$response = curl_exec($ch);
		$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		
		if ($httpCode !== 200) {
			throw new Exception("HTTP error! Status: $httpCode");
		}
		
		if (curl_errno($ch)) {
			throw new Exception(curl_error($ch));
		}
		
		curl_close($ch);
		
		return $response;
	}
	
	/**
	 * Parses JSON-stat data and initializes internal properties
	 * 
	 * @param string $jsonData The JSON-stat data as a string
	 * @throws Exception If the data is not valid JSON-stat
	 */
	private function parseJSONstat($jsonData) {
		// Convert into object
		$jsonstat = json_decode($jsonData);
		
		if ($jsonstat === null) {
			throw new Exception('Error: response was not valid JSON.');
		}
		
		// If no "class", assume "bundle" response and use the first dataset
		if (!isset($jsonstat->class)) {
			$vars = get_object_vars($jsonstat);
			$dsname = key($vars);
			$jsonstat = $jsonstat->$dsname;
		} else {
			// JSON-stat v.2.0 Verify it's a "dataset" response
			if ($jsonstat->class != 'dataset') {
				throw new Exception('Error: response was not a JSON-stat bundle or dataset response.');
			}
		}
		
		// Validate required properties
		if (!isset($jsonstat->value) || !isset($jsonstat->dimension)) {
			throw new Exception('Error: response is not valid JSON-stat or does not contain required information.');
		}
		
		$this->jsonstat = $jsonstat;
		$this->label = $jsonstat->label;
		$this->dimension = $jsonstat->dimension;

		// JSON-stat 2.0 backward-compat
		$this->size = (isset($jsonstat->size)) ? $jsonstat->size : $this->dimension->size;
		$this->ids = (isset($jsonstat->id)) ? $jsonstat->id : $this->dimension->id;

		$this->ndims = count($this->size);
		$this->value = $jsonstat->value;
		$this->status = isset($jsonstat->status) ? $jsonstat->status : null;
	}
	
	/**
	 * Parses an observation (value/status) from JSON-stat data
	 * 
	 * @param mixed $obs The observation array or object from the JSON-stat
	 * @param int $index The flat index of the observation
	 * @return mixed The data value/status or null if not found
	 */
	private function parseObs($obs, $index) {
		if (is_object($obs)) {
			$value = isset($obs->{$index}) ? $obs->{$index} : null;
		} else {
			// An array with a single value can be used to assign the same value or status to all observations
			$value = isset($obs[$index]) ? $obs[$index] : $obs[0];
		}
		
		return $value;
	}

	/**
	 * Gets a value from the dataset using various input formats
	 * 
	 * @param array|int $input Either:
	 *                         - Associative array mapping dimension IDs to category values
	 *                           Example: array('concept'=>'UNR','area'=>'US','year'=>'2010')
	 *                         - Array of dimension indices
	 *                           Example: array(0, 33, 7)
	 *                         - Integer representing flat index
	 * @return array The observation object (value and status) or null if not found
	 */
	public function getObs($input) {
		if (is_int($input)) {
			// Input is already a flat index
			return $this->getObsByIndex($input);
		}
		
		if (!is_array($input) || count($input) == 0) {
			return null;
		}
		
		// Check if input is sequential array (indices) or associative array (query)
		if (array_keys($input) === range(0, count($input) - 1)) {
			// Input is array of dimension indices
			$index = $this->toObsIndex($input);
		} else {
			// Input is dimension/category pairs
			$indices = $this->toDimIndices($input);
			$index = $this->toObsIndex($indices);
		}
		
		return $this->getObsByIndex($index);
	}

	/**
	 * Converts dimension/category pairs to dimension indices
	 * 
	 * @param array $query Associative array mapping dimension IDs to category values
	 *                     Example: array('concept'=>'UNR','area'=>'US','year'=>'2010')
	 * @return array Array of dimension indices
	 *               Example: array(0, 33, 7)
	 */
	public function toDimIndices($query) {
		$arr = array();
		
		for ($i = 0; $i < $this->ndims; $i++) {
			$arr[$i] = $this->toDimIndex($this->ids[$i], $query[$this->ids[$i]]);
		}
		
		return $arr;
	}
	
	/**
	 * Converts a dimension ID and category value to its numeric index
	 * 
	 * @param string $name Dimension ID (e.g., "area")
	 * @param string $value Category value (e.g., "US")
	 * @return int The numeric index of the category in the dimension
	 */
	public function toDimIndex($name, $value) {
		// In single category dimensions, "index" is optional
		if (!isset($this->dimension->$name->category->index)) {
			return 0;
		}
		
		$ndx = $this->dimension->$name->category->index;
		
		// "index" can be an object or an array
		if (is_object($ndx)) {
			// Object
			return $ndx->$value;
		} else {
			// Array
			return array_search($value, $ndx, true);
		}
	}
	
	/**
	 * Converts an array of dimension indices to a flat value index
	 * 
	 * @param array $indices Array of dimension indices
	 *                       Example: array(0, 33, 7)
	 * @return int The flat value index
	 *             Example: 403
	 */
	public function toObsIndex($indices) {
		$num = 0;
		$mult = 1;
		
		for ($i = 0; $i < $this->ndims; $i++) {
			$mult *= ($i > 0) ? $this->size[$this->ndims - $i] : 1;
			$num += $mult * $indices[$this->ndims - $i - 1];
		}
		
		return $num;
	}
	
	/**
	 * Gets a value/status from the dataset using its flat index
	 * 
	 * @param mixed $val The value/status array or object from the JSON-stat
	 * @param int $index The flat index of the value
	 * @return mixed The data value/status or null if not found
	 */
	private function getObsByIndex($index) {
		// "value"/"status" can be an array or an object (sparse cube)
		$value = $this->parseObs($this->value, $index);

		if ($this->status==null) {
			$status = null;
		} else {
			$status = $this->parseObs($this->status, $index);	
		}
		
		return array('value'=>$value, 'status'=>$status);
	}
	
	/**
	 * Converts a flat value index back to an array of dimension indices
	 * 
	 * @param int $obsIndex The flat value index
	 * @return array Array of dimension indices
	 */
	public function toIndices($obsIndex) {
		$indices = array_fill(0, $this->ndims, 0);
		
		for ($i = 0; $i < $this->ndims; $i++) {
			$p = 1;
			for ($j = $i + 1; $j < $this->ndims; $j++) {
				$p *= $this->size[$j];
			}
			$indices[$i] = floor($obsIndex / $p) % $this->size[$i];
		}
		
		return $indices;
	}
	
	/**
	 * Converts dimension indices to dimension/category pairs
	 * 
	 * @param array $indices Array of dimension indices
	 *                       Example: array(0, 33, 7)
	 * @return array Associative array mapping dimension IDs to category values
	 *               Example: array('concept'=>'UNR','area'=>'US','year'=>'2010')
	 */
	public function toCatIds($indices) {
		$arr = array();
		
		foreach ($indices as $dimIndex => $catIndex) {
			$dimId = $this->ids[$dimIndex];			
			$arr[$dimId] = $this->getCategoryId($dimId, $catIndex);
		}
		
		return $arr;
	}
	
	/**
	 * Gets a category ID from its index
	 * 
	 * @param string $dimId Dimension ID string
	 * @param int $index Category index
	 * @return string|null The category ID or null if not found
	 */
	public function getCategoryId($dimId, $index) {
		$catIds = $this->getCategoryIds($dimId);
		
		return isset($catIds[$index]) ? $catIds[$index] : null;
	}

	/**
	 * Gets a category label from ID
	 * 
	 * @param string $dimId Dimension ID
	 * @param string $catId Category ID
	 * @return string|null The category label or null if not found
	 */
	public function getCategoryLabel($dimId, $catId) {
		//pendent comprovar altres estructures de categories...
		//comprovar que nulls van

		$dim = $this->dimension->{$dimId};
		if(!isset($dim)) {
			return null;
		}

		$label =  $dim->category->label;
		if(!isset($label)){
			return $catId;
		}

		$catLabel = $label->{$catId};
		
		if(!isset($catLabel)) {
			return null;
		}

		return $catLabel;
	}


	/**
	 * Iterates through all values in all dimensions following row major order
	 * 
	 * @param callable $callback Function to call for each value with parameters:
	 *                           - $value: The actual data value
	 *                           - $status: The status value (if available)
	 *                           - $catIds: Associative array mapping dimension IDs to category values
	 *                           - $positions: Additional information about the value
	 */
	public function unflatten($callback) {
		// Calculate total number of values
		$totalValues = 1;
		$cells = array();

		for ($i = 0; $i < $this->ndims; $i++) {
			$totalValues *= $this->size[$i];
		}
		
		// Iterate through all values
		for ($index = 0; $index < $totalValues; $index++) {
			$coord = $this->toCatIds($this->toIndices($index));
			
			// Get value & status
			$point = $this->getObsByIndex($index);
			
			$result = call_user_func($callback, $coord, $point, $index, $cells);
			if(isset($result)) {
				$cells[] = $result;
			}
		}
		
		return $cells;
	}
	
	/**
	 * Gets the raw JSON-stat object
	 * 
	 * @return object The JSON-stat object
	 */
	public function getJSONstat() {
		return $this->jsonstat;
	}
	
	
	/**
	 * Gets the size array
	 * 
	 * @return array The size array
	 */
	public function getSize() {
		return $this->size;
	}
	
	/**
	 * Gets the number of dimensions
	 * 
	 * @return int The number of dimensions
	 */
	public function getDimensionsN() {
		return $this->ndims;
	}

	/**
	 * Gets dataset label
	 * 
	 * @return string The dataset label
	 */
	public function getLabel() {
		return $this->label;
	}


	/**
	 * Gets the dimension IDs array
	 * 
	 * @return array The dimension IDs array
	 */
	public function getDimensionIds() {
		return $this->ids;
	}

	/**
	 * Gets the dimension labels assoc. array
	 * 
	 * @return array The dimension labels assoc. array
	 */
	public function getDimensionLabels() {
		$labels = array();

		foreach ($this->dimension as $id => $dim) {
			$labels[$id] = $dim->label;
		}
		return $labels;
	}

	/**
	 * Gets the dimension label
	 * 
	 * @param string $dimId Dimension ID
	 * @return array Dimension label string
	 */
	public function getDimensionLabel($dimId) {
		return $this->dimension->$dimId->label;
	}

	/**
	 * Gets the categories' id array for a dimension
	 * 
	 * @param string $name Dimension ID (e.g., "area")
	 * @return array The categories' id array
	 */

	public function getCategoryIds($dimId) {
		$cat = $this->dimension->$dimId->category;
		
		if (isset($cat->index)) {
			$catIds = $cat->index;
		} else {
			$catIds = $cat->label;
			$labels = get_object_vars($catIds);
			if (count($labels) == 1) {
				$catIds = array_keys($labels);
			}
		}
		
		if (is_object($catIds)) {
			$catIds = array_flip((array)$catIds);
		}

		return $catIds;
	}
}
