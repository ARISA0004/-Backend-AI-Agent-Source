<?php
require_once __DIR__ . '/../servicer/firebaservice.php';
require_once __DIR__ . '/../servicer/systemservice.php';
use function servicer\systemservice\firestoreToTreeText;
use servicer\firebaservice\firebaservice;

$fi = new firebaservice();


$data = Array
(
    'userId' => ['integerValue' => (int)8242503209],
    'date' =>  ['stringValue' => (string)'none'],
    'category' =>  ['stringValue' => (string)'none'],
     'id' => ['stringValue' => 'iVwwwURRAI06TAKHQUlY']
  
        );

$res = $fi->get(
    'transactions',
    '8242503209',
            );

echo firestoreToTreeText($res);
?>