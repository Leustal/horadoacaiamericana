<?php
// public_html/api/pedidos.php

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../conexao.php';

// Recebe os dados em formato JSON enviados pelo JavaScript
$dados = json_decode(file_get_contents('php://input'), true);

if (!$dados) {
    http_response_code(400);
    echo json_encode(['sucesso' => false, 'mensagem' => 'Dados inválidos']);
    exit;
}

$nome = $dados['cliente_nome'] ?? '';
$telefone = $dados['cliente_telefone'] ?? '';
$endereco = $dados['endereco'] ?? '';
$pagamento = $dados['forma_pagamento'] ?? '';
$total = $dados['total'] ?? 0.0;
$itens = json_encode($dados['itens'] ?? [], JSON_UNESCAPED_UNICODE);

if (empty($nome) || empty($telefone) || empty($endereco)) {
    http_response_code(422);
    echo json_encode(['sucesso' => false, 'mensagem' => 'Campos obrigatórios não preenchidos']);
    exit;
}

try {
    $stmt = $pdo->prepare("
        INSERT INTO pedidos (cliente_nome, cliente_telefone, endereco, forma_pagamento, total, itens_detalhes)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([$nome, $telefone, $endereco, $pagamento, $total, $itens]);

    $pedidoId = $pdo->lastInsertId();

    echo json_encode([
        'sucesso' => true,
        'pedido_id' => $pedidoId,
        'mensagem' => 'Pedido registrado com sucesso!'
    ]);
} catch (\PDOException $e) {
    http_response_code(500);
    echo json_encode(['sucesso' => false, 'mensagem' => 'Erro ao salvar pedido: ' . $e->getMessage()]);
}
?>