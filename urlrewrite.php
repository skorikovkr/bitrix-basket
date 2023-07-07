<?php
$arUrlRewrite=array (
  array(
    'CONDITION' => '#^/api/(.*)/(.*)/(.*)#',
    'RULE' => 'CLASS=$1&METHOD=$2',
    'ID' => 'legacy:api',
    'PATH' => '/local/api/index.php',
    'SORT' => 100,
  ),
);
