<?php
// check_status.php - Helper to check order status for polling
require_once('../_config.php');

// Define constants expected by mysql.php
define("SERVIDOR", $config['SERVIDOR']);
define("USUARIO", $config['USUARIO']);
define("SENHA", $config['SENHA']);
define("BANCO", $config['BANCO']);

if ($config['SSL']) {
    $config_dominio = "https://" . $_SERVER['HTTP_HOST'] . "/";
    if ($config['PASTA']) {
        $config_dominio = $config_dominio . $config['PASTA'] . "/";
    }
} else {
    $config_dominio = "http://" . $_SERVER['HTTP_HOST'] . "/";
    if ($config['PASTA']) {
        $config_dominio = $config_dominio . $config['PASTA'] . "/";
    }
}
define("DOMINIO", $config_dominio);

require_once('../system/mysql.php');

$codigo_sessao = $_REQUEST['order_code'] ?? '';

if (empty($codigo_sessao)) {
    echo json_encode(['status' => 'error']);
    exit;
}

$conexao = new mysql();
$query = "SELECT status FROM pedido_loja WHERE codigo='$codigo_sessao' LIMIT 1";
$resultado = $conexao->Executar($query);

if ($resultado->num_rows > 0) {
    $data = $resultado->fetch_object();
    // Status 2 usually means Paid/Approved
    $status_str = ($data->status == 2) ? 'approved' : 'pending';
    echo json_encode(['status' => $status_str, 'db_status' => $data->status]);
} else {
    echo json_encode(['status' => 'not_found']);
}
?>