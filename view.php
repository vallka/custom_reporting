<?php
/*
*
*  @author vallka
*  @copyright  2021
*  @license    http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*/

ini_set('error_reporting', E_ALL);
ini_set('display_errors', 1);


include(dirname(__FILE__) . '/../../config/config.inc.php');

PrestaShopLogger::addLog('custom_reporting view.php');  

$db = \Db::getInstance();
$_DB_PREFIX_ = _DB_PREFIX_;


function get_data($sql) {
  global $db,$_DB_PREFIX_;

  $result = $db->executeS($sql);
  return $result;
}


function custom_reporting_main() {
  global $db,$_DB_PREFIX_;
  $id = isset($_GET['id']) ? $_GET['id'] : null;
  $token = isset($_GET['token']) ? $_GET['token'] : null;
  $_token = isset($_GET['_token']) ? $_GET['_token'] : null;

  if (!$id) {
    $sql = "select id_custom_reporting as id,name as `name-link`,description from {$_DB_PREFIX_}custom_reporting where active=1 order by sort";
    $name = "Custom Reports";
  }
  else {
    $id=(int)$id;
    $param = isset($_GET['param']) ? $_GET['param'] : '';

    $sql = "select * from {$_DB_PREFIX_}custom_reporting where id_custom_reporting=$id";

    $result = $db->getRow($sql);
    $sql = $result['sql'];

    //if ($param) {
      $param = $db->escape($param);
      $sql = str_replace('{$param}', $param, $sql);
    //}

    $name = $result['name'];
  }


  if ($sql) {
    $pp = get_data($sql);

    if (strtolower($_GET['output_format']=='csv')) {
      header("Content-Type: text/csv");
      header("Content-Disposition: attachment; filename=\"report.csv\"");

      $output = fopen("php://output", "w");
      fputcsv($output, array_keys($pp[0]));

      foreach($pp as $row) {
          fputcsv($output, $row);
      }

      fclose($output);
    }
    elseif (strtolower($_GET['output_format']=='json')) {
      $json_result = json_encode($pp);

      header('Content-Type: application/json');
      //header('Access-Control-Allow-Origin: *');
      //header('Access-Control-Allow-Methods: GET, POST');
      //header('Access-Control-Allow-Headers: Content-Type');
      header('Access-Control-Allow-Origin: *');
      header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
      header('Access-Control-Allow-Headers: Content-Type, Authorization');
      echo $json_result;
      
    }
    else {
      //echo 'HTML';

      display_html($name,$token,$_token);
    }
  }
}


function display_html($name,$token,$_token) {
  $fullUrl = getFullUrl();
  $scriptUrl = getScriptUrl();

  $viewFile = dirname(__FILE__) . '/view.html';

  if (!is_readable($viewFile)) {
    $html = '<p>Error: view.html not found or not readable.</p>';
  } else {
    $html = file_get_contents($viewFile);

    // Prepare safe replacements for the placeholders used in the template
    $escapedName = htmlspecialchars($name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    // fullUrl is used inside a JS single-quoted string in the template, escape single quotes
    $escapedFullUrl = str_replace("'", "\\'", $fullUrl);
    $escapedScriptUrl = htmlspecialchars($scriptUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

    $html = str_replace(
      ['{$name}', '{$fullUrl}', '{$scriptUrl}', '{$token}', '{$_token}'],
      [$escapedName, $escapedFullUrl, $escapedScriptUrl,$token,  $_token],
      $html
    );
  }

  echo $html;
  return;

  $html=<<<EOD
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$name}</title>
    <script src="https://cdn.jsdelivr.net/npm/vue@3.5.12"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-EVSTQN3/azprG1Anm3QDgpJLIm9Nao0Yz1ztcQTwFspd3yD65VohhpuuCOmLASjC" crossorigin="anonymous">
    <style>
    .table {
        width: fit-content;
    }
    </style>

</head>
<body>

<div id="app">
    <h1>{$name}</h1>
    <table v-if="rows.length" class="table table-striped table-hover table-bordered table-sm">
        <thead>
            <tr>
                <th v-for="(value, key) in rows[0]" :key="key">{{ key }}</th>
            </tr>
        </thead>
        <tbody>
            <tr v-for="row in rows" :key="row.id || index">
                <td v-for="(value, key) in row" :key="key"><span v-html="formatData(key, row)"></span></td>
            </tr>
        </tbody>
    </table>
    <p v-else>No data available.</p>
</div>

<script>
const app = Vue.createApp({
    data() {
        return {
            rows: []
        };
    },
    mounted() {
        this.fetchrows();
    },
    methods: {
        fetchrows() {
            fetch('{$fullUrl}&output_format=json')
                .then(response => response.json())
                .then(data => this.rows = data);
        },
        formatData(column_name, row) { 
            if (column_name == 'name-link') {
                return `<a href="{$scriptUrl}?id=\${row['id']}">\${row['name-link']}</a>`;
            } else if (column_name.startsWith('link-')) {
                return `<a href="\${row[column_name]}" target="_blank">\${column_name.replace('link-', '')}</a>`;
            } else {
                return row[column_name];
            }
        }
    }
});

app.mount('#app');
</script>

</body>
</html>
EOD;

  echo $html;
}

function getFullUrl() {

  //return "https://www.gellifique.co.uk/modules/custom_reporting/view.php";

  // Check if the request is over HTTPS
  $isHttps = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';

  // Determine the protocol
  $protocol = $isHttps ? 'https://' : 'http://';

  // Combine to create the full URL
  $fullUrl = $protocol . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];

  return $fullUrl;
}

function getScriptUrl() {

    //return "https://www.gellifique.co.uk/modules/custom_reporting/view.php";
  
    // Check if the request is over HTTPS
    $isHttps = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';
  
    // Determine the protocol
    $protocol = $isHttps ? 'https://' : 'http://';
  
    // Combine to create the full URL
    $fullUrl = $protocol . $_SERVER['HTTP_HOST'] . $_SERVER['PHP_SELF'];
  
    return $fullUrl;
  }
  
custom_reporting_main();