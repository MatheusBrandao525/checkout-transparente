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

session_start();

// Tenta identificar a sessão principal (mesma lógica do system/controller.php)
if (isset($_SESSION['sessaouserloja'])) {
  $sessao_principal = $_SESSION['sessaouserloja'];
} else {
  // Se não tem sessão principal, não tem como pagar
  header("Location: ../");
  exit;
}

if (!isset($_SESSION[$sessao_principal]['loja_cod_sessao'])) {
  header("Location: ../");
  exit;
}

$codigo_sessao = $_SESSION[$sessao_principal]['loja_cod_sessao'];

// Busca dados do pedido
$conexao = new mysql();
$coisas_pedido = $conexao->Executar("SELECT * FROM pedido_loja WHERE codigo='$codigo_sessao' ORDER BY id DESC LIMIT 1");
if ($coisas_pedido->num_rows == 0) {
  echo "Pedido não encontrado.";
  exit;
}
$data_pedido = $coisas_pedido->fetch_object();

// Busca dados do cliente
$conexao = new mysql();
$coisas_cadastro = $conexao->Executar("SELECT * FROM cadastro WHERE codigo='$data_pedido->cadastro' ");
$data_cadastro = $coisas_cadastro->fetch_object();

// Define valores para o formulário
$amount_val = number_format((float) $data_pedido->valor_total, 2, '.', '');
$email_val = $data_cadastro->email;
$description_val = "Pedido #" . $data_pedido->id;
$doc_number_val = ($data_cadastro->tipo == 'F') ? $data_cadastro->fisica_cpf : $data_cadastro->juridica_cnpj;
$cardholder_name_val = ($data_cadastro->tipo == 'F') ? $data_cadastro->fisica_nome : $data_cadastro->juridica_nome;

// Verifica disponibilidade do Boleto (Mínimo R$ 4,00)
$boleto_active = ($amount_val >= 4.00);

// Pega Public Key do Banco
$conexao = new mysql();
$coisas_pg = $conexao->Executar("SELECT mercadopago_public_key FROM pagamento WHERE id='3' ");
$data_pg = $coisas_pg->fetch_object();
$mp_public_key = $data_pg->mercadopago_public_key;

?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Checkout Seguro | Pagamento</title>

  <script src="https://code.jquery.com/jquery-1.11.0.min.js"></script>
  <script src="https://secure.mlstatic.com/sdk/javascript/v1/mercadopago.js"></script>

  <!-- Modern Layout CSS -->
  <link href="css/modern_checkout.css" rel="stylesheet">
</head>

<body>

  <div class="principal">

    <div class="checkout-card">

      <h1 class="checkout-title">Finalizar Pagamento</h1>

      <!-- Payment Method Tabs -->
      <div class="payment-tabs">
        <div class="payment-tab active" data-target="card">Cartão de Crédito</div>
        <div class="payment-tab" data-target="pix">PIX</div>
        <div class="payment-tab <?php echo (!$boleto_active) ? 'disabled' : ''; ?>" data-target="boleto">
          Boleto Bancário
          <?php if (!$boleto_active) {
            echo "<br><span style='font-size:10px;'>(Mín. R$ 4,00)</span>";
          } ?>
        </div>
      </div>

      <form action="payment.php" method="post" id="pay" name="pay">


        <!-- Hidden Fields (Order Logic) -->
        <input type="hidden" name="order_code" value="<?php echo $codigo_sessao; ?>" />
        <input data-checkout="docType" type="hidden" name="docType" value="CPF" />
        <input data-checkout="siteId" type="hidden" name="siteId" value="MLB" />

        <!-- NEW: Track selected payment mode -->
        <input type="hidden" name="payment_mode" id="payment_mode" value="card" />

        <!-- Order Summary (Common) -->
        <div class="form-group">
          <label class="form-label">Descrição do Pedido</label>
          <input type="text" name="description" id="description" data-checkout="description" class="form-control"
            value="<?php echo $description_val; ?>" readonly />
        </div>

        <div class="form-group">
          <label class="form-label">Total a Pagar (R$)</label>
          <input type="text" class="form-control" name="amount" id="amount" value="<?php echo $amount_val; ?>" readonly
            style="font-weight: 700; color: #059669;" />
        </div>

        <div class="form-group">
          <label class="form-label" for="email">Email</label>
          <input id="email" class="form-control" name="email" value="<?php echo $email_val; ?>" type="email"
            placeholder="seu@email.com" />
        </div>

        <div class="form-group">
          <label class="form-label" for="docNumber">CPF / CNPJ</label>
          <input type="text" id="docNumber" name="docNumber" data-checkout="docNumber" class="form-control"
            placeholder="000.000.000-00" value="<?php echo $doc_number_val; ?>" />
        </div>

        <hr style="border: 0; border-top: 1px solid #e5e7eb; margin: 24px 0;">

        <!-- TAB: Credit Card -->
        <div id="tab-card" class="tab-content active">

          <div class="supported-cards" style="margin-bottom: 20px;">
            <img
              src="https://upload.wikimedia.org/wikipedia/commons/thumb/5/5e/Visa_Inc._logo.svg/100px-Visa_Inc._logo.svg.png"
              alt="Visa" title="Visa">
            <img
              src="https://upload.wikimedia.org/wikipedia/commons/thumb/a/a4/Mastercard_2019_logo.svg/100px-Mastercard_2019_logo.svg.png"
              alt="Mastercard" title="Mastercard">
            <img
              src="https://upload.wikimedia.org/wikipedia/commons/thumb/3/30/American_Express_logo.svg/100px-American_Express_logo.svg.png"
              alt="Amex" title="American Express">
            <img src="img/elo_logo.png" alt="Elo" title="Elo">
          </div>

          <div class="form-group">
            <label class="form-label" for="cardNumber">Número do Cartão</label>
            <div style="position: relative;">
              <input type="text" id="cardNumber" data-checkout="cardNumber" class="form-control"
                placeholder="0000 0000 0000 0000" />
              <div id="bandeira" style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%);"></div>
            </div>
          </div>

          <div class="form-group">
            <label class="form-label" for="cardholderName">Nome impresso no cartão</label>
            <input type="text" id="cardholderName" name="cardholderName" data-checkout="cardholderName"
              class="form-control" placeholder="COMO NO CARTÃO" value="<?php echo $cardholder_name_val; ?>"
              style="text-transform: uppercase;" />
          </div>

          <div class="form-row">
            <div class="form-col">
              <label class="form-label">Validade (Mês/Ano)</label>
              <div style="display: flex; gap: 10px;">
                <input type="text" id="cardExpirationMonth" data-checkout="cardExpirationMonth" class="form-control"
                  placeholder="MM" maxlength="2" />
                <input type="text" id="cardExpirationYear" data-checkout="cardExpirationYear" class="form-control"
                  placeholder="AA" maxlength="2" />
              </div>
            </div>
            <div class="form-col">
              <label class="form-label" for="securityCode">CVV</label>
              <input type="text" id="securityCode" data-checkout="securityCode" class="form-control" placeholder="123"
                maxlength="4" />
            </div>
          </div>

          <div class="form-group">
            <label class="form-label" for="installments">Parcelas</label>
            <select id="installments" class="form-control" name="installmentsOption">
              <option disabled selected>Digite o cartão para ver opções</option>
            </select>
          </div>
        </div>

        <!-- TAB: PIX -->
        <div id="tab-pix" class="tab-content" style="text-align: center;">
          <div style="padding: 20px; background: #e0f2fe; border-radius: 8px; color: #0369a1;">
            <p><strong>Pagamento Instantâneo via PIX</strong></p>
            <p style="font-size: 14px;">Ao clicar em "Pagar Agora", será gerado um QR Code e um código "Copia e Cola"
              para você realizar o pagamento no app do seu banco.</p>
            <p style="font-size: 14px; margin-top: 10px;">O pagamento é aprovado na hora!</p>
          </div>
        </div>

        <!-- TAB: Boleto -->
        <div id="tab-boleto" class="tab-content" style="text-align: center;">
          <div style="padding: 20px; background: #fff7ed; border-radius: 8px; color: #9a3412;">
            <p><strong>Boleto Bancário</strong></p>
            <p style="font-size: 14px;">Ao clicar em "Pagar Agora", seu boleto será gerado.</p>
            <p style="font-size: 14px;">Lembre-se: O boleto pode levar até 3 dias úteis para ser compensado.</p>
          </div>
        </div>

        <div class="form-group" style="margin-top: 32px;">
          <input class="btn-pay" type="submit" value="Pagar Agora" />
        </div>

        <div class="security-badge">
          <svg fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd"
              d="M2.166 4.999A11.954 11.954 0 0010 1.944 11.954 11.954 0 0017.834 5c.11.65.166 1.32.166 2.001 0 5.225-3.34 9.67-8 11.317C5.34 16.67 2 12.225 2 7c0-.682.057-1.35.166-2.001zm11.541 3.708a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
              clip-rule="evenodd"></path>
          </svg>
          Transação criptografada e segura de ponta a ponta.
        </div>

      </form>
    </div>
  </div>

  <br><br>

  <!-- Keeping the logic script intact but cleaner -->
  <script type="text/javascript">

    // TAB Switching Logic
    $(document).ready(function () {
      $('.payment-tab').click(function () {
        if ($(this).hasClass('disabled')) return;

        var target = $(this).data('target');

        // Switch Tabs
        $('.payment-tab').removeClass('active');
        $(this).addClass('active');

        // Switch Content
        $('.tab-content').removeClass('active');
        $('#tab-' + target).addClass('active');

        // Update hidden Input
        $('#payment_mode').val(target);
      });
    });

    Mercadopago.setPublishableKey("<?php echo $mp_public_key; ?>");

    $(document).ready(function () {
      // $("#amount").val(Math.floor(Math.random() * 600) + 10)
    });

    function addEvent(el, eventName, handler) {
      if (el.addEventListener) {
        el.addEventListener(eventName, handler);
      } else {
        el.attachEvent('on' + eventName, function () {
          handler.call(el);
        });
      }
    };

    function getBin() {
      var ccNumber = document.querySelector('input[data-checkout="cardNumber"]');
      return ccNumber.value.replace(/[ .-]/g, '').slice(0, 6);
    };

    function guessingPaymentMethod(event) {
      var bin = getBin();

      if (event.type == "keyup") {
        if (bin.length >= 6) {
          Mercadopago.getPaymentMethod({
            "bin": bin
          }, setPaymentMethodInfo);
        }
      } else {
        setTimeout(function () {
          if (bin.length >= 6) {
            Mercadopago.getPaymentMethod({
              "bin": bin
            }, setPaymentMethodInfo);
          }
        }, 100);
      }
    };

    function setPaymentMethodInfo(status, response) {
      if (status == 200) {

        var form = document.querySelector('#pay');

        if (document.querySelector("input[name=paymentMethodId]") == null) {
          var paymentMethod = document.createElement('input');
          paymentMethod.setAttribute('name', "paymentMethodId");
          paymentMethod.setAttribute('type', "hidden");
          paymentMethod.setAttribute('value', response[0].id);
          form.appendChild(paymentMethod);
        } else {
          document.querySelector("input[name=paymentMethodId]").value = response[0].id;
        }

        var img = "<img src='" + response[0].thumbnail + "' align='center' style='margin-left:10px;' ' >";
        $("#bandeira").empty();
        $("#bandeira").append(img);
        amount = document.querySelector('#amount').value;
        Mercadopago.getInstallments({
          "bin": getBin(),
          "amount": amount
        }, setInstallmentInfo);

      }
    };

    addEvent(document.querySelector('input[data-checkout="cardNumber"]'), 'keyup', guessingPaymentMethod);
    addEvent(document.querySelector('input[data-checkout="cardNumber"]'), 'change', guessingPaymentMethod);

    doSubmit = false;
    addEvent(document.querySelector('#pay'), 'submit', doPay);

    function doPay(event) {
      event.preventDefault();

      var mode = document.querySelector('#payment_mode').value;

      if (mode === 'card') {
        // Logic for Credit Card (Needs Token)
        if (!doSubmit) {
          var $form = document.querySelector('#pay');
          Mercadopago.createToken($form, sdkResponseHandler);
          return false;
        }
      } else {
        // Logic for PIX or Boleto (Direct Submit)
        var $form = document.querySelector('#pay');

        // Remove required attributes from card fields if any, to avoid HTML5 validation blocking
        // (Current HTML doesn't use 'required' attribute, but good practice)

        doSubmit = true;
        $form.submit();
      }
    };

    function sdkResponseHandler(status, response) {
      if (status != 200 && status != 201) {
        alert("Verifique os dados do cartão.");
      } else {
        var form = document.querySelector('#pay');
        var card = document.createElement('input');
        card.setAttribute('name', "token");
        card.setAttribute('type', "hidden");
        card.setAttribute('value', response.id);
        form.appendChild(card);
        doSubmit = true;
        form.submit();
      }
    };

    function setInstallmentInfo(status, response) {
      var selectorInstallments = document.querySelector("#installments"),
        fragment = document.createDocumentFragment();
      selectorInstallments.options.length = 0;
      if (response.length > 0) {
        var option = new Option("Selecione...", '-1'),
          payerCosts = response[0].payer_costs;
        fragment.appendChild(option);
        for (var i = 0; i < payerCosts.length; i++) {
          option = new Option(payerCosts[i].recommended_message || payerCosts[i].installments, payerCosts[i].installments);
          fragment.appendChild(option);
        }
        selectorInstallments.appendChild(fragment);
        selectorInstallments.removeAttribute('disabled');
      }
    };

  </script>
</body>

</html>