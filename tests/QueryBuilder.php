<?php

require __DIR__ . '/../src/QueryBuilder.php';

$x = '{"value":["Pending","Shipped"],"type":"set","meta":{"model_name":"TtSample","model_field":"status"}}';
$filter_c = json_decode($x);

$params['fields'] = array(
    'TtSampleDatum_upc',
    'TtSampleDatumCopy_am_user_id',
);

$params['filters'] = array(
    /*
    'upc' => (object)array(
        'value' => '0890',
        'type' => 'contains',
        'meta' => (object)array(
            'model_name' => 'TtSampleDatum',
            'model_field' => 'upc',
        ),
    ),
    'tagged_date' => (object)array(
        "value" => "2015-01-01 to 2016-01-27",
        "start" => "2015-01-01 03:00:00",
        "end" => "2016-01-27 03:00:00",
        "type" => "range",
        "meta" =>  (object)array(
            "model_name" => "TtSample",
            "model_field" => "tagged_date",
            "function" => "date",
        )
    ),
    'TtSample.status' => $filter_c,
*/
);

$QueryBuilder = new QueryBuilder();
$QueryBuilder->setMappingFile('../resources/mapping.json');

echo $QueryBuilder->build($params);
