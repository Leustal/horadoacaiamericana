<?php
// public_html/api/gerar_pix.php

header('Content-Type: application/json; charset=utf-8');

// ⚠️ COLE AQUI O SEU ACCESS TOKEN DO MERCADO PAGO ⚠️
// (Você pega essa chave no painel do Mercado Pago em "Seu Negócio" > "Credenciais")
$accessToken = "APP_USR-6104372763510370-082520-3bb53298a67b6e5c5daf02a5d0e7c0db-533345983"; 

$dados = json_decode(file_get_contents('php://input'), true);
$valorTotal = $dados['total'] ?? 0;
$clienteNome = $dados['cliente_nome'] ?? 'Cliente Açaí';
$clienteEmail = $dados['cliente_email'] ?? 'cliente@email.com';

if ($valorTotal <= 0) {
    http_response_code(400);
    echo json_encode(['sucesso' => false, 'mensagem' => 'Valor inválido para o PIX.']);
    exit;
}

// Dados da cobrança para a API do Mercado Pago
$payload = [
    "transaction_amount" => (float) $valorTotal,
    "description" => "Pedido Hora do Açaí",
    "payment_method_id" => "pix",
    "payer" => [
        "email" => $clienteEmail,
        "first_name" => $clienteNome
    ]
];

$ch = curl_init('https://api.mercadopago.com/v1/payments');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Authorization: Bearer " . $accessToken,
    "Content-Type: application/json",
    "X-Idempotency-Key: " . uniqid()
]);

$respostaApi = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode >= 200 && $httpCode < 300) {
    $respostaJson = json_decode($respostaApi, true);
    
    // Extrai os dados do Pix retornados pelo Mercado Pago
    $pontoDeCopiaECola = $respostaJson['point_of_interaction']['transaction_data']['qr_code'] ?? '';
    $qrCodeBase64 = $respostaJson['point_of_interaction']['transaction_data']['qr_code_base64'] ?? '';
    $pagamentoId = $respostaJson['id'] ?? '';

    echo json_encode([
        'sucesso' => true,
        'payment_id' => $pagamentoId,
        'qr_code' => $pontoDeCopiaECola,
        'qr_code_base64' => $qrCodeBase64
    ]);
} else {
    http_response_code(500);
    echo json_encode([
        'sucesso' => false, 
        'mensagem' => 'Erro ao comunicar com o Mercado Pago.',
        'detalhes' => json_decode($respostaApi, true)
    ]);
}
?>