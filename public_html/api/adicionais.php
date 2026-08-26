<?php
// public_html/api/adicionais.php

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../conexao.php';

try {
    $stmt = $pdo->query("SELECT id, nome, preco FROM adicionais");
    $adicionaisBanco = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $adicionaisModificados = [];
    foreach ($adicionaisBanco as $add) {
        $adicionaisModificados[] = [
            'id' => $add['id'],
            'nome' => $add['nome'],
            'preco' => 0.01 // Força para 1 centavo nos testes
        ];
    }

    echo json_encode($adicionaisModificados, JSON_UNESCAPED_UNICODE);
} catch (\PDOException $e) {
    http_response_code(500);
    echo json_encode(['erro' => $e->getMessage()]);
}
?>