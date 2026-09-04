<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../conexao.php';
require_once __DIR__ . '/../../mercadopago_config.php';


/*
=====================================================
 FUNÇÃO DE RESPOSTA
=====================================================
*/

function responder(array $dados, int $status = 200): never
{
    http_response_code($status);

    echo json_encode(
        $dados,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );

    exit;
}


/*
=====================================================
 UUID V4
=====================================================
*/

function gerarUuidV4(): string
{
    $data = random_bytes(16);

    $data[6] = chr(
        (ord($data[6]) & 0x0f) | 0x40
    );

    $data[8] = chr(
        (ord($data[8]) & 0x3f) | 0x80
    );

    return vsprintf(
        '%s%s-%s-%s-%s-%s%s%s',
        str_split(bin2hex($data), 4)
    );
}


/*
=====================================================
 PERMITIR APENAS POST
=====================================================
*/

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    responder([
        'sucesso' => false,
        'mensagem' => 'Método não permitido.'
    ], 405);
}


/*
=====================================================
 TOKEN
=====================================================
*/

$accessToken = $mercadoPagoAccessToken ?? '';

if (
    !is_string($accessToken) ||
    trim($accessToken) === ''
) {

    error_log(
        '[HORA DO ACAI] Access Token do Mercado Pago não configurado.'
    );

    responder([
        'sucesso' => false,
        'mensagem' => 'Pagamento PIX temporariamente indisponível.'
    ], 500);
}


/*
=====================================================
 RECEBER JSON
=====================================================
*/

$conteudo = file_get_contents('php://input');

$dados = json_decode($conteudo, true);


if (
    json_last_error() !== JSON_ERROR_NONE ||
    !is_array($dados)
) {

    responder([
        'sucesso' => false,
        'mensagem' => 'Dados inválidos.'
    ], 400);
}


/*
=====================================================
 PEDIDO ID
=====================================================
*/

$pedidoId = isset($dados['pedido_id'])
    ? (int) $dados['pedido_id']
    : 0;


if ($pedidoId <= 0) {

    responder([
        'sucesso' => false,
        'mensagem' => 'Pedido inválido.'
    ], 422);
}


/*
=====================================================
 BUSCAR PEDIDO
=====================================================
*/

try {

    $stmt = $pdo->prepare("
        SELECT
            p.id,
            p.total,
            p.status,
            p.metodo_pagamento,
            p.status_pagamento,
            p.pagamento_id,

            c.id AS cliente_id,
            c.nome AS cliente_nome,
            c.email AS cliente_email

        FROM pedidos p

        INNER JOIN clientes c
            ON c.id = p.cliente_id

        WHERE p.id = :pedido_id

        LIMIT 1
    ");


    $stmt->execute([
        ':pedido_id' => $pedidoId
    ]);


    $pedido = $stmt->fetch(PDO::FETCH_ASSOC);


    if (!$pedido) {

        responder([
            'sucesso' => false,
            'mensagem' => 'Pedido não encontrado.'
        ], 404);
    }


    /*
    =====================================================
     VERIFICAR MÉTODO DE PAGAMENTO
    =====================================================
    */

    if ($pedido['metodo_pagamento'] !== 'pix') {

        responder([
            'sucesso' => false,
            'mensagem' => 'Este pedido não foi configurado para pagamento via PIX.'
        ], 422);
    }


    /*
    =====================================================
     VERIFICAR STATUS
    =====================================================
    */

    if ($pedido['status_pagamento'] === 'pago') {

        responder([
            'sucesso' => true,
            'pago' => true,
            'payment_id' => $pedido['pagamento_id'],
            'mensagem' => 'Este pedido já foi pago.'
        ]);
    }


    /*
    =====================================================
     VALIDAR E-MAIL
    =====================================================
    */

    $email = trim((string) $pedido['cliente_email']);


    if (
        $email === '' ||
        !filter_var($email, FILTER_VALIDATE_EMAIL)
    ) {

        responder([
            'sucesso' => false,
            'mensagem' => 'O cliente precisa possuir um e-mail válido para gerar o PIX.'
        ], 422);
    }


    /*
    =====================================================
     TOTAL
     
     O valor vem diretamente do banco.
    =====================================================
    */

    $valorTotal = round(
        (float) $pedido['total'],
        2
    );


    if ($valorTotal <= 0) {

        responder([
            'sucesso' => false,
            'mensagem' => 'Valor do pedido inválido.'
        ], 422);
    }


    /*
    =====================================================
     SE JÁ EXISTE PAGAMENTO
     
     Não criar outro pagamento.
    =====================================================
    */

    if (
        !empty($pedido['pagamento_id'])
    ) {

        $paymentId =
            (string) $pedido['pagamento_id'];


        /*
        -------------------------------------------------
         CONSULTAR PAGAMENTO EXISTENTE
        -------------------------------------------------
        */

        $ch = curl_init(
            'https://api.mercadopago.com/v1/payments/' .
            rawurlencode($paymentId)
        );


        curl_setopt_array($ch, [

            CURLOPT_RETURNTRANSFER => true,

            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' .
                $accessToken,

                'Content-Type: application/json'
            ],

            CURLOPT_CONNECTTIMEOUT => 10,

            CURLOPT_TIMEOUT => 30
        ]);


        $respostaApi = curl_exec($ch);

        $erroCurl = curl_error($ch);

        $httpCode =
            (int) curl_getinfo(
                $ch,
                CURLINFO_HTTP_CODE
            );


        curl_close($ch);


        if (
            $respostaApi !== false &&
            $erroCurl === '' &&
            $httpCode >= 200 &&
            $httpCode < 300
        ) {

            $pagamento =
                json_decode(
                    $respostaApi,
                    true
                );


            if (is_array($pagamento)) {

                $statusPagamento =
                    $pagamento['status'] ?? 'pending';


                /*
                -----------------------------------------
                 JÁ PAGO
                -----------------------------------------
                */

                if (
                    $statusPagamento === 'approved'
                ) {

                    $stmtUpdate =
                        $pdo->prepare("
                            UPDATE pedidos

                            SET
                                status_pagamento = 'pago'

                            WHERE id = :pedido_id
                        ");


                    $stmtUpdate->execute([
                        ':pedido_id' => $pedidoId
                    ]);


                    responder([
                        'sucesso' => true,
                        'pago' => true,
                        'payment_id' => $paymentId,
                        'status' => 'approved',
                        'mensagem' => 'Pagamento confirmado.'
                    ]);
                }


                /*
                -----------------------------------------
                 QR CODE EXISTENTE
                -----------------------------------------
                */

                $transactionData =
                    $pagamento[
                        'point_of_interaction'
                    ]['transaction_data']
                    ?? [];


                $qrCode =
                    $transactionData['qr_code']
                    ?? '';


                $qrCodeBase64 =
                    $transactionData['qr_code_base64']
                    ?? '';


                if (
                    $qrCode !== '' ||
                    $qrCodeBase64 !== ''
                ) {

                    responder([
                        'sucesso' => true,
                        'pago' => false,
                        'payment_id' => $paymentId,
                        'status' => $statusPagamento,
                        'qr_code' => $qrCode,
                        'qr_code_base64' => $qrCodeBase64,
                        'mensagem' => 'PIX recuperado com sucesso.'
                    ]);
                }
            }
        }


        /*
        -------------------------------------------------
         Se não conseguimos recuperar o pagamento,
         não criamos outro automaticamente.
        -------------------------------------------------
        */

        error_log(
            '[HORA DO ACAI] Não foi possível recuperar o pagamento ' .
            $paymentId .
            ' do pedido ' .
            $pedidoId .
            '. HTTP: ' .
            $httpCode .
            '. CURL: ' .
            $erroCurl
        );


        responder([
            'sucesso' => false,
            'mensagem' => 'Já existe um pagamento associado a este pedido. Não foi criado outro PIX.'
        ], 409);
    }


    /*
    =====================================================
     CRIAR PAGAMENTO PIX
    =====================================================
    */

    $payload = [

        'transaction_amount' =>
            $valorTotal,

        'description' =>
            'Hora do Açaí - Pedido #' .
            $pedidoId,

        'payment_method_id' =>
            'pix',

        /*
        -------------------------------------------------
         Vincula Mercado Pago ao pedido interno.
        -------------------------------------------------
        */

        'external_reference' =>
            (string) $pedidoId,

        'payer' => [

            'email' =>
                $email,

            'first_name' =>
                (string) $pedido['cliente_nome']
        ]
    ];


    /*
    =====================================================
     UUID DE IDEMPOTÊNCIA
    =====================================================
    */

    $idempotencyKey =
        gerarUuidV4();


    /*
    =====================================================
     CURL
    =====================================================
    */

    $ch = curl_init(
        'https://api.mercadopago.com/v1/payments'
    );


    curl_setopt_array($ch, [

        CURLOPT_RETURNTRANSFER => true,

        CURLOPT_POST => true,

        CURLOPT_POSTFIELDS =>
            json_encode(
                $payload,
                JSON_UNESCAPED_UNICODE |
                JSON_UNESCAPED_SLASHES
            ),

        CURLOPT_HTTPHEADER => [

            'Authorization: Bearer ' .
            $accessToken,

            'Content-Type: application/json',

            'X-Idempotency-Key: ' .
            $idempotencyKey
        ],

        CURLOPT_CONNECTTIMEOUT => 10,

        CURLOPT_TIMEOUT => 30
    ]);


    $respostaApi = curl_exec($ch);

    $erroCurl = curl_error($ch);

    $httpCode =
        (int) curl_getinfo(
            $ch,
            CURLINFO_HTTP_CODE
        );


    curl_close($ch);


    /*
    =====================================================
     ERRO DE CURL
    =====================================================
    */

    if ($respostaApi === false) {

        error_log(
            '[HORA DO ACAI] Erro CURL Mercado Pago: ' .
            $erroCurl
        );


        responder([
            'sucesso' => false,
            'mensagem' => 'Não foi possível comunicar com o Mercado Pago.'
        ], 502);
    }


    /*
    =====================================================
     DECODIFICAR RESPOSTA
    =====================================================
    */

    $respostaJson =
        json_decode(
            $respostaApi,
            true
        );


    /*
    =====================================================
     VERIFICAR RESPOSTA
    =====================================================
    */

    if (
        $httpCode < 200 ||
        $httpCode >= 300 ||
        !is_array($respostaJson)
    ) {

        error_log(
            '[HORA DO ACAI] Erro Mercado Pago. ' .
            'HTTP: ' .
            $httpCode .
            ' Resposta: ' .
            $respostaApi
        );


        responder([
            'sucesso' => false,
            'mensagem' => 'O Mercado Pago não conseguiu criar o PIX.'
        ], 502);
    }


    /*
    =====================================================
     PAYMENT ID
    =====================================================
    */

    $pagamentoId =
        isset($respostaJson['id'])
            ? (string) $respostaJson['id']
            : '';


    if ($pagamentoId === '') {

        error_log(
            '[HORA DO ACAI] Mercado Pago retornou pagamento sem ID.'
        );


        responder([
            'sucesso' => false,
            'mensagem' => 'Resposta inválida do Mercado Pago.'
        ], 502);
    }


    /*
    =====================================================
     STATUS
    =====================================================
    */

    $statusPagamento =
        $respostaJson['status']
        ?? 'pending';


    /*
    =====================================================
     QR CODE
    =====================================================
    */

    $transactionData =
        $respostaJson[
            'point_of_interaction'
        ]['transaction_data']
        ?? [];


    $qrCode =
        $transactionData['qr_code']
        ?? '';


    $qrCodeBase64 =
        $transactionData['qr_code_base64']
        ?? '';


    if (
        $qrCode === '' &&
        $qrCodeBase64 === ''
    ) {

        error_log(
            '[HORA DO ACAI] Mercado Pago não retornou QR Code. ' .
            'Pagamento: ' .
            $pagamentoId
        );


        responder([
            'sucesso' => false,
            'mensagem' => 'O PIX foi criado, mas o QR Code não foi retornado.'
        ], 502);
    }


    /*
    =====================================================
     SALVAR PAYMENT ID
    =====================================================
    */

    $stmtUpdate = $pdo->prepare("
        UPDATE pedidos

        SET
            pagamento_id = :pagamento_id

        WHERE id = :pedido_id
    ");


    $stmtUpdate->execute([
        ':pagamento_id' => $pagamentoId,
        ':pedido_id' => $pedidoId
    ]);


    /*
    =====================================================
     RESPOSTA FINAL
    =====================================================
    */

    responder([

        'sucesso' => true,

        'pago' => false,

        'pedido_id' =>
            $pedidoId,

        'payment_id' =>
            $pagamentoId,

        'status' =>
            $statusPagamento,

        'qr_code' =>
            $qrCode,

        'qr_code_base64' =>
            $qrCodeBase64,

        'mensagem' =>
            'PIX criado com sucesso.'

    ], 201);


} catch (Throwable $e) {

    /*
    =====================================================
     LOG INTERNO
    =====================================================
    */

    error_log(
        '[HORA DO ACAI] Erro em gerar_pix.php: ' .
        $e->getMessage()
    );


    responder([
        'sucesso' => false,
        'mensagem' => 'Não foi possível gerar o PIX. Tente novamente.'
    ], 500);
}

