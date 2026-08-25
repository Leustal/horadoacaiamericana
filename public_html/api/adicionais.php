<?php
// public_html/api/adicionais.php

// 1. Cabeçalhos para retornar JSON
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');

// 2. Chama a conexão (voltando duas pastas para achar o conexao.php na raiz)
require_once __DIR__ . '/../../conexao.php';

try {
    // 3. Busca apenas os adicionais que estão ativos e ordena por tipo
    $sql = "SELECT id, nome, preco, tipo FROM adicionais WHERE ativo = 1 ORDER BY tipo, nome";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    
    // 4. Pega todos os resultados
    $adicionais = $stmt->fetchAll();
    
    // 5. Retorna para o front-end
    echo json_encode($adicionais, JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    // Retorna erro 500 caso o banco falhe
    http_response_code(500);
    echo json_encode(['erro' => 'Erro ao buscar adicionais: ' . $e->getMessage()]);
}
?>