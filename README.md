# JSONstat PHP Library

A PHP library for working with JSON-stat datasets (https://json-stat.org/). This library provides functions to parse, navigate, and extract data from JSON-stat dataset documents.

This simple library focuses on providing general solutions for the more flexible aspects of JSON-stat like handling 'value', 'status', or dimension information. For the properties that don't require normalization, the library offers the `getJSONstat()` method that exposes all the elements of a JSON-stat dataset response. Finally, the `unflatten()` method can be used to pair data and metadata.

## Installation

Copy the `JSONstat.php` file to your project and include it:

```php
require_once 'JSONstat.php';
```

## Class Constructor

### JSONstat($source, $options = array())

Creates a new JSONstat object from a JSON string or URL.

- **Parameters:**
  - `$source` (string): Either a JSON string or a URL to fetch JSON-stat data from
  - `$options` (array): Optional cURL configuration options for URL fetching
- **Throws:** Exception if the JSON-stat data is invalid or cannot be retrieved

```php
// From URL
$jsonstat = new JSONstat('https://json-stat.org/samples/oecd.json');

// From JSON string
$jsonData = '{"class":"dataset",...}';
$jsonstat = new JSONstat($jsonData);
```

`$options` can be used to connect through a proxy server. Or, when required, to retrieve data using the POST method:

```php
//$query is for example a JSON string
$options = [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $query,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'Accept: application/json'
    ]
];

$jsonstat = new JSONstat($url, $options);
```

## Public Methods

### getJSONstat()

Gets the raw JSON-stat object.

- **Returns:** object

```php
$raw = $jsonstat->getJSONstat();
```

### getSize()

Gets dimensions' sizes.

- **Returns:** array of dimension sizes

```php
$sizes = $jsonstat->getSize();
```

### getDimensionsN()

Gets the number of dimensions.

- **Returns:** int

```php
$count = $jsonstat->getDimensionsN();
```

### getLabel()

Gets the dataset label.

- **Returns:** string

```php
$label = $jsonstat->getLabel();
```

### getDimensionIds()

Gets all dimension IDs.

- **Returns:** array of dimension IDs

```php
$dims = $jsonstat->getDimensionIds();
```

### getDimensionLabels()

Gets all dimension labels.

- **Returns:** associative array mapping dimension IDs to labels

```php
$labels = $jsonstat->getDimensionLabels();
```

### getDimensionLabel($dimId)

Gets a single dimension's label.

- **Parameters:**
  - `$dimId` (string): Dimension ID
- **Returns:** string

```php
$label = $jsonstat->getDimensionLabel('area');
```

### getCategoryIds($dimId)

Gets category IDs for a dimension.

- **Parameters:**
  - `$dimId` (string): Dimension ID
- **Returns:** array of category IDs

```php
$categories = $jsonstat->getCategoryIds('area');
```

### getCategoryLabel($dimId, $catId)

Gets a category's label.

- **Parameters:**
  - `$dimId` (string): Dimension ID
  - `$catId` (string): Category ID
- **Returns:** string|null

```php
$label = $jsonstat->getCategoryLabel('area', 'US');
```

### getObs($input)

Gets a value from the dataset using various input formats.

- **Parameters:**
  - `$input` (array|int): Can be:
    - Associative array mapping dimension IDs to category values
    - Array of dimension indices
    - Integer representing flat index
- **Returns:** array containing 'value' and 'status'

```php
// Using dimension/category pairs
$obs = $jsonstat->getObs([
    'concept' => 'UNR',
    'area' => 'US',
    'year' => '2010'
]);

// Using dimension indices
$obs = $jsonstat->getObs([0, 33, 7]);

// Using flat index
$obs = $jsonstat->getObs(403);
```

### unflatten($callback)

Iterates through all data points.

- **Parameters:**
  - `$callback` (callable): Function called for each data points with parameters:
    - `$coordinates`: Dimension/category pairs
    - `$datapoint`: Value and status
    - `$index`: Flat index
    - `$cells`: Accumulated results
- **Returns:** array of accumulated results

```php
$jsonstat->unflatten(function($coord, $point) {
    return [
      'coord' => $coord,
      'point' => $point
    ];
});
```

