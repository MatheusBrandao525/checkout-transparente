<?php
// Carrega configurações do sistema pai
// Carrega configurações do sistema pai
require_once('../_config.php');

// Define constants expected by mysql.php
define("SERVIDOR", $config['SERVIDOR']);
define("USUARIO", $config['USUARIO']);
define("SENHA", $config['SENHA']);
define("BANCO", $config['BANCO']);

// Define DOMINIO constant
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

// Carrega dependencias locais do modulo
// IMPORTANTE: Usa o vendor da raiz do projeto, pois o vendor local está vazio/corrompido
require_once '../vendor/autoload.php';

// Busca credenciais do Banco
$conexao = new mysql();
$coisas_pagamento = $conexao->Executar("SELECT * FROM pagamento WHERE id='3' ");
$data_pagamento = $coisas_pagamento->fetch_object();

// Configuração do SDK V3
use MercadoPago\MercadoPagoConfig;
use MercadoPago\Client\Payment\PaymentClient;
use MercadoPago\Exceptions\MPApiException;

MercadoPagoConfig::setAccessToken($data_pagamento->mercadopago_access_token);

$client = new PaymentClient();

// Dados básicos do pagador
$payer = [
    "email" => $_REQUEST['email'],
    "first_name" => $_REQUEST['cardholderName'] ?? 'Cliente', // Nome pode vir do cartão ou padrão
    "last_name" => "Loja",
    "identification" => [
        "type" => $_REQUEST['docType'] ?? 'CPF',
        "number" => preg_replace('/[^0-9]/', '', $_REQUEST['docNumber'] ?? '')
    ]
];

// Monta o array de requisição base
$request = [
    "transaction_amount" => (float) $_REQUEST['amount'],
    "description" => $_REQUEST['description'],
    "payer" => $payer,
    "external_reference" => $_REQUEST['order_code'] ?? null
];

// Pega o modo de pagamento
$payment_mode = $_REQUEST['payment_mode'] ?? 'card';

if ($payment_mode == 'card') {
    // === CARTÃO ===
    $request["token"] = $_REQUEST['token'];
    $request["installments"] = (int) $_REQUEST['installmentsOption'];
    $request["payment_method_id"] = $_REQUEST['paymentMethodId'];

} elseif ($payment_mode == 'pix') {
    // === PIX ===
    $request["payment_method_id"] = "pix";
    $request["installments"] = 1;

} elseif ($payment_mode == 'boleto') {
    // === BOLETO ===
    $request["payment_method_id"] = "bolbradesco"; // Verifique se sua conta suporta 'bolbradesco' ou use 'pec'
    $request["installments"] = 1;
}

try {
    // Cria o pagamento
    $payment = $client->create($request);

    // Lógica de Sucesso / Pendente
    if ($payment->status == 'approved' || $payment->status == 'in_process' || $payment->status == 'pending') {

        $codigo_sessao = $_REQUEST['order_code'];
        $id_transacao = $payment->id;

        // Status: 2 se aprovado, 1 se pendente
        $status_pedido = ($payment->status == 'approved') ? 2 : 1;

        $update_data = [
            "id_transacao" => $id_transacao,
            "status" => $status_pedido
        ];

        // Tenta salvar Link do Boleto ou Código PIX
        // V3 response structure navigation
        if (isset($payment->point_of_interaction->transaction_data->qr_code)) {
            $update_data["pix_chave"] = $payment->point_of_interaction->transaction_data->qr_code; // PIX Copy Paste
        }

        if (isset($payment->point_of_interaction->transaction_data->qr_code_base64)) {
            $update_data["pix_qrcode"] = $payment->point_of_interaction->transaction_data->qr_code_base64; // PIX Image Base64
        }

        if (isset($payment->transaction_details->external_resource_url)) {
            $update_data["link_boleto"] = $payment->transaction_details->external_resource_url; // Link Boleto
            $update_data["link_cielo"] = $payment->transaction_details->external_resource_url; // Fallback ou uso generico
        }

        $conexao = new mysql();
        $conexao->alterar("pedido_loja", $update_data, "codigo='$codigo_sessao'");

        if ($payment->status == 'approved') {
            // Se já aprovou de cara (ex: Cartão), vai para detalhes
            header("Location: " . DOMINIO . "index/pedidos_detalhes/codigo/$codigo_sessao");
            exit;
        } else {
            // Se pendente (PIX ou Boleto), mostra tela de finalização/instruções

            // Variáveis para a view pending.php
            $pix_copia_cola = $update_data["pix_chave"] ?? null;
            $pix_base64 = $update_data["pix_qrcode"] ?? null;
            $boleto_url = $update_data["link_boleto"] ?? null;
            $order_id_display = $_REQUEST['description'] ?? $codigo_sessao;

            // Inclui a view de pendente
            include 'pending.php';
            exit;
        }

    } else {
        // Recusado
        echo "<h3>Pagamento não aprovado.</h3>";
        echo "<p>Status: " . $payment->status_detail . "</p>";
        echo "<p><a href='" . DOMINIO . "index/carrinho'>Voltar ao Carrinho</a></p>";
    }

} catch (MPApiException $e) {
    echo "<h3>Erro da API Mercado Pago</h3>";
    echo "<p>Status Code: " . $e->getStatusCode() . "</p>";
    $response = $e->getApiResponse();
    if ($response) {
        $content = $response->getContent();

        // Verifica erros comuns
        $causes = $content['cause'] ?? [];
        if (!is_array($causes))
            $causes = [];

        $msg_amigavel = "";

        foreach ($causes as $cause) {
            if ($cause['code'] == 4037) {
                $msg_amigavel .= "<p style='color:red; font-weight:bold;'>Erro: O valor da transação é inválido. Para Boletos, o valor mínimo geralmente é R$ 4,00.</p>";
            }
            if ($cause['code'] == 324) {
                $msg_amigavel .= "<p style='color:red; font-weight:bold;'>Erro: CPF/CNPJ inválido.</p>";
            }
            if ($cause['code'] == 2067) {
                $msg_amigavel .= "<p style='color:red; font-weight:bold;'>Erro: Número do documento inválido.</p>";
            }
        }

        if ($msg_amigavel) {
            echo $msg_amigavel;
        }

        echo "<pre>";
        print_r($content);
        echo "</pre>";
    } else {
        echo "<p>" . $e->getMessage() . "</p>";
    }
    echo "<p><a href='" . DOMINIO . "checkout-transparente/'>Tentar Novamente</a></p>";

} catch (Exception $e) {
    echo "<h3>Erro ao processar pagamento</h3>";
    echo "<p>" . $e->getMessage() . "</p>";
    // echo "<pre>"; print_r($e); echo "</pre>";
    echo "<p><a href='" . DOMINIO . "checkout-transparente/'>Tentar Novamente</a></p>";
}
?>