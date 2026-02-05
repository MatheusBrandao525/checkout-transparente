<?php
// pending.php - Tela de Aguarde Pagamento (PIX/Boleto)
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Finalize seu Pagamento</title>
    <link href="css/modern_checkout.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-1.11.0.min.js"></script>
</head>

<body>

    <div class="principal">
        <div class="checkout-card" style="text-align: center;">

            <div style="margin-bottom: 20px;">
                <svg style="width: 50px; height: 50px; color: #f59e0b;" fill="none" stroke="currentColor"
                    viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
            </div>

            <h1 class="checkout-title">Aguardando Pagamento</h1>
            <p style="color: #6b7280; margin-bottom: 30px;">
                <?php echo $order_id_display; ?>
            </p>

            <?php if ($pix_base64): ?>
                <!-- PIX Block -->
                <div
                    style="background: #f0fdf4; border: 1px solid #bbf7d0; padding: 20px; border-radius: 12px; margin-bottom: 25px;">
                    <h3 style="color: #166534; margin-bottom: 15px; font-size: 18px; font-weight: 600;">Pagamento via PIX
                    </h3>

                    <div style="margin-bottom: 15px;">
                        <img src="data:image/png;base64, <?php echo $pix_base64; ?>"
                            style="width: 200px; height: 200px; border-radius: 8px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);">
                    </div>

                    <p style="font-size: 14px; color: #166534; margin-bottom: 10px;">Escaneie o QR Code acima ou copie a
                        chave abaixo:</p>

                    <div style="position: relative;">
                        <textarea id="pix-key" class="form-control" style="font-size: 13px; height: 60px; color: #374151;"
                            readonly><?php echo $pix_copia_cola; ?></textarea>
                        <button onclick="copiachave()"
                            style="margin-top: 8px; background: #166534; color: white; border: none; padding: 8px 16px; border-radius: 6px; font-size: 14px; cursor: pointer;">Copiar
                            Chave</button>
                    </div>

                    <div id="check-msg" style="margin-top: 15px; font-size: 13px; color: #15803d; display:none;">
                        <span class="loader"></span> Aguardando confirmação...
                    </div>
                </div>

            <?php elseif ($boleto_url): ?>
                <!-- Boleto Block -->
                <div
                    style="background: #fff7ed; border: 1px solid #fed7aa; padding: 20px; border-radius: 12px; margin-bottom: 25px;">
                    <h3 style="color: #9a3412; margin-bottom: 15px; font-size: 18px; font-weight: 600;">Boleto Bancário</h3>
                    <p style="margin-bottom: 20px; color: #9a3412;">Seu boleto foi gerado com sucesso.</p>

                    <a href="<?php echo $boleto_url; ?>" target="_blank" class="btn-pay"
                        style="background-color: #ea580c; text-decoration: none; display: inline-block;">Visualizar Imprimir
                        Boleto</a>

                    <p style="font-size: 12px; color: #7c2d12; margin-top: 15px;">* O pagamento pode levar até 3 dias úteis
                        para ser compensado.</p>
                </div>

            <?php else: ?>
                <!-- Generic Fallback -->
                <p>Seu pedido foi registrado. Aguarde o processamento do pagamento.</p>
            <?php endif; ?>

            <div style="border-top: 1px solid #e5e7eb; padding-top: 20px;">
                <a href="<?php echo DOMINIO . "index/pedidos_detalhes/codigo/$codigo_sessao"; ?>" style="color:
                    #4b5563; font-size: 14px; text-decoration: none;">Já fiz o pagamento, ir para Meus Pedidos
                    &rarr;</a>
            </div>

        </div>
    </div>

    <script>
        function copiachave() {
            var copyText = document.getElementById("pix-key");
            copyText.select();
            copyText.setSelectionRange(0, 99999);
            document.execCommand("copy");
            alert("Chave Copiada!");
        }

    // Polling System for PIX
    <?php if ($pix_base64): ?>
                $(document).ready(function () {
                    $('#check-msg').show();
                    var orderCode = "<?php echo $codigo_sessao; ?>";

                    var checkInterval = setInterval(function () {
                        $.ajax({
                            url: 'check_status.php',
                            type: 'POST',
                            data: { order_code: orderCode },
                            dataType: 'json',
                            success: function (data) {
                                if (data.status == 'approved' || data.status == 'paid' || data.status == 2) {
                                    clearInterval(checkInterval);
                                    window.location.href = "<?php echo DOMINIO . 'index/pedidos_detalhes/codigo/' . $codigo_sessao; ?>";
                                }
                            }
                        });
                    }, 5000); // Check every 5 seconds
                });
    <?php endif; ?>
    </script>

</body>

</html>